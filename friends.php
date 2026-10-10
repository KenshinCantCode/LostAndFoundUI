<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !verifyCSRFToken($csrfToken)) {
        setFlash('danger', 'Your session expired. Refresh the page and try again.');
        redirect(SITE_URL . '/friends.php');
    }

    $friendId = filter_var($_POST['friend_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if (!$friendId || $friendId === $userId) {
        setFlash('danger', 'That user could not be found.');
        redirect(SITE_URL . '/friends.php');
    }

    $userOneId = min($userId, $friendId);
    $userTwoId = max($userId, $friendId);
    $stmt = $conn->prepare('SELECT id, requested_by_id, status FROM friendships WHERE user_one_id = ? AND user_two_id = ?');
    $stmt->execute([$userOneId, $userTwoId]);
    $friendship = $stmt->fetch(PDO::FETCH_ASSOC);

    switch ($action) {
        case 'send_request':
            $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 AND email_verified_at IS NOT NULL');
            $stmt->execute([$friendId]);
            if (!$stmt->fetchColumn()) {
                setFlash('danger', 'That user is unavailable.');
            } elseif ($friendship && $friendship['status'] === 'accepted') {
                setFlash('info', 'You are already friends.');
            } elseif ($friendship && (int) $friendship['requested_by_id'] === $userId) {
                setFlash('info', 'Your friend request is already pending.');
            } elseif ($friendship) {
                setFlash('info', 'This user has already sent you a request. Accept it below.');
            } else {
                $stmt = $conn->prepare("INSERT INTO friendships (user_one_id, user_two_id, requested_by_id, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$userOneId, $userTwoId, $userId]);
                setFlash('success', 'Friend request sent.');
            }
            break;

        case 'accept_request':
            if ($friendship && $friendship['status'] === 'pending' && (int) $friendship['requested_by_id'] !== $userId) {
                $stmt = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND status = 'pending' AND requested_by_id <> ?");
                $stmt->execute([$friendship['id'], $userId]);
                setFlash('success', 'Friend request accepted. You can now message each other.');
            } else {
                setFlash('danger', 'That friend request is no longer available.');
            }
            break;

        case 'decline_request':
        case 'cancel_request':
            $isIncoming = $friendship
                && $friendship['status'] === 'pending'
                && (int) $friendship['requested_by_id'] !== $userId;
            $isOutgoing = $friendship
                && $friendship['status'] === 'pending'
                && (int) $friendship['requested_by_id'] === $userId;
            if (($action === 'decline_request' && $isIncoming) || ($action === 'cancel_request' && $isOutgoing)) {
                $stmt = $conn->prepare("DELETE FROM friendships WHERE id = ? AND status = 'pending'");
                $stmt->execute([$friendship['id']]);
                setFlash('success', $action === 'decline_request' ? 'Friend request declined.' : 'Friend request cancelled.');
            } else {
                setFlash('danger', 'That friend request is no longer available.');
            }
            break;

        case 'remove_friend':
            if ($friendship && $friendship['status'] === 'accepted') {
                $stmt = $conn->prepare("DELETE FROM friendships WHERE id = ? AND status = 'accepted'");
                $stmt->execute([$friendship['id']]);
                setFlash('success', 'Friend removed.');
            } else {
                setFlash('danger', 'That friendship is no longer available.');
            }
            break;

        default:
            setFlash('danger', 'Invalid action.');
            break;
    }

    redirect(SITE_URL . '/friends.php');
}

$searchInput = $_GET['q'] ?? '';
$query = is_string($searchInput) ? trim($searchInput) : '';
$searchResults = [];
if ($query !== '') {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.full_name, u.role, u.avatar,
               f.id AS friendship_id, f.status AS friendship_status, f.requested_by_id
        FROM users u
        LEFT JOIN friendships f
          ON f.user_one_id = LEAST(u.id, ?) AND f.user_two_id = GREATEST(u.id, ?)
        WHERE u.id <> ? AND u.is_active = 1 AND u.email_verified_at IS NOT NULL
          AND (u.username LIKE ? OR u.full_name LIKE ?)
        ORDER BY u.full_name
        LIMIT 30
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$userId, $userId, $userId, $searchTerm, $searchTerm]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name, u.role, u.avatar, f.created_at
    FROM friendships f
    JOIN users u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
    WHERE f.status = 'accepted' AND (f.user_one_id = ? OR f.user_two_id = ?)
    ORDER BY u.full_name
");
$stmt->execute([$userId, $userId, $userId]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name, u.role, u.avatar, f.id AS friendship_id, f.created_at
    FROM friendships f
    JOIN users u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
    WHERE f.status = 'pending' AND f.requested_by_id <> ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    ORDER BY f.created_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name, u.role, u.avatar, f.id AS friendship_id, f.created_at
    FROM friendships f
    JOIN users u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
    WHERE f.status = 'pending' AND f.requested_by_id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    ORDER BY f.created_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$outgoingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.username, u.avatar, c.id AS conversation_id,
           (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
           (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at
    FROM conversations c
    JOIN friendships f ON f.user_one_id = c.user_one_id AND f.user_two_id = c.user_two_id AND f.status = 'accepted'
    JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
    WHERE c.user_one_id = ? OR c.user_two_id = ?
    ORDER BY COALESCE(last_message_at, c.created_at) DESC
");
$stmt->execute([$userId, $userId, $userId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'People & Messages';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-5xl space-y-8">
    <section class="rounded-3xl bg-ink p-8 text-white shadow-xl sm:p-10">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-coral">Campus community</p>
        <h1 class="font-display mt-2 text-3xl font-bold sm:text-4xl">People &amp; Messages</h1>
        <p class="mt-3 max-w-2xl leading-7 text-slate-300">Find students and staff, connect with a friend request, and chat privately after they accept.</p>
        <form method="GET" action="<?= SITE_URL ?>/friends.php" class="mt-6 flex flex-col gap-3 sm:flex-row">
            <div class="relative min-w-0 flex-1">
                <label for="people-search" class="sr-only">Search people</label>
                <input id="people-search" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="Search by name or username" autocomplete="off" role="combobox"
                    aria-autocomplete="list" aria-controls="people-suggestions" aria-expanded="false"
                    class="w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-ink outline-none focus:ring-4 focus:ring-coral/30">
                <div id="people-suggestions" role="listbox" aria-label="People suggestions"
                    class="absolute left-0 right-0 top-full z-40 mt-2 hidden max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white py-2 text-ink shadow-xl"></div>
                <p id="people-search-status" class="sr-only" aria-live="polite"></p>
            </div>
            <button class="rounded-xl bg-coral px-6 py-3 font-bold text-white transition hover:bg-mint" type="submit"><i class="fas fa-search mr-2"></i>Search people</button>
        </form>
    </section>

    <?php if ($query !== ''): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="font-display mb-5 text-xl font-bold">Search results</h2>
        <?php if (!$searchResults): ?>
            <p class="text-slate-500">No active, verified users matched that search.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($searchResults as $person): ?>
                    <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-bold text-ink"><?= htmlspecialchars($person['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <p class="text-sm text-slate-500">@<?= htmlspecialchars($person['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= ucfirst($person['role'] === 'staff' ? 'Teacher/Staff' : $person['role']) ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if (!$person['friendship_id']): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="friend_id" value="<?= (int) $person['id'] ?>">
                                    <button class="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-mint" name="action" value="send_request"><i class="fas fa-user-plus mr-2"></i>Add friend</button>
                                </form>
                            <?php elseif ($person['friendship_status'] === 'accepted'): ?>
                                <a class="rounded-lg bg-coral px-4 py-2 text-sm font-bold text-white hover:bg-mint" href="<?= SITE_URL ?>/conversation.php?user_id=<?= (int) $person['id'] ?>"><i class="fas fa-comment mr-2"></i>Message</a>
                            <?php elseif ((int) $person['requested_by_id'] === $userId): ?>
                                <span class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">Request pending</span>
                            <?php else: ?>
                                <span class="rounded-lg bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">They sent you a request</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($incomingRequests): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="font-display mb-4 text-xl font-bold">Friend requests <span class="ml-1 rounded-full bg-coral/10 px-2 py-1 text-sm text-coral"><?= count($incomingRequests) ?></span></h2>
        <div class="divide-y divide-slate-100">
            <?php foreach ($incomingRequests as $person): ?>
                <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="font-bold"><?= htmlspecialchars($person['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><p class="text-sm text-slate-500">@<?= htmlspecialchars($person['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></div>
                    <div class="flex gap-2">
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="friend_id" value="<?= (int) $person['id'] ?>"><button class="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-mint" name="action" value="accept_request">Accept</button></form>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="friend_id" value="<?= (int) $person['id'] ?>"><button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50" name="action" value="decline_request">Decline</button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($conversations): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="font-display mb-4 text-xl font-bold">Conversations</h2>
        <div class="divide-y divide-slate-100">
            <?php foreach ($conversations as $conversation): ?>
                <a href="<?= SITE_URL ?>/conversation.php?user_id=<?= (int) $conversation['id'] ?>" class="flex items-center justify-between gap-4 py-4 hover:text-coral">
                    <div class="min-w-0"><p class="font-bold text-ink"><?= htmlspecialchars($conversation['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><p class="truncate text-sm text-slate-500"><?= $conversation['last_message'] !== null ? htmlspecialchars($conversation['last_message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Start the conversation' ?></p></div>
                    <span class="shrink-0 text-xs text-slate-400"><?= $conversation['last_message_at'] ? formatDateTime($conversation['last_message_at']) : '' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="font-display mb-4 text-xl font-bold">Friends <span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-sm text-slate-600"><?= count($friends) ?></span></h2>
        <?php if (!$friends): ?>
            <p class="text-slate-500">You don't have any friends yet. Search for a person above to send a friend request.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($friends as $person): ?>
                    <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-bold"><?= htmlspecialchars($person['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><p class="text-sm text-slate-500">@<?= htmlspecialchars($person['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= ucfirst($person['role'] === 'staff' ? 'Teacher/Staff' : $person['role']) ?></p></div>
                        <div class="flex gap-2">
                            <a class="rounded-lg bg-coral px-4 py-2 text-sm font-bold text-white hover:bg-mint" href="<?= SITE_URL ?>/conversation.php?user_id=<?= (int) $person['id'] ?>"><i class="fas fa-comment mr-2"></i>Message</a>
                            <form method="POST" onsubmit="return confirm('Remove this friend?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="friend_id" value="<?= (int) $person['id'] ?>"><button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50" name="action" value="remove_friend">Remove</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($outgoingRequests): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="font-display mb-4 text-xl font-bold">Sent requests</h2>
        <div class="divide-y divide-slate-100">
            <?php foreach ($outgoingRequests as $person): ?>
                <div class="flex items-center justify-between gap-4 py-4"><div><p class="font-bold"><?= htmlspecialchars($person['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><p class="text-sm text-slate-500">@<?= htmlspecialchars($person['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></div><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="friend_id" value="<?= (int) $person['id'] ?>"><button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50" name="action" value="cancel_request">Cancel request</button></form></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script src="<?= SITE_URL ?>/assets/js/people-search.js?v=20261010-1"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
