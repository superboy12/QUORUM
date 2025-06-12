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
$username = $_SESSION['username'];
$user_avatar = $_SESSION['user_avatar'];
$error = '';

// Handle semua aksi dari form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $redirect_url = strtok($redirect_url, '#');

    // Handle post baru
    if (isset($_POST['new_post'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $community_id = isset($_POST['community_id']) ? (int)$_POST['community_id'] : null;
        if ($community_id === 0) { $community_id = null; }
        
        $media_path = null;
        if (!empty($_FILES['media']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = uniqid() . '-' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES["media"]["name"]));
            $target_file = $target_dir . $filename;
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg','jpeg','png','gif','mp4','webm','ogg'];
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Format file tidak didukung.";
            } else {
                if (!move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
                    $error = "Gagal upload file.";
                } else {
                     $media_path = $target_file;
                }
            }
        }
        if (empty($title)) $error = "Judul harus diisi.";
        elseif (empty($content) && !$media_path) $error = "Isi post atau upload media minimal satu.";

        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, media_path, community_id) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssi", $user_id, $title, $content, $media_path, $community_id);
                $stmt->execute();
                $stmt->close();
                
                $xp_to_add = 5;
                $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
                $stmt_xp->bind_param("ii", $xp_to_add, $user_id);
                $stmt_xp->execute();
                $stmt_xp->close();
                $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + $xp_to_add;
                
                $redirect_page = $community_id ? "community.php?id=$community_id" : "index.php";
                header("Location: " . $redirect_page);
                exit;
            }
        }
    }

    // Handle Komentar & Balasan
    if (isset($_POST['comment_post_id']) || isset($_POST['reply_comment_id'])) {
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : trim($_POST['reply_content']);
        $post_id = isset($_POST['comment_post_id']) ? (int)$_POST['comment_post_id'] : (int)$_POST['reply_post_id'];
        $parent_id = isset($_POST['reply_comment_id']) ? (int)$_POST['reply_comment_id'] : null;

        if (!empty($comment) && $post_id > 0) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $post_id, $user_id, $comment, $parent_id);
            $stmt->execute();
            $new_comment_id = $stmt->insert_id;
            $stmt->close();

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
                function get_ai_prompt($text) { if (preg_match('/@ai\s*\((.*?)\)/i', $text, $matches)) return trim($matches[1]); elseif (preg_match('/@ai\s+(.*)/i', $text, $matches)) return trim($matches[1]); return '';}
                $ai_prompt = get_ai_prompt($comment);
                if (!empty($ai_prompt)) {
                    $ai_reply = get_ai_reply($ai_prompt);
                    $ai_user_id = 9999;
                    $stmt_ai = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                    $stmt_ai->bind_param("iisi", $post_id, $ai_user_id, $ai_reply, $new_comment_id);
                    $stmt_ai->execute();
                    $stmt_ai->close();
                }
            }
            header("Location: " . $redirect_url . "#comment-" . $new_comment_id);
            exit;
        }
    }
    
    // Sisa logika POST lainnya (like comment, delete, edit)
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
    
    if (isset($_POST['delete_post_id'])) {
        $post_id = (int)$_POST['delete_post_id'];
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit;
    }
}

// --- Fungsi-fungsi pembantu ---
function make_clickable_links($text) { return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text); }

// --- Query untuk data Sidebar ---
$popular_communities_query = $conn->query("SELECT c.id, c.name, c.avatar_url, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count FROM communities c ORDER BY member_count DESC LIMIT 5");
$popular_communities = $popular_communities_query->fetch_all(MYSQLI_ASSOC);

$top_users_query = $conn->query("SELECT id, username, avatar_url, xp FROM users WHERE id != 9999 ORDER BY xp DESC LIMIT 5");
$top_users = $top_users_query->fetch_all(MYSQLI_ASSOC);

// Memanggil file header
include 'templates/header.php';

// --- Query Utama untuk Feed ---
$result = $conn->query("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(id) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(id) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = {$user_id}) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.community_id IS NULL ORDER BY p.created_at DESC");

// --- Fungsi Render Komentar ---
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
    <div class="container mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-8">
        
        <aside class="hidden lg:block">
            </aside>

        <div class="md:col-span-2 space-y-5">
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
                                    <a href="<?= htmlspecialchars($row['media_path']) ?>" class="block mt-4" data-lightbox="post-<?= $row['id'] ?>"><img src="<?= htmlspecialchars($row['media_path']) ?>" class="w-full rounded-lg"></a>
                                    <?php elseif (in_array($ext, ['mp4','webm','ogg'])): ?>
                                    <video controls class="w-full rounded-lg mt-4"><source src="<?= htmlspecialchars($row['media_path']) ?>" type="video/<?= $ext ?>"></video>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-5">
                                    <button data-post-id="<?= $row['id'] ?>" class="like-btn flex items-center gap-1.5 text-sm hover:text-red-500 transition <?= $row['user_liked'] ? 'text-red-500' : '' ?>">
                                        <i class="like-icon fas fa-heart"></i>
                                        <span class="like-count"><?= $row['like_count'] ?></span>
                                    </button>
                                    <a href="post.php?id=<?= $row['id'] ?>#comments" class="flex items-center gap-1.5 text-sm hover:text-blue-500 transition"><i class="fas fa-comment-dots"></i><span><?= $row['comment_count'] ?></span></a>
                                </div>
                                <?php if ($row['user_id'] == $user_id): ?>
                                <div class="flex items-center gap-4">
                                    <button type="button" onclick="toggleEditPost(<?= $row['id'] ?>)" class="text-sm hover:text-yellow-500 transition">Edit</button>
                                    <form method="post" action="index.php" class="inline" onsubmit="return confirm('Apakah Anda yakin?');"><input type="hidden" name="delete_post_id" value="<?= $row['id'] ?>"><button type="submit" class="text-sm hover:text-red-500 transition">Hapus</button></form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="px-5 pb-3">
                            <div class="pt-2 border-t border-gray-100 dark:border-gray-700/50">
                                <form method="post" action="index.php" class="flex items-start gap-3 mt-4">
                                    <input type="hidden" name="comment_post_id" value="<?= $row['id'] ?>">
                                    <textarea name="comment" rows="1" placeholder="Tulis komentar..." class="flex-1 bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"></textarea>
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm self-start">Kirim</button>
                                </form>
                                <div class="mt-4 space-y-2">
                                    <?php render_comments($conn, $row['id']); ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center text-gray-500 dark:text-gray-400 p-10 bg-white dark:bg-[#1e1e1e] rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Selamat Datang di Qurio!</h2>
                    <p class="mt-2">Belum ada postingan umum untuk ditampilkan.</p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="hidden md:block">
            <div class="sticky top-24">
                <div class="max-h-[calc(100vh-6.5rem)] overflow-y-auto space-y-6 pr-4">
                    
                    <div class="bg-white dark:bg-[#1e1e1e] rounded-xl shadow-md p-5">
                        <h3 class="font-bold uppercase tracking-wider text-sm mb-4 text-gray-500 dark:text-gray-400">Komunitas Populer</h3>
                        <div class="space-y-4">
                            <?php if(empty($popular_communities)): ?>
                                 <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada komunitas untuk dijelajahi.</p>
                            <?php else: ?>
                                <?php foreach($popular_communities as $p_community): ?>
                                <a href="community.php?id=<?= $p_community['id'] ?>" class="flex items-center gap-3 group">
                                    <img src="<?= htmlspecialchars($p_community['avatar_url']) ?>" alt="<?= htmlspecialchars($p_community['name']) ?>" class="w-10 h-10 rounded-full object-cover">
                                    <div class="text-sm">
                                        <h4 class="font-semibold group-hover:text-red-500 transition text-gray-800 dark:text-gray-100">r/<?= htmlspecialchars($p_community['name']) ?></h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($p_community['member_count']) ?> anggota</p>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="communities.php" class="block mt-4 text-center text-sm text-red-500 font-semibold hover:underline">Lihat Semua Komunitas</a>
                    </div>
                    
                    <div class="bg-white dark:bg-[#1e1e1e] rounded-xl shadow-md p-5">
                        <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white">🏆 Anggota Teraktif</h3>
                        <div class="space-y-4">
                            <?php foreach($top_users as $top_user): ?>
                            <a href="profile.php?id=<?= $top_user['id'] ?>" class="flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($top_user['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <h4 class="font-semibold text-sm group-hover:text-red-500 transition text-gray-800 dark:text-gray-100"><?= htmlspecialchars($top_user['username']) ?></h4>
                                    <p class="text-xs text-yellow-500 dark:text-yellow-400"><?= number_format($top_user['xp']) ?> XP</p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<script>
function toggleEditPost(id) { document.getElementById('edit-post-form-' + id).classList.toggle('hidden'); }
function toggleEditComment(id) { document.getElementById('edit-comment-form-' + id).classList.toggle('hidden'); }
document.addEventListener('DOMContentLoaded', function() {
    // Tombol Buat Postingan di Sidebar kini sudah tidak ada, jadi scriptnya bisa dihapus
    
    document.addEventListener('click', function(e) {
        const likeButton = e.target.closest('.like-btn');
        if (likeButton) {
            e.preventDefault();
            const postId = likeButton.dataset.postId;
            const countSpan = likeButton.querySelector('.like-count');
            const isCurrentlyLiked = likeButton.classList.contains('text-red-500');
            const currentCount = parseInt(countSpan.textContent);
            
            likeButton.classList.toggle('text-red-500');
            countSpan.textContent = isCurrentlyLiked ? currentCount - 1 : currentCount + 1;

            fetch('action_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'like_post', post_id: postId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    likeButton.classList.toggle('text-red-500', isCurrentlyLiked);
                    countSpan.textContent = currentCount;
                } else {
                    countSpan.textContent = data.new_like_count;
                    likeButton.classList.toggle('text-red-500', data.is_liked);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                likeButton.classList.toggle('text-red-500', isCurrentlyLiked);
                countSpan.textContent = currentCount;
            });
        }
    });
});
</script>

</body>
</html>