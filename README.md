<div align="center">

# 🚀 Nexus-IaaS

**Production-Grade Infrastructure as a Service Platform**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://www.php.net/)
[![Python Version](https://img.shields.io/badge/Python-3.10+-green.svg)](https://www.python.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://www.mysql.com/)
[![Proxmox](https://img.shields.io/badge/Proxmox-VE-red.svg)](https://www.proxmox.com/)

*A self-hosted cloud control panel for managing virtual machines on Proxmox VE*

</div>

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Architecture](#-architecture)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [API Documentation](#-api-documentation)
- [Development](#-development)
- [Security](#-security)
- [License](#-license)
- [Support](#-support)

---

## 🎯 Overview

**Nexus-IaaS** is a production-ready, self-hosted Infrastructure as a Service (IaaS) platform built with a classic LAMP stack. It provides a modern web interface for managing virtual machines on Proxmox VE, featuring a queue-based architecture that ensures the UI never hangs during long-running operations.

### Why Nexus-IaaS?

- **🔐 Security First**: Argon2id password hashing, CSRF protection, PDO prepared statements
- **⚡ Non-Blocking Architecture**: Queue-based task system keeps UI responsive
- **🎨 Modern Interface**: Dark-themed Bootstrap 5 dashboard with real-time updates
- **🐍 Separation of Concerns**: PHP for web, Python for infrastructure operations
- **💰 Built-in Billing**: User balance tracking and cost management
- **📊 Audit Logging**: Complete action tracking for compliance
- **🔧 Lightweight**: No heavy frameworks - pure PHP 8.2+ with OOP principles

---

## ✨ Features

### Core Functionality

- **🖥️ VM Management**: Create, start, stop, and delete virtual machines
- **👤 User Management**: Registration, authentication, role-based access
- **💳 Billing System**: Balance tracking, transaction history, cost calculation
- **🌐 IP Pool Management**: Automatic IP allocation for VMs
- **📝 Audit Logging**: Track every action with IP, user agent, and timestamp
- **🔄 Task Queue**: Asynchronous operations via MySQL queue
- **⚙️ Admin Panel**: Comprehensive administrative controls

### Technical Features

- **RESTful API**: JSON-based API for all operations
- **AJAX Polling**: Real-time status updates without page refresh
- **CSRF Protection**: Token-based security for all forms
- **Session Management**: Secure session handling with regeneration
- **Error Handling**: Comprehensive exception handling and logging
- **Database Transactions**: ACID-compliant operations

---

## 🏗️ Architecture

### System Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                         USER BROWSER                              │
│  ┌────────────────┐       AJAX        ┌──────────────────────┐   │
│  │  Dashboard UI  │ ←────────────────→ │   API Endpoint       │   │
│  └────────────────┘   (Polling 2s)    └──────────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                      PHP WEB APPLICATION                          │
│  ┌──────────┐   ┌──────────┐   ┌─────────┐   ┌──────────────┐   │
│  │   Auth   │   │ Instance │   │ Billing │   │  AuditLog    │   │
│  └──────────┘   └──────────┘   └─────────┘   └──────────────┘   │
│                          │                                        │
│                          ▼                                        │
│                   ┌─────────────┐                                 │
│                   │    Queue    │ (Insert Task)                   │
│                   └─────────────┘                                 │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                      MySQL DATABASE                               │
│  ┌─────────┐  ┌──────────┐  ┌────────────┐  ┌──────────────┐    │
│  │  users  │  │instances │  │task_queue  │  │ audit_logs   │    │
│  └─────────┘  └──────────┘  └────────────┘  └──────────────┘    │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                   PHP WORKER DAEMON (Systemd)                     │
│                                                                    │
│  while(true) {                                                    │
│    1. Pop pending task from queue                                │
│    2. Execute Python bridge                                      │
│    3. Update instance status                                     │
│    4. Mark task completed/failed                                 │
│    5. Sleep 5 seconds                                            │
│  }                                                                │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│              PYTHON PROXMOX BRIDGE (proxmoxer)                    │
│                                                                    │
│  - Receives CLI arguments                                        │
│  - Connects to Proxmox API                                       │
│  - Executes VM operations                                        │
│  - Returns JSON result to PHP                                    │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                      PROXMOX VE CLUSTER                           │
│  ┌──────────┐   ┌──────────┐   ┌──────────┐                      │
│  │  Node 1  │   │  Node 2  │   │  Node N  │                      │
│  └──────────┘   └──────────┘   └──────────┘                      │
└──────────────────────────────────────────────────────────────────┘
```

### Data Flow: Creating a VM

1. **User clicks "Create VM"** → Bootstrap modal appears
2. **Form submission** → AJAX POST to `/api.php?action=create_instance`
3. **PHP validates** → Checks balance, allocates IP, deducts cost
4. **Insert to DB** → Creates instance record (status: pending)
5. **Queue task** → task_queue table (action: create, status: pending)
6. **Return to user** → JSON response with instance_id and task_id
7. **Worker detects** → Daemon pops task from queue
8. **Python bridge** → Executes proxmox_bridge.py with arguments
9. **Proxmox API** → Creates actual VM on hypervisor
10. **Update status** → Instance status → running, Task status → completed
11. **AJAX polling** → Dashboard shows updated status in real-time

---

## 🔧 Requirements

### Server Requirements

- **Operating System**: Linux (Ubuntu 22.04, Debian 12, or CentOS 9 recommended)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.2 or higher with extensions:
  - pdo_mysql
  - mbstring
  - json
  - session
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Python**: 3.10 or higher
- **Proxmox VE**: 7.0 or higher

### PHP Extensions Required

```bash
# Ubuntu/Debian
sudo apt install php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml

# CentOS/RHEL
sudo dnf install php82-cli php82-mysqlnd php82-mbstring php82-xml
```

### Python Packages

```bash
pip install proxmoxer requests
```

---

## 📦 Installation

### Step 1: Clone Repository

```bash
cd /var/www
git clone https://github.com/yourusername/nexus-iaas.git
cd nexus-iaas
```

### Step 2: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/nexus-iaas
sudo chmod -R 755 /var/www/nexus-iaas
sudo mkdir -p /var/log/nexus-iaas
sudo chown www-data:www-data /var/log/nexus-iaas
```

### Step 3: Configure Environment

```bash
cp .env.example .env
nano .env
```

**Update the following critical values:**

```ini
# Database
DB_HOST=localhost
DB_NAME=nexus_iaas
DB_USER=nexus_user
DB_PASS=YourSecurePassword123!

# Proxmox
PROXMOX_HOST=192.168.1.100
PROXMOX_USER=root@pam
PROXMOX_PASSWORD=YourProxmoxPassword

# Security (generate random string)
SESSION_SECRET=$(openssl rand -hex 32)
```

### Step 4: Import Database Schema

```bash
mysql -u root -p
```

```sql
CREATE DATABASE nexus_iaas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nexus_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON nexus_iaas.* TO 'nexus_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
mysql -u nexus_user -p nexus_iaas < database/schema.sql
```

### Step 5: Install Python Dependencies

```bash
cd scripts
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install -r requirements.txt
```

**Update worker.php to use virtual environment:**

Edit `scripts/worker.php` line ~70:

```php
$pythonExe = __DIR__ . '/venv/bin/python';  // Use venv Python
```

### Step 6: Configure Web Server

#### Apache Configuration

```bash
sudo nano /etc/apache2/sites-available/nexus-iaas.conf
```

```apache
<VirtualHost *:80>
    ServerName cloud.yourdomain.com
    DocumentRoot /var/www/nexus-iaas/public

    <Directory /var/www/nexus-iaas/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/nexus-iaas-error.log
    CustomLog ${APACHE_LOG_DIR}/nexus-iaas-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite nexus-iaas.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/nexus-iaas
```

```nginx
server {
    listen 80;
    server_name cloud.yourdomain.com;
    root /var/www/nexus-iaas/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/nexus-iaas /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 7: Install Worker Daemon

```bash
sudo cp nexus-iaas.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable nexus-iaas.service
sudo systemctl start nexus-iaas.service
```

**Check daemon status:**

```bash
sudo systemctl status nexus-iaas.service
sudo tail -f /var/log/nexus-iaas/worker.log
```

### Step 8: SSL Setup (Recommended)

```bash
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

sudo certbot --apache -d cloud.yourdomain.com    # For Apache
# OR
sudo certbot --nginx -d cloud.yourdomain.com     # For Nginx
```

---

## ⚙️ Configuration

### Default Admin Account

**After installation, login with:**

- **Email**: admin@nexus-iaas.local
- **Password**: Admin@123456

**⚠️ IMPORTANT: Change this password immediately after first login!**

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | Database host | localhost |
| `DB_NAME` | Database name | nexus_iaas |
| `PROXMOX_HOST` | Proxmox VE IP/hostname | - |
| `PROXMOX_NODE` | Default Proxmox node | pve |
| `VM_CREATION_COST` | Cost per VM creation | 10.00 |
| `WORKER_SLEEP_INTERVAL` | Worker cycle delay (seconds) | 5 |
| `SESSION_SECRET` | Session encryption key | - |

### IP Pool Management

Add IPs to the pool via SQL:

```sql
INSERT INTO ip_pool (ip_address, gateway) VALUES
('192.168.100.20', '192.168.100.1'),
('192.168.100.21', '192.168.100.1');
```

---

## 🎮 Usage

### User Operations

1. **Register Account**: `/register.php`
2. **Login**: `/login.php`
3. **Create VM**: Dashboard → "Create VM" button
4. **Manage VMs**: Start, stop, delete from dashboard
5. **Check Balance**: View balance and billing summary
6. **View Logs**: Access audit logs in settings

### Admin Operations

1. **Access Admin Panel**: Dashboard → Admin Panel (admin users only)
2. **Manage Users**: Ban/unban users, add balance
3. **View All Instances**: See all VMs across users
4. **Monitor Queue**: Check task queue statistics

---

## 📡 API Documentation

### Authentication

All API requests require authentication. Send CSRF token in POST requests:

```javascript
fetch('/api.php?action=create_instance', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken },
    body: formData
});
```

### Endpoints

#### Create Instance

```
POST /api.php?action=create_instance
```

**Parameters:**

- `name` (string): Instance name
- `vcpu` (int): Number of CPU cores
- `ram` (int): RAM in MB
- `disk` (int): Disk size in GB
- `os_template` (string): OS template name
- `csrf_token` (string): CSRF token

**Response:**

```json
{
    "success": true,
    "message": "VM creation queued successfully",
    "data": {
        "instance_id": 1,
        "task_id": 5
    }
}
```

#### Get Instances

```
GET /api.php?action=get_instances
```

**Response:**

```json
{
    "success": true,
    "message": "Instances retrieved",
    "data": [
        {
            "id": 1,
            "name": "web-server-01",
            "vmid": 101,
            "ip_address": "192.168.100.10",
            "status": "running",
            "vcpu": 2,
            "ram": 2048,
            "disk": 40
        }
    ]
}
```

#### Get Task Status

```
GET /api.php?action=get_task_status&task_id=5
```

**Response:**

```json
{
    "success": true,
    "message": "Task status retrieved",
    "data": {
        "id": 5,
        "action": "create",
        "status": "completed",
        "payload": {...},
        "result": {...}
    }
}
```

---

## 🛠️ Development

### Project Structure

```
nexus-iaas/
├── config/               # Configuration files
│   └── Database.php      # Database connection singleton
├── database/             # SQL schemas
│   └── schema.sql        # Database schema
├── public/               # Web-accessible files
│   ├── index.php         # Main router
│   ├── api.php           # REST API endpoint
│   └── assets/           # CSS, JS, images
├── scripts/              # Background scripts
│   ├── proxmox_bridge.py # Python Proxmox bridge
│   ├── worker.php        # Task queue daemon
│   └── requirements.txt  # Python dependencies
├── src/                  # Core PHP classes
│   ├── Auth.php          # Authentication
│   ├── Queue.php         # Task queue
│   ├── Instance.php      # VM management
│   ├── Billing.php       # Billing system
│   └── AuditLog.php      # Audit logging
├── views/                # HTML templates
│   ├── dashboard.php     # Main dashboard
│   ├── login.php         # Login page
│   └── register.php      # Registration page
├── .env.example          # Environment template
├── .gitignore            # Git ignore rules
├── LICENSE               # MIT License
├── nexus-iaas.service    # Systemd unit file
└── README.md             # This file
```

### Coding Standards

- **PHP**: PSR-12, strict types, OOP
- **Python**: PEP 8
- **Database**: Prepared statements only
- **Security**: OWASP Top 10 compliance

### Running Tests

```bash
# Test database connection
php -r "require 'config/Database.php'; NexusIaaS\Config\Database::getInstance();"

# Test Python bridge
cd scripts
python proxmox_bridge.py --action status --vmid 100

# Test worker (foreground mode)
php scripts/worker.php
```

---

## 🔒 Security

### Security Features

- ✅ Argon2id password hashing
- ✅ CSRF token protection
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Session fixation protection
- ✅ Secure session cookies (HttpOnly, Secure, SameSite)
- ✅ Input validation and sanitization
- ✅ Rate limiting (recommended: use fail2ban)
- ✅ Audit logging for all actions

### Security Best Practices

1. **Change default admin password immediately**
2. **Use HTTPS in production** (Let's Encrypt recommended)
3. **Set strong SESSION_SECRET** (64+ characters)
4. **Keep PHP and Python dependencies updated**
5. **Restrict file permissions** (755 for directories, 644 for files)
6. **Enable firewall** (ufw/iptables)
7. **Regular backups** of database and .env file

### Reporting Security Issues

Email security issues to: krzysiek@sieekuu.xyz
---

## 📄 License

Copyright (c) 2026 Krzysztof Siek

Licensed under the MIT License. See [LICENSE](LICENSE) file for details.

---

## 🤝 Support

### Documentation

- [Installation Guide](#installation)
- [API Documentation](#api-documentation)
- [Architecture Overview](#architecture)

### Community

- **Issues**: [GitHub Issues](https://github.com/yourusername/nexus-iaas/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/nexus-iaas/discussions)

### Professional Support

For enterprise support, custom development, or consulting:

📧 Email: krzysiek@sieekuu.xyz

---

<div align="center">

**Built with ❤️ using Vanilla PHP, Python, and Proxmox VE**

⭐ Star this project if you find it useful!

</div>


