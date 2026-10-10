<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$verifiedUser = false;
$token = $_GET['token'] ?? '';

if (is_string($token) && $token !== '') {
    $verifiedUser = $auth->verifyEmail($token);
}

if ($verifiedUser) {
    setFlash('success', 'Your email is verified. You can now sign in.');
    try {
        if (!sendWelcomeEmail($verifiedUser['email'], $verifiedUser['full_name'])) {
            error_log('Welcome email could not be sent after email verification.');
        }
    } catch (Throwable $e) {
        error_log('Failed to send post-verification welcome email: ' . $e->getMessage());
    }
}
$verified = (bool) $verifiedUser;

$pageTitle = 'Email Verification';
require_once 'includes/header.php';
?>

<main class="mx-auto max-w-xl px-5 py-16">
    <section class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl sm:p-12">
        <span
            class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl <?= $verified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?> text-2xl">
            <i class="fas <?= $verified ? 'fa-circle-check' : 'fa-envelope-circle-check' ?>" aria-hidden="true"></i>
        </span>
        <h1 class="font-display mt-6 text-3xl font-bold text-ink">
            
            <?= $verified ? 'Email verified' : 'Link invalid or expired' ?></h1>
        <p class="mt-3 leading-7 text-slate-600">
            <?= $verified
                ? 'Your account is ready. Sign in to continue.'
                : 'This verification link cannot be used. Request a fresh link and try again.' ?>
        </p>
        <a class="mt-7 inline-flex items-center justify-center rounded-xl bg-ink px-6 py-3 font-bold text-white transition hover:bg-slate-700"
            href="<?= SITE_URL . ($verified ? '/login.php' : '/resend-verification.php') ?>">
            <?= $verified ? 'Go to sign in' : 'Resend verification email' ?>
        </a>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>