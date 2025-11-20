#!/bin/bash

#############################################
# WhatsApp Bot - One-Click Installation Script
# Author: Claude AI
# Description: Automated installation for Ubuntu 22.04+
#############################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored messages
print_message() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

# Function to check if script is run as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi
    print_message "Running as root"
}

# Function to check OS compatibility
check_os() {
    if [[ ! -f /etc/lsb-release ]]; then
        print_error "This script is designed for Ubuntu. Your OS is not supported."
        exit 1
    fi

    source /etc/lsb-release
    if [[ "$DISTRIB_ID" != "Ubuntu" ]]; then
        print_error "This script is designed for Ubuntu only."
        exit 1
    fi

    print_message "OS: Ubuntu $DISTRIB_RELEASE"
}

# Function to update system
update_system() {
    print_info "Updating system packages..."
    apt update > /dev/null 2>&1
    print_message "System packages updated"
}

# Function to install dependencies
install_dependencies() {
    print_info "Installing required packages (this may take a few minutes)..."

    DEBIAN_FRONTEND=noninteractive apt install -y \
        nginx \
        mysql-server \
        php8.1-fpm \
        php8.1-mysql \
        php8.1-curl \
        php8.1-mbstring \
        php8.1-xml \
        php8.1-zip \
        php8.1-bcmath \
        php8.1-bz2 \
        git \
        curl \
        unzip \
        > /dev/null 2>&1

    print_message "All dependencies installed"
}

# Function to generate random password
generate_password() {
    openssl rand -base64 32
}

# Function to get user input
get_user_input() {
    echo ""
    print_info "=== Configuration Setup ==="
    echo ""

    # Store Name
    read -p "Enter your store/business name: " STORE_NAME

    # Database Configuration
    print_info "Generating secure database password..."
    DB_PASS=$(generate_password)
    DB_NAME="whatsapp_bot"
    DB_USER="whatsapp_user"

    # Brains API
    read -p "Enter Brains ERP API Base URL (or press Enter to skip): " BRAINS_API_BASE

    # WhatsApp API (ProxSMS)
    read -p "Enter ProxSMS Account ID (or press Enter to skip): " WHATSAPP_ACCOUNT_ID
    read -p "Enter ProxSMS Send Secret (or press Enter to skip): " WHATSAPP_SEND_SECRET

    # Webhook Secret
    print_info "Generating webhook secret..."
    WEBHOOK_SECRET=$(openssl rand -hex 20)

    # Anthropic API
    read -p "Enter Anthropic Claude API Key (sk-ant-...): " ANTHROPIC_API_KEY

    # Store Information
    read -p "Enter store location (e.g., Beirut, Lebanon): " STORE_LOCATION
    read -p "Enter store phone number: " STORE_PHONE
    read -p "Enter store website (optional): " STORE_WEBSITE
    read -p "Enter business hours (e.g., Mon-Sat 9AM-7PM): " STORE_HOURS

    # Store Coordinates
    read -p "Enter store latitude (for Google Maps, default: 34.00951559789577): " STORE_LATITUDE
    STORE_LATITUDE=${STORE_LATITUDE:-34.00951559789577}

    read -p "Enter store longitude (for Google Maps, default: 35.654434764102675): " STORE_LONGITUDE
    STORE_LONGITUDE=${STORE_LONGITUDE:-35.654434764102675}

    # Domain Configuration (for license system)
    echo ""
    print_info "=== Domain Configuration ==="

    # Try to auto-detect domain/IP
    DETECTED_IP=$(hostname -I | awk '{print $1}')
    DETECTED_HOSTNAME=$(hostname -f 2>/dev/null || hostname)

    echo ""
    print_info "Auto-detected server information:"
    echo "  - IP Address: $DETECTED_IP"
    echo "  - Hostname: $DETECTED_HOSTNAME"
    echo ""

    read -p "Enter your domain name (e.g., bot.example.com) or press Enter to use IP [$DETECTED_IP]: " SITE_DOMAIN
    SITE_DOMAIN=${SITE_DOMAIN:-$DETECTED_IP}

    print_message "Using domain: $SITE_DOMAIN"

    # Admin User
    echo ""
    print_info "=== Admin Account Setup ==="
    read -p "Enter admin username (default: admin): " ADMIN_USERNAME
    ADMIN_USERNAME=${ADMIN_USERNAME:-admin}

    while true; do
        read -s -p "Enter admin password (min 6 characters): " ADMIN_PASSWORD
        echo ""
        if [[ ${#ADMIN_PASSWORD} -lt 6 ]]; then
            print_error "Password must be at least 6 characters"
            continue
        fi
        read -s -p "Confirm admin password: " ADMIN_PASSWORD_CONFIRM
        echo ""
        if [[ "$ADMIN_PASSWORD" == "$ADMIN_PASSWORD_CONFIRM" ]]; then
            break
        else
            print_error "Passwords do not match. Try again."
        fi
    done

    # Installation directory
    read -p "Enter installation directory (default: /var/www/whatsbot): " INSTALL_DIR
    INSTALL_DIR=${INSTALL_DIR:-/var/www/whatsbot}

    echo ""
    print_message "Configuration collected successfully"
}

# Function to create database
setup_database() {
    print_info "Setting up MySQL database..."

    # Create database and user
    mysql <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    print_message "Database created: ${DB_NAME}"
    print_message "Database user created: ${DB_USER}"
}

# Function to clone repository
clone_repository() {
    print_info "Cloning WhatsBot repository..."

    if [[ -d "$INSTALL_DIR" ]]; then
        print_warning "Directory $INSTALL_DIR already exists"
        read -p "Remove existing directory and continue? (y/N): " confirm
        if [[ $confirm == [yY] ]]; then
            rm -rf "$INSTALL_DIR"
        else
            print_error "Installation cancelled"
            exit 1
        fi
    fi

    git clone https://github.com/mmdelhajj/whatsbot.git "$INSTALL_DIR" > /dev/null 2>&1
    print_message "Repository cloned to $INSTALL_DIR"
}

# Function to create .env file
create_env_file() {
    print_info "Creating environment configuration..."

    cat > "$INSTALL_DIR/.env" <<EOF
# Database Configuration
DB_HOST=localhost
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}

# Brains ERP API
BRAINS_API_BASE=${BRAINS_API_BASE}

# WhatsApp (ProxSMS)
WHATSAPP_ACCOUNT_ID=${WHATSAPP_ACCOUNT_ID}
WHATSAPP_SEND_SECRET=${WHATSAPP_SEND_SECRET}
WEBHOOK_SECRET=${WEBHOOK_SECRET}

# Anthropic Claude AI
ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}

# Application Settings
TIMEZONE=Asia/Beirut
CURRENCY=LBP
STORE_NAME=${STORE_NAME}
STORE_LOCATION=${STORE_LOCATION}
STORE_PHONE=${STORE_PHONE}
STORE_WEBSITE=${STORE_WEBSITE}
STORE_HOURS=${STORE_HOURS}

STORE_LATITUDE=${STORE_LATITUDE}
STORE_LONGITUDE=${STORE_LONGITUDE}

# Automatic Sync Settings
SYNC_INTERVAL=240

# License Configuration - Auto Trial System
LICENSE_SERVER_URL=https://lic.proxpanel.com
LICENSE_KEY=
SITE_DOMAIN=${SITE_DOMAIN}
LICENSE_CHECK_ENABLED=true
EOF

    chmod 600 "$INSTALL_DIR/.env"
    print_message "Environment file created with license configuration"
}

# Function to import database schema
import_database() {
    print_info "Importing database schema..."

    if [[ -f "$INSTALL_DIR/database/schema.sql" ]]; then
        mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/database/schema.sql"
        print_message "Database schema imported"
    else
        print_warning "Database schema file not found, skipping..."
    fi
}

# Function to create admin user
create_admin_user() {
    print_info "Creating admin user..."

    HASHED_PASSWORD=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_BCRYPT);")

    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO admin_users (username, password, email, created_at)
VALUES ('${ADMIN_USERNAME}', '${HASHED_PASSWORD}', 'admin@localhost', NOW())
ON DUPLICATE KEY UPDATE password='${HASHED_PASSWORD}';
EOF

    print_message "Admin user created: ${ADMIN_USERNAME}"
}

# Function to setup nginx
setup_nginx() {
    print_info "Configuring Nginx..."

    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')

    cat > /etc/nginx/sites-available/whatsbot <<EOF
server {
    listen 80;
    server_name ${SERVER_IP};
    root ${INSTALL_DIR}/public;
    index index.php;

    # Logging
    access_log /var/log/nginx/whatsbot-access.log;
    error_log /var/log/nginx/whatsbot-error.log;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Admin panel - handle PHP files
    location ~ ^/admin/.+\.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME ${INSTALL_DIR}\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Admin panel - serve index.php for /admin
    location = /admin {
        return 301 /admin/;
    }

    location = /admin/ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME ${INSTALL_DIR}/admin/index.php;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Admin static files
    location /admin/ {
        alias ${INSTALL_DIR}/admin/;
        try_files \$uri \$uri/ =404;
    }

    # PHP processing for main site
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Webhook endpoint
    location /webhook-whatsapp.php {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to .env and other sensitive files
    location ~ /\.env {
        deny all;
    }

    # Images directory
    location /images {
        alias ${INSTALL_DIR}/images;
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    # phpMyAdmin (if installed)
    location /phpmyadmin {
        alias /usr/share/phpmyadmin;
        index index.php;

        location ~ ^/phpmyadmin/(.+\.php)$ {
            alias /usr/share/phpmyadmin/\$1;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/\$1;
            include fastcgi_params;
        }

        location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
            alias /usr/share/phpmyadmin/\$1;
        }
    }
}
EOF

    # Enable site
    ln -sf /etc/nginx/sites-available/whatsbot /etc/nginx/sites-enabled/whatsbot

    # Remove default site
    rm -f /etc/nginx/sites-enabled/default

    # Test nginx config
    nginx -t > /dev/null 2>&1

    # Reload nginx
    systemctl reload nginx

    print_message "Nginx configured and reloaded"
}

# Function to set permissions
set_permissions() {
    print_info "Setting file permissions..."

    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod 600 "$INSTALL_DIR/.env"

    # Create logs directory
    mkdir -p "$INSTALL_DIR/logs"
    chmod 777 "$INSTALL_DIR/logs"

    # Create images directory
    mkdir -p "$INSTALL_DIR/images/products"
    chmod 777 "$INSTALL_DIR/images/products"

    print_message "Permissions set correctly"
}

# Function to install phpMyAdmin (optional)
install_phpmyadmin() {
    read -p "Install phpMyAdmin for database management? (y/N): " install_pma
    if [[ $install_pma == [yY] ]]; then
        print_info "Installing phpMyAdmin..."
        DEBIAN_FRONTEND=noninteractive apt install -y phpmyadmin > /dev/null 2>&1
        print_message "phpMyAdmin installed (accessible at http://${SERVER_IP}/phpmyadmin)"
    fi
}

# Function to setup automatic sync cron job
setup_cron_sync() {
    print_info "Setting up automatic sync cron job..."

    # Create system-wide cron job in /etc/cron.d/ (better than user crontab)
    cat > /etc/cron.d/whatsbot-sync <<EOF
# WhatsApp Bot - Automatic Sync
# Runs every minute, but script only syncs based on SYNC_INTERVAL setting
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

* * * * * root /usr/bin/php ${INSTALL_DIR}/scripts/sync-brains.php >> ${INSTALL_DIR}/logs/sync.log 2>&1
EOF

    chmod 644 /etc/cron.d/whatsbot-sync
    print_message "Cron job installed at /etc/cron.d/whatsbot-sync (syncs every ${SYNC_INTERVAL:-240} minutes)"
}

# Function to print installation summary
print_summary() {
    echo ""
    echo "============================================="
    print_message "WhatsApp Bot Installation Complete!"
    echo "============================================="
    echo ""
    print_info "Installation Details:"
    echo ""
    echo "  📁 Installation Directory: $INSTALL_DIR"
    echo "  🌐 Server IP: $SERVER_IP"
    echo "  🌍 Domain: $SITE_DOMAIN"
    echo ""
    print_info "Access URLs:"
    echo ""
    echo "  🔗 Admin Panel: http://${SERVER_IP}/admin"
    echo "  🔗 Webhook URL: http://${SERVER_IP}/webhook-whatsapp.php"
    if [[ $install_pma == [yY] ]]; then
        echo "  🔗 phpMyAdmin: http://${SERVER_IP}/phpmyadmin"
    fi
    echo ""
    print_info "Admin Login:"
    echo ""
    echo "  👤 Username: $ADMIN_USERNAME"
    echo "  🔑 Password: [the password you entered]"
    echo ""
    print_info "Database Credentials:"
    echo ""
    echo "  🗄️  Database: $DB_NAME"
    echo "  👤 User: $DB_USER"
    echo "  🔑 Password: $DB_PASS"
    echo ""
    print_info "Webhook Configuration:"
    echo ""
    echo "  🔐 Webhook Secret: $WEBHOOK_SECRET"
    echo ""
    print_info "Automatic Sync:"
    echo ""
    echo "  🔄 Cron job installed (syncs every 4 hours by default)"
    echo "  📊 Change interval in Admin → Settings → Sync Interval"
    echo ""
    print_info "License Information:"
    echo ""
    echo "  🔐 License Server: https://lic.proxpanel.com"
    echo "  📝 Domain: $SITE_DOMAIN"
    echo "  ⏰ Trial: 3 days (automatic on first access)"
    echo "  📊 On first admin panel visit, your installation will auto-register"
    echo "     and appear at lic.proxpanel.com for license management"
    echo ""
    print_warning "IMPORTANT: Save these credentials in a secure location!"
    echo ""
    print_info "Next Steps:"
    echo ""
    echo "  1. Login to admin panel: http://${SERVER_IP}/admin"
    echo "  2. Configure ProxSMS webhook to point to: http://${SERVER_IP}/webhook-whatsapp.php"
    echo "  3. Import products from Brains ERP (if configured)"
    echo "  4. Test the bot by sending a WhatsApp message"
    echo "  5. Your 3-day trial will start automatically - check lic.proxpanel.com to activate"
    echo ""
    echo "============================================="
    print_message "Installation Complete! 🎉"
    echo "============================================="
    echo ""
}

# Main installation function
main() {
    clear
    echo "============================================="
    echo "  WhatsApp Bot - Automated Installer"
    echo "  Version: 1.0"
    echo "============================================="
    echo ""

    check_root
    check_os
    update_system
    install_dependencies
    get_user_input
    setup_database
    clone_repository
    create_env_file
    import_database
    create_admin_user
    setup_nginx
    set_permissions
    setup_cron_sync
    install_phpmyadmin
    print_summary
}

# Run main function
main
