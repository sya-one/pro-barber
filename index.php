<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$services = $db->query("SELECT * FROM services WHERE is_active=1 ORDER BY price ASC LIMIT 6")->fetchAll();
$barbers = $db->query("SELECT * FROM barbers WHERE is_active=1")->fetchAll();
$products = $db->query("SELECT * FROM products WHERE is_active=1 AND stock_quantity>0 ORDER BY created_at DESC LIMIT 4")->fetchAll();
$galleryImages = getGalleryImages();

$openingHours = [
    'Monday' => '08:00 - 18:00',
    'Tuesday' => '08:00 - 18:00',
    'Wednesday' => '08:00 - 18:00',
    'Thursday' => '08:00 - 18:00',
    'Friday' => '08:00 - 18:00',
    'Saturday' => '08:00 - 18:00',
    'Sunday' => '09:00 - 16:00'
];

$pageTitle = 'Home';
$pageDescription = 'Premium barbershop in KwaDukuza. Professional haircuts, beard grooming, and quality grooming products. Book your appointment online.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="mb-3">
                <span class="highlight">LOOK SHARP.</span><br>
                FEEL CONFIDENT.
            </h1>
            <p class="lead mb-4">Premium grooming, professional barbers, and an experience built around you.</p>
            <div class="location-badge mb-4">
                <i class="fas fa-map-marker-alt text-success"></i>
                16 Blaine St, KwaDukuza Central
            </div>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="/book" class="btn btn-success btn-lg px-4"><i class="fas fa-calendar-alt me-2"></i>BOOK YOUR APPOINTMENT</a>
                <a href="/shop" class="btn btn-outline-light btn-lg px-4"><i class="fas fa-shopping-bag me-2"></i>SHOP GROOMING PRODUCTS</a>
            </div>
        </div>
    </section>

    <!-- Services -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Premium Services</h2>
                <p class="text-readable-muted">Professional grooming tailored to your style</p>
            </div>

            <div class="row g-4">
                <?php foreach ($services as $service): ?>
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <div class="service-icon">
                            <i class="fas fa-cut"></i>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($service['name']) ?></h5>
                        <p class="card-text small text-readable-muted"><?= htmlspecialchars($service['description'] ?? '') ?></p>
                        <div class="mt-auto">
                            <div class="service-price mb-3"><?= formatCurrency($service['price']) ?></div>
                            <?php if (!empty($service['duration'])): ?>
                            <small class="text-readable-muted"><i class="fas fa-clock me-1"></i><?= $service['duration'] ?> min</small>
                            <?php endif; ?>
                            <a href="/book?service=<?= $service['id'] ?>" class="btn btn-success btn-sm mt-3"><i class="fas fa-calendar-plus"></i> Book Now</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="/services" class="btn btn-outline-light"><i class="fas fa-list me-2"></i> View All Services</a>
            </div>
        </div>
    </section>

    <!-- Meet Our Barbers -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Meet Our Barbers</h2>
                <p class="text-readable-muted">Skilled professionals dedicated to your perfect look</p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($barbers as $barber): ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 text-center p-4">
                        <img src="/barbershop-system/uploads/barbers/<?= htmlspecialchars($barber['photo'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($barber['full_name']) ?>" class="barber-img" onerror="this.src='/barbershop-system/assets/images/default-avatar.png'">
                        <h5 class="mb-1 barber-name"><?= htmlspecialchars($barber['full_name']) ?></h5>
                        <p class="barber-specialty mb-2" style="color: var(--green);">Master Barber</p>
                        <small class="text-readable-muted mb-3 d-block">Expert in classic cuts and modern styles</small>
                        <span class="availability-badge mb-3" style="color: var(--green);">Available</span>
                        <a href="/book?barber=<?= $barber['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-calendar-plus"></i> Book Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="/barbers" class="btn btn-outline-light"><i class="fas fa-users me-2"></i> Meet All Barbers</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-5 why-choose-us">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Why Choose Us</h2>
                <p>The difference is in the details</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-user-tie fa-3x text-success mb-3"></i>
                        <h5>Professional Barbers</h5>
                        <p class="small text-readable">Experienced, skilled barbers who understand your style.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-spa fa-3x text-success mb-3"></i>
                        <h5>Premium Products</h5>
                        <p class="small text-readable">Top-quality grooming products for home care.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-bolt fa-3x text-success mb-3"></i>
                        <h5>Easy Online Booking</h5>
                        <p class="small text-readable">Book your appointment in seconds, 24/7.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Gallery</h2>
                <p class="text-readable-muted">See our work in action</p>
            </div>

            <div class="row g-3">
                <?php if (!empty($galleryImages)): ?>
                    <?php foreach ($galleryImages as $img): ?>
                    <div class="col-6 col-md-4">
                        <div class="gallery-item">
                            <img src="/barbershop-system/assets/gallery/<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['caption'] ?? 'Gallery image') ?>" loading="lazy">
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="col-6 col-md-4">
                        <div class="gallery-item">
                            <img src="/barbershop-system/assets/images/default-avatar.png" alt="Gallery placeholder" loading="lazy">
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Grooming Products -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Grooming Products</h2>
                <p class="text-readable-muted">Premium products for your daily routine</p>
            </div>

            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-3">
                    <div class="card h-100 d-flex flex-column">
                        <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($product['image'] ?? 'default-product.png') ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='/barbershop-system/assets/images/default-product.png'">
                        <div class="card-body d-flex flex-column">
                            <h6><?= htmlspecialchars($product['name']) ?></h6>
                            <?php if (!empty($product['product_size'])): ?>
                            <small class="text-readable-muted"><?= htmlspecialchars($product['product_size']) ?></small>
                            <?php endif; ?>
                            <strong class="mt-auto mb-2 product-price"><?= formatCurrency($product['price']) ?></strong>
                            <button class="btn btn-success btn-sm add-to-cart" data-id="<?= $product['id'] ?>"><i class="fas fa-cart-plus"></i> Add</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="/shop" class="btn btn-outline-light btn-lg"><i class="fas fa-store me-2"></i> View All Products</a>
            </div>
        </div>
    </section>

    <!-- Loyalty Program -->
    <section class="py-5 text-center" style="background: rgba(15,169,88,0.05);">
        <div class="container">
            <h2 class="mb-4">Loyalty Rewards</h2>
            <p class="mb-4 text-readable">Earn points with every visit and unlock exclusive benefits</p>

            <div class="row g-3 justify-content-center mb-4">
                <?php foreach (['Bronze' => '0-499 pts', 'Silver' => '500-1499 pts', 'Gold' => '1500-2999 pts', 'VIP' => '3000+ pts'] as $tier => $pts): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card p-3">
                        <h5 class="text-success"><?= $tier ?></h5>
                        <small class="text-readable-muted"><?= $pts ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <a href="/book" class="btn btn-success btn-lg"><i class="fas fa-award me-2"></i> JOIN THE LOYALTY PROGRAM</a>
        </div>
    </section>

    <!-- Location & Hours -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <h3 class="mb-4">Visit Our Shop</h3>
                    <div class="mb-4">
                        <p class="mb-2 text-readable"><i class="fas fa-map-marker-alt text-success me-2"></i> 16 Blaine St, KwaDukuza Central</p>
                        <p class="mb-2 text-readable"><i class="fas fa-city text-success me-2"></i> KwaDukuza, 4449</p>
                        <p class="mb-2 text-readable"><i class="fas fa-globe-africa text-success me-2"></i> South Africa</p>
                    </div>

                    <div class="map-container mb-4">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d7354.808317699802!2d31.28455767574448!3d-29.337011175287373!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1ef7473f35cd315f%3A0x3150369064b328aa!2s16%20Blaine%20St%2C%20KwaDukuza%20Central%2C%20Durban%2C%204450!5e1!3m2!1sen!2sza!4v1784793004969!5m2!1sen!2sza"
                            width="100%"
                            height="300"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin">
                        </iframe>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="https://wa.me/27612345678" target="_blank" class="btn btn-success"><i class="fab fa-whatsapp me-2"></i>WhatsApp Us</a>
                        <a href="tel:0612345678" class="btn btn-outline-light"><i class="fas fa-phone me-2"></i>Call Us</a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h3 class="mb-4">Opening Hours</h3>
                    <div class="row g-2">
                        <?php foreach ($openingHours as $day => $hours): ?>
                        <div class="col-6 col-sm-4">
                            <div class="d-flex justify-content-between">
                                <span class="text-readable"><?= $day ?>:</span>
                                <span style="color: var(--green);"><?= $hours ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php $extraJs = '
    $(document).ready(function(){
        $(window).scroll(function() {
            if ($(this).scrollTop() > 100) $("#navbar").addClass("shrink");
            else $("#navbar").removeClass("shrink");
        });
    });

    $(document).on("click", ".add-to-cart", function(){
        let id = $(this).data("id");
        $.post("cart_ajax.php", {action:"add", product_id:id}, function(data){
            if(data.success) {
                let badge = $("#cartBadge");
                if(badge.length) badge.text(data.cartCount);
                else $(".fa-shopping-cart").after("<span class=\"position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success\" id=\"cartBadge\">"+data.cartCount+"</span>");
            }
        }, "json");
    });
'; ?>
<?php include 'includes/footer.php'; ?>