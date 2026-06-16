<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
redirectIfNotLoggedIn(['user', 'dietitian']);

function mOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mOut(['success' => false, 'message' => 'Method not allowed'], 405);
}

function messageAttachmentUrl(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }

    return rtrim(UPLOAD_URL, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

function formatMessageRow(array $row): array
{
    $attachmentPath = (string) ($row['attachment_path'] ?? '');

    return [
        'id' => (int) ($row['id'] ?? 0),
        'sender_id' => (int) ($row['sender_id'] ?? 0),
        'sender_type' => (string) ($row['sender_type'] ?? ''),
        'receiver_id' => (int) ($row['receiver_id'] ?? 0),
        'receiver_type' => (string) ($row['receiver_type'] ?? ''),
        'message' => (string) ($row['message'] ?? ''),
        'attachment_path' => $attachmentPath !== '' ? $attachmentPath : null,
        'attachment_name' => ($row['attachment_name'] ?? null) !== null ? (string) $row['attachment_name'] : null,
        'attachment_mime' => ($row['attachment_mime'] ?? null) !== null ? (string) $row['attachment_mime'] : null,
        'attachment_url' => messageAttachmentUrl($attachmentPath !== '' ? $attachmentPath : null),
        'has_attachment' => $attachmentPath !== '',
        'is_read' => (int) ($row['is_read'] ?? 0) === 1,
        'seen_at' => ($row['seen_at'] ?? null) !== null ? (string) $row['seen_at'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function participantExists(PDO $pdo, int $id, string $type): bool
{
    $table = $type === 'user' ? 'users' : 'dietitians';
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return (bool) $stmt->fetchColumn();
}

function fetchPartnerProfile(PDO $pdo, int $id, string $type): array
{
    $table = $type === 'user' ? 'users' : 'dietitians';
    $stmt = $pdo->prepare("SELECT full_name, profile_pic FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $profile = $stmt->fetch();

    return [
        'full_name' => (string) ($profile['full_name'] ?? 'Unknown'),
        'profile_pic' => ($profile['profile_pic'] ?? null) !== null ? (string) $profile['profile_pic'] : null,
    ];
}

$pdo = Database::getInstance()->getConnection();
ensureMessagesInfrastructure($pdo);

$actorId = (int) ($_SESSION['user_id'] ?? 0);
$actorType = (string) ($_SESSION['user_type'] ?? '');
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

if ($actorId <= 0 || !in_array($actorType, ['user', 'dietitian'], true)) {
    mOut(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    if ($action === 'send') {
        $receiverId = (int) ($_POST['receiver_id'] ?? $_POST['dietitian_id'] ?? $_POST['user_id'] ?? 0);
        $receiverType = (string) ($_POST['receiver_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        $message = trim((string) ($_POST['message'] ?? ''));
        $attachment = $_FILES['attachment'] ?? null;

        if ($receiverId <= 0) {
            mOut(['success' => false, 'message' => 'Invalid receiver.'], 422);
        }
        if (!in_array($receiverType, ['user', 'dietitian'], true) || $receiverType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid receiver type.'], 422);
        }
        if ($message === '' && (!$attachment || (int) ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            mOut(['success' => false, 'message' => 'Message or photo is required.'], 422);
        }
        if (mb_strlen($message) > 1000) {
            mOut(['success' => false, 'message' => 'Message max length is 1000 chars.'], 422);
        }
        if (!participantExists($pdo, $receiverId, $receiverType)) {
            mOut(['success' => false, 'message' => 'Receiver not found.'], 404);
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        if ($attachment && (int) ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = uploadMessageAttachment($attachment);
            if (!($upload['success'] ?? false)) {
                mOut(['success' => false, 'message' => (string) ($upload['error'] ?? 'Attachment upload failed.')], 422);
            }
            $attachmentPath = (string) ($upload['path'] ?? '');
            $attachmentName = (string) ($attachment['name'] ?? basename($attachmentPath));
            $attachmentMime = (string) (mime_content_type((string) ($attachment['tmp_name'] ?? '')) ?: ($attachment['type'] ?? 'image/jpeg'));
        }

        $ins = $pdo->prepare(
            'INSERT INTO messages (
                sender_id, sender_type, receiver_id, receiver_type, message,
                attachment_path, attachment_name, attachment_mime, is_read, seen_at, created_at
            ) VALUES (
                :sid, :st, :rid, :rt, :message,
                :attachment_path, :attachment_name, :attachment_mime, 0, NULL, NOW()
            )'
        );
        $ins->execute([
            ':sid' => $actorId,
            ':st' => $actorType,
            ':rid' => $receiverId,
            ':rt' => $receiverType,
            ':message' => $message,
            ':attachment_path' => $attachmentPath,
            ':attachment_name' => $attachmentName,
            ':attachment_mime' => $attachmentMime,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $pdo->lastInsertId()]);
        $row = $stmt->fetch() ?: [];

        logActivity($actorId, $actorType, 'Sent message');
        mOut([
            'success' => true,
            'message' => 'Message sent',
            'data' => formatMessageRow($row),
        ]);
    }

    if ($action === 'get_conversation' || $action === 'get') {
        $partnerId = (int) ($_POST['partner_id'] ?? $_POST['dietitian_id'] ?? $_POST['user_id'] ?? $_POST['receiver_id'] ?? 0);
        $partnerType = (string) ($_POST['partner_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        if ($partnerId <= 0 || !in_array($partnerType, ['user', 'dietitian'], true) || $partnerType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid conversation partner.'], 422);
        }

        $profile = fetchPartnerProfile($pdo, $partnerId, $partnerType);
        $q = $pdo->prepare(
            'SELECT *
             FROM messages
             WHERE
             (sender_id = :sid_1 AND sender_type = :st_1 AND receiver_id = :rid_1 AND receiver_type = :rt_1)
             OR
             (sender_id = :rid_2 AND sender_type = :rt_2 AND receiver_id = :sid_2 AND receiver_type = :st_2)
             ORDER BY created_at ASC, id ASC'
        );
        $q->execute([
            ':sid_1' => $actorId,
            ':st_1' => $actorType,
            ':rid_1' => $partnerId,
            ':rt_1' => $partnerType,
            ':rid_2' => $partnerId,
            ':rt_2' => $partnerType,
            ':sid_2' => $actorId,
            ':st_2' => $actorType,
        ]);

        $messages = array_map('formatMessageRow', $q->fetchAll());
        mOut([
            'success' => true,
            'partner' => [
                'id' => $partnerId,
                'type' => $partnerType,
                'name' => $profile['full_name'],
                'profile_pic' => $profile['profile_pic'],
                'profile_url' => messageAttachmentUrl($profile['profile_pic']),
            ],
            'messages' => $messages,
        ]);
    }

    if ($action === 'get_list') {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM messages
             WHERE
             (sender_id = :id_1 AND sender_type = :type_1)
             OR
             (receiver_id = :id_2 AND receiver_type = :type_2)
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([
            ':id_1' => $actorId,
            ':type_1' => $actorType,
            ':id_2' => $actorId,
            ':type_2' => $actorType,
        ]);
        $rows = $stmt->fetchAll();

        $unreadStmt = $pdo->prepare(
            'SELECT sender_id, sender_type, COUNT(*) AS unread_count
             FROM messages
             WHERE receiver_id = :id AND receiver_type = :type AND is_read = 0
             GROUP BY sender_id, sender_type'
        );
        $unreadStmt->execute([':id' => $actorId, ':type' => $actorType]);
        $unreadMap = [];
        foreach ($unreadStmt->fetchAll() as $unreadRow) {
            $unreadMap[$unreadRow['sender_type'] . ':' . $unreadRow['sender_id']] = (int) $unreadRow['unread_count'];
        }

        $list = [];
        $seenPartners = [];
        foreach ($rows as $row) {
            $partnerId = (int) ($row['sender_id'] === $actorId && $row['sender_type'] === $actorType ? $row['receiver_id'] : $row['sender_id']);
            $partnerType = (string) ($row['sender_id'] === $actorId && $row['sender_type'] === $actorType ? $row['receiver_type'] : $row['sender_type']);
            $key = $partnerType . ':' . $partnerId;
            if (isset($seenPartners[$key])) {
                continue;
            }

            $profile = fetchPartnerProfile($pdo, $partnerId, $partnerType);
            $lastMessage = trim((string) ($row['message'] ?? ''));
            if ($lastMessage === '' && !empty($row['attachment_path'])) {
                $lastMessage = 'Photo';
            }

            $list[] = [
                'partner_id' => $partnerId,
                'partner_type' => $partnerType,
                'partner_name' => $profile['full_name'],
                'partner_profile_pic' => $profile['profile_pic'],
                'partner_profile_url' => messageAttachmentUrl($profile['profile_pic']),
                'last_message' => $lastMessage,
                'last_time' => (string) ($row['created_at'] ?? ''),
                'unread_count' => $unreadMap[$key] ?? 0,
                'last_message_has_attachment' => !empty($row['attachment_path']),
            ];
            $seenPartners[$key] = true;
        }

        mOut(['success' => true, 'list' => $list]);
    }

    if ($action === 'mark_read') {
        $partnerId = (int) ($_POST['partner_id'] ?? $_POST['dietitian_id'] ?? $_POST['user_id'] ?? $_POST['receiver_id'] ?? 0);
        $partnerType = (string) ($_POST['partner_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        if ($partnerId <= 0 || !in_array($partnerType, ['user', 'dietitian'], true) || $partnerType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid partner.'], 422);
        }

        $u = $pdo->prepare(
            'UPDATE messages
             SET is_read = 1, seen_at = COALESCE(seen_at, NOW())
             WHERE receiver_id = :id AND receiver_type = :type
             AND sender_id = :pid AND sender_type = :ptype
             AND is_read = 0'
        );
        $u->execute([
            ':id' => $actorId,
            ':type' => $actorType,
            ':pid' => $partnerId,
            ':ptype' => $partnerType,
        ]);
        mOut(['success' => true, 'updated' => $u->rowCount()]);
    }

    if ($action === 'get_unread_count' || $action === 'get_count') {
        $u = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE receiver_id = :id AND receiver_type = :type AND is_read = 0');
        $u->execute([':id' => $actorId, ':type' => $actorType]);
        mOut(['success' => true, 'unread_count' => (int) ($u->fetch()['c'] ?? 0)]);
    }

    mOut(['success' => false, 'message' => 'Unsupported action'], 422);
} catch (Throwable) {
    mOut(['success' => false, 'message' => 'Server error while processing messages'], 500);
}
