<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /shop');
    exit;
}

// Get product by slug or name
$stmt = $db->prepare("SELECT * FROM products WHERE is_active=1 AND (LOWER(REPLACE(name, ' ', '-')) = ? OR LOWER(name) LIKE ? OR id = ?");
$stmt->execute([strtolower($slug), '%' . strtolower(str_replace('-', ' ', $slug)) . '%', intval($slug)]);
$product = $stmt->fetch();

if (!$product) {
    header('HTTP/1.0 404');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Product not found</h1></body></html>';
    exit;
}

// Related products
$related = $db->query("SELECT * FROM products WHERE is_active=1 AND stock_quantity>0 AND id != {$product['id']} ORDER BY RAND() LIMIT 4")->fetchAll();

$pageTitle = $product['name'];
$pageDescription = htmlspecialchars(substr($product['description'] ?? $product['name'], 0, 160));
?>
<?php include 'includes/header.php'; ?>

    <!-- Product Detail -->
    <section class="py-5" style="padding-top: 8rem;">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="product-gallery">
                        <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($product['image'] ?? 'default-product.png') ?>" id="mainImage" class="w-100 mb-3" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='/barbershop-system/assets/images/default-product.png'" style="border-radius: 18px;">
                        <div class="d-flex gap-2">
                            <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($product['image'] ?? 'default-product.png') ?>" class="thumb-img active" onclick="swapImage(this)" onerror="this.src='/barbershop-system/assets/images/default-product.png'" style="border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h1 class="mb-3"><?= htmlspecialchars($product['name']) ?></h1>

                    <?php if (!empty($product['product_size'])): ?>
                    <p class="text-readable-muted mb-4"><i class="fas fa-tag me-2"></i><?= htmlspecialchars($product['product_size']) ?></p>
                    <?php endif; ?>

                    <div class="mb-4">
                        <span class="price"><?= formatCurrency($product['price']) ?></span>
                    </div>

                    <div class="mb-4">
                        <?php if ($product['stock_quantity'] > 10): ?>
                        <span class="stock-badge stock-in"><i class="fas fa-check-circle me-2"></i>In Stock (<?= $product['stock_quantity'] ?> available)</span>
                        <?php elseif ($product['stock_quantity'] > 0): ?>
                        <span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock (Only <?= $product['stock_quantity'] ?> left)</span>
                        <?php else: ?>
                        <span class="stock-badge stock-out"><i class="fas fa-times-circle me-2"></i>Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <p class="mb-4"><?= nl2br(htmlspecialchars($product['description'] ?? 'Premium quality grooming product.')) ?></p>

                    <div class="d-flex gap-3 align-items-center mb-4">
                        <label class="form-label mb-0">Quantity:</label>
                        <input type="number" id="quantity" class="form-control" value="1" min="1" max="<?= min(10, $product['stock_quantity']) ?>" style="width: 80px;">
                    </div>

                    <button class="btn btn-success btn-lg add-to-cart" data-id="<?= $product['id'] ?>" <?= $product['stock_quantity'] > 0 ? '' : 'disabled' ?>>
                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                    </button>

                    <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">

                    <div class="d-flex gap-3">
                        <a href="/cart" class="btn btn-outline-light"><i class="fas fa-shopping-cart me-2"></i>View Cart</a>
                        <a href="/shop" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i>Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <section class="py-5">
        <div class="container">
            <h3 class="mb-4">Related Products</h3>
            <div class="row g-4">
                <?php foreach ($related as $p): ?>
                <div class="col-6 col-md-3">
                    <div class="card h-100">
                        <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($p['image'] ?? 'default-product.png') ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.src='/barbershop-system/assets/images/default-product.png'">
                        <div class="card-body d-flex flex-column">
                            <h6><?= htmlspecialchars($p['name']) ?></h6>
                            <strong class="mt-auto mb-2 product-price"><?= formatCurrency($p['price']) ?></strong>
                            <a href="/shop/product/<?= strtolower(str_replace(' ', '-', $p['name'])) ?>" class="btn btn-outline-success btn-sm">View</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php $extraJs = '
    function swapImage(el) {
        $(".thumb-img").removeClass("active");
        $(el).addClass("active");
        $("#mainImage").attr("src", $(el).attr("src"));
    }

    $(document).on("click", ".add-to-cart", function(){
        let id = $(this).data("id");
        let qty = $("#quantity").val();
        $.post("cart_ajax.php", {action:"add", product_id:id, quantity:qty}, function(data){
            if(data.success) {
                let badge = $("#cartBadge");
                if(badge.length) badge.text(data.cartCount);
                else $(".fa-shopping-cart").after("<span class=\"position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success\" id=\"cartBadge\">"+data.cartCount+"</span>");
                alert("Product added to cart!");
            }
        }, "json");
    });
'; ?>
<?php include 'includes/footer.php'; ?>