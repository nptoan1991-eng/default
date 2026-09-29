<?php
/**
 * Nhật ký chênh lệch XAUUSDT / PAXGUSDT so với giá vàng forex (Swissquote), gộp theo giờ.
 * alert-check.php (chạy bằng cron) ghi mỗi lần chạy khi forex đang giao dịch;
 * spread-history.php đọc ra cho trang. Lưu trong spread_history.json.
 */

define('SPREAD_FILE', __DIR__ . '/spread_history.json');
define('SPREAD_KEEP_DAYS', 400);

function spread_read() {
    if (!is_readable(SPREAD_FILE)) return [];
    $rows = json_decode((string) @file_get_contents(SPREAD_FILE), true);
    return is_array($rows) ? $rows : [];
}

// Mỗi giờ một dòng: giá lần ghi cuối trong giờ (xau, paxg, fx) và cao nhất / thấp nhất
// của XAU − forex và PAXG − forex trong giờ, để thấy cả các lần giãn ngắn.
function spread_log($t, $xau, $paxg, $fx) {
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
    $row['fx'] = round($fx, 3);
    foreach (['xf' => $xau, 'pf' => $paxg] as $key => $price) {
        if ($price === null) continue;
        $d = round($price - $fx, 3);
        $row[$key . 'Max'] = $row[$key . 'Max'] === null ? $d : max($row[$key . 'Max'], $d);
        $row[$key . 'Min'] = $row[$key . 'Min'] === null ? $d : min($row[$key . 'Min'], $d);
    }
    $rows[] = $row;
    $cut = (time() - SPREAD_KEEP_DAYS * 86400) * 1000;
    $rows = array_values(array_filter($rows, function ($r) use ($cut) { return $r['h'] >= $cut; }));
    // Ghi ra file tạm rồi đổi tên, để trang không đọc phải file ghi dở
    $tmp = SPREAD_FILE . '.tmp';
    if (@file_put_contents($tmp, json_encode($rows)) !== false) @rename($tmp, SPREAD_FILE);
}
