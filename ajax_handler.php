<?php
// ajax_handler.php

// =================================================================
// BAGIAN 1: SETUP & KEAMANAN
// =================================================================

// Pastikan ini adalah request AJAX untuk mencegah akses langsung dari browser.
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403); // Forbidden
    die('Akses tidak diizinkan.');
}

// Memuat semua file yang dibutuhkan
require_once 'includes/db.php';
require_once 'includes/ai_functions.php';
require_once 'includes/view_helpers.php'; // Diperlukan untuk render_comments dan make_clickable_links

// Memulai sesi untuk mengakses data pengguna yang login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================
// BAGIAN 2: AUTENTIKASI & INPUT
// =================================================================

// Semua aksi di sini memerlukan pengguna untuk login.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda harus login.']);
    exit();
}

// Ambil ID pengguna dari sesi dan decode data JSON yang dikirim dari frontend.
$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// Set header response sebagai JSON
header('Content-Type: application/json');

// Validasi input dasar
if (!$data || !isset($data['action'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
    exit();
}


// =================================================================
// BAGIAN 3: ROUTER AKSI (SWITCH-CASE)
// =================================================================

switch ($data['action']) {
    
    case 'get_comments':
        $post_id = (int)($data['post_id'] ?? 0);
        if ($post_id > 0) {
            // Gunakan output buffering untuk menangkap HTML yang di-generate oleh render_comments
            ob_start();
            render_comments($conn, $post_id);
            $comments_html = ob_get_clean();
            echo json_encode(['status' => 'success', 'html' => $comments_html]);
        }
        break;
    
    case 'delete_comment':
        $comment_id = (int)($data['comment_id'] ?? 0);
        $stmt_check = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt_check->bind_param("i", $comment_id);
        $stmt_check->execute();
        $owner_id_result = $stmt_check->get_result();
        if ($owner_id_result->num_rows > 0) {
            $owner_id = $owner_id_result->fetch_assoc()['user_id'];
            if ($owner_id == $user_id) {
                // Hapus juga likes terkait komentar ini untuk menjaga integritas data
                $stmt_delete_likes = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ?");
                $stmt_delete_likes->bind_param("i", $comment_id);
                $stmt_delete_likes->execute();
                $stmt_delete_likes->close();
                
                // Hapus komentar
                $stmt_delete = $conn->prepare("DELETE FROM comments WHERE id = ?");
                $stmt_delete->bind_param("i", $comment_id);
                $stmt_delete->execute();
                $stmt_delete->close();
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
            }
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Komentar tidak ditemukan.']);
        }
        $stmt_check->close();
        break;

    case 'edit_comment':
        $comment_id = (int)($data['comment_id'] ?? 0);
        $new_content = trim($data['content'] ?? '');
        if (empty($new_content)) { die(json_encode(['status' => 'error', 'message' => 'Komentar tidak boleh kosong.'])); }
        
        $stmt_check = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt_check->bind_param("i", $comment_id);
        $stmt_check->execute();
        $owner_id_result = $stmt_check->get_result();
        if ($owner_id_result->num_rows > 0) {
            $owner_id = $owner_id_result->fetch_assoc()['user_id'];
            if ($owner_id == $user_id) {
                $stmt_update = $conn->prepare("UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->bind_param("si", $new_content, $comment_id);
                $stmt_update->execute();
                $stmt_update->close();
                // Gunakan fungsi dari view_helpers.php
                echo json_encode(['status' => 'success', 'new_html' => make_clickable_links(nl2br(htmlspecialchars($new_content)))]);
            } else {
                 echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
            }
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Komentar tidak ditemukan.']);
        }
        $stmt_check->close();
        break;
        
    case 'like_post':
        $post_id = (int)($data['post_id'] ?? 0);
        if ($post_id === 0) { die(json_encode(['status' => 'error', 'message' => 'Post ID tidak valid.'])); }
        
        $stmt_check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt_check->bind_param("ii", $post_id, $user_id);
        $stmt_check->execute();
        $is_liked = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();
        
        if ($is_liked) {
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $post_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $post_id, $user_id);
        }
        $stmt->execute();
        $stmt->close();
        
        $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $stmt_count->bind_param("i", $post_id);
        $stmt_count->execute();
        $new_like_count = $stmt_count->get_result()->fetch_assoc()['count'];
        $stmt_count->close();
        
        echo json_encode(['status' => 'success', 'new_like_count' => $new_like_count, 'is_liked' => !$is_liked]);
        break;

    case 'like_comment':
        $comment_id = (int)($data['comment_id'] ?? 0);
        if ($comment_id === 0) { die(json_encode(['status' => 'error', 'message' => 'Comment ID tidak valid.'])); }
        
        $stmt_check = $conn->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt_check->bind_param("ii", $comment_id, $user_id);
        $stmt_check->execute();
        $is_liked = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();
        
        if ($is_liked) {
            $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $comment_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $comment_id, $user_id);
        }
        $stmt->execute();
        $stmt->close();
        
        $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
        $stmt_count->bind_param("i", $comment_id);
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
            
            // Update XP Pengguna
            $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + 5;
            $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 5 WHERE id = ?");
            $stmt_xp->bind_param("i", $user_id);
            $stmt_xp->execute();
            $stmt_xp->close();
            
            // Cek jika memanggil AI (@ai)
            $ai_response_data = null;
            if (stripos($comment_text, '@ai') !== false) {
                $question = trim(substr($comment_text, stripos($comment_text, '@ai') + 3));
                $ai_content = empty($question) ? "Anda memanggil saya, tetapi tidak ada pertanyaan. Ada yang bisa saya bantu?" : get_ai_reply($question);
                
                $ai_user_id = 9999; // ID khusus untuk AI
                $stmt_ai = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                // Balasan AI me-reply komentar pengguna
                $ai_parent_id = $new_comment_id; 
                $stmt_ai->bind_param("iisi", $post_id, $ai_user_id, $ai_content, $ai_parent_id);
                $stmt_ai->execute();
                $ai_comment_id = $stmt_ai->insert_id;
                $stmt_ai->close();
                
                // Ambil data balasan AI untuk dikirim ke frontend
                $stmt_ai_new = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
                $stmt_ai_new->bind_param("i", $ai_comment_id);
                $stmt_ai_new->execute();
                $ai_response_data = $stmt_ai_new->get_result()->fetch_assoc();
                $stmt_ai_new->close();
            }

            // Ambil data komentar baru pengguna untuk dikirim ke frontend
            $stmt_new = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
            $stmt_new->bind_param("i", $new_comment_id);
            $stmt_new->execute();
            $user_comment_data = $stmt_new->get_result()->fetch_assoc();
            $stmt_new->close();
            
            // Format konten sebelum dikirim sebagai JSON
            if ($user_comment_data) {
                $user_comment_data['content_formatted'] = make_clickable_links(nl2br(htmlspecialchars($user_comment_data['content'])));
            }
            if ($ai_response_data) {
                $ai_response_data['content_formatted'] = make_clickable_links(nl2br(htmlspecialchars($ai_response_data['content'])));
            }
            
            echo json_encode(['status' => 'success', 'comment' => $user_comment_data, 'ai_reply' => $ai_response_data ]);

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Komentar tidak boleh kosong.']);
        }
        break;

    default:
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak diketahui.']);
        break;
}

// =================================================================
// BAGIAN 4: CLEANUP
// =================================================================

// Tutup koneksi database dan hentikan script
$conn->close();
exit();