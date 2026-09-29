# Gold Perp Live

Dashboard xem dữ liệu live của hợp đồng vàng, bạc và dầu vĩnh cửu, gồm 5 tab:

- **XAUUSDT**: hai sub tab. **Bybit** (mặc định): giá khớp, mark price, funding và đếm ngược, funding theo vị thế. **Binance**: cùng các mục đó cho XAUUSDT Binance, kèm chênh lệch Bybit − Binance; dữ liệu Binance chỉ tải khi bấm vào sub tab này. Bên dưới là Bybit XAU so với forex kèm cảnh báo, SJC so với thế giới và lịch sử funding 14 ngày của sàn đang chọn.
- **XAGUSDT** (link `#xag`): bạc, cùng cách bố trí với tab XAUUSDT: sub tab **Bybit** (chính) và **Binance** (tải khi bấm), XAGUSDT Bybit so với giá bạc forex XAG/USD kèm cảnh báo trên trang, lịch sử funding 14 ngày của sàn đang chọn. Chỉ tải dữ liệu khi mở tab (hoặc khi đang bật cảnh báo).
- **PAXGUSDT Binance** (link `#paxg`): giá khớp, mark price, funding, funding theo vị thế, PAXGUSDT và token PAXG so với forex kèm cảnh báo, PAXG − forex theo giờ, lịch sử funding 14 ngày.
- **Dầu WTI** (link `#dau`): CLUSDT trên Bybit và Binance, giá, funding, chênh lệch hai sàn, funding theo vị thế (số thùng) và lịch sử funding 7 / 30 ngày. Chỉ tải dữ liệu khi mở tab này.
- **So sánh** (link `#so-sanh`): funding 3 sàn (Binance, Bybit, Hyperliquid), chênh lệch giá XAU − PAXG và XAU − forex trên Binance kèm phân tích từng phút, so sánh funding XAUUSDT với PAXGUSDT trên Binance.

## Cách dùng

Upload nguyên thư mục `gold-perp-live/` lên host PHP rồi mở `https://<tên-miền>/gold-perp-live/`. Trang chính là `index.html` nên web server tự mở khi vào link thư mục.

Bản đóng gói sẵn: `gold-perp-live.zip` ở thư mục gốc repo. Gói không có `api/alert-config.php` (chứa kênh ntfy và cron key), nên upload đè lên thư mục cũ trên host thì cấu hình đang dùng vẫn giữ nguyên.

```
gold-perp-live/
├── index.html          trang dashboard
├── README.md
└── api/
    ├── sjc-price.php   lấy giá SJC và tỷ giá cho trang
    ├── fx-price.php    lấy giá XAU/USD (hoặc XAG/USD với ?inst=XAG) trên sàn forex (Swissquote) cho trang
    ├── fx-lib.php      phần lấy giá forex dùng chung
    ├── alert-check.php cảnh báo Bybit XAU − forex và PAXG − forex qua ntfy, ghi chênh lệch theo giờ, chạy bằng cron
    ├── alert-config.example.php   cấu hình mẫu cho cảnh báo
    ├── spread-lib.php  ghi nhật ký XAU/PAXG − giá Swissquote theo giờ
    ├── spread-history.php   đọc nhật ký đó cho trang
    └── exchanges.php   giá, funding và lịch sử funding hợp đồng vàng trên Bybit và Hyperliquid
```

Thư mục `api/` cần quyền ghi. Các file PHP tự tạo `sjc_state.json`, `sjc_history.json`, `sjc.lock`, `fx_state.json`, `fx.lock`, `fx_state_xag.json`, `fx_xag.lock`, `alert_state.json`, `alert.lock`, `site.json` (link trang, dùng cho thông báo) `spread_history.json` (chênh lệch theo giờ), `exchanges_live.json`, `exchanges_hist.json` và `exchanges.lock` (dữ liệu Bybit, Hyperliquid) trong đó.

Nếu mở `index.html` trực tiếp trên máy thì phần Binance và giá Bybit live vẫn chạy, riêng lịch sử funding Bybit, giá forex và khung SJC báo cần mở từ host.

Trang nhớ tab và sub tab mở gần nhất. Link có `#xag` mở thẳng tab XAGUSDT, `#paxg` mở tab PAXGUSDT Binance, `#dau` mở tab Dầu WTI, `#so-sanh` mở tab So sánh.

Trang chỉ nhận dữ liệu của phần đang xem: sub tab Binance (mark, funding, 24h của XAUUSDT Binance) và tab Dầu WTI mở thêm luồng khi bấm vào và đóng luồng khi rời đi. Giá khớp XAUUSDT Binance vẫn luôn nhận vì khung SJC và tab So sánh cần.

## Dữ liệu hiển thị

**XAUUSDT Bybit** (khung chính tab XAUUSDT), WebSocket `wss://stream.bybit.com/v5/public/linear`, chủ đề `tickers.XAUUSDT`:

| Mục | Nguồn |
| --- | --- |
| Giá khớp, % thay đổi 24h, mark price, funding rate, giờ funding tiếp theo, chu kỳ funding | Bản đầu là snapshot đủ trường, sau đó Bybit chỉ gửi các trường vừa đổi nên trang ghép dần. Trang ping 20 giây một lần để giữ kết nối |
| Dự phòng khi chưa kết nối được Bybit | Giá và funding lấy qua host (`api/exchanges.php`, cập nhật mỗi phút), trang hiện thông báo |
| CLUSDT (tab Dầu WTI) | Chủ đề `tickers.CLUSDT` trên cùng kết nối, chỉ đăng ký khi mở tab |

**Binance**, WebSocket `wss://fstream.binance.com/market/stream`:

| Mục | Nguồn |
| --- | --- |
| Giá khớp gần nhất | `<symbol>@aggTrade`, luôn nhận cả XAUUSDT và PAXGUSDT |
| % thay đổi 24h (PAXGUSDT) | `paxgusdt@ticker` |
| Mark price, funding rate, giờ funding tiếp theo | `paxgusdt@markPrice@1s` (tab PAXGUSDT, kèm index = giá token PAXG); `xauusdt@markPrice@1s` và `xauusdt@ticker` chỉ khi mở sub tab Binance; `clusdt@aggTrade`, `clusdt@markPrice@1s`, `clusdt@ticker` chỉ khi mở tab Dầu WTI. Trang thêm và bớt luồng bằng lệnh `SUBSCRIBE` / `UNSUBSCRIBE` trên kết nối đang chạy |
| Chu kỳ funding | REST `GET /fapi/v1/fundingInfo`, mặc định 8h nếu không lấy được |

Chung cho cả hai sàn:

- **Funding quy năm** = `funding rate × (24 / chu kỳ) × 365`.
- **Funding theo vị thế** (USDT mỗi kỳ, mỗi ngày, mỗi năm) = `mark × khối lượng × |funding rate|`, nhân số kỳ mỗi ngày và 365 ngày. Ô khối lượng ở tab XAUUSDT và tab PAXGUSDT là một (mặc định 1 oz), sửa ô nào ô kia theo.
- **Sub tab Binance**: giá khớp, mark, funding, funding theo vị thế của XAUUSDT Binance, kèm chênh lệch Bybit − Binance về giá, mark và funding quy năm (dương là bên Short trên Bybit có lợi hơn về funding).


## Lịch sử funding 14 ngày

Một khung dùng chung: ở tab XAUUSDT theo sub tab đang chọn (**Bybit** hoặc **Binance**), ở tab PAXGUSDT là PAXGUSDT Binance. Tự tải lại sau mỗi lần chốt funding.

- Binance: REST `GET /fapi/v1/fundingRate` (thời điểm chốt, funding rate, mark price lúc chốt).
- Bybit: qua `api/exchanges.php?days=14`. Host lấy `/v5/market/funding/history` và mark price theo giờ `/v5/market/mark-price-kline` để có mark lúc chốt từng kỳ. Kỳ nào thiếu mark thì dùng mark hiện tại và đánh dấu ≈.

- USDT mỗi kỳ = `funding rate × mark lúc chốt × khối lượng`. Khối lượng dùng chung ô ở mục Funding theo vị thế (mặc định 1 oz).
- Số liệu tính cho bên Short: dương là nhận, âm là trả. Bên Long luôn ngược dấu vì funding chỉ chuyển giữa hai bên. Tổng mỗi ngày là số ròng.
- Ngày chia theo giờ máy, 14 ngày gồm hôm nay. Cột của hôm nay vẽ mờ vì chưa đủ kỳ, và trung bình mỗi ngày chỉ tính các ngày đủ.
- Bảng xem theo ngày (số kỳ, tổng rate, tổng USDT) hoặc theo từng kỳ (rate, mark lúc chốt, USDT).

## Funding 3 sàn

Tab So sánh. Bảng live chia theo tài sản gốc:

| Nhóm | Binance | Bybit | Hyperliquid |
| --- | --- | --- | --- |
| Vàng giao ngay | XAUUSDT | XAUUSDT | xyz:GOLD (thị trường HIP-3 của TradeXYZ) |
| Token PAXG | PAXGUSDT | PAXGUSDT | PAXG |

- Binance lấy trực tiếp (`/fapi/v1/premiumIndex`, `/fapi/v1/fundingRate`). Bybit (`/v5/market/tickers`, `/v5/market/funding/history`, `/v5/market/mark-price-kline`) và Hyperliquid (`metaAndAssetCtxs`, `fundingHistory`) lấy qua `api/exchanges.php` trên host, cache 30 giây cho số liệu hiện tại và 30 phút cho lịch sử (lịch sử tải lại sớm hơn ngay sau mỗi kỳ funding của Bybit). Host phải truy cập được Bybit và Hyperliquid (Bybit chặn IP Mỹ). Giá Bybit XAUUSDT trong bảng lấy theo WebSocket nếu có.
- Mỗi sàn chốt funding theo chu kỳ riêng (Hyperliquid mỗi giờ), nên bảng quy về **cùng 8 giờ** và **theo năm**. Có thêm USDT/ngày cho khối lượng đang nhập (bên Short), kỳ funding tới, giá và độ lệch so với XAUUSDT Binance. Ô tô đậm là funding quy năm cao nhất trong nhóm.
- Lịch sử 7 hoặc 30 ngày: funding thực tế trung bình quy năm của từng hợp đồng, và biểu đồ tổng funding mỗi ngày của 3 sàn cho từng nhóm.
- Kiểm tra: mở `api/exchanges.php?debug=1` để xem dữ liệu thô từng lần gọi Bybit và Hyperliquid. Nếu sàn nào đổi định dạng, gửi nội dung trang debug để sửa.

## XAGUSDT (bạc)

Tab `#xag`, giống tab XAUUSDT nhưng cho hợp đồng bạc XAGUSDT (giá USDT cho 1 troy ounce bạc).

- **Sub tab Bybit** (mặc định, WebSocket `tickers.XAGUSDT`): giá khớp, % 24h, mark, funding, đếm ngược, funding theo vị thế. Ô khối lượng bạc (oz) riêng với vàng, dùng chung cho hai sub tab.
- **Sub tab Binance**: cùng các mục đó cho XAGUSDT Binance (`xagusdt@markPrice@1s`, `xagusdt@ticker`, chỉ mở khi bấm), kèm chênh lệch Bybit − Binance về giá, mark và funding quy năm.
- **XAGUSDT Bybit so với forex**: Bybit XAG − forex và Binance XAG − forex, giá bạc XAG/USD của Swissquote qua `api/fx-price.php?inst=XAG` (cache 5 giây, trang lấy lại mỗi 10 giây).
- **Cảnh báo Bybit XAG − forex** trên trang: mặc định **≥ 0.3** hoặc **< −0.3** USDT, ra khỏi vùng 0.05 USDT mới báo lại, dùng chung nút bật/tắt với các cảnh báo khác. Khi đang bật cảnh báo, trang nhận giá bạc cả khi xem tab khác. Chưa có cảnh báo bạc qua điện thoại.
- **Lịch sử funding 14 ngày** theo sub tab đang chọn (Bybit qua `api/exchanges.php`, có XAGUSDT trong danh sách mã Bybit).

## Dầu WTI

Tab `#dau`, so sánh **CLUSDT** (hợp đồng vĩnh cửu theo hợp đồng tương lai dầu thô WTI, giá USDT cho 1 thùng) trên Bybit và Binance. Hai sàn chốt funding mỗi 4 giờ và giao dịch 24/7.

- **Live**: giá khớp, % 24h, mark, funding kỳ này, funding quy năm và đếm ngược tới kỳ sau của từng sàn; chênh lệch Bybit − Binance về giá khớp, mark và funding quy năm.
- **Funding theo vị thế**: nhập số thùng (mặc định 1, trang nhớ lại). Bảng USDT mỗi kỳ, mỗi ngày, mỗi năm của từng sàn cho bên Short (dương là nhận).
- **Lịch sử funding 7 / 30 ngày**: funding trung bình quy năm, tổng rate, tổng USDT cho số thùng đang nhập (theo mark lúc chốt từng kỳ), số kỳ và % kỳ dương của từng sàn; biểu đồ và bảng tổng funding mỗi ngày của hai sàn. Tự tải lại 1 phút sau mỗi kỳ funding.
- Nguồn: Binance trực tiếp (`/fapi/v1/fundingRate`, WebSocket), Bybit qua WebSocket và `api/exchanges.php` (CLUSDT có trong danh sách mã Bybit của file này).

## So sánh funding XAUUSDT và PAXGUSDT

Tải toàn bộ lịch sử funding của 2 mã từ 05/01/2026 (ngày XAUUSDT ra mắt), tự tải lại sau mỗi lần chốt funding.

- So theo **tổng funding rate mỗi ngày** (bên Short) vì 2 mã có thể chốt funding ở giờ khác nhau. Không tính hôm nay và ngày XAUUSDT ra mắt vì chưa đủ kỳ.
- Hiện số ngày PAXGUSDT cao hơn, lần gần nhất, trung bình mỗi ngày của từng mã (kèm USDT cho khối lượng đang nhập), biểu đồ chênh lệch XAU − PAXG mỗi ngày và bảng các ngày PAXGUSDT cao hơn.

## Chênh lệch giá

So theo **giá khớp** gần nhất và giá XAU/USD trên sàn forex. Chia theo tab:

- Tab XAUUSDT: **Bybit XAU − forex** (chính) và Binance XAU − forex, kèm cảnh báo Bybit XAU − forex.
- Tab PAXGUSDT Binance: **PAXGUSDT − forex** và **token PAXG − forex** (index PAXGUSDT, giá token trên các sàn giao ngay; có thêm PAXGUSDT − token), kèm cảnh báo PAXG − forex và biểu đồ PAXG − forex theo giờ.
- Tab So sánh: **XAU − PAXG** và **XAU − forex** trên Binance, biểu đồ theo giờ và phân tích từng phút.

Chi tiết:

- **Giá forex**: `api/fx-price.php` lấy bid/ask XAU/USD từ feed công khai của Swissquote, cache 5 giây; trang lấy lại mỗi 10 giây và dùng giá giữa (bid + ask) / 2. Feed này không có tài liệu chính thức và chặn gọi thẳng từ trình duyệt nên phải đi qua host. IC Markets không có API giá công khai (chỉ có cTrader Open API / FIX cần tài khoản) nên dùng Swissquote làm đại diện. Kiểm tra bằng `api/fx-price.php?debug=1`.
- Giá forex cũ hơn 3 phút thì trang báo forex đang nghỉ (17:00 thứ Sáu đến 18:00 Chủ nhật giờ New York). Lúc đó Binance vẫn chạy nên XAU − forex không phản ánh chênh lệch thật.
- **Chênh lệch theo giờ** (giá Binance): 3 biểu đồ XAU − PAXG, XAU − forex (tab So sánh) và PAXG − forex (tab PAXGUSDT), chọn 7, 30 (mặc định), 90 ngày hoặc từ 05/01/2026, lựa chọn ở hai tab luôn giống nhau, tự tải lại mỗi giờ. Mỗi biểu đồ có đường 0 (trên 0 tô xanh), thống kê % số giờ dương, trung bình, dương/âm lớn nhất kèm thời điểm và khoảng 90% số giờ nằm trong.
  - XAU − PAXG: nến 1 giờ giá khớp của 2 mã (`/fapi/v1/klines`).
  - XAU − forex, PAXG − forex: giá vàng giao ngay cho quá khứ lấy theo **index XAUUSDT** (`/fapi/v1/indexPriceKlines`, Binance tổng hợp từ các nhà cung cấp dữ liệu vàng). Giờ nào host có ghi thì dùng **giá Swissquote thật** (vùng nền xám trên biểu đồ), kèm cao/thấp trong giờ. Host ghi mỗi lần cron `alert-check.php` chạy khi forex đang giao dịch (`spread_history.json`, giữ 400 ngày). Không tính giờ thị trường vàng nghỉ: 17:00 thứ Sáu tới 18:00 Chủ nhật và 17:00–18:00 mỗi ngày (giờ New York); ngày lễ chưa được loại.
- **Phân tích một thời điểm**: bấm vào một điểm trên biểu đồ theo giờ (từ tab PAXGUSDT thì trang chuyển sang tab So sánh) để xem từng phút từ 2 giờ trước tới 1 giờ sau thời điểm đó, gồm XAUUSDT, PAXGUSDT, token PAXG (index PAXGUSDT, giá token trên các sàn giao ngay) và vàng giao ngay (index XAUUSDT), cùng funding kỳ gần nhất. Tại phút chênh XAU − PAXG lớn nhất, trang tách chênh lệch thành 3 phần và ghi phần nào chiếm nhiều nhất:

  ```
  XAU − PAXG = (XAUUSDT − vàng) − (token PAXG − vàng) − (PAXGUSDT − token PAXG)
  ```

  Nếu lúc đó thị trường vàng đang nghỉ, trang ghi chú phần "lệch khỏi vàng giao ngay" kém tin cậy vì index XAUUSDT tính theo sổ lệnh.

### Cảnh báo trên trang

Hai cảnh báo, mỗi cái có ngưỡng, vùng và nhật ký riêng; nút bật/tắt dùng chung:

| Cảnh báo | Nằm ở | Giá so với forex | Ngưỡng mặc định |
| --- | --- | --- | --- |
| Bybit XAU − forex | Tab XAUUSDT | Giá khớp XAUUSDT Bybit | **≥ 7** hoặc **< 2** USDT |
| PAXG − forex | Tab PAXGUSDT Binance | Giá khớp PAXGUSDT Binance | **≥ 20** hoặc **< −20** USDT |

- Trang nhớ lại ngưỡng và trạng thái bật.
- Báo **một lần** khi chênh lệch vừa vào vùng; phải ra khỏi vùng 0.5 USDT mới báo lại. Không báo khi forex đang nghỉ.
- Khi bật: thanh cảnh báo ở đầu trang, 2 tiếng bíp, và thông báo của trình duyệt (cần cho phép, trang phải chạy qua https). Âm thanh chỉ phát sau khi đã bấm vào trang ít nhất một lần kể từ lúc mở. Khi tắt vẫn ghi nhật ký 10 lần gần nhất.
- Chỉ chạy khi trang đang mở. Trên điện thoại, khoá màn hình hoặc chuyển app có thể làm trình duyệt tạm dừng trang. Muốn nhận cả khi tắt trang, xem phần cảnh báo qua ntfy bên dưới.

### Cảnh báo 24/7 qua ntfy

`api/alert-check.php` chạy bằng cron trên host: lấy giá khớp XAUUSDT trên Bybit, PAXGUSDT trên Binance và giá forex (Swissquote), tính **Bybit XAU − forex** (ngưỡng `high` / `low`) và **PAXG − forex** (ngưỡng `paxg_high` / `paxg_low`) rồi gửi thông báo về điện thoại qua [ntfy.sh](https://ntfy.sh) (miễn phí, không cần tài khoản). Cách báo giống trên trang: mỗi cảnh báo báo một lần khi vừa vào vùng, ra khỏi vùng `gap` USDT mới báo lại, không báo khi forex nghỉ. Nguồn giá nào (Bybit, Binance, Swissquote) lỗi 3 lần liền thì báo lỗi một lần, lấy lại được thì báo chạy lại. Mỗi lần chạy khi forex mở còn ghi chênh lệch theo giờ (giá Binance, kèm giá Bybit) vào `spread_history.json`.

1. **Cài app ntfy** trên điện thoại (Google Play hoặc App Store). Bấm **+**, nhập một tên kênh dài và khó đoán (ví dụ `gold-7f3k9q2x`), giữ server mặc định `ntfy.sh`. Ai biết tên kênh cũng đọc được thông báo.
2. **Tạo file cấu hình** trên host: chép `api/alert-config.example.php` thành `api/alert-config.php`, sửa `ntfy_topic` trùng tên kênh ở bước 1, chỉnh ngưỡng `high` / `low` (Bybit XAU) và `paxg_high` / `paxg_low` (PAXG) nếu muốn. File cấu hình cũ chưa có `paxg_high` / `paxg_low` thì dùng mặc định 20 / −20. Khi cập nhật tool, **đừng ghi đè** `alert-config.php`.
   - `page_url` để trống thì tự dùng link trang dashboard: lần đầu trang được mở trên host, `fx-price.php` ghi link vào `api/site.json`. Chỉ ghi lần đầu để request giả tên miền không đổi được link; muốn đổi thì xoá `site.json` hoặc điền `page_url`.
3. **Gửi thử**: mở trang trên host, bấm **Gửi thử lên điện thoại** trong khung cảnh báo (gọi `api/alert-check.php?test=1`, tối đa 1 lần mỗi phút, phản hồi không lộ tên kênh). Kết quả cho biết đã gửi chưa, cron chạy lần cuối lúc nào và bấm vào thông báo sẽ mở link nào. Cũng có thể chạy `php /đường-dẫn/gold-perp-live/api/alert-check.php test`. Điện thoại nhận tin "Thử cảnh báo Gold Perp Live" là xong.
4. **Cài cron** (cPanel → Cron Jobs), chạy mỗi phút hoặc mỗi 2–5 phút tuỳ host cho phép:

   ```
   php /home/<user>/public_html/gold-perp-live/api/alert-check.php >/dev/null 2>&1
   ```

   Nếu host không cho chạy lệnh `php`, dùng link web có key:

   ```
   curl -s "https://<tên-miền>/gold-perp-live/api/alert-check.php?key=<cron_key>" >/dev/null 2>&1
   ```

- Host phải truy cập được Bybit và Binance. Host đặt ở Mỹ sẽ bị chặn (Bybit lỗi 403, Binance lỗi 451), khi đó ntfy sẽ báo "Cảnh báo Bybit XAU − forex tạm ngừng" hoặc "Cảnh báo PAXG − forex tạm ngừng".
- Ngưỡng trên host (trong `alert-config.php`) và ngưỡng trên trang là hai chỗ riêng.
- Chạy tay không kèm `test` sẽ in kết quả lần kiểm tra (giá, chênh lệch, vùng, đã gửi hay chưa) để xem nhanh.

## SJC so với thế giới

Khung này dùng để canh lúc chênh lệch giữa giá vàng SJC và giá thế giới đang thấp.

- **Giá SJC**: `api/sjc-price.php` lấy từ API công khai của BTMC (dòng `VÀNG MIẾNG SJC`). BTMC niêm yết theo chỉ nên nhân 10 ra lượng. Host gọi BTMC tối đa 15 phút một lần (5 phút nếu lần trước lỗi), trang hỏi host 10 phút một lần.
- **Tỷ giá**: USD bán ra của Vietcombank (API JSON, dự phòng bằng file XML). Lỗi thì dùng tỷ giá lấy lúc trước và ghi rõ trên trang.
- **Giá thế giới quy đổi** = giá khớp XAUUSDT **Binance** × 1.20565 × tỷ giá. 1 lượng = 37.5 g, 1 troy oz = 31.1035 g.
- **Chênh lệch bán ra** = SJC bán ra − giá thế giới quy đổi (giá bạn trả khi mua). Có thêm chênh lệch mua vào để thấy khoản mất nếu bán lại ngay.
- **Lịch sử 30 ngày**: host ghi mỗi mốc giá SJC và mỗi lần tỷ giá đổi vào `sjc_history.json` (giữ 400 ngày). Trang ghép với giá khớp XAUUSDT theo giờ từ Binance (`/fapi/v1/klines`) để ra chênh lệch từng giờ. Lịch sử bắt đầu từ lần đầu trang gọi file PHP, cần ít nhất 1 ngày dữ liệu mới so sánh được.
- **Nhãn Chênh thấp / Chênh cao**: chênh hiện tại thấp hơn 75% số giờ trong kỳ là nhóm thấp, cao hơn 75% số giờ là nhóm cao.

Kiểm tra file PHP: mở `api/sjc-price.php?debug=1` để xem từng bước gọi BTMC và Vietcombank, `?refresh=1` để bỏ qua cache. Nếu debug báo lỗi curl 60 (host thiếu chứng chỉ CA), đổi `VERIFY_SSL` thành `false` ở đầu file.

## Trạng thái kết nối

Đầu trang có hai ô trạng thái, Bybit và Binance:

- **Live**: đang nhận dữ liệu.
- **Dữ liệu chậm** (Binance): hơn 5 giây không có tin nhắn mới. Sau 30 giây trang tự kết nối lại. Bybit chỉ gửi khi giá đổi nên trang dựa vào phản hồi ping: 45 giây không có gì thì kết nối lại.
- **Mất kết nối**: tự thử lại, thời gian chờ tăng dần tới tối đa 30 giây.
- **Không có dữ liệu**: đã kết nối nhưng 10 giây không có dữ liệu.

Bybit và Binance chặn truy cập từ một số khu vực (ví dụ Mỹ). Nếu trang không kết nối được khi đang dùng VPN, hãy đổi vị trí VPN.
