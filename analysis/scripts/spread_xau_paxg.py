# Chênh lệch giá PAXGUSDT Binance − XAUUSDT Bybit theo từng giờ
# Chạy: python3 spread_xau_paxg.py <JSON Binance PAXGUSDT> <JSON Bybit XAUUSDT> (xuất từ Gold Perp Live, "Từ đầu năm")
import json, sys, warnings
import numpy as np, pandas as pd
warnings.filterwarnings('ignore')
pd.set_option('display.width', 220)
pd.set_option('display.max_columns', 30)

def hourly(fn):
    t = json.load(open(fn))['tables']['hourly']
    x = pd.DataFrame(t['rows'], columns=t['columns'])
    x['t'] = pd.to_datetime(x['hour_open_utc']) + pd.Timedelta(hours=1)  # giá đóng nến, tính theo lúc đóng
    return x.set_index('t').sort_index()[['perp_close', 'index_close']]

P = hourly(sys.argv[1]); X = hourly(sys.argv[2])
H = pd.DataFrame({'pp': P.perp_close, 'pi': P.index_close, 'xp': X.perp_close, 'xi': X.index_close}).dropna()
H = H[(H > 0).all(axis=1)]
H['usd'] = H.pp - H.xp                        # PAXG perp − XAU perp (USD/oz)
H['pct'] = 100 * H.usd / H.xp                 # theo % giá vàng
H['token'] = 100 * (H.pi - H.xi) / H.xp       # index PAXG − index vàng Bybit: token đắt/rẻ hơn vàng
H['p_prem'] = 100 * (H.pp - H.pi) / H.xp      # perp PAXG lệch index của nó
H['x_prem'] = 100 * (H.xp - H.xi) / H.xp      # perp XAU lệch index của nó
# pct = token + p_prem − x_prem
wd, hr = H.index.dayofweek, H.index.hour
H['wkend'] = (wd == 5) | ((wd == 6) & (hr < 23)) | ((wd == 4) & (hr >= 22))  # vàng thế giới nghỉ ~ T6 21:00 → CN 22:00 UTC
print('Giờ chung', len(H), H.index[0], '->', H.index[-1])

def st(s):
    return pd.Series({'n': len(s), 'TB': s.mean(), 'trung vị': s.median(), 'đlc': s.std(), 'p5': s.quantile(.05), 'p95': s.quantile(.95), 'min': s.min(), 'max': s.max()})

print('\n=== 1. Tổng quan (%, USD)')
print(pd.DataFrame({'pct': st(H.pct), 'usd': st(H.usd), 'token': st(H.token), 'p_prem': st(H.p_prem), 'x_prem': st(H.x_prem)}).round(4))

print('\n=== 2. Theo tháng (%)')
g = H.groupby(H.index.to_period('M'))
M = pd.DataFrame({'TB': g.pct.mean(), 'đlc': g.pct.std(), 'p5': g.pct.quantile(.05), 'p95': g.pct.quantile(.95), 'min': g.pct.min(), 'max': g.pct.max(),
                  'TB usd': g.usd.mean(), 'token': g.token.mean(), 'p_prem': g.p_prem.mean(), 'x_prem': g.x_prem.mean(),
                  'đlc ngày thường': H[~H.wkend].groupby(H[~H.wkend].index.to_period('M')).pct.std(),
                  'đlc cuối tuần': H[H.wkend].groupby(H[H.wkend].index.to_period('M')).pct.std(),
                  'vàng %': 100 * (g.xi.last() / g.xi.first() - 1)})
print(M.round(4))

print('\n=== 3. Ngày thường / cuối tuần (%)')
for k, s in H.groupby('wkend'):
    print('cuối tuần' if k else 'ngày thường', st(s.pct).round(4).to_dict(), '| token TB %.4f p_prem %.4f x_prem %.4f' % (s.token.mean(), s.p_prem.mean(), s.x_prem.mean()))

print('\n=== 4. Các khoảng gần đây (%)')
end = H.index[-1]
for d in (7, 14, 30, 60, 90):
    s = H[H.index > end - pd.Timedelta(days=d)]
    print('%3d ngày: TB %.4f đlc %.4f p5 %.4f p95 %.4f min %.4f max %.4f | TB usd %.2f | token %.4f p_prem %.4f x_prem %.4f'
          % (d, s.pct.mean(), s.pct.std(), s.pct.quantile(.05), s.pct.quantile(.95), s.pct.min(), s.pct.max(), s.usd.mean(), s.token.mean(), s.p_prem.mean(), s.x_prem.mean()))

print('\n=== 5. Theo tuần (%)')
W = H.pct.resample('W-SUN').agg(['mean', 'std', 'min', 'max']).round(4)
print(W.tail(14))

print('\n=== 6. Tốc độ quay về (AR(1) theo giờ)')
for name, s in (('cả giai đoạn', H.pct), ('ngày thường', H.pct[~H.wkend]), ('60 ngày', H.pct[H.index > end - pd.Timedelta(days=60)])):
    d = s - s.rolling(24 * 7, min_periods=24).mean()
    d = d.dropna()
    a, b = d.values[:-1], d.values[1:]
    ok = (pd.Series(d.index).diff().dt.total_seconds().values[1:] == 3600)
    phi = np.corrcoef(a[ok], b[ok])[0, 1]
    print('%-12s phi %.3f nửa thời gian %.1f giờ' % (name, phi, np.log(.5) / np.log(phi) if 0 < phi < 1 else np.nan))
# Thời gian từ khi lệch xa trung vị 7 ngày cho tới khi về lại trong 1/3 mức lệch đó
dev = (H.pct - H.pct.rolling(24 * 7, min_periods=24).median()).dropna()
for th in (0.05, 0.1, 0.2):
    idx = np.where(dev.abs().values > th)[0]
    durs, i, vals = [], 0, dev.values
    while i < len(idx):
        k = idx[i]; j = k
        while j < len(vals) and abs(vals[j]) > th / 3: j += 1
        durs.append(j - k)
        while i < len(idx) and idx[i] < j: i += 1
    print('lệch > %.2f%%: %d lần, số giờ để về trong %.3f%%: trung vị %.0f, p90 %.0f, max %d' % (th, len(durs), th / 3, np.median(durs) if durs else 0, np.quantile(durs, .9) if durs else 0, max(durs) if durs else 0))

print('\n=== 7. Lệch lớn nhất')
T = H.assign(dev=dev).dropna(subset=['dev'])
for c in ('pct',):
    print('cao nhất'); print(H.nlargest(6, c)[['pct', 'usd', 'token', 'p_prem', 'x_prem', 'xp', 'wkend']].round(4))
    print('thấp nhất'); print(H.nsmallest(6, c)[['pct', 'usd', 'token', 'p_prem', 'x_prem', 'xp', 'wkend']].round(4))

print('\n=== 8. Liên hệ với biến động vàng (theo ngày, giờ UTC)')
D = pd.DataFrame({'ret': H.xi.resample('D').last().pct_change() * 100,
                  'rng': (H.xi.resample('D').max() / H.xi.resample('D').min() - 1) * 100,
                  'sd': H.pct.resample('D').std(), 'm': H.pct.resample('D').mean(), 'tok': H.token.resample('D').mean()}).dropna()
print('tương quan: biên độ vàng/ngày ~ đlc chênh %.2f | lợi nhuận vàng ~ TB chênh %.2f | lợi nhuận vàng ~ token %.2f' % (D.rng.corr(D.sd), D.ret.corr(D.m), D.ret.corr(D.tok)))
D['nhóm'] = pd.cut(D.rng, [0, 1, 2, 3, 100], labels=['<1%', '1–2%', '2–3%', '>3%'])
print(D.groupby('nhóm').agg(ngày=('sd', 'size'), đlc_chênh=('sd', 'mean'), TB_chênh=('m', 'mean'), token=('tok', 'mean')).round(4))
R = pd.DataFrame({'r1': H.xi.pct_change() * 100, 'd': H.pct.diff(), 'tok': H.token.diff()}).dropna()
R['g'] = pd.cut(R.r1, [-100, -1, -0.5, -0.2, 0.2, 0.5, 1, 100])
print('theo biến động vàng trong 1 giờ: thay đổi chênh trong giờ đó')
print(R.groupby('g').agg(giờ=('d', 'size'), TB_thay_đổi=('d', 'mean'), token=('tok', 'mean'), đlc=('d', 'std')).round(4))

print('\n=== 9. Xu hướng mức chênh (hồi quy TB tuần theo thời gian)')
w = H.pct.resample('W-SUN').mean().dropna()
for name, s in (('cả giai đoạn', w), ('từ tháng 6', w[w.index >= '2026-06-01'])):
    x = (s.index - s.index[0]).days.values / 30
    k = np.polyfit(x, s.values, 1)[0]
    print('%-12s độ dốc %.4f %%/tháng' % (name, k))
v = H.pct.resample('W-SUN').std().dropna()
x = (v.index - v.index[0]).days.values / 30
print('đlc tuần: độ dốc %.4f %%/tháng, TB 4 tuần đầu %.4f, 4 tuần cuối %.4f' % (np.polyfit(x, v.values, 1)[0], v.iloc[:4].mean(), v.iloc[-4:].mean()))
