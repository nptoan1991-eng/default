<?php
/**
 * Hàm dùng chung cho exchanges.php (trang dashboard) và alert-check.php (cron): gọi Bybit, Hyperliquid,
 * Binance và các kho lịch sử lưu dần trên host (funding, giá theo giờ, open interest).
 */

require_once __DIR__ . '/fx-lib.php'; // dùng fx_fetch, fx_now_ms

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

// Nến Bybit ($path: /v5/market/kline giá khớp, mark-price-kline, index-price-kline), nến $minutes phút,
// tối đa 1000 nến mỗi lần, mới nhất trước. Trả ['vals' => [thời điểm mở nến => cột $col], 'from' => mốc đã lấy đủ tới];
// cột 1 là giá mở, cột 4 là giá đóng. Lỗi ngay lần gọi đầu thì trả null.
function bybit_kline($path, $symbol, $start, $end, $minutes, $col, &$log) {
    $out = [];
    $from = null;
    $pages = min(30, (int) ceil(($end - $start) / ($minutes * 60000 * 1000)) + 1);
    for ($page = 0; $page < $pages; $page++) {
        $raw = fx_fetch(EX_BYBIT . $path . '?category=linear&symbol=' . $symbol . '&interval=' . $minutes . '&start=' . $start . '&end=' . $end . '&limit=1000', $log);
        $json = $raw !== false ? json_decode($raw, true) : null;
        if (!isset($json['result']['list']) || !is_array($json['result']['list'])) {
            if (!$page) return null;
            break;
        }
        $list = $json['result']['list'];
        $oldest = PHP_INT_MAX;
        foreach ($list as $r) {
            if (!isset($r[0], $r[$col]) || !is_numeric($r[$col])) continue;
            $t = (int) $r[0];
            $out[$t] = (float) $r[$col];
            $oldest = min($oldest, $t);
        }
        if (count($list) < 1000 || $oldest <= $start) {
            $from = $start;
            break;
        }
        $end = $oldest - 1;
    }
    if ($from === null) $from = $out ? min(array_keys($out)) : $end + 1;
    return ['vals' => $out, 'from' => $from];
}

// Mark price Bybit: thời điểm mở nến => giá mở nến, tức mark lúc chốt funding đúng thời điểm đó
function bybit_marks($symbol, $start, $end, $minutes, &$log) {
    $k = bybit_kline('/v5/market/mark-price-kline', $symbol, $start, $end, $minutes, 1, $log);
    return $k ? $k['vals'] : [];
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

// Kho dữ liệu theo thời gian của một mã, lưu trong $file. Lần đầu lấy từ $start tới hiện tại, sau đó chỉ lấy
// thêm phần mới khi kho đã cũ (30 phút, hoặc vừa qua kỳ chốt đã hẹn $due), và lấy lùi thêm khi trang xin mốc sớm
// hơn mốc đã có. $fetch($a, $b) trả ['rows' => [[thời điểm, ...], ...], 'from' => mốc đã lấy đủ tới] hoặc null khi lỗi;
// $since($map, $now, $from) cho mốc bắt đầu lấy phần mới. Trả ['rows' => các dòng từ $start, 'from' => mốc kho có đủ].
function ex_store($file, $start, $due, $force, $fetch, $since, $what, &$errors) {
    $now = fx_now_ms();
    $st = ex_read($file);
    $map = [];
    if (isset($st['rows']) && is_array($st['rows'])) {
        foreach ($st['rows'] as $r) {
            if (is_array($r) && isset($r[0], $r[1])) $map[(int) $r[0]] = $r;
        }
    }
    $from = isset($st['from']) ? (int) $st['from'] : null;
    $at = isset($st['at']) ? (int) $st['at'] : 0;
    $failAt = isset($st['failAt']) ? (int) $st['failAt'] : 0;
    // Vừa gọi lỗi trong 1 phút qua thì chưa gọi lại, để host bị Bybit chặn không gọi mãi mỗi lần mở trang
    $failed = !$force && $now - $failAt < 60000;
    $changed = false;

    if (!$failed && ($from === null || $start < $from)) {
        // Lần đầu lấy hết tới hiện tại; kho đã có thì chỉ lấy lùi phần còn thiếu phía trước
        $first = $from === null;
        $f = $fetch($start, $first ? $now : $from - 1);
        if ($f === null) {
            $failed = true;
        } else {
            foreach ($f['rows'] as $r) $map[$r[0]] = $r;
            $from = $first ? $f['from'] : min($from, $f['from']);
            if ($first) $at = $now;
            $changed = true;
        }
    }

    $stDue = isset($st['due']) ? (int) $st['due'] : 0;
    if ($from !== null && !$failed && ($force || $now - $at > EX_HIST_TTL_MS || ($stDue && $now >= $stDue + 30000))) {
        ksort($map);
        $f = $fetch($since($map, $now, $from), $now);
        if ($f === null) {
            $failed = true;
        } else {
            foreach ($f['rows'] as $r) $map[$r[0]] = $r;
            $at = $now;
            $changed = true;
        }
    }

    if ($failed && $now - $failAt >= 60000) {
        $failAt = $now;
        $changed = true;
    }
    if ($changed) {
        // Bỏ các dòng quá cũ để file không lớn dần mãi
        $cut = $now - EX_STORE_MAX_DAYS * 86400000;
        foreach ($map as $t => $r) {
            if ($t < $cut) unset($map[$t]);
        }
        if ($from !== null && $from < $cut) $from = $cut;
        ksort($map);
        ex_write($file, ['from' => $from, 'at' => $at, 'due' => $due, 'failAt' => $failAt, 'rows' => array_values($map)]);
    }

    if ($failed) $errors[] = $what . ($map ? ', đang dùng dữ liệu lấy lúc trước.' : '.');
    if ($from === null) return null;
    $rows = [];
    foreach ($map as $t => $r) {
        if ($t >= $start) $rows[] = $r;
    }
    return ['rows' => $rows, 'from' => $from];
}

// Mốc lấy phần mới: sau dòng cuối cùng trong kho
function ex_after_last($map, $now, $from) {
    end($map);
    return $map ? key($map) + 1 : $from;
}

// Kho lịch sử funding Bybit của một mã (bybit_hist_<MÃ>.json), mỗi kỳ [thời điểm, rate, mark lúc chốt].
// $due là kỳ chốt kế tiếp của mã (để có ngay kỳ vừa chốt).
function bybit_store($symbol, $start, $due, $force, &$debug, &$errors) {
    $k = ex_log($debug, 'bybit_store_' . $symbol);
    $log = &$debug[$k];
    $fetch = function ($a, $b) use ($symbol, &$log) {
        $f = bybit_funding($symbol, $a, $b, $log);
        return $f === null ? null : ['rows' => bybit_attach_marks($symbol, $f['rates'], $log), 'from' => $f['from']];
    };
    // Lấy lại cả các kỳ trong 3 ngày gần đây còn thiếu mark lúc chốt
    $since = function ($map, $now, $from) {
        $since = ex_after_last($map, $now, $from);
        foreach ($map as $t => $r) {
            if ($r[2] === null && $t > $now - 3 * 86400000) return min($since, $t);
        }
        return $since;
    };
    return ex_store(__DIR__ . '/bybit_hist_' . $symbol . '.json', $start, $due, $force, $fetch, $since,
        'Bybit: không lấy được lịch sử funding ' . $symbol, $errors);
}

// Kho giá theo giờ của một mã Bybit (bybit_px_<MÃ>.json), mỗi giờ [giờ mở nến, giá khớp đóng nến, index đóng nến].
// Chỉ lưu nến đã đóng.
function bybit_px_store($symbol, $start, $due, $force, &$debug, &$errors) {
    $k = ex_log($debug, 'bybit_px_' . $symbol);
    $log = &$debug[$k];
    $fetch = function ($a, $b) use ($symbol, &$log) {
        $b = min($b, (int) (floor(fx_now_ms() / 3600000) * 3600000) - 1); // bỏ nến giờ hiện tại chưa đóng
        if ($b < $a) return ['rows' => [], 'from' => $a];
        $p = bybit_kline('/v5/market/kline', $symbol, $a, $b, 60, 4, $log);
        $i = $p === null ? null : bybit_kline('/v5/market/index-price-kline', $symbol, $a, $b, 60, 4, $log);
        if ($p === null || $i === null) return null;
        $rows = [];
        foreach ($p['vals'] as $t => $v) $rows[] = [$t, $v, isset($i['vals'][$t]) ? $i['vals'][$t] : null];
        return ['rows' => $rows, 'from' => max($p['from'], $i['from'])];
    };
    return ex_store(__DIR__ . '/bybit_px_' . $symbol . '.json', $start, $due, $force, $fetch, 'ex_after_last',
        'Bybit: không lấy được giá theo giờ ' . $symbol, $errors);
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

// ---------- Open interest và tỷ lệ Long/Short (nến 4 giờ) ----------
// Xuất kèm dữ liệu funding để phân tích. Binance chỉ giữ 30 ngày gần nhất nên host lưu dần
// (cron alert-check.php cập nhật mỗi 6 giờ) thì về sau mới có lịch sử dài.
define('EX_BINANCE', 'https://fapi.binance.com');
define('OI_COLLECT_MS', 6 * 3600000);
$OI_SOURCES = ['binance' => ['XAUUSDT', 'XAGUSDT', 'PAXGUSDT'], 'bybit' => ['XAUUSDT', 'XAGUSDT']];

// Dữ liệu futures/data của Binance trong [start, end], tối đa 500 dòng mỗi lần, cũ nhất trước:
// [thời điểm => dòng]. Lỗi ngay lần gọi đầu thì trả null.
function binance_data($path, $symbol, $start, $end, &$log) {
    $out = [];
    for ($page = 0; $page < 20 && $start <= $end; $page++) {
        $raw = fx_fetch(EX_BINANCE . $path . '?symbol=' . $symbol . '&period=4h&limit=500&startTime=' . $start . '&endTime=' . $end, $log);
        $list = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($list) || ($list && !isset($list[0]))) { // Binance báo lỗi bằng object {code, msg}
            if (!$page) return null;
            break;
        }
        $last = 0;
        foreach ($list as $r) {
            if (!isset($r['timestamp'])) continue;
            $t = (int) $r['timestamp'];
            $out[$t] = $r;
            $last = max($last, $t);
        }
        if (count($list) < 500 || !$last) break;
        $start = $last + 1;
    }
    return $out;
}

// Dữ liệu Bybit trong [start, end], mới nhất trước, lùi dần endTime:
// ['vals' => [thời điểm => dòng], 'from' => mốc đã lấy đủ tới]. Lỗi ngay lần gọi đầu thì trả null.
function bybit_desc($url, $start, $end, $limit, &$log) {
    $out = [];
    $from = null;
    for ($page = 0; $page < 40; $page++) {
        $raw = fx_fetch($url . '&startTime=' . $start . '&endTime=' . $end . '&limit=' . $limit, $log);
        $json = $raw !== false ? json_decode($raw, true) : null;
        if (!isset($json['result']['list']) || !is_array($json['result']['list'])) {
            if (!$page) return null;
            break;
        }
        $list = $json['result']['list'];
        $oldest = PHP_INT_MAX;
        foreach ($list as $r) {
            if (!isset($r['timestamp'])) continue;
            $t = (int) $r['timestamp'];
            $out[$t] = $r;
            $oldest = min($oldest, $t);
        }
        if (count($list) < $limit || $oldest <= $start) {
            $from = $start;
            break;
        }
        $end = $oldest - 1;
    }
    if ($from === null) $from = $out ? min(array_keys($out)) : $start;
    return ['vals' => $out, 'from' => $from];
}

// Mỗi dòng [thời điểm, open interest (số lượng theo đơn vị hợp đồng, ví dụ oz), tỷ lệ tài khoản đang Long 0..1 hoặc null]
function binance_oi($symbol, $a, $b, &$log) {
    $a2 = max($a, fx_now_ms() - 29 * 86400000); // cũ hơn 30 ngày Binance không còn, coi như đã lấy đủ
    if ($a2 > $b) return ['rows' => [], 'from' => $a];
    $oi = binance_data('/futures/data/openInterestHist', $symbol, $a2, $b, $log);
    if ($oi === null) return null;
    $ls = binance_data('/futures/data/globalLongShortAccountRatio', $symbol, $a2, $b, $log);
    $rows = [];
    foreach ($oi as $t => $r) {
        if (!isset($r['sumOpenInterest']) || !is_numeric($r['sumOpenInterest'])) continue;
        $long = isset($ls[$t]['longAccount']) && is_numeric($ls[$t]['longAccount']) ? (float) $ls[$t]['longAccount'] : null;
        $rows[] = [$t, (float) $r['sumOpenInterest'], $long];
    }
    return ['rows' => $rows, 'from' => $a];
}

function bybit_oi($symbol, $a, $b, &$log) {
    $base = EX_BYBIT . '/v5/market/';
    $oi = bybit_desc($base . 'open-interest?category=linear&symbol=' . $symbol . '&intervalTime=4h', $a, $b, 200, $log);
    if ($oi === null) return null;
    $ls = bybit_desc($base . 'account-ratio?category=linear&symbol=' . $symbol . '&period=4h', $a, $b, 500, $log);
    $rows = [];
    foreach ($oi['vals'] as $t => $r) {
        if (!isset($r['openInterest']) || !is_numeric($r['openInterest'])) continue;
        $long = $ls && isset($ls['vals'][$t]['buyRatio']) && is_numeric($ls['vals'][$t]['buyRatio']) ? (float) $ls['vals'][$t]['buyRatio'] : null;
        $rows[] = [$t, (float) $r['openInterest'], $long];
    }
    return ['rows' => $rows, 'from' => $oi['from']];
}

// Kho open interest + tỷ lệ Long của một mã (oi_<sàn>_<MÃ>.json)
function oi_store($ex, $symbol, $start, $force, &$debug, &$errors) {
    $k = ex_log($debug, 'oi_' . $ex . '_' . $symbol);
    $log = &$debug[$k];
    $fetch = function ($a, $b) use ($ex, $symbol, &$log) {
        return $ex === 'binance' ? binance_oi($symbol, $a, $b, $log) : bybit_oi($symbol, $a, $b, $log);
    };
    return ex_store(__DIR__ . '/oi_' . $ex . '_' . $symbol . '.json', $start, null, $force, $fetch, 'ex_after_last',
        ($ex === 'binance' ? 'Binance' : 'Bybit') . ': không lấy được open interest ' . $symbol, $errors);
}

// Cron: cập nhật kho open interest của mọi mã, từ đầu năm (hoặc 90 ngày, lấy mốc sớm hơn). Trả số mã có dữ liệu.
function oi_collect_all(&$errors) {
    global $OI_SOURCES;
    $debug = [];
    $start = min(mktime(0, 0, 0, 1, 1, (int) date('Y')) * 1000, fx_now_ms() - 90 * 86400000);
    // Dùng chung khoá với exchanges.php để không ghi đè kho cùng lúc với trang
    $lock = @fopen(EX_LOCK_FILE, 'c');
    if ($lock) flock($lock, LOCK_EX);
    $n = 0;
    foreach ($OI_SOURCES as $ex => $list) {
        foreach ($list as $symbol) {
            if (oi_store($ex, $symbol, $start, false, $debug, $errors)) $n++;
        }
    }
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $n;
}
