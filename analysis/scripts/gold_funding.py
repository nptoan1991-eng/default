# Phân tích funding một hợp đồng so với giá index: xu hướng, biến động, khả năng báo trước
# Chạy: python3 gold_funding.py <file JSON xuất từ Gold Perp Live, khoảng "Từ đầu năm">
import json, sys
import numpy as np
import pandas as pd

pd.set_option('display.width', 200)
pd.set_option('display.max_columns', 30)
F = sys.argv[1]
d = json.load(open(F))
T = d['tables']

def frame(name):
    t = T[name]
    return pd.DataFrame(t['rows'], columns=t['columns'])

f = frame('funding')
f['t'] = pd.to_datetime(f['time_utc'])
f = f.set_index('t').sort_index()
h = frame('hourly')
h['t'] = pd.to_datetime(h['hour_open_utc']) + pd.Timedelta(hours=1)  # thời điểm đóng nến
h = h.set_index('t').sort_index()
oi = frame('openInterest')
oi['t'] = pd.to_datetime(oi['time_utc'])
oi = oi.set_index('t').sort_index()

PER_YEAR = 6 * 365  # kỳ 4 giờ
f['r'] = f['rate'] * 100  # % mỗi kỳ
f['nz'] = (f['rate'] != 0).astype(int)
f['pos'] = (f['rate'] > 0).astype(int)
f['neg'] = (f['rate'] < 0).astype(int)
f['absr'] = f['r'].abs()

# ---------- 0. Tổng quan ----------
print('=== 0. Tổng quan')
print('kỳ', len(f), 'từ', f.index[0], 'tới', f.index[-1])
print('rate trung bình %/kỳ', round(f['r'].mean(), 5), '-> quy năm %', round(f['r'].mean() * PER_YEAR, 2))
print('kỳ = 0: %.1f%%, dương: %.1f%%, âm: %.1f%%' % (100 * (1 - f['nz'].mean()), 100 * f['pos'].mean(), 100 * f['neg'].mean()))
print('khi khác 0: TB %+.4f%%, trung vị |r| %.4f%%, p90 |r| %.4f%%' % (f.loc[f.nz == 1, 'r'].mean(), f.loc[f.nz == 1, 'absr'].median(), f.loc[f.nz == 1, 'absr'].quantile(0.9)))
print('index đầu/cuối', h['index_close'].iloc[0], h['index_close'].iloc[-1], 'thay đổi %.1f%%' % (100 * (h['index_close'].iloc[-1] / h['index_close'].iloc[0] - 1)))
m = f.resample('MS').agg(r=('r', 'sum'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'), n=('r', 'size'))
mi = h['index_close'].resample('MS').agg(['first', 'last'])
m['idx_chg%'] = 100 * (mi['last'] / mi['first'] - 1)
m['vol_d%'] = np.log(h['index_close']).diff().resample('MS').std() * np.sqrt(24) * 100
print(m.round(3))

# ---------- 1. Funding khác 0 khi nào: vùng chênh lệch ----------
print('\n=== 1. Chênh perp − index trong kỳ (premium_pct_period) và funding')
g = f.dropna(subset=['premium_pct_period']).copy()
g['pb'] = pd.cut(g['premium_pct_period'], [-9, -0.3, -0.2, -0.15, -0.1, -0.075, -0.05, -0.025, 0, 0.025, 0.05, 0.075, 0.1, 0.15, 0.2, 0.3, 9])
print(g.groupby('pb', observed=True).agg(n=('r', 'size'), nz=('nz', 'mean'), r_mean=('r', 'mean'), prem_mean=('premium_pct_period', 'mean')).round(4))
nzp = g[g.nz == 1]
fit = np.polyfit(nzp['premium_pct_period'], nzp['r'], 1)
print('khi khác 0: rate ≈ %.3f × premium %+.4f' % tuple(fit), '| corr', round(nzp[['premium_pct_period', 'r']].corr().iloc[0, 1], 3))

# ---------- Đặc trưng tại lúc chốt (chỉ dùng dữ liệu trước đó) ----------
lp = np.log(h['index_close'])
hr = lp.diff()
feat = pd.DataFrame(index=f.index)
for days in (1, 3, 7, 14):
    past = lp.reindex(f.index - pd.Timedelta(days=days), method='ffill').values
    now = lp.reindex(f.index, method='ffill').values
    feat['ret%dd' % days] = 100 * (now - past)
for days in (1, 3, 7):
    v = hr.rolling(24 * days, min_periods=12 * days).std() * np.sqrt(24) * 100  # % mỗi ngày
    feat['vol%dd' % days] = v.reindex(f.index, method='ffill').values
# Perp − index trung bình 24 giờ trước (không tính kỳ đang xét để tránh trùng với chính funding)
prem24 = h['premium_pct'].rolling(24, min_periods=12).mean()
feat['prem24h'] = prem24.reindex(f.index, method='ffill').values
# Open interest và tỷ lệ Long (dữ liệu tới lúc chốt)
oi_now = oi.reindex(f.index, method='ffill')
for days in (1, 3, 7):
    oi_past = oi['open_interest'].reindex(f.index - pd.Timedelta(days=days), method='ffill').values
    feat['oi_chg%dd' % days] = 100 * (oi_now['open_interest'].values / oi_past - 1)
feat['long'] = oi_now['long_account_ratio'].values
feat['long_chg3d'] = feat['long'] - oi['long_account_ratio'].reindex(f.index - pd.Timedelta(days=3), method='ffill').values
feat['r_prev'] = f['r'].shift(1)
feat['r_prev1d'] = f['r'].shift(1).rolling(6, min_periods=3).mean()
feat['nz_prev1d'] = f['nz'].shift(1).rolling(6, min_periods=3).mean()
X = feat.join(f[['r', 'nz', 'pos', 'neg', 'absr']])
# Mục tiêu tương lai: funding các kỳ SAU kỳ hiện tại (không gồm kỳ hiện tại)
for n, lab in ((6, '1d'), (18, '3d'), (42, '7d')):
    X['fut_r_' + lab] = f['r'][::-1].rolling(n, min_periods=n).mean()[::-1].shift(-1)
    X['fut_abs_' + lab] = f['absr'][::-1].rolling(n, min_periods=n).mean()[::-1].shift(-1)
    X['fut_nz_' + lab] = f['nz'][::-1].rolling(n, min_periods=n).mean()[::-1].shift(-1)

def bucket_table(col, bins, labels=None, targets=('r', 'absr', 'nz', 'pos', 'neg', 'fut_r_3d', 'fut_abs_3d', 'fut_r_7d')):
    b = pd.cut(X[col], bins, labels=labels)
    agg = {t: (t, 'mean') for t in targets}
    out = X.groupby(b, observed=True).agg(n=('r', 'size'), **agg)
    out['r_năm%'] = out['r'] * PER_YEAR
    out['fut3d_năm%'] = out['fut_r_3d'] * PER_YEAR
    return out.round(4)

print('\n=== 2. Xu hướng: thay đổi index 7 ngày trước lúc chốt')
print(bucket_table('ret7d', [-99, -5, -2, -0.5, 0.5, 2, 5, 99]))
print('\n--- thay đổi index 3 ngày')
print(bucket_table('ret3d', [-99, -3, -1, -0.3, 0.3, 1, 3, 99]))
print('\n--- thay đổi index 1 ngày')
print(bucket_table('ret1d', [-99, -2, -1, -0.3, 0.3, 1, 2, 99]))

print('\n=== 3. Biến động: độ lệch chuẩn lợi suất theo giờ, quy ra %/ngày, 3 ngày trước lúc chốt (chia 5 nhóm bằng nhau)')
X['vol3q'] = pd.qcut(X['vol3d'], 5)
print(X.groupby('vol3q', observed=True).agg(n=('r', 'size'), vol=('vol3d', 'mean'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'), fut_abs_3d=('fut_abs_3d', 'mean'), fut_r_3d=('fut_r_3d', 'mean')).round(4))
print('--- biến động 1 ngày')
X['vol1q'] = pd.qcut(X['vol1d'], 5)
print(X.groupby('vol1q', observed=True).agg(n=('r', 'size'), vol=('vol1d', 'mean'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'), fut_abs_1d=('fut_abs_1d', 'mean')).round(4))
print('--- xu hướng x biến động (ret7d dấu x vol3d cao/thấp): rate TB %/kỳ và % khác 0')
X['trend'] = np.where(X['ret7d'] < -1, 'giảm', np.where(X['ret7d'] > 1, 'tăng', 'ngang'))
X['volhl'] = np.where(X['vol3d'] > X['vol3d'].median(), 'biến động cao', 'biến động thấp')
print(X.groupby(['trend', 'volhl']).agg(n=('r', 'size'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'), fut_r_3d=('fut_r_3d', 'mean')).round(4))

# ---------- 4. Tương quan thứ hạng (Spearman) với funding hiện tại và tương lai ----------
def spear(a, b):
    m = a.notna() & b.notna()
    return a[m].rank().corr(b[m].rank())
cols = ['ret1d', 'ret3d', 'ret7d', 'ret14d', 'vol1d', 'vol3d', 'vol7d', 'prem24h', 'oi_chg1d', 'oi_chg3d', 'oi_chg7d', 'long', 'long_chg3d', 'r_prev', 'r_prev1d', 'nz_prev1d']
tg = ['r', 'absr', 'fut_r_1d', 'fut_r_3d', 'fut_r_7d', 'fut_abs_1d', 'fut_abs_3d', 'fut_abs_7d']
print('\n=== 4. Tương quan thứ hạng (Spearman): hàng là tín hiệu biết trước, cột là funding')
print(pd.DataFrame({t: {c: spear(X[c], X[t]) for c in cols} for t in tg}).round(3))

# ---------- 5. Kiểm tra ngoài mẫu: 60% đầu tìm quy luật, 40% sau kiểm tra ----------
print('\n=== 5. Kiểm tra ngoài mẫu: dự báo funding TB 3 ngày tới')
OI_OK = X['long'].notna().mean() > 0.5  # Binance chỉ có 30 ngày open interest: bỏ khỏi mô hình
print('open interest phủ %.0f%% số kỳ' % (100 * X['long'].notna().mean()))
Y = X.dropna(subset=['fut_r_3d', 'r_prev1d', 'ret7d', 'vol3d', 'prem24h'] + (['oi_chg3d', 'long'] if OI_OK else []))
cut = int(len(Y) * 0.6)
tr, te = Y.iloc[:cut], Y.iloc[cut:]
print('train', tr.index[0], '->', tr.index[-1], '| test', te.index[0], '->', te.index[-1])
def ols(cols, target='fut_r_3d'):
    A = np.column_stack([np.ones(len(tr))] + [tr[c] for c in cols])
    coef, *_ = np.linalg.lstsq(A, tr[target], rcond=None)
    B = np.column_stack([np.ones(len(te))] + [te[c] for c in cols])
    pred = B @ coef
    err = te[target] - pred
    r2 = 1 - (err ** 2).sum() / ((te[target] - tr[target].mean()) ** 2).sum()
    sign_ok = (np.sign(pred) == np.sign(te[target])).mean()
    return r2, sign_ok, coef
for name, cs in [('chỉ funding 1 ngày qua', ['r_prev1d']), ('chỉ xu hướng 7 ngày', ['ret7d']), ('chỉ biến động 3 ngày', ['vol3d']),
                 ('chỉ chênh 24h qua', ['prem24h'])] + ([('OI + tỷ lệ Long', ['oi_chg3d', 'long'])] if OI_OK else []) + [
                 ('funding qua + xu hướng + biến động', ['r_prev1d', 'ret7d', 'vol3d']), ('tất cả', ['r_prev1d', 'ret7d', 'vol3d', 'prem24h'] + (['oi_chg3d', 'long'] if OI_OK else []))]:
    r2, s, coef = ols(cs)
    print('%-38s R² ngoài mẫu %+.3f | đúng dấu %.0f%% | hệ số %s' % (name, r2, 100 * s, np.round(coef, 4)))
print('cách đơn giản "3 ngày tới bằng TB 1 ngày qua": đúng dấu %.0f%%' % (100 * (np.sign(te['r_prev1d']) == np.sign(te['fut_r_3d'])).mean()))
print('tỷ lệ 3 ngày tới dương trong test: %.0f%%' % (100 * (te['fut_r_3d'] > 0).mean()))
print('--- dự báo độ lớn |funding| 3 ngày tới')
for name, cs in [('chỉ |funding| 1 ngày qua', ['nz_prev1d']), ('chỉ biến động 3 ngày', ['vol3d']), ('biến động + |funding| qua', ['vol3d', 'nz_prev1d'])]:
    r2, s, coef = ols(cs, 'fut_abs_3d')
    print('%-38s R² ngoài mẫu %+.3f | hệ số %s' % (name, r2, np.round(coef, 4)))

# ---------- 6. Quán tính ----------
print('\n=== 6. Quán tính')
for lag in (1, 6, 18, 42):
    print('tự tương quan rate trễ %d kỳ (%s): %.3f' % (lag, {1: '4 giờ', 6: '1 ngày', 18: '3 ngày', 42: '7 ngày'}[lag], f['r'].autocorr(lag)))
s = np.sign(f['r'])
for cur in (1, -1, 0):
    nxt = s.shift(-1)[s == cur]
    print('kỳ này %+d -> kỳ sau: dương %.0f%%, 0 %.0f%%, âm %.0f%% (n=%d)' % (cur, 100 * (nxt > 0).mean(), 100 * (nxt == 0).mean(), 100 * (nxt < 0).mean(), len(nxt)))
runs = (s != s.shift()).cumsum()
rl = s.groupby(runs).agg(['first', 'size'])
for cur, lab in ((1, 'dương'), (-1, 'âm'), (0, 'bằng 0')):
    x = rl[rl['first'] == cur]['size']
    print('chuỗi %s liên tiếp: TB %.1f kỳ, trung vị %d, dài nhất %d kỳ (%d chuỗi)' % (lab, x.mean(), x.median(), x.max(), len(x)))

# ---------- 7. Lịch ----------
print('\n=== 7. Theo giờ chốt (giờ VN) và thứ trong tuần (giờ VN)')
vn = f.index + pd.Timedelta(hours=7)
print(f.groupby(vn.hour).agg(n=('r', 'size'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean')).round(4))
wd = pd.Series(vn.dayofweek, index=f.index).map({0: 'T2', 1: 'T3', 2: 'T4', 3: 'T5', 4: 'T6', 5: 'T7', 6: 'CN'})
print(f.groupby(wd.values).agg(n=('r', 'size'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean')).round(4))
# Thị trường vàng thế giới nghỉ: khoảng 22:00 UTC thứ Sáu tới 22:00 UTC Chủ nhật
u = f.index
closed = ((u.dayofweek == 4) & (u.hour >= 22)) | (u.dayofweek == 5) | ((u.dayofweek == 6) & (u.hour < 22))
print('vàng thế giới nghỉ (cuối tuần):', f[closed].agg({'r': 'mean', 'absr': 'mean', 'nz': 'mean'}).round(4).to_dict(), 'n', closed.sum())
print('vàng thế giới mở:', f[~closed].agg({'r': 'mean', 'absr': 'mean', 'nz': 'mean'}).round(4).to_dict(), 'n', (~closed).sum())

# ---------- 8. Các đợt funding mạnh nhất ----------
print('\n=== 8. 10 ngày funding cao nhất / thấp nhất (tổng %/ngày) và giá trước đó')
day = f['r'].groupby((f.index + pd.Timedelta(hours=7)).date).sum()
idx_d = h['index_close'].groupby((h.index + pd.Timedelta(hours=7)).date).last()
dd = pd.DataFrame({'r_ngày': day, 'idx': idx_d}).dropna()
dd['ret_3d_trước%'] = 100 * (dd['idx'].shift(1) / dd['idx'].shift(4) - 1)
dd['ret_trong_ngày%'] = 100 * (dd['idx'] / dd['idx'].shift(1) - 1)
dd['ret_3d_sau%'] = 100 * (dd['idx'].shift(-3) / dd['idx'] - 1)
print(dd.sort_values('r_ngày', ascending=False).head(10).round(3))
print(dd.sort_values('r_ngày').head(10).round(3))
