<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            setFlash('success', $result['message']);
            redirect(SITE_URL . '/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = "Login";
require_once 'includes/header.php';
?>

<div class="mx-auto max-w-5xl overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/70 lg:grid lg:grid-cols-[0.9fr_1.1fr]">
    <div class="relative overflow-hidden bg-ink p-8 text-white sm:p-12">
        <div class="absolute -bottom-20 -right-20 h-56 w-56 rounded-full border-[28px] border-coral/20"></div>
        <div class="relative flex h-full flex-col justify-between">
            <div>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-coral text-xl shadow-lg shadow-coral/20"><i class="fas fa-search-location"></i></span>
                <p class="mt-10 text-sm font-bold uppercase tracking-[0.2em] text-coral">Welcome back</p>
                <h1 class="font-display mt-3 text-4xl font-bold leading-tight">PHINMA UI<br>Lost And Found</h1>
                <p class="mt-5 max-w-xs leading-7 text-slate-300">Find And Claim Your Lost Item</p>
            </div>
        </div>
    </div>
    <div class="p-8 sm:p-12">
        <div class="mb-8">
            <p class="text-sm font-bold uppercase tracking-widest text-coral">Account access</p>
            <h2 class="font-display mt-2 text-3xl font-bold text-ink">Sign in to your account</h2>
            <p class="mt-2 text-sm text-slate-500">Enter your details to continue.</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700" role="alert"><i class="fas fa-circle-exclamation mt-0.5"></i><span><?= $error ?></span></div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">
            <div>
                <label for="username" class="mb-2 block text-sm font-bold text-slate-700">Username or email</label>
                <div class="flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 transition focus-within:border-coral focus-within:bg-white focus-within:ring-4 focus-within:ring-coral/10">
                    <i class="fas fa-user text-sm text-slate-400"></i>
                    <input type="text" class="w-full border-0 bg-transparent px-3 py-3.5 text-sm text-ink outline-none ring-0 placeholder:text-slate-400" id="username" name="username" placeholder="you@example.com" value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-bold text-slate-700">Password</label>
                <div class="flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 transition focus-within:border-coral focus-within:bg-white focus-within:ring-4 focus-within:ring-coral/10">
                    <i class="fas fa-lock text-sm text-slate-400"></i>
                    <input type="password" class="w-full border-0 bg-transparent px-3 py-3.5 text-sm text-ink outline-none ring-0" id="password" name="password" required>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-500"><input type="checkbox" id="remember" name="remember" class="h-4 w-4 rounded border-slate-300 text-coral focus:ring-coral">Remember me</label>

            <button type="submit" class="w-full rounded-xl bg-ink px-5 py-3.5 font-bold text-white shadow-lg shadow-slate-300 transition hover:-translate-y-0.5 hover:bg-slate-700"><i class="fas fa-sign-in-alt mr-2 text-coral"></i>Sign in</button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">Don't have an account? <a href="register.php" class="font-bold text-coral hover:underline">Create one here</a></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
