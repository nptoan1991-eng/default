# Funding Bybit XAUUSDT so với giá vàng

Dữ liệu: file xuất từ Gold Perp Live ngày 2/10/2026, khoảng "Từ đầu năm". Bybit niêm yết XAUUSDT ngày 9/3/2026, nên dữ liệu chạy từ **9/3 đến 1/10/2026**: 1.241 kỳ funding (mỗi kỳ 4 giờ), giá khớp và index theo giờ, open interest và tỷ lệ tài khoản Long theo nến 4 giờ. Trong thời gian này index vàng giảm **18%**, từ 5.096 xuống 4.180.

Cách đọc số:
- **Rate** là funding mỗi kỳ 4 giờ. Rate dương thì Long trả Short.
- **"%/năm"** là rate trung bình nhân 6 kỳ/ngày × 365 ngày. Ví dụ +10%/năm ở giá vàng 4.200 là khoảng 1,15 USDT mỗi oz mỗi ngày cho bên Short.
- **"Xu hướng 7 ngày"** là % thay đổi của index trong 7 ngày trước lúc chốt kỳ.
- Mọi tín hiệu chỉ dùng dữ liệu có trước lúc chốt. **"3 ngày tới"** là funding trung bình của 18 kỳ sau đó.

## Tóm tắt

1. **Funding bằng 0 trong 67% số kỳ.** Hợp đồng này không có phần lãi suất mặc định. Funding chỉ khác 0 khi giá perp lệch index trung bình hơn khoảng ±0.05% trong kỳ. Trung bình cả giai đoạn chỉ **+4.2%/năm**.
2. **Vàng giảm thì funding dương, vàng tăng thì funding về 0 hoặc âm.**
   - Vàng giảm hơn 1% trong 7 ngày: 3 ngày sau funding trung bình khoảng **+10%/năm** ở cả hai nửa dữ liệu. Ở nửa sau, 90% các lần như vậy funding 3 ngày tới dương.
   - Vàng tăng hơn 1% trong 7 ngày: funding khoảng 0 hoặc âm.
3. **Biến động mạnh không làm funding cao hơn**, dù vàng tăng hay giảm.
4. **Cuối tuần là lúc funding cực đoan nhất**, cả dương lẫn âm, gấp khoảng 3 lần ngày thường. Hướng của cuối tuần đi ngược xu hướng tuần trước: sau tuần vàng tăng mạnh thì cuối tuần âm. Từ tháng 6 cuối tuần đã yên hơn.
5. **Báo trước được bao lâu:**
   - Funding hiện tại chỉ giữ quán tính vài giờ.
   - Xu hướng 7 ngày có tác dụng trong 3–7 ngày tới.
   - Mức chênh perp − index 24 giờ qua là tín hiệu mạnh nhất cho 1–3 ngày tới.

## 1. Funding khác 0 khi nào

Chia các kỳ theo mức chênh trung bình (giá khớp perp − index) / index trong kỳ:

| Chênh perp − index trong kỳ | Số kỳ | % kỳ funding khác 0 | Rate TB %/kỳ |
|---|---|---|---|
| −0.10% đến −0.075% | 15 | 100% | −0.0264 |
| −0.075% đến −0.05% | 56 | 77% | −0.0085 |
| −0.05% đến −0.025% | 103 | 11% | −0.0007 |
| −0.025% đến 0 | 107 | 0% | 0 |
| 0 đến +0.025% | 210 | 0% | 0 |
| +0.025% đến +0.05% | 350 | 6% | +0.0003 |
| +0.05% đến +0.075% | 334 | 77% | +0.0066 |
| +0.075% đến +0.10% | 43 | 100% | +0.0279 |

Funding gần như chỉ bật khỏi 0 khi perp lệch index quá **±0.05%**. Lệch càng xa thì funding càng lớn: tương quan giữa mức chênh và rate là 0.81 ở các kỳ khác 0. Không có kỳ nào funding dương khi perp thấp hơn index, hoặc ngược lại.

## 2. Theo tháng

| Tháng | Tổng rate | % kỳ dương | % kỳ âm | Vàng trong tháng |
|---|---|---|---|---|
| 3 (từ 9/3) | +0.54% | 18% | 7% | −8.2% |
| 4 | **−0.98%** | 22% | 17% | −1.3% |
| 5 | +0.93% | 25% | 3% | −2.0% |
| 6 | +0.27% | 22% | 3% | −11.6% |
| 7 | +0.38% | 19% | 2% | +1.2% |
| 8 | −0.01% | 11% | 13% | **+9.8%** |
| 9 | **+1.24%** | 67% | 0% | −6.7% |

Hai tháng âm là tháng 4 (do các cuối tuần, xem mục 5) và tháng 8 (vàng hồi mạnh). Tháng tốt nhất là tháng 9, khi vàng giảm đều.

## 3. Xu hướng giá

| Xu hướng 7 ngày | Số kỳ | Funding lúc đó | % kỳ dương | % kỳ âm | Funding 3 ngày tới |
|---|---|---|---|---|---|
| Giảm hơn 5% | 109 | +13.3%/năm | 43% | 2% | +10.7%/năm |
| Giảm 2–5% | 296 | +10.5%/năm | 44% | 0% | +10.9%/năm |
| Giảm 0.5–2% | 208 | +6.1%/năm | 30% | 2% | +10.5%/năm |
| Ngang ±0.5% | 123 | +20.6%/năm | 30% | 2% | +9.4%/năm |
| Tăng 0.5–2% | 211 | −5.5%/năm | 17% | 13% | −2.8%/năm |
| Tăng 2–5% | 181 | −8.2%/năm | 8% | 14% | −5.1%/năm |
| Tăng hơn 5% | 70 | −1.7%/năm | 4% | 14% | −7.4%/năm |

**Quy luật giữ ở cả hai nửa dữ liệu** (mốc chia 20/6), tính funding 3 ngày tới:

| Xu hướng 7 ngày | Nửa đầu (9/3–20/6) | Nửa sau (20/6–1/10) |
|---|---|---|
| Giảm hơn 1% | +9.7%/năm | +10.7%/năm |
| Ngang ±1% | +5.3%/năm | +7.3%/năm |
| Tăng hơn 1% | −12.4%/năm | −0.1%/năm |

Áp quy tắc "giảm hơn 1% trong 7 ngày thì 3 ngày tới funding dương" ở nửa sau:
- Sau khi vàng giảm: đúng **90%** (10% âm).
- Sau khi vàng đi ngang: 70% dương.
- Sau khi vàng tăng: chỉ 52% dương, 39% âm.

Hai điểm cần lưu ý:
- **Giảm sâu hơn không làm funding cao hơn nhiều.** Giảm 2–5% và giảm hơn 5% cho mức gần như nhau. Tháng 6 vàng giảm 11.6% nhưng funding cả tháng chỉ +0.27%. Hướng giá quan trọng hơn độ sâu.
- **Tác động của giá tăng yếu dần.** Ở tháng 3–4, sau khi vàng tăng 1–2% thì funding khoảng −32%/năm. Ở tháng 5–9 chỉ còn khoảng +1.5%/năm.

**Cách hiểu:** người chơi vàng trên sàn crypto đa số đứng phe Long (60–75% tài khoản) và có thói quen bắt đáy. Khi vàng giảm, họ mua thêm và đẩy perp lên cao hơn index, nên phe Long trả funding. Khi vàng hồi lên trong xu hướng giảm, họ chốt lời hoặc bán ra, perp tụt dưới index, funding âm.

## 4. Biến động

Biến động đo bằng độ lệch chuẩn lợi suất theo giờ, quy ra % mỗi ngày, trong 3 ngày trước lúc chốt. Các kỳ được chia thành 5 nhóm bằng nhau:

| Biến động 3 ngày | Độ lớn funding TB %/kỳ | % kỳ khác 0 | Độ lớn funding 3 ngày tới |
|---|---|---|---|
| 0.7%/ngày | 0.0076 | 34% | 0.0035 |
| 1.0%/ngày | 0.0064 | 38% | 0.0055 |
| 1.2%/ngày | 0.0032 | 31% | 0.0047 |
| 1.4%/ngày | 0.0035 | 30% | 0.0051 |
| 2.1%/ngày | 0.0048 | 33% | 0.0070 |

**Không có quy luật "biến động mạnh thì funding cao".** Bỏ cuối tuần và chỉ tính tháng 5–9, độ lớn funding 3 ngày tới cũng không tăng theo biến động. Nhóm có biến động trong ngày thấp nhất lại có funding lớn nhất, vì đó chủ yếu là cuối tuần: index đứng yên còn perp tự trôi.

## 5. Cuối tuần

Thị trường vàng thế giới nghỉ từ khoảng 22:00 UTC thứ Sáu đến 22:00 UTC Chủ nhật (5:00 sáng thứ Bảy đến 5:00 sáng thứ Hai giờ Việt Nam). Perp vẫn giao dịch trong lúc đó.

| | Số kỳ | Funding TB | Độ lớn TB %/kỳ | % kỳ âm |
|---|---|---|---|---|
| Cuối tuần | 348 | +0.2%/năm | 0.0096 | 16% |
| Ngày thường | 893 | +5.7%/năm | 0.0034 | 3% |

- 10 ngày funding cao nhất và 10 ngày thấp nhất đều rơi chủ yếu vào thứ Bảy, Chủ nhật hoặc sáng thứ Hai.
  - Dương mạnh: 24/5, 28–30/3, 3/5.
  - Âm mạnh: 4–6/4, 12–13/4, 19–20/4.
- **Hướng cuối tuần ngược với tuần trước.** Tổng funding cuối tuần có tương quan −0.39 với % thay đổi giá 5 ngày trước, và +0.60 với mức chênh perp − index ngày thứ Sáu.
  - Tháng 4 có ba tuần vàng tăng 2–4%, và cả ba cuối tuần sau đó funding âm (−0.33% đến −0.59% mỗi cuối tuần).
  - Sau các tuần vàng giảm (24/4, 1/5) thì cuối tuần dương.
- Từ tháng 6, cuối tuần yên hơn hẳn: phần lớn chỉ dao động trong khoảng ±0.07% mỗi cuối tuần.

## 6. Báo trước được bao lâu

**Quán tính của funding:**
- Tương quan giữa funding kỳ này và các kỳ sau: 0.66 (4 giờ sau), 0.13 (1 ngày sau), 0.00 (3 ngày sau).
- Kỳ này dương thì kỳ sau 72% dương, 28% bằng 0, không lần nào âm.
- Chuỗi kỳ dương liên tiếp trung bình 3.5 kỳ (khoảng 14 giờ), dài nhất 23 kỳ. Chuỗi bằng 0 trung bình 6.7 kỳ.

**Tương quan thứ hạng giữa tín hiệu (biết trước) và funding trung bình sau đó:**

| Tín hiệu | 1 ngày tới | 3 ngày tới | 7 ngày tới |
|---|---|---|---|
| Xu hướng 7 ngày | −0.44 | −0.46 | −0.49 |
| Xu hướng 3 ngày | −0.32 | −0.24 | −0.32 |
| Biến động 3 ngày | −0.08 | −0.05 | 0.00 |
| Chênh perp − index 24 giờ qua | **+0.62** | +0.51 | +0.44 |
| Tỷ lệ tài khoản Long | +0.27 | +0.25 | +0.22 |
| Tỷ lệ Long thay đổi 3 ngày | +0.27 | +0.24 | +0.23 |
| Open interest thay đổi 3 ngày | −0.01 | −0.10 | −0.10 |
| Funding trung bình 1 ngày qua | +0.50 | +0.42 | +0.37 |

**Kiểm tra ngoài mẫu**, dự báo funding trung bình 3 ngày tới:
- Tìm quy luật trên 60% dữ liệu đầu (16/3–12/7), rồi thử trên 40% sau (12/7–28/9).
- Trong phần thử, 71% số lần funding 3 ngày tới dương.
- R² cho biết mô hình giải thích được bao nhiêu phần biến động của funding: 1 là hoàn hảo, 0 là không hơn đoán bằng trung bình, âm là tệ hơn.

| Dự báo bằng | R² ngoài mẫu | Đúng chiều |
|---|---|---|
| Chênh perp − index 24 giờ qua | **+0.36** | **80%** |
| Funding qua + xu hướng + biến động | +0.13 | 67% |
| Funding trung bình 1 ngày qua | +0.07 | 71% |
| Open interest + tỷ lệ Long | +0.06 | 73% |
| Xu hướng 7 ngày (đường thẳng) | +0.05 | 65% |
| Biến động 3 ngày | −0.02 | 71% |

Xu hướng 7 ngày có tương quan thứ hạng mạnh nhưng dự báo bằng đường thẳng thì kém. Lý do: quan hệ này không thẳng. Funding chủ yếu bằng 0, và chỉ "bật" dương khi giá giảm. Cách dùng tốt hơn là chia nhóm như ở mục 3.

## 7. Giờ trong ngày

Kỳ chốt lúc 3:00 sáng giờ Việt Nam có funding dao động lớn nhất (độ lớn TB 0.0086%/kỳ so với 0.004% ở các giờ khác) và nhiều kỳ âm nhất (9%). Phần lớn chênh lệch này đến từ các kỳ cuối tuần. Ngoài ra các giờ chốt khác nhau không đáng kể.

## Quy tắc dùng được (cho người giữ Short ăn funding)

1. **Vàng giảm hơn 1% trong 7 ngày:** 3–7 ngày tới nhiều khả năng funding dương, khoảng +10%/năm. Đây là giai đoạn thuận lợi để giữ Short.
2. **Vàng tăng hơn 1% trong 7 ngày:** funding khoảng 0 hoặc âm. Cẩn thận nhất là cuối tuần ngay sau một tuần tăng mạnh.
3. **Muốn biết 1–3 ngày tới:** xem mức chênh perp − index trên trang. Nếu trung bình trên +0.05% thì funding đang và sẽ dương.
4. **Không dùng biến động để đoán funding.**
5. Phần lớn thời gian funding bằng 0. Mức trung bình cả giai đoạn chỉ +4%/năm, nên thu nhập đáng kể chỉ đến trong các đợt vàng giảm.

## Giới hạn

- Chỉ có 7 tháng của một sàn, trong giai đoạn vàng chủ yếu đi xuống. Chưa có giai đoạn vàng tăng mạnh kéo dài để kiểm tra. Dữ liệu Binance tháng 1–2 cho thấy lúc đó funding lại cao khi giá tăng; xem `phan-tich-binance-XAUUSDT.md`.
- Các khoảng 3 ngày và 7 ngày chồng lên nhau, nên số mẫu độc lập ít hơn số kỳ, và kết quả trông chắc hơn thực tế.
- Quy luật có thể thay đổi: hiệu ứng cuối tuần và tác động của giá tăng đều đã yếu đi từ tháng 6.
- Mức chênh ở đây tính theo giá khớp nến 1 giờ. Sàn tính funding theo giá đặt mua/bán có độ sâu, nên ngưỡng ±0.05% là gần đúng.

Chạy lại: `python3 scripts/gold_funding.py <file JSON>`, `scripts/extra.py`, `scripts/extra2.py` (cần numpy, pandas).
