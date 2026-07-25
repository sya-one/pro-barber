<?php
require_once __DIR__ . '/../shop_functions.php';
$settings = getSettings();
$cartCount = count(getCart());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' – ' : '' ?>The Professional Barbershop</title>
    <link rel="icon" type="image/png" href="/barbershop-system/assets/images/default-avatar.png">
    
    <?php if (!isset($pageDescription)): ?>
    <meta name="description" content="Premium barbershop in KwaDukuza. Professional haircuts, beard grooming, and quality grooming products. Book your appointment online.">
    <?php else: ?>
    <meta name="description" content="<?= $pageDescription ?>">
    <?php endif; ?>
    
    <link rel="canonical" href="<?= isset($pageUrl) ? $pageUrl : 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= isset($pageTitle) ? $pageTitle : 'The Professional Barbershop' ?>">
    <meta property="og:description" content="Premium barbershop in KwaDukuza. Professional haircuts, beard grooming, and quality grooming products.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:image" content="<?= 'https://' . $_SERVER['HTTP_HOST'] ?>/barbershop-system/assets/images/logo.png">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="The Professional Barbershop">
    <meta name="twitter:description" content="Premium barbershop in KwaDukuza">
    
    <!-- Schema.org LocalBusiness -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "The Professional Barbershop",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "16 Blaine St",
            "addressLocality": "KwaDukuza Central",
            "addressRegion": "KwaDukuza",
            "postalCode": "4449",
            "addressCountry": "ZA"
        },
        "telephone": "<?= $settings['phone'] ?? '0612345678' ?>",
        "url": "<?= 'https://' . $_SERVER['HTTP_HOST'] ?>",
        "openingHours": [
            "Mo-Fr 08:00-18:00",
            "Sa 08:00-18:00",
            "Su 09:00-16:00"
        ],
        "image": "<?= 'https://' . $_SERVER['HTTP_HOST'] ?>/barbershop-system/assets/images/logo.png"
    }
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
     <?php if (isset($extraCss)): ?><style><?= $extraCss ?></style><?php endif; ?>
</head>
<body class="bg-black text-white">
    <!-- Header/Navigation -->
    <header class="header" id="header">
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top top-navbar" style="transition: all 0.3s ease;">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="/">
                    <img src="/barbershop-system/assets/images/logo.png" alt="The Professional Barbershop" height="40" class="me-2">
                    <span class="fw-bold">The Pro Barber</span>
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarMenu">
                    <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="/"><span>Home</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/services"><span>Services</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/barbers"><span>Barbers</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/book"><span>Book Appointment</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/shop"><span>Shop</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/about"><span>About</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/contact"><span>Contact</span></a></li>
                    </ul>
                    
                    <div class="d-flex align-items-center">
                        <a href="/barbershop-system/customer/login.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-user me-1"></i>Customer Login</a>
                        <a href="/barbershop-system/customer/register.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-user-plus me-1"></i>Sign Up</a>
                        <a href="/cart" class="btn btn-outline-light btn-sm position-relative me-2">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" id="cartBadge"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/book" class="btn btn-success btn-sm book-now-btn"><i class="fas fa-cut"></i> Book Now</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>