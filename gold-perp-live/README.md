# Gold Perp Live

Dashboard xem dữ liệu live của hợp đồng vàng vĩnh cửu trên Binance USDⓈ-M Futures (`XAUUSDT`, `PAXGUSDT`).

## Cách dùng

Mở file `index.html` bằng trình duyệt (Chrome, Edge, Firefox, Safari). Không cần cài đặt, không cần API key.

Chọn hợp đồng ở góc trên bên phải. Lựa chọn được nhớ cho lần mở sau.

## Dữ liệu hiển thị

| Mục | Nguồn (WebSocket `wss://fstream.binance.com`) |
| --- | --- |
| Giá khớp gần nhất | `<symbol>@aggTrade` |
| % thay đổi 24h | `<symbol>@ticker` |
| Mark price, index price, funding rate, giờ funding tiếp theo | `<symbol>@markPrice@1s` |
| Basis (mark − index) | tính từ mark và index |
| Funding quy năm | `funding rate × (24 / chu kỳ) × 365` |
| Chu kỳ funding | REST `GET /fapi/v1/fundingInfo`, mặc định 8h nếu không lấy được |

Biểu đồ mark và index ghi lại từ lúc mở trang, giữ 15 phút gần nhất (mỗi giây một điểm).

## Trạng thái kết nối

- **Live**: đang nhận dữ liệu.
- **Dữ liệu chậm**: hơn 5 giây không có tin nhắn mới. Sau 30 giây trang tự kết nối lại.
- **Mất kết nối**: tự thử lại, thời gian chờ tăng dần tới tối đa 30 giây.
- **Không có dữ liệu**: đã kết nối nhưng 10 giây không có dữ liệu cho mã đang chọn.

Binance chặn truy cập từ một số khu vực (ví dụ Mỹ). Nếu trang không kết nối được khi đang dùng VPN, hãy đổi vị trí VPN.
