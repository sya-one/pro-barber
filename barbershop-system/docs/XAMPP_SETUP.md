# XAMPP Setup Guide

## Prerequisites
- XAMPP for Windows (or Mac/Linux equivalent)
- PHP 8.0 or higher
- MySQL/MariaDB

## Installation Steps

### 1. Install XAMPP
Download and install XAMPP from: https://www.apachefriends.org/index.html

### 2. Start Services
Open XAMPP Control Panel and start:
- Apache
- MySQL

### 3. Import Database
1. Open phpMyAdmin at http://localhost/phpmyadmin
2. Create database: `professional_barbershop`
3. Import SQL file: `sql/professional_barbershop.sql`

Or use command line:
```bash
mysql -u root -e "CREATE DATABASE professional_barbershop"
mysql -u root professional_barbershop < sql/professional_barbershop.sql
```

### 4. Configure Database
Edit `config/database.php`:
```php
private $host = "localhost";
private $db_name = "professional_barbershop";
private $username = "root";
private $password = "";
```

### 5. Configure Application URL
Edit `reception/pos.php` and other files to set the correct base URL:
```php
$base_url = "http://localhost:81/barbershop-system/";
```

### 6. Import Migration Script
Run the migration script to add new columns/tables:
```sql
-- In phpMyAdmin or command line
SOURCE sql/migrations/2026_07_add_features.sql;
```

### 7. Set File Permissions
Ensure these directories are writable:
- `uploads/products/`
- `uploads/barbers/`
- `uploads/expenses/`
- `logs/`

### 8. Create Default Users
Default credentials:
- Admin: `admin` / `password123`
- Receptionist: `reception` / `password123`

Change passwords immediately after first login!

## Troubleshooting

### Port Conflicts
If port 80 is in use, change Apache port in XAMPP Control Panel settings.

### MySQL Connection Errors
Ensure MySQL is running and the database was created correctly.

### Permission Errors
On Windows, run XAMPP Control Panel as Administrator.

### Barcode Scanner Issues
- Use HTTPS (XAMPP has SSL support)
- Grant camera permissions when prompted
- Try a different browser if issues persist

## Production Considerations
- Change all default passwords
- Update SMTP settings in database
- Configure Paystack keys
- Set up proper backup schedule
- Enable SSL certificate