# So sánh funding XAUUSDT: Bybit và Binance

So trong giai đoạn cả hai sàn đều có dữ liệu, **9/3 đến 1/10/2026**: 1.240 kỳ funding 4 giờ, cùng giờ chốt ở hai sàn. Dữ liệu và cách đọc số như hai file `phan-tich-bybit-XAUUSDT.md` và `phan-tich-binance-XAUUSDT.md`.

## Tóm tắt

1. **Vì sao trông khác nhau nhiều:**
   - Nếu xem "từ đầu năm", Binance có thêm tháng 1 đến đầu tháng 3. Giai đoạn đó vàng tăng mạnh và Binance còn mức nền +0.005%/kỳ trong tháng 1, nên Binance trung bình +12%/năm còn Bybit +4.2%/năm.
   - **Trong cùng giai đoạn 9/3–1/10, hai sàn gần bằng nhau:** Bybit +4.2%/năm, Binance +3.7%/năm.
2. **Cách funding ra tiền khác nhau:**
   - Binance dương thường xuyên hơn với mức nhỏ: 42% số kỳ dương, so với 27% ở Bybit.
   - Bybit ít kỳ dương hơn nhưng mỗi lần lớn hơn. Khi chỉ Bybit dương, trung bình 0.0143%/kỳ. Khi chỉ Binance dương, chỉ 0.0054%/kỳ.
3. **Hai sàn đi cùng nhau:** tương quan cùng kỳ 0.80, không kỳ nào ngược dấu, và không sàn nào đi trước sàn nào.
4. **Khác nhau nhất ở lúc xấu:**
   - Tháng 4 và các cuối tuần: Binance âm sâu hơn.
   - Từ tháng 5: Binance không có kỳ âm nào, Bybit thì có (tháng 8 có 13% số kỳ âm).
5. **Tiền thực nhận cho 1 oz Short** cả giai đoạn: Bybit +99.6 USDT, Binance +84.0 USDT. Tính từ tháng 5 thì Binance hơn: +131.3 so với +122.7 USDT.

## 1. Tổng quan cùng giai đoạn

| | Bybit | Binance |
|---|---|---|
| Funding trung bình | +4.2%/năm | +3.7%/năm |
| % kỳ bằng 0 | 67% | 54% |
| % kỳ dương | 27% | 42% |
| % kỳ âm | 6% | 5% |
| Tổng rate cả giai đoạn | +2.39% | +2.09% |
| Tiền nhận cho 1 oz Short | +99.6 USDT | +84.0 USDT |

Dấu funding hai sàn trong cùng kỳ (% trên tổng số kỳ):

| Bybit \ Binance | Âm | Bằng 0 | Dương |
|---|---|---|---|
| Âm | 2.8% | 3.6% | 0% |
| Bằng 0 | 1.7% | 43.5% | **21.7%** |
| Dương | 0% | 6.4% | 20.2% |

- Không có kỳ nào một sàn dương còn sàn kia âm.
- Trường hợp lệch thường gặp nhất là **Bybit bằng 0 nhưng Binance dương nhẹ** (21.7% số kỳ). Binance bật khỏi 0 dễ hơn, có thể vì perp Binance thường nằm cao hơn index của chính nó (mục 5).

## 2. Theo tháng

| Tháng | Bybit | Binance | Binance − Bybit | Vàng | % ngày Binance cao hơn |
|---|---|---|---|---|---|
| 3 (từ 9/3) | +8.5%/năm | +11.8%/năm | +3.3 | −8.2% | 52% |
| 4 | −11.9%/năm | **−20.5%/năm** | −8.6 | −1.4% | 23% |
| 5 | +11.0%/năm | +9.7%/năm | −1.3 | −2.0% | 65% |
| 6 | +3.2%/năm | **+8.6%/năm** | +5.4 | −11.6% | 70% |
| 7 | +4.4%/năm | +3.6%/năm | −0.8 | +1.2% | 45% |
| 8 | −0.2%/năm | +2.7%/năm | +2.9 | +9.8% | 68% |
| 9 | **+15.1%/năm** | +11.7%/năm | −3.4 | −6.7% | 40% |

Tính theo ngày: 52% số ngày Binance cao hơn, 33% Bybit cao hơn, 15% bằng nhau. Bybit vẫn nhận nhiều hơn cả giai đoạn, chủ yếu nhờ tháng 4 lỗ ít hơn và tháng 9 nhận nhiều hơn.

## 3. Theo xu hướng giá 7 ngày (chỉ ngày thường)

| Xu hướng 7 ngày | Số kỳ | Bybit | Binance | % kỳ dương Bybit | % kỳ dương Binance |
|---|---|---|---|---|---|
| Giảm hơn 2% | 303 | +12.7%/năm | **+16.5%/năm** | 46% | **80%** |
| Giảm 1–2% | 88 | +7.5%/năm | +9.8%/năm | 34% | 64% |
| Ngang ±1% | 181 | +5.9%/năm | +7.7%/năm | 29% | 60% |
| Tăng 1–2% | 98 | +1.3%/năm | +1.8%/năm | 16% | 35% |
| Tăng hơn 2% | 191 | −2.6%/năm | −1.9%/năm | 6% | 16% |

- **Cùng một quy luật:** vàng giảm thì funding cao, vàng tăng thì funding về 0 hoặc âm.
- **Ngày thường Binance cao hơn Bybit ở mọi nhóm**, nhất là khi vàng giảm: 80% số kỳ dương so với 46%.
- Tính cả cuối tuần, nhóm tăng 1–2% âm ở cả hai sàn: Bybit −10.6%/năm, Binance −17.7%/năm. Phần âm chủ yếu đến từ các cuối tuần tháng 4.

## 4. Cuối tuần và ngày thường

| | Số kỳ | Bybit | Binance | % kỳ âm Bybit | % kỳ âm Binance |
|---|---|---|---|---|---|
| Cuối tuần (vàng thế giới nghỉ) | 348 | +0.2%/năm | **−7.4%/năm** | 16% | 9% |
| Ngày thường | 892 | +5.8%/năm | +8.0%/năm | 3% | 3% |

- **Ngày thường Binance tốt hơn**, cuối tuần Bybit tốt hơn.
- Binance âm cuối tuần ít kỳ hơn Bybit (9% so với 16%), nhưng mỗi kỳ âm sâu hơn. Phần âm này chủ yếu ở tháng 4. Từ tháng 5 Binance không còn âm cả cuối tuần.

## 5. Giá perp và index của hai sàn

- **Không sàn nào đi trước.** Tương quan giữa funding Bybit kỳ này và funding Binance:

  | Binance lệch so với Bybit | −12 giờ | −8 giờ | −4 giờ | cùng kỳ | +4 giờ | +8 giờ | +12 giờ |
  |---|---|---|---|---|---|---|---|
  | Tương quan | 0.22 | 0.31 | 0.52 | **0.80** | 0.54 | 0.32 | 0.23 |

  Hai bên gần như đối xứng, nghĩa là hai sàn phản ứng cùng lúc với cùng một đợt mua bán. Không thể nhìn funding sàn này để đoán trước sàn kia.
- **Mức chênh perp − index** của hai sàn tương quan 0.70. Trung bình:
  - Bybit: +0.024%, lệch tuyệt đối trung bình 0.045%.
  - Binance: +0.029%, lệch tuyệt đối trung bình 0.049%.
  
  Perp Binance thường nằm cao hơn index của nó một chút, nên funding Binance dễ dương hơn.
- **Hai sàn dùng index vàng khác nhau:**
  - Ngày thường, index Bybit cao hơn index Binance trung bình 0.023%.
  - Cuối tuần thì ngược lại (−0.015%), và lệch tuyệt đối gần gấp đôi (0.050%), vì mỗi sàn xử lý giá vàng lúc thị trường nghỉ theo cách riêng.
  - Đây là một lý do funding hai sàn khác nhau nhiều nhất vào cuối tuần.

## 6. Nên giữ Short ở sàn nào (theo dữ liệu này)

| Tình huống | Nên chọn | Lý do |
|---|---|---|
| Vàng giảm đều, ngày thường | Binance | 80% số kỳ dương so với 46%, trung bình cao hơn (+16.5 so với +12.7%/năm) |
| Vàng sập mạnh (như tháng 6, −11.6%) | Binance | +8.6 so với +3.2%/năm |
| Vàng giảm và có những đợt bật funding lớn (như tháng 9) | Bybit | Bybit bật mạnh hơn: +15.1 so với +11.7%/năm |
| Vàng hồi mạnh (như tháng 8, +9.8%) | Binance | Binance vẫn dương nhẹ (+2.7%/năm), Bybit âm nhẹ |
| Vàng vừa đổi chiều, nhiều nhịp hồi và cuối tuần âm (như tháng 4) | Bybit, hoặc giảm Short | Binance âm sâu hơn (−20.5 so với −11.9%/năm) |
| Cuối tuần | Bybit (tính cả giai đoạn) | Cả giai đoạn: Bybit +0.2%/năm, Binance −7.4%/năm. Nhưng từ tháng 5 Binance không âm, còn Bybit có vài cuối tuần âm nhẹ (tối đa −0.07% mỗi cuối tuần). |

**Nếu chỉ chọn một sàn cho giai đoạn hiện tại** (từ tháng 5, vàng giảm chậm): Binance ổn định hơn vì không có kỳ âm nào và dương thường xuyên hơn. Bybit cho đợt nhận lớn hơn khi funding bật lên, nhưng có lúc âm. Hai sàn tương quan 0.80, nên chia vị thế hai sàn không giảm dao động funding được bao nhiêu. Lợi ích chính của việc chia là tránh rủi ro một sàn đổi cách tính funding, như Binance đã đổi hai lần trong năm.

## Giới hạn

- Chỉ có 7 tháng chung, và vàng chủ yếu đi xuống trong thời gian này.
- Binance đã đổi cách tính ít nhất hai lần; Bybit có thể cũng đổi mà dữ liệu chưa thấy rõ. So sánh này có thể không còn đúng sau lần đổi tiếp theo.
- Tiền nhận tính theo giá khớp và mark từng kỳ cho 1 oz. Chưa tính phí giao dịch, phí rút nạp, và chênh lệch giá khi mở hoặc đóng vị thế ở hai sàn.

Chạy lại: `python3 scripts/compare.py <JSON Bybit> <JSON Binance>`.
