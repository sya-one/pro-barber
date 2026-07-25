<!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-3 text-white">The Professional Barbershop</h5>
                    <p class="mb-2 text-readable">
                        16 Blaine St<br>
                        KwaDukuza Central<br>
                        KwaDukuza, 4449<br>
                        South Africa
                    </p>
                </div>

                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-3 text-white">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/services" class="text-light text-decoration-none">Services</a></li>
                        <li><a href="/barbers" class="text-light text-decoration-none">Our Barbers</a></li>
                        <li><a href="/shop" class="text-light text-decoration-none">Shop Products</a></li>
                        <li><a href="/book" class="text-light text-decoration-none">Book Appointment</a></li>
                        <li><a href="/contact" class="text-light text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <h5 class="mb-3 text-white">Connect With Us</h5>
                    <div class="d-flex gap-2">
                        <a href="https://wa.me/27612345678" target="_blank" class="btn btn-success btn-sm"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        <a href="tel:0612345678" class="btn btn-outline-light btn-sm"><i class="fas fa-phone"></i> Call Us</a>
                        <a href="https://www.instagram.com/the_professional_barbershop/" target="_blank" class="btn btn-outline-light btn-sm"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <small class="text-readable-muted">
                    &copy; <?= date('Y') ?> The Professional 🟢 Barbershop. 16 Blaine St, KwaDukuza. <br>
                    Design by <a href="https://horsementech.com" target="_blank">Horsemen Technologies</a>
                </small>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>