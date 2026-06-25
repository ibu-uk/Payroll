# PayrollPro – Bilingual Payroll Management System

**PHP + MySQL | Arabic/English | PDF | Excel | 10,000+ employees**

---

## ⚡ Quick Start (XAMPP / Local)

### Method A – Browser Installer (Easiest)
1. Copy the `payroll` folder to `C:\xampp\htdocs\payroll\`
2. Start Apache + MySQL in XAMPP Control Panel
3. Open: **http://localhost/payroll/install.php**
4. Follow the 2-step wizard (DB settings + admin account)
5. Login at: **http://localhost/payroll/**
6. 🗑️ **Delete `install.php` after setup!**

### Method B – phpMyAdmin
1. Copy folder to `htdocs/payroll/`
2. Open phpMyAdmin → Create database `payroll_db` (utf8mb4)
3. Import `install.sql` into the database
4. Edit `config/config.php`: set DB_PASS to your password if any
5. Open: **http://localhost/payroll/**
6. Login: `admin@payroll.local` / `admin123`

---

## 🚀 Deploy to Digital Ocean (Production)

```bash
# Upload files to server
scp -r payroll/ root@YOUR_SERVER_IP:/var/www/

# SSH into server
ssh root@YOUR_SERVER_IP

# Set permissions
chown -R www-data:www-data /var/www/payroll
chmod -R 755 /var/www/payroll
chmod -R 775 /var/www/payroll/uploads /var/www/payroll/cache

# Create database
mysql -u root -p
CREATE DATABASE payroll_db CHARACTER SET utf8mb4;
CREATE USER 'payroll_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL ON payroll_db.* TO 'payroll_user'@'localhost';
EXIT;

mysql -u root -p payroll_db < /var/www/payroll/install.sql

# Edit config
nano /var/www/payroll/config/config.php
# Set DB_USER, DB_PASS, display_errors=0

# Apache vhost
nano /etc/apache2/sites-available/payroll.conf
```

Apache vhost:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/payroll
    <Directory /var/www/payroll>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
```bash
a2ensite payroll.conf && a2enmod rewrite && systemctl restart apache2
```

---

## 📦 Features

| Module | Description |
|--------|-------------|
| 🏠 Dashboard | Live stats, 6-month payroll chart, dept breakdown |
| 👥 Employees | Full CRUD, photo upload, allowances, bank info |
| 💰 Payroll | Monthly processing, approval workflow, payslips |
| 📅 Attendance | Monthly grid, P/A/L tracking per employee |
| 🏖️ Leaves | Request & approval, 6 leave types |
| 🏢 Departments | Card-based management |
| 📊 Reports | PDF payslips (print), Excel XLSX, CSV exports |
| ⚙️ Settings | Company info, allowances, deductions, users |
| 🌐 Bilingual | Full Arabic/English with RTL layout |

## 📋 Requirements

- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Extensions: pdo_mysql, mbstring, gd, zip
- Apache with mod_rewrite

## 🔐 Default Login (after install)

```
Email:    admin@payroll.local
Password: (set during installation)
```

## 📁 Folder Structure

```
payroll/
├── index.php          ← Main router
├── install.php        ← Browser installer (DELETE after use)
├── install.sql        ← Database schema
├── config/
│   ├── config.php     ← ⚡ Edit DB credentials here
│   ├── en.php         ← English translations
│   └── ar.php         ← Arabic translations
├── includes/          ← Core PHP classes
├── pages/             ← All page modules
├── exports/           ← PDF, Excel, CSV exports
├── assets/css/        ← Stylesheets
├── assets/js/         ← JavaScript
├── uploads/           ← Employee photos, logos (writable)
└── cache/             ← Error logs (writable)
```

## 🛠️ Seeding Demo Data

After installation, run in browser:
```
http://localhost/payroll/seed_demo.php?count=50
```
Or via CLI: `php seed_demo.php 100`
