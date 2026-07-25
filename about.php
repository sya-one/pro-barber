<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$pageTitle = 'About Us';
$pageDescription = 'About The Professional Barbershop - Premium grooming services in KwaDukuza with experienced barbers and quality products.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">About Us</h1>
        </div>
    </section>

    <!-- About Content -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <h2 class="mb-4">The Professional Barbershop</h2>
                    <p class="lead text-readable-muted">Premium grooming for the modern gentleman.</p>
                    <p class="text-readable">Located in the heart of KwaDukuza Central, we've been providing exceptional barber services since 2024. Our team of skilled barbers combines traditional techniques with modern style to give you the perfect look.</p>
                    <p class="text-readable">We believe that a great haircut isn't just about looking good - it's about feeling confident. Every visit to our shop is designed to be a premium experience, from the moment you walk in to the moment you leave looking sharp.</p>
                    <p class="text-readable">Our commitment to quality extends beyond our services. We hand-select the finest grooming products to ensure you can maintain your look at home with professional-grade products.</p>
                </div>
                <div class="col-lg-6">
                    <img src="/barbershop-system/assets/images/barber-shop-interior.jpg" alt="Barbershop Interior" class="img-fluid rounded" onerror="this.src='/barbershop-system/assets/images/default-avatar.png'">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Promise -->
    <section class="py-5" style="background: rgba(15,169,88,0.05);">
        <div class="container text-center">
            <h3 class="mb-4">Our Promise</h3>
            <div class="row g-4">
                <div class="col-md-4">
                    <i class="fas fa-clock fa-3x text-success mb-3"></i>
                    <h5>Timely Service</h5>
                    <p class="small text-readable">We respect your time and start punctually.</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-star fa-3x text-success mb-3"></i>
                    <h5>Premium Quality</h5>
                    <p class="small text-readable">Only the best products and techniques.</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-heart fa-3x text-success mb-3"></i>
                    <h5>Customer Satisfaction</h5>
                    <p class="small text-readable">Your happiness is our success.</p>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>