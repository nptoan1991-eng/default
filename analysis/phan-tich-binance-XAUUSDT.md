# Funding Binance XAUUSDT so với giá vàng

Dữ liệu: file xuất từ Gold Perp Live ngày 2/10/2026, khoảng "Từ đầu năm", **1/1 đến 1/10/2026**: 1.643 kỳ funding (mỗi kỳ 4 giờ) và giá khớp, index theo giờ. Open interest chỉ có 30 ngày gần nhất vì Binance chỉ giữ chừng đó, nên chưa phân tích được.

Index vàng đi qua ba giai đoạn rõ rệt:
- **Tăng mạnh** từ 4.322 (1/1) lên đỉnh khoảng 5.316 (2/3).
- **Giảm nhanh** trong tháng 3–4.
- **Giảm chậm, có nhịp hồi** từ tháng 5, đến 1/10 còn 4.179.

Cách đọc số giống file Bybit: rate là funding mỗi kỳ 4 giờ, dương thì Long trả Short. "%/năm" là rate trung bình × 6 × 365.

## Tóm tắt

1. **Binance đã đổi cách tính funding hai lần trong năm:**
   - Đến khoảng 23/1 có mức nền +0.005%/kỳ (khoảng +11%/năm). Sau đó bỏ, funding bằng 0 khi perp sát index, giống Bybit.
   - **Từ tháng 5 không có kỳ funding âm nào.** Lý do: perp Binance gần như chưa lần nào thấp hơn index quá 0.03% trong giai đoạn này. Bybit cùng lúc có 15 kỳ thấp hơn quá 0.06%, và đều có funding âm.
2. Trung bình cả năm **+12%/năm**, nhưng phần lớn đến từ tháng 1–3. Riêng tháng 1 cộng dồn +4.35%, tức khoảng +51%/năm.
3. **Quan hệ với xu hướng giá đổi theo giai đoạn:**
   - Tháng 1–2, khi vàng tăng mạnh: funding cao nhất **khi giá tăng** (+50%/năm khi vàng tăng hơn 2% trong 7 ngày), vì người chơi đuổi theo giá.
   - Từ tháng 3 trở đi: giống Bybit, **giá giảm thì funding cao hơn**, giá hồi thì funding thấp.
4. **Biến động:** trên cả năm có vẻ biến động cao thì funding lớn hơn. Nhưng đó là do tháng 1–4 vừa biến động mạnh vừa funding lớn. Riêng tháng 5–9 thì biến động không liên quan.
5. **Cuối tuần** funding dao động gấp khoảng 4 lần ngày thường. Các ngày funding cực đoan nhất đều là cuối tuần sát đỉnh giá hoặc ngay sau đợt sập.
6. Funding dương có quán tính lâu hơn Bybit: một chuỗi kỳ dương trung bình kéo dài khoảng 1 ngày, dài nhất 7 ngày.

## 1. Cơ chế và các lần đổi cách tính

| Tháng | Tổng rate | % kỳ dương | % kỳ âm | % kỳ = 0 | % kỳ đúng +0.005% | Vàng trong tháng |
|---|---|---|---|---|---|---|
| 1 | **+4.35%** | 82% | 8% | 10% | 42% | +13.2% |
| 2 | +1.24% | 27% | 14% | 59% | 0 | +7.9% |
| 3 | +2.04% | 32% | 12% | 55% | 0 | −11.3% |
| 4 | **−1.69%** | 24% | 27% | 49% | 0 | −1.4% |
| 5 | +0.82% | 50% | 0% | 51% | 0 | −2.0% |
| 6 | +0.70% | 57% | 0% | 43% | 0 | −11.6% |
| 7 | +0.31% | 35% | 0% | 65% | 0 | +1.3% |
| 8 | +0.23% | 22% | 0% | 78% | 0 | +9.7% |
| 9 | +0.96% | 67% | 0% | 33% | 0 | −6.7% |

- **Mức nền +0.005%/kỳ** xuất hiện từ 1/1 đến 23/1. Trong thời gian đó, khi perp sát index, funding bằng đúng +0.005% thay vì 0. Vì vậy có 35 kỳ funding dương dù perp thấp hơn index, và cả 35 kỳ đều trong tháng 1.
- **Sau khi bỏ mức nền**, funding chỉ khác 0 khi perp lệch index quá khoảng ±0.05% trong kỳ, giống Bybit:
  - lệch 0.025–0.05%: 24% số kỳ khác 0;
  - lệch 0.05–0.075%: 90%;
  - lệch trên 0.075%: gần 100%.
- **Từ tháng 5**, perp Binance gần như luôn ngang hoặc cao hơn index. Chỉ có 2 kỳ thấp hơn quá 0.03%, cả hai funding bằng 0. Mình không chắc Binance có đặt sàn funding bằng 0 hay không, vì chưa có lần nào perp tụt đủ sâu để kiểm tra.

## 2. Xu hướng giá theo từng giai đoạn

Funding lúc chốt và funding 3 ngày tới, chia theo % thay đổi của index trong 7 ngày trước (%/năm):

| Xu hướng 7 ngày | Tháng 1–2 (vàng tăng mạnh) | Tháng 3–4 (vàng giảm nhanh) | Tháng 5–9 (giảm chậm) |
|---|---|---|---|
| Giảm hơn 2% | +10.8 → 3 ngày tới +20.7 | +9.6 → +12.1 | +13.1 → +11.2 |
| Giảm 1–2% | −71.2 → −38.6 | +0.7 → +5.1 | +6.6 → +7.7 |
| Ngang ±1% | −2.7 → +22.3 | +11.5 → −3.2 | +5.9 → +6.8 |
| Tăng 1–2% | +37.0 → +29.8 | **−55.0 → −37.7** | +4.3 → +3.6 |
| Tăng hơn 2% | **+49.4 → +58.2** | +16.6 → −16.7 | +1.1 → +2.4 |

Ở tháng 1–2, các nhóm giảm và đi ngang chỉ có 14–38 kỳ, nên các con số đó dao động mạnh.

- **Khi vàng tăng mạnh và lập đỉnh mới (tháng 1–2):** người chơi mua đuổi, perp cao hơn index nhiều, nên vàng càng tăng thì funding càng cao.
- **Khi vàng chuyển sang giảm (tháng 3–4):** nhịp hồi 1–2% bị bán ra, funding âm mạnh. Nhịp giảm thì được mua bắt đáy, funding dương.
- **Từ tháng 5:** quan hệ yếu hơn nhưng cùng chiều với Bybit. Vàng giảm hơn 2% thì funding khoảng +11–13%/năm, vàng tăng hơn 2% thì chỉ khoảng +1–2%/năm. Không bao giờ âm.

Áp quy tắc "giảm hơn 1% trong 7 ngày thì 3 ngày tới funding dương" ở nửa sau dữ liệu (17/5–1/10):
- Sau khi vàng giảm: **99%** dương, trung bình +9.5%/năm.
- Sau khi vàng đi ngang: 93% dương, +6.5%/năm.
- Sau khi vàng tăng: 68% dương, +2.4%/năm.

Ở nửa đầu (1/1–17/5) thì ngược lại: sau khi vàng tăng hơn 1%, funding 3 ngày tới trung bình +21.9%/năm.

## 3. Biến động

| Biến động 3 ngày (chia 5 nhóm, cả năm) | Độ lớn funding TB %/kỳ | % kỳ âm | Độ lớn funding 3 ngày tới |
|---|---|---|---|
| 0.7%/ngày | 0.0180 | 6% | 0.0073 |
| 1.0%/ngày | 0.0114 | 7% | 0.0095 |
| 1.2%/ngày | 0.0041 | 2% | 0.0090 |
| 1.5%/ngày | 0.0052 | 3% | 0.0089 |
| 2.5%/ngày | 0.0149 | **16%** | **0.0182** |

Trên cả năm, nhóm biến động cao nhất có funding 3 ngày tới lớn gấp đôi, và kiểm tra ngoài mẫu cũng có tác dụng: R² +0.29 cho độ lớn funding. Nhưng tính riêng **tháng 5–9, bỏ cuối tuần**, độ lớn funding 3 ngày tới theo 4 nhóm biến động là 0.0036, 0.0036, 0.0032, 0.0023, tức không tăng theo biến động. Tác động trên cả năm đến từ tháng 1–4, khi giá vừa biến động mạnh vừa có xu hướng rõ.

## 4. Cuối tuần

| Cả năm | Số kỳ | Funding TB | Độ lớn TB %/kỳ | % kỳ khác 0 |
|---|---|---|---|---|
| Cuối tuần (vàng thế giới nghỉ) | 468 | +15.8%/năm | 0.0229 | 40% |
| Ngày thường | 1.175 | +10.5%/năm | 0.0060 | 55% |

- **Ngày funding dương mạnh nhất** đều là cuối tuần lúc vàng đang lập đỉnh:
  - 25/1 (Chủ nhật): +0.97% trong một ngày;
  - 26/1: +0.72%;
  - 28/2 – 2/3: +0.80–0.85% mỗi ngày, ngay sát đỉnh 5.316.
- **Ngày âm mạnh nhất:**
  - 1/2 (Chủ nhật): −0.70%, ngay sau khi vàng sập 7.3% trong 3 ngày;
  - 2/2;
  - các cuối tuần tháng 4 (5–6/4, 12–13/4, 19–20/4), cùng lúc với Bybit.
- **Không đoán được hướng cuối tuần bằng thông tin ngày thứ Sáu:**
  - tương quan với % thay đổi giá tuần trước: 0.01;
  - tương quan với chênh perp − index ngày thứ Sáu: 0.18.
- Nhưng funding cuối tuần có tương quan **+0.61 với khoảng nhảy giá khi vàng mở cửa lại sáng thứ Hai**. Nghĩa là perp cuối tuần phản ánh trước hướng mở cửa: perp cao hơn index cuối tuần thì thường vàng mở cửa cao hơn. Đây là thông tin về giá, không giúp đoán funding.
- Kỳ chốt lúc 3:00 sáng giờ Việt Nam và thứ Bảy, Chủ nhật, thứ Hai có funding dao động lớn nhất. Thứ Ba đến thứ Sáu ổn định.

## 5. Báo trước được bao lâu

**Quán tính của funding:**
- Tương quan giữa funding kỳ này và các kỳ sau: 0.69 (4 giờ sau), 0.24 (1 ngày sau), gần 0 (3 ngày và 7 ngày sau).
- Kỳ này dương thì kỳ sau 84% dương. Kỳ này âm thì kỳ sau 74% âm.
- Chuỗi kỳ dương liên tiếp trung bình 6.2 kỳ (khoảng 1 ngày), trung vị 4 kỳ, dài nhất 41 kỳ (gần 7 ngày). Chuỗi âm trung bình 3.8 kỳ, dài nhất 21 kỳ.

**Tương quan thứ hạng giữa tín hiệu và funding sau đó:**

| Tín hiệu | 1 ngày tới | 3 ngày tới | 7 ngày tới |
|---|---|---|---|
| Xu hướng 7 ngày | −0.24 | −0.25 | −0.26 |
| Chênh perp − index 24 giờ qua | +0.48 | +0.31 | +0.29 |
| Funding trung bình 1 ngày qua | +0.45 | +0.35 | +0.33 |
| Biến động 7 ngày (với độ lớn funding) | +0.09 | +0.16 | +0.29 |

Tương quan với xu hướng yếu hơn Bybit (−0.25 so với −0.46), vì quan hệ này đảo chiều giữa tháng 1–2 và các tháng sau.

**Kiểm tra ngoài mẫu:** tìm quy luật trên 7/1–14/6, thử trên 15/6–28/9.
- Trong phần thử, 86% số lần funding 3 ngày tới dương. Mọi mô hình đều đúng chiều 86%, tức không hơn việc luôn đoán "dương".
- Về R²: funding 1 ngày qua đạt +0.13. Xu hướng 7 ngày đạt −0.72, vì mô hình học từ tháng 1–2 rằng "giá tăng thì funding cao", điều đã không còn đúng sau đó.
- Đây là bằng chứng rõ nhất rằng **quy luật phụ thuộc giai đoạn thị trường**.

## Quy tắc dùng được

1. **Giai đoạn hiện tại (từ tháng 5):** funding Binance không âm.
   - Vàng giảm hơn 2% trong 7 ngày: khoảng +11–13%/năm.
   - Vàng tăng: chỉ khoảng +1–4%/năm.
2. **Nếu vàng quay lại tăng mạnh, lập đỉnh mới liên tục như tháng 1–2:** funding có thể lên rất cao (+30–60%/năm), nhất là cuối tuần sát đỉnh (+0.8–1% mỗi ngày). Đổi lại, nếu đỉnh gãy và sập mạnh trước cuối tuần, cuối tuần đó có thể âm rất sâu (như 1/2).
3. **Khi vàng vừa chuyển từ tăng sang giảm (như tháng 3–4):** các nhịp hồi làm funding âm mạnh, đặc biệt cuối tuần.
4. Không dùng biến động để đoán funding trong giai đoạn yên như hiện tại.

## Giới hạn

- Một sàn, 9 tháng, với ít nhất hai lần Binance đổi cách tính (bỏ mức nền tháng 1; perp không còn thấp hơn index từ tháng 5). Các quy luật trước và sau mỗi lần đổi không so trực tiếp được.
- Giai đoạn tăng mạnh chỉ có khoảng 2 tháng. Quy luật "giá tăng thì funding cao" cần thêm dữ liệu để chắc.
- Chưa có open interest và tỷ lệ Long dài hạn. Host đang lưu dần mỗi 6 giờ, vài tháng nữa sẽ phân tích được.

Chạy lại: `python3 scripts/gold_funding.py <file JSON>`, `scripts/extra.py`, `scripts/extra2.py`.
