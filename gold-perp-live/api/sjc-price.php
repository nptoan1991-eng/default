<?php
/**
 * API giá vàng SJC cho trang Gold Perp Live.
 *
 * Trả về JSON gồm:
 * - giá vàng miếng SJC mới nhất (VND/lượng), lấy từ API công khai của BTMC
 *   (BTMC niêm yết giá SJC như một dòng tham chiếu trong bảng giá của họ)
 * - tỷ giá USD bán ra của Vietcombank
 * - lịch sử giá SJC và tỷ giá do chính file này ghi lại theo thời gian
 *
 * Kết quả được cache trong sjc_state.json (15 phút), lịch sử lưu ở sjc_history.json.
 * Hai file nằm cùng thư mục với file này nên thư mục cần quyền ghi.
 * Thêm ?debug=1 để xem chi tiết từng bước, ?refresh=1 để bỏ qua cache.
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('BTMC_URL', 'http://api.btmc.vn/api/BTMCAPI/getpricebtmc?key=3kd8ub1llcg9t45hnoh8hmn7t5kc2v');
define('VCB_JSON_URL', 'https://www.vietcombank.com.vn/api/exchangerates?date=');
define('VCB_XML_URL', 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=10');
define('STATE_FILE', __DIR__ . '/sjc_state.json');
define('HISTORY_FILE', __DIR__ . '/sjc_history.json');
define('LOCK_FILE', __DIR__ . '/sjc.lock');
define('TTL_OK', 15 * 60);     // giây giữa 2 lần gọi BTMC khi lần trước thành công
define('TTL_ERROR', 5 * 60);   // thử lại sớm hơn khi lần trước lỗi
define('KEEP_DAYS', 400);      // số ngày lịch sử giữ trên host
define('SEND_DAYS', 35);       // số ngày lịch sử trả về cho trang
define('TIMEOUT', 8);
define('VERIFY_SSL', true);    // đổi thành false nếu debug báo lỗi curl 60 (host thiếu chứng chỉ CA)

$DEBUG = isset($_GET['debug']);
$FORCE = isset($_GET['refresh']);
$debug = [];

function now_ms() {
    return (int) round(microtime(true) * 1000);
}

function fetch_raw($url, &$log) {
    $entry = ['url' => $url];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
        // Trình duyệt tự theo redirect (vd http -> https) nhưng curl thì phải bật rõ ràng
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GoldPerpLive/1.0)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, VERIFY_SSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, VERIFY_SSL ? 2 : 0);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) $entry['curl_error'] = curl_errno($ch) . ': ' . curl_error($ch);
        if (PHP_VERSION_ID < 80000) curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => TIMEOUT, 'header' => "User-Agent: Mozilla/5.0\r\n", 'ignore_errors' => true],
            'ssl' => ['verify_peer' => VERIFY_SSL, 'verify_peer_name' => VERIFY_SSL],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) $code = (int) $m[1];
            }
        }
    }
    $ok = $res !== false && $code > 0 && $code < 400;
    $entry['http_code'] = $code;
    $entry['ok'] = $ok;
    $entry['length'] = $res === false ? 0 : strlen($res);
    $entry['preview'] = $res === false ? '' : substr($res, 0, 500);
    $log[] = $entry;
    return $ok ? $res : false;
}

// Dữ liệu tiếng Việt cũ có thể là Windows-1258. preg_match với /u trả về lỗi cho cả
// chuỗi chỉ vì 1 byte không hợp lệ, nên chuẩn hoá về UTF-8 trước khi đọc.
function ensure_utf8($raw) {
    if (@preg_match('//u', $raw) !== false) return $raw;
    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1258', 'UTF-8//IGNORE', $raw);
        if ($converted !== false && @preg_match('//u', $converted) !== false) return $converted;
    }
    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1258');
        if ($converted !== false && @preg_match('//u', $converted) !== false) return $converted;
    }
    return preg_replace('/[\x80-\xFF]/', '', $raw);
}

// BTMC trả JSON với khoá có tiền tố "@" (@row, @n_1, @pb_1...) khi gọi từ server,
// còn trình duyệt nhận XML dạng n_1="...". Đọc JSON trước, không được thì đọc kiểu XML.
function parse_btmc($raw) {
    $rows = [];
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($json['DataList']['Data']) && is_array($json['DataList']['Data'])) {
        foreach ($json['DataList']['Data'] as $item) {
            if (!is_array($item) || !isset($item['@row'])) continue;
            $i = $item['@row'];
            $rows[] = [
                'name' => isset($item['@n_' . $i]) ? html_entity_decode((string) $item['@n_' . $i], ENT_QUOTES, 'UTF-8') : '',
                'buy' => isset($item['@pb_' . $i]) && $item['@pb_' . $i] !== '' ? (float) $item['@pb_' . $i] : 0,
                'sell' => isset($item['@ps_' . $i]) && $item['@ps_' . $i] !== '' ? (float) $item['@ps_' . $i] : 0,
                'time' => isset($item['@d_' . $i]) ? (string) $item['@d_' . $i] : '',
            ];
        }
        return $rows;
    }
    if (!preg_match_all('/n_(\d+)="([^"]*)"/', $raw, $names, PREG_SET_ORDER)) return $rows;
    foreach ($names as $m) {
        $i = $m[1];
        $field = function ($key) use ($raw, $i) {
            return preg_match('/\b' . $key . '_' . $i . '="([^"]*)"/', $raw, $f) ? $f[1] : '';
        };
        $rows[] = [
            'name' => html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'),
            'buy' => (float) $field('pb'),
            'sell' => (float) $field('ps'),
            'time' => $field('d'),
        ];
    }
    return $rows;
}

// Các mốc giá vàng miếng SJC (BTMC trả nhiều mốc giờ trong ngày), đổi về VND/lượng.
function sjc_points($rows) {
    $pick = [];
    foreach ($rows as $r) if (stripos($r['name'], 'MIẾNG SJC') !== false) $pick[] = $r;
    if (!$pick) foreach ($rows as $r) if (stripos($r['name'], 'SJC') !== false) $pick[] = $r;
    $points = [];
    foreach ($pick as $r) {
        if ($r['buy'] <= 0 || $r['sell'] <= 0) continue;
        // Dấu | đặt giây về 0, nếu không PHP lấy giây hiện tại và mốc giờ đổi sau mỗi lần gọi
        $dt = DateTime::createFromFormat('d/m/Y H:i|', trim($r['time']));
        if (!$dt) continue;
        // BTMC niêm yết theo chỉ (1 lượng = 10 chỉ). Giá vàng miếng theo lượng đã trên
        // 50 triệu từ lâu, nên giá dưới 50 triệu là giá theo chỉ.
        $mult = $r['sell'] < 50000000 ? 10 : 1;
        $t = $dt->getTimestamp() * 1000;
        $points[$t] = ['t' => $t, 'buy' => $r['buy'] * $mult, 'sell' => $r['sell'] * $mult, 'name' => $r['name']];
    }
    ksort($points);
    return array_values($points);
}

function to_number($s) {
    $s = str_replace([',', ' '], '', trim((string) $s));
    return is_numeric($s) ? (float) $s : 0.0;
}

// Tỷ giá USD bán ra của Vietcombank: thử API JSON mới trước, rồi tới file XML cũ.
function fetch_vcb_sell(&$log) {
    $raw = fetch_raw(VCB_JSON_URL . date('Y-m-d'), $log);
    if ($raw !== false) {
        $json = json_decode($raw, true);
        $list = isset($json['Data']) ? $json['Data'] : (isset($json['data']) ? $json['data'] : []);
        if (is_array($list)) {
            foreach ($list as $c) {
                $c = array_change_key_case((array) $c, CASE_LOWER);
                if (isset($c['currencycode'], $c['sell']) && strtoupper($c['currencycode']) === 'USD') {
                    $v = to_number($c['sell']);
                    if ($v > 1000) return $v;
                }
            }
        }
    }
    $raw = fetch_raw(VCB_XML_URL, $log);
    if ($raw !== false && preg_match('/<Exrate\b[^>]*CurrencyCode="USD"[^>]*>/i', $raw, $m)
        && preg_match('/\bSell="([^"]+)"/i', $m[0], $s)) {
        $v = to_number($s[1]);
        if ($v > 1000) return $v;
    }
    return null;
}

function read_json($file) {
    if (!is_readable($file)) return null;
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function write_json($file, $data) {
    return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function update_history($points, $fx) {
    $byT = [];
    foreach (read_json(HISTORY_FILE) ?: [] as $p) {
        if (isset($p['t'])) $byT[(int) $p['t']] = $p;
    }
    foreach ($points as $p) {
        if (!isset($byT[$p['t']])) $byT[$p['t']] = ['t' => $p['t'], 'buy' => $p['buy'], 'sell' => $p['sell'], 'fx' => $fx];
    }
    ksort($byT);
    $history = array_values($byT);
    // Tỷ giá đổi mà giá SJC chưa đổi: thêm một mốc tại thời điểm hiện tại với tỷ giá mới
    $last = end($history);
    if ($last && abs($last['fx'] - $fx) >= 1) {
        $history[] = ['t' => now_ms(), 'buy' => $last['buy'], 'sell' => $last['sell'], 'fx' => $fx];
    }
    $cut = (time() - KEEP_DAYS * 86400) * 1000;
    $history = array_values(array_filter($history, function ($p) use ($cut) { return $p['t'] >= $cut; }));
    write_json(HISTORY_FILE, $history);
}

function is_fresh($state) {
    if (!isset($state['fetchedAt'])) return false;
    $ttl = (isset($state['status']) && $state['status'] === 'live') ? TTL_OK : TTL_ERROR;
    return (now_ms() - $state['fetchedAt']) < $ttl * 1000;
}

function refresh($state, &$debug) {
    $now = now_ms();
    $state['errors'] = [];

    $debug['btmc'] = [];
    $points = [];
    $raw = fetch_raw(BTMC_URL, $debug['btmc']);
    if ($raw !== false) {
        $rows = parse_btmc(ensure_utf8($raw));
        $points = sjc_points($rows);
        $debug['btmc_rows'] = count($rows);
        $debug['sjc_points'] = count($points);
    }
    if ($points) {
        $latest = end($points);
        $state['sjc'] = ['buy' => $latest['buy'], 'sell' => $latest['sell'], 'time' => $latest['t'], 'name' => $latest['name'], 'fetchedAt' => $now];
    } else {
        $state['errors'][] = 'Không lấy được giá SJC từ BTMC' . (isset($state['sjc']) ? ', đang dùng giá lấy lúc trước.' : '.');
    }

    $debug['vcb'] = [];
    $fx = fetch_vcb_sell($debug['vcb']);
    if ($fx) {
        $state['fx'] = ['sell' => $fx, 'source' => 'Vietcombank bán ra', 'fetchedAt' => $now];
    } else {
        $state['errors'][] = 'Không lấy được tỷ giá Vietcombank' . (isset($state['fx']) ? ', đang dùng tỷ giá lấy lúc trước.' : '.');
    }

    if ($points && isset($state['fx'])) update_history($points, $state['fx']['sell']);

    $state['status'] = !$state['errors'] ? 'live' : (isset($state['sjc'], $state['fx']) ? 'stale' : 'error');
    $state['fetchedAt'] = $now;
    write_json(STATE_FILE, $state);
    return $state;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$errors = [];
if (!is_writable(__DIR__)) {
    $errors[] = 'Thư mục chứa sjc-price.php không có quyền ghi nên không lưu được cache và lịch sử.';
}

$state = read_json(STATE_FILE) ?: [];
if ($FORCE || !is_fresh($state)) {
    // Khoá để nhiều người mở trang cùng lúc không cùng gọi BTMC
    $lock = @fopen(LOCK_FILE, 'c');
    if ($lock) flock($lock, LOCK_EX);
    $state = read_json(STATE_FILE) ?: [];
    if ($FORCE || !is_fresh($state)) $state = refresh($state, $debug);
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

$from = (time() - SEND_DAYS * 86400) * 1000;
$history = array_values(array_filter(read_json(HISTORY_FILE) ?: [], function ($p) use ($from) {
    return isset($p['t']) && $p['t'] >= $from;
}));

$out = [
    'status' => isset($state['status']) ? $state['status'] : 'error',
    'fetchedAt' => isset($state['fetchedAt']) ? $state['fetchedAt'] : null,
    'sjc' => isset($state['sjc']) ? $state['sjc'] : null,
    'fx' => isset($state['fx']) ? $state['fx'] : null,
    'history' => $history,
    'errors' => array_merge($errors, isset($state['errors']) ? $state['errors'] : []),
];
if ($DEBUG) $out['debug'] = $debug;

echo json_encode($out, JSON_UNESCAPED_UNICODE | ($DEBUG ? JSON_PRETTY_PRINT : 0));
