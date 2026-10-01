# So sánh funding Bybit và Binance XAUUSDT trong cùng giai đoạn
# Chạy: python3 compare.py <file JSON Bybit> <file JSON Binance> (xuất từ Gold Perp Live, khoảng "Từ đầu năm")
import json, sys, warnings
import numpy as np, pandas as pd
warnings.filterwarnings('ignore')
pd.set_option('display.width', 200)
def load(fn):
    d = json.load(open(fn)); T = d['tables']
    def fr(n, tcol, shift=0):
        t = T[n]; x = pd.DataFrame(t['rows'], columns=t['columns']); x['t'] = pd.to_datetime(x[tcol]) + pd.Timedelta(hours=shift); return x.set_index('t').sort_index()
    return fr('funding', 'time_utc'), fr('hourly', 'hour_open_utc', 1)
fy, hy = load(sys.argv[1])
fb, hb = load(sys.argv[2])
PY = 6 * 365
J = pd.DataFrame({'by': fy['rate'] * 100, 'bn': fb['rate'] * 100, 'pby': fy['premium_pct_period'], 'pbn': fb['premium_pct_period']}).dropna(subset=['by', 'bn'])
print('Kỳ chung', len(J), J.index[0], '->', J.index[-1])
print('=== 1. Tổng quan cùng giai đoạn')
for c, n in (('by', 'Bybit'), ('bn', 'Binance')):
    s = J[c]
    print('%-8s TB quy năm %.2f%% | =0 %.0f%% | dương %.0f%% | âm %.0f%% | tổng rate %.3f%% | |r| TB %.4f' % (n, s.mean() * PY, 100 * (s == 0).mean(), 100 * (s > 0).mean(), 100 * (s < 0).mean(), s.sum(), s.abs().mean()))
print('tương quan Pearson %.3f | Spearman %.3f' % (J.by.corr(J.bn), J.by.rank().corr(J.bn.rank())))
print('bảng dấu (hàng Bybit, cột Binance), % số kỳ:')
print((pd.crosstab(np.sign(J.by), np.sign(J.bn), normalize=True) * 100).round(1))
print('\n=== 2. Theo tháng: tổng rate %')
mi = hy['index_close'].resample('MS').agg(['first', 'last'])
M = J[['by', 'bn']].resample('MS').sum()
M['bn−by'] = M.bn - M.by
M['by_năm%'] = J.by.resample('MS').mean() * PY; M['bn_năm%'] = J.bn.resample('MS').mean() * PY
M['vàng%'] = 100 * (mi['last'] / mi['first'] - 1)
M['ngày bn>by%'] = None
D = J[['by', 'bn']].groupby((J.index + pd.Timedelta(hours=7)).date).sum()
D.index = pd.to_datetime(D.index)
M['ngày bn>by%'] = (100 * (D.bn > D.by).groupby(D.index.to_period('M')).mean()).values[:len(M)]
print(M.round(3))
print('ngày Binance > Bybit: %.0f%%, bằng nhau %.0f%%, Bybit > Binance %.0f%%' % (100 * (D.bn > D.by).mean(), 100 * (D.bn == D.by).mean(), 100 * (D.by > D.bn).mean()))
print('\n=== 3. Theo xu hướng giá 7 ngày (index Bybit) — quy năm %')
lp = np.log(hy['index_close'])
ret7 = 100 * (lp.reindex(J.index, method='ffill').values - lp.reindex(J.index - pd.Timedelta(days=7), method='ffill').values)
J['ret7d'] = ret7
J['trend'] = pd.cut(J.ret7d, [-99, -2, -1, 1, 2, 99], labels=['giảm >2%', 'giảm 1-2%', 'ngang ±1%', 'tăng 1-2%', 'tăng >2%'])
u = J.index
J['wk'] = ((u.dayofweek == 4) & (u.hour >= 22)) | (u.dayofweek == 5) | ((u.dayofweek == 6) & (u.hour < 22))
for lab, Z in (('cả tuần', J), ('ngày thường', J[~J.wk])):
    g = Z.groupby('trend', observed=True).agg(n=('by', 'size'), by=('by', 'mean'), bn=('bn', 'mean'), by_pos=('by', lambda s: (s > 0).mean()), bn_pos=('bn', lambda s: (s > 0).mean()), by_neg=('by', lambda s: (s < 0).mean()), bn_neg=('bn', lambda s: (s < 0).mean()))
    g['by'] *= PY; g['bn'] *= PY
    print('--', lab); print(g.round(3))
print('\n=== 4. Cuối tuần (vàng thế giới nghỉ) và ngày thường')
for lab, Z in (('cuối tuần', J[J.wk]), ('ngày thường', J[~J.wk])):
    print('%-11s n=%d | Bybit %.1f%%/năm |r| %.4f âm %.0f%% | Binance %.1f%%/năm |r| %.4f âm %.0f%%' % (lab, len(Z), Z.by.mean() * PY, Z.by.abs().mean(), 100 * (Z.by < 0).mean(), Z.bn.mean() * PY, Z.bn.abs().mean(), 100 * (Z.bn < 0).mean()))
print('\n=== 5. Sàn nào bật trước: tương quan rate Bybit(t) với Binance(t+k)')
for k in (-3, -2, -1, 0, 1, 2, 3):
    print('k=%+d (%+d giờ): %.3f' % (k, 4 * k, J.by.corr(J.bn.shift(-k))))
print('\n=== 6. Chênh perp − index từng sàn theo giờ, và chênh hai index')
H = pd.DataFrame({'pby': hy['premium_pct'], 'pbn': hb['premium_pct'], 'iby': hy['index_close'], 'ibn': hb['index_close'], 'by': hy['perp_close'], 'bn': hb['perp_close']}).dropna()
print('giờ chung', len(H), '| tương quan premium hai sàn %.3f' % H.pby.corr(H.pbn))
print('premium TB: Bybit %+.4f%%, Binance %+.4f%% | |premium| TB: Bybit %.4f, Binance %.4f' % (H.pby.mean(), H.pbn.mean(), H.pby.abs().mean(), H.pbn.abs().mean()))
H['idx_diff%'] = 100 * (H.iby / H.ibn - 1); H['perp_diff%'] = 100 * (H.by / H.bn - 1)
hu = H.index - pd.Timedelta(hours=1)
H['wk'] = ((hu.dayofweek == 4) & (hu.hour >= 22)) | (hu.dayofweek == 5) | ((hu.dayofweek == 6) & (hu.hour < 22))
for lab, Z in (('ngày thường', H[~H.wk]), ('cuối tuần', H[H.wk])):
    print('%-11s index Bybit − Binance: TB %+.4f%%, |TB| %.4f%% | giá perp Bybit − Binance: TB %+.4f%%, |TB| %.4f%%' % (lab, Z['idx_diff%'].mean(), Z['idx_diff%'].abs().mean(), Z['perp_diff%'].mean(), Z['perp_diff%'].abs().mean()))
print('\n=== 7. Binance từ tháng 5: chênh âm lớn mà funding vẫn 0?')
B5 = fb[(fb.index >= '2026-05-01')].dropna(subset=['premium_pct_period'])
for lo, hi in ((-9, -0.1), (-0.1, -0.06), (-0.06, -0.03)):
    s = B5[(B5.premium_pct_period > lo) & (B5.premium_pct_period <= hi)]
    print('Binance premium (%.2f, %.2f]: n=%d, rate âm %d, rate 0 %d' % (lo, hi, len(s), (s.rate < 0).sum(), (s.rate == 0).sum()))
Y5 = fy[(fy.index >= '2026-05-01')].dropna(subset=['premium_pct_period'])
s = Y5[Y5.premium_pct_period <= -0.06]
print('Bybit cùng giai đoạn premium <= -0.06: n=%d, rate âm %d' % (len(s), (s.rate < 0).sum()))
print('\n=== 8. Tiền thực nhận cho 1 oz Short (usdt_short) cùng giai đoạn')
uy = fy.loc[J.index, 'usdt_short'].sum(); ub = fb.loc[J.index, 'usdt_short'].sum()
print('Bybit %.2f USDT | Binance %.2f USDT | chênh %.2f' % (uy, ub, ub - uy))
for start in ('2026-05-01', '2026-07-01', '2026-09-01'):
    m = J.index >= start
    print('từ %s: Bybit %.2f | Binance %.2f' % (start, fy.loc[J.index[m], 'usdt_short'].sum(), fb.loc[J.index[m], 'usdt_short'].sum()))
