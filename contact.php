<?php
require_once 'shop_functions.php';
require_once 'barbershop-system/includes/csrf.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $msg = 'Invalid request.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!empty($name) && !empty($message)) {
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $phone, $message]);

            notifyAdminEmail($db, "Contact form: $name", "<p>$message<br>Phone: $phone<br>Email: $email</p>");
            $msg = 'Thank you! Your message has been sent.';
        }
    }
}

$openingHours = [
    'Monday' => '08:00 - 18:00',
    'Tuesday' => '08:00 - 18:00',
    'Wednesday' => '08:00 - 18:00',
    'Thursday' => '08:00 - 18:00',
    'Friday' => '08:00 - 18:00',
    'Saturday' => '08:00 - 17:00',
    'Sunday' => '09:00 - 15:00'
];

$pageTitle = 'Contact Us';
$pageDescription = 'Contact The Professional Barbershop in KwaDukuza. Call, WhatsApp, or send us a message.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Contact Us</h1>
            <p class="lead">Get in touch with our team</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <h3 class="mb-4">Send a Message</h3>

                    <?php if ($msg): ?>
                    <div class="alert alert-success"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>

                        <button type="submit" name="send_message" class="btn btn-success btn-lg"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                    </form>
                </div>

                <div class="col-lg-6">
                    <h3 class="mb-4">Contact Information</h3>

                    <div class="mb-4">
                        <h5><i class="fas fa-map-marker-alt text-success me-2"></i> Address</h5>
                        <p class="mb-0 text-readable">16 Blaine St, KwaDukuza Central<br>KwaDukuza, 4449<br>South Africa</p>
                    </div>

                    <div class="mb-4">
                        <h5><i class="fas fa-phone text-success me-2"></i> Phone</h5>
                        <p class="mb-0"><a href="tel:0612345678" class="text-success">061 234 5678</a></p>
                    </div>

                    <div class="mb-4">
                        <h5><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp</h5>
                        <p class="mb-0"><a href="https://wa.me/27612345678" target="_blank" class="text-success">061 234 5678</a></p>
                    </div>

                    <h5><i class="fas fa-clock text-success me-2"></i> Opening Hours</h5>
                    <?php foreach ($openingHours as $day => $hours): ?>
                    <div class="d-flex justify-content-between">
                        <span class="text-readable"><?= $day ?>:</span>
                        <span style="color: var(--green);"><?= $hours ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Map -->
    <section class="py-5">
        <div class="container-fluid px-0">
            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d7354.808317699802!2d31.28455767574448!3d-29.337011175287373!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1ef7473f35cd315f%3A0x3150369064b328aa!2s16%20Blaine%20St%2C%20KwaDukuza%20Central%2C%20Durban%2C%204450!5e1!3m2!1sen!2sza!4v1784793004969!5m2!1sen!2sza"
                    width="100%"
                    height="300"
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin">
                </iframe>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>