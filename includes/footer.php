    </main>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-search-location me-2"></i><?= SITE_NAME ?></h5>
                    <p class="text-muted">Helping reunite the campus community with their belongings.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?= SITE_URL ?>" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="<?= SITE_URL ?>/search.php" class="text-muted text-decoration-none">Search Items</a></li>
                        <li><a href="<?= SITE_URL ?>/report-lost.php" class="text-muted text-decoration-none">Report Lost</a></li>
                        <li><a href="<?= SITE_URL ?>/report-found.php" class="text-muted text-decoration-none">Report Found</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Contact</h6>
                    <ul class="list-unstyled text-muted">
                        <li><i class="fas fa-envelope me-2"></i>LostAndFoundUI@gmail.com</li>
                        <li><i class="fas fa-phone me-2"></i>+63 9946635672</li>
                    </ul>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center text-muted">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
