    </main>

    <footer class="mt-12 border-t border-slate-200 bg-ink text-white">
        <div class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
            <div class="grid gap-10 md:grid-cols-3">
                <div><h5 class="font-display text-lg font-bold"><i class="fas fa-search-location mr-2 text-coral"></i><?= SITE_NAME ?></h5><p class="mt-3 max-w-sm text-sm leading-6 text-slate-400">Helping reunite the campus community with their belongings.</p></div>
                <div><h6 class="font-bold">Quick Links</h6><div class="mt-3 grid gap-2 text-sm text-slate-400"><a class="hover:text-white" href="<?= SITE_URL ?>">Home</a><a class="hover:text-white" href="<?= SITE_URL ?>/search.php">Search Items</a><a class="hover:text-white" href="<?= SITE_URL ?>/report-lost.php">Report Lost</a><a class="hover:text-white" href="<?= SITE_URL ?>/report-found.php">Report Found</a></div></div>
                <div><h6 class="font-bold">Contact</h6><div class="mt-3 grid gap-2 text-sm text-slate-400"><span><i class="fas fa-envelope mr-2 text-coral"></i>LostAndFoundUI@gmail.com</span><span><i class="fas fa-phone mr-2 text-coral"></i>+63 9946635672</span></div></div>
            </div>
            <div class="mt-10 border-t border-white/10 pt-5 text-center text-xs text-slate-500">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js?v=20260907"></script>
</body>
</html>
