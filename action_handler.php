<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Akses ditolak.']));
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Request tidak valid.']));
}

header('Content-Type: application/json');

switch ($data['action']) {

    case 'like_post':
        $post_id = (int)($data['post_id'] ?? 0);
        if ($post_id === 0) die(json_encode(['status' => 'error', 'message' => 'Post ID tidak valid.']));

        $stmt_check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt_check->bind_param("ii", $post_id, $user_id);
        $stmt_check->execute();
        $is_liked = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if ($is_liked) {
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
            
            // Notifikasi Like
            $stmt_owner = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt_owner->bind_param("i", $post_id);
            $stmt_owner->execute();
            $post_owner_id = $stmt_owner->get_result()->fetch_assoc()['user_id'];
            $stmt_owner->close();
            if ($post_owner_id != $user_id) {
                $type = 'like';
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, target_id) VALUES (?, ?, ?, ?)");
                $stmt_notif->bind_param("iisi", $post_owner_id, $user_id, $type, $post_id);
                $stmt_notif->execute();
                $stmt_notif->close();
            }
        }
        $stmt->close();
        
        $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $stmt_count->bind_param("i", $post_id);
        $stmt_count->execute();
        $new_like_count = $stmt_count->get_result()->fetch_assoc()['count'];
        $stmt_count->close();
        
        echo json_encode(['status' => 'success', 'new_like_count' => $new_like_count, 'is_liked' => !$is_liked]);
        break;

    case 'add_comment':
        $post_id = (int)($data['post_id'] ?? 0);
        $comment_text = trim($data['comment_text'] ?? '');
        $parent_id = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

        if ($post_id > 0 && !empty($comment_text)) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_id);
            $stmt->execute();
            $new_comment_id = $stmt->insert_id;
            $stmt->close();

            // Logika XP & Notifikasi
            $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + 1;
            $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 1 WHERE id = ?");
            $stmt_xp->bind_param("i", $user_id);
            $stmt_xp->execute();
            $stmt_xp->close();

            // Notifikasi ke pemilik post
            $stmt_owner = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt_owner->bind_param("i", $post_id);
            $stmt_owner->execute();
            $post_owner_id = $stmt_owner->get_result()->fetch_assoc()['user_id'];
            $stmt_owner->close();
            if ($post_owner_id != $user_id) {
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, target_id) VALUES (?, ?, 'comment', ?)");
                $stmt_notif->bind_param("iii", $post_owner_id, $user_id, $post_id);
                $stmt_notif->execute();
                $stmt_notif->close();
            }

            // Notifikasi ke pemilik komentar yang dibalas (jika ini balasan)
            if ($parent_id) {
                $stmt_parent_owner = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
                $stmt_parent_owner->bind_param("i", $parent_id);
                $stmt_parent_owner->execute();
                $parent_owner_id = $stmt_parent_owner->get_result()->fetch_assoc()['user_id'];
                $stmt_parent_owner->close();
                // Kirim notif hanya jika pemilik komen bukan orang yg sama dgn yg membalas ATAU pemilik post
                if ($parent_owner_id != $user_id && $parent_owner_id != $post_owner_id) {
                    $stmt_notif_reply = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, target_id) VALUES (?, ?, 'comment', ?)");
                    $stmt_notif_reply->bind_param("iii", $parent_owner_id, $user_id, $post_id);
                    $stmt_notif_reply->execute();
                    $stmt_notif_reply->close();
                }
            }

            // Ambil data komentar baru untuk dikirim balik ke frontend
            $stmt_new = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
            $stmt_new->bind_param("i", $new_comment_id);
            $stmt_new->execute();
            $new_comment_data = $stmt_new->get_result()->fetch_assoc();
            $stmt_new->close();

            $new_comment_data['created_at_formatted'] = date("d M Y");
            $new_comment_data['content_formatted'] = nl2br(htmlspecialchars(make_clickable_links($new_comment_data['content'])));

            echo json_encode(['status' => 'success', 'comment' => $new_comment_data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Komentar tidak boleh kosong.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
        break;
}
exit();