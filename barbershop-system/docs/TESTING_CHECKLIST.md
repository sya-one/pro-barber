# Testing Checklist

## Authentication Tests
- [ ] Login with valid credentials
- [ ] Login with invalid credentials (should fail)
- [ ] Session timeout after 30 minutes
- [ ] Remember me functionality
- [ ] Logout clears session
- [ ] Password hashing working
- [ ] Role-based access control

## Admin Tests
- [ ] Dashboard loads correctly
- [ ] All menu items accessible
- [ ] User management works
- [ ] Barber management works
- [ ] Service management works
- [ ] Product management works
- [ ] Customer management works
- [ ] Booking management works
- [ ] Payment recording works
- [ ] Invoice generation works
- [ ] Email sending works
- [ ] WhatsApp notifications work

## Receptionist Tests
- [ ] POS system loads
- [ ] Customer search works
- [ ] Product selection works
- [ ] Service selection works
- [ ] Barber selection works
- [ ] Appointment loading works
- [ ] Barcode scanning works
- [ ] Loyalty points applied
- [ ] Payment methods work (Cash, Card, EFT, Paystack)
- [ ] Invoice generation
- [ ] Email invoice
- [ ] Inventory deduction
- [ ] Commission calculation

## Barber Tests
- [ ] Dashboard loads
- - Appointments view
- - Earnings view
- - Queue view
- - Profile view

## Online Booking Tests
- [ ] Booking page loads
- [ ] Service selection
- [ ] Barber selection
- [ ] Date/time selection
- [ ] Availability check
- [ ] Booking confirmation
- [ ] Booking code generated
- [ ] Customer created/updated
- [ ] Admin notification
- [ ] Email notification
- [ ] WhatsApp notification

## Walk-in Queue Tests
- [ ] Walk-in registration
- [ ] Customer autocomplete
- [ ] Queue position
- [ ] Status updates (waiting, called, in-service, completed)
- [ ] TV display auto-refresh

## Barcode Scanner Tests
- [ ] Camera permission request
- [ ] Barcode detection
- [ ] Product lookup
- [ ] Add to cart
- [ ] Duplicate scan prevention
- [ ] Manual entry
- [ ] Mobile device compatibility
- [ ] iOS Safari compatibility
- [ ] Android compatibility

## Payment Tests
- [ ] Cash payment
- [ ] Card payment (Yaco)
- [ ] EFT payment
- [ ] Paystack payment:
  - [ ] Test mode
  - [ ] Live mode
  - [ ] Payment verification
  - [ ] Callback handling
  - [ ] Duplicate prevention

## Inventory Tests
- [ ] Stock deduction on sale
- [ ] Low stock alert
- [ ] Stock adjustment
- [ ] Product creation
- [ ] Product editing
- [ ] Product deletion

## Commission Tests
- [ ] Commission rate applied
- [ ] Commission calculated
- [ ] Commission recorded
- [ ] Barber earnings view
- [ ] Admin commission view
- [ ] Commission payment marking

## Expense Management Tests
- [ ] Add expense
- [ ] Edit expense
- [ ] Delete expense
- [ ] Receipt upload
- [ ] Expense categories
- [ ] Expense reports

## Cash-Up Tests
- [ ] Submit cash-up
- [ ] Expected cash calculation
- [ ] Actual cash entry
- [ ] Variance calculation
- [ ] Status changes (open, submitted, approved, closed)
- [ ] Cash-up reports

## Refund Tests
- [ ] Refund request
- [ ] Admin approval
- [ ] Stock return
- [ ] Loyalty points reversal
- [ ] Invoice update
- [ ] Full and partial refunds

## Loyalty Tests
- [ ] Points earned on sale
- [ ] Points earned on online order
- [ ] Points redemption
- [ ] Tier assignment
- [ ] Points reversal on refund

## Reporting Tests
- [ ] Revenue charts
- [ ] Service reports
- [ ] Barber performance
- [ ] Product sales
- [ ] Commission reports
- [ ] CSV export
- [ ] Print/PDF export

## Notification Tests
- [ ] In-app notifications
- [ ] Notification bell count
- [ ] Email notifications
- [ ] WhatsApp notifications

## Mobile/Tablet Tests
- [ ] Mobile layout
- [ ] Tablet layout
- [ ] Touch optimization
- [ ] Barcode scanner on mobile
- [ ] Payment forms

## Security Tests
- [ ] CSRF protection
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] Session security
- [ ] Password hashing
- [ ] File upload security
- [ ] CORS headers
- [ ] Security headers

## Database Tests
- [ ] All tables created
- [ ] Foreign keys working
- [ ] Indexes exist
- [ ] Data integrity
- [ ] Migrations applied

## Error Handling Tests
- [ ] No PHP errors/warnings
- [ ] No SQL errors
- [ ] No JavaScript errors
- [ ] Graceful error messages
- [ ] Proper redirects

## Final Verification
- [ ] All pages load
- [ ] All links work
- [ ] All forms validate
- [ ] All AJAX calls work
- [ ] Console has no errors
- [ ] All features functional