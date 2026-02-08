# Nexus-IaaS Project Structure

```
nexus-iaas/
│
├── .env                           # Environment configuration (DO NOT COMMIT)
├── .env.example                   # Environment template
├── .gitignore                     # Git ignore rules
├── LICENSE                        # MIT License
├── README.md                      # Main documentation
├── install.sh                     # Quick installation script
├── nexus-iaas.service            # Systemd service file
│
├── config/                        # Application configuration
│   └── Database.php               # Database connection singleton
│
├── database/                      # Database schemas and migrations
│   └── schema.sql                 # Main database schema
│
├── public/                        # Web-accessible directory (DocumentRoot)
│   ├── index.php                  # Main router/entry point
│   ├── api.php                    # REST API endpoint
│   ├── .htaccess                  # Apache rewrite rules (optional)
│   └── assets/                    # Static assets
│       ├── css/
│       │   └── style.css          # Custom stylesheet
│       ├── js/
│       │   └── app.js             # Frontend JavaScript (optional)
│       └── images/
│           └── logo.png           # Logo (optional)
│
├── scripts/                       # Background scripts and utilities
│   ├── proxmox_bridge.py          # Python Proxmox API bridge
│   ├── worker.php                 # Task queue daemon (PHP CLI)
│   ├── requirements.txt           # Python dependencies
│   └── venv/                      # Python virtual environment (created during install)
│
├── src/                           # Core PHP application classes
│   ├── Auth.php                   # Authentication & session management
│   ├── Queue.php                  # Task queue management
│   ├── Instance.php               # VM instance operations
│   ├── Billing.php                # Billing and balance management
│   └── AuditLog.php               # Audit logging system
│
├── views/                         # HTML templates
│   ├── dashboard.php              # Main dashboard (after login)
│   ├── login.php                  # Login page
│   ├── register.php               # Registration page
│   ├── instances.php              # Instance list view (optional)
│   ├── billing.php                # Billing page (optional)
│   ├── settings.php               # User settings (optional)
│   └── admin.php                  # Admin panel (optional)
│
└── logs/                          # Application logs (created at runtime)
    ├── worker.log                 # Worker daemon log
    └── worker-error.log           # Worker error log
```

## Directory Purpose

### `/config` - Configuration Layer
Central configuration management including database connections and environment loading.

### `/database` - Database Layer
SQL schemas, migrations, and seed data for initial setup.

### `/public` - Web Root
The only directory accessible via HTTP. Contains entry points (index.php, api.php) and static assets.

### `/scripts` - Background Processing
Python bridge for Proxmox API calls and PHP worker daemon for async task processing.

### `/src` - Business Logic
Core PHP classes implementing application functionality using OOP principles.

### `/views` - Presentation Layer
HTML templates with embedded PHP for rendering the user interface.

### `/logs` - Runtime Logs
Application and worker logs (excluded from Git, created during installation).

## File Ownership & Permissions

```bash
# Ownership
sudo chown -R www-data:www-data /var/www/nexus-iaas

# Directory permissions
find /var/www/nexus-iaas -type d -exec chmod 755 {} \;

# File permissions
find /var/www/nexus-iaas -type f -exec chmod 644 {} \;

# Executable scripts
chmod +x /var/www/nexus-iaas/scripts/proxmox_bridge.py
chmod +x /var/www/nexus-iaas/install.sh

# Sensitive files (restrict access)
chmod 600 /var/www/nexus-iaas/.env

# Log directory (writable)
chmod 775 /var/www/nexus-iaas/logs
```

## Key Files Explained

| File | Purpose | Critical? |
|------|---------|-----------|
| `.env` | Environment variables (DB, Proxmox credentials) | ✅ YES |
| `config/Database.php` | PDO singleton, environment loader | ✅ YES |
| `public/index.php` | Main router (handles all web requests) | ✅ YES |
| `public/api.php` | REST API endpoint (JSON responses) | ✅ YES |
| `src/Auth.php` | Login, registration, session management | ✅ YES |
| `src/Queue.php` | Task queue (push/pop tasks) | ✅ YES |
| `scripts/proxmox_bridge.py` | Python bridge to Proxmox API | ✅ YES |
| `scripts/worker.php` | Background daemon (processes queue) | ✅ YES |
| `database/schema.sql` | Database structure | ✅ YES |
| `views/dashboard.php` | Main UI after login | ⚠️ Important |
| `nexus-iaas.service` | Systemd unit for worker daemon | ⚠️ Important |

## Security Notes

### Files to NEVER commit to Git:
- `.env` (contains secrets)
- `/logs/*` (may contain sensitive data)
- `/scripts/venv/*` (Python virtual environment)

### Files that MUST exist:
- `.env` (copy from `.env.example`)
- `database/schema.sql` (imported during setup)
- `scripts/requirements.txt` (for Python dependencies)

---

**Copyright (c) 2026 Krzysztof Siek | Licensed under MIT License**
