# WhatsApp Bot for Librarie Memoires

Intelligent multilingual WhatsApp bot for product catalog, orders, and customer service.

## 🌟 Features

- **Multilingual Support**: Arabic, English, French, Lebanese Arabic
- **Smart Product Search**: AI-powered product search with 200+ translations
- **Order Management**: Complete order workflow with Brains ERP integration
- **FAQ Auto-Responses**: Automatic answers for hours, location, delivery
- **Conversation Memory**: State-based conversation tracking
- **Pagination**: Shows 10 products per page with smart navigation
- **Visual Product Display**: Product listings with prices and stock status

## 📋 Requirements

- PHP 8.1 or higher
- MySQL/MariaDB
- Composer
- Nginx or Apache
- Git

## 🚀 Installation on New Server

### Step 1: Clone the Repository

```bash
cd /var/www
git clone https://github.com/YOUR_USERNAME/whatsapp-bot.git
cd whatsapp-bot
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Configure Environment

```bash
# Copy the example environment file
cp .env.example .env

# Edit the .env file with your credentials
nano .env
```

Fill in your actual credentials:
- Database connection details
- Brains API URL
- WhatsApp (ProxSMS) credentials
- Anthropic Claude AI API key

### Step 4: Set Up Database

```bash
# Create the database
mysql -u root -p

CREATE DATABASE whatsapp_bot;
CREATE USER 'whatsapp_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON whatsapp_bot.* TO 'whatsapp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import the database schema (if you have a backup)
mysql -u whatsapp_user -p whatsapp_bot < database_backup.sql
```

### Step 5: Set Permissions

```bash
# Create required directories
mkdir -p logs uploads images

# Set proper permissions
chown -R www-data:www-data /var/www/whatsapp-bot
chmod -R 755 /var/www/whatsapp-bot
chmod -R 775 logs uploads images
```

### Step 6: Configure Nginx

```bash
# Create Nginx configuration
nano /etc/nginx/sites-available/whatsapp-bot
```

Add this configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/whatsapp-bot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
# Enable the site
ln -s /etc/nginx/sites-available/whatsapp-bot /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### Step 7: Restart PHP-FPM

```bash
systemctl restart php8.3-fpm
```

## 📸 Product Images (Optional)

If you have product images, upload them separately to save space in git:

```bash
# On new server, create images directory
mkdir -p /var/www/whatsapp-bot/images/products

# Upload images via SCP/SFTP
scp -r /path/to/images/* user@newserver:/var/www/whatsapp-bot/images/products/
```

## 🧪 Testing

Test the bot is working:

```bash
cd /var/www/whatsapp-bot/admin
php test-comprehensive-translations.php
```

## 📚 File Structure

```
whatsapp-bot/
├── admin/              # Admin dashboard and test scripts
├── config/             # Configuration files
├── logs/               # Log files
├── public/             # Public facing files
├── src/
│   ├── Controllers/    # MessageController, etc.
│   ├── Models/         # Database models
│   ├── Services/       # External service integrations
│   └── Utils/          # Helper classes
├── .env.example        # Environment template
├── .gitignore          # Git ignore rules
└── README.md           # This file
```

## 🔧 Configuration

### Environment Variables

All sensitive configuration is in `.env`:

- **Database**: Connection details for MySQL
- **Brains API**: ERP integration endpoint
- **WhatsApp**: ProxSMS account credentials
- **Claude AI**: Anthropic API key for smart features

### Store Settings

Update in `.env`:
- `STORE_NAME`: Your store name
- `STORE_LOCATION`: Your store location
- `TIMEZONE`: Your timezone
- `CURRENCY`: Your currency code

## 🌍 Supported Languages

The bot automatically detects and responds in:

1. **English** - Full support
2. **Arabic** - Native script support
3. **French** - Full support
4. **Lebanese Arabic** - Transliteration (Franco-Arabic)

## 🤖 AI Features

Powered by Claude AI for:
- Smart product search
- Natural language understanding
- Complex query handling
- Multilingual intent detection

## 📞 Support

For issues or questions, check the logs:

```bash
tail -f /var/www/whatsapp-bot/logs/app.log
tail -f /var/www/whatsapp-bot/logs/webhook.log
```

## 📝 License

Private project for Librarie Memoires

## 👨‍💻 Development

To contribute or continue development:

1. Clone the repository
2. Create a new branch: `git checkout -b feature-name`
3. Make your changes
4. Test thoroughly
5. Commit: `git commit -m "Description"`
6. Push: `git push origin feature-name`

---

**Last Updated**: 2025
**Version**: 2.0
