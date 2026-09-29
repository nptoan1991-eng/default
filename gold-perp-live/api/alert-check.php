<?php
/**
 * Kiểm tra XAUUSDT − giá vàng forex và gửi thông báo qua ntfy.sh khi vượt ngưỡng.
 * Chạy bằng cron vài phút một lần nên vẫn báo khi đã tắt trang dashboard.
 *
 * - Giá XAUUSDT: giá khớp gần nhất trên Binance (host phải truy cập được Binance).
 * - Giá forex: giữa bid và ask XAU/USD của Swissquote (dùng chung cache với fx-price.php).
 * - Báo một lần khi vừa vào vùng; phải ra khỏi vùng 'gap' USDT mới báo lại.
 *   Không báo khi forex đang nghỉ. Không lấy được giá 3 lần liền thì báo lỗi một lần.
 *
 * Mỗi lần chạy khi forex đang giao dịch cũng ghi XAU − forex và PAXG − forex theo giờ
 * (spread-lib.php) để trang vẽ lịch sử.
 *
 * Cấu hình: alert-config.php (chép từ alert-config.example.php).
 * Gửi thử: php alert-check.php test, hoặc nút "Gửi thử lên điện thoại" trên trang
 * (gọi alert-check.php?test=1, trả JSON, tối đa 1 lần mỗi phút, không cần key).
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');
require __DIR__ . '/fx-lib.php';
require __DIR__ . '/spread-lib.php';

define('PRICE_URL', 'https://fapi.binance.com/fapi/v1/ticker/price?symbol=');
define('ALERT_STATE_FILE', __DIR__ . '/alert_state.json');
define('ALERT_LOCK_FILE', __DIR__ . '/alert.lock');
define('FAILS_BEFORE_NOTICE', 3);
define('TEST_COOLDOWN_MS', 60 * 1000);

$cli = PHP_SAPI === 'cli';
$test = $cli ? in_array('test', array_slice($argv, 1), true) : isset($_GET['test']);
$asJson = !$cli && $test; // nút gửi thử trên trang nhận JSON
if (!$cli) {
    header('Content-Type: ' . ($asJson ? 'application/json' : 'text/plain') . '; charset=utf-8');
    header('Cache-Control: no-store');
}

function finish($text, $ok = true, $http = 200) {
    global $cli, $asJson;
    if (!$cli && $http !== 200) http_response_code($http);
    echo $asJson ? json_encode(['ok' => $ok, 'text' => $text], JSON_UNESCAPED_UNICODE) : $text . "\n";
    exit($ok ? 0 : 1);
}

$cfgFile = __DIR__ . '/alert-config.php';
if (!is_readable($cfgFile)) {
    finish('Chưa có api/alert-config.php trên host. Chép alert-config.example.php thành alert-config.php rồi điền tên kênh ntfy.', false, 500);
}
$cfg = require $cfgFile;
$cfg = array_merge(['ntfy_server' => 'https://ntfy.sh', 'ntfy_token' => '', 'gap' => 0.5, 'cron_key' => '', 'page_url' => ''], (array) $cfg);
if (empty($cfg['ntfy_topic']) || $cfg['ntfy_topic'] === 'DOI-TEN-KENH-NAY') {
    finish('Hãy đổi ntfy_topic trong alert-config.php thành tên kênh bạn đã đăng ký trong app ntfy.', false, 500);
}
if (!is_numeric($cfg['high']) || !is_numeric($cfg['low']) || $cfg['low'] >= $cfg['high']) {
    finish('Ngưỡng trong alert-config.php không hợp lệ: low phải nhỏ hơn high.', false, 500);
}

// Kiểm tra thật qua web phải có đúng cron_key; gửi thử thì không cần nhưng bị giới hạn tần suất
if (!$cli && !$test && ($cfg['cron_key'] === '' || !isset($_GET['key']) || !hash_equals((string) $cfg['cron_key'], (string) $_GET['key']))) {
    finish('Không có quyền. Chạy bằng lệnh php, hoặc đặt cron_key trong alert-config.php và thêm ?key=... vào link.', false, 403);
}

// Link trang để bấm vào thông báo: lấy từ cấu hình, nếu trống thì dùng link trang đã ghi lại
if ($cfg['page_url'] === '') $cfg['page_url'] = (string) ($cli ? stored_page_url() : remember_page_url());

// Một lần chạy tại một thời điểm, tránh cron chạy chồng khi mạng chậm
$lock = @fopen(ALERT_LOCK_FILE, 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) finish('Lần chạy trước chưa xong, bỏ qua.', false, 409);

$state = is_readable(ALERT_STATE_FILE) ? json_decode((string) @file_get_contents(ALERT_STATE_FILE), true) : null;
$state = array_merge(['zone' => null, 'fails' => 0, 'failNotified' => false], is_array($state) ? $state : []);
function save_state($state) {
    @file_put_contents(ALERT_STATE_FILE, json_encode($state), LOCK_EX);
}

if ($test && !$cli && isset($state['lastTest']) && fx_now_ms() - $state['lastTest'] < TEST_COOLDOWN_MS) {
    finish('Vừa gửi thử lúc ' . date('H:i:s', (int) ($state['lastTest'] / 1000)) . ', chờ 1 phút rồi thử lại.', false, 429);
}

function ntfy_send($cfg, $title, $message, $priority, $tags) {
    $payload = ['topic' => $cfg['ntfy_topic'], 'title' => $title, 'message' => $message, 'priority' => $priority, 'tags' => $tags];
    if ($cfg['page_url'] !== '') $payload['click'] = $cfg['page_url'];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json'];
    if ($cfg['ntfy_token'] !== '') $headers[] = 'Authorization: Bearer ' . $cfg['ntfy_token'];
    $url = rtrim($cfg['ntfy_server'], '/') . '/';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FX_VERIFY_SSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FX_VERIFY_SSL ? 2 : 0);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (PHP_VERSION_ID < 80000) curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 10, 'ignore_errors' => true]]);
        @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) $code = (int) $m[1];
    }
    return $code >= 200 && $code < 300;
}

function fetch_price($symbol, &$error) {
    $log = [];
    $raw = fx_fetch(PRICE_URL . $symbol, $log);
    $json = $raw !== false ? json_decode($raw, true) : null;
    if (isset($json['price']) && is_numeric($json['price']) && $json['price'] > 0) return (float) $json['price'];
    $error = 'Không lấy được giá ' . $symbol . ' từ Binance (HTTP ' . (isset($log['http_code']) ? $log['http_code'] : 0) . ')'
        . (isset($log['http_code']) && $log['http_code'] == 451 ? ', Binance chặn khu vực của host' : '');
    return null;
}

function fmt_signed($v) {
    return ($v >= 0 ? '+' : '−') . number_format(abs($v), 2);
}

// Lấy giá
$error = null;
$xau = fetch_price('XAUUSDT', $error);
$fxDebug = [];
$fx = fx_get(false, $fxDebug);
$quote = isset($fx['quote']) ? $fx['quote'] : null;
if (!$error && (!$quote || (isset($fx['status']) && $fx['status'] !== 'live'))) {
    $error = 'Không lấy được giá XAU/USD từ Swissquote';
}
$mid = $quote ? ($quote['bid'] + $quote['ask']) / 2 : null;
$now = date('H:i d/m');

if ($test) {
    $msg = $xau !== null && $mid !== null
        ? 'XAUUSDT ' . number_format($xau, 2) . ', forex ' . number_format($mid, 2) . ', chênh ' . fmt_signed($xau - $mid) . ' USDT.'
        : 'Chưa lấy được giá: ' . $error . '.';
    $state['lastTest'] = fx_now_ms();
    save_state($state);
    $ok = ntfy_send($cfg, 'Thử cảnh báo Gold Perp Live', $msg . ' Ngưỡng: ≥ ' . $cfg['high'] . ' hoặc < ' . $cfg['low'] . ' USDT.', 3, ['white_check_mark']);
    $parts = [$ok ? 'Đã gửi thông báo thử, kiểm tra app ntfy trên điện thoại.' : 'Gửi thông báo thử thất bại, kiểm tra ntfy_server và ntfy_topic trong alert-config.php.'];
    if ($cli) $parts[] = 'Kênh: ' . $cfg['ntfy_topic'] . '.'; // không lộ tên kênh ra trang web
    $parts[] = $msg;
    $parts[] = isset($state['lastRun'])
        ? 'Cron kiểm tra tự động lần cuối lúc ' . date('H:i d/m', (int) ($state['lastRun'] / 1000)) . '.'
        : 'Chưa thấy cron chạy lần nào, hãy cài cron theo README.';
    $parts[] = $cfg['page_url'] !== '' ? 'Bấm vào thông báo sẽ mở ' . $cfg['page_url'] : 'Chưa biết link trang nên bấm vào thông báo sẽ không mở trang.';
    finish(implode(' ', $parts), $ok, $ok ? 200 : 502);
}

$lines = [];
if ($error) {
    $state['fails']++;
    $lines[] = $error . ' (lần ' . $state['fails'] . ').';
    if ($state['fails'] >= FAILS_BEFORE_NOTICE && !$state['failNotified']) {
        $state['failNotified'] = ntfy_send($cfg, 'Cảnh báo XAU − forex tạm ngừng', $error . ' từ ' . $now . '. Sẽ báo lại khi lấy được giá.', 3, ['warning']);
        $lines[] = $state['failNotified'] ? 'Đã báo lỗi qua ntfy.' : 'Gửi báo lỗi qua ntfy thất bại.';
    }
} else {
    if ($state['failNotified']) {
        ntfy_send($cfg, 'Cảnh báo XAU − forex chạy lại', 'Đã lấy lại được giá lúc ' . $now . '.', 2, ['white_check_mark']);
        $lines[] = 'Đã báo lấy lại được giá.';
    }
    $state['fails'] = 0;
    $state['failNotified'] = false;

    $d = $xau - $mid;
    $lines[] = 'XAUUSDT ' . number_format($xau, 2) . ' · forex ' . number_format($mid, 2) . ' · chênh ' . fmt_signed($d) . ' USDT';
    $open = !$quote['ts'] || fx_now_ms() - $quote['ts'] <= FX_STALE_MS;
    if (!$open) {
        // Forex nghỉ thì giá forex đứng yên, không báo; khi mở lại sẽ xét lại từ đầu
        $state['zone'] = null;
        $lines[] = 'Forex đang nghỉ, không báo.';
    } else {
        // Ghi lịch sử theo giờ; PAXG lỗi thì chỉ thiếu phần PAXG − forex
        $paxgError = null;
        $paxg = fetch_price('PAXGUSDT', $paxgError);
        spread_log(fx_now_ms(), $xau, $paxg, $mid);
        if ($paxg !== null) $lines[] = '· PAXG − forex ' . fmt_signed($paxg - $mid) . ' USDT';

        $hi = (float) $cfg['high'];
        $lo = (float) $cfg['low'];
        $gap = (float) $cfg['gap'];
        $zone = $state['zone'];
        if ($d >= $hi) $zone = 'high';
        elseif ($d < $lo) $zone = 'low';
        elseif ($zone === 'high' && $d < $hi - $gap) $zone = 'mid';
        elseif ($zone === 'low' && $d >= $lo + $gap) $zone = 'mid';
        elseif ($zone === null) $zone = 'mid';

        if ($zone !== $state['zone'] && $zone !== 'mid') {
            $title = $zone === 'high' ? 'XAU − forex ' . fmt_signed($d) . ' USDT, từ ' . $hi . ' trở lên' : 'XAU − forex ' . fmt_signed($d) . ' USDT, dưới ' . $lo;
            $msg = 'XAUUSDT ' . number_format($xau, 2) . ', forex XAU/USD ' . number_format($mid, 2) . ' lúc ' . $now . '.';
            $sent = ntfy_send($cfg, $title, $msg, 4, [$zone === 'high' ? 'chart_with_upwards_trend' : 'chart_with_downwards_trend']);
            $lines[] = $sent ? 'Đã gửi cảnh báo: ' . $title : 'Gửi cảnh báo thất bại, lần sau sẽ thử lại.';
            // Gửi lỗi thì giữ vùng cũ để lần chạy sau thử gửi lại
            if ($sent) $state['zone'] = $zone;
        } else {
            $state['zone'] = $zone;
        }
        $lines[] = 'Vùng: ' . ($zone === 'high' ? '≥ ' . $hi : ($zone === 'low' ? '< ' . $lo : 'trong khoảng ' . $lo . ' đến ' . $hi)) . '.';
    }
}

$state['lastRun'] = fx_now_ms();
save_state($state);
if ($lock) {
    flock($lock, LOCK_UN);
    fclose($lock);
}
finish(date('Y-m-d H:i:s') . ' ' . implode(' ', $lines));
