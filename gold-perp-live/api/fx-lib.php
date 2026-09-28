<?php
/**
 * Lấy giá vàng XAU/USD trên thị trường forex từ feed công khai của Swissquote.
 * Dùng chung cho fx-price.php (trang dashboard) và alert-check.php (cảnh báo qua cron).
 * Feed này không có tài liệu chính thức và chặn gọi thẳng từ trình duyệt (CORS).
 * Kết quả được cache vài giây trong fx_state.json (thư mục cần quyền ghi).
 */

define('FX_URL', 'https://forex-data-feed.swissquote.com/public-quotes/bboquotes/instrument/XAU/USD');
define('FX_STATE_FILE', __DIR__ . '/fx_state.json');
define('FX_LOCK_FILE', __DIR__ . '/fx.lock');
define('FX_TTL_MS', 5000);
define('FX_TIMEOUT', 5);
define('FX_VERIFY_SSL', true); // đổi thành false nếu debug báo lỗi curl 60 (host thiếu chứng chỉ CA)
define('FX_STALE_MS', 3 * 60 * 1000); // giá forex cũ hơn 3 phút coi như thị trường đang nghỉ


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
function fx_pick($quotes) {
    $valid = array_values(array_filter($quotes, function ($q) {
        return $q['bid'] > 500 && $q['bid'] < 100000 && $q['ask'] >= $q['bid'];
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

function fx_read_state() {
    if (!is_readable(FX_STATE_FILE)) return [];
    $data = json_decode((string) @file_get_contents(FX_STATE_FILE), true);
    return is_array($data) ? $data : [];
}

function fx_refresh($state, &$debug) {
    $state['errors'] = [];
    $raw = fx_fetch(FX_URL, $debug);
    $quote = null;
    if ($raw !== false) {
        $json = json_decode($raw, true);
        $quotes = [];
        fx_find_quotes($json, 0, $quotes);
        $debug['quotes_found'] = count($quotes);
        $quote = fx_pick($quotes);
    }
    if ($quote) {
        $state['quote'] = $quote;
        $state['status'] = 'live';
    } else {
        $state['errors'][] = 'Không lấy được giá XAU/USD từ Swissquote' . (isset($state['quote']) ? ', đang dùng giá lấy lúc trước.' : '.');
        $state['status'] = isset($state['quote']) ? 'stale' : 'error';
    }
    $state['fetchedAt'] = fx_now_ms();
    @file_put_contents(FX_STATE_FILE, json_encode($state), LOCK_EX);
    return $state;
}

function fx_is_fresh($state) {
    return isset($state['fetchedAt']) && fx_now_ms() - $state['fetchedAt'] < FX_TTL_MS;
}

// Trả trạng thái giá forex, gọi lại Swissquote khi cache đã cũ (hoặc khi $force)
function fx_get($force, &$debug) {
    $state = fx_read_state();
    if ($force || !fx_is_fresh($state)) {
        // Khoá để nhiều người mở trang cùng lúc không cùng gọi Swissquote
        $lock = @fopen(FX_LOCK_FILE, 'c');
        if ($lock) flock($lock, LOCK_EX);
        $state = fx_read_state();
        if ($force || !fx_is_fresh($state)) $state = fx_refresh($state, $debug);
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    return $state;
}
