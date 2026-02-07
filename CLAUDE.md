# VSISPanel - Web Hosting Control Panel

## Mô tả dự án
VSISPanel là web hosting control panel tương tự Plesk Panel, viết bằng PHP/Laravel với giao diện CloudStack-inspired.

## Tech Stack Bắt Buộc
- **Backend:** Laravel 11.x, PHP 8.3+, MySQL 8.0, Redis 7.x
- **Frontend:** Vue 3 (Composition API) + Vite + Tailwind CSS 3 + Pinia
- **UI Style:** CloudStack-inspired (fixed sidebar 260px, top navbar, card-based dashboard)
- **i18n:** Vue I18n (multilingual support - vi, en default)
- **API:** RESTful với Laravel Sanctum, JSON Resources
- **Queue:** Laravel Horizon + Redis
- **WebSocket:** Laravel Reverb
- **Testing:** Pest PHP + Cypress

## Kiến Trúc Module
```
app/Modules/
├── Auth/          # Authentication, RBAC, 2FA
├── Server/        # Server info, service management
├── WebServer/     # Nginx/Apache virtual host config
├── Domain/        # Domain, subdomain management
├── Hosting/       # Plans, subscriptions, quotas
├── Database/      # MySQL database/user management
├── Mail/          # Postfix/Dovecot email system
├── DNS/           # PowerDNS zone/record management
├── SSL/           # Let's Encrypt + custom SSL
├── FTP/           # ProFTPD/Pure-FTPd accounts
├── FileManager/   # Web file manager
├── Backup/        # Restic/Borg backup/restore
├── Firewall/      # UFW/Fail2Ban management
├── Monitoring/    # Prometheus metrics, alerts
├── Cron/          # Cron job manager
└── Marketplace/   # Plugin system
```

## Cấu Trúc Mỗi Module
```
app/Modules/{ModuleName}/
├── Models/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Services/          # Business logic + system commands
├── Policies/
├── Events/
├── Listeners/
├── Providers/
│   └── {ModuleName}ServiceProvider.php
├── Routes/
│   ├── api.php
│   └── web.php
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Config/
│   └── {module}.php
└── Tests/
    ├── Unit/
    └── Feature/
```

## Coding Conventions
- PHP: PSR-12, declare(strict_types=1), type hints mọi nơi
- Vue: Composition API + `<script setup>`, Tailwind utility classes
- API Response format chuẩn:
  ```json
  {"success": true, "data": {}, "message": ""}
  {"success": false, "error": {"code": "ERROR_CODE", "message": ""}}
  ```
- Database: snake_case, UUID primary keys, soft deletes, timestamps
- Tên file test: `{Feature}Test.php`, dùng Pest syntax

## Design System (CloudStack-Inspired)
- **Colors:** Primary #1A5276, Secondary #2ECC71, Danger #E74C3C, BG Light #F8F9FA, BG Dark #1A1A2E
- **Layout:** Fixed sidebar (260px collapsible) + Top navbar (56px) + Content area
- **Typography:** Inter font, 14px base
- **Components:** Cards, DataTable, Modal, Drawer, Toast, Breadcrumb, StatusBadge
- **Dark Mode:** CSS variables toggle, localStorage preference
- **i18n:** Vue I18n, support vi/en (default vi), language switcher trong user dropdown, localStorage preference

## Workflow Files
Xem chi tiết từng phase trong thư mục `docs/workflows/`:
- `Phase1_Foundation.md` - Nền tảng (Tuần 1-6)
- `Phase2_WebHosting.md` - Web Hosting Core (Tuần 7-12)
- `Phase3_EmailDNS.md` - Email & DNS (Tuần 13-18)
- `Phase4_Security.md` - Security & Backup (Tuần 19-24)
- `Phase5_Advanced.md` - Advanced Features (Tuần 25-30)
- `Phase6_Launch.md` - Polish & Launch (Tuần 31-36)

## Quy Tắc Quan Trọng
1. Mỗi module PHẢI có ServiceProvider đăng ký trong config/app.php
2. System commands (nginx, mysql, etc.) PHẢI chạy qua Service layer, KHÔNG trực tiếp trong Controller
3. Mọi config change PHẢI backup trước khi apply
4. Mọi action PHẢI ghi audit log
5. API endpoints PHẢI có FormRequest validation
6. Vue components PHẢI support dark mode
7. Vue components PHẢI support multilingual (i18n) - KHÔNG hardcode text
8. KHÔNG hardcode paths, dùng config()
