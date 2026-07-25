<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$services = $db->query("SELECT * FROM services WHERE is_active=1 ORDER BY price ASC")->fetchAll();

$pageTitle = 'Our Services';
$pageDescription = 'Premium barbershop services including haircuts, beard trims, and grooming packages. Book online at The Professional Barbershop, KwaDukuza.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Our Services</h1>
            <p class="lead">Premium grooming tailored to your style</p>
        </div>
    </section>

    <!-- Services List -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <?php foreach ($services as $service): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 text-center p-4">
                        <div class="service-icon">
                            <i class="fas fa-cut"></i>
                        </div>
                        <h4 class="mb-2"><?= htmlspecialchars($service['name']) ?></h4>
                        <p class="text-readable-muted mb-3"><?= htmlspecialchars($service['description'] ?? '') ?></p>
                        <div class="mb-3">
                            <span class="service-price display-6"><?= formatCurrency($service['price']) ?></span>
                        </div>
                        <?php if (!empty($service['duration'])): ?>
                        <p class="text-readable-muted small mb-3"><i class="fas fa-clock me-1"></i> <?= $service['duration'] ?> minutes</p>
                        <?php endif; ?>
                        <a href="/book?service=<?= $service['id'] ?>" class="btn btn-success"><i class="fas fa-calendar-plus me-2"></i>Book Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Booking CTA -->
    <section class="py-5 text-center" style="background: rgba(15,169,88,0.05);">
        <div class="container">
            <h2 class="mb-3">Ready to Look Sharp?</h2>
            <p class="mb-4 text-readable">Book your appointment in seconds</p>
            <a href="/book" class="btn btn-success btn-lg px-5"><i class="fas fa-calendar-alt me-2"></i> BOOK APPOINTMENT</a>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>