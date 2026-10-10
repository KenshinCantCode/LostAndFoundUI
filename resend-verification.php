<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/email.php';

$auth = new Auth();
$error = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';

    if (!is_string($csrfToken) || !verifyCSRFToken($csrfToken)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!validateEmail($email)) {
        $error = 'Enter a valid email address.';
    } else {
        $verification = $auth->resendEmailVerification($email);
        if ($verification !== null) {
            try {
                if (!sendEmailVerificationEmail(
                    $verification['email'],
                    $verification['full_name'],
                    $verification['verification_token']
                )) {
                    error_log('Verification email could not be sent from resend flow.');
                }
            } catch (Throwable $e) {
                error_log('Failed to send resent verification email: ' . $e->getMessage());
            }
        }

        $submitted = true;
    }
}

$pageTitle = 'Resend Verification Email';
require_once 'includes/header.php';
?>

<main class="mx-auto max-w-xl px-5 py-16">
    <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl sm:p-12">
        <div class="mb-7 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-2xl text-emerald-700">
                <i class="fas fa-envelope" aria-hidden="true"></i>
            </span>
            <h1 class="font-display mt-5 text-3xl font-bold text-ink">Resend verification email</h1>
            <p class="mt-3 leading-7 text-slate-600">Enter the address used to register. If it belongs to an account that still needs verification, we’ll send a fresh link.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert"><?= sanitize($error) ?></div>
        <?php elseif ($submitted): ?>
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                If that address has an unverified account, a verification email will be sent. You can request another link in 60 seconds.
            </div>
        <?php endif; ?>

        <?php if (!$submitted || $error): ?>
            <form method="POST" action="<?= SITE_URL ?>/resend-verification.php" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= sanitize(generateCSRFToken()) ?>">
                <div>
                    <label for="email" class="mb-2 block text-sm font-bold text-slate-700">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="email" required
                        value="<?= sanitize(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-ink outline-none transition focus:border-coral focus:bg-white focus:ring-4 focus:ring-coral/10">
                </div>
                <button type="submit" class="w-full rounded-xl bg-ink px-5 py-3.5 font-bold text-white transition hover:bg-slate-700">Send verification link</button>
            </form>
        <?php endif; ?>
        <p class="mt-7 text-center text-sm text-slate-500"><a href="<?= SITE_URL ?>/login.php" class="font-bold text-coral hover:underline">Back to sign in</a></p>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
