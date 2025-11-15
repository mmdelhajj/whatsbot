# GitHub Setup Instructions

Follow these steps to push your project to GitHub:

## Step 1: Reinitialize Git (if needed)

```bash
cd /var/www/whatsapp-bot
rm -rf .git
git init
git branch -M main
```

## Step 2: Configure Git

```bash
git config user.name "Your Name"
git config user.email "your.email@example.com"
```

## Step 3: Add Files to Git

```bash
# Add all files (respecting .gitignore)
git add .

# Check what will be committed
git status
```

## Step 4: Create First Commit

```bash
git commit -m "Initial commit: WhatsApp Bot for Librarie Memoires

Features:
- Multilingual support (Arabic, English, French, Lebanese)
- Smart product search with AI
- Order management with Brains ERP
- FAQ auto-responses
- 10 products per page pagination
- GPS location integration"
```

## Step 5: Create GitHub Repository

1. Go to https://github.com
2. Click the **"+"** button (top right)
3. Select **"New repository"**
4. Name it: `whatsapp-bot` or `librarie-memoires-bot`
5. **Don't initialize** with README (we already have one)
6. Click **"Create repository"**

## Step 6: Push to GitHub

GitHub will show you commands. Use these:

```bash
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
git push -u origin main
```

**Example:**
```bash
git remote add origin https://github.com/mmdelhajj/whatsapp-bot.git
git push -u origin main
```

You'll be prompted for your GitHub username and password (or personal access token).

## Step 7: Verify Upload

Visit your GitHub repository URL to confirm all files are there.

---

## ⚠️ Important Notes

### What's NOT in GitHub (by design):

- ❌ `.env` file (your actual credentials)
- ❌ `config/config.php` (if it has sensitive data)
- ❌ `images/` folder (too large)
- ❌ `logs/` folder
- ❌ `vendor/` folder (use `composer install` on new server)

### What IS in GitHub:

- ✅ All PHP source code
- ✅ `.env.example` (template without secrets)
- ✅ `.gitignore` (protection rules)
- ✅ `README.md` (setup instructions)
- ✅ Database schema (if you add it)
- ✅ Admin dashboard
- ✅ Test scripts

---

## 🔐 Security Tips

1. **Never** commit your `.env` file
2. **Never** commit API keys or passwords
3. **Always** use `.env.example` as a template
4. Consider making the repository **private** if it contains business logic

---

## 📦 Deploying to New Server

On your new Ubuntu server with more storage:

```bash
# Install requirements
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring composer git nginx mysql-server

# Clone your repository
cd /var/www
git clone https://github.com/YOUR_USERNAME/whatsapp-bot.git
cd whatsapp-bot

# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env
nano .env  # Add your credentials

# Set up database (see README.md)
# Configure Nginx (see README.md)
# Set permissions (see README.md)

# Upload product images separately
mkdir -p images/products
# Use SCP or SFTP to upload images

# Test
php admin/test-comprehensive-translations.php
```

---

## 🔄 Keeping Servers in Sync

After setup, you can continue development with me on the new server:

```bash
# Make changes on new server
git add .
git commit -m "Description of changes"
git push

# Or pull changes from another location
git pull origin main
```

---

## 💬 Continue with Claude Code

On your new server, you can continue our conversation:

1. Install Claude Code CLI
2. Run: `claude code` in your project directory
3. Reference this conversation or start fresh
4. All your code will be in sync via GitHub!

---

## ❓ Troubleshooting

**Q: Git push asks for password but won't accept it?**
A: GitHub no longer accepts passwords. Create a Personal Access Token:
- GitHub → Settings → Developer settings → Personal access tokens → Generate new token
- Use the token as your password

**Q: Files are missing after clone?**
A: Check if they're in `.gitignore`. Large files (images) should be uploaded separately.

**Q: Database is empty?**
A: Export your current database and import on new server:
```bash
# Old server
mysqldump -u whatsapp_user -p whatsapp_bot > database_backup.sql

# Transfer to new server, then:
mysql -u whatsapp_user -p whatsapp_bot < database_backup.sql
```

---

**Ready to push!** Follow Step 1-6 above to get started. 🚀
