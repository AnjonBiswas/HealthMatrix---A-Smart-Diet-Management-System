<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

$selectedDietitianId = (int) ($_GET['dietitian_id'] ?? 0);

$stmtConversations = $pdo->prepare(
    'SELECT
        d.id AS dietitian_id,
        d.full_name,
        d.profile_pic,
        last_msg.message AS last_message,
        last_msg.created_at AS last_time,
        COALESCE(unread.unread_count, 0) AS unread_count
     FROM dietitians d
     JOIN (
         SELECT
            CASE
                WHEN sender_type = "dietitian" THEN sender_id
                ELSE receiver_id
            END AS dietitian_id,
            MAX(id) AS max_msg_id
         FROM messages
         WHERE
         (sender_type = "user" AND sender_id = :user_id_1 AND receiver_type = "dietitian")
         OR
         (sender_type = "dietitian" AND receiver_type = "user" AND receiver_id = :user_id_2)
         GROUP BY dietitian_id
     ) conv ON conv.dietitian_id = d.id
     JOIN messages last_msg ON last_msg.id = conv.max_msg_id
     LEFT JOIN (
        SELECT sender_id AS dietitian_id, COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_type = "user" AND receiver_id = :user_id_3
        AND sender_type = "dietitian" AND is_read = 0
        GROUP BY sender_id
     ) unread ON unread.dietitian_id = d.id
     ORDER BY last_msg.created_at DESC'
);
$stmtConversations->execute([
    ':user_id_1' => $userId,
    ':user_id_2' => $userId,
    ':user_id_3' => $userId,
]);
$conversations = $stmtConversations->fetchAll();

if ($selectedDietitianId <= 0 && !empty($conversations)) {
    $selectedDietitianId = (int) $conversations[0]['dietitian_id'];
}

$stmtDietitians = $pdo->query(
    'SELECT id, full_name, profile_pic, specialization
     FROM dietitians
     WHERE status = "active"
     ORDER BY full_name ASC'
);
$activeDietitians = $stmtDietitians->fetchAll();

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
    <style>
        .chat-layout { display:grid; grid-template-columns:340px 1fr; gap:1rem; min-height:72vh; }
        .conv-list { border:1px solid var(--border); border-radius:12px; background:#fff; overflow:hidden; }
        .conv-header { padding:.9rem; border-bottom:1px solid #edf1f3; }
        .conv-scroll { max-height:62vh; overflow:auto; }
        .conv-item { display:flex; gap:.6rem; padding:.7rem .9rem; border-bottom:1px solid #f2f4f6; cursor:pointer; }
        .conv-item.active { background:#eefbf3; }
        .conv-item:hover { background:#f8fffb; }
        .chat-panel { border:1px solid var(--border); border-radius:12px; background:#fff; display:flex; flex-direction:column; min-height:72vh; }
        .chat-head { padding:.9rem 1rem; border-bottom:1px solid #edf1f3; }
        .chat-body { flex:1; overflow:auto; padding:1rem; background:#f7fbf9; }
        .chat-input { border-top:1px solid #edf1f3; padding:.8rem; }
        .msg-row { display:flex; margin-bottom:.75rem; }
        .msg-row.user { justify-content:flex-end; }
        .bubble { max-width:75%; padding:.6rem .75rem; border-radius:14px; line-height:1.4; }
        .bubble.user { background:#2ECC71; color:#fff; border-bottom-right-radius:4px; }
        .bubble.dietitian { background:#e9edf1; color:#2C3E50; border-bottom-left-radius:4px; }
        .msg-time { font-size:.72rem; color:#6b7a86; margin-top:.2rem; text-align:right; }
        .msg-time.left { text-align:left; }
        .avatar-sm { width:42px; height:42px; border-radius:50%; object-fit:cover; }
        @media (max-width: 991px) { .chat-layout { grid-template-columns:1fr; } .chat-panel { min-height:60vh; } .conv-scroll { max-height:36vh; } }
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <nav class="navbar">
                <button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div><h5 class="mb-0">Messages</h5><small class="text-muted">Chat with your dietitian</small></div>
            </nav>
            <div id="msgAlert"></div>

            <div class="chat-layout">
                <section class="conv-list">
                    <div class="conv-header">
                        <div class="d-flex gap-2">
                            <input type="text" id="convSearch" class="form-control" placeholder="Search conversations...">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">New Message</button>
                        </div>
                    </div>
                    <div class="conv-scroll" id="convList">
                        <?php foreach ($conversations as $c): ?>
                            <?php
                            $avatar = !empty($c['profile_pic']) ? SITE_URL . '/uploads/' . ltrim((string) $c['profile_pic'], '/') : SITE_URL . '/assets/images/default_avatar.png';
                            $active = (int) $c['dietitian_id'] === $selectedDietitianId ? 'active' : '';
                            ?>
                            <a class="conv-item <?= $active ?>" data-name="<?= e(strtolower((string) $c['full_name'])) ?>" href="<?= SITE_URL ?>/user/messages.php?dietitian_id=<?= (int) $c['dietitian_id'] ?>">
                                <img class="avatar-sm" src="<?= e($avatar) ?>" alt="avatar">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= e((string) $c['full_name']) ?></strong>
                                        <small class="text-muted"><?= e(date('h:i A', strtotime((string) $c['last_time']))) ?></small>
                                    </div>
                                    <small class="text-muted d-block"><?= e(mb_strimwidth((string) $c['last_message'], 0, 36, '...')) ?></small>
                                </div>
                                <?php if ((int) $c['unread_count'] > 0): ?>
                                    <span class="badge"><?= (int) $c['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                            <p class="p-3 text-muted mb-0">No conversations yet. Start by messaging a dietitian.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="chat-panel">
                    <div class="chat-head">
                        <?php if ($selectedDietitianId > 0): ?>
                            <strong id="chatTitle">Conversation with Dietitian #<?= $selectedDietitianId ?></strong>
                        <?php else: ?>
                            <strong id="chatTitle">No conversation selected</strong>
                        <?php endif; ?>
                    </div>
                    <div class="chat-body" id="chatBody">
                        <p class="text-muted">Loading conversation...</p>
                    </div>
                    <div class="chat-input">
                        <form id="sendMessageForm" class="d-flex gap-2">
                            <input type="hidden" id="selectedDietitianId" value="<?= (int) $selectedDietitianId ?>">
                            <textarea class="form-control" id="messageInput" rows="2" maxlength="1000" placeholder="Type your message..."></textarea>
                            <button class="btn btn-success" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                        <small class="text-muted">Enter to send, Shift+Enter for newline.</small>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="newMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Start New Message</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($activeDietitians as $d): ?>
                        <a class="list-group-item list-group-item-action" href="<?= SITE_URL ?>/user/messages.php?dietitian_id=<?= (int) $d['id'] ?>">
                            <strong><?= e((string) $d['full_name']) ?></strong>
                            <small class="d-block text-muted"><?= e((string) $d['specialization']) ?></small>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($activeDietitians)): ?><p class="text-muted mb-0">No active dietitians found.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const msgApi = '<?= SITE_URL ?>/api/messages.php';
const chatBody = document.getElementById('chatBody');
const messageInput = document.getElementById('messageInput');
const selectedDietitianIdInput = document.getElementById('selectedDietitianId');
const msgAlert = document.getElementById('msgAlert');

function showMsgAlert(message, type='success') {
    msgAlert.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
}

async function msgPost(payload) {
    const res = await fetch(msgApi, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload)
    });
    return await res.json();
}

function renderMessages(messages) {
    if (!messages || !messages.length) {
        chatBody.innerHTML = '<p class="text-muted">No messages yet. Start the conversation.</p>';
        return;
    }
    chatBody.innerHTML = messages.map(m => {
        const isUser = m.sender_type === 'user';
        const time = new Date(m.created_at.replace(' ', 'T')).toLocaleString([], { month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        return `<div class="msg-row ${isUser ? 'user' : ''}">
            <div>
                <div class="bubble ${isUser ? 'user' : 'dietitian'}">${escapeHtml(m.message)}</div>
                <div class="msg-time ${isUser ? '' : 'left'}">${time}</div>
            </div>
        </div>`;
    }).join('');
    chatBody.scrollTop = chatBody.scrollHeight;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[s]));
}

async function loadMessages() {
    const dietitianId = parseInt(selectedDietitianIdInput.value || '0', 10);
    if (!dietitianId) {
        chatBody.innerHTML = '<p class="text-muted">Choose a dietitian to start messaging.</p>';
        return;
    }
    const result = await msgPost({ action: 'get', dietitian_id: dietitianId });
    if (!result.success) {
        showMsgAlert(result.message || 'Could not load messages.', 'danger');
        return;
    }
    renderMessages(result.messages || []);
    await msgPost({ action: 'mark_read', dietitian_id: dietitianId });
}

document.getElementById('sendMessageForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const dietitianId = parseInt(selectedDietitianIdInput.value || '0', 10);
    const message = messageInput.value.trim();
    if (!dietitianId) { showMsgAlert('Select a dietitian first.', 'warning'); return; }
    if (!message) { showMsgAlert('Message cannot be empty.', 'warning'); return; }
    if (message.length > 1000) { showMsgAlert('Message too long (max 1000 chars).', 'warning'); return; }
    const result = await msgPost({ action: 'send', dietitian_id: dietitianId, message });
    if (!result.success) { showMsgAlert(result.message || 'Could not send message.', 'danger'); return; }
    messageInput.value = '';
    await loadMessages();
});

messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('sendMessageForm').dispatchEvent(new Event('submit'));
    }
});

document.getElementById('convSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#convList .conv-item').forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(q) ? '' : 'none';
    });
});

setInterval(loadMessages, 10000);
loadMessages();
</script>
</body>
</html>

