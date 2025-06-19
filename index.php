<?php
// =================================================================
// BLOK 1: INISIALISASI, HANDLER, DAN PENGAMBILAN DATA
// =================================================================

// Memuat file-file konfigurasi dan fungsionalitas inti
require_once 'includes/db.php';
require_once 'post_handler.php'; // Handle form post SEBELUM output HTML apapun
require_once 'includes/ai_functions.php'; 
require_once 'includes/view_helpers.php';

// Memulai sesi untuk manajemen login pengguna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Halaman ini wajib login, redirect ke halaman login jika sesi tidak ada
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Mengambil data pengguna dari sesi untuk digunakan di konten halaman ini
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$user_xp = $_SESSION['user_xp'] ?? 0;

// Mengambil data untuk widget di sidebar kanan (Komunitas Populer & Anggota Teraktif)
$popular_communities_query = $conn->query("SELECT c.id, c.name, c.avatar_url, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count FROM communities c ORDER BY member_count DESC LIMIT 5");
$popular_communities = $popular_communities_query->fetch_all(MYSQLI_ASSOC);

$top_users_query = $conn->query("SELECT id, username, avatar_url, xp FROM users WHERE id != 9999 ORDER BY xp DESC LIMIT 5");
$top_users = $top_users_query->fetch_all(MYSQLI_ASSOC);

// Mengambil data untuk sidebar kiri (daftar komunitas yang diikuti pengguna)
$stmt_joined = $conn->prepare("SELECT c.id, c.name, c.avatar_url FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE cm.user_id = ? AND cm.status = 'approved' ORDER BY c.name ASC");
$stmt_joined->bind_param("i", $user_id);
$stmt_joined->execute();
$joined_communities = $stmt_joined->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_joined->close();

// Mengambil data konten utama halaman: daftar postingan di feed utama
$result = $conn->query("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(id) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(id) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = {$user_id}) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.community_id IS NULL ORDER BY p.created_at DESC");

?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Qurio - Beranda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    spacing: { '18': '4.5rem', '20': '5rem', '72': '18rem' },
                    colors: {
                        gray: { 900: '#111827', 800: '#1f2937', 700: '#374151', 200: '#e5e7eb', 100: '#f3f4f6' },
                        dark: '#0c0c0c'
                    },
                    transitionProperty: { 'width': 'width', 'margin': 'margin', 'left': 'left' }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #left-sidebar, #content-wrapper, #main-header { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #left-sidebar.collapsed .menu-text { opacity: 0; visibility: hidden; pointer-events: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark text-gray-800 dark:text-gray-200 font-sans">

<?php 
// Memuat seluruh komponen header (navigasi atas, modal postingan, notifikasi, dll)
require_once 'templates/header.php'; 
?>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden"></div>

<aside id="left-sidebar" data-state="expanded" class="fixed top-0 left-0 h-full bg-white dark:bg-gray-900 z-50 transition-transform lg:transition-all duration-300 ease-in-out border-r border-gray-200 dark:border-gray-800 pt-4 w-72 -translate-x-full lg:translate-x-0">
    <div class="h-full flex flex-col">
        <div class="px-4 h-14 flex items-center justify-between flex-shrink-0">
            <a href="index.php" class="flex items-center gap-2 text-red-600 font-bold text-xl menu-text">
                <i class="fa-solid fa-feather-pointed"></i>
                <span>Qurio</span>
            </a>
            <button id="sidebar-toggle-btn" class="text-gray-600 dark:text-gray-300 text-lg w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto no-scrollbar space-y-4 pt-0">
            <a href="profile.php?id=<?= htmlspecialchars($user_id) ?>" class="flex items-center gap-4 group p-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover flex-shrink-0">
                <div class="overflow-hidden menu-text transition-opacity duration-200">
                    <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($user_xp) ?> XP</p>
                </div>
            </a>
            <div class="space-y-1">
                <a href="index.php" class="flex items-center gap-4 px-3 py-2.5 rounded-lg font-semibold text-sm text-red-600 dark:text-red-500">
                    <i class="fas fa-home fa-fw text-lg w-6 text-center"></i><span class="menu-text">Beranda</span>
                </a>
                <a href="profile.php?id=<?= htmlspecialchars($user_id) ?>" class="flex items-center gap-4 px-3 py-2.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors text-sm font-medium">
                    <i class="fas fa-user fa-fw text-lg w-6 text-center"></i><span class="menu-text">Profil Saya</span>
                </a>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700/50"></div>
            <div>
                <h3 class="px-3 mb-2 mt-2 text-xs font-semibold text-gray-400 uppercase tracking-wider menu-text">Komunitas</h3>
                <ul class="space-y-1">
                    <li><a href="create_community.php" class="flex items-center gap-4 px-3 py-2 text-sm rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"><i class="fas fa-plus-circle fa-fw text-lg w-6 text-center"></i><span class="menu-text">Buat Komunitas</span></a></li>
                    <li><a href="communities.php" class="flex items-center gap-4 px-3 py-2 text-sm rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"><i class="fas fa-compass fa-fw text-lg w-6 text-center"></i><span class="menu-text">Jelajahi</span></a></li>
                </ul>
                <ul class="space-y-1 mt-2">
                    <?php if (empty($joined_communities)): ?>
                        <li class="px-3 py-2 text-sm text-gray-500 menu-text">Anda belum bergabung.</li>
                    <?php endif; ?>
                    <?php foreach($joined_communities as $community): ?>
                    <li><a href="community.php?id=<?= $community['id'] ?>" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                        <img src="<?= htmlspecialchars($community['avatar_url']) ?>" class="w-6 h-6 rounded-md object-cover flex-shrink-0">
                        <span class="font-medium truncate menu-text"><?= htmlspecialchars($community['name']) ?></span>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <a href="logout.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-500 dark:hover:text-red-500 transition-colors group">
                <i class="fas fa-sign-out-alt fa-fw text-lg group-hover:text-red-500 transition-colors w-6 text-center"></i><span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
</aside>

<div id="content-wrapper" class="pt-18 lg:ml-72">
    <div class="container mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <main class="lg:col-span-2 xl:col-span-3 space-y-6">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <article id="post-<?= $row['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700/50 overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center gap-4 mb-4">
                                    <a href="profile.php?id=<?= $row['user_id'] ?>"><img src="<?= htmlspecialchars($row['avatar_url']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover"></a>
                                    <div>
                                        <a href="profile.php?id=<?= $row['user_id'] ?>" class="font-bold text-gray-800 dark:text-gray-100 hover:underline"><?= htmlspecialchars($row['username']) ?></a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= date("d F Y, H:i", strtotime($row['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <a href="post.php?id=<?= $row['id'] ?>" class="block"><h2 class="text-xl font-bold text-gray-900 dark:text-white hover:text-red-500 transition"><?= htmlspecialchars($row['title'] ?? 'Tanpa Judul') ?></h2></a>
                                    <div class="text-base text-gray-600 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed">
                                        <?= nl2br(make_clickable_links(htmlspecialchars($row['content']))) ?>
                                    </div>
                                    <?php if ($row['media_path']): ?>
                                        <div class="mt-4">
                                            <?php $ext = strtolower(pathinfo($row['media_path'], PATHINFO_EXTENSION)); ?>
                                            <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                                <a href="<?= htmlspecialchars($row['media_path']) ?>" target="_blank"><img src="<?= htmlspecialchars($row['media_path']) ?>" class="max-w-full h-auto rounded-lg"></a>
                                            <?php elseif (in_array($ext, ['mp4','webm'])): ?>
                                                <video controls class="w-full rounded-lg"><source src="<?= htmlspecialchars($row['media_path']) ?>" type="video/<?= $ext ?>"></video>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700/50">
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-6">
                                        <button data-post-id="<?= $row['id'] ?>" class="like-btn flex items-center gap-2 text-sm hover:text-red-500 transition-colors <?= $row['user_liked'] ? 'text-red-500' : '' ?>"><i class="like-icon fas fa-heart text-lg"></i><span class="like-count font-medium"><?= $row['like_count'] ?></span></button>
                                        <button data-post-id="<?= $row['id'] ?>" class="toggle-comments-btn flex items-center gap-2 text-sm hover:text-blue-500 transition-colors">
                                            <i class="fas fa-comment-dots text-lg"></i><span class="font-medium"><?= $row['comment_count'] ?></span>
                                        </button>
                                    </div>
                                    <?php if ($row['user_id'] == $user_id): ?>
                                    <div class="flex items-center gap-4">
                                        <a href="edit_post.php?id=<?= $row['id'] ?>" class="text-sm hover:text-yellow-500 transition-colors">Edit</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-section hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                                    <form class="comment-form flex items-start gap-3">
                                        <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-8 h-8 rounded-full object-cover">
                                        <input type="hidden" name="post_id" value="<?= $row['id'] ?>">
                                        <textarea name="comment_text" rows="1" placeholder="Tulis komentar..." class="comment-input flex-1 bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"></textarea>
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm self-start">Kirim</button>
                                    </form>
                                    <div class="comment-list mt-2" data-post-id="<?= $row['id'] ?>">
                                        </div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-gray-500 p-10 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Selamat Datang di Qurio!</h2>
                        <p class="mt-2">Jadilah yang pertama membuat postingan.</p>
                    </div>
                <?php endif; ?>
            </main>

            <aside class="hidden lg:block xl:col-span-1">
                <div class="sticky top-24 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-sm mb-4 text-gray-600 dark:text-gray-400 uppercase tracking-wider">Komunitas Populer</h3>
                        <div class="space-y-4">
                            <?php foreach($popular_communities as $p_community): ?>
                            <a href="community.php?id=<?= $p_community['id'] ?>" class="flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($p_community['avatar_url']) ?>" alt="<?= htmlspecialchars($p_community['name']) ?>" class="w-10 h-10 rounded-lg object-cover">
                                <div>
                                    <h4 class="font-semibold group-hover:text-red-500 transition text-gray-800 dark:text-gray-100 text-sm">r/<?= htmlspecialchars($p_community['name']) ?></h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($p_community['member_count']) ?> anggota</p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-base mb-4 text-gray-800 dark:text-gray-100">🏆 Anggota Teraktif</h3>
                        <div class="space-y-4">
                            <?php foreach($top_users as $top_user): ?>
                            <a href="profile.php?id=<?= $top_user['id'] ?>" class="flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($top_user['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <h4 class="font-semibold text-sm group-hover:text-red-500 transition text-gray-800 dark:text-gray-100"><?= htmlspecialchars($top_user['username']) ?></h4>
                                    <p class="text-xs text-yellow-500 dark:text-yellow-400 font-semibold"><?= number_format($top_user['xp']) ?> XP</p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Logika untuk Sidebar Toggle ---
    const header = document.getElementById('main-header');
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
                header.classList.remove('lg:left-20'); header.classList.add('lg:left-72');
                sidebar.classList.remove('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'expanded');
            } else { // 'collapsed'
                sidebar.classList.remove('lg:w-72'); sidebar.classList.add('lg:w-20');
                contentWrapper.classList.remove('lg:ml-72'); contentWrapper.classList.add('lg:ml-20');
                header.classList.remove('lg:left-72'); header.classList.add('lg:left-20');
                sidebar.classList.add('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'collapsed');
            }
        } else { // Mobile view
            header.classList.remove('lg:left-72', 'lg:left-20');
            contentWrapper.classList.remove('lg:ml-72', 'lg:ml-20');
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
    
    toggleButton.addEventListener('click', (e) => {
        e.stopPropagation();
        const currentState = isDesktop() 
            ? (sidebar.classList.contains('lg:w-72') ? 'expanded' : 'collapsed')
            : (sidebar.classList.contains('-translate-x-full') ? 'closed' : 'expanded');
        const newState = isDesktop() ? (currentState === 'expanded' ? 'collapsed' : 'expanded') : (currentState === 'closed' ? 'expanded' : 'closed');
        setSidebarState(newState, true);
    });
    
    overlay.addEventListener('click', () => setSidebarState('closed'));
    let initialState = localStorage.getItem('sidebarState') || 'expanded';
    setSidebarState(isDesktop() ? initialState : 'closed');
    window.addEventListener('resize', () => {
        let currentState = localStorage.getItem('sidebarState') || 'expanded';
        setSidebarState(isDesktop() ? currentState : 'closed');
    });

    // --- Event Listener Global untuk Interaksi Konten ---
    document.body.addEventListener('click', function(e) {
        
        // Handler untuk Suka Postingan
        const likeButton = e.target.closest('.like-btn');
        if (likeButton) {
            e.preventDefault();
            const postId = likeButton.dataset.postId;
            const countSpan = likeButton.querySelector('.like-count');
            const isCurrentlyLiked = likeButton.classList.contains('text-red-500');
            const currentCount = parseInt(countSpan.textContent);
            
            likeButton.classList.toggle('text-red-500');
            countSpan.textContent = isCurrentlyLiked ? currentCount - 1 : currentCount + 1;
            
            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'like_post', post_id: postId }) })
            .then(response => response.json()).then(data => {
                if (data.status !== 'success') {
                    likeButton.classList.toggle('text-red-500', isCurrentlyLiked); 
                    countSpan.textContent = currentCount;
                } else {
                    countSpan.textContent = data.new_like_count; 
                    likeButton.classList.toggle('text-red-500', data.is_liked);
                }
            }).catch(error => { 
                console.error('Error:', error); 
                likeButton.classList.toggle('text-red-500', isCurrentlyLiked); 
                countSpan.textContent = currentCount; 
            });
        }
        
        // Handler untuk Suka Komentar
        const commentLikeButton = e.target.closest('.comment-like-btn');
        if (commentLikeButton) {
            e.preventDefault();
            const commentId = commentLikeButton.dataset.commentId;
            const countSpan = commentLikeButton.querySelector('.comment-like-count');
            const isCurrentlyLiked = commentLikeButton.classList.contains('text-pink-500');
            const currentCount = parseInt(countSpan.textContent.match(/\d+/)[0]);
            
            commentLikeButton.classList.toggle('text-pink-500');
            countSpan.textContent = isCurrentlyLiked ? currentCount - 1 : currentCount + 1;
            
            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'like_comment', comment_id: commentId }) })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success'){
                    countSpan.textContent = data.new_like_count;
                    commentLikeButton.classList.toggle('text-pink-500', data.is_liked);
                } else {
                    commentLikeButton.classList.toggle('text-pink-500', isCurrentlyLiked);
                    countSpan.textContent = currentCount;
                }
            }).catch(error => { console.error('Error:', error); });
        }
        
        // Handler untuk menampilkan/menyembunyikan komentar
        const toggleCommentsBtn = e.target.closest('.toggle-comments-btn');
        if (toggleCommentsBtn) {
            const postId = toggleCommentsBtn.dataset.postId;
            const commentSection = toggleCommentsBtn.closest('article').querySelector('.comment-section');
            const commentList = commentSection.querySelector('.comment-list');
            const isHidden = commentSection.classList.toggle('hidden');

            if (!isHidden && !commentList.hasAttribute('data-loaded')) {
                commentList.innerHTML = `<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>`;
                fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'get_comments', post_id: postId }) })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        commentList.innerHTML = data.html;
                        commentList.setAttribute('data-loaded', 'true');
                    } else {
                        commentList.innerHTML = `<p class="text-xs text-red-500">Gagal memuat komentar.</p>`;
                    }
                });
            }
        }
        
        // Handler untuk tombol Balas
        const replyButton = e.target.closest('.reply-btn');
        if (replyButton) {
            const formId = replyButton.dataset.targetForm;
            const form = document.getElementById(formId);
            if (form) { 
                form.classList.toggle('hidden'); 
                if (!form.classList.contains('hidden')) form.querySelector('textarea').focus(); 
            }
        }

        // Handler untuk tombol Edit Komentar
        const editBtn = e.target.closest('.edit-comment-btn');
        if (editBtn) {
            const commentItem = editBtn.closest('.comment-item');
            commentItem.querySelector('.comment-content').classList.add('hidden');
            commentItem.querySelector('.comment-actions').classList.add('hidden');
            commentItem.querySelector('.edit-comment-form').classList.remove('hidden');
        }
        
        // Handler untuk tombol Batal Edit
        const cancelEditBtn = e.target.closest('.cancel-edit-btn');
        if (cancelEditBtn) {
            const commentItem = cancelEditBtn.closest('.comment-item');
            commentItem.querySelector('.comment-content').classList.remove('hidden');
            commentItem.querySelector('.comment-actions').classList.remove('hidden');
            commentItem.querySelector('.edit-comment-form').classList.add('hidden');
        }

        // Handler untuk Hapus Komentar
        const deleteBtn = e.target.closest('.delete-comment-btn');
        if (deleteBtn) {
            if (confirm('Apakah Anda yakin ingin menghapus komentar ini?')) {
                const commentId = deleteBtn.dataset.commentId;
                fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'delete_comment', comment_id: commentId }) })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const commentDiv = document.getElementById('comment-' + commentId);
                        if (commentDiv) {
                            commentDiv.style.transition = 'opacity 0.3s ease';
                            commentDiv.style.opacity = '0';
                            setTimeout(() => commentDiv.remove(), 300);
                        }
                    } else { alert(data.message || 'Gagal menghapus komentar.'); }
                });
            }
        }
    });

    // --- Event Listener Global untuk Submit Form ---
    document.body.addEventListener('submit', function(e) {
        
        // Handler untuk Submit Komentar Baru / Balasan
        if (e.target.matches('.comment-form')) {
            e.preventDefault();
            const form = e.target;
            const postId = form.querySelector('input[name="post_id"]').value;
            const commentTextarea = form.querySelector('textarea[name="comment_text"]');
            const commentText = commentTextarea.value.trim();
            const parentIdInput = form.querySelector('input[name="parent_id"]');
            const parentId = parentIdInput ? parentIdInput.value : null;
            if (commentText === '') return;

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true; 
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'add_comment', post_id: postId, comment_text: commentText, parent_id: parentId }) })
            .then(response => { if (!response.ok) { throw new Error('Network response was not ok'); } return response.json(); })
            .then(data => {
                if (data.status === 'success') {
                    const userComment = data.comment;
                    const newCommentHtml = `<div id="comment-${userComment.id}" class="comment-item flex items-start gap-3 ${parentId ? 'ml-10' : ''} pt-4" style="opacity:0; transform: translateY(-10px); animation: fadeIn 0.5s forwards;"><div class="flex-shrink-0"><a href="profile.php?id=${userComment.user_id}"><img src="${userComment.avatar_url}" alt="${userComment.username}" class="w-8 h-8 rounded-full object-cover"></a></div><div class="flex-1"><div class="comment-content text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2"><a href="profile.php?id=${userComment.user_id}" class="font-semibold text-gray-900 dark:text-white hover:underline">${userComment.username}</a><p class="comment-text-display text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">${userComment.content_formatted}</p></div><form class="edit-comment-form hidden mt-2 space-y-2"><input type="hidden" name="comment_id" value="${userComment.id}"><textarea name="edit_content" rows="2" class="w-full bg-gray-200 dark:bg-gray-700 rounded-md p-2 text-sm">${userComment.content}</textarea><div class="flex items-center gap-2"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded-md font-semibold">Simpan</button><button type="button" class="cancel-edit-btn text-xs text-gray-500 hover:underline">Batal</button></div></form><div class="comment-actions text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-4"><span title="${new Date(userComment.created_at).toLocaleString()}">Baru saja</span><button type="button" data-comment-id="${userComment.id}" class="font-semibold hover:text-pink-400 transition comment-like-btn">Suka (<span>0</span>)</button><button type="button" data-target-form="reply-form-${userComment.id}" class="font-semibold reply-btn">Balas</button><button type="button" class="font-semibold edit-comment-btn">Edit</button><button type="button" class="font-semibold delete-comment-btn" data-comment-id="${userComment.id}">Hapus</button></div><form class="comment-form hidden mt-2 flex items-center gap-2" id="reply-form-${userComment.id}"><input type="hidden" name="post_id" value="${postId}"><input type="hidden" name="parent_id" value="${userComment.id}"><textarea name="comment_text" rows="1" class="comment-input flex-1 w-full bg-gray-200 dark:bg-gray-700 rounded-lg px-3 py-1.5 text-sm" placeholder="Balas kepada ${userComment.username}..."></textarea><button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md font-semibold">Kirim</button></form></div></div>`;
                    
                    const targetList = parentId ? document.getElementById(`comment-${parentId}`).querySelector('.flex-1') : form.closest('.comment-section').querySelector('.comment-list');
                    if (parentId) {
                        targetList.insertAdjacentHTML('beforeend', newCommentHtml);
                    } else {
                        targetList.insertAdjacentHTML('afterbegin', newCommentHtml);
                    }
                    form.reset();
                    commentTextarea.style.height = 'auto';

                    if (data.ai_reply) {
                        const aiReply = data.ai_reply;
                        const aiCommentHtml = `<div id="comment-${aiReply.id}" class="comment-item flex items-start gap-3 ml-10 pt-4" style="opacity:0; transform: translateY(-10px); animation: fadeIn 1s forwards .5s;"><div class="flex-shrink-0"><div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-lg">AI</div></div><div class="flex-1"><div class="comment-content text-sm bg-purple-50 dark:bg-gray-800 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50"><div class="flex items-center gap-2 mb-2"><strong class="font-bold text-purple-600 dark:text-purple-400">NOURA</strong><i class="fas fa-check-circle text-purple-500" title="Verified AI Assistant"></i></div><p class="text-gray-700 dark:text-gray-300 leading-relaxed">${aiReply.content_formatted}</p></div></div></div>`;
                        const userCommentDiv = document.getElementById(`comment-${userComment.id}`);
                        if(userCommentDiv) { userCommentDiv.insertAdjacentHTML('afterend', aiCommentHtml); }
                    }
                } else { alert('Gagal: ' + (data.message || 'Terjadi kesalahan.')); }
            })
            .catch(error => { console.error('Error:', error); alert('Terjadi kesalahan koneksi.'); })
            .finally(() => { submitButton.disabled = false; submitButton.textContent = 'Kirim'; });
        }

        // Handler untuk Submit Edit Komentar
        if (e.target.matches('.edit-comment-form')) {
            e.preventDefault();
            const form = e.target;
            const commentId = form.querySelector('input[name="comment_id"]').value;
            const content = form.querySelector('textarea[name="edit_content"]').value;
            
            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'edit_comment', comment_id: commentId, content: content }) })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const commentItem = form.closest('.comment-item');
                    commentItem.querySelector('.comment-text-display').innerHTML = data.new_html;
                    commentItem.querySelector('.comment-content').classList.remove('hidden');
                    commentItem.querySelector('.comment-actions').classList.remove('hidden');
                    form.classList.add('hidden');
                } else {
                    alert(data.message || 'Gagal menyimpan perubahan.');
                }
            });
        }
    });
});
</script>

</body>
</html>