<?php
/**
 * Nhật ký chênh lệch XAUUSDT / PAXGUSDT so với giá vàng forex (Swissquote), gộp theo giờ.
 * alert-check.php (chạy bằng cron) ghi mỗi lần chạy khi forex đang giao dịch;
 * spread-history.php đọc ra cho trang. Lưu trong spread_history.json.
 */

define('SPREAD_FILE', __DIR__ . '/spread_history.json');
define('SPREAD_KEEP_DAYS', 400);

// PAXGUSDT − vàng forex theo từng phút, cho khung PAXGUSDT so với vàng forex trên trang.
// Mỗi lần cron chạy ghi một dòng [đầu phút (ms), giá vàng Swissquote giữa bid và ask, index PAXGUSDT Binance (giá token),
// 1 nếu forex đang giao dịch / 0 nếu đang nghỉ (giá forex là giá đóng cửa), giá khớp PAXGUSDT Binance], giữ 7 ngày gần nhất.
// Dòng ghi trước khi có giá khớp chỉ có 4 phần tử.
define('TOKEN_FILE', __DIR__ . '/token_minutes.json');
define('TOKEN_KEEP_MS', 7 * 86400000);

function token_read() {
    if (!is_readable(TOKEN_FILE)) return [];
    $rows = json_decode((string) @file_get_contents(TOKEN_FILE), true);
    return is_array($rows) ? $rows : [];
}

function token_log($t, $fx, $k, $open, $p = null) {
    $rows = token_read();
    $m = (int) (floor($t / 60000) * 60000);
    if ($rows && (int) $rows[count($rows) - 1][0] === $m) array_pop($rows);
    $rows[] = [$m, round($fx, 3), $k === null ? null : round($k, 3), $open ? 1 : 0, $p === null ? null : round($p, 3)];
    $cut = $t - TOKEN_KEEP_MS;
    $rows = array_values(array_filter($rows, function ($r) use ($cut) { return $r[0] >= $cut; }));
    $tmp = TOKEN_FILE . '.tmp';
    if (@file_put_contents($tmp, json_encode($rows)) !== false) @rename($tmp, TOKEN_FILE);
}

function spread_read() {
    if (!is_readable(SPREAD_FILE)) return [];
    $rows = json_decode((string) @file_get_contents(SPREAD_FILE), true);
    return is_array($rows) ? $rows : [];
}

// Mỗi giờ một dòng: giá lần ghi cuối trong giờ (xau, paxg, fx, và bybit là XAUUSDT Bybit)
// và cao nhất / thấp nhất của XAU − forex, PAXG − forex, Bybit XAU − forex trong giờ,
// để thấy cả các lần giãn ngắn.
function spread_log($t, $xau, $paxg, $fx, $bybit = null) {
    $rows = spread_read();
    $h = (int) (floor($t / 3600000) * 3600000);
    $last = $rows ? $rows[count($rows) - 1] : null;
    if ($last && (int) $last['h'] === $h) {
        $row = array_pop($rows);
    } else {
        $row = ['h' => $h, 'n' => 0, 'xfMax' => null, 'xfMin' => null, 'pfMax' => null, 'pfMin' => null];
    }
    $row['n']++;
    $row['t'] = (int) $t;
    $row['xau'] = $xau === null ? null : round($xau, 3);
    $row['paxg'] = $paxg === null ? null : round($paxg, 3);
    $row['bybit'] = $bybit === null ? null : round($bybit, 3);
    $row['fx'] = round($fx, 3);
    foreach (['xf' => $xau, 'pf' => $paxg, 'bf' => $bybit] as $key => $price) {
        if ($price === null) continue;
        $d = round($price - $fx, 3);
        $max = isset($row[$key . 'Max']) ? $row[$key . 'Max'] : null;
        $min = isset($row[$key . 'Min']) ? $row[$key . 'Min'] : null;
        $row[$key . 'Max'] = $max === null ? $d : max($max, $d);
        $row[$key . 'Min'] = $min === null ? $d : min($min, $d);
    }
    $rows[] = $row;
    $cut = (time() - SPREAD_KEEP_DAYS * 86400) * 1000;
    $rows = array_values(array_filter($rows, function ($r) use ($cut) { return $r['h'] >= $cut; }));
    // Ghi ra file tạm rồi đổi tên, để trang không đọc phải file ghi dở
    $tmp = SPREAD_FILE . '.tmp';
    if (@file_put_contents($tmp, json_encode($rows)) !== false) @rename($tmp, SPREAD_FILE);
}
