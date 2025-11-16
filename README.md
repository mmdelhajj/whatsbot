# WhatsApp Bot - AI-Powered Customer Service

An intelligent WhatsApp chatbot powered by Claude AI that handles customer inquiries, product searches, order management, and integrates with Brains ERP system.

## Features

- 🤖 **AI-Powered Responses** - Intelligent customer service using Anthropic's Claude AI
- 🛍️ **Product Search** - Search and browse products with multilingual support
- 📦 **Order Management** - Track orders and manage customer purchases
- 👥 **Customer Management** - Automatic customer profile creation and management
- 🌍 **Multilingual Support** - Arabic, English, French, and Lebanese (Franco-Arabic)
- 💬 **Custom Q&A** - Admin-managed custom responses for common questions
- 📊 **Admin Dashboard** - Comprehensive admin panel for management
- 🔗 **ERP Integration** - Seamless integration with Brains ERP system
- 📍 **Google Maps Integration** - Share store location with customers
- ⚡ **Fast Response Times** - Optimized for speed and performance

## One-Click Installation

### Prerequisites

- Ubuntu 22.04 or higher
- Root or sudo access
- Minimum 1GB RAM
- Internet connection

### Installation

Run this single command as root (or with sudo):

```bash
wget -O install.sh https://raw.githubusercontent.com/mmdelhajj/whatsbot/main/install.sh && sudo bash install.sh
```

The installer will:
1. ✅ Install all required dependencies (Nginx, MySQL, PHP 8.1)
2. ✅ Create and configure the database
3. ✅ Download and setup the application
4. ✅ Configure Nginx web server
5. ✅ Create admin user account
6. ✅ Set proper file permissions
7. ✅ Optionally install phpMyAdmin

### What You'll Need

During installation, you'll be prompted for:

- **Store Name** - Your business name
- **Anthropic API Key** - Get one at https://console.anthropic.com
- **ProxSMS Credentials** - Account ID and Send Secret from ProxSMS
- **Brains ERP API URL** - Your Brains ERP API endpoint (optional)
- **Store Information** - Location, phone, hours, coordinates
- **Admin Credentials** - Username and password for admin panel

## Quick Start

After installation:

1. **Access Admin Panel**: http://YOUR_SERVER_IP/admin
2. **Configure ProxSMS Webhook**: Point to http://YOUR_SERVER_IP/webhook-whatsapp.php
3. **Import Products**: Use admin panel to sync from Brains ERP
4. **Test**: Send a WhatsApp message to your bot number

## Configuration

### ProxSMS Webhook Setup

1. Login to ProxSMS at https://proxsms.com
2. Go to Settings → Webhooks
3. Add new webhook:
   - **URL**: http://YOUR_SERVER_IP/webhook-whatsapp.php
   - **Secret**: (from your .env WEBHOOK_SECRET)
   - **Events**: Enable "WhatsApp Messages"
4. Save and activate

### Admin Panel Features

- 📊 Dashboard with real-time statistics
- 👥 Customer management and search
- 💬 Complete message history
- 📦 Order tracking and management
- 🛍️ Product catalog with search
- ❓ Custom Q&A for instant responses
- ⚙️ Settings and configuration

## Custom Q&A

Create instant responses without using AI:

1. Go to Admin Panel → Custom Q&A
2. Click "Add New Q&A"
3. Enter keywords in multiple languages
4. Provide answers in Arabic, English, French, Lebanese
5. Save and activate

## Multilingual Support

The bot automatically detects and responds in:

- **Arabic (العربية)** - Full RTL support
- **English** - Default fallback
- **French (Français)** - Complete translation
- **Lebanese (Franco-Arabic)** - Casual dialect (e.g., "fi kteb", "3andak")

## Troubleshooting

### Bot doesn't respond

1. Check webhook in ProxSMS settings
2. Verify webhook secret matches .env
3. Check logs: `tail -f /var/www/whatsbot/logs/webhook.log`
4. Test APIs in Admin → API Tests

### Database errors

1. Verify .env credentials
2. Check MySQL status: `sudo systemctl status mysql`
3. Test connection: `mysql -u whatsapp_user -p whatsapp_bot`

### Nginx errors

1. Check PHP-FPM: `sudo systemctl status php8.1-fpm`
2. View nginx logs: `sudo tail -f /var/log/nginx/whatsbot-error.log`
3. Test config: `sudo nginx -t`

## Updating

```bash
cd /var/www/whatsbot
sudo git pull origin main
sudo chown -R www-data:www-data /var/www/whatsbot
sudo systemctl reload php8.1-fpm
```

## Security

- Environment variables in .env (never committed)
- Bcrypt password hashing
- Webhook secret validation
- Proper file permissions
- Nginx blocks sensitive files

## Support

- GitHub Issues: https://github.com/mmdelhajj/whatsbot/issues
- Documentation: This README

## Credits

Built with Claude Code by Anthropic
