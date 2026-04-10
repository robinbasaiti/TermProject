<?php
session_start();
require_once 'includes/session_check.php';
require_once 'db/conn.php';
require_once 'includes/csrf.php';
require_once 'includes/marketplace_relations.php';

$user_id    = (int)$_SESSION['user_id'];
$with_id    = (int)($_GET['with'] ?? 0);
$product_id = (int)($_GET['product'] ?? 0);
$access_error = '';

$conversations = [];
$conv_stmt = mysqli_prepare($conn,
    "SELECT DISTINCT
        IF(sender_id = ?, receiver_id, sender_id) AS other_id,
        u.name AS other_name,
        MAX(m.created_at) AS last_message_time
     FROM messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     GROUP BY other_id, u.name
     ORDER BY last_message_time DESC");
mysqli_stmt_bind_param($conv_stmt, 'iiii', $user_id, $user_id, $user_id, $user_id);
mysqli_stmt_execute($conv_stmt);
$conv_res = mysqli_stmt_get_result($conv_stmt);
while ($row = mysqli_fetch_assoc($conv_res)) {
    $conversations[] = $row;
}
mysqli_stmt_close($conv_stmt);

$thread = [];
$other_user = null;
if ($with_id > 0) {
    $user_stmt = mysqli_prepare($conn, "SELECT id, name FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($user_stmt, 'i', $with_id);
    mysqli_stmt_execute($user_stmt);
    $ur = mysqli_stmt_get_result($user_stmt);
    $other_user = mysqli_fetch_assoc($ur);
    mysqli_stmt_close($user_stmt);

    if ($other_user && !marketplace_can_message_user($conn, $user_id, $with_id, $product_id > 0 ? $product_id : null)) {
        $access_error = 'You can only message users you have bought from, sold to, or contacted through a listing.';
        $other_user = null;
        $with_id = 0;
        $product_id = 0;
    }

    if ($other_user) {
        $thread_stmt = mysqli_prepare($conn,
            "SELECT m.*, u.name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC");
        mysqli_stmt_bind_param($thread_stmt, 'iiii', $user_id, $with_id, $with_id, $user_id);
        mysqli_stmt_execute($thread_stmt);
        $thread_res = mysqli_stmt_get_result($thread_stmt);
        while ($row = mysqli_fetch_assoc($thread_res)) {
            $thread[] = $row;
        }
        mysqli_stmt_close($thread_stmt);
    }

    $found = false;
    foreach ($conversations as $c) {
        if ((int)$c['other_id'] === $with_id) {
            $found = true;
            break;
        }
    }
    if (!$found && $other_user) {
        array_unshift($conversations, [
            'other_id'   => $with_id,
            'other_name' => $other_user['name'],
            'last_message_time' => null,
        ]);
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Conversations</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-chat-dots me-2 text-primary"></i>Messages</h2>
        <p class="mb-0 text-muted">Stay in touch with sellers and buyers without leaving the marketplace.</p>
    </div>
</section>

<?php if ($access_error): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($access_error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 message-layout" style="min-height:520px;">
    <div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold py-3">Conversations</div>
            <?php if (empty($conversations)): ?>
            <div class="card-body text-muted small text-center pt-5">
                <i class="bi bi-chat-square fs-2 d-block mb-2"></i>No conversations yet.
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($conversations as $conv): ?>
                <a href="/marketplace/messages.php?with=<?= (int)$conv['other_id'] ?>"
                   class="list-group-item list-group-item-action conversation-item py-3 <?= ($with_id === (int)$conv['other_id']) ? 'active' : '' ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle fs-4"></i>
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($conv['other_name']) ?></div>
                            <?php if ($conv['last_message_time']): ?>
                            <div class="text-muted" style="font-size:.7rem;"><?= date('d M, H:i', strtotime($conv['last_message_time'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8 col-lg-9">
        <div class="card border-0 shadow-sm h-100 d-flex flex-column">
            <?php if ($with_id > 0 && $other_user): ?>
            <div class="card-header bg-transparent py-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-circle fs-4"></i>
                <span class="fw-semibold"><?= htmlspecialchars($other_user['name']) ?></span>
            </div>
            <div class="card-body flex-grow-1 overflow-auto d-flex flex-column gap-2 py-3"
                 style="max-height:380px;" id="message-thread">
                <?php if (empty($thread)): ?>
                <p class="text-muted text-center my-auto">No messages yet. Say hello!</p>
                <?php else: ?>
                <?php foreach ($thread as $msg): ?>
                <?php $is_mine = ((int)$msg['sender_id'] === $user_id); ?>
                <div class="d-flex <?= $is_mine ? 'justify-content-end' : 'justify-content-start' ?>">
                    <div class="message-bubble <?= $is_mine ? 'mine' : 'theirs' ?>">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <div class="mt-1 text-end" style="font-size:.65rem;opacity:.7;">
                            <?= date('d M, H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent py-3">
                <form method="POST" action="/marketplace/actions/send_message.php" data-validate-form="message">
                    <?= csrf_field() ?>
                    <input type="hidden" name="receiver_id" value="<?= $with_id ?>">
                    <?php if ($product_id > 0): ?>
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <?php endif; ?>
                    <div class="input-group">
                        <textarea class="form-control" name="message" rows="1"
                                  placeholder="Type a message&hellip;" required
                                  style="resize:none;"></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                <div class="text-center">
                    <i class="bi bi-chat-square-text fs-1 d-block mb-3"></i>
                    Select a conversation or start a new one from a product page.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const thread = document.getElementById('message-thread');
if (thread) thread.scrollTop = thread.scrollHeight;
</script>

<?php require_once 'includes/footer.php'; ?>
