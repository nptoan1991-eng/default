# Chênh lệch giá PAXGUSDT Binance và XAUUSDT Bybit

So giá perp theo từng giờ trong giai đoạn cả hai đều có dữ liệu, **9/3 đến 2/11/2026**: 5.719 giờ. Dữ liệu lấy từ hai file JSON xuất từ Gold Perp Live ("Từ đầu năm", xuất ngày 2/11).

Trong file này:
- **Chênh lệch** = giá PAXGUSDT Binance − giá XAUUSDT Bybit, lấy giá đóng nến 1 giờ.
- Chênh lệch tính theo % giá vàng. Ở giá vàng hiện tại (khoảng 3.900 USD/oz), **0.1% ≈ 3.9 USD/oz**.
- Âm nghĩa là PAXG rẻ hơn XAU.

## Tóm tắt

1. **PAXG thường rẻ hơn XAU một chút.**
   - Trung bình −0.20%, tức khoảng −8.8 USD/oz.
   - 90% số giờ nằm trong khoảng −0.42% đến +0.07%.
2. **Chênh lệch đến chủ yếu từ chính token PAXG**, không phải từ hai hợp đồng perp:
   - **Token PAXG so với vàng:** trung bình −0.13%, dao động mạnh nhất.
   - **Perp PAXG so với index của nó:** luôn thấp hơn khoảng 0.045% và rất đều.
   - **Perp XAU Bybit so với index của nó:** thường cao hơn 0.03%.
3. **Ổn định hay không thì chia làm hai giai đoạn:**
   - **Tháng 5 đến tháng 8: rất ổn định.** Chênh lệch gần như luôn nằm khoảng −0.20% đến −0.25%. 98% số giờ nằm trong khoảng −0.47% đến −0.03%, tức lúc nào PAXG cũng rẻ hơn.
   - **Từ tháng 9: kém ổn định hơn rõ rệt.**
     - Mức chênh nhích dần lên: tháng 9 trung bình −0.14%, tháng 10 −0.08%, 7 ngày gần nhất khoảng 0%.
     - Có lúc PAXG đắt hơn XAU tới +0.22%.
     - Chênh lệch dao động thành từng đợt khoảng một tuần, giữa khoảng −0.28% và +0.18%.
4. **Lệch xa thì thường quay về.**
   - Khoảng 18 giờ thì phần lệch so với mức bình thường của tuần giảm đi một nửa (60 ngày gần đây thì khoảng 33 giờ).
   - Lệch quá 0.2% thì thường mất khoảng 1.5 ngày để về lại, lâu nhất khoảng 4 ngày.
5. **Dự kiến:**
   - Nếu không có sự kiện bất thường, chênh lệch nhiều khả năng vẫn nằm trong khoảng **−0.38% đến +0.19%**, tức khoảng −15 đến +7 USD/oz. Từ tháng 9, 98% số giờ nằm trong khoảng này.
   - Lệch lớn hơn (0.5% đến 1%) chủ yếu xảy ra vào **cuối tuần**, khi vàng thế giới nghỉ.
   - Không thấy dấu hiệu chênh lệch sẽ giãn ra nhiều. Nhưng mức chênh trung bình đã đổi từ khoảng −0.23% sang khoảng −0.1% đến 0%, nên không nên coi −0.2% là mức "bình thường" nữa.

## 1. Tổng quan

| | Trung bình | Trung vị | Độ lệch chuẩn | 5% thấp nhất | 5% cao nhất | Thấp nhất | Cao nhất |
|---|---|---|---|---|---|---|---|
| Chênh lệch (%) | −0.198 | −0.216 | 0.151 | −0.42 | +0.07 | −1.01 | +0.67 |
| Chênh lệch (USD/oz) | −8.8 | −9.3 | 6.9 | −19.8 | +3.1 | −48.5 | +33.7 |

Chênh lệch tách thành ba phần cộng lại (tính theo % giá vàng):

| Phần | Trung bình | Độ lệch chuẩn | Ý nghĩa |
|---|---|---|---|
| Token: index PAXG − index vàng Bybit | −0.126% | 0.168% | Token PAXG đang được mua bán rẻ hay đắt hơn vàng thật |
| Perp PAXG − index PAXG | −0.045% | 0.015% | Perp PAXG luôn hơi thấp hơn token, rất đều |
| Perp XAU − index XAU (trừ đi) | +0.027% | 0.048% | Perp XAU Bybit thường hơi cao hơn giá vàng |

- **Gần như toàn bộ dao động đến từ phần token.** Phần perp PAXG gần như cố định. Phần perp XAU nhỏ: đây chính là mức perp − index quyết định funding Bybit.
- Mức cao nhất +0.67% là giờ đầu tiên Bybit mở XAUUSDT (9/3), nên không dùng làm mốc. Mức thấp nhất −1.01% rơi vào cuối tuần 18–19/4 (xem mục 3).

## 2. Theo tháng

| Tháng | Trung bình | Độ lệch chuẩn | 5% thấp nhất | 5% cao nhất | Token | Vàng trong tháng |
|---|---|---|---|---|---|---|
| 3 (từ 9/3) | −0.02% | 0.146 | −0.28 | +0.21 | +0.04% | −8.2% |
| 4 | **−0.39%** | 0.142 | −0.68 | −0.20 | −0.33% | −1.3% |
| 5 | −0.21% | 0.087 | −0.33 | −0.05 | −0.13% | −2.0% |
| 6 | −0.23% | 0.072 | −0.35 | −0.13 | −0.16% | −11.6% |
| 7 | −0.25% | **0.046** | −0.34 | −0.18 | −0.18% | +1.2% |
| 8 | −0.24% | 0.086 | −0.44 | −0.13 | −0.20% | +9.8% |
| 9 | −0.14% | 0.117 | −0.36 | +0.06 | −0.04% | −6.7% |
| 10 | −0.08% | 0.137 | −0.29 | +0.12 | +0.01% | −6.5% |
| 1–2/11 | +0.12% | 0.047 | +0.06 | +0.21 | +0.17% | +0.4% |

- **Tháng 7 ổn định nhất:** độ lệch chuẩn 0.046%, chênh lệch luôn trong khoảng −0.39% đến −0.13%.
- **Từ tháng 9, dao động tăng gần gấp ba so với tháng 7,** về lại mức tháng 3–4.
- **Mức chênh không đi theo xu hướng vàng trong tháng.** Tháng 6 vàng giảm 11.6% và tháng 8 tăng 9.8%, nhưng chênh lệch gần như không đổi (−0.23% và −0.24%).

Theo tuần (tuần kết thúc Chủ nhật, giờ UTC, tính cả cuối tuần):

| Tuần (kết thúc) | Trung bình | Thấp nhất | Cao nhất |
|---|---|---|---|
| 27/9 | −0.22% | −0.35 | −0.09 |
| 4/10 | +0.01% | −0.21 | +0.12 |
| 11/10 | −0.09% | −0.34 | +0.17 |
| 18/10 | −0.20% | −0.36 | +0.07 |
| 25/10 | −0.05% | −0.36 | +0.15 |
| 1/11 | +0.00% | −0.23 | +0.22 |

Mức chênh đi lên xuống thành đợt (trung bình từng ngày):
- 29/9–8/10: PAXG ngang hoặc đắt hơn XAU tới +0.07%.
- 9/10–20/10: PAXG rẻ hơn khoảng 0.15–0.29%.
- 22–26/10: lại ngang hoặc đắt hơn.
- 27–29/10: rẻ hơn khoảng 0.05–0.14%.
- Từ 30/10: đắt hơn lên tới +0.10%.

## 3. Cuối tuần và ngày thường

Cuối tuần ở đây là lúc vàng thế giới nghỉ, khoảng 22 giờ UTC thứ 6 đến 23 giờ UTC Chủ nhật.

| | Số giờ | Trung bình | Độ lệch chuẩn | 5% thấp nhất | 5% cao nhất | Thấp nhất |
|---|---|---|---|---|---|---|
| Ngày thường | 4.053 | −0.19% | 0.129 | −0.39 | +0.06 | −0.58 |
| Cuối tuần | 1.666 | −0.23% | **0.191** | −0.51 | +0.13 | **−1.01** |

- **Cuối tuần chênh lệch dao động mạnh hơn khoảng 50%.** Vàng thật không giao dịch, nên index vàng của Bybit tự xoay theo cách riêng, còn token PAXG vẫn được mua bán bình thường trên các sàn crypto.
- **Mọi lần lệch lớn nhất đều vào cuối tuần.**
  - 18–19/4: PAXG rẻ hơn tới 1%, khoảng 48 USD/oz.
  - 21/3: PAXG đắt hơn 0.49%.
  - 23/8: PAXG rẻ hơn 0.57%.
- Mức chênh trung bình mỗi cuối tuần thay đổi khá nhiều từ tuần này sang tuần khác. Ví dụ: cuối tuần 17–18/10 là −0.27%, cuối tuần 24–25/10 là +0.06%, cuối tuần 31/10–1/11 là +0.16%.

## 4. Lệch rồi có quay về không

- Tính phần lệch so với mức trung bình 7 ngày:
  - Cả giai đoạn: sau khoảng **18 giờ** thì phần lệch còn một nửa.
  - 60 ngày gần đây: khoảng **33 giờ**, tức quay về chậm hơn.
- Lệch so với trung vị 7 ngày, và mất bao lâu để về lại trong 1/3 mức lệch đó:

  | Lệch hơn | Số lần | Trung vị số giờ để về | 90% số lần về trong | Lâu nhất |
  |---|---|---|---|---|
  | 0.05% | 183 | 9 giờ | 50 giờ | 208 giờ |
  | 0.10% | 80 | 14 giờ | 85 giờ | 194 giờ |
  | 0.20% | 26 | 35 giờ | 81 giờ | 93 giờ |

- Trong 24 giờ, mức chênh thường thay đổi khoảng 0.05%. 10% số lần thay đổi hơn 0.16%, và 1% số lần hơn 0.30%. Từ tháng 9 lớn hơn trước một chút.

## 5. Chênh lệch có liên quan đến biến động vàng không

- **Hai giá đi cùng giờ, không bên nào trễ.** Tương quan giá token PAXG với giá vàng trong cùng giờ là 0.99. Lệch 1 giờ thì tương quan gần 0.
- **Nhưng PAXG chỉ đi khoảng 95% biên độ của vàng.** Vì vậy:

  | Vàng trong 1 giờ | Số giờ | Chênh lệch thay đổi trung bình |
  |---|---|---|
  | Giảm hơn 1% | 33 | +0.034% (PAXG giảm ít hơn) |
  | Giảm 0.5–1% | 139 | +0.012% |
  | Đi ngang ±0.2% | 4.184 | 0% |
  | Tăng 0.5–1% | 135 | −0.016% |
  | Tăng hơn 1% | 30 | −0.036% (PAXG tăng ít hơn) |

  Vàng giảm nhanh thì PAXG tạm "đắt lên" so với XAU, vàng tăng nhanh thì ngược lại. Phần này nhỏ (khoảng 0.03% cho mỗi 1% vàng chạy) và thường quay về sau vài giờ.
- **Tính theo ngày thì liên hệ yếu.** Ngày vàng chạy hơn 3% thì chênh lệch dao động mạnh hơn khoảng 50% so với ngày bình thường (độ lệch chuẩn 0.060% so với 0.034–0.042%). Tương quan giữa biên độ vàng trong ngày và độ dao động của chênh lệch chỉ 0.18.
- **Kết luận:** mức chênh trung bình bị quyết định bởi lượng mua bán token PAXG trên thị trường crypto, không phải bởi xu hướng vàng. Đó là lý do tháng 6 (vàng −11.6%) và tháng 8 (vàng +9.8%) có mức chênh như nhau.

## 6. Dự kiến thời gian tới

**Khả năng cao nhất: chênh lệch không giãn ra nhiều.**
- Từ tháng 6, mức chênh trung bình mỗi tuần tăng dần khoảng 0.05% mỗi tháng, nghĩa là PAXG đắt dần lên so với XAU.
- Độ dao động mỗi tuần không tăng thêm: trung bình 4 tuần đầu là 0.149%, 4 tuần cuối là 0.093%.

**Khoảng nên dùng làm mốc** (theo giai đoạn từ tháng 9):

| | % | USD/oz (vàng 3.900) |
|---|---|---|
| Thường gặp (50% số giờ) | −0.21% đến +0.01% | −8 đến 0 |
| Đa số (90% số giờ) | −0.31% đến +0.10% | −12 đến +4 |
| Hiếm (98% số giờ) | −0.38% đến +0.19% | −15 đến +7 |
| Bất thường, chủ yếu cuối tuần | ngoài ±0.5% | ngoài ±20 |

**Lúc nào có thể chênh nhiều:**
- **Cuối tuần có tin lớn về vàng:** như 18–19/4, lệch tới 1%.
- **Vàng chạy rất mạnh trong vài giờ:** lệch thêm khoảng 0.03% cho mỗi 1% vàng chạy, thường hết sau vài giờ.
- **Sự kiện riêng của token PAXG:** tin về Paxos, rút hoặc nạp token lớn, sàn ngừng nạp rút. Dữ liệu chưa có lần nào như vậy, nhưng đây là rủi ro lớn nhất vì phần token chiếm gần hết dao động.

**Không nên kỳ vọng mức chênh quay lại −0.2% ngay.** Hai tháng gần đây mức chênh đi lên xuống giữa −0.3% và +0.2%. Tháng 5–8 luôn âm khoảng −0.23%, nhưng hiện tại không còn như vậy.

## 7. Nếu giữ cặp Short XAU Bybit và Long PAXG Binance

- **Lời lỗ phần giá** của cặp này đúng bằng thay đổi của chênh lệch:
  - Chênh lệch tăng (PAXG đắt lên so với XAU) thì lời.
  - Chênh lệch giảm thì lỗ.
- **Funding 30 ngày gần đây, cho 1 oz:**
  - Short XAU Bybit nhận khoảng **1.34 USDT/ngày**.
  - Long PAXG Binance trả khoảng **0.79 USDT/ngày** (PAXG Binance hiện chốt 4 giờ một kỳ).
  - Còn lại khoảng **+0.55 USDT/ngày**.
- **Dao động giá lớn hơn nhiều so với tiền funding mỗi ngày:**
  - 24 giờ, chênh lệch thường đổi khoảng 0.05%, tức khoảng 2 USD/oz.
  - 10% số ngày đổi hơn 0.16%, tức khoảng 6 USD/oz.
  - Vì vậy cặp này chỉ có lời khi giữ đủ lâu để mức chênh quay về, thường vài ngày.
- **Mở cặp lúc PAXG đang rẻ thì tốt hơn lúc PAXG đang đắt.** Mở khi chênh lệch gần đáy khoảng (khoảng −0.3%) thì phần giá có lợi thế. Mở khi chênh lệch đang khoảng +0.1% như hiện tại thì phần giá dễ lỗ nếu mức chênh quay về khoảng −0.1%.

## Giới hạn

- Chỉ có gần 8 tháng dữ liệu chung, bắt đầu từ ngày Bybit mở XAUUSDT.
- Dùng giá đóng nến 1 giờ, nên không thấy các lần lệch ngắn trong vài phút. Chênh lệch thực khi đặt lệnh còn phụ thuộc giá mua và giá bán, và phí giao dịch.
- Phần "token" so index PAXG của Binance với index vàng của Bybit. Hai index do hai sàn tự tính, và cách tính cuối tuần của Bybit có thể đổi.
- Phần dự kiến dựa trên việc mẫu hình mấy tháng gần đây tiếp tục. Một sự kiện riêng của token PAXG có thể làm chênh lệch vượt xa các khoảng trên.

Chạy lại: `python3 scripts/spread_xau_paxg.py <JSON Binance PAXGUSDT> <JSON Bybit XAUUSDT>`.
