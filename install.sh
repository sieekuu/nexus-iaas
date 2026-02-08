#!/bin/bash
#
# Nexus-IaaS Quick Installation Script
# Copyright (c) 2026 Krzysztof Siek
# Licensed under the MIT License.
#
# This script automates the installation of Nexus-IaaS on Ubuntu/Debian systems.
# For production use, review and customize before running.
#

set -e

echo "========================================="
echo "  Nexus-IaaS Installation Script"
echo "========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "⚠️  Please run this script as root or with sudo"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VER=$VERSION_ID
else
    echo "❌ Unable to detect OS"
    exit 1
fi

echo "✅ Detected OS: $OS $VER"
echo ""

# Install dependencies
echo "📦 Installing dependencies..."
if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    apt update
    apt install -y \
        apache2 \
        php8.2 \
        php8.2-cli \
        php8.2-mysql \
        php8.2-mbstring \
        php8.2-xml \
        mysql-server \
        python3 \
        python3-pip \
        python3-venv \
        git \
        curl
    
    # Enable Apache modules
    a2enmod rewrite
    systemctl restart apache2
else
    echo "❌ Unsupported OS. Please install dependencies manually."
    exit 1
fi

echo "✅ Dependencies installed"
echo ""

# Create directories
echo "📁 Creating directories..."
mkdir -p /var/www/nexus-iaas
mkdir -p /var/log/nexus-iaas
chown -R www-data:www-data /var/www/nexus-iaas
chown -R www-data:www-data /var/log/nexus-iaas

echo "✅ Directories created"
echo ""

# Database setup
echo "🗄️  Setting up MySQL database..."
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASSWORD
echo ""
read -p "Enter database name [nexus_iaas]: " DB_NAME
DB_NAME=${DB_NAME:-nexus_iaas}
read -p "Enter database user [nexus_user]: " DB_USER
DB_USER=${DB_USER:-nexus_user}
read -sp "Enter database password: " DB_PASSWORD
echo ""

mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<MYSQL_SCRIPT
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

echo "✅ Database configured"
echo ""

# Import schema if files exist
if [ -f "database/schema.sql" ]; then
    echo "📥 Importing database schema..."
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" $DB_NAME < database/schema.sql
    echo "✅ Schema imported"
else
    echo "⚠️  database/schema.sql not found. Please import manually."
fi
echo ""

# Configure .env
echo "⚙️  Configuring environment..."
if [ -f ".env.example" ]; then
    cp .env.example .env
    
    # Generate random session secret
    SESSION_SECRET=$(openssl rand -hex 32)
    
    # Update .env file
    sed -i "s/DB_NAME=.*/DB_NAME=$DB_NAME/" .env
    sed -i "s/DB_USER=.*/DB_USER=$DB_USER/" .env
    sed -i "s/DB_PASS=.*/DB_PASS=$DB_PASSWORD/" .env
    sed -i "s/SESSION_SECRET=.*/SESSION_SECRET=$SESSION_SECRET/" .env
    
    echo "✅ Environment configured"
else
    echo "⚠️  .env.example not found"
fi
echo ""

# Setup Python virtual environment
echo "🐍 Setting up Python environment..."
cd scripts
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate
cd ..

echo "✅ Python environment ready"
echo ""

# Install systemd service
echo "⚙️  Installing worker daemon..."
if [ -f "nexus-iaas.service" ]; then
    # Update paths in service file
    sed -i "s|/var/www/nexus-iaas|$(pwd)|g" nexus-iaas.service
    cp nexus-iaas.service /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable nexus-iaas.service
    echo "✅ Worker daemon installed (not started yet)"
else
    echo "⚠️  nexus-iaas.service not found"
fi
echo ""

# Setup Apache
echo "🌐 Configuring Apache..."
read -p "Enter your domain name (e.g., cloud.example.com): " DOMAIN

cat > /etc/apache2/sites-available/nexus-iaas.conf <<APACHE_CONF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $(pwd)/public

    <Directory $(pwd)/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/nexus-iaas-error.log
    CustomLog \${APACHE_LOG_DIR}/nexus-iaas-access.log combined
</VirtualHost>
APACHE_CONF

a2ensite nexus-iaas.conf
systemctl reload apache2

echo "✅ Apache configured"
echo ""

echo "========================================="
echo "  ✅ Installation Complete!"
echo "========================================="
echo ""
echo "Next Steps:"
echo ""
echo "1. Update .env with your Proxmox credentials:"
echo "   nano .env"
echo ""
echo "2. Start the worker daemon:"
echo "   sudo systemctl start nexus-iaas.service"
echo ""
echo "3. Check worker status:"
echo "   sudo systemctl status nexus-iaas.service"
echo ""
echo "4. Access the dashboard:"
echo "   http://$DOMAIN"
echo ""
echo "5. Default admin login:"
echo "   Email: admin@nexus-iaas.local"
echo "   Password: Admin@123456"
echo "   ⚠️  CHANGE THIS IMMEDIATELY!"
echo ""
echo "6. (Optional) Setup SSL with Let's Encrypt:"
echo "   sudo apt install certbot python3-certbot-apache"
echo "   sudo certbot --apache -d $DOMAIN"
echo ""
echo "========================================="
