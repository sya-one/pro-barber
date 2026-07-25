<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
?>
<nav class="sidebar d-flex flex-column p-3">
    <div class="sidebar-logo text-center mb-4">
        <img src="../assets/images/logo.png" width="60" alt="Logo">
        <h5 class="mt-2 text-white">The Professional 🟢<br>Barbershop</h5>
    </div>
    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($role == 'admin'): ?>
            <li class="nav-item">
                <a href="../admin/dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="../reception/pos.php" class="nav-link text-white <?= $current_page=='pos.php'?'active':'' ?>"><i class="fas fa-cash-register me-2"></i>POS</a>
        </li>
            <li class="nav-item">
                <a href="../admin/barbers.php" class="nav-link text-white <?= $current_page == 'barbers.php' ? 'active' : '' ?>">
                    <i class="fas fa-cut me-2"></i>Barbers
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/services.php" class="nav-link text-white <?= $current_page == 'services.php' ? 'active' : '' ?>">
                    <i class="fas fa-scissors me-2"></i>Services
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/products.php" class="nav-link text-white <?= $current_page=='products.php'?'active':'' ?>">
                    <i class="fas fa-box me-2"></i>Products
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/gallery.php" class="nav-link text-white <?= $current_page=='gallery.php'?'active':'' ?>">
                    <i class="fas fa-images me-2"></i>Gallery
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/customers.php" class="nav-link text-white <?= $current_page == 'customers.php' ? 'active' : '' ?>">
                    <i class="fas fa-users me-2"></i>Customers
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/bookings.php" class="nav-link text-white <?= $current_page == 'bookings.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Bookings
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/payments.php" class="nav-link text-white <?= $current_page == 'payments.php' ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-wave me-2"></i>Payments
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/invoices.php" class="nav-link text-white <?= $current_page=='invoices.php'?'active':'' ?>"><i class="fas fa-file-invoice me-2"></i>Invoices</a>
            </li>
            <li class="nav-item">
                <a href="../admin/loyalty.php" class="nav-link text-white <?= $current_page=='loyalty.php'?'active':'' ?>"><i class="fas fa-star me-2"></i>Loyalty</a>
            </li>
            <li class="nav-item">
                <a href="../admin/reports.php" class="nav-link text-white <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/users.php" class="nav-link text-white <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-cog me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/queue.php" class="nav-link text-white <?= $current_page == 'queue.php' ? 'active' : '' ?>">
                    <i class="fas fa-list-ol me-2"></i>Queue
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/commissions.php" class="nav-link text-white <?= $current_page == 'commissions.php' ? 'active' : '' ?>">
                    <i class="fas fa-coins me-2"></i>Commissions
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/expenses.php" class="nav-link text-white <?= $current_page == 'expenses.php' ? 'active' : '' ?>">
                    <i class="fas fa-receipt me-2"></i>Expenses
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/cashup.php" class="nav-link text-white <?= $current_page == 'cashup.php' ? 'active' : '' ?>">
                    <i class="fas fa-calculator me-2"></i>Cash-Up
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/suppliers.php" class="nav-link text-white <?= $current_page == 'suppliers.php' ? 'active' : '' ?>">
                    <i class="fas fa-truck me-2"></i>Suppliers
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/refunds.php" class="nav-link text-white <?= $current_page == 'refunds.php' ? 'active' : '' ?>">
                    <i class="fas fa-undo me-2"></i>Refunds
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/branches.php" class="nav-link text-white <?= $current_page == 'branches.php' ? 'active' : '' ?>">
                    <i class="fas fa-building me-2"></i>Branches
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/financial_dashboard.php" class="nav-link text-white <?= $current_page == 'financial_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line me-2"></i>Financial
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/calendar.php" class="nav-link text-white <?= $current_page == 'calendar.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Calendar
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/product_profitability.php" class="nav-link text-white <?= $current_page == 'product_profitability.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie me-2"></i>Products
                </a>
            </li>
            <li class="nav-item">
                <a href="../admin/settings.php" class="nav-link text-white <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="../migrate_web.php" class="nav-link text-white <?= $current_page == 'migrate_web.php' ? 'active' : '' ?>">
                    <i class="fas fa-sync-alt me-2"></i>Migrate
                </a>
            </li>
        <?php elseif ($role == 'barber'): ?>
            <li class="nav-item">
                <a href="../barber/dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="../barber/appointments.php" class="nav-link text-white <?= $current_page == 'appointments.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check me-2"></i>Appointments
                </a>
            </li>
            <li class="nav-item">
                <a href="../barber/queue.php" class="nav-link text-white <?= $current_page == 'queue.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-clock me-2"></i>My Queue
                </a>
            </li>
            <li class="nav-item">
                <a href="../barber/earnings.php" class="nav-link text-white <?= $current_page == 'earnings.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line me-2"></i>Earnings
                </a>
            </li>
            <li class="nav-item">
                <a href="../barber/profile.php" class="nav-link text-white <?= $current_page=='profile.php'?'active':'' ?>">
                    <i class="fas fa-user-circle me-2"></i>Profile</a>
            </li>

        <?php elseif ($role == 'receptionist'): ?>
            <li class="nav-item">
                <a href="../reception/dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li><a href="../reception/payments.php" class="nav-link text-white <?= $current_page=='payments.php'?'active':'' ?>"><i class="fas fa-money-bill-wave me-2"></i>Record Payment</a>
        </li>
            <li class="nav-item">
                <a href="../reception/pos.php" class="nav-link text-white <?= $current_page=='pos.php'?'active':'' ?>"><i class="fas fa-cash-register me-2"></i>POS</a>
        </li>
            <li class="nav-item">
                <a href="../reception/walkin.php" class="nav-link text-white <?= $current_page == 'walkin.php' ? 'active' : '' ?>">
                    <i class="fas fa-walking me-2"></i>Walk‑ins
                </a>
            </li>
            <li class="nav-item">
                <a href="../reception/queue.php" class="nav-link text-white <?= $current_page == 'queue.php' ? 'active' : '' ?>">
                    <i class="fas fa-list-ol me-2"></i>Queue
                </a>
            </li>
            <li class="nav-item">
                <a href="../reception/bookings.php" class="nav-link text-white <?= $current_page == 'bookings.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Bookings
                </a>
            </li>
        <?php endif; ?>

        <!-- Logout (always shown) -->
        <li class="mt-auto">
            <a href="../logout.php" class="nav-link text-white">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </li>
    </ul>
</nav>