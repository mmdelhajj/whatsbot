# WhatsApp Bot - Customer Installation Guide

## What Happens When You Clone from GitHub

When a customer (or you) clones the bot from GitHub, here's what happens:

### Files Included in GitHub:
✅ All PHP code (with license checking built-in)
✅ Database structure
✅ Admin panel
✅ `.env.example` (template configuration)
✅ `LICENSE_SYSTEM.md` (documentation)

### Files NOT in GitHub (Gitignored):
❌ `.env` (your actual secrets and config)
❌ `storage/` (license cache)
❌ `logs/` (log files)
❌ `images/` (product images)

## Two Installation Scenarios

### Scenario 1: YOUR OWN Server (No License Required)

When **you** install on a new server:

```bash
# 1. Clone from GitHub
git clone https://github.com/yourusername/whatsapp-bot.git
cd whatsapp-bot

# 2. Copy example config
cp .env.example .env

# 3. Edit .env with your settings
nano .env

# 4. IMPORTANT: License is DISABLED by default in .env.example
# Leave it as:
LICENSE_CHECK_ENABLED=false

# 5. Complete setup
# ... database, nginx, etc ...
```

**Result:** Bot works immediately, NO license required!

---

### Scenario 2: CUSTOMER Server (License Required)

When **customer** installs from GitHub:

```bash
# 1. Customer clones
git clone https://github.com/yourusername/whatsapp-bot.git
cd whatsapp-bot

# 2. Customer copies config
cp .env.example .env

# 3. Customer edits .env
nano .env
```

**At this point:**
- `.env.example` has `LICENSE_CHECK_ENABLED=false`
- Customer can configure everything
- Bot will run WITHOUT license

**To ENFORCE license for customer:**

You need to tell customer to enable licensing:

```bash
# Customer must edit .env and set:
LICENSE_CHECK_ENABLED=true
LICENSE_KEY=<key-you-provide>
SITE_DOMAIN=customer-domain.com
```

**Problem:** Customer could leave `LICENSE_CHECK_ENABLED=false` and bypass!

---

## Solution: Two Distribution Methods

### Method A: GitHub (Trust-Based)

**For customers you trust:**
1. Give them GitHub access
2. Instruct them to set `LICENSE_CHECK_ENABLED=true`
3. Generate license for them
4. They configure `.env` honestly

**Pros:** Easy distribution
**Cons:** Customer can disable license

---

### Method B: Direct ZIP (License Enforced)

**For paying customers:**

**You create a custom `.env.example` for them:**

```bash
# Create customer-specific package
cp -r /var/www/whatsbot /tmp/customer-bot
cd /tmp/customer-bot

# Create enforced .env.example
cat > .env.example << 'EOF'
# ... all settings ...

# LICENSE REQUIRED - DO NOT DISABLE
LICENSE_SERVER_URL=https://lic.proxpanel.com
LICENSE_KEY=<ENTER_YOUR_LICENSE_KEY_HERE>
SITE_DOMAIN=<YOUR_DOMAIN_HERE>
LICENSE_CHECK_ENABLED=true
EOF

# Package it
tar -czf whatsapp-bot-licensed.tar.gz .
```

**Send customer:**
1. The ZIP file (not GitHub link)
2. License key after they pay
3. Instructions to fill in their domain

**Pros:** License enforced by default
**Cons:** Manual packaging per customer

---

## Method C: Hybrid (Recommended)

**Use GitHub for YOUR servers + Manual packages for CUSTOMERS:**

### For Your Servers:
```bash
git clone https://github.com/yourusername/whatsapp-bot.git
# .env.example has LICENSE_CHECK_ENABLED=false
```

### For Customer Servers:
1. **Customer requests installation**
2. **You generate license first**
3. **You create custom package:**

```bash
# Script to create customer package
./create-customer-package.sh customer-name customer-domain.com LICENSE-KEY-HERE
```

4. **Send ZIP + installation instructions**

---

## Automated Customer Package Creation

Create this script: `create-customer-package.sh`

```bash
#!/bin/bash

CUSTOMER_NAME=$1
CUSTOMER_DOMAIN=$2
LICENSE_KEY=$3

if [ -z "$CUSTOMER_NAME" ] || [ -z "$CUSTOMER_DOMAIN" ] || [ -z "$LICENSE_KEY" ]; then
    echo "Usage: ./create-customer-package.sh <customer-name> <domain> <license-key>"
    exit 1
fi

echo "Creating package for: $CUSTOMER_NAME"
echo "Domain: $CUSTOMER_DOMAIN"
echo "License: $LICENSE_KEY"

# Create temp directory
TEMP_DIR="/tmp/whatsapp-bot-$CUSTOMER_NAME"
rm -rf $TEMP_DIR
mkdir -p $TEMP_DIR

# Copy all files
cp -r /var/www/whatsbot/* $TEMP_DIR/
cd $TEMP_DIR

# Remove sensitive files
rm -f .env
rm -rf storage/
rm -rf logs/

# Create customer-specific .env
cat > .env << EOF
# Database Configuration
DB_HOST=localhost
DB_NAME=whatsapp_bot
DB_USER=whatsapp_user
DB_PASS=<YOUR_DB_PASSWORD>

# Brains ERP API
BRAINS_API_BASE=http://your-erp-server:port/Api

# WhatsApp (ProxSMS)
WHATSAPP_ACCOUNT_ID=<YOUR_PROXSMS_ACCOUNT>
WHATSAPP_SEND_SECRET=<YOUR_PROXSMS_SECRET>
WEBHOOK_SECRET=<YOUR_WEBHOOK_SECRET>

# Anthropic Claude AI
ANTHROPIC_API_KEY=<OPTIONAL_AI_KEY>

# Application Settings
TIMEZONE=Asia/Beirut
CURRENCY=LBP
STORE_NAME=<YOUR_STORE_NAME>
STORE_LOCATION=<YOUR_LOCATION>
STORE_PHONE=<YOUR_PHONE>
STORE_WEBSITE=<YOUR_WEBSITE>
STORE_HOURS=Monday-Saturday 9:00 AM - 7:00 PM
STORE_LATITUDE=<YOUR_LATITUDE>
STORE_LONGITUDE=<YOUR_LONGITUDE>

# Sync Settings
SYNC_INTERVAL=1

# ============================================================================
# LICENSE CONFIGURATION - REQUIRED FOR OPERATION
# ============================================================================
# Your license has been pre-configured below
# DO NOT modify these values or bot will stop working
# ============================================================================

LICENSE_SERVER_URL=https://lic.proxpanel.com
LICENSE_KEY=$LICENSE_KEY
SITE_DOMAIN=$CUSTOMER_DOMAIN
LICENSE_CHECK_ENABLED=true
EOF

# Create package
PACKAGE_NAME="whatsapp-bot-$CUSTOMER_NAME.tar.gz"
cd /tmp
tar -czf $PACKAGE_NAME whatsapp-bot-$CUSTOMER_NAME/

echo ""
echo "✅ Package created: /tmp/$PACKAGE_NAME"
echo ""
echo "Send to customer with these instructions:"
echo "1. Extract: tar -xzf $PACKAGE_NAME"
echo "2. Edit .env and fill in YOUR_ placeholders"
echo "3. Run installation script"
echo ""
```

Make it executable:
```bash
chmod +x create-customer-package.sh
```

---

## Summary: Best Practice

**What to commit to GitHub:**
- ✅ All code with license validation
- ✅ `.env.example` with `LICENSE_CHECK_ENABLED=false` (for your own use)
- ✅ Documentation
- ❌ No actual `.env` file
- ❌ No customer data

**For YOUR installations:**
```bash
git clone <repo>
cp .env.example .env
# LICENSE_CHECK_ENABLED=false (already default)
```

**For CUSTOMER installations:**
```bash
./create-customer-package.sh "Customer Name" "customer.com" "LIC-abc123..."
# Send the generated .tar.gz
# Customer gets pre-configured license
```

**Result:**
- Your GitHub code is clean and usable by you
- Customers get custom packages with license pre-enforced
- License bypass is prevented
- You maintain full control

---

## Quick Reference

**Check if license is enabled:**
```bash
grep LICENSE_CHECK_ENABLED .env
```

**Disable license (your servers only):**
```bash
sed -i 's/LICENSE_CHECK_ENABLED=true/LICENSE_CHECK_ENABLED=false/' .env
```

**Enable license (customer servers):**
```bash
sed -i 's/LICENSE_CHECK_ENABLED=false/LICENSE_CHECK_ENABLED=true/' .env
```
