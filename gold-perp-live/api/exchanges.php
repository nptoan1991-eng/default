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
 * lưu dần trong bybit_hist_<MÃ>.json: lần đầu lấy hết, sau đó chỉ lấy thêm các kỳ mới.
 * ?debug=1 xem dữ liệu thô từng lần gọi.
 * Kết quả được cache: giá/funding hiện tại 30 giây, lịch sử 30 phút (hoặc tới kỳ chốt kế tiếp).
 */

require __DIR__ . '/fx-lib.php'; // dùng fx_fetch, fx_now_ms

define('EX_BYBIT', 'https://api.bybit.com');
define('EX_HL', 'https://api.hyperliquid.xyz/info');
define('EX_LIVE_FILE', __DIR__ . '/exchanges_live.json');
define('EX_HIST_FILE', __DIR__ . '/exchanges_hist.json');
define('EX_LOCK_FILE', __DIR__ . '/exchanges.lock');
define('EX_LIVE_TTL_MS', 30 * 1000);
define('EX_HIST_TTL_MS', 30 * 60 * 1000);
define('EX_STORE_MAX_DAYS', 400); // kho lịch sử từng mã giữ tối đa chừng này ngày

$BYBIT_SYMBOLS = ['XAUUSDT', 'PAXGUSDT', 'CLUSDT', 'XAGUSDT']; // CLUSDT: tab Dầu WTI, XAGUSDT: tab XAGUSDT
$HL_COINS = ['xyz:GOLD' => 'xyz', 'PAXG' => '']; // tên hợp đồng => dex (HIP-3), '' là dex chính

$DEBUG = isset($_GET['debug']);
$debug = [];

function ex_num($v) {
    return is_numeric($v) ? (float) $v : null;
}

function ex_post($url, $body, &$log) {
    $payload = json_encode($body);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, FX_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FX_VERIFY_SSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FX_VERIFY_SSL ? 2 : 0);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) $log['curl_error'] = curl_errno($ch) . ': ' . curl_error($ch);
        if (PHP_VERSION_ID < 80000) curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => FX_TIMEOUT, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) $code = (int) $m[1];
    }
    $log['http_code'] = $code;
    $log['preview'] = $res === false ? '' : substr($res, 0, 800);
    return ($res !== false && $code > 0 && $code < 400) ? $res : false;
}

// Mỗi lần gọi ghi một mục debug riêng
function ex_log(&$debug, $name) {
    $debug[$name] = [];
    return $name;
}

function bybit_live($symbol, &$debug) {
    $k = ex_log($debug, 'bybit_live_' . $symbol);
    $raw = fx_fetch(EX_BYBIT . '/v5/market/tickers?category=linear&symbol=' . $symbol, $debug[$k]);
    $json = $raw !== false ? json_decode($raw, true) : null;
    $t = isset($json['result']['list'][0]) ? $json['result']['list'][0] : null;
    if (!$t || ex_num($t['fundingRate']) === null) return null;
    $interval = isset($t['fundingIntervalHour']) ? ex_num($t['fundingIntervalHour']) : null;
    return [
        'price' => ex_num($t['lastPrice']),
        'mark' => isset($t['markPrice']) ? ex_num($t['markPrice']) : null,
        'rate' => ex_num($t['fundingRate']),
        'intervalH' => $interval ?: 8,
        'next' => isset($t['nextFundingTime']) ? (int) $t['nextFundingTime'] : null,
    ];
}

// Hyperliquid trả danh sách hợp đồng (meta.universe) và số liệu theo cùng thứ tự (assetCtxs)
function hl_ctxs($dex, &$debug) {
    $k = ex_log($debug, 'hyperliquid_ctx_' . ($dex === '' ? 'main' : $dex));
    $body = ['type' => 'metaAndAssetCtxs'];
    if ($dex !== '') $body['dex'] = $dex;
    $raw = ex_post(EX_HL, $body, $debug[$k]);
    $json = $raw !== false ? json_decode($raw, true) : null;
    return (is_array($json) && isset($json[0]['universe'], $json[1]) && is_array($json[1])) ? $json : null;
}

function hl_live($coin, $dex, $ctxs) {
    if (!$ctxs) return null;
    $short = $dex !== '' && strpos($coin, $dex . ':') === 0 ? substr($coin, strlen($dex) + 1) : $coin;
    foreach ($ctxs[0]['universe'] as $i => $u) {
        $name = isset($u['name']) ? $u['name'] : '';
        if ($name !== $coin && $name !== $short) continue;
        $c = isset($ctxs[1][$i]) ? $ctxs[1][$i] : null;
        if (!$c || !isset($c['funding']) || ex_num($c['funding']) === null) return null;
        $mark = isset($c['markPx']) ? ex_num($c['markPx']) : null;
        $mid = isset($c['midPx']) ? ex_num($c['midPx']) : null;
        return [
            'price' => $mid ?: $mark,
            'mark' => $mark,
            'rate' => ex_num($c['funding']),
            'intervalH' => 1,
            'next' => (int) (ceil(fx_now_ms() / 3600000) * 3600000),
        ];
    }
    return null;
}

// Mark price Bybit (nến $minutes phút, tối đa 1000 nến mỗi lần, mới nhất trước):
// thời điểm mở nến => giá mở nến, tức mark lúc chốt funding đúng thời điểm đó
function bybit_marks($symbol, $start, $end, $minutes, &$log) {
    $out = [];
    $pages = min(30, (int) ceil(($end - $start) / ($minutes * 60000 * 1000)) + 1);
    for ($page = 0; $page < $pages; $page++) {
        $raw = fx_fetch(EX_BYBIT . '/v5/market/mark-price-kline?category=linear&symbol=' . $symbol . '&interval=' . $minutes . '&start=' . $start . '&end=' . $end . '&limit=1000', $log);
        $json = $raw !== false ? json_decode($raw, true) : null;
        $list = isset($json['result']['list']) && is_array($json['result']['list']) ? $json['result']['list'] : [];
        if (!$list) break;
        $oldest = PHP_INT_MAX;
        foreach ($list as $r) {
            if (!isset($r[0], $r[1]) || !is_numeric($r[1])) continue;
            $t = (int) $r[0];
            $out[$t] = (float) $r[1];
            $oldest = min($oldest, $t);
        }
        if (count($list) < 1000 || $oldest <= $start) break;
        $end = $oldest - 1;
    }
    return $out;
}

// Funding rate Bybit trong [start, end]: tối đa 200 dòng mỗi lần, mới nhất trước, lùi dần endTime.
// Trả ['rates' => [thời điểm => rate], 'from' => mốc đã lấy đủ dữ liệu tới]; lỗi ngay lần gọi đầu thì trả null.
function bybit_funding($symbol, $start, $end, &$log) {
    $rates = [];
    $from = null;
    for ($page = 0; $page < 60; $page++) {
        $raw = fx_fetch(EX_BYBIT . '/v5/market/funding/history?category=linear&symbol=' . $symbol . '&startTime=' . $start . '&endTime=' . $end . '&limit=200', $log);
        $json = $raw !== false ? json_decode($raw, true) : null;
        if (!isset($json['result']['list']) || !is_array($json['result']['list'])) {
            if (!$page) return null;
            break; // lỗi giữa chừng: chỉ chắc có đủ từ kỳ cũ nhất đã lấy được
        }
        $list = $json['result']['list'];
        $oldest = PHP_INT_MAX;
        foreach ($list as $r) {
            if (!isset($r['fundingRateTimestamp'], $r['fundingRate']) || !is_numeric($r['fundingRate'])) continue;
            $t = (int) $r['fundingRateTimestamp'];
            $rates[$t] = (float) $r['fundingRate'];
            $oldest = min($oldest, $t);
        }
        if (count($list) < 200 || $oldest <= $start) {
            $from = $start;
            break;
        }
        $end = $oldest - 1;
    }
    ksort($rates);
    if ($from === null) $from = $rates ? key($rates) : $end + 1;
    return ['rates' => $rates, 'from' => $from];
}

// Gắn mark lúc chốt cho từng kỳ: [thời điểm, rate, mark]; thiếu mark thì phần tử thứ ba là null.
// Khi mọi kỳ rơi đúng mốc 4 giờ thì dùng nến 4 giờ (ít lần gọi hơn), không thì nến 1 giờ.
function bybit_attach_marks($symbol, $rates, &$log) {
    if (!$rates) return [];
    $step = 14400000;
    foreach ($rates as $t => $rate) {
        if ((int) (floor($t / 3600000) * 3600000) % 14400000 !== 0) {
            $step = 3600000;
            break;
        }
    }
    $ts = array_keys($rates);
    $marks = bybit_marks($symbol, $ts[0] - $step, $ts[count($ts) - 1] + 1, $step / 60000, $log);
    $rows = [];
    foreach ($rates as $t => $rate) {
        $h = (int) (floor($t / $step) * $step);
        $rows[] = [$t, $rate, isset($marks[$h]) ? $marks[$h] : null];
    }
    return $rows;
}

// Lịch sử funding Bybit từ $start tới hiện tại, dùng cho ?days=N
function bybit_hist($symbol, $start, &$debug) {
    $k = ex_log($debug, 'bybit_hist_' . $symbol);
    $f = bybit_funding($symbol, $start, fx_now_ms(), $debug[$k]);
    if (!$f || !$f['rates']) return null;
    $m = ex_log($debug, 'bybit_mark_' . $symbol);
    return bybit_attach_marks($symbol, $f['rates'], $debug[$m]);
}

// Kho lịch sử funding Bybit của một mã (bybit_hist_<MÃ>.json). Lần đầu lấy từ $start tới hiện tại,
// sau đó chỉ lấy thêm các kỳ mới khi kho đã cũ, và lấy lùi thêm khi trang xin mốc sớm hơn mốc đã có.
// $due là kỳ chốt kế tiếp của mã (để có ngay kỳ vừa chốt). Trả ['rows' => các kỳ từ $start, 'from' => mốc kho có đủ].
function bybit_store($symbol, $start, $due, $force, &$debug, &$errors) {
    $file = __DIR__ . '/bybit_hist_' . $symbol . '.json';
    $now = fx_now_ms();
    $st = ex_read($file);
    $map = [];
    if (isset($st['rows']) && is_array($st['rows'])) {
        foreach ($st['rows'] as $r) {
            if (is_array($r) && isset($r[0], $r[1])) $map[(int) $r[0]] = [(int) $r[0], (float) $r[1], isset($r[2]) ? (float) $r[2] : null];
        }
    }
    $from = isset($st['from']) ? (int) $st['from'] : null;
    $at = isset($st['at']) ? (int) $st['at'] : 0;
    $failAt = isset($st['failAt']) ? (int) $st['failAt'] : 0;
    // Vừa gọi lỗi trong 1 phút qua thì chưa gọi lại, để host bị Bybit chặn không gọi mãi mỗi lần mở trang
    $failed = !$force && $now - $failAt < 60000;
    $changed = false;
    $k = ex_log($debug, 'bybit_store_' . $symbol);

    if (!$failed && ($from === null || $start < $from)) {
        // Lần đầu lấy hết tới hiện tại; kho đã có thì chỉ lấy lùi phần còn thiếu phía trước
        $first = $from === null;
        $f = bybit_funding($symbol, $start, $first ? $now : $from - 1, $debug[$k]);
        if ($f === null) {
            $failed = true;
        } else {
            foreach (bybit_attach_marks($symbol, $f['rates'], $debug[$k]) as $r) $map[$r[0]] = $r;
            $from = $first ? $f['from'] : min($from, $f['from']);
            if ($first) $at = $now;
            $changed = true;
        }
    }

    // Lấy thêm các kỳ mới sau 30 phút, hoặc ngay khi vừa qua kỳ chốt đã hẹn
    $stDue = isset($st['due']) ? (int) $st['due'] : 0;
    if ($from !== null && !$failed && ($force || $now - $at > EX_HIST_TTL_MS || ($stDue && $now >= $stDue + 30000))) {
        ksort($map);
        end($map);
        $since = $map ? key($map) + 1 : $from;
        // Lấy lại cả các kỳ trong 3 ngày gần đây còn thiếu mark lúc chốt
        foreach ($map as $t => $r) {
            if ($r[2] === null && $t > $now - 3 * 86400000) {
                $since = min($since, $t);
                break;
            }
        }
        $f = bybit_funding($symbol, $since, $now, $debug[$k]);
        if ($f === null) {
            $failed = true;
        } else {
            foreach (bybit_attach_marks($symbol, $f['rates'], $debug[$k]) as $r) $map[$r[0]] = $r;
            $at = $now;
            $changed = true;
        }
    }

    if ($failed && $now - $failAt >= 60000) {
        $failAt = $now;
        $changed = true;
    }
    if ($changed) {
        // Bỏ các kỳ quá cũ để file không lớn dần mãi
        $cut = $now - EX_STORE_MAX_DAYS * 86400000;
        foreach ($map as $t => $r) {
            if ($t < $cut) unset($map[$t]);
        }
        if ($from !== null && $from < $cut) $from = $cut;
        ksort($map);
        ex_write($file, ['from' => $from, 'at' => $at, 'due' => $due, 'failAt' => $failAt, 'rows' => array_values($map)]);
    }

    if ($failed) $errors[] = 'Bybit: không lấy được lịch sử funding ' . $symbol . ($map ? ', đang dùng dữ liệu lấy lúc trước.' : '.');
    if ($from === null) return null;
    $rows = [];
    foreach ($map as $t => $r) {
        if ($t >= $start) $rows[] = $r;
    }
    return ['rows' => $rows, 'from' => $from];
}

// Lịch sử funding Hyperliquid: mỗi giờ một dòng, tối đa 500 dòng mỗi lần
function hl_hist($coin, $start, &$debug) {
    $k = ex_log($debug, 'hyperliquid_hist_' . $coin);
    $out = [];
    $from = $start;
    for ($page = 0; $page < 10; $page++) {
        $raw = ex_post(EX_HL, ['type' => 'fundingHistory', 'coin' => $coin, 'startTime' => $from], $debug[$k]);
        $list = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($list) || !$list) break;
        $last = 0;
        foreach ($list as $r) {
            if (!isset($r['time'], $r['fundingRate'])) continue;
            $t = (int) $r['time'];
            $out[$t] = (float) $r['fundingRate'];
            $last = max($last, $t);
        }
        if (count($list) < 500 || !$last) break;
        $from = $last + 1;
    }
    ksort($out);
    return $out ? array_map(null, array_keys($out), array_values($out)) : null;
}

function ex_read($file) {
    if (!is_readable($file)) return [];
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function ex_write($file, $data) {
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, json_encode($data)) !== false) @rename($tmp, $file);
}

function ex_refresh_live(&$debug) {
    global $BYBIT_SYMBOLS, $HL_COINS;
    $live = [];
    $errors = [];
    foreach ($BYBIT_SYMBOLS as $s) {
        $v = bybit_live($s, $debug);
        if ($v) $live['bybit:' . $s] = $v;
        else $errors[] = 'Bybit: không lấy được ' . $s . '.';
    }
    $ctxByDex = [];
    foreach ($HL_COINS as $coin => $dex) {
        if (!array_key_exists($dex, $ctxByDex)) $ctxByDex[$dex] = hl_ctxs($dex, $debug);
        $v = hl_live($coin, $dex, $ctxByDex[$dex]);
        if ($v) $live['hyperliquid:' . $coin] = $v;
        else $errors[] = 'Hyperliquid: không lấy được ' . $coin . '.';
    }
    return ['at' => fx_now_ms(), 'live' => $live, 'errors' => $errors];
}

function ex_refresh_hist($days, &$debug) {
    global $BYBIT_SYMBOLS, $HL_COINS;
    $start = fx_now_ms() - $days * 86400000;
    $hist = [];
    $errors = [];
    foreach ($BYBIT_SYMBOLS as $s) {
        $v = bybit_hist($s, $start, $debug);
        if ($v) $hist['bybit:' . $s] = $v;
        else $errors[] = 'Bybit: không lấy được lịch sử funding ' . $s . '.';
    }
    foreach ($HL_COINS as $coin => $dex) {
        $v = hl_hist($coin, $start, $debug);
        if ($v) $hist['hyperliquid:' . $coin] = $v;
        else $errors[] = 'Hyperliquid: không lấy được lịch sử funding ' . $coin . '.';
    }
    return ['at' => fx_now_ms(), 'days' => $days, 'history' => $hist, 'errors' => $errors];
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$days = isset($_GET['days']) ? max(1, min(60, (int) $_GET['days'])) : 0;
$symbol = isset($_GET['symbol']) ? strtoupper((string) $_GET['symbol']) : '';
if ($symbol !== '' && !in_array($symbol, $BYBIT_SYMBOLS, true)) {
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
    // Lần đầu lấy cả năm có thể mất vài chục lần gọi Bybit nên nới giới hạn thời gian chạy.
    @set_time_limit(90);
    $now = fx_now_ms();
    $start = isset($_GET['start']) && is_numeric($_GET['start']) ? (int) $_GET['start'] : $now - 14 * 86400000;
    $start = max($now - EX_STORE_MAX_DAYS * 86400000, min($now, $start));
    $next = isset($live['live']['bybit:' . $symbol]['next']) ? (int) $live['live']['bybit:' . $symbol]['next'] : 0;
    $errors = [];
    $store = bybit_store($symbol, $start, $next > $now ? $next : null, $DEBUG, $debug, $errors);
    $hist = [
        'history' => $store ? ['bybit:' . $symbol => $store['rows']] : [],
        'from' => $store ? $store['from'] : null,
        'errors' => $errors,
    ];
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
if ($symbol !== '') {
    $out['symbol'] = $symbol;
    $out['from'] = $hist['from']; // kho có đủ dữ liệu từ mốc này (mã niêm yết sau đó thì kỳ đầu tiên muộn hơn)
}
if ($DEBUG) $out['debug'] = $debug;

echo json_encode($out, $DEBUG ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_UNICODE);
