<?php
/**
 * Giá và funding các hợp đồng vàng trên Bybit và Hyperliquid cho khung "Funding 3 sàn"
 * (Binance do trang tự lấy). Đi qua host để trình duyệt không bị chặn và gom về một định dạng.
 * Tab XAUUSDT / XAGUSDT cũng dùng file này: lịch sử funding Bybit của một mã (kèm mark lúc chốt
 * từng kỳ, tới hơn một năm), và giá/funding Bybit dự phòng khi WebSocket của Bybit chưa kết nối
 * được. Tab Dầu WTI dùng giá/funding và lịch sử funding Bybit CLUSDT.
 *
 * - Bybit: XAUUSDT, PAXGUSDT, CLUSDT, XAGUSDT (REST v5, funding theo chu kỳ của từng mã: vàng thường 8 giờ, dầu 4 giờ)
 * - Hyperliquid: xyz:GOLD (thị trường HIP-3 của TradeXYZ), PAXG (funding mỗi giờ)
 *
 * ?days=N lấy thêm lịch sử funding N ngày của mọi mã (tối đa 60).
 * ?symbol=XAUUSDT&start=<mili giây> lấy lịch sử funding Bybit của một mã từ mốc start (tối đa 400 ngày),
 * lưu dần trong bybit_hist_<MÃ>.json: lần đầu lấy hết, sau đó chỉ lấy thêm các kỳ mới. Thêm &px=1 để có cả
 * giá khớp và index theo giờ (lưu trong bybit_px_<MÃ>.json), dùng so giá perp với giá vàng.
 * ?oi=1&ex=binance|bybit&symbol=XAUUSDT&start=<mili giây> lấy open interest và tỷ lệ tài khoản Long theo nến 4 giờ
 * (lưu trong oi_<sàn>_<MÃ>.json; Binance chỉ giữ 30 ngày nên cron alert-check.php lưu dần mỗi 6 giờ).
 * Các hàm nằm trong ex-lib.php.
 * ?debug=1 xem dữ liệu thô từng lần gọi.
 * Kết quả được cache: giá/funding hiện tại 30 giây, lịch sử 30 phút (hoặc tới kỳ chốt kế tiếp).
 */

require __DIR__ . '/ex-lib.php';

$DEBUG = isset($_GET['debug']);
$debug = [];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$days = isset($_GET['days']) ? max(1, min(60, (int) $_GET['days'])) : 0;
$symbol = isset($_GET['symbol']) ? strtoupper((string) $_GET['symbol']) : '';
$oiEx = isset($_GET['oi']) ? (isset($_GET['ex']) ? (string) $_GET['ex'] : '') : null;
if ($oiEx !== null && (!isset($OI_SOURCES[$oiEx]) || !in_array($symbol, $OI_SOURCES[$oiEx], true))) {
    http_response_code(400);
    echo json_encode(['errors' => ['Không có open interest cho ' . $oiEx . ' ' . $symbol . '.']], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($oiEx === null && $symbol !== '' && !in_array($symbol, $BYBIT_SYMBOLS, true)) {
    http_response_code(400);
    echo json_encode(['errors' => ['Mã ' . $symbol . ' không có trong danh sách Bybit của trang.']], JSON_UNESCAPED_UNICODE);
    exit;
}

// Khoá để nhiều người mở trang cùng lúc không cùng gọi các sàn
$lock = @fopen(EX_LOCK_FILE, 'c');
if ($lock) flock($lock, LOCK_EX);

$live = ex_read(EX_LIVE_FILE);
if ($DEBUG || !isset($live['at']) || fx_now_ms() - $live['at'] > EX_LIVE_TTL_MS) {
    $live = ex_refresh_live($debug);
    ex_write(EX_LIVE_FILE, $live);
}

$hist = null;
if ($symbol !== '') {
    // Lịch sử một mã từ mốc start (mặc định 14 ngày), tối đa EX_STORE_MAX_DAYS ngày.
    // Lần đầu lấy cả năm có thể mất vài chục lần gọi sàn nên nới giới hạn thời gian chạy.
    @set_time_limit(90);
    $now = fx_now_ms();
    $start = isset($_GET['start']) && is_numeric($_GET['start']) ? (int) $_GET['start'] : $now - 14 * 86400000;
    $start = max($now - EX_STORE_MAX_DAYS * 86400000, min($now, $start));
}
if ($oiEx !== null) {
    // ?oi=1&ex=binance|bybit&symbol=...&start=...: open interest và tỷ lệ Long theo nến 4 giờ
    $errors = [];
    $store = oi_store($oiEx, $symbol, $start, $DEBUG, $debug, $errors);
    $hist = ['history' => null, 'errors' => $errors, 'oi' => $store ? $store['rows'] : null, 'oiFrom' => $store ? $store['from'] : null];
} elseif ($symbol !== '') {
    $next = isset($live['live']['bybit:' . $symbol]['next']) ? (int) $live['live']['bybit:' . $symbol]['next'] : 0;
    $errors = [];
    $store = bybit_store($symbol, $start, $next > $now ? $next : null, $DEBUG, $debug, $errors);
    $hist = [
        'history' => $store ? ['bybit:' . $symbol => $store['rows']] : [],
        'from' => $store ? $store['from'] : null,
        'errors' => $errors,
    ];
    // ?px=1: thêm giá khớp và index theo giờ để trang so giá perp với giá vàng
    if (isset($_GET['px'])) {
        $pxErrors = [];
        $px = bybit_px_store($symbol, $start, $next > $now ? $next : null, $DEBUG, $debug, $pxErrors);
        $hist['prices'] = $px ? ['bybit:' . $symbol => $px['rows']] : [];
        $hist['pxFrom'] = $px ? $px['from'] : null;
        $hist['pxErrors'] = $pxErrors;
    }
} elseif ($days) {
    $cache = ex_read(EX_HIST_FILE);
    $key = (string) $days;
    $now = fx_now_ms();
    // Hết hạn sau 30 phút, hoặc sớm hơn khi Bybit vừa chốt một kỳ funding (để có ngay kỳ mới)
    $stale = !isset($cache[$key]['at']) || $now - $cache[$key]['at'] > EX_HIST_TTL_MS
        || (!empty($cache[$key]['due']) && $now >= $cache[$key]['due'] + 30000);
    if ($DEBUG || $stale) {
        $cache[$key] = ex_refresh_hist($days, $debug);
        $due = null;
        foreach ($live['live'] as $k => $v) {
            if (strpos($k, 'bybit:') === 0 && !empty($v['next']) && $v['next'] > $now) $due = $due === null ? $v['next'] : min($due, $v['next']);
        }
        $cache[$key]['due'] = $due;
        ex_write(EX_HIST_FILE, $cache);
    }
    $hist = $cache[$key];
}

if ($lock) {
    flock($lock, LOCK_UN);
    fclose($lock);
}

$out = [
    'fetchedAt' => $live['at'],
    'live' => $live['live'],
    'history' => $hist ? $hist['history'] : null,
    'days' => $days ?: null,
    'errors' => array_merge($live['errors'], $hist ? $hist['errors'] : []),
    'histErrors' => $hist ? $hist['errors'] : [],
];
if ($oiEx !== null) {
    $out['symbol'] = $symbol;
    $out['oi'] = $hist['oi']; // [thời điểm, open interest, tỷ lệ tài khoản Long 0..1]
    $out['oiFrom'] = $hist['oiFrom'];
} elseif ($symbol !== '') {
    $out['symbol'] = $symbol;
    $out['from'] = $hist['from']; // kho có đủ dữ liệu từ mốc này (mã niêm yết sau đó thì kỳ đầu tiên muộn hơn)
    if (isset($hist['prices'])) {
        $out['prices'] = $hist['prices']; // [giờ mở nến, giá khớp đóng nến, index đóng nến]
        $out['pxFrom'] = $hist['pxFrom'];
        $out['pxErrors'] = $hist['pxErrors'];
    }
}
if ($DEBUG) $out['debug'] = $debug;

echo json_encode($out, $DEBUG ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_UNICODE);
