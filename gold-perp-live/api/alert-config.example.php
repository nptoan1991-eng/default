<?php
/**
 * Cấu hình cảnh báo XAUUSDT − giá forex qua ntfy.sh.
 * Chép file này thành alert-config.php (cùng thư mục) rồi sửa.
 * Khi cập nhật tool, đừng ghi đè alert-config.php để giữ cấu hình của bạn.
 */
return [
    // Tên kênh ntfy, trùng với tên đã đăng ký trong app ntfy trên điện thoại.
    // Ai biết tên kênh cũng đọc được thông báo, nên đặt dài và khó đoán.
    'ntfy_topic' => 'DOI-TEN-KENH-NAY',
    'ntfy_server' => 'https://ntfy.sh',
    'ntfy_token' => '',      // chỉ cần khi kênh có mật khẩu hoặc dùng server ntfy riêng

    'high' => 7,             // báo khi XAUUSDT − forex >= số này (USDT)
    'low' => 2,              // báo khi XAUUSDT − forex < số này (USDT)
    'gap' => 0.5,            // phải ra khỏi vùng bấy nhiêu USDT mới báo lại

    // Chỉ cần khi cron gọi qua link web (curl/wget): đặt một chuỗi bí mật
    // rồi thêm ?key=chuỗi-đó vào link. Để trống thì chỉ chạy được bằng lệnh php.
    'cron_key' => '',

    // Link mở ra khi bấm vào thông báo. Để trống thì tự dùng link trang dashboard,
    // ghi lại từ lần đầu trang được mở trên host (lưu trong api/site.json).
    'page_url' => '',
];
