# Automatic Sync Setup (Cron Job)

This guide shows how to set up automatic syncing of products and customers from Brains ERP every 4 hours.

## What Gets Synced

- **Products**: Prices, stock quantities, names, categories
- **Customers**: Names, phone numbers, emails, addresses

## Setup Instructions (On New Server: 157.90.101.21)

### 1. SSH to the new server
```bash
ssh root@157.90.101.21
```

### 2. Test the sync script manually
```bash
php /var/www/whatsbot/scripts/sync-brains.php
```

You should see output like:
```
[2025-11-16 18:00:00] Starting Brains ERP Sync
[2025-11-16 18:00:01] Fetching products from Brains...
[2025-11-16 18:00:02] Found 1250 products
[2025-11-16 18:00:15] Products: Created=100, Updated=1150, Skipped=0
[2025-11-16 18:00:16] Fetching customers from Brains...
[2025-11-16 18:00:17] Found 850 accounts
[2025-11-16 18:00:25] Customers: Created=50, Updated=800, Skipped=0
[2025-11-16 18:00:25] Sync completed successfully
```

### 3. Set up cron job (runs every 4 hours)
```bash
crontab -e
```

Add this line at the bottom:
```
0 */4 * * * /usr/bin/php /var/www/whatsbot/scripts/sync-brains.php >> /var/www/whatsbot/logs/sync.log 2>&1
```

Save and exit (Ctrl+X, then Y, then Enter if using nano).

### 4. Verify cron job is installed
```bash
crontab -l
```

## Cron Schedule Explained

`0 */4 * * *` means:
- Run at minute 0 (top of the hour)
- Every 4 hours
- Every day

So it runs at: **12:00 AM, 4:00 AM, 8:00 AM, 12:00 PM, 4:00 PM, 8:00 PM**

## Alternative Schedules

### Every 6 hours
```
0 */6 * * * /usr/bin/php /var/www/whatsbot/scripts/sync-brains.php >> /var/www/whatsbot/logs/sync.log 2>&1
```

### Every 2 hours
```
0 */2 * * * /usr/bin/php /var/www/whatsbot/scripts/sync-brains.php >> /var/www/whatsbot/logs/sync.log 2>&1
```

### Once per day at 2:00 AM
```
0 2 * * * /usr/bin/php /var/www/whatsbot/scripts/sync-brains.php >> /var/www/whatsbot/logs/sync.log 2>&1
```

## View Sync Logs

```bash
tail -f /var/www/whatsbot/logs/sync.log
```

Or view last 50 lines:
```bash
tail -50 /var/www/whatsbot/logs/sync.log
```

## Troubleshooting

### Cron not running?
Check cron service:
```bash
sudo systemctl status cron
```

### Check if cron executed:
```bash
grep CRON /var/log/syslog | tail -20
```

### Manual run to test:
```bash
php /var/www/whatsbot/scripts/sync-brains.php
```

## Disable Cron Job

To temporarily disable:
```bash
crontab -e
```

Add `#` at the start of the line:
```
# 0 */4 * * * /usr/bin/php /var/www/whatsbot/scripts/sync-brains.php >> /var/www/whatsbot/logs/sync.log 2>&1
```

Or remove the cron job entirely:
```bash
crontab -r
```
