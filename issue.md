Add Domain có chọn SSL, Create Domain, Zone DNS đều không được tạo(cả trên server dev và server product test)
SSL Let's Encrypt lỗi không tạo được (Trên server product test)
Add remote nhưng khi tạo Backup Configuration lại không có(Trên server product test)
Thử Quick Deploy với Wordpress thành công nhưng database lại không được ghi record dẫn tới trong bảng Database không hiển thị(cả trên server dev và server product test)
Khi cài mới thì nên cho người dùng lựa chọn nhập admin email thay vì admin@vsispanel.local , admin password có thể cho phép nhập, nếu không nhập thì tạo ngẫu nhiên(Trên server product test)
Lỗi Enable Email "Failed to generate DKIM key: sudo: opendkim-genkey: command not found"(cả trên server dev và server product test)
Create Zone DNS bị lỗi No data available(cả trên server dev và server product test)
Thử Quick Deploy với Wordpress thành công, Có ghi vào bảng Databases nhưng không thấy user được tạo và dẫn tới "missing authentication token" khi mở phpmyadmin auto-login. Wordpress mới chỉ tải source code, cấu hình file wp-config chứ chưa tạo site và ghi vào database nên người dùng vẫn phải dùng