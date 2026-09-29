<?php
/**
 * Lấy giá vàng XAU/USD (và bạc XAG/USD) trên thị trường forex từ feed công khai của Swissquote.
 * Dùng chung cho fx-price.php (trang dashboard) và alert-check.php (cảnh báo qua cron).
 * Feed này không có tài liệu chính thức và chặn gọi thẳng từ trình duyệt (CORS).
 * Kết quả được cache vài giây trong fx_state.json, bạc trong fx_state_xag.json (thư mục cần quyền ghi).
 */

define('FX_URL', 'https://forex-data-feed.swissquote.com/public-quotes/bboquotes/instrument/XAU/USD');
define('FX_STATE_FILE', __DIR__ . '/fx_state.json');
define('FX_LOCK_FILE', __DIR__ . '/fx.lock');
define('FX_TTL_MS', 5000);
define('FX_TIMEOUT', 5);
define('FX_VERIFY_SSL', true); // đổi thành false nếu debug báo lỗi curl 60 (host thiếu chứng chỉ CA)
define('FX_STALE_MS', 3 * 60 * 1000); // giá forex cũ hơn 3 phút coi như thị trường đang nghỉ
define('SITE_FILE', __DIR__ . '/site.json');

// Kim loại hỗ trợ và khoảng giá hợp lệ (để bỏ qua số lạ trong feed)
function fx_inst($inst) {
    return $inst === 'XAG' ? 'XAG' : 'XAU';
}
function fx_range($inst) {
    return $inst === 'XAG' ? [1, 1000] : [500, 100000];
}
function fx_url($inst) {
    return str_replace('/XAU/USD', '/' . $inst . '/USD', FX_URL);
}
// Vàng giữ tên file cũ, bạc thêm hậu tố _xag
function fx_path($file, $inst) {
    return $inst === 'XAU' ? $file : preg_replace('/(\.\w+)$/', '_' . strtolower($inst) . '$1', $file);
}


function fx_now_ms() {
    return (int) round(microtime(true) * 1000);
}

function fx_fetch($url, &$debug) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, FX_TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GoldPerpLive/1.0)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FX_VERIFY_SSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FX_VERIFY_SSL ? 2 : 0);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) $debug['curl_error'] = curl_errno($ch) . ': ' . curl_error($ch);
        if (PHP_VERSION_ID < 80000) curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => FX_TIMEOUT, 'header' => "User-Agent: Mozilla/5.0\r\n", 'ignore_errors' => true],
            'ssl' => ['verify_peer' => FX_VERIFY_SSL, 'verify_peer_name' => FX_VERIFY_SSL],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) $code = (int) $m[1];
            }
        }
    }
    $debug['http_code'] = $code;
    $debug['preview'] = $res === false ? '' : substr($res, 0, 1500);
    return ($res !== false && $code > 0 && $code < 400) ? $res : false;
}

// Tìm mọi cặp bid/ask ở bất kỳ tầng nào, kèm spreadProfile và ts gần nhất phía trên,
// để vẫn đọc được nếu Swissquote đổi cách lồng dữ liệu.
function fx_find_quotes($node, $ts, &$out) {
    if (!is_array($node)) return;
    if (isset($node['ts']) && is_numeric($node['ts'])) $ts = (float) $node['ts'];
    if (isset($node['bid'], $node['ask']) && is_numeric($node['bid']) && is_numeric($node['ask'])) {
        $out[] = [
            'bid' => (float) $node['bid'],
            'ask' => (float) $node['ask'],
            'profile' => isset($node['spreadProfile']) ? (string) $node['spreadProfile'] : '',
            'ts' => $ts,
        ];
    }
    foreach ($node as $child) {
        if (is_array($child)) fx_find_quotes($child, $ts, $out);
    }
}

// Ưu tiên bộ giá "prime" (spread hẹp nhất của Swissquote), rồi giá mới nhất, rồi spread nhỏ nhất
function fx_pick($quotes, $inst = 'XAU') {
    list($lo, $hi) = fx_range($inst);
    $valid = array_values(array_filter($quotes, function ($q) use ($lo, $hi) {
        return $q['bid'] > $lo && $q['bid'] < $hi && $q['ask'] >= $q['bid'];
    }));
    if (!$valid) return null;
    $prime = array_values(array_filter($valid, function ($q) { return strtolower($q['profile']) === 'prime'; }));
    $list = $prime ?: $valid;
    usort($list, function ($a, $b) {
        if ($a['ts'] != $b['ts']) return $a['ts'] < $b['ts'] ? 1 : -1;
        $sa = $a['ask'] - $a['bid'];
        $sb = $b['ask'] - $b['bid'];
        return $sa == $sb ? 0 : ($sa < $sb ? -1 : 1);
    });
    $q = $list[0];
    if ($q['ts'] > 0 && $q['ts'] < 1e12) $q['ts'] *= 1000; // giây -> mili giây
    $q['ts'] = $q['ts'] > 0 ? (int) $q['ts'] : null;
    return $q;
}

function fx_read_state($inst = 'XAU') {
    $file = fx_path(FX_STATE_FILE, $inst);
    if (!is_readable($file)) return [];
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function fx_refresh($state, &$debug, $inst = 'XAU') {
    $state['errors'] = [];
    $raw = fx_fetch(fx_url($inst), $debug);
    $quote = null;
    if ($raw !== false) {
        $json = json_decode($raw, true);
        $quotes = [];
        fx_find_quotes($json, 0, $quotes);
        $debug['quotes_found'] = count($quotes);
        $quote = fx_pick($quotes, $inst);
    }
    if ($quote) {
        $state['quote'] = $quote;
        $state['status'] = 'live';
    } else {
        $state['errors'][] = 'Không lấy được giá ' . $inst . '/USD từ Swissquote' . (isset($state['quote']) ? ', đang dùng giá lấy lúc trước.' : '.');
        $state['status'] = isset($state['quote']) ? 'stale' : 'error';
    }
    $state['fetchedAt'] = fx_now_ms();
    @file_put_contents(fx_path(FX_STATE_FILE, $inst), json_encode($state), LOCK_EX);
    return $state;
}

function fx_is_fresh($state) {
    return isset($state['fetchedAt']) && fx_now_ms() - $state['fetchedAt'] < FX_TTL_MS;
}

// Trả trạng thái giá forex, gọi lại Swissquote khi cache đã cũ (hoặc khi $force)
function fx_get($force, &$debug, $inst = 'XAU') {
    $inst = fx_inst($inst);
    $state = fx_read_state($inst);
    if ($force || !fx_is_fresh($state)) {
        // Khoá để nhiều người mở trang cùng lúc không cùng gọi Swissquote
        $lock = @fopen(fx_path(FX_LOCK_FILE, $inst), 'c');
        if ($lock) flock($lock, LOCK_EX);
        $state = fx_read_state($inst);
        if ($force || !fx_is_fresh($state)) $state = fx_refresh($state, $debug, $inst);
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    return $state;
}

// Link trang dashboard (thư mục cha của api/) suy ra từ request web hiện tại.
// Chạy bằng lệnh php (cron) thì không có tên miền nên trả null.
function detect_page_url() {
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST']) || empty($_SERVER['SCRIPT_NAME'])) return null;
    $host = $_SERVER['HTTP_HOST'];
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) return null;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

function stored_page_url() {
    if (!is_readable(SITE_FILE)) return null;
    $data = json_decode((string) @file_get_contents(SITE_FILE), true);
    return isset($data['pageUrl']) ? $data['pageUrl'] : null;
}

// Chỉ ghi lần đầu, để request giả tên miền sau này không đổi được link trong thông báo.
// Muốn đổi thì xoá site.json hoặc điền page_url trong alert-config.php.
function remember_page_url() {
    $stored = stored_page_url();
    if ($stored) return $stored;
    $url = detect_page_url();
    if ($url) @file_put_contents(SITE_FILE, json_encode(['pageUrl' => $url, 'savedAt' => fx_now_ms()]), LOCK_EX);
    return $url;
}
