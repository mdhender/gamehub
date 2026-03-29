# Deploying GameHub to a DigitalOcean Droplet

Target environment: **Ubuntu 25.10** (GNU/Linux 6.17.0-19-generic x86_64) with **Nginx**.

This guide assumes a fresh droplet. Replace `gamehub.example.com` with your actual domain throughout.

---

## Server Sizing

**Expected load:** ~3 concurrent users day-to-day, with spikes of up to a dozen players. The heaviest CPU usage occurs when the GM processes a turn (roughly every two weeks).

**Recommended droplet:** **1 vCPU / 1 GB RAM / 25 GB SSD ($6/mo)**

This is more than sufficient for this workload. SQLite handles low-concurrency reads/writes very well, and a dozen simultaneous players generating page loads is trivial for PHP-FPM + Nginx. The fortnightly turn processing may briefly spike CPU but won't be an issue on even the smallest droplet.

If you find memory pressure from PHP-FPM workers during turn processing, the next step up is **1 vCPU / 2 GB RAM / 50 GB SSD ($12/mo)**, which gives comfortable headroom.

> **When to upgrade:** Sustained memory usage above 80% (check with `free -h`), or if turn processing begins to time out. Monitor with `htop` and the `/up` health endpoint.

---

## 1. Initial Server Setup

### 1.1 Create a Deploy User

```bash
adduser deploy
usermod -aG sudo deploy
```

Copy your SSH key to the new user:

```bash
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

From this point forward, SSH in as `deploy`. Disable root SSH login in `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sudo systemctl restart ssh
```

### 1.2 System Updates

```bash
sudo apt update && sudo apt upgrade -y
```

---

## 2. Install Required Software

### 2.1 PHP 8.4

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo add-apt-repository -y ppa:ondrej/nginx
sudo apt update

sudo apt install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-sqlite3 \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-readline \
    php8.4-tokenizer \
    php8.4-fileinfo \
    php8.4-gd
```

### 2.2 SQLite3

```bash
sudo apt install -y sqlite3
```

### 2.3 Nginx

```bash
sudo apt install -y nginx
```

### 2.4 Certbot (Let's Encrypt SSL)

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 2.5 Node.js & Bun (for building frontend assets)

Install Node.js (LTS) via NodeSource:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Install Bun:

```bash
sudo apt install unzip
curl -fsSL https://bun.sh/install | bash
source ~/.bashrc
```

### 2.6 Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2.7 Other Utilities

```bash
sudo apt install -y git unzip acl
```

---

## 3. Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status verbose
```

This opens ports **22** (SSH), **80** (HTTP), and **443** (HTTPS) only.

---

## 4. Fail2Ban

```bash
sudo apt install -y fail2ban
```

Create a local config:

```bash
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
```

Edit `/etc/fail2ban/jail.local` and ensure these jails are enabled:

```ini
[sshd]
enabled  = true
port     = ssh
filter   = sshd
logpath  = /var/log/auth.log
maxretry = 5
bantime  = 3600
findtime = 600

[nginx-http-auth]
enabled  = true
port     = http,https
filter   = nginx-http-auth
logpath  = /var/log/nginx/error.log
maxretry = 5

[nginx-botsearch]
enabled  = true
port     = http,https
filter   = nginx-botsearch
logpath  = /var/log/nginx/access.log
maxretry = 2
```

```bash
sudo systemctl enable fail2ban
sudo systemctl restart fail2ban
```

---

## 5. Application Deployment

### 5.1 Directory Structure

```bash
sudo mkdir -p /var/www/gamehub
sudo chown deploy:www-data /var/www/gamehub
sudo chmod 770 /var/www/gamehub
```

### 5.2 Clone the Repository

```bash
cd /var/www/gamehub
git clone https://github.com/mdhender/gamehub.git .
```

### 5.3 Install Dependencies & Build

```bash
composer install --no-dev --optimize-autoloader

bun install
bun run build
```

### 5.4 Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with production values:

```dotenv
APP_NAME=GameHub
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gamehub.example.com

DB_CONNECTION=sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=stack
LOG_LEVEL=error
```

### 5.5 Create Database & Run Migrations

```bash
touch database/database.sqlite
php artisan migrate --force
```

### 5.6 Create the Admin User (First Deploy Only)

Run the dedicated command to create your admin account on first deploy:

```bash
php artisan app:create-admin-user "Your Name" "admin@example.com" "your-secure-password"
```

If you omit the arguments, the command will prompt you interactively. **Change the password** after your first login via the application's profile settings.

### 5.7 Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5.8 Set Permissions

The parent directory (`/var/www/gamehub`) is locked down to `deploy:www-data` with `770` (step 5.1), so no outside users can access any files within. Set all contents to `deploy:www-data` ownership — file modes stay at their git defaults (644/755). Only the runtime-writable directories need group-write access:

```bash
sudo chown -R deploy:www-data /var/www/gamehub
find /var/www/gamehub/storage -type d -exec chmod 770 {} \;
find /var/www/gamehub/storage -type f -exec chmod 660 {} \;
find /var/www/gamehub/bootstrap/cache -type d -exec chmod 770 {} \;
find /var/www/gamehub/bootstrap/cache -type f -exec chmod 660 {} \;
sudo chmod 770 /var/www/gamehub/database
sudo chmod 660 /var/www/gamehub/database/database.sqlite
```

---

## 6. PHP-FPM Configuration

Edit `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
user = deploy
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.4-fpm
```

---

## 7. Nginx Configuration

Create `/etc/nginx/sites-available/gamehub`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name gamehub.example.com;

    root /var/www/gamehub/public;
    index index.php;

    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(css|js|ico|gif|jpeg|jpg|png|woff2?|ttf|svg|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/gamehub /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

---

## 8. SSL with Certbot

Point your domain's DNS A record to the droplet's IP, then:

```bash
sudo certbot --nginx -d gamehub.example.com
```

Certbot will automatically modify the Nginx config to handle HTTPS and set up auto-renewal. Verify the renewal timer:

```bash
sudo systemctl status certbot.timer
```

---

## 9. Mailgun Configuration

The application uses Mailgun for transactional email. Ensure the required packages are installed (these should already be in `composer.json`):

```bash
composer require symfony/mailgun-mailer symfony/http-client
```

Add the following to your production `.env`:

```dotenv
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

MAILGUN_DOMAIN=yourdomain.com
MAILGUN_SECRET=your-mailgun-api-key
MAILGUN_ENDPOINT=api.mailgun.net
```

> **EU region:** If your Mailgun account is in the EU, set `MAILGUN_ENDPOINT=api.eu.mailgun.net`.

Rebuild the config cache for the changes to take effect:

```bash
php artisan config:cache
```

Verify mail is working by triggering a password reset or other notification from the application and checking the Mailgun dashboard for delivery logs.

---

## 10. Queue Worker (systemd)

The app uses `QUEUE_CONNECTION=database`. Create `/etc/systemd/system/gamehub-queue.service`:

```ini
[Unit]
Description=GameHub Queue Worker
After=network.target

[Service]
User=deploy
Group=www-data
WorkingDirectory=/var/www/gamehub
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/www/gamehub/storage/logs/queue-worker.log
StandardError=append:/var/www/gamehub/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable gamehub-queue
sudo systemctl start gamehub-queue
```

---

## 11. Automated Deployments

Create a deploy script at `/opt/gamehub/deploy.sh`:

```bash
#!/bin/bash
set -e

cd /var/www/gamehub

# Pull latest changes (reset ensures a clean state even if files were modified on the server)
git fetch origin main
git reset --hard origin/main

# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Install JS dependencies and build
bun install
bun run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue worker
sudo systemctl restart gamehub-queue

# Reload PHP-FPM (zero-downtime)
sudo systemctl reload php8.4-fpm

echo "✅ Deployed successfully."
```

```bash
chmod +x /var/www/gamehub/deploy.sh
```

Allow the deploy user to restart services without a password by adding to `/etc/sudoers.d/deploy`:

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart gamehub-queue
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
```

---

## 12. Additional Hardening

### Automatic Security Updates

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

### Limit Nginx Request Size

Add to the `server` block or `http` context in Nginx:

```nginx
client_max_body_size 10M;
```

### Disable Server Tokens

In `/etc/nginx/nginx.conf`, inside the `http` block:

```nginx
server_tokens off;
```

```bash
sudo systemctl reload nginx
```

### PHP Hardening

In `/etc/php/8.4/fpm/php.ini`:

```ini
expose_php = Off
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
```

---

## 13. Health Check

Verify the application is running:

```bash
curl -I https://gamehub.example.com/up
```

You should receive a `200 OK` response.
