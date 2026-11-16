<?php

return [
    'required' => ':attribute là thông tin bắt buộc.', 'email' => ':attribute phải là email hợp lệ.',
    'unique' => ':attribute đã được sử dụng.', 'confirmed' => 'Xác nhận :attribute chưa khớp.',
    'string' => ':attribute phải là chuỗi ký tự.', 'integer' => ':attribute phải là số nguyên.',
    'boolean' => ':attribute phải có giá trị có/không.', 'in' => ':attribute không hợp lệ.',
    'array' => ':attribute không đúng cấu trúc được hỗ trợ.', 'accepted' => 'Bạn cần xác nhận :attribute.',
    'uuid' => ':attribute không hợp lệ. Vui lòng mở lại giỏ hàng.', 'regex' => ':attribute không đúng định dạng.',
    'not_regex' => ':attribute không được chỉ gồm khoảng trắng.',
    'alpha_dash' => ':attribute chỉ dùng chữ, số, dấu gạch ngang và gạch dưới.',
    'distinct' => ':attribute không được trùng lặp.', 'present' => 'Cần có trường :attribute.',
    'required_with' => ':attribute là thông tin bắt buộc khi nhập :values.',
    'min' => ['string' => ':attribute cần ít nhất :min ký tự.', 'numeric' => ':attribute phải từ :min trở lên.', 'array' => ':attribute cần ít nhất :min mục.'],
    'max' => ['string' => ':attribute tối đa :max ký tự.', 'numeric' => ':attribute không được vượt :max.', 'array' => ':attribute tối đa :max mục.'],
    'attributes' => ['name' => 'Tên', 'email' => 'Email', 'password' => 'Mật khẩu', 'recipient' => 'Người nhận',
        'phone' => 'Số điện thoại', 'address' => 'Địa chỉ', 'simulation_ack' => 'đây là đơn mô phỏng',
        'accept_warnings' => 'đã đọc các cảnh báo', 'budget' => 'Ngân sách', 'purpose' => 'Nhu cầu',
        'price' => 'Giá', 'data_source' => 'Nguồn dữ liệu', 'title' => 'Tiêu đề', 'content' => 'Nội dung',
        'excerpt' => 'Tóm tắt', 'min_ram_gb' => 'Mục tiêu RAM', 'min_storage_gb' => 'Mục tiêu lưu trữ'],
];
