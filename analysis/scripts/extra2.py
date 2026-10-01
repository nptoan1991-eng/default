# Kiểm tra thêm (chạy trong cùng thư mục với gold_funding.py): python3 extra2.py <file JSON>
exec(open('gold_funding.py').read().split("def bucket_table")[0])
import warnings; warnings.filterwarnings('ignore')
for lab, a, b in (('Tháng 1-2 (vàng tăng mạnh)', '2026-01-01', '2026-03-01'), ('Tháng 3-4', '2026-03-01', '2026-05-01'), ('Tháng 5-9', '2026-05-01', '2026-10-01')):
    Z = X[(X.index >= a) & (X.index < b)].copy()
    Z['trend'] = pd.cut(Z.ret7d, [-99, -2, -1, 1, 2, 99], labels=['giảm >2%', 'giảm 1-2%', 'ngang', 'tăng 1-2%', 'tăng >2%'])
    g = Z.groupby('trend', observed=True).agg(n=('r', 'size'), r=('r', 'mean'), fut3d=('fut_r_3d', 'mean'), pos=('pos', 'mean'), neg=('neg', 'mean'))
    g['r'] *= PER_YEAR; g['fut3d'] *= PER_YEAR
    print('==', lab); print(g.round(2))
Z = X[X.index >= '2026-05-01'].copy()
u = Z.index; wk = ((u.dayofweek == 4) & (u.hour >= 22)) | (u.dayofweek == 5) | ((u.dayofweek == 6) & (u.hour < 22))
Z = Z[~wk]; Z['vq'] = pd.qcut(Z.vol3d, 4)
print('== Tháng 5-9, ngày thường: biến động 3 ngày -> funding (|r| %/kỳ, quy năm %)')
g = Z.groupby('vq', observed=True).agg(n=('r', 'size'), r=('r', 'mean'), absr=('absr', 'mean'), nz=('nz', 'mean'), fut3d=('fut_r_3d', 'mean'), fut_abs_3d=('fut_abs_3d', 'mean'))
g['r'] *= PER_YEAR; g['fut3d'] *= PER_YEAR
print(g.round(4))
