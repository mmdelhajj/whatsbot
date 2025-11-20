# WhatsApp Bot License System

## Overview
Complete license management system to protect your WhatsApp Bot when selling to customers.

## System Components

### License Server
- **URL**: https://lic.proxpanel.com
- **Server**: 157.90.101.18
- **Database**: MySQL (license_server)
- **SSL**: Let's Encrypt (expires Feb 18, 2026 - auto-renews)

### Endpoints
- **API**: https://lic.proxpanel.com/api/validate.php
- **Admin Panel**: https://lic.proxpanel.com/admin/generate.php
- **Admin Password**: admin123 (CHANGE THIS!)

## How to Generate Customer Licenses

### Method 1: Admin Panel (Recommended)
1. Visit: https://lic.proxpanel.com/admin/generate.php
2. Login with password: `admin123`
3. Fill in customer details:
   - Customer Name
   - Customer Email
   - Domain (from customer)
   - Server Fingerprint (from customer)
   - Valid Days (default: 365)
4. Click "Generate License"
5. Copy the license key and send to customer

### Method 2: Direct SQL
```bash
ssh root@157.90.101.18
mysql -u root license_server

INSERT INTO licenses (license_key, customer_name, customer_email, domain, server_fingerprint, product_name, status, expires_at)
VALUES (
    CONCAT('LIC-', SUBSTRING(MD5(RAND()), 1, 24)),
    'Customer Name',
    'email@example.com',
    'customer-domain.com',
    'fingerprint-from-customer',
    'WhatsApp Bot',
    'active',
    DATE_ADD(NOW(), INTERVAL 365 DAY)
);
```

## Customer Installation Process

### 1. Customer Installs Bot
Customer installs bot files on their server.

### 2. Customer Gets Fingerprint
Customer runs:
```bash
cd /var/www/whatsbot
php bin/get-fingerprint.php
```

This displays:
- Domain
- Server Fingerprint

### 3. Customer Sends You Info
Customer sends you:
- Domain: example.com
- Fingerprint: 92ad2544f506e644ec144c4ead315df1

### 4. You Generate License
Use admin panel or SQL to create license with customer's domain and fingerprint.

### 5. You Send License Key
Send customer the generated license key:
```
LICENSE_KEY=LIC-abc123def456...
```

### 6. Customer Activates
Customer edits `/var/www/whatsbot/.env`:
```bash
LICENSE_SERVER_URL=https://lic.proxpanel.com
LICENSE_KEY=LIC-abc123def456...
SITE_DOMAIN=example.com
LICENSE_CHECK_ENABLED=true
```

### 7. Customer Verifies
Customer runs:
```bash
php bin/get-fingerprint.php
```

Should show: ✅ Status: VALID

## License Protection Features

### Hardware Binding
- License tied to server fingerprint
- Cannot be moved to different server without new license

### Domain Binding
- License tied to specific domain
- Cannot be used on different domain

### Remote Control
- Suspend license anytime via admin panel
- Set expiry dates
- Track all validation checks

### Usage Tracking
- Last check timestamp
- Total check count
- Full audit log of all validations

## Managing Licenses

### View All Licenses
```bash
mysql -u root license_server -e "SELECT license_key, customer_name, domain, status, expires_at, check_count, last_check FROM licenses;"
```

### Suspend License
```bash
mysql -u root license_server -e "UPDATE licenses SET status='suspended' WHERE license_key='LIC-...';"
```

### Extend License
```bash
mysql -u root license_server -e "UPDATE licenses SET expires_at=DATE_ADD(NOW(), INTERVAL 365 DAY) WHERE license_key='LIC-...';"
```

### View License Logs
```bash
mysql -u root license_server -e "SELECT * FROM license_logs WHERE license_id=(SELECT id FROM licenses WHERE license_key='LIC-...') ORDER BY created_at DESC LIMIT 20;"
```

## How It Works

### Validation Flow
1. Every webhook call checks license
2. Bot calls: https://lic.proxpanel.com/api/validate.php
3. Sends: key, domain, fingerprint
4. Server validates:
   - License key exists
   - Status = active
   - Not expired
   - Domain matches
   - Fingerprint matches
5. Returns: valid/invalid
6. If invalid: Bot stops processing messages

### Caching
- Valid responses cached for 1 hour
- Reduces API calls (3-5 per hour instead of hundreds)
- Grace period: 2 hours if license server unreachable

### Security
- HTTPS encryption (SSL)
- Server fingerprint prevents VM cloning
- Domain binding prevents redistribution
- Remote kill switch (suspend status)

## For Your Internal Bot

Disable license checking on your own servers:
```bash
# /var/www/whatsbot/.env
LICENSE_CHECK_ENABLED=false
```

Or use a permanent license without expiry.

## Troubleshooting

### Customer: "License validation failed"
1. Check domain matches .env file
2. Verify fingerprint hasn't changed
3. Check license not expired/suspended
4. Test API manually:
   ```bash
   curl "https://lic.proxpanel.com/api/validate.php?key=LIC-...&domain=example.com&fingerprint=abc123..."
   ```

### Server Unreachable
- Bot uses cached validation (grace period: 2 hours)
- Check DNS: `nslookup lic.proxpanel.com`
- Check SSL: `curl https://lic.proxpanel.com`

### Certificate Expired
SSL auto-renews, but if needed:
```bash
ssh root@157.90.101.18
certbot renew
systemctl reload nginx
```

## Security Notes

1. **Change admin password**:
   ```bash
   ssh root@157.90.101.18
   mysql -u root license_server
   UPDATE admin_users SET password_hash='$2y$10$...' WHERE username='admin';
   ```

2. **Database user** (optional):
   Fix licenseuser permissions or use root

3. **Backup database**:
   ```bash
   mysqldump -u root license_server > license_backup.sql
   ```

4. **Monitor logs**:
   - Nginx: /var/log/nginx/license_*.log
   - SSL: /var/log/letsencrypt/

## Statistics

View all license statistics:
```bash
mysql -u root license_server -e "
SELECT
    COUNT(*) as total_licenses,
    COUNT(CASE WHEN status='active' THEN 1 END) as active,
    COUNT(CASE WHEN status='suspended' THEN 1 END) as suspended,
    COUNT(CASE WHEN status='expired' THEN 1 END) as expired,
    SUM(check_count) as total_validations
FROM licenses;
"
```

## Support

For issues with license system:
- Check logs: /var/log/nginx/license_error.log
- Database access: mysql -u root license_server
- Bot logs: /var/www/whatsbot/logs/webhook.log
