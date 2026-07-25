# Configuration Guide

## Environment Configuration

### Database Settings
File: `config/database.php`

```php
private $host = "localhost";
private $db_name = "professional_barbershop";
private $username = "your_username";
private $password = "your_password";
```

### Application Settings
Access via Admin > Settings

#### General Settings
- **Shop Name**: Business name displayed everywhere
- **Address**: Full business address
- **Currency Symbol**: Default: R
- **Timezone**: Recommended: Africa/Johannesburg
- **Admin WhatsApp**: Format: 27XXXXXXXXX (no +)

#### SMTP Settings
Required for email notifications:
- **SMTP Host**: e.g., mail.yourdomain.com
- **SMTP Port**: 465 (SSL) or 587 (TLS)
- **SMTP Username**: Full email address
- **SMTP Password**: Email password
- **Encryption**: SSL or TLS

#### Paystack Settings
For card payment processing:
- **Public Key**: `pk_test_...` or `pk_live_...`
- **Secret Key**: `sk_test_...` or `sk_live_...`
- **Use Test Mode**: Enable for testing

**Getting Paystack Keys:**
1. Sign up at https://paystack.com
2. Go to Dashboard > Settings > API Keys
3. Copy Public Key and Secret Key
4. Use test keys for development, live keys for production

#### Loyalty Settings
- **Loyalty Rate**: Points earned per Rand (default: 0.1 = 1 point per R10)
- **Bronze Tier**: 0-999 points
- **Silver Tier**: 1000-2499 points
- **Gold Tier**: 2500-4999 points
- **VIP Tier**: 5000+ points

## User Roles

### Admin
Full access to all features:
- Manage all settings
- Add/edit/delete barbers
- View all reports
- Approve refunds
- Process cash-ups
- Manage branches

### Receptionist
Access to:
- POS system
- Walk-in management
- Queue management
- Bookings
- Payments
- Cash-up submission

### Barber
Access to:
- Own appointments
- Own earnings
- Personal profile

## Payment Methods

### Cash
- Immediate payment
- No transaction fees
- Manual entry

### Card (Yaco)
- Manual terminal entry
- Receptionist enters transaction code
- No online integration

### EFT
- Bank transfer
- Manual recording

### Paystack
- Online card payments
- Automatic verification
- Test/Live mode support

## Restaurant Hours Configuration
Configure in Settings:
- Opening hours
- Service duration
- Time slot intervals

## Notification Settings

### Email Notifications
- New bookings
- Sales completed
- Low stock alerts
- Cash-up submissions

### WhatsApp Notifications
Configure Admin WhatsApp number in Settings.
Uses CallMeBot API for messaging.

## Barcode Scanner Configuration

### Requirements
- HTTPS connection (required by browsers)
- Camera access permission
- Modern browser (Chrome, Safari, Edge)

### Supported Formats
- EAN-13
- EAN-8
- UPC-A
- UPC-E
- CODE-128

### Troubleshooting
1. Ensure HTTPS is enabled
2. Grant camera permission when prompted
3. Try different browser if issues persist
4. Check device camera works in other apps

## Backup Recommendations

### Daily
- Database dump
- Configuration files

### Weekly
- Full file backup
- Upload directory backup

### Monthly
- Complete system backup

## Security Checklist

- [ ] Change default passwords
- [ ] Enable SSL/HTTPS
- [ ] Update SMTP credentials
- [ ] Configure Paystack keys
- [ ] Set up WhatsApp notifications
- [ ] Review user permissions
- [ ] Test all payment methods
- [ ] Verify email notifications