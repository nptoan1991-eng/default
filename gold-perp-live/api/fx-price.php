<?php
/**
 * Giá vàng XAU/USD trên thị trường forex cho trang Gold Perp Live (nguồn Swissquote, xem fx-lib.php).
 * Thêm ?debug=1 để xem dữ liệu thô và các bộ giá đọc được.
 */

require __DIR__ . '/fx-lib.php';

$DEBUG = isset($_GET['debug']);
$debug = [];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$state = fx_get($DEBUG, $debug);

$q = isset($state['quote']) ? $state['quote'] : null;
$out = [
    'status' => isset($state['status']) ? $state['status'] : 'error',
    'source' => 'Swissquote',
    'bid' => $q ? $q['bid'] : null,
    'ask' => $q ? $q['ask'] : null,
    'profile' => $q ? $q['profile'] : null,
    'ts' => $q ? $q['ts'] : null,
    'fetchedAt' => isset($state['fetchedAt']) ? $state['fetchedAt'] : null,
    'errors' => isset($state['errors']) ? $state['errors'] : [],
];
if ($DEBUG) $out['debug'] = $debug;

echo json_encode($out, $DEBUG ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE);
