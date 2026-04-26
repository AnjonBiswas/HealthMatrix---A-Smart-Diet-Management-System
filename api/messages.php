<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
redirectIfNotLoggedIn(['user', 'dietitian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$actorType = (string) ($_SESSION['user_type'] ?? '');
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

function mOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($actorId <= 0 || !in_array($actorType, ['user', 'dietitian'], true)) {
    mOut(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    if ($action === 'send') {
        $receiverId = (int) ($_POST['receiver_id'] ?? $_POST['dietitian_id'] ?? 0);
        $receiverType = (string) ($_POST['receiver_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($receiverId <= 0) mOut(['success' => false, 'message' => 'Invalid receiver.'], 422);
        if (!in_array($receiverType, ['user', 'dietitian'], true) || $receiverType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid receiver type.'], 422);
        }
        if ($message === '') mOut(['success' => false, 'message' => 'Message cannot be empty.'], 422);
        if (mb_strlen($message) > 1000) mOut(['success' => false, 'message' => 'Message max length is 1000 chars.'], 422);

        if ($receiverType === 'user') {
            $q = $pdo->prepare('SELECT id FROM users WHERE id=:id LIMIT 1');
        } else {
            $q = $pdo->prepare('SELECT id FROM dietitians WHERE id=:id LIMIT 1');
        }
        $q->execute([':id' => $receiverId]);
        if (!$q->fetchColumn()) mOut(['success' => false, 'message' => 'Receiver not found.'], 404);

        $ins = $pdo->prepare(
            'INSERT INTO messages (sender_id,sender_type,receiver_id,receiver_type,message,is_read,created_at)
             VALUES (:sid,:st,:rid,:rt,:m,0,NOW())'
        );
        $ins->execute([
            ':sid' => $actorId,
            ':st' => $actorType,
            ':rid' => $receiverId,
            ':rt' => $receiverType,
            ':m' => $message,
        ]);

        logActivity($actorId, $actorType, 'Sent message');
        mOut([
            'success' => true,
            'message' => 'Message sent',
            'data' => [
                'id' => (int) $pdo->lastInsertId(),
                'sender_id' => $actorId,
                'sender_type' => $actorType,
                'receiver_id' => $receiverId,
                'receiver_type' => $receiverType,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    if ($action === 'get_conversation' || $action === 'get') {
        $partnerId = (int) ($_POST['partner_id'] ?? $_POST['dietitian_id'] ?? $_POST['receiver_id'] ?? 0);
        $partnerType = (string) ($_POST['partner_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        if ($partnerId <= 0 || !in_array($partnerType, ['user', 'dietitian'], true) || $partnerType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid conversation partner.'], 422);
        }

        $q = $pdo->prepare(
            'SELECT id,sender_id,sender_type,receiver_id,receiver_type,message,is_read,created_at
             FROM messages
             WHERE
             (sender_id=:sid AND sender_type=:st AND receiver_id=:rid AND receiver_type=:rt)
             OR
             (sender_id=:rid AND sender_type=:rt AND receiver_id=:sid AND receiver_type=:st)
             ORDER BY created_at ASC, id ASC'
        );
        $q->execute([
            ':sid' => $actorId, ':st' => $actorType,
            ':rid' => $partnerId, ':rt' => $partnerType,
        ]);
        mOut(['success' => true, 'messages' => $q->fetchAll()]);
    }

    if ($action === 'get_list') {
        $q = $pdo->prepare(
            'SELECT
               CASE WHEN sender_id=:id AND sender_type=:type THEN receiver_id ELSE sender_id END AS partner_id,
               CASE WHEN sender_id=:id AND sender_type=:type THEN receiver_type ELSE sender_type END AS partner_type,
               MAX(id) AS last_message_id
             FROM messages
             WHERE
             (sender_id=:id AND sender_type=:type)
             OR
             (receiver_id=:id AND receiver_type=:type)
             GROUP BY partner_id, partner_type
             ORDER BY last_message_id DESC'
        );
        $q->execute([':id' => $actorId, ':type' => $actorType]);
        $pairs = $q->fetchAll();

        $list = [];
        foreach ($pairs as $p) {
            $lm = $pdo->prepare('SELECT id,message,created_at,sender_id,sender_type FROM messages WHERE id=:id LIMIT 1');
            $lm->execute([':id' => (int) $p['last_message_id']]);
            $last = $lm->fetch();
            $partnerId = (int) $p['partner_id'];
            $partnerType = (string) $p['partner_type'];

            if ($partnerType === 'user') {
                $pn = $pdo->prepare('SELECT full_name,profile_pic FROM users WHERE id=:id LIMIT 1');
            } else {
                $pn = $pdo->prepare('SELECT full_name,profile_pic FROM dietitians WHERE id=:id LIMIT 1');
            }
            $pn->execute([':id' => $partnerId]);
            $profile = $pn->fetch() ?: ['full_name' => 'Unknown', 'profile_pic' => null];

            $un = $pdo->prepare(
                'SELECT COUNT(*) c FROM messages
                 WHERE receiver_id=:id AND receiver_type=:type
                 AND sender_id=:pid AND sender_type=:ptype
                 AND is_read=0'
            );
            $un->execute([':id' => $actorId, ':type' => $actorType, ':pid' => $partnerId, ':ptype' => $partnerType]);
            $unread = (int) ($un->fetch()['c'] ?? 0);

            $list[] = [
                'partner_id' => $partnerId,
                'partner_type' => $partnerType,
                'partner_name' => (string) $profile['full_name'],
                'partner_profile_pic' => $profile['profile_pic'],
                'last_message' => (string) ($last['message'] ?? ''),
                'last_time' => (string) ($last['created_at'] ?? ''),
                'unread_count' => $unread,
            ];
        }
        mOut(['success' => true, 'list' => $list]);
    }

    if ($action === 'mark_read') {
        $partnerId = (int) ($_POST['partner_id'] ?? $_POST['dietitian_id'] ?? $_POST['receiver_id'] ?? 0);
        $partnerType = (string) ($_POST['partner_type'] ?? ($actorType === 'user' ? 'dietitian' : 'user'));
        if ($partnerId <= 0 || !in_array($partnerType, ['user', 'dietitian'], true) || $partnerType === $actorType) {
            mOut(['success' => false, 'message' => 'Invalid partner.'], 422);
        }
        $u = $pdo->prepare(
            'UPDATE messages SET is_read=1
             WHERE receiver_id=:id AND receiver_type=:type
             AND sender_id=:pid AND sender_type=:ptype
             AND is_read=0'
        );
        $u->execute([':id' => $actorId, ':type' => $actorType, ':pid' => $partnerId, ':ptype' => $partnerType]);
        mOut(['success' => true, 'updated' => $u->rowCount()]);
    }

    if ($action === 'get_unread_count' || $action === 'get_count') {
        $u = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE receiver_id=:id AND receiver_type=:type AND is_read=0');
        $u->execute([':id' => $actorId, ':type' => $actorType]);
        mOut(['success' => true, 'unread_count' => (int) ($u->fetch()['c'] ?? 0)]);
    }

    mOut(['success' => false, 'message' => 'Unsupported action'], 422);
} catch (Throwable $e) {
    mOut(['success' => false, 'message' => 'Server error while processing messages'], 500);
}
