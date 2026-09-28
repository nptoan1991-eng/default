# Gold Perp Live

Dashboard xem dữ liệu live của hợp đồng vàng vĩnh cửu trên Binance USDⓈ-M Futures (`XAUUSDT`, `PAXGUSDT`).

## Cách dùng

Upload nguyên thư mục `gold-perp-live/` lên host PHP rồi mở `https://<tên-miền>/gold-perp-live/`. Trang chính là `index.html` nên web server tự mở khi vào link thư mục.

```
gold-perp-live/
├── index.html          trang dashboard
├── README.md
└── api/
    ├── sjc-price.php   lấy giá SJC và tỷ giá cho trang
    ├── fx-price.php    lấy giá XAU/USD trên sàn forex (Swissquote) cho trang
    ├── fx-lib.php      phần lấy giá forex dùng chung
    ├── alert-check.php cảnh báo XAU − forex qua ntfy, chạy bằng cron
    └── alert-config.example.php   cấu hình mẫu cho cảnh báo
```

Thư mục `api/` cần quyền ghi. Các file PHP tự tạo `sjc_state.json`, `sjc_history.json`, `sjc.lock`, `fx_state.json`, `fx.lock`, `alert_state.json` và `alert.lock` trong đó.

Nếu mở `index.html` trực tiếp trên máy thì phần Binance vẫn chạy, riêng khung SJC báo cần mở từ host.

Chọn hợp đồng ở góc trên bên phải. Lựa chọn được nhớ cho lần mở sau.

## Dữ liệu hiển thị

| Mục | Nguồn (WebSocket `wss://fstream.binance.com/market/stream`) |
| --- | --- |
| Giá khớp gần nhất | `<symbol>@aggTrade`, luôn nhận cả XAUUSDT và PAXGUSDT |
| % thay đổi 24h | `<symbol>@ticker` |
| Mark price, funding rate, giờ funding tiếp theo | `<symbol>@markPrice@1s` |
| Funding quy năm | `funding rate × (24 / chu kỳ) × 365` |
| Chu kỳ funding | REST `GET /fapi/v1/fundingInfo`, mặc định 8h nếu không lấy được |
| Funding theo vị thế (USDT mỗi kỳ, mỗi ngày, mỗi năm) | `mark × khối lượng × |funding rate|`, nhân số kỳ mỗi ngày và 365 ngày. Khối lượng nhập trên trang, mặc định 1 oz |

Biểu đồ giá khớp trong phiên ghi lại từ lúc mở trang, giữ 15 phút gần nhất (mỗi giây một điểm).

## Lịch sử funding 14 ngày

Lấy từ REST `GET /fapi/v1/fundingRate` (thời điểm chốt, funding rate, mark price lúc chốt), tự tải lại 30 giây sau mỗi lần chốt funding.

- USDT mỗi kỳ = `funding rate × mark lúc chốt × khối lượng`. Khối lượng dùng chung ô ở mục Funding theo vị thế (mặc định 1 oz).
- Số liệu tính cho bên Short: dương là nhận, âm là trả. Bên Long luôn ngược dấu vì funding chỉ chuyển giữa hai bên. Tổng mỗi ngày là số ròng.
- Ngày chia theo giờ máy, 14 ngày gồm hôm nay. Cột của hôm nay vẽ mờ vì chưa đủ kỳ, và trung bình mỗi ngày chỉ tính các ngày đủ.
- Bảng xem theo ngày (số kỳ, tổng rate, tổng USDT) hoặc theo từng kỳ (rate, mark lúc chốt, USDT).

## So sánh funding XAUUSDT và PAXGUSDT

Tải toàn bộ lịch sử funding của 2 mã từ 05/01/2026 (ngày XAUUSDT ra mắt), tự tải lại sau mỗi lần chốt funding.

- So theo **tổng funding rate mỗi ngày** (bên Short) vì 2 mã có thể chốt funding ở giờ khác nhau. Không tính hôm nay và ngày XAUUSDT ra mắt vì chưa đủ kỳ.
- Hiện số ngày PAXGUSDT cao hơn, lần gần nhất, trung bình mỗi ngày của từng mã (kèm USDT cho khối lượng đang nhập), biểu đồ chênh lệch XAU − PAXG mỗi ngày và bảng các ngày PAXGUSDT cao hơn.

## Chênh lệch giá

So theo **giá khớp** gần nhất trên Binance và giá XAU/USD trên sàn forex.

- **XAU − PAXG** và **XAU − forex** live, bằng USDT và %.
- **Giá forex**: `api/fx-price.php` lấy bid/ask XAU/USD từ feed công khai của Swissquote, cache 5 giây; trang lấy lại mỗi 10 giây và dùng giá giữa (bid + ask) / 2. Feed này không có tài liệu chính thức và chặn gọi thẳng từ trình duyệt nên phải đi qua host. IC Markets không có API giá công khai (chỉ có cTrader Open API / FIX cần tài khoản) nên dùng Swissquote làm đại diện. Kiểm tra bằng `api/fx-price.php?debug=1`.
- Giá forex cũ hơn 3 phút thì trang báo forex đang nghỉ (17:00 thứ Sáu đến 18:00 Chủ nhật giờ New York). Lúc đó Binance vẫn chạy nên XAU − forex không phản ánh chênh lệch thật.
- **XAU − PAXG theo giờ**: nến 1 giờ giá khớp của 2 mã (`/fapi/v1/klines`), chọn 7, 30 (mặc định), 90 ngày hoặc từ 05/01/2026, tự tải lại mỗi giờ. Biểu đồ có đường 0, trên 0 (xanh) là XAUUSDT cao hơn. Thống kê % số giờ XAU cao hơn, trung bình, dương/âm lớn nhất kèm thời điểm và khoảng 90% số giờ nằm trong.

### Cảnh báo XAU − forex

- Hai ngưỡng sửa được trên trang (mặc định **≥ 7** và **< 2** USDT), trang nhớ lại ngưỡng và trạng thái bật.
- Báo **một lần** khi XAU − forex vừa vào vùng; phải ra khỏi vùng 0.5 USDT mới báo lại. Không báo khi forex đang nghỉ.
- Khi bật: thanh cảnh báo ở đầu trang, 2 tiếng bíp, và thông báo của trình duyệt (cần cho phép, trang phải chạy qua https). Âm thanh chỉ phát sau khi đã bấm vào trang ít nhất một lần kể từ lúc mở. Khi tắt vẫn ghi nhật ký 10 lần gần nhất.
- Chỉ chạy khi trang đang mở. Trên điện thoại, khoá màn hình hoặc chuyển app có thể làm trình duyệt tạm dừng trang. Muốn nhận cả khi tắt trang, xem phần cảnh báo qua ntfy bên dưới.

### Cảnh báo 24/7 qua ntfy

`api/alert-check.php` chạy bằng cron trên host: lấy giá khớp XAUUSDT (Binance) và giá forex (Swissquote), tính XAU − forex rồi gửi thông báo về điện thoại qua [ntfy.sh](https://ntfy.sh) (miễn phí, không cần tài khoản). Cách báo giống trên trang: báo một lần khi vừa vào vùng, ra khỏi vùng `gap` USDT mới báo lại, không báo khi forex nghỉ. Không lấy được giá 3 lần liền thì báo lỗi một lần, lấy lại được thì báo chạy lại.

1. **Cài app ntfy** trên điện thoại (Google Play hoặc App Store). Bấm **+**, nhập một tên kênh dài và khó đoán (ví dụ `gold-7f3k9q2x`), giữ server mặc định `ntfy.sh`. Ai biết tên kênh cũng đọc được thông báo.
2. **Tạo file cấu hình** trên host: chép `api/alert-config.example.php` thành `api/alert-config.php`, sửa `ntfy_topic` trùng tên kênh ở bước 1, chỉnh ngưỡng `high` / `low` nếu muốn, điền `page_url` là link trang để bấm vào thông báo là mở trang. Khi cập nhật tool, **đừng ghi đè** `alert-config.php`.
3. **Gửi thử**: chạy `php /đường-dẫn/gold-perp-live/api/alert-check.php test` (qua SSH hoặc một cron chạy một lần). Hoặc đặt `cron_key` trong cấu hình rồi mở `https://<tên-miền>/gold-perp-live/api/alert-check.php?key=<cron_key>&test=1`. Điện thoại nhận tin "Thử cảnh báo Gold Perp Live" là xong.
4. **Cài cron** (cPanel → Cron Jobs), chạy mỗi phút hoặc mỗi 2–5 phút tuỳ host cho phép:

   ```
   php /home/<user>/public_html/gold-perp-live/api/alert-check.php >/dev/null 2>&1
   ```

   Nếu host không cho chạy lệnh `php`, dùng link web có key:

   ```
   curl -s "https://<tên-miền>/gold-perp-live/api/alert-check.php?key=<cron_key>" >/dev/null 2>&1
   ```

- Host phải truy cập được Binance. Host đặt ở Mỹ sẽ bị Binance chặn (lỗi 451), khi đó ntfy sẽ báo "Cảnh báo XAU − forex tạm ngừng".
- Ngưỡng trên host (trong `alert-config.php`) và ngưỡng trên trang là hai chỗ riêng.
- Chạy tay không kèm `test` sẽ in kết quả lần kiểm tra (giá, chênh lệch, vùng, đã gửi hay chưa) để xem nhanh.

## SJC so với thế giới

Khung này dùng để canh lúc chênh lệch giữa giá vàng SJC và giá thế giới đang thấp.

- **Giá SJC**: `api/sjc-price.php` lấy từ API công khai của BTMC (dòng `VÀNG MIẾNG SJC`). BTMC niêm yết theo chỉ nên nhân 10 ra lượng. Host gọi BTMC tối đa 15 phút một lần (5 phút nếu lần trước lỗi), trang hỏi host 10 phút một lần.
- **Tỷ giá**: USD bán ra của Vietcombank (API JSON, dự phòng bằng file XML). Lỗi thì dùng tỷ giá lấy lúc trước và ghi rõ trên trang.
- **Giá thế giới quy đổi** = giá khớp XAUUSDT × 1.20565 × tỷ giá. 1 lượng = 37.5 g, 1 troy oz = 31.1035 g. Luôn dùng XAUUSDT, kể cả khi đang xem PAXGUSDT.
- **Chênh lệch bán ra** = SJC bán ra − giá thế giới quy đổi (giá bạn trả khi mua). Có thêm chênh lệch mua vào để thấy khoản mất nếu bán lại ngay.
- **Lịch sử 30 ngày**: host ghi mỗi mốc giá SJC và mỗi lần tỷ giá đổi vào `sjc_history.json` (giữ 400 ngày). Trang ghép với giá khớp XAUUSDT theo giờ từ Binance (`/fapi/v1/klines`) để ra chênh lệch từng giờ. Lịch sử bắt đầu từ lần đầu trang gọi file PHP, cần ít nhất 1 ngày dữ liệu mới so sánh được.
- **Nhãn Chênh thấp / Chênh cao**: chênh hiện tại thấp hơn 75% số giờ trong kỳ là nhóm thấp, cao hơn 75% số giờ là nhóm cao.

Kiểm tra file PHP: mở `api/sjc-price.php?debug=1` để xem từng bước gọi BTMC và Vietcombank, `?refresh=1` để bỏ qua cache. Nếu debug báo lỗi curl 60 (host thiếu chứng chỉ CA), đổi `VERIFY_SSL` thành `false` ở đầu file.

## Trạng thái kết nối

- **Live**: đang nhận dữ liệu.
- **Dữ liệu chậm**: hơn 5 giây không có tin nhắn mới. Sau 30 giây trang tự kết nối lại.
- **Mất kết nối**: tự thử lại, thời gian chờ tăng dần tới tối đa 30 giây.
- **Không có dữ liệu**: đã kết nối nhưng 10 giây không có dữ liệu cho mã đang chọn.

Binance chặn truy cập từ một số khu vực (ví dụ Mỹ). Nếu trang không kết nối được khi đang dùng VPN, hãy đổi vị trí VPN.
