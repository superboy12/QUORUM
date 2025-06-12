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

// Ambil ID Komunitas dari URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Halaman tidak ditemukan.");
}
$community_id = (int)$_GET['id'];

// Ambil data detail komunitas, pembuat, dan jumlah anggota
$stmt_comm = $conn->prepare("
    SELECT c.*, u.username as creator_name, 
           (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count 
    FROM communities c 
    JOIN users u ON c.creator_id = u.id 
    WHERE c.id = ?
");
$stmt_comm->bind_param("i", $community_id);
$stmt_comm->execute();
$community = $stmt_comm->get_result()->fetch_assoc();
$stmt_comm->close();

if (!$community) {
    // Jika komunitas tidak ditemukan, tampilkan pesan error yang rapi
    include 'templates/header.php';
    echo '<main id="main-content"><div class="text-center p-10 text-gray-500">Komunitas tidak ditemukan.</div></main>';
    exit();
}

// Cek status keanggotaan user yang sedang login
$membership_status = null;
$is_member = false;
$stmt_member = $conn->prepare("SELECT status FROM community_members WHERE community_id = ? AND user_id = ?");
$stmt_member->bind_param("ii", $community_id, $user_id);
$stmt_member->execute();
$member_result = $stmt_member->get_result();
if ($member_result->num_rows > 0) {
    $membership_status = $member_result->fetch_assoc()['status'];
}
$stmt_member->close();

if ($membership_status === 'approved') {
    $is_member = true;
}

// Tentukan apakah user bisa melihat konten
$can_view_content = ($community['type'] === 'public' || $is_member);

// Ambil postingan HANYA jika user boleh melihat
$posts = [];
if ($can_view_content) {
    $stmt_posts = $conn->prepare("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(*) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.community_id = ? ORDER BY p.created_at DESC");
    $stmt_posts->bind_param("ii", $user_id, $community_id);
    $stmt_posts->execute();
    $posts = $stmt_posts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_posts->close();
}

include 'templates/header.php';
function make_clickable_links($text) { return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text); }
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
            if ($c['user_id'] == 9999) { echo '<div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold text-sm">AI</div>'; } 
            else { echo '<img src="'.htmlspecialchars($c['avatar_url']).'" alt="'.htmlspecialchars($c['username']).'" class="w-8 h-8 rounded-full object-cover">'; }
            echo '</a>';
            echo '<div class="flex-1">';
                if ($c['user_id'] == 9999) {
                    echo '<div class="p-3 rounded-lg bg-purple-100 dark:bg-[#2a2a40] text-sm text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800"><strong class="text-purple-600 dark:text-purple-400">NOURA AI</strong><p class="mt-1 leading-relaxed">'.make_clickable_links(nl2br(htmlspecialchars($c['content']))).'</p></div>';
                } else {
                    echo '<div class="text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2"><a href="profile.php?id='.$c['user_id'].'" class="font-semibold text-gray-900 dark:text-white hover:underline">' . htmlspecialchars($c['username']) . '</a><p class="text-gray-800 dark:text-gray-200 mt-1 leading-relaxed">' . make_clickable_links(nl2br(htmlspecialchars($c['content']))) . '</p></div>';
                }
                echo '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-4">';
                    echo '<span title="'.date("d M Y, H:i", strtotime($c['created_at'])).'">'.date("d M Y", strtotime($c['created_at'])).'</span>';
                    echo '<form method="post" action="index.php" class="inline-block"><input type="hidden" name="like_comment_id" value="'.$c['id'].'"><input type="hidden" name="post_id_for_redirect" value="'.$post_id.'"><button type="submit" class="font-semibold hover:text-pink-400">Suka ('.$c['like_count'].')</button></form>';
                    echo '<button type="button" onclick="document.getElementById(\'reply-form-'.$c['id'].'\').classList.toggle(\'hidden\')" class="font-semibold">Balas</button>';
                    if ($c['user_id'] == $user_id) { echo '<button type="button" onclick="toggleEditComment('.$c['id'].')" class="font-semibold">Edit</button>'; }
                echo '</div>';
                echo '<form method="post" action="index.php" id="edit-comment-form-'.$c['id'].'" class="hidden mt-2"> ... </form>';
                echo '<form method="post" action="index.php" id="reply-form-'.$c['id'].'" class="hidden mt-2"> ... </form>';
            echo '</div>';
        echo '</div>';
        render_comments($conn, $post_id, $c['id'], $level + 1);
    }
    $stmt->close();
}
?>

<main id="main-content">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white dark:bg-[#1e1e1e] shadow-md rounded-b-lg">
            <div class="h-48 bg-cover bg-center" style="background-image: url('https://source.unsplash.com/1600x900/?nature,technology,community');"></div>
            <div class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:gap-4">
                    <img src="<?= htmlspecialchars($community['avatar_url']) ?>" class="w-24 h-24 md:w-32 md:h-32 rounded-xl object-cover border-4 border-white dark:border-[#1e1e1e] -mt-16 flex-shrink-0">
                    <div class="flex-grow mt-4 sm:mt-0">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($community['name']) ?></h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Dibuat oleh <?= htmlspecialchars($community['creator_name']) ?></p>
                    </div>
                    <div class="flex-shrink-0 mt-4 sm:mt-0 flex items-center gap-2">
                        <form action="community_handler.php" method="POST">
                            <input type="hidden" name="community_id" value="<?= $community['id'] ?>">
                            <?php if ($membership_status === null): ?>
                                <input type="hidden" name="action" value="join_community">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-full transition"><i class="fas fa-plus mr-2"></i><?= $community['type'] === 'public' ? 'Gabung' : 'Minta Bergabung' ?></button>
                            <?php elseif ($membership_status === 'pending'): ?>
                                <button class="bg-gray-500 text-white font-bold py-2 px-4 rounded-full cursor-not-allowed" disabled>Diminta</button>
                            <?php elseif ($membership_status === 'approved'): ?>
                                <input type="hidden" name="action" value="leave_community">
                                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-full transition"><i class="fas fa-check mr-2"></i>Bergabung</button>
                            <?php endif; ?>
                        </form>
                         <?php if ($user_id === (int)$community['creator_id']): ?>
                            <form action="community_handler.php" method="POST" onsubmit="return confirm('ANDA YAKIN? Menghapus komunitas akan menghilangkan semua postingan di dalamnya secara permanen!')">
                                <input type="hidden" name="community_id" value="<?= $community['id'] ?>">
                                <input type="hidden" name="action" value="delete_community">
                                <button type="submit" class="bg-red-800 hover:bg-red-900 text-white p-2 w-10 h-10 flex items-center justify-center rounded-full transition" title="Hapus Komunitas"><i class="fas fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="mt-4 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($community['description']) ?></p>
                <div class="mt-4 text-sm text-gray-500 dark:text-gray-400"><i class="fas fa-users mr-2"></i><?= number_format($community['member_count']) ?> anggota • Tipe: <span class="font-semibold"><?= ucfirst($community['type']) ?></span></div>
            </div>
        </div>

        <div class="mt-6 px-4 sm:px-0">
            <?php if ($can_view_content): ?>
                <?php if ($is_member): ?>
                    <div class="bg-white dark:bg-[#1e1e1e] p-4 rounded-lg shadow-md mb-5">
                        <div class="flex items-center gap-3">
                            <img src="<?= $_SESSION['user_avatar'] ?>" class="w-10 h-10 rounded-full object-cover">
                            <button id="communityPostBtn" class="w-full text-left bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 p-3 rounded-lg text-gray-500 dark:text-gray-400">Buat postingan di <?= htmlspecialchars($community['name']) ?>...</button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-5">
                    <?php if (empty($posts)): ?>
                        <div class="text-center text-gray-500 py-10 bg-white dark:bg-[#1e1e1e] rounded-lg shadow-md"><p>Belum ada postingan di komunitas ini.</p></div>
                    <?php else: ?>
                        <?php foreach($posts as $row): ?>
                            <article id="post-<?= $row['id'] ?>" class="bg-white dark:bg-[#1e1e1e] rounded-xl shadow-md overflow-hidden">
                                <div class="p-5"></div>
                                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700/50"></div>
                                <div class="px-5 pb-3"></div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 py-20 bg-white dark:bg-[#1e1e1e] rounded-lg shadow-md">
                    <i class="fas fa-lock fa-3x mb-4"></i>
                    <h2 class="text-xl font-bold">Komunitas ini Privat</h2>
                    <p class="mt-2">Minta untuk bergabung agar bisa melihat dan membuat postingan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('communityPostBtn')?.addEventListener('click', () => {
        const postModal = document.getElementById('postModal');
        const form = document.getElementById('postForm');
        let communityInput = form.querySelector('input[name="community_id"]');
        if (!communityInput) {
            communityInput = document.createElement('input');
            communityInput.type = 'hidden';
            communityInput.name = 'community_id';
            form.appendChild(communityInput);
        }
        communityInput.value = '<?= $community_id ?>';
        postModal.classList.remove('hidden');
    });
});
</script>

</body>
</html>