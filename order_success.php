<?php
$invoice = htmlspecialchars($_GET['invoice'] ?? '');
$saleId = intval($_GET['id'] ?? 0);

$pageTitle = 'Order Confirmed';
$pageDescription = 'Your order has been placed successfully. Thank you for shopping at The Professional Barbershop.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Success Content -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">
                    <i class="fas fa-check-circle success-icon mb-4" style="font-size: 5rem; color: var(--green);"></i>
                    <h1 class="mb-3">Order Confirmed!</h1>
                    <p class="lead mb-4 text-readable">Thank you for your purchase. Your order has been placed successfully.</p>

                    <?php if ($invoice): ?>
                    <div class="card bg-dark p-4 mb-4">
                        <h5>Invoice Number</h5>
                        <p class="display-6 text-success"><?= $invoice ?></p>
                        <small class="text-readable-muted">Please keep this for reference</small>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <a href="/" class="btn btn-outline-light"><i class="fas fa-home me-2"></i>Back to Home</a>
                        <a href="/shop" class="btn btn-success"><i class="fas fa-store me-2"></i>Shop More</a>
                        <a href="/book" class="btn btn-success"><i class="fas fa-calendar-alt me-2"></i>Book Appointment</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>