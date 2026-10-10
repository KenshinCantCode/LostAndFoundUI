<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$userId = (int) $_SESSION['user_id'];
$friendId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!$friendId || $friendId === $userId) {
    setFlash('danger', 'That conversation could not be found.');
    redirect(SITE_URL . '/friends.php');
}

$userOneId = min($userId, $friendId);
$userTwoId = max($userId, $friendId);
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name, u.avatar
    FROM friendships f
    JOIN users u ON u.id = ?
    WHERE f.user_one_id = ? AND f.user_two_id = ? AND f.status = 'accepted'
      AND (f.user_one_id = ? OR f.user_two_id = ?)
      AND u.is_active = 1
");
$stmt->execute([$friendId, $userOneId, $userTwoId, $userId, $userId]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    setFlash('warning', 'You can only message an active friend who has accepted your request.');
    redirect(SITE_URL . '/friends.php');
}

$stmt = $conn->prepare("
    INSERT INTO conversations (user_one_id, user_two_id)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
");
$stmt->execute([$userOneId, $userTwoId]);
$conversationId = (int) $conn->lastInsertId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !verifyCSRFToken($csrfToken)) {
        setFlash('danger', 'Your session expired. Refresh the page and try again.');
        redirect(SITE_URL . '/conversation.php?user_id=' . $friendId);
    }

    $body = $_POST['message'] ?? '';
    $body = is_string($body) ? trim($body) : '';
    $characterCount = preg_match_all('/./us', $body, $matches);
    if ($body === '' || $characterCount === false || $characterCount > 2000) {
        setFlash('danger', 'Enter a message of 1 to 2,000 characters.');
        redirect(SITE_URL . '/conversation.php?user_id=' . $friendId);
    }

    $stmt = $conn->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, $userId, $body]);
    $stmt = $conn->prepare('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$conversationId]);
    redirect(SITE_URL . '/conversation.php?user_id=' . $friendId);
}

$stmt = $conn->prepare("
    SELECT sender_id, body, created_at
    FROM messages
    WHERE conversation_id = ?
    ORDER BY id DESC
    LIMIT 100
");
$stmt->execute([$conversationId]);
$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

$pageTitle = 'Conversation with ' . $friend['full_name'];
require_once __DIR__ . '/includes/header.php';
?>

<section class="mx-auto flex max-w-4xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
    <header class="flex items-center justify-between gap-4 border-b border-slate-100 p-5 sm:p-7">
        <div>
            <a href="<?= SITE_URL ?>/friends.php" class="mb-2 inline-flex items-center text-sm font-semibold text-coral hover:text-mint"><i class="fas fa-arrow-left mr-2"></i>People &amp; Messages</a>
            <h1 class="font-display text-2xl font-bold text-ink"><?= htmlspecialchars($friend['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <p class="text-sm text-slate-500">@<?= htmlspecialchars($friend['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
    </header>

    <div class="min-h-80 space-y-4 bg-paper p-5 sm:p-7">
        <?php if (!$messages): ?>
            <div class="py-12 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-xl text-coral shadow-sm"><i class="fas fa-comments"></i></span>
                <p class="mt-4 font-bold text-ink">Start the conversation</p>
                <p class="mt-1 text-sm text-slate-500">Messages are private to you and your friend.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $isOwnMessage = (int) $message['sender_id'] === $userId; ?>
                <div class="flex <?= $isOwnMessage ? 'justify-end' : 'justify-start' ?>">
                    <div class="<?= $isOwnMessage ? 'bg-ink text-white' : 'border border-slate-200 bg-white text-ink' ?> max-w-[85%] rounded-2xl px-4 py-3 shadow-sm sm:max-w-[75%]">
                        <p class="whitespace-pre-wrap break-words"><?= htmlspecialchars($message['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <time class="mt-2 block text-right text-xs <?= $isOwnMessage ? 'text-slate-300' : 'text-slate-400' ?>"><?= formatDateTime($message['created_at']) ?></time>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form method="POST" class="border-t border-slate-100 p-4 sm:p-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
        <label for="message" class="sr-only">Write a message</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <textarea id="message" name="message" rows="2" maxlength="2000" required placeholder="Write a message…"
                class="min-h-14 min-w-0 flex-1 resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-ink outline-none focus:border-coral focus:ring-4 focus:ring-coral/10"></textarea>
            <button type="submit" class="rounded-xl bg-ink px-6 py-3 font-bold text-white transition hover:bg-mint sm:self-end"><i class="fas fa-paper-plane mr-2"></i>Send</button>
        </div>
        <p class="mt-2 text-xs text-slate-400">Up to 2,000 characters. Refresh the page to see new messages.</p>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
