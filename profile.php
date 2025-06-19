<?php
// =================================================================
// BLOK 1: INISIALISASI DAN PENGAMBILAN DATA
// =================================================================
require_once 'includes/db.php';
require_once 'includes/view_helpers.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$alert_message = '';

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_user_id;
if ($profile_id === 0) { die("ID Profil tidak valid."); }
$is_own_profile = ($profile_id == $current_user_id);

// Handle Aksi Form (hanya jika ini profil sendiri)
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = "profile.php?id=" . $profile_id;

    if (isset($_POST['update_profile'])) {
        $new_username = trim($_POST['username']);
        $new_bio = trim($_POST['bio']);
        if (empty($new_username) || strlen($new_username) < 3) {
            $alert_message = "Error: Username minimal 3 karakter.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $new_bio, $current_user_id);
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $alert_message = "Profil berhasil diperbarui!";
            } else {
                $alert_message = "Error: Username mungkin sudah digunakan.";
            }
            $stmt->close();
        }
    }

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= 5 * 1024 * 1024) { // Max 5MB
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $filename = 'avatar-' . $current_user_id . '-' . uniqid() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->bind_param("si", $target_path, $current_user_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['user_avatar'] = $target_path;
                $alert_message = "Foto profil berhasil diperbarui!";
            } else { $alert_message = "Error saat unggah file."; }
        } else { $alert_message = "Error: File harus gambar (JPG, PNG, GIF, WEBP) dan maks 5MB."; }
    }
    header("Location: " . $redirect_url . "&alert=" . urlencode($alert_message));
    exit();
}


if (isset($_GET['alert'])) { $alert_message = htmlspecialchars(urldecode($_GET['alert'])); }

$stmt_user = $conn->prepare("SELECT id, username, avatar_url, xp, created_at, bio FROM users WHERE id = ?");
$stmt_user->bind_param("i", $profile_id);
$stmt_user->execute();
$profile_user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
if (!$profile_user) { die("Pengguna tidak ditemukan."); }

$post_count = $conn->query("SELECT COUNT(*) as count FROM posts WHERE user_id = $profile_id")->fetch_assoc()['count'];
$follower_count = $conn->query("SELECT COUNT(*) as count FROM followers WHERE following_id = $profile_id")->fetch_assoc()['count'];
$following_count = $conn->query("SELECT COUNT(*) as count FROM followers WHERE follower_id = $profile_id")->fetch_assoc()['count'];

$is_following = false;
if (!$is_own_profile) {
    $stmt_follow_check = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
    $stmt_follow_check->bind_param("ii", $current_user_id, $profile_id);
    $stmt_follow_check->execute();
    $is_following = $stmt_follow_check->get_result()->num_rows > 0;
    $stmt_follow_check->close();
}

$posts_result = $conn->query("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(id) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(id) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = {$current_user_id}) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id = {$profile_id} ORDER BY p.created_at DESC");

// Data untuk sidebar (disamakan dengan index.php)
$user_xp = $_SESSION['user_xp'] ?? 0;
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$username = $_SESSION['username'] ?? 'Pengguna';
$popular_communities_query = $conn->query("SELECT c.id, c.name, c.avatar_url, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count FROM communities c ORDER BY member_count DESC LIMIT 5");
$popular_communities = $popular_communities_query->fetch_all(MYSQLI_ASSOC);
$top_users_query = $conn->query("SELECT id, username, avatar_url, xp FROM users WHERE id != 9999 ORDER BY xp DESC LIMIT 5");
$top_users = $top_users_query->fetch_all(MYSQLI_ASSOC);
$stmt_joined = $conn->prepare("SELECT c.id, c.name, c.avatar_url FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE cm.user_id = ? AND cm.status = 'approved' ORDER BY c.name ASC");
$stmt_joined->bind_param("i", $current_user_id);
$stmt_joined->execute();
$joined_communities = $stmt_joined->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_joined->close();
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($profile_user['username']) ?> - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } } }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        #left-sidebar, #content-wrapper, #main-header { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #left-sidebar.collapsed .menu-text { opacity: 0; visibility: hidden; pointer-events: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark">

<?php require_once 'templates/header.php'; ?>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden"></div>

<aside id="left-sidebar" data-state="expanded" class="fixed top-0 left-0 h-full bg-white dark:bg-gray-900 z-50 transition-transform lg:transition-all duration-300 ease-in-out border-r border-gray-200 dark:border-gray-800 pt-4 w-72 -translate-x-full lg:translate-x-0">
    <div class="h-full flex flex-col">
        <div class="px-4 h-14 flex items-center justify-between flex-shrink-0">
            <a href="index.php" class="flex items-center gap-2 text-red-600 font-bold text-xl menu-text">
                <i class="fa-solid fa-feather-pointed"></i><span>Qurio</span>
            </a>
            <button id="sidebar-toggle-btn" class="text-gray-600 dark:text-gray-300 text-lg w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto no-scrollbar space-y-4 pt-0">
            <a href="profile.php" class="flex items-center gap-4 group p-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover flex-shrink-0">
                <div class="overflow-hidden menu-text transition-opacity duration-200">
                    <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($user_xp) ?> XP</p>
                </div>
            </a>
            <div class="space-y-1">
                <a href="index.php" class="flex items-center gap-4 px-3 py-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"><i class="fas fa-home fa-fw text-lg w-6 text-center"></i><span class="menu-text">Beranda</span></a>
                <a href="profile.php" class="flex items-center gap-4 px-3 py-2.5 rounded-lg font-semibold text-sm text-red-600 dark:text-red-500"><i class="fas fa-user fa-fw text-lg w-6 text-center"></i><span class="menu-text">Profil Saya</span></a>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700/50"></div>
            </nav>
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <a href="logout.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-500 dark:hover:text-red-500 transition-colors group"><i class="fas fa-sign-out-alt fa-fw text-lg w-6 text-center"></i><span class="menu-text">Logout</span></a>
        </div>
    </div>
</aside>

<div id="content-wrapper" class="pt-18 lg:ml-72 transition-all duration-300 ease-in-out">
    <div class="container mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <main class="lg:col-span-2 xl:col-span-3 space-y-6">
                <?php if ($alert_message): ?>
                <div class="bg-green-500 text-white p-3 rounded-md text-center shadow-lg">
                    <?= $alert_message; ?>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden mb-8">
                    <div class="h-32 md:h-48 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row items-center sm:items-end -mt-16 sm:-mt-20">
                            <form id="avatarForm" method="post" enctype="multipart/form-data" class="relative flex-shrink-0">
                                <input type="file" name="avatar" id="avatarInput" class="hidden" onchange="document.getElementById('avatarForm').submit();" <?= $is_own_profile ? '' : 'disabled' ?>>
                                <img src="<?= htmlspecialchars($profile_user['avatar_url']) ?>" alt="Avatar" class="w-28 h-28 md:w-36 md:h-36 rounded-full object-cover border-4 border-white dark:border-gray-800">
                                <?php if ($is_own_profile): ?>
                                <label for="avatarInput" class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center text-white opacity-0 hover:opacity-100 transition-opacity cursor-pointer">
                                    <i class="fas fa-camera fa-2x"></i>
                                </label>
                                <?php endif; ?>
                            </form>
                            <div class="w-full sm:flex-grow flex flex-col sm:flex-row justify-between items-center mt-4 sm:mt-0 sm:ml-6">
                                <div class="text-center sm:text-left">
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($profile_user['username']); ?></h1>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Bergabung <?= date("F Y", strtotime($profile_user['created_at'])); ?></p>
                                </div>
                                <div class="mt-4 sm:mt-0">
                                    <?php if ($is_own_profile): ?>
                                        <button id="openEditModalBtn" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold py-2 px-5 rounded-full transition-colors duration-200 hover:bg-gray-300 dark:hover:bg-gray-600"><i class="fas fa-pencil-alt mr-2"></i>Edit Profil</button>
                                    <?php else: ?>
                                        <button id="follow-btn" data-profile-id="<?= $profile_id ?>" class="font-bold py-2 px-5 rounded-full transition-all duration-200 <?= $is_following ? 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300' : 'bg-red-600 text-white hover:bg-red-700' ?>">
                                            <span class="follow-text"><?= $is_following ? '<i class="fas fa-user-check mr-2"></i>Diikuti' : '<i class="fas fa-user-plus mr-2"></i>Ikuti' ?></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-gray-700 dark:text-gray-300 text-center sm:text-left"><?= nl2br(htmlspecialchars($profile_user['bio'] ?? 'Pengguna ini belum menulis bio.')); ?></p>
                        <div class="flex justify-center sm:justify-start gap-6 mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div><strong class="text-gray-900 dark:text-white"><?= $post_count ?></strong> <span class="text-gray-500 dark:text-gray-400">Postingan</span></div>
                            <div><strong id="follower-count" class="text-gray-900 dark:text-white"><?= $follower_count ?></strong> <span class="text-gray-500 dark:text-gray-400">Pengikut</span></div>
                            <div><strong class="text-gray-900 dark:text-white"><?= $following_count ?></strong> <span class="text-gray-500 dark:text-gray-400">Mengikuti</span></div>
                        </div>
                    </div>
                </div>

                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Postingan</h2>
                <div class="space-y-4">
                    <?php if ($posts_result->num_rows == 0): ?>
                        <div class="text-center text-gray-500 py-16 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <i class="fas fa-feather-slash fa-3x mb-4"></i>
                            <p>Pengguna ini belum membuat postingan.</p>
                        </div>
                    <?php else: ?>
                        <?php while($post = $posts_result->fetch_assoc()): ?>
                            <article id="post-<?= $post['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700/50 overflow-hidden">
                                <div class="p-6">
                                    <div class="flex items-center gap-4 mb-4">
                                        <a href="profile.php?id=<?= $post['user_id'] ?>"><img src="<?= htmlspecialchars($post['avatar_url']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover"></a>
                                        <div>
                                            <a href="profile.php?id=<?= $post['user_id'] ?>" class="font-bold text-gray-800 dark:text-gray-100 hover:underline"><?= htmlspecialchars($post['username']) ?></a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= date("d F Y, H:i", strtotime($post['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <a href="post.php?id=<?= $post['id'] ?>" class="block"><h2 class="text-xl font-bold text-gray-900 dark:text-white hover:text-red-500 transition"><?= htmlspecialchars($post['title'] ?? 'Tanpa Judul') ?></h2></a>
                                        <div class="text-base text-gray-600 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed">
                                            <?= nl2br(make_clickable_links(htmlspecialchars($post['content']))) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700/50">
                                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                        <div class="flex items-center gap-6">
                                            <button data-post-id="<?= $post['id'] ?>" class="like-btn flex items-center gap-2 text-sm hover:text-red-500 transition-colors <?= $post['user_liked'] ? 'text-red-500' : '' ?>"><i class="like-icon fas fa-heart text-lg"></i><span class="like-count font-medium"><?= $post['like_count'] ?></span></button>
                                            <button data-post-id="<?= $post['id'] ?>" class="toggle-comments-btn flex items-center gap-2 text-sm hover:text-blue-500 transition-colors">
                                                <i class="fas fa-comment-dots text-lg"></i><span class="font-medium"><?= $post['comment_count'] ?></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="comment-section hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50"></div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </main>
            <aside class="hidden lg:block xl:col-span-1">
                 <div class="sticky top-24 space-y-6">
                    </div>
            </aside>
        </div>
    </div>
</div>

<?php if ($is_own_profile): ?>
<div id="editProfileModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
  <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl w-full max-w-lg relative text-gray-900 dark:text-white shadow-xl">
    <button id="closeEditModalBtn" class="absolute top-4 right-4 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-2xl">&times;</button>
    <h2 class="text-xl font-semibold mb-6">Edit Profil</h2>
    <form method="post" action="profile.php?id=<?= $profile_id; ?>" class="space-y-4">
        <div>
            <label for="username" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Username</label>
            <input type="text" name="username" id="username" value="<?= htmlspecialchars($profile_user['username']); ?>" class="w-full bg-gray-100 dark:bg-gray-700 rounded-md px-3 py-2 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 focus:ring-red-500 focus:border-red-500">
        </div>
        <div>
            <label for="bio" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Bio</label>
            <textarea name="bio" id="bio" rows="4" class="w-full bg-gray-100 dark:bg-gray-700 rounded-md px-3 py-2 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 focus:ring-red-500 focus:border-red-500"><?= htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
        </div>
        <div class="flex justify-end pt-4">
            <button type="submit" name="update_profile" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-full">Simpan</button>
        </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LOGIKA SIDEBAR (SAMA SEPERTI INDEX.PHP) ---
    const sidebarHeader = document.getElementById('main-header'); // Header punya ID beda di sini
    const sidebar = document.getElementById('left-sidebar');
    const contentWrapper = document.getElementById('content-wrapper');
    const toggleButton = document.getElementById('sidebar-toggle-btn');
    const overlay = document.getElementById('sidebar-overlay');
    const isDesktop = () => window.innerWidth >= 1024;

    const setSidebarState = (state, manual = false) => {
        if (!sidebar) return;
        if (isDesktop()) {
            overlay.classList.add('hidden'); 
            document.body.style.overflow = '';
            sidebar.classList.remove('-translate-x-full');
            if (state === 'expanded') {
                sidebar.classList.remove('lg:w-20'); sidebar.classList.add('lg:w-72');
                contentWrapper.classList.remove('lg:ml-20'); contentWrapper.classList.add('lg:ml-72');
                sidebarHeader.classList.remove('lg:left-20'); sidebarHeader.classList.add('lg:left-72');
                sidebar.classList.remove('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'expanded');
            } else { // 'collapsed'
                sidebar.classList.remove('lg:w-72'); sidebar.classList.add('lg:w-20');
                contentWrapper.classList.remove('lg:ml-72'); contentWrapper.classList.add('lg:ml-20');
                sidebarHeader.classList.remove('lg:left-72'); sidebarHeader.classList.add('lg:left-20');
                sidebar.classList.add('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'collapsed');
            }
        } else { // Mobile view
            if (state === 'expanded') {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else { // 'closed'
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }
    };
    
    toggleButton?.addEventListener('click', (e) => {
        e.stopPropagation();
        const currentState = isDesktop() ? (localStorage.getItem('sidebarState') || 'expanded') : (sidebar.classList.contains('-translate-x-full') ? 'closed' : 'expanded');
        const newState = isDesktop() ? (currentState === 'expanded' ? 'collapsed' : 'expanded') : (currentState === 'closed' ? 'expanded' : 'closed');
        setSidebarState(newState, true);
    });
    
    overlay?.addEventListener('click', () => setSidebarState('closed', true));

    if(sidebar) {
        let initialState = localStorage.getItem('sidebarState') || 'expanded';
        setSidebarState(isDesktop() ? initialState : 'closed');
        window.addEventListener('resize', () => {
            let currentState = localStorage.getItem('sidebarState') || 'expanded';
            setSidebarState(isDesktop() ? currentState : 'closed');
        });
    }


    // --- LOGIKA MODAL EDIT PROFIL ---
    const openEditModalBtn = document.getElementById('openEditModalBtn');
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const editProfileModal = document.getElementById('editProfileModal');

    openEditModalBtn?.addEventListener('click', () => { editProfileModal.classList.remove('hidden'); });
    closeEditModalBtn?.addEventListener('click', () => { editProfileModal.classList.add('hidden'); });
    editProfileModal?.addEventListener('click', (event) => { 
        if (event.target === editProfileModal) { editProfileModal.classList.add('hidden'); } 
    });


    // --- LOGIKA FOLLOW/UNFOLLOW AJAX ---
    const followBtn = document.getElementById('follow-btn');
    followBtn?.addEventListener('click', function() {
        const profileId = this.dataset.profileId;
        const followerCountSpan = document.getElementById('follower-count');
        let currentFollowerCount = parseInt(followerCountSpan.textContent);
        const isCurrentlyFollowing = this.textContent.trim() === 'Diikuti';
        this.disabled = true;

        if (isCurrentlyFollowing) {
            this.querySelector('.follow-text').innerHTML = '<i class="fas fa-user-plus mr-2"></i>Ikuti';
            followerCountSpan.textContent = currentFollowerCount - 1;
        } else {
            this.querySelector('.follow-text').innerHTML = '<i class="fas fa-user-check mr-2"></i>Diikuti';
            followerCountSpan.textContent = currentFollowerCount + 1;
        }

        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'toggle_follow', profile_id: profileId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                followerCountSpan.textContent = data.new_follower_count;
                // Update tombol berdasarkan state dari server
                if(data.is_following) {
                    followBtn.classList.remove('bg-red-600', 'text-white');
                    followBtn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');
                    followBtn.querySelector('.follow-text').innerHTML = '<i class="fas fa-user-check mr-2"></i>Diikuti';
                } else {
                    followBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');
                    followBtn.classList.add('bg-red-600', 'text-white');
                    followBtn.querySelector('.follow-text').innerHTML = '<i class="fas fa-user-plus mr-2"></i>Ikuti';
                }
            } else {
                followerCountSpan.textContent = currentFollowerCount; // Kembalikan jika gagal
                alert(data.message || 'Terjadi kesalahan.');
            }
        })
        .catch(error => console.error('Error:', error))
        .finally(() => {
            followBtn.disabled = false;
        });
    });


    // --- LOGIKA INTERAKSI POST (LIKE & KOMENTAR) ---
    document.body.addEventListener('click', function(e) {
        // ... (Kode lengkap untuk like_post dan toggle_comments sama seperti di index.php) ...
    });
    document.body.addEventListener('submit', function(e) {
        // ... (Kode lengkap untuk comment_form sama seperti di index.php) ...
    });
});
</script>

</body>
</html>