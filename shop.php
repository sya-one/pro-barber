<?php
require_once 'shop_functions.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

// Get category filter
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["is_active=1 AND stock_quantity>0"];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE $whereClause")->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

$stmt = $db->prepare("SELECT * FROM products WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'Shop';
$pageDescription = 'Premium grooming products for sale at The Professional Barbershop. Hair pomades, beard oils, durags, and styling products.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Grooming Shop</h1>
            <p class="lead">Premium products for your daily routine</p>
        </div>
    </section>

    <!-- Search & Filter -->
    <section class="py-4">
        <div class="container">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="hair" <?= $category == 'hair' ? 'selected' : '' ?>>Hair Care</option>
                        <option value="beard" <?= $category == 'beard' ? 'selected' : '' ?>>Beard Care</option>
                        <option value="tools" <?= $category == 'tools' ? 'selected' : '' ?>>Tools & Accessories</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Products Grid -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-readable-muted mb-3"></i>
                    <h4>No products found</h4>
                    <p class="text-readable-muted">Try adjusting your search</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 position-relative">
                            <span class="stock-badge badge stock-in">In Stock</span>
                            <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($p['image'] ?? 'default-product.png') ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.src='/barbershop-system/assets/images/default-product.png'">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                                <?php if (!empty($p['product_size'])): ?>
                                <small class="text-readable-muted"><?= htmlspecialchars($p['product_size']) ?></small>
                                <?php endif; ?>
                                <strong class="mt-auto mb-2 product-price"><?= formatCurrency($p['price']) ?></strong>
                                <button class="btn btn-success btn-sm add-to-cart" data-id="<?= $p['id'] ?>"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

<?php $extraJs = '
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