# VSISPanel — Hướng Dẫn Sử Dụng Workflows với Claude Code

## 📋 Tổng Quan

Bộ workflows này gồm **48 tasks** chia thành **6 phases**, mỗi task có prompt sẵn sàng paste vào Claude Code.

| Phase | Tên | Tuần | Tasks | Mô tả |
|-------|-----|------|-------|-------|
| 1 | Foundation | 1-6 | 10 tasks | Project setup, Auth, RBAC, UI scaffold, Dashboard |
| 2 | Web Hosting Core | 7-12 | 8 tasks | Domain, Nginx, PHP, MySQL, SSL, File Manager |
| 3 | Email & DNS | 13-18 | 5 tasks | Postfix/Dovecot, Email accounts, PowerDNS, FTP |
| 4 | Security & Backup | 19-24 | 5 tasks | Firewall, Fail2Ban, WAF, Backup/Restore |
| 5 | Advanced Features | 25-30 | 7 tasks | Monitoring, Terminal, Cron, Reseller, Apps |
| 6 | Polish & Launch | 31-36 | 7 tasks | Testing, i18n, Installer, Docs, Launch |

## 🚀 Cách Sử Dụng

### Bước 1: Setup Project
```bash
mkdir vsispanel && cd vsispanel
# Copy toàn bộ thư mục này vào project root
cp -r <đường_dẫn>/CLAUDE.md .
cp -r <đường_dẫn>/docs/ ./docs/
```

### Bước 2: Mở Claude Code trong VSCode
- Mở thư mục `vsispanel/` trong VSCode
- Mở Claude Code extension
- Claude Code sẽ TỰ ĐỘNG đọc file `CLAUDE.md` ở root

### Bước 3: Thực hiện từng Task
1. Mở file Phase tương ứng (ví dụ: `docs/workflows/Phase1_Foundation.md`)
2. Tìm Task tiếp theo cần làm
3. Copy nội dung trong block `>>> PROMPT cho Claude Code:`
4. Paste vào Claude Code
5. Chờ Claude Code thực hiện
6. Kiểm tra kết quả theo mục "Kiểm tra hoàn thành"
7. Chạy tests: `php artisan test`
8. Commit code
9. Chuyển sang Task tiếp theo

### Bước 4: Verify mỗi Phase
Cuối mỗi Phase có block "Checklist Hoàn Thành" — paste vào Claude Code để verify.

## ⚡ Mẹo Sử Dụng Hiệu Quả

### DO ✅
- **Làm theo thứ tự** — tasks sau phụ thuộc vào tasks trước
- **Test sau mỗi task** — bắt lỗi sớm, fix dễ hơn
- **Commit thường xuyên** — mỗi task = 1 commit
- **Đọc output** — kiểm tra code Claude tạo trước khi tiếp tục
- **Sửa ngay lỗi nhỏ** — nói Claude: "Fix lỗi X trong file Y"

### DON'T ❌
- **Không paste nhiều tasks cùng lúc** — Claude sẽ bị quá tải context
- **Không skip tasks** — dependencies sẽ bị thiếu
- **Không quên chạy tests** — accumulated bugs rất khó fix
- **Không sửa code tay mà không thông báo Claude** — Claude có thể overwrite

### Khi Claude Code gặp lỗi
```
Nói: "Lỗi [mô tả lỗi] khi chạy [command]. Hãy xem file [đường dẫn] 
và fix lỗi. Sau đó chạy lại test để verify."
```

### Khi cần thay đổi thiết kế
```
Nói: "Tôi muốn thay đổi [feature] từ [hiện tại] sang [mong muốn]. 
Hãy cập nhật code theo CLAUDE.md conventions. Đảm bảo tests vẫn pass."
```

## 📁 Cấu Trúc Files

```
vsispanel/
├── CLAUDE.md                              ← Claude Code đọc tự động
├── docs/
│   └── workflows/
│       ├── Phase1_Foundation.md           ← Tasks 1.1 → 1.10
│       ├── Phase2_WebHosting.md           ← Tasks 2.1 → 2.8
│       ├── Phase3_EmailDNS.md             ← Tasks 3.1 → 3.5
│       ├── Phase4_Security.md             ← Tasks 4.1 → 4.5
│       ├── Phase5_Advanced.md             ← Tasks 5.1 → 5.7
│       └── Phase6_Launch.md               ← Tasks 6.1 → 6.7
├── app/
│   └── Modules/                           ← Code sẽ được tạo ở đây
├── resources/
│   └── js/                                ← Vue frontend
├── tests/                                 ← Test files
└── ...
```

## 🔄 Progress Tracker

Dùng bảng dưới để track tiến độ (đánh ✅ khi hoàn thành):

### Phase 1: Foundation
- [ ] Task 1.1: Khởi tạo Laravel Project
- [ ] Task 1.2: Module Autoloader
- [ ] Task 1.3: Database Schema Core
- [ ] Task 1.4: Authentication System
- [ ] Task 1.5: RBAC - Phân Quyền
- [ ] Task 1.6: Vue.js Frontend Scaffold
- [ ] Task 1.7: Dashboard Skeleton
- [ ] Task 1.8: System Command Executor
- [ ] Task 1.9: API Base Structure
- [ ] Task 1.10: CI/CD & Docker Dev Environment
- [ ] ✅ Phase 1 Checklist PASSED

### Phase 2: Web Hosting Core
- [ ] Task 2.1: Domain Management Module
- [ ] Task 2.2: Nginx Virtual Host Service
- [ ] Task 2.3: PHP-FPM Multi-Version Management
- [ ] Task 2.4: MySQL Database Management Module
- [ ] Task 2.5: Hosting Plans & Subscriptions
- [ ] Task 2.6: SSL Certificate Module
- [ ] Task 2.7: File Manager v1
- [ ] Task 2.8: Websites Page UI Hoàn Chỉnh
- [ ] ✅ Phase 2 Checklist PASSED

### Phase 3: Email & DNS
- [ ] Task 3.1: Postfix & Dovecot Service Integration
- [ ] Task 3.2: Email Account Management Module
- [ ] Task 3.3: Spam Filtering (Rspamd)
- [ ] Task 3.4: PowerDNS Integration & DNS Editor
- [ ] Task 3.5: FTP Account Management
- [ ] ✅ Phase 3 Checklist PASSED

### Phase 4: Security & Backup
- [ ] Task 4.1: Firewall Management Module
- [ ] Task 4.2: Fail2Ban Integration
- [ ] Task 4.3: ModSecurity WAF & Malware Scanner
- [ ] Task 4.4: Backup & Restore Module
- [ ] Task 4.5: Security Dashboard
- [ ] ✅ Phase 4 Checklist PASSED

### Phase 5: Advanced Features
- [ ] Task 5.1: Real-Time Server Monitoring
- [ ] Task 5.2: Alert System
- [ ] Task 5.3: Web SSH Terminal
- [ ] Task 5.4: Cron Job Manager
- [ ] Task 5.5: Reseller Module
- [ ] Task 5.6: Advanced File Manager v2
- [ ] Task 5.7: One-Click App Installer & API Docs
- [ ] ✅ Phase 5 Checklist PASSED

### Phase 6: Polish & Launch
- [ ] Task 6.1: Comprehensive Testing
- [ ] Task 6.2: Performance Optimization
- [ ] Task 6.3: Multi-Language Support (i18n)
- [ ] Task 6.4: Installation Wizard
- [ ] Task 6.5: User Documentation
- [ ] Task 6.6: Migration Tool & Plugin Marketplace
- [ ] Task 6.7: Final Polish & Launch
- [ ] ✅ FINAL LAUNCH CHECKLIST PASSED

---

**Chúc bạn build thành công VSISPanel! 🚀**
