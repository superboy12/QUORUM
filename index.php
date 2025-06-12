<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

// Handle semua aksi dari form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $redirect_url = strtok($redirect_url, '#');

    // Handle post baru
    if (isset($_POST['new_post'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $media_path = null;
        if (!empty($_FILES['media']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = basename($_FILES["media"]["name"]);
            $target_file = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "", $filename);
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg','jpeg','png','gif','mp4','webm','ogg'];
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Format file tidak didukung.";
            } else {
                if (move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
                    $media_path = $target_file;
                } else {
                    $error = "Gagal upload file.";
                }
            }
        }
        if (empty($title)) $error = "Judul harus diisi.";
        elseif (empty($content) && !$media_path) $error = "Isi post atau upload media minimal satu.";
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, media_path) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isss", $user_id, $title, $content, $media_path);
                $stmt->execute();
                $stmt->close();

                // ### XP SYSTEM: Tambah 5 XP untuk postingan baru ###
                $xp_to_add = 5;
                $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
                $stmt_xp->bind_param("ii", $xp_to_add, $user_id);
                $stmt_xp->execute();
                $stmt_xp->close();
                $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + $xp_to_add;

                header("Location: index.php");
                exit;
            }
        }
    }

    // Handle Like Post
    if (isset($_POST['like_post_id'])) {
        $post_id = (int)$_POST['like_post_id'];
        $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE post_id = post_id");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
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
        header("Location: " . $redirect_url . "#post-" . $post_id);
        exit;
    }

    // Handle Komentar Baru
    if (isset($_POST['comment_post_id'])) {
        $post_id = (int)$_POST['comment_post_id'];
        $comment = trim($_POST['comment'] ?? '');
        if (!empty($comment)) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, NULL)");
            $stmt->bind_param("iis", $post_id, $user_id, $comment);
            $stmt->execute();
            $new_comment_id = $stmt->insert_id;
            $stmt->close();

            // ### XP SYSTEM: Tambah 1 XP untuk komentar baru ###
            $xp_to_add = 1;
            $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt_xp->bind_param("ii", $xp_to_add, $user_id);
            $stmt_xp->execute();
            $stmt_xp->close();
            $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + $xp_to_add;
            
            $stmt_owner = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt_owner->bind_param("i", $post_id);
            $stmt_owner->execute();
            $post_owner_id = $stmt_owner->get_result()->fetch_assoc()['user_id'];
            $stmt_owner->close();
            if ($post_owner_id != $user_id) {
                $type = 'comment';
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, target_id) VALUES (?, ?, ?, ?)");
                $stmt_notif->bind_param("iisi", $post_owner_id, $user_id, $type, $post_id);
                $stmt_notif->execute();
                $stmt_notif->close();
            }
            
            if (stripos($comment, '@ai') !== false) {
                require_once 'ai.php';
                $ai_prompt = get_ai_prompt($comment);
                if (!empty($ai_prompt)) {
                    $ai_reply = get_ai_reply($ai_prompt);
                    $ai_user_id = 9999;
                    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iisi", $post_id, $ai_user_id, $ai_reply, $new_comment_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            header("Location: " . $redirect_url . "#comment-" . $new_comment_id);
            exit;
        }
    }
    
    // Handle Balas Komentar
    if (isset($_POST['reply_comment_id'])) {
        $comment_id = (int)$_POST['reply_comment_id'];
        $reply_content = trim($_POST['reply_content'] ?? '');
        $parent_post_id = (int)$_POST['reply_post_id'];
        if (!empty($reply_content)) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $parent_post_id, $user_id, $reply_content, $comment_id);
            $stmt->execute();
            $new_reply_id = $stmt->insert_id;
            $stmt->close();

            // ### XP SYSTEM: Tambah 1 XP untuk balasan komentar ###
            $xp_to_add = 1;
            $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt_xp->bind_param("ii", $xp_to_add, $user_id);
            $stmt_xp->execute();
            $stmt_xp->close();
            $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + $xp_to_add;

            header("Location: " . $redirect_url . "#comment-" . $new_reply_id);
            exit;
        }
    }

    // Handle Like Komentar
    if (isset($_POST['like_comment_id'])) {
        $comment_id = (int)$_POST['like_comment_id'];
        $post_id_for_redirect = (int)($_POST['post_id_for_redirect'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE comment_id = comment_id");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $redirect_url . "#comment-" . $comment_id);
        exit;
    }
    
    // Handle Delete Post
    if (isset($_POST['delete_post_id'])) {
        $post_id = (int)$_POST['delete_post_id'];
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit;
    }

    // Handle Edit Post
    if (isset($_POST['edit_post_id'])) {
        $post_id = (int)$_POST['edit_post_id'];
        $new_title = trim($_POST['edit_title'] ?? '');
        $new_content = trim($_POST['edit_content'] ?? '');
        if (!empty($new_title) && (!empty($new_content))) {
            $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $new_title, $new_content, $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: " . $redirect_url . "#post-" . $post_id);
        exit;
    }
    
    // Handle Edit Komentar
    if (isset($_POST['edit_comment_id'])) {
        $comment_id = (int)$_POST['edit_comment_id'];
        $new_content = trim($_POST['edit_comment_content'] ?? '');
        $post_id_for_redirect = (int)($_POST['post_id_for_redirect'] ?? 0);
        if (!empty($new_content)) {
            $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $new_content, $comment_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: " . $redirect_url . "#comment-" . $comment_id);
        exit;
    }
}

// --- Fungsi-fungsi pembantu ---
function get_ai_prompt($text) { if (preg_match('/@ai\s*\((.*?)\)/i', $text, $matches)) return trim($matches[1]); elseif (preg_match('/@ai\s+(.*)/i', $text, $matches)) return trim($matches[1]); return '';}
function make_clickable_links($text) { return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text); }

include 'templates/header.php';

// --- Query Utama ---
$result = $conn->query("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(*) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM comments WHERE comments.post_id = p.id) AS comment_count FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");

// --- Fungsi Render Komentar (dengan desain baru) ---
function render_comments($conn, $post_id, $parent_id = null, $level = 0) {
    global $user_id;
    $stmt = $conn->prepare("SELECT c.*, u.username, u.avatar_url, (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS like_count FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? AND c.parent_id ".($parent_id === null ? "IS NULL" : "= ?")." ORDER BY c.created_at ASC");
    if ($parent_id === null) $stmt->bind_param("i", $post_id);
    else $stmt->bind_param("ii", $post_id, $parent_id);
    $stmt->execute();
    $comments = $stmt->get_result();
    while ($c = $comments->fetch_assoc()) {
        echo '<div id="comment-'.$c['id'].'" class="flex items-start gap-3 ' . ($level > 0 ? 'ml-8' : '') . ' pt-4">';
            echo '<a href="profile.php?id='.$c['user_id'].'" class="flex-shrink-0">';
            if ($c['user_id'] == 9999) {
                echo '<div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold text-sm">AI</div>';
            } else {
                echo '<img src="'.htmlspecialchars($c['avatar_url']).'" alt="'.htmlspecialchars($c['username']).'" class="w-8 h-8 rounded-full object-cover">';
            }
            echo '</a>';
            
            echo '<div class="flex-1">';
                if ($c['user_id'] == 9999) {
                    echo '<div class="p-3 rounded-lg bg-purple-100 dark:bg-[#2a2a40] text-sm text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800">';
                    echo '<strong class="text-purple-600 dark:text-purple-400">NOURA AI</strong><p class="mt-1 leading-relaxed">'.make_clickable_links(nl2br(htmlspecialchars($c['content']))).'</p>';
                    echo '</div>';
                } else {
                    echo '<div class="text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2">';
                    echo '<a href="profile.php?id='.$c['user_id'].'" class="font-semibold text-gray-900 dark:text-white hover:underline">' . htmlspecialchars($c['username']) . '</a>';
                    echo '<p class="text-gray-800 dark:text-gray-200 mt-1 leading-relaxed">' . make_clickable_links(nl2br(htmlspecialchars($c['content']))) . '</p></div>';
                }
                echo '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-4">';
                    echo '<span title="'.date("d M Y, H:i", strtotime($c['created_at'])).'">'.date("d M Y", strtotime($c['created_at'])).'</span>';
                    echo '<form method="post" action="index.php" class="inline-block"><input type="hidden" name="like_comment_id" value="'.$c['id'].'"><input type="hidden" name="post_id_for_redirect" value="'.$post_id.'"><button type="submit" class="font-semibold hover:text-pink-400">Suka ('.$c['like_count'].')</button></form>';
                    echo '<button type="button" onclick="document.getElementById(\'reply-form-'.$c['id'].'\').classList.toggle(\'hidden\')" class="font-semibold">Balas</button>';
                    if ($c['user_id'] == $user_id) {
                        echo '<button type="button" onclick="toggleEditComment('.$c['id'].')" class="font-semibold">Edit</button>';
                    }
                echo '</div>';
                
                echo '<form method="post" action="index.php" id="edit-comment-form-'.$c['id'].'" class="hidden mt-2 space-y-1"><input type="hidden" name="edit_comment_id" value="'.$c['id'].'"><input type="hidden" name="post_id_for_redirect" value="'.$post_id.'"><textarea name="edit_comment_content" rows="2" class="w-full bg-gray-200 dark:bg-[#2c2c2c] rounded-md px-2 py-1 text-sm text-gray-900 dark:text-white">'.htmlspecialchars($c['content']).'</textarea><div class="text-right"><button type="submit" class="bg-yellow-500 text-white text-xs px-3 py-1 rounded-md">Simpan</button></div></form>';
                echo '<form method="post" action="index.php" id="reply-form-'.$c['id'].'" class="hidden mt-2 flex items-center gap-2"><input type="hidden" name="reply_comment_id" value="'.$c['id'].'"><input type="hidden" name="reply_post_id" value="'.$post_id.'"><textarea name="reply_content" rows="1" class="flex-1 w-full bg-gray-200 dark:bg-[#2c2c2c] rounded-md px-2 py-1 text-sm text-gray-900 dark:text-white" placeholder="Balas kepada '.htmlspecialchars($c['username']).'..."></textarea><button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md">Kirim</button></form>';
            echo '</div>';
        echo '</div>';
        render_comments($conn, $post_id, $c['id'], $level + 1);
    }
    $stmt->close();
}
?>

<main id="main-content">
    <div class="p-4 max-w-2xl mx-auto space-y-5">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <article id="post-<?= $row['id'] ?>" class="bg-white dark:bg-[#1e1e1e] rounded-xl shadow-md overflow-hidden transition hover:shadow-lg duration-300">
                <div class="p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <a href="profile.php?id=<?= $row['user_id'] ?>"><img src="<?= htmlspecialchars($row['avatar_url']) ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover"></a>
                        <div class="text-sm">
                            <a href="profile.php?id=<?= $row['user_id'] ?>" class="font-bold text-gray-900 dark:text-white hover:underline"><?= htmlspecialchars($row['username']) ?></a>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= date("d F Y, H:i", strtotime($row['created_at'])) ?></p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="post.php?id=<?= $row['id'] ?>" class="block"><h2 class="text-xl font-bold text-gray-900 dark:text-white hover:text-red-500 transition"><?= htmlspecialchars($row['title'] ?? 'Tanpa Judul') ?></h2></a>
                        <div class="text-base text-gray-700 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed">
                            <?= nl2br(make_clickable_links(htmlspecialchars(mb_strimwidth($row['content'], 0, 400, "...")))) ?>
                            <?php if (mb_strlen($row['content']) > 400): ?>
                                <a href="post.php?id=<?= $row['id'] ?>" class="text-blue-500 text-sm font-semibold hover:underline">Baca selengkapnya</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($row['media_path']): ?>
                            <?php $ext = strtolower(pathinfo($row['media_path'], PATHINFO_EXTENSION)); ?>
                            <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                            <a href="post.php?id=<?= $row['id'] ?>" class="block mt-4"><img src="<?= htmlspecialchars($row['media_path']) ?>" class="w-full rounded-lg"></a>
                            <?php elseif (in_array($ext, ['mp4','webm','ogg'])): ?>
                            <video controls class="w-full rounded-lg mt-4"><source src="<?= htmlspecialchars($row['media_path']) ?>" type="video/<?= $ext ?>"></video>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700/50">
                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                        <div class="flex items-center gap-5">
                            <form method="post" action="index.php" class="inline"><input type="hidden" name="like_post_id" value="<?= $row['id'] ?>"><button type="submit" class="flex items-center gap-1.5 text-sm hover:text-red-500 transition"><i class="fas fa-heart"></i><span><?= $row['like_count'] ?></span></button></form>
                            <a href="post.php?id=<?= $row['id'] ?>#comments" class="flex items-center gap-1.5 text-sm hover:text-blue-500 transition"><i class="fas fa-comment"></i><span><?= $row['comment_count'] ?></span></a>
                        </div>
                        <?php if ($row['user_id'] == $user_id): ?>
                        <div class="flex items-center gap-4">
                            <button type="button" onclick="toggleEditPost(<?= $row['id'] ?>)" class="text-sm hover:text-yellow-500 transition">Edit</button>
                            <form method="post" action="index.php" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus post ini?');"><input type="hidden" name="delete_post_id" value="<?= $row['id'] ?>"><button type="submit" class="text-sm hover:text-red-500 transition">Hapus</button></form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($row['user_id'] == $user_id): ?>
                    <div class="px-5 pb-3">
                        <form method="post" action="index.php" id="edit-post-form-<?= $row['id'] ?>" class="hidden my-4 space-y-2">
                            <input type="hidden" name="edit_post_id" value="<?= $row['id'] ?>">
                            <input type="text" name="edit_title" value="<?= htmlspecialchars($row['title']) ?>" class="w-full bg-gray-200 dark:bg-[#2c2c2c] rounded-md px-3 py-2 text-gray-900 dark:text-white text-sm" required>
                            <textarea name="edit_content" rows="4" class="w-full bg-gray-200 dark:bg-[#2c2c2c] rounded-md px-3 py-2 text-gray-900 dark:text-white text-sm" required><?= htmlspecialchars($row['content']) ?></textarea>
                            <div class="text-right"><button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white text-sm px-4 py-1.5 rounded-md">Simpan</button></div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="px-5 pb-3 border-t border-gray-100 dark:border-gray-700/50">
                    <form method="post" action="index.php" class="flex items-start gap-3 mt-4">
                        <input type="hidden" name="comment_post_id" value="<?= $row['id'] ?>">
                        <textarea name="comment" rows="1" placeholder="Tulis komentar..." class="flex-1 bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"></textarea>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm self-start">Kirim</button>
                    </form>
                    <div class="mt-4 space-y-2">
                        <?php render_comments($conn, $row['id']); ?>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center text-gray-500 dark:text-gray-400 p-10 bg-white dark:bg-[#1e1e1e] rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Selamat Datang di Qurio!</h2>
            <p class="mt-2">Jadilah yang pertama membuat postingan!</p>
        </div>
      <?php endif; ?>
    </div>
</main>

<script>
function toggleEditPost(id) {
    const form = document.getElementById('edit-post-form-' + id);
    form.classList.toggle('hidden');
}
function toggleEditComment(id) {
    const form = document.getElementById('edit-comment-form-' + id);
    form.classList.toggle('hidden');
}
</script>

</body>
</html>