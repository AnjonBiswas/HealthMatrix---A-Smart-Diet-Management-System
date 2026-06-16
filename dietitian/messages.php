<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['dietitian']);

$pdo = Database::getInstance()->getConnection();
ensureMessagesInfrastructure($pdo);

$dietitianId = (int) ($_SESSION['user_id'] ?? 0);
if ($dietitianId <= 0) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

$selectedUserId = (int) ($_GET['user_id'] ?? 0);

$stmtConversations = $pdo->prepare(
    'SELECT
        u.id AS user_id,
        u.full_name,
        u.profile_pic,
        u.goal,
        last_msg.message AS last_message,
        last_msg.attachment_path AS last_attachment_path,
        last_msg.created_at AS last_time,
        COALESCE(unread.unread_count, 0) AS unread_count
     FROM users u
     JOIN (
         SELECT
            CASE WHEN sender_type = "user" THEN sender_id ELSE receiver_id END AS user_id,
            MAX(id) AS max_msg_id
         FROM messages
         WHERE
         (sender_type = "dietitian" AND sender_id = :dietitian_id_1 AND receiver_type = "user")
         OR
         (sender_type = "user" AND receiver_type = "dietitian" AND receiver_id = :dietitian_id_2)
         GROUP BY user_id
     ) conv ON conv.user_id = u.id
     JOIN messages last_msg ON last_msg.id = conv.max_msg_id
     LEFT JOIN (
        SELECT sender_id AS user_id, COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_type = "dietitian" AND receiver_id = :dietitian_id_3
        AND sender_type = "user" AND is_read = 0
        GROUP BY sender_id
     ) unread ON unread.user_id = u.id
     ORDER BY last_msg.created_at DESC'
);
$stmtConversations->execute([
    ':dietitian_id_1' => $dietitianId,
    ':dietitian_id_2' => $dietitianId,
    ':dietitian_id_3' => $dietitianId,
]);
$conversations = $stmtConversations->fetchAll();

$stmtUsers = $pdo->prepare(
    'SELECT DISTINCT u.id, u.full_name, u.profile_pic, u.goal, u.email
     FROM users u
     JOIN user_diet_plans udp ON udp.user_id = u.id
     WHERE udp.dietitian_id = :dietitian_id
     ORDER BY u.full_name ASC'
);
$stmtUsers->execute([':dietitian_id' => $dietitianId]);
$assignedUsers = $stmtUsers->fetchAll();

$userMap = [];
foreach ($assignedUsers as $user) {
    $userMap[(int) $user['id']] = $user;
}
foreach ($conversations as $conversation) {
    $userMap[(int) $conversation['user_id']] = [
        'id' => (int) $conversation['user_id'],
        'full_name' => (string) $conversation['full_name'],
        'profile_pic' => $conversation['profile_pic'],
        'goal' => (string) ($conversation['goal'] ?? ''),
        'email' => '',
    ];
}

if ($selectedUserId <= 0) {
    if (!empty($conversations)) {
        $selectedUserId = (int) $conversations[0]['user_id'];
    } elseif (!empty($assignedUsers)) {
        $selectedUserId = (int) $assignedUsers[0]['id'];
    }
}

$selectedUser = $userMap[$selectedUserId] ?? null;

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
        .chat-layout { display:grid; grid-template-columns:350px 1fr; gap:1rem; min-height:72vh; }
        .conv-list, .chat-panel { border:1px solid rgba(63, 86, 74, .12); border-radius:24px; background:#fff; overflow:hidden; box-shadow:0 18px 40px rgba(27, 67, 50, .08); }
        .conv-list { background:linear-gradient(180deg, #ffffff 0%, #f5fbf7 100%); }
        .conv-header, .chat-head { padding:1rem 1.1rem; border-bottom:1px solid #edf1f3; }
        .conv-scroll { max-height:72vh; overflow:auto; }
        .conv-item { display:flex; gap:.85rem; padding:1rem 1.05rem; border-bottom:1px solid rgba(237, 241, 243, .85); cursor:pointer; text-decoration:none; color:inherit; transition:background .2s ease, transform .2s ease; }
        .conv-item.active { background:linear-gradient(135deg, #e9fff0 0%, #f5fff8 100%); }
        .conv-item:hover { background:#f8fffb; transform:translateX(2px); }
        .conv-item .badge { align-self:center; background:#1f8f5f; color:#fff; border-radius:999px; min-width:28px; }
        .chat-panel { display:flex; flex-direction:column; min-height:72vh; background:linear-gradient(180deg, #ffffff 0%, #fbfefc 100%); }
        .chat-head { background:
            radial-gradient(circle at top left, rgba(46, 204, 113, .14), transparent 28%),
            linear-gradient(135deg, #ffffff 0%, #f4fbf7 100%); }
        .chat-body { flex:1; overflow:auto; padding:1.2rem; background:
            radial-gradient(circle at top left, rgba(46, 204, 113, .07), transparent 22%),
            radial-gradient(circle at bottom right, rgba(31, 143, 95, .06), transparent 24%),
            linear-gradient(180deg, #f7fbf9 0%, #f2f8f5 100%); }
        .chat-input { border-top:1px solid #edf1f3; padding:1rem; background:#fff; }
        .composer-shell { border:1px solid #dce9e2; border-radius:22px; padding:.8rem; background:linear-gradient(180deg, #ffffff 0%, #f9fcfa 100%); box-shadow:0 10px 24px rgba(26, 43, 34, .05); }
        .message-box { border:none; resize:none; min-height:54px; background:transparent; box-shadow:none !important; font-size:.98rem; }
        .message-box:focus { background:transparent; }
        .msg-row { display:flex; margin-bottom:1rem; }
        .msg-row.self { justify-content:flex-end; }
        .bubble-wrap { max-width:min(78%, 560px); }
        .bubble { padding:.85rem .95rem; border-radius:22px; line-height:1.48; box-shadow:0 10px 24px rgba(44, 62, 80, .05); position:relative; }
        .bubble.self { background:linear-gradient(135deg, #2ecc71 0%, #1f8f5f 100%); color:#fff; border-bottom-right-radius:8px; }
        .bubble.other { background:#fff; color:#234; border:1px solid #e6edef; border-bottom-left-radius:8px; }
        .message-image { width:100%; max-width:300px; border-radius:16px; margin-top:.65rem; display:block; box-shadow:0 10px 22px rgba(25, 38, 33, .12); }
        .msg-meta { font-size:.74rem; color:#6b7a86; margin-top:.35rem; display:flex; gap:.45rem; justify-content:flex-end; align-items:center; }
        .msg-meta.left { justify-content:flex-start; }
        .seen-pill { padding:.1rem .45rem; border-radius:999px; background:rgba(31, 143, 95, .12); color:#1f8f5f; font-weight:600; }
        .avatar-sm { width:48px; height:48px; border-radius:16px; object-fit:cover; flex-shrink:0; box-shadow:0 8px 18px rgba(27, 67, 50, .12); }
        .composer-tools { display:flex; justify-content:space-between; align-items:center; gap:.75rem; margin-top:.6rem; flex-wrap:wrap; }
        .attach-btn { border-radius:999px; border-color:#cfe4d8; background:#f7fbf8; color:#245b40; }
        .send-btn { width:48px; height:48px; border-radius:16px; border:none; background:linear-gradient(135deg, #2ecc71 0%, #1f8f5f 100%); box-shadow:0 12px 24px rgba(31, 143, 95, .28); }
        .attachment-chip { display:none; align-items:center; gap:.65rem; padding:.45rem .7rem; border:1px solid #d9e9de; background:linear-gradient(180deg, #f7fbf8 0%, #f0f8f3 100%); border-radius:18px; }
        .attachment-chip.active { display:inline-flex; }
        .attachment-preview { width:54px; height:54px; border-radius:14px; object-fit:cover; box-shadow:0 8px 18px rgba(31, 143, 95, .15); }
        .attachment-meta { display:flex; flex-direction:column; line-height:1.2; }
        .attachment-meta strong { font-size:.82rem; color:#245b40; }
        .empty-chat { height:100%; display:flex; align-items:center; justify-content:center; color:#7c8b97; text-align:center; padding:2rem; }
        .empty-state-card { max-width:360px; padding:1.4rem; border-radius:24px; background:rgba(255, 255, 255, .82); border:1px solid rgba(31, 143, 95, .1); box-shadow:0 14px 28px rgba(27, 67, 50, .08); }
        @media (max-width: 991px) { .chat-layout { grid-template-columns:1fr; } .chat-panel { min-height:62vh; } .conv-scroll { max-height:35vh; } .bubble-wrap { max-width:88%; } }
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
            <li><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
            <li><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <nav class="navbar">
                <button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div><h5 class="mb-0">Messages</h5><small class="text-muted">Support your assigned users with instant replies and meal-photo feedback</small></div>
            </nav>
            <div id="msgAlert"></div>

            <div class="chat-layout">
                <section class="conv-list">
                    <div class="conv-header">
                        <div class="d-flex gap-2">
                            <input type="text" id="convSearch" class="form-control" placeholder="Search users...">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">Assigned Users</button>
                        </div>
                    </div>
                    <div class="conv-scroll" id="convList">
                        <?php foreach ($conversations as $c): ?>
                            <?php
                            $avatar = !empty($c['profile_pic']) ? SITE_URL . '/uploads/' . ltrim((string) $c['profile_pic'], '/') : SITE_URL . '/assets/images/default_avatar.png';
                            $active = (int) $c['user_id'] === $selectedUserId ? 'active' : '';
                            $snippet = trim((string) $c['last_message']) !== '' ? (string) $c['last_message'] : (!empty($c['last_attachment_path']) ? 'Photo' : '');
                            ?>
                            <a class="conv-item <?= $active ?>" data-name="<?= e(strtolower((string) $c['full_name'])) ?>" href="<?= SITE_URL ?>/dietitian/messages.php?user_id=<?= (int) $c['user_id'] ?>">
                                <img class="avatar-sm" src="<?= e($avatar) ?>" alt="avatar">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong class="text-truncate"><?= e((string) $c['full_name']) ?></strong>
                                        <small class="text-muted"><?= e(date('h:i A', strtotime((string) $c['last_time']))) ?></small>
                                    </div>
                                    <small class="text-muted d-block text-truncate"><?= e(mb_strimwidth($snippet, 0, 40, '...')) ?></small>
                                </div>
                                <?php if ((int) $c['unread_count'] > 0): ?>
                                    <span class="badge"><?= (int) $c['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                            <p class="p-3 text-muted mb-0">No conversations yet. Choose an assigned user to start chatting.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="chat-panel">
                    <div class="chat-head">
                        <strong id="chatTitle"><?= $selectedUser ? e((string) $selectedUser['full_name']) : 'No conversation selected' ?></strong>
                        <div class="text-muted small" id="chatSubtitle"><?= $selectedUser ? e(ucwords(str_replace('_', ' ', (string) ($selectedUser['goal'] ?? 'User')))) : 'Choose an assigned user to start messaging' ?></div>
                    </div>
                    <div class="chat-body" id="chatBody">
                        <div class="empty-chat">Loading conversation...</div>
                    </div>
                    <div class="chat-input">
                        <form id="sendMessageForm" enctype="multipart/form-data">
                            <input type="hidden" id="selectedPartnerId" value="<?= (int) $selectedUserId ?>">
                            <div class="composer-shell">
                                <textarea class="form-control message-box" id="messageInput" rows="2" maxlength="1000" placeholder="Type a message or ask for a meal photo update..."></textarea>
                                <div class="composer-tools">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <input type="file" id="attachmentInput" accept="image/*" capture="environment" hidden>
                                        <button class="btn attach-btn btn-sm" type="button" id="attachBtn"><i class="fa-solid fa-camera"></i> Add photo</button>
                                        <div class="attachment-chip" id="attachmentChip">
                                            <img class="attachment-preview" id="attachmentPreview" alt="preview">
                                            <div class="attachment-meta">
                                                <strong id="attachmentName">Photo ready</strong>
                                                <span class="small text-muted">Will send with your message</span>
                                            </div>
                                            <button class="btn btn-sm btn-link text-danger p-0" type="button" id="removeAttachmentBtn"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <small class="text-muted">Enter to send</small>
                                        <button class="btn send-btn text-white" type="submit" id="sendBtn"><i class="fa-solid fa-paper-plane"></i></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="newMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Assigned Users</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($assignedUsers as $u): ?>
                        <a class="list-group-item list-group-item-action" href="<?= SITE_URL ?>/dietitian/messages.php?user_id=<?= (int) $u['id'] ?>">
                            <strong><?= e((string) $u['full_name']) ?></strong>
                            <small class="d-block text-muted"><?= e((string) $u['email']) ?></small>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($assignedUsers)): ?><p class="text-muted mb-0">No users are assigned to you yet.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const msgApi = '<?= SITE_URL ?>/api/messages.php';
const actorType = 'dietitian';
const partnerType = 'user';
const chatBody = document.getElementById('chatBody');
const chatTitle = document.getElementById('chatTitle');
const chatSubtitle = document.getElementById('chatSubtitle');
const messageInput = document.getElementById('messageInput');
const selectedPartnerIdInput = document.getElementById('selectedPartnerId');
const msgAlert = document.getElementById('msgAlert');
const attachmentInput = document.getElementById('attachmentInput');
const attachmentChip = document.getElementById('attachmentChip');
const attachmentPreview = document.getElementById('attachmentPreview');
const attachmentName = document.getElementById('attachmentName');
const sendMessageForm = document.getElementById('sendMessageForm');
const sendBtn = document.getElementById('sendBtn');
let activeRequest = null;
let attachmentPreviewToken = 0;

function showMsgAlert(message, type = 'success') {
    msgAlert.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
    window.setTimeout(() => {
        if (msgAlert.textContent.trim() === message.trim()) {
            msgAlert.innerHTML = '';
        }
    }, 4000);
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;' }[s]));
}

function formatTime(value) {
    if (!value) return '';
    const dt = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(dt.getTime()) ? value : dt.toLocaleString([], { month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
}

async function msgPostForm(formData) {
    const res = await fetch(msgApi, { method: 'POST', body: formData });
    return await res.json();
}

async function msgPost(payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
    return await msgPostForm(formData);
}

function currentPartnerId() {
    return parseInt(selectedPartnerIdInput.value || '0', 10);
}

function clearAttachment() {
    attachmentPreviewToken += 1;
    attachmentInput.value = '';
    attachmentPreview.removeAttribute('src');
    attachmentName.textContent = 'Photo ready';
    attachmentChip.classList.remove('active');
}

function resetComposer() {
    sendMessageForm.reset();
    messageInput.value = '';
    clearAttachment();
}

function renderMessages(messages) {
    if (!messages || !messages.length) {
        chatBody.innerHTML = '<div class="empty-chat"><div class="empty-state-card"><h6 class="mb-2">Reach out first</h6><p class="mb-0">Send encouragement, request an update, or ask for a meal photo to keep progress moving.</p></div></div>';
        return;
    }

    chatBody.innerHTML = messages.map((m) => {
        const isSelf = m.sender_type === actorType;
        const textHtml = m.message ? `<div>${escapeHtml(m.message).replace(/\n/g, '<br>')}</div>` : '';
        const imageHtml = m.attachment_url ? `<a href="${encodeURI(m.attachment_url)}" target="_blank" rel="noopener"><img class="message-image" src="${encodeURI(m.attachment_url)}" alt="${escapeHtml(m.attachment_name || 'Attachment')}"></a>` : '';
        const receipt = isSelf ? (m.is_read ? 'Seen' : 'Sent') : '';
        const receiptHtml = receipt ? `<span class="${m.is_read ? 'seen-pill' : ''}">${receipt}</span>` : '';
        return `<div class="msg-row ${isSelf ? 'self' : ''}">
            <div class="bubble-wrap">
                <div class="bubble ${isSelf ? 'self' : 'other'}">
                    ${textHtml}${imageHtml}
                </div>
                <div class="msg-meta ${isSelf ? '' : 'left'}">
                    <span>${formatTime(m.created_at)}</span>
                    ${receiptHtml}
                </div>
            </div>
        </div>`;
    }).join('');

    chatBody.scrollTop = chatBody.scrollHeight;
}

async function loadMessages() {
    const partnerId = currentPartnerId();
    if (!partnerId) {
        chatTitle.textContent = 'No conversation selected';
        chatSubtitle.textContent = 'Choose an assigned user to start messaging';
        chatBody.innerHTML = '<div class="empty-chat">Choose an assigned user to start messaging.</div>';
        return;
    }

    activeRequest = partnerId;
    const result = await msgPost({ action: 'get', partner_id: partnerId, partner_type: partnerType });
    if (activeRequest !== partnerId) {
        return;
    }
    if (!result.success) {
        chatBody.innerHTML = '<div class="empty-chat">Could not load messages right now.</div>';
        showMsgAlert(result.message || 'Could not load messages.', 'danger');
        return;
    }

    if (result.partner) {
        chatTitle.textContent = result.partner.name || 'User';
        chatSubtitle.textContent = 'Direct conversation';
    }
    renderMessages(result.messages || []);
    await msgPost({ action: 'mark_read', partner_id: partnerId, partner_type: partnerType });
}

document.getElementById('sendMessageForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const partnerId = currentPartnerId();
    const message = messageInput.value.trim();
    const attachment = attachmentInput.files[0];
    if (!partnerId) {
        showMsgAlert('Select a user first.', 'warning');
        return;
    }
    if (!message && !attachment) {
        showMsgAlert('Write a message or attach a photo.', 'warning');
        return;
    }
    if (message.length > 1000) {
        showMsgAlert('Message too long (max 1000 chars).', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('partner_type', partnerType);
    formData.append('receiver_type', partnerType);
    formData.append('user_id', String(partnerId));
    formData.append('message', message);
    if (attachment) {
        formData.append('attachment', attachment);
    }

    sendBtn.disabled = true;
    const result = await msgPostForm(formData);
    sendBtn.disabled = false;
    if (!result.success) {
        showMsgAlert(result.message || 'Could not send message.', 'danger');
        return;
    }

    resetComposer();
    await loadMessages();
});

messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('sendMessageForm').requestSubmit();
    }
});

document.getElementById('convSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#convList .conv-item').forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(q) ? '' : 'none';
    });
});

document.getElementById('attachBtn').addEventListener('click', () => attachmentInput.click());
document.getElementById('removeAttachmentBtn').addEventListener('click', clearAttachment);
attachmentInput.addEventListener('change', () => {
    const file = attachmentInput.files[0];
    if (!file) {
        clearAttachment();
        return;
    }
    const token = ++attachmentPreviewToken;
    attachmentName.textContent = file.name;
    attachmentChip.classList.add('active');
    const reader = new FileReader();
    reader.onload = (event) => {
        if (token !== attachmentPreviewToken) {
            return;
        }
        attachmentPreview.src = event.target?.result || '';
    };
    reader.readAsDataURL(file);
});

setInterval(loadMessages, 5000);
loadMessages();
</script>
</body>
</html>
