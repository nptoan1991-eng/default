# Kiểm tra thêm (chạy trong cùng thư mục với gold_funding.py): python3 extra.py <file JSON>
exec(open('gold_funding.py').read().split("def bucket_table")[0])
import warnings; warnings.filterwarnings('ignore')
u = X.index
closed = ((u.dayofweek == 4) & (u.hour >= 22)) | (u.dayofweek == 5) | ((u.dayofweek == 6) & (u.hour < 22))
X['wkend'] = closed
X['trend'] = np.where(X['ret7d'] < -1, '1 giảm >1%', np.where(X['ret7d'] > 1, '3 tăng >1%', '2 ngang'))
half = X.index[len(X) // 2]
X['nửa'] = np.where(X.index < half, '1 đầu (3-6)', '2 sau (6-10)')
print('mốc chia', half)
print('=== A. Xu hướng 7 ngày -> funding 3 ngày tới, từng nửa dữ liệu (quy năm %), chỉ ngày thường / cả tuần')
for wk, lab in ((None, 'cả tuần'), (False, 'ngày thường')):
    Z = X if wk is None else X[X.wkend == wk]
    t = Z.groupby(['nửa', 'trend']).agg(n=('r', 'size'), r_now=('r', 'mean'), fut3d=('fut_r_3d', 'mean'), fut7d=('fut_r_7d', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'))
    for c in ('r_now', 'fut3d', 'fut7d'): t[c] = (t[c] * PER_YEAR).round(1)
    print('--', lab); print(t.round(3))
print('\n=== B. Quy tắc đơn giản ở nửa sau: "giá giảm >1% trong 7 ngày -> 3 ngày tới funding dương"')
te = X[(X.index >= half) & X['fut_r_3d'].notna()]
for lab, msk in (('giảm >1%', te.ret7d < -1), ('ngang', te.ret7d.abs() <= 1), ('tăng >1%', te.ret7d > 1)):
    s = te[msk]
    print('%-9s n=%d | 3 ngày tới TB dương: %.0f%% | âm: %.0f%% | funding TB quy năm %.1f%%' % (lab, len(s), 100 * (s.fut_r_3d > 0).mean(), 100 * (s.fut_r_3d < 0).mean(), s.fut_r_3d.mean() * PER_YEAR))
print('\n=== C. Cuối tuần: tổng funding từ tối thứ Sáu tới sáng thứ Hai (UTC Fri 20:00 -> Mon 00:00), so với giá tuần trước')
f2 = f.copy()
wk = []
for fri in pd.date_range(f.index[0].normalize(), f.index[-1], freq='W-FRI'):
    a, b = fri + pd.Timedelta(hours=20), fri + pd.Timedelta(days=3)
    s = f2.loc[(f2.index > a) & (f2.index <= b), 'r']
    if len(s) < 10: continue
    pf = lp.asof(a); p5 = lp.asof(a - pd.Timedelta(days=5)); p1 = lp.asof(a - pd.Timedelta(days=1))
    mon = lp.asof(b + pd.Timedelta(hours=4))  # sau khi vàng mở lại
    lr = oi['long_account_ratio'].asof(a)
    pr = h['premium_pct'].loc[(h.index > a - pd.Timedelta(hours=24)) & (h.index <= a)].mean()
    wk.append({'tuần': fri.date(), 'funding_cuối_tuần%': s.sum(), 'ret5d_trước%': 100 * (pf - p5), 'ret_thứ6%': 100 * (pf - p1), 'long_tối_thứ6': lr, 'prem24h_thứ6': pr, 'gap_mở_cửa%': 100 * (mon - pf), 'kỳ_âm': (s < 0).sum(), 'kỳ_dương': (s > 0).sum()})
W = pd.DataFrame(wk).set_index('tuần')
print(W.round(3).to_string())
for c in ('ret5d_trước%', 'ret_thứ6%', 'long_tối_thứ6', 'prem24h_thứ6', 'gap_mở_cửa%'):
    print('Spearman funding cuối tuần vs %s: %.2f' % (c, W['funding_cuối_tuần%'].rank().corr(W[c].rank())))
print('\n=== D. Bỏ cuối tuần: funding theo biến động 3 ngày (quy năm % và |r|)')
Z = X[~X.wkend].copy(); Z['vq'] = pd.qcut(Z['vol3d'], 4)
print(Z.groupby('vq', observed=True).agg(n=('r', 'size'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), fut_abs_3d=('fut_abs_3d', 'mean')).round(4))
