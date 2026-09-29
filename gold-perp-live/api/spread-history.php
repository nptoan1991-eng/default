<?php
/**
 * Nhật ký chênh lệch XAUUSDT / PAXGUSDT − giá forex theo giờ do host ghi (xem spread-lib.php).
 * ?days=N lấy N ngày gần nhất (mặc định 30).
 */

require __DIR__ . '/spread-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$days = isset($_GET['days']) ? max(1, min(SPREAD_KEEP_DAYS, (int) $_GET['days'])) : 30;
$from = (time() - $days * 86400) * 1000;
$all = spread_read();
$rows = array_values(array_filter($all, function ($r) use ($from) { return $r['h'] >= $from; }));

echo json_encode(['firstAt' => $all ? $all[0]['h'] : null, 'rows' => $rows]);
