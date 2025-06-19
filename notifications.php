<?php
// =================================================================
// BLOK 1: INISIALISASI DAN PENGAMBILAN DATA
// =================================================================
require_once 'includes/db.php';
// Kita tidak lagi butuh helper AI atau view di sini, jadi bisa dihapus
// require_once 'includes/ai_functions.php'; 
// require_once 'includes/view_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Mengambil data pengguna dari sesi untuk digunakan di halaman ini
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$user_xp = $_SESSION['user_xp'] ?? 0;

// =================================================================
// LOGIKA KHUSUS HALAMAN NOTIFIKASI
// =================================================================
// 1. Tandai semua notifikasi yang belum dibaca sebagai "sudah dibaca"
$stmt_mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt_mark_read->bind_param("i", $user_id);
$stmt_mark_read->execute();
$stmt_mark_read->close();

// 2. Ambil semua riwayat notifikasi untuk ditampilkan
$stmt_notif = $conn->prepare(
    "SELECT n.*, u.username as actor_username, u.avatar_url as actor_avatar 
     FROM notifications n 
     JOIN users u ON n.actor_id = u.id 
     WHERE n.user_id = ? 
     ORDER BY n.created_at DESC LIMIT 50"
);
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_notif->close();

// 3. Ambil data untuk sidebar (diambil lagi di sini agar sidebar bisa ditampilkan)
$stmt_joined = $conn->prepare("SELECT c.id, c.name, c.avatar_url FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE cm.user_id = ? AND cm.status = 'approved' ORDER BY c.name ASC");
$stmt_joined->bind_param("i", $user_id);
$stmt_joined->execute();
$joined_communities = $stmt_joined->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_joined->close();

// Data untuk header navigasi atas
$unread_count = 0; // Set ke 0 karena halaman ini akan dibaca semua
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notifikasi - Qurio</title>
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
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark text-gray-800 dark:text-gray-200 font-sans">

<header id="main-header" class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md px-4 h-18 flex items-center justify-between border-b border-gray-200 dark:border-gray-800 fixed top-0 right-0 z-30 left-0 lg:left-72">
    <div class="flex-1 min-w-0"></div>
    <div class="flex-1 flex justify-center px-4 sm:px-8">
        <form action="search.php" method="get" class="relative w-full max-w-xl">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" name="q" placeholder="Cari di Qurio..." class="w-full bg-gray-200/70 dark:bg-gray-800 text-gray-900 dark:text-white rounded-full pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 transition-all" />
        </form>
    </div>
    <div class="flex-1 flex items-center justify-end gap-2 sm:gap-3">
        <button id="openPostModalBtn" class="bg-red-600 hover:bg-red-700 text-sm px-4 py-2 rounded-full text-white font-semibold flex items-center gap-2">
            <i class="fas fa-plus"></i><span class="hidden md:inline">Buat</span>
        </button>
        <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            <i id="theme-toggle-icon" class="fas fa-sun text-lg"></i>
        </button>
        <a href="notifications.php" class="text-gray-600 dark:text-gray-300 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors relative">
            <i class="fas fa-bell text-xl"></i>
            <?php if ($unread_count > 0): ?>
                <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span></span>
            <?php endif; ?>
        </a>
        <a href="profile.php?id=<?= htmlspecialchars($user_id); ?>" class="p-0.5 rounded-full hover:ring-2 hover:ring-red-500 transition-all">
            <img src="<?= htmlspecialchars($user_avatar); ?>" class="w-9 h-9 rounded-full object-cover">
        </a>
    </div>
</header>

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
                <a href="index.php" class="flex items-center gap-4 px-3 py-2.5 rounded-lg font-semibold text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
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

<div id="content-wrapper" class="pt-18 lg:ml-72 transition-all duration-300 ease-in-out">
    <main class="container mx-auto max-w-3xl px-4 py-8">
        <div class="pb-4 mb-6 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Notifikasi</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Semua pembaruan terkait aktivitas Anda ada di sini.</p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <?php if (empty($notifications)): ?>
                <div class="p-16 text-center text-gray-400 dark:text-gray-500">
                    <i class="fas fa-bell-slash fa-4x mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-300">Tidak Ada Notifikasi</h3>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                            $link = '#'; $text = ''; $icon = ''; $icon_bg = 'bg-gray-100 dark:bg-gray-700'; $icon_color = 'text-gray-500 dark:text-gray-300';
                            $actor = '<strong class="font-semibold text-gray-900 dark:text-white">' . htmlspecialchars($notif['actor_username']) . '</strong>';
                            
                            switch ($notif['type']) {
                                case 'follow':
                                    $link = 'profile.php?id=' . $notif['actor_id'];
                                    $text = $actor . ' mulai mengikuti Anda.';
                                    $icon = 'fas fa-user-plus'; 
                                    $icon_bg = 'bg-blue-50 dark:bg-blue-900/50'; $icon_color = 'text-blue-500';
                                    break;
                                case 'like_post':
                                    $link = 'post.php?id=' . $notif['target_id'];
                                    $text = $actor . ' menyukai postingan Anda.';
                                    $icon = 'fas fa-heart';
                                    $icon_bg = 'bg-red-50 dark:bg-red-900/50'; $icon_color = 'text-red-500';
                                    break;
                                case 'comment':
                                    $link = 'post.php?id=' . $notif['target_id'] . '#comments';
                                    $text = $actor . ' mengomentari postingan Anda.';
                                    $icon = 'fas fa-comment';
                                    $icon_bg = 'bg-green-50 dark:bg-green-900/50'; $icon_color = 'text-green-500';
                                    break;
                                case 'like_comment':
                                    $stmt_post = $conn->prepare("SELECT post_id FROM comments WHERE id = ?");
                                    $stmt_post->bind_param("i", $notif['target_id']);
                                    $stmt_post->execute();
                                    $post_id_result = $stmt_post->get_result()->fetch_assoc();
                                    $stmt_post->close();
                                    if($post_id_result) {
                                        $link = 'post.php?id=' . $post_id_result['post_id'] . '#comment-' . $notif['target_id'];
                                    }
                                    $text = $actor . ' menyukai komentar Anda.';
                                    $icon = 'fas fa-thumbs-up';
                                    $icon_bg = 'bg-purple-50 dark:bg-purple-900/50'; $icon_color = 'text-purple-500';
                                    break;
                            }
                        ?>
                        <li class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <a href="<?= $link; ?>" class="p-4 flex items-start space-x-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center <?= $icon_bg ?>">
                                    <i class="<?= $icon ?> text-lg <?= $icon_color ?>"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= $text; ?>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        <i class="far fa-clock mr-1"></i>
                                        <?= date("d F Y, H:i", strtotime($notif['created_at'])); ?>
                                    </p>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Kode JS untuk sidebar disalin dari index.php agar berfungsi sama
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
            ? (localStorage.getItem('sidebarState') || 'expanded')
            : (sidebar.classList.contains('-translate-x-full') ? 'closed' : 'expanded');
        const newState = isDesktop() 
            ? (currentState === 'expanded' ? 'collapsed' : 'expanded') 
            : (currentState === 'closed' ? 'expanded' : 'closed');
        setSidebarState(newState, true);
    });
    
    overlay.addEventListener('click', () => setSidebarState('closed', true));

    let initialState = localStorage.getItem('sidebarState') || 'expanded';
    setSidebarState(isDesktop() ? initialState : 'closed');
    window.addEventListener('resize', () => {
        let currentState = localStorage.getItem('sidebarState') || 'expanded';
        setSidebarState(isDesktop() ? currentState : 'closed');
    });
});
</script>

</body>
</html>