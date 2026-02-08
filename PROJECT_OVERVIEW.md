# 🚀 Nexus-IaaS - Complete Project Overview

**A Production-Grade, Self-Hosted Cloud Control Panel for Proxmox VE**

---

## 📊 Project Statistics

- **Total Files Created**: 30+
- **Lines of Code**: 5,000+
- **Languages**: PHP 8.2+, Python 3.10+, SQL, JavaScript, HTML/CSS
- **Architecture**: Queue-based, non-blocking, MVC pattern
- **License**: MIT License
- **Copyright**: © 2026 Krzysztof Siek

---

## 🎯 What is Nexus-IaaS?

Nexus-IaaS is a **production-ready Infrastructure as a Service platform** that allows you to:

- ✅ Manage Proxmox VE virtual machines through a beautiful web interface
- ✅ Handle user authentication, registration, and role-based access
- ✅ Track billing, balance, and resource usage
- ✅ Process VM operations asynchronously (create, start, stop, delete)
- ✅ Monitor everything with comprehensive audit logging
- ✅ Scale with a queue-based architecture that never blocks the UI

**Key Differentiator**: Unlike heavy frameworks, Nexus-IaaS is built with **Vanilla PHP 8.2+** using strict OOP principles, making it lightweight, fast, and easy to understand.

---

## 🏗️ Architecture Highlights

### The Three-Tier System

```
┌─────────────────────┐
│   WEB INTERFACE     │  ← Bootstrap 5 Dark Theme, AJAX Polling
│   (PHP 8.2+)        │
└─────────────────────┘
          │
          ▼
┌─────────────────────┐
│    TASK QUEUE       │  ← MySQL-based, ACID compliant
│   (MySQL/MariaDB)   │
└─────────────────────┘
          │
          ▼
┌─────────────────────┐
│   WORKER DAEMON     │  ← PHP CLI + Python Bridge
│  (Background Jobs)  │
└─────────────────────┘
          │
          ▼
┌─────────────────────┐
│   PROXMOX VE API    │  ← Infrastructure Layer
│  (Hypervisor)       │
└─────────────────────┘
```

### Why This Architecture?

1. **Non-Blocking UI**: When a user clicks "Create VM", the web interface immediately returns. The task is queued and processed in the background.

2. **Separation of Concerns**:
   - **PHP handles**: Web requests, database operations, user management
   - **Python handles**: Proxmox API calls (using `proxmoxer` library)
   - **MySQL handles**: Data persistence and task queue

3. **Fault Tolerance**: If a VM creation fails, the task is retried automatically (configurable retry limit).

4. **Scalability**: Add more worker daemons for parallel processing (future enhancement).

---

## 📁 Complete File Structure

```
nexus-iaas/
│
├── 📄 README.md                   # Main documentation (comprehensive)
├── 📄 QUICKSTART.md               # 10-minute setup guide
├── 📄 CHANGELOG.md                # Version history
├── 📄 STRUCTURE.md                # Project structure explained
├── 📄 LICENSE                     # MIT License
├── 📄 .env.example                # Environment template
├── 📄 .gitignore                  # Git ignore rules
├── 📄 install.sh                  # Automated installation script
├── 📄 nexus-iaas.service          # Systemd unit file
│
├── 📂 config/
│   └── Database.php               # PDO singleton + env loader
│
├── 📂 database/
│   └── schema.sql                 # Complete database schema (5 tables)
│
├── 📂 public/                     # Web root
│   ├── index.php                  # Main router
│   ├── api.php                    # REST API endpoint
│   ├── .htaccess                  # Apache security rules
│   └── assets/
│       └── css/
│           └── style.css          # Custom dark theme
│
├── 📂 scripts/
│   ├── proxmox_bridge.py          # Python Proxmox API bridge (300+ lines)
│   ├── worker.php                 # Background daemon (200+ lines)
│   └── requirements.txt           # Python dependencies
│
├── 📂 src/                        # Core business logic
│   ├── Auth.php                   # Authentication (400+ lines)
│   ├── Queue.php                  # Task queue manager (200+ lines)
│   ├── Instance.php               # VM management (350+ lines)
│   ├── Billing.php                # Billing system (200+ lines)
│   └── AuditLog.php               # Audit logging (150+ lines)
│
└── 📂 views/                      # HTML templates
    ├── dashboard.php              # Main dashboard (300+ lines)
    ├── login.php                  # Login page
    ├── register.php               # Registration page
    ├── billing.php                # Billing page
    ├── settings.php               # User settings
    └── partials/
        └── sidebar.php            # Reusable sidebar
```

**Total**: 30+ files, 5,000+ lines of production-ready code

---

## 🔐 Security Features

### 1. Authentication & Authorization
- ✅ **Argon2id password hashing** (memory-hard, resistant to GPU attacks)
- ✅ **Session fixation protection** (regenerate session ID periodically)
- ✅ **Secure session cookies** (HttpOnly, Secure, SameSite=Strict)
- ✅ **Role-based access control** (admin vs. regular user)
- ✅ **Ban/unban functionality** (prevent malicious users)

### 2. Input Validation & Sanitization
- ✅ **PDO prepared statements** (100% of SQL queries)
- ✅ **CSRF token protection** (all POST requests)
- ✅ **XSS prevention** (htmlspecialchars on all output)
- ✅ **Email validation** (filter_var with FILTER_VALIDATE_EMAIL)
- ✅ **Password strength** (minimum 8 characters enforced)

### 3. Infrastructure Security
- ✅ **Environment-based secrets** (.env file, never committed)
- ✅ **File permissions** (600 for .env, 755 for directories)
- ✅ **Apache security headers** (X-Frame-Options, CSP, etc.)
- ✅ **Directory listing disabled**
- ✅ **SSL/TLS support** (Let's Encrypt integration)

### 4. Audit & Compliance
- ✅ **Comprehensive logging** (every action tracked)
- ✅ **IP address tracking** (with proxy detection)
- ✅ **User agent logging**
- ✅ **Timestamp for all events**
- ✅ **90-day log retention** (configurable)

---

## 💡 Key Features Breakdown

### User Management
- Registration with email validation
- Secure login with Argon2id
- Session management with auto-regeneration
- Admin panel for user control
- Ban/unban functionality

### VM Management
- Create VMs with custom specs (vCPU, RAM, disk)
- Start/stop/delete operations
- Real-time status updates (AJAX polling every 2 seconds)
- Automatic IP allocation from pool
- Support for multiple OS templates

### Billing System
- User balance tracking (decimal precision)
- Automatic cost deduction on VM creation
- Hourly cost calculation for running VMs
- Transaction history with descriptions
- Admin balance management

### Task Queue
- MySQL-based queue (ACID compliant)
- Status tracking (pending → processing → completed/failed)
- Automatic retry mechanism (up to 3 times)
- JSON payload and result storage
- Cleanup of old tasks (30+ days)

### Audit Logging
- Every action logged (create, start, stop, delete, login, etc.)
- User context (user_id, IP address, user agent)
- Resource tracking (resource_type, resource_id)
- JSON details field for flexible data
- Search and filter capabilities

---

## 🎨 User Interface

### Design Principles
- **Dark Theme**: Easy on the eyes, modern aesthetic
- **Bootstrap 5**: Responsive, mobile-friendly
- **Bootstrap Icons**: Professional iconography
- **Real-time Updates**: AJAX polling (non-intrusive)
- **Modal-based Actions**: Clean, focused interactions
- **Status Badges**: Color-coded (green=running, yellow=pending, red=error)

### Pages
1. **Login/Register**: Clean, centered forms
2. **Dashboard**: Overview cards + instance table + create VM button
3. **Instances**: Detailed VM list with actions
4. **Billing**: Balance cards + transaction history
5. **Settings**: Account info + password change
6. **Admin Panel**: System-wide controls (admin only)

---

## 📡 API Design

### RESTful Endpoints

All API calls return JSON:

```json
{
    "success": true,
    "message": "Operation completed",
    "data": { ... },
    "timestamp": 1739001234
}
```

### Key Endpoints
- `POST /api.php?action=create_instance` → Queue VM creation
- `POST /api.php?action=start_instance` → Start VM
- `POST /api.php?action=stop_instance` → Stop VM
- `POST /api.php?action=delete_instance` → Delete VM
- `GET /api.php?action=get_instances` → List user VMs
- `GET /api.php?action=get_task_status` → Check task progress
- `GET /api.php?action=get_balance` → Fetch user balance
- `GET /api.php?action=ping` → Health check

### AJAX Polling Example

```javascript
// Poll every 2 seconds
setInterval(async () => {
    const response = await fetch('/api.php?action=get_instances');
    const result = await response.json();
    
    if (result.success) {
        result.data.forEach(instance => {
            updateInstanceStatus(instance.id, instance.status);
        });
    }
}, 2000);
```

---

## 🐍 Python Bridge

### How It Works

1. **Worker daemon** detects a pending task in the queue
2. **Constructs command**: `python proxmox_bridge.py --action create --vmid 101 --name test-vm ...`
3. **Python script** connects to Proxmox API using `proxmoxer`
4. **Executes operation** (create, start, stop, delete)
5. **Returns JSON** to stdout (parsed by PHP worker)
6. **Worker updates** database (instance status, task status)

### Bridge Actions

```python
# Create VM
python proxmox_bridge.py --action create --vmid 101 --name web-server \
    --vcpu 2 --ram 2048 --disk 40 --os-template ubuntu-22.04 \
    --ip-address 192.168.100.10 --gateway 192.168.100.1

# Start VM
python proxmox_bridge.py --action start --vmid 101

# Stop VM
python proxmox_bridge.py --action stop --vmid 101 --force

# Delete VM
python proxmox_bridge.py --action delete --vmid 101

# Get Status
python proxmox_bridge.py --action status --vmid 101
```

### Error Handling

```python
# All operations return JSON
{
    "success": true,
    "message": "VM created successfully",
    "vmid": 101,
    "task_id": "UPID:pve:00001234..."
}

# Or on error
{
    "success": false,
    "message": "VM creation failed",
    "error": "Insufficient storage on node"
}
```

---

## 🔄 Worker Daemon Flow

```php
while (true) {
    // 1. Check for pending tasks
    $task = Queue::pop();
    
    if (!$task) {
        sleep(5);  // No tasks, wait 5 seconds
        continue;
    }
    
    // 2. Build Python command
    $command = "python proxmox_bridge.py --action {$task['action']} ...";
    
    // 3. Execute and capture output
    exec($command, $output, $exitCode);
    
    // 4. Parse JSON result
    $result = json_decode(implode("\n", $output), true);
    
    // 5. Update database
    if ($result['success']) {
        Queue::complete($task['id'], $result);
        Instance::updateStatus($instanceId, 'running');
    } else {
        Queue::fail($task['id'], $result['message']);
        Instance::updateStatus($instanceId, 'error');
    }
    
    // 6. Log the action
    AuditLog::log($userId, "task_completed_{$action}", ...);
}
```

---

## 📊 Database Schema

### 5 Core Tables

1. **users** (8 columns)
   - Authentication, balance, admin flag
   - Indexes on: email, is_admin

2. **instances** (15 columns)
   - VM records with specs and status
   - Foreign key: user_id → users(id)
   - Indexes on: user_id, vmid, status

3. **task_queue** (12 columns)
   - Async task processing
   - JSON payload and result
   - Retry mechanism
   - Indexes on: status, action, created_at

4. **ip_pool** (7 columns)
   - Static IP allocation
   - Foreign key: allocated_to → users(id)
   - Indexes on: is_allocated, ip_address

5. **audit_logs** (9 columns)
   - Action tracking and compliance
   - Foreign key: user_id → users(id)
   - Indexes on: user_id, timestamp, action

### Relationships

```
users (1) ──────────────── (N) instances
users (1) ──────────────── (N) ip_pool (allocated_to)
users (1) ──────────────── (N) audit_logs
```

---

## 🚀 Performance Considerations

### Database Optimization
- ✅ Proper indexes on all foreign keys and frequently queried columns
- ✅ JSON columns for flexible data (task payloads, audit details)
- ✅ Transactions for atomic operations (balance deduction + instance creation)
- ✅ Connection pooling via PDO singleton

### Frontend Optimization
- ✅ Minimal dependencies (Bootstrap CDN only)
- ✅ Efficient AJAX polling (only updates changed data)
- ✅ Inline critical CSS for first paint
- ✅ Browser caching for static assets

### Worker Optimization
- ✅ Configurable sleep interval (default 5s, adjust based on load)
- ✅ Batch processing limit (max 10 tasks per cycle)
- ✅ Graceful shutdown (SIGTERM handling)
- ✅ Python virtual environment (isolated dependencies)

---

## 🔮 Future Enhancements (Roadmap)

### v1.1 (Planned)
- [ ] Multi-node Proxmox support (cluster management)
- [ ] VM backup and restore functionality
- [ ] Email notifications (creation, errors, low balance)
- [ ] Two-factor authentication (TOTP)
- [ ] API rate limiting (prevent abuse)

### v1.2 (Planned)
- [ ] VM resource monitoring (CPU, RAM, disk usage graphs)
- [ ] Automated billing cycles (hourly deductions via cron)
- [ ] Webhook support (external integrations)
- [ ] Advanced IP management (VLAN support)
- [ ] VM templates and presets

### v2.0 (Long-term)
- [ ] Kubernetes cluster management
- [ ] Container support (LXC/Docker)
- [ ] Multi-tenancy (organizations, teams)
- [ ] Billing invoices and receipts
- [ ] Advanced RBAC (custom roles/permissions)

---

## 📚 Documentation Files

| File | Purpose | Audience |
|------|---------|----------|
| `README.md` | Comprehensive documentation | All users |
| `QUICKSTART.md` | 10-minute setup guide | New users |
| `CHANGELOG.md` | Version history | Developers |
| `STRUCTURE.md` | Project structure explained | Developers |
| `LICENSE` | MIT License terms | Legal |

---

## 🎓 Learning Value

This project demonstrates:

1. **Clean PHP Architecture**: MVC without frameworks
2. **Security Best Practices**: OWASP Top 10 compliance
3. **Database Design**: Normalization, indexes, foreign keys
4. **Async Processing**: Queue-based task management
5. **API Design**: RESTful JSON endpoints
6. **System Integration**: Python ↔ PHP ↔ Proxmox
7. **DevOps**: Systemd services, automation scripts
8. **Frontend**: Modern responsive design
9. **Documentation**: Professional technical writing

---

## 🏆 Production Readiness Checklist

- ✅ **Security**: Argon2id, CSRF, prepared statements, secure sessions
- ✅ **Logging**: Comprehensive audit trail with retention policy
- ✅ **Error Handling**: Try-catch blocks, graceful degradation
- ✅ **Documentation**: README, quickstart, changelog, inline comments
- ✅ **Deployment**: Systemd service, installation script, .env config
- ✅ **Performance**: Indexes, transactions, connection pooling
- ✅ **Scalability**: Queue-based, non-blocking architecture
- ✅ **Testing**: Manual test procedures documented
- ✅ **License**: MIT (open source friendly)
- ✅ **Versioning**: Semantic versioning (v1.0.0)

---

## 🤝 Contributing

We welcome contributions! Here's how:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

**Coding Standards**: PSR-12 for PHP, PEP 8 for Python

---

## 📞 Support & Contact

- **Issues**: [GitHub Issues](https://github.com/yourusername/nexus-iaas/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/nexus-iaas/discussions)
- **Email**: support@yourdomain.com
- **Documentation**: [README.md](README.md)

---

## 📝 License

**MIT License**

Copyright (c) 2026 Krzysztof Siek

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED.

---

<div align="center">

## 🌟 Thank You for Choosing Nexus-IaaS!

**Built with ❤️ by Krzysztof Siek**

If this project helped you, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting features
- 📢 Sharing with others

**Happy Cloud Computing! ☁️**

</div>
