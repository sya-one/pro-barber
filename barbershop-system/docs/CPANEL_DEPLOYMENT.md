# cPanel Deployment Guide

## Prerequisites
- cPanel hosting account
- PHP 8.0 or higher
- MySQL database
- FTP/SFTP access

## Deployment Steps

### 1. Create Database
1. Log in to cPanel
2. Go to "MySQL Databases"
3. Create database: `professional_barbershop`
4. Create database user
5. Add user to database with ALL PRIVILEGES
6. Note database name, username, and password

### 2. Import Database
1. Go to "phpMyAdmin"
2. Select your database
3. Click "Import"
4. Choose SQL file: `sql/professional_barbershop.sql`
5. Click "Go"

### 3. Upload Files
1. Use FTP/SFTP or File Manager
2. Upload all files to: `public_html/` or `public_html/barbershop-system/`
3. Ensure file permissions:
   - Directories: 755
   - Files: 644
   - `uploads/`, `logs/` directories should be writable

### 4. Configure Database
Edit `config/database.php`:
```php
private $host = "localhost";
private $db_name = "professional_barbershop";  // Your database name
private $username = "your_db_user";             // Your database user
private $password = "your_db_password";          // Your database password
```

### 5. Configure Application URL
In `reception/pos.php`, update the base URL:
```php
$base_url = "https://yourdomain.com/barbershop-system/";
```

### 6. Import Migration Script
Run the migration SQL in phpMyAdmin or via command line:
```sql
-- Run each CREATE TABLE statement from sql/migrations/2026_07_add_features.sql
-- Or import the entire file
```

### 7. Configure Email Settings
1. Go to cPanel > "Email Accounts"
2. Create email addresses for:
   - `notifications@yourdomain.com`
   - `info@yourdomain.com`
3. Update SMTP settings in Admin > Settings

### 8. SSL Certificate
1. Go to cPanel > "SSL/TLS"
2. Install SSL certificate or use Let's Encrypt
3. Update `$base_url` to use HTTPS

### 9. Set Up Cron Jobs (Optional)
For notification polling and maintenance:
```bash
# Every 5 minutes
*/5 * * * * /usr/local/bin/php /home/username/public_html/barbershop-system/cron/notifications.php
```

### 10. Configure Email Sending
If using SMTP2GO or other email service:
1. Updates SMTP settings in Admin > Settings > SMTP
2. Test email functionality

## Common Issues

### 1. Database Connection Failed
- Verify database credentials in `config/database.php`
- Ensure database user has proper permissions
- Check if database exists

### 2. File Permission Errors
- Set `uploads/` and `logs/` directories to 755
- Some hosts require 777 for uploads

### 3. Email Not Sending
- Verify SMTP settings
- Check if host allows outbound SMTP
- Consider using external email service (SMTP2GO, SendGrid)

### 4. Barcode Scanner Not Working
- Must use HTTPS
- Grant camera permissions
- Some hosts block getUserMedia API

### 5. Session Issues
- Ensure `session.save_path` is writable
- Check session cookie settings

## Security Recommendations

1. **Change all default passwords**
2. **Enable SSL**
3. **Regular backups**
4. **Update PHP regularly**
5. **Use strong database passwords**
6. **Limit login attempts**

## Post-Installation

1. Log in as admin
2. Change admin password
3. Configure SMTP settings
4. Add Paystack API keys
5. Set up WhatsApp notifications
6. Create user accounts for staff
7. Configure business settings