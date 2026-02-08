# Nexus-IaaS Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-02-08

### 🎉 Initial Release

**Nexus-IaaS v1.0.0** - Production-ready Infrastructure as a Service platform for Proxmox VE.

### Added

#### Core Features
- **User Management System**
  - User registration with email validation
  - Secure login with Argon2id password hashing
  - Session management with CSRF protection
  - Admin role-based access control
  - Ban/unban user functionality

- **Virtual Machine Management**
  - Create VMs with custom specifications (vCPU, RAM, disk)
  - Start, stop, and delete VM operations
  - Real-time status updates via AJAX polling
  - Queue-based async operations (non-blocking UI)
  - Automatic IP address allocation from pool

- **Billing System**
  - User balance tracking
  - Automatic cost deduction on VM creation
  - Transaction history logging
  - Hourly cost calculation for running VMs
  - Balance management (admin can add credits)

- **Task Queue System**
  - MySQL-based task queue
  - Status tracking (pending, processing, completed, failed)
  - Automatic retry mechanism (configurable)
  - Task result storage (JSON format)

- **Audit Logging**
  - Comprehensive action logging
  - IP address and user agent tracking
  - Resource-based log filtering
  - Retention policy support

#### Technical Implementation

- **Backend (PHP 8.2+)**
  - MVC architecture with OOP principles
  - Singleton database pattern with PDO
  - Strict type declarations throughout
  - PSR-12 coding standards
  - Environment-based configuration (.env file)

- **Infrastructure Bridge (Python 3.10+)**
  - Proxmoxer library integration
  - CLI argument parsing with argparse
  - JSON-based communication with PHP
  - Error handling and logging
  - Virtual environment support

- **Worker Daemon**
  - Systemd service integration
  - Continuous queue monitoring
  - Configurable sleep intervals
  - Graceful shutdown handling (SIGTERM, SIGINT)
  - Comprehensive logging

- **Frontend**
  - Bootstrap 5 dark theme
  - Responsive design
  - Real-time AJAX polling (2-second interval)
  - Modal-based VM creation
  - Status badges with color coding

- **Security**
  - CSRF token protection
  - SQL injection prevention (prepared statements)
  - XSS protection (input sanitization)
  - Secure session configuration
  - Password strength validation
  - Session regeneration

#### Database Schema

- **users** - User accounts and authentication
- **instances** - Virtual machine records
- **task_queue** - Asynchronous task processing
- **ip_pool** - IP address allocation management
- **audit_logs** - Action tracking and compliance

#### Documentation

- Comprehensive README.md with installation guide
- API documentation with examples
- Architecture diagrams and flow charts
- Security best practices guide
- systemd service configuration
- Quick installation script (install.sh)
- Project structure documentation

#### Configuration

- Environment variable configuration (.env)
- Configurable worker parameters
- Customizable billing rates
- Flexible Proxmox connection settings

### Technical Specifications

- **PHP**: 8.2+ with strict types
- **MySQL**: 8.0+ / MariaDB 10.6+
- **Python**: 3.10+ with proxmoxer
- **Web Server**: Apache 2.4+ / Nginx 1.18+
- **Proxmox VE**: 7.0+

### Dependencies

#### PHP Extensions
- pdo_mysql
- mbstring
- json
- session

#### Python Packages
- proxmoxer >= 2.0.1
- requests >= 2.31.0

### License

MIT License - Copyright (c) 2026 Krzysztof Siek

### Known Limitations

- Single Proxmox node support (multi-node in future release)
- Manual IP pool management (GUI planned for v1.1)
- Email notifications not yet implemented
- Backup/restore functionality pending

### Planned Features (v1.1+)

- Multi-node Proxmox support
- VM backup and restore
- Email notifications
- Two-factor authentication
- API rate limiting
- Webhook support for external integrations
- VM resource monitoring graphs
- Automated billing cycles

---

## Links

- **Repository**: https://github.com/yourusername/nexus-iaas
- **Documentation**: [README.md](README.md)
- **License**: [LICENSE](LICENSE)
- **Issues**: https://github.com/yourusername/nexus-iaas/issues

---

**Built with ❤️ by Krzysztof Siek**
