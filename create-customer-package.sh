#!/bin/bash

# WhatsApp Bot - Customer Package Generator
# Creates a licensed installation package for customers

CUSTOMER_NAME=$1
CUSTOMER_DOMAIN=$2
LICENSE_KEY=$3

if [ -z "$CUSTOMER_NAME" ] || [ -z "$CUSTOMER_DOMAIN" ] || [ -z "$LICENSE_KEY" ]; then
    echo "Usage: ./create-customer-package.sh <customer-name> <domain> <license-key>"
    echo ""
    echo "Example:"
    echo "  ./create-customer-package.sh 'John Store' 'johnstore.com' 'LIC-abc123def456'"
    echo ""
    exit 1
fi

echo "============================================"
echo "  WhatsApp Bot - Customer Package Creator"
echo "============================================"
echo ""
echo "Customer: $CUSTOMER_NAME"
echo "Domain: $CUSTOMER_DOMAIN"
echo "License: $LICENSE_KEY"
echo ""

# Create temp directory
SAFE_NAME=$(echo "$CUSTOMER_NAME" | tr ' ' '-' | tr '[:upper:]' '[:lower:]')
TEMP_DIR="/tmp/whatsapp-bot-$SAFE_NAME"
rm -rf $TEMP_DIR
mkdir -p $TEMP_DIR

echo "[1/5] Copying bot files..."
cp -r /var/www/whatsbot/* $TEMP_DIR/
cd $TEMP_DIR

echo "[2/5] Cleaning sensitive data..."
rm -f .env
rm -rf storage/ 2>/dev/null
rm -rf logs/ 2>/dev/null
rm -f .git 2>/dev/null

echo "[3/5] Creating customer .env file..."
cat > .env << EOF
# Database Configuration
DB_HOST=localhost
DB_NAME=whatsapp_bot
DB_USER=whatsapp_user
DB_PASS=CHANGE_THIS_PASSWORD

# Brains ERP API
BRAINS_API_BASE=http://your-erp-server:port/Api

# WhatsApp (ProxSMS)
WHATSAPP_ACCOUNT_ID=YOUR_PROXSMS_ACCOUNT_ID
WHATSAPP_SEND_SECRET=YOUR_PROXSMS_SEND_SECRET
WEBHOOK_SECRET=YOUR_WEBHOOK_SECRET

# Anthropic Claude AI (optional)
ANTHROPIC_API_KEY=

# Application Settings
TIMEZONE=Asia/Beirut
CURRENCY=LBP
STORE_NAME=YOUR_STORE_NAME
STORE_LOCATION=YOUR_STORE_LOCATION
STORE_PHONE=YOUR_PHONE_NUMBER
STORE_WEBSITE=https://yourwebsite.com/
STORE_HOURS=Monday-Saturday 9:00 AM - 7:00 PM

STORE_LATITUDE=34.0000
STORE_LONGITUDE=35.0000

# Automatic Sync Settings
SYNC_INTERVAL=1

# ============================================================================
# LICENSE CONFIGURATION - PRE-CONFIGURED
# ============================================================================
# Your license has been configured below
# DO NOT modify these values or the bot will stop working
# Contact support if you need to change your domain
# ============================================================================

LICENSE_SERVER_URL=https://lic.proxpanel.com
LICENSE_KEY=$LICENSE_KEY
SITE_DOMAIN=$CUSTOMER_DOMAIN
LICENSE_CHECK_ENABLED=true
EOF

echo "[4/5] Creating installation instructions..."
cat > INSTALLATION.txt << 'INSTEOF'
WhatsApp Bot - Installation Instructions
==========================================

Thank you for purchasing WhatsApp Bot!

Your license has been PRE-CONFIGURED in the .env file.

INSTALLATION STEPS:
-------------------

1. Extract the package:
   tar -xzf whatsapp-bot-*.tar.gz
   cd whatsapp-bot-*/

2. Edit .env and fill in YOUR details:
   nano .env
   
   Required changes:
   - DB_PASS (your database password)
   - BRAINS_API_BASE (your ERP API URL)
   - WHATSAPP_ACCOUNT_ID (from ProxSMS)
   - WHATSAPP_SEND_SECRET (from ProxSMS)
   - WEBHOOK_SECRET (generate random string)
   - STORE_NAME, STORE_LOCATION, etc.

   DO NOT CHANGE:
   - LICENSE_SERVER_URL
   - LICENSE_KEY
   - SITE_DOMAIN
   - LICENSE_CHECK_ENABLED

3. Run installation:
   sudo ./install.sh

4. Verify license:
   php bin/get-fingerprint.php
   
   You should see: ✅ Status: VALID

5. Configure your webhook in ProxSMS to point to:
   https://yourdomain.com/public/webhook-whatsapp.php

IMPORTANT NOTES:
----------------
- Your license is tied to your domain and server
- Do not move to a different server without contacting us
- License must remain active for bot to work
- Support: contact@yourcompany.com

Enjoy your WhatsApp Bot!
INSTEOF

echo "[5/5] Creating package..."
cd /tmp
PACKAGE_NAME="whatsapp-bot-$SAFE_NAME-$(date +%Y%m%d).tar.gz"
tar -czf $PACKAGE_NAME whatsapp-bot-$SAFE_NAME/

echo ""
echo "============================================"
echo "  ✅ Package Created Successfully!"
echo "============================================"
echo ""
echo "Package: /tmp/$PACKAGE_NAME"
echo "Size: $(du -h /tmp/$PACKAGE_NAME | cut -f1)"
echo ""
echo "SEND TO CUSTOMER:"
echo "  1. Download: scp root@yourserver:/tmp/$PACKAGE_NAME ."
echo "  2. Send package to customer"
echo "  3. Customer follows INSTALLATION.txt"
echo ""
echo "Customer Info:"
echo "  Name: $CUSTOMER_NAME"
echo "  Domain: $CUSTOMER_DOMAIN"
echo "  License: $LICENSE_KEY"
echo "  Expires: Check admin panel"
echo ""
