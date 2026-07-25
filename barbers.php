<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$barbers = $db->query("SELECT * FROM barbers WHERE is_active=1")->fetchAll();

$pageTitle = 'Our Barbers';
$pageDescription = 'Meet our master barbers at The Professional Barbershop. Experienced professionals dedicated to premium grooming in KwaDukuza.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Meet Our Barbers</h1>
            <p class="lead">Skilled professionals dedicated to your perfect look</p>
        </div>
    </section>

    <!-- Barbers Grid -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <?php foreach ($barbers as $barber): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 text-center p-4">
                        <img src="/barbershop-system/uploads/barbers/<?= htmlspecialchars($barber['photo'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($barber['full_name']) ?>" class="barber-img" onerror="this.src='/barbershop-system/assets/images/default-avatar.png'">
                        <h3 class="mb-1 barber-name"><?= htmlspecialchars($barber['full_name']) ?></h3>
                        <p class="barber-specialty text-success mb-3">Master Barber</p>
                        <p class="text-readable-muted small mb-3">Expert in classic cuts, modern styles, and precision grooming.</p>
                        <span class="availability-badge availability-free mb-3">Available Today</span>
                        <a href="/book?barber=<?= $barber['id'] ?>" class="btn btn-success"><i class="fas fa-calendar-plus me-2"></i>Book Appointment</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Booking CTA -->
    <section class="py-5 text-center" style="background: rgba(15,169,88,0.05);">
        <div class="container">
            <h2 class="mb-3">Choose Your Preferred Barber</h2>
            <p class="mb-4 text-readable">Select any barber above or let us assign the best available</p>
            <a href="/book" class="btn btn-success btn-lg px-5"><i class="fas fa-calendar-alt me-2"></i> BOOK APPOINTMENT</a>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>