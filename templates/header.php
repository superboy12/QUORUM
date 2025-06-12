<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) { // Mencegah error jika file di-include berkali-kali
    require_once 'includes/db.php';
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Pengguna';
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$user_xp = $_SESSION['user_xp'] ?? 0;

// Variabel default
$following_count = 0;
$follower_count = 0;
$post_count = 0;
$notifications = [];
$unread_count = 0;

// Ambil semua data dinamis jika user sudah login
if ($user_id) {
    // Ambil jumlah Following (yang Anda ikuti) - Disederhanakan
    $stmt_following = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE follower_id = ?");
    $stmt_following->bind_param("i", $user_id);
    $stmt_following->execute();
    $following_count = $stmt_following->get_result()->fetch_assoc()['count'];
    $stmt_following->close();

    // Ambil jumlah Followers (yang mengikuti Anda) - Disederhanakan
    $stmt_followers = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
    $stmt_followers->bind_param("i", $user_id);
    $stmt_followers->execute();
    $follower_count = $stmt_followers->get_result()->fetch_assoc()['count'];
    $stmt_followers->close();
    
    // Ambil jumlah Postingan
    $stmt_posts = $conn->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = ?");
    $stmt_posts->bind_param("i", $user_id);
    $stmt_posts->execute();
    $post_count = $stmt_posts->get_result()->fetch_assoc()['count'];
    $stmt_posts->close();
    
    // Ambil semua notifikasi untuk user ini - Disederhanakan
    $stmt_notif = $conn->prepare(
        "SELECT n.*, u.username as actor_username, u.avatar_url as actor_avatar 
         FROM notifications n 
         JOIN users u ON n.actor_id = u.id 
         WHERE n.user_id = ? 
         ORDER BY n.created_at DESC LIMIT 20"
    );
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_notif->close();
    
    // Hitung notifikasi yang belum dibaca
    foreach ($notifications as $notif) {
        if ($notif['is_read'] == 0) {
            $unread_count++;
        }
    }
}

// Logika progress bar XP
$xp_current_level = $user_xp > 0 ? $user_xp % 1000 : 0;
$xp_percentage = $user_xp > 0 ? ($xp_current_level / 1000) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="stylesheet" href="style.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white font-sans transition-colors duration-300">

<div id="menuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>
<aside id="sideMenu" class="fixed top-0 left-0 h-full w-64 bg-white dark:bg-[#1e1e1e] border-r border-gray-200 dark:border-gray-700 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex justify-between items-center">
            <div class="text-red-600 font-bold text-xl">Qurio Menu</div>
            <button id="closeMenuBtn" class="text-gray-500 dark:text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <a href="profile.php?id=<?php echo htmlspecialchars($user_id); ?>" class="flex flex-col items-center text-center group">
            <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-gray-300 dark:border-gray-600 group-hover:border-red-500 transition">
            <p class="font-bold text-lg mt-3 text-gray-900 dark:text-white"><?php echo htmlspecialchars($username); ?></p>
        </a>
        <div class="mt-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">XP Progress</p>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5"><div class="bg-yellow-400 h-2.5 rounded-full" style="width: <?php echo $xp_percentage; ?>%"></div></div>
            <p class="text-xs text-right text-yellow-500 dark:text-yellow-400 mt-1"><?php echo number_format($user_xp); ?> XP</p>
        </div>
        <div class="flex justify-around mt-4 text-center">
            <div><p class="font-bold text-lg text-gray-900 dark:text-white"><?php echo $post_count; ?></p><p class="text-xs text-gray-500 dark:text-gray-400">Postingan</p></div>
            <div><p class="font-bold text-lg text-gray-900 dark:text-white"><?php echo $follower_count; ?></p><p class="text-xs text-gray-500 dark:text-gray-400">Followers</p></div>
            <div><p class="font-bold text-lg text-gray-900 dark:text-white"><?php echo $following_count; ?></p><p class="text-xs text-gray-500 dark:text-gray-400">Following</p></div>
        </div>
    </div>
    <nav class="p-4">
        <ul class="space-y-2">
            <li><a href="index.php" class="flex items-center gap-4 p-2 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-home w-5 text-center"></i><span>Beranda</span></a></li>
            <li><a href="profile.php?id=<?php echo htmlspecialchars($user_id); ?>" class="flex items-center gap-4 p-2 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-user w-5 text-center"></i><span>Profil Saya</span></a></li>
            <li><a href="settings.php" class="flex items-center gap-4 p-2 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-cog w-5 text-center"></i><span>Pengaturan</span></a></li>
        </ul>
    </nav>
    <div class="absolute bottom-0 w-full p-4 border-t border-gray-200 dark:border-gray-700">
        <a href="logout.php" class="flex items-center gap-4 p-2 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition"><i class="fas fa-sign-out-alt w-5 text-center"></i><span>Logout</span></a>
    </div>
</aside>

<header id="main-header" class="bg-white dark:bg-[#1e1e1e] px-4 py-3 flex items-center justify-between border-b border-gray-200 dark:border-gray-700 fixed top-0 w-full z-30">
    <div class="flex items-center gap-4">
        <button id="menuBtn" class="text-gray-600 dark:text-white text-xl"><i class="fas fa-bars"></i></button>
        <div class="text-red-600 font-bold text-xl">Qurio</div>
    </div>
    <div class="flex items-center gap-4">
        <form action="search.php" method="get" class="relative"><input type="text" name="q" placeholder="Cari..." class="bg-gray-200 dark:bg-[#2c2c2c] text-gray-900 dark:text-white text-sm rounded px-3 py-1 focus:outline-none" /></form>
        <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none rounded-lg text-sm p-2.5"><i id="theme-toggle-dark-icon" class="hidden fas fa-moon"></i><i id="theme-toggle-light-icon" class="hidden fas fa-sun"></i></button>
        
        <a href="notifications.php" class="text-gray-600 dark:text-white hover:text-red-500 text-lg relative">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0): ?>
                <span id="notif-indicator" class="absolute -top-1 -right-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span></span>
            <?php endif; ?>
        </a>
        
        <button id="openPostModal" class="bg-red-600 hover:bg-red-700 text-sm px-3 py-1 rounded text-white font-semibold"><i class="fas fa-plus"></i> Buat Post</button>
        
        <div class="relative">
          <button id="userBtn" class="flex items-center gap-2 text-gray-800 dark:text-white text-sm hover:opacity-80 transition">
            <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border-2 border-gray-400 dark:border-gray-600">
            <span class="font-medium hidden md:block"><?php echo htmlspecialchars($username); ?></span>
          </button>
          <div id="userDropdown" class="hidden absolute right-0 mt-3 w-60 bg-white dark:bg-[#2c2c2c] border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
            <div class="flex flex-col items-center p-4 border-b border-gray-200 dark:border-gray-600"><img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="w-16 h-16 rounded-full object-cover mb-2"><p class="font-bold text-gray-900 dark:text-white text-md"><?php echo htmlspecialchars($username); ?></p><p class="text-xs text-yellow-400 font-semibold">XP: <?php echo number_format($user_xp); ?></p></div>
            <div class="py-2"><a href="profile.php?id=<?php echo htmlspecialchars($user_id); ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"><i class="fas fa-user-circle w-4 text-center"></i><span>Profil Saya</span></a><a href="logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-700 hover:text-white transition"><i class="fas fa-sign-out-alt w-4 text-center"></i><span>Logout</span></a></div>
          </div>
        </div>
    </div>
</header>

<div id="postModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
  <div class="bg-white dark:bg-[#1e1e1e] p-6 rounded-lg w-full max-w-md relative text-gray-900 dark:text-white">
    <button id="closePostModal" class="absolute top-2 right-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><i class="fas fa-times"></i></button>
    <h2 class="text-lg font-semibold mb-3">Buat Post Baru</h2>
    <form method="post" action="index.php" enctype="multipart/form-data" class="space-y-3" id="postForm">
      <input type="hidden" name="new_post" value="1" />
      <input type="text" name="title" placeholder="Judul post..." class="w-full rounded bg-gray-100 dark:bg-[#2c2c2c] text-gray-900 dark:text-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-600" />
      <textarea name="content" placeholder="Apa yang Anda pikirkan?" rows="4" class="w-full rounded bg-gray-100 dark:bg-[#2c2c2c] text-gray-900 dark:text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 resize-none"></textarea>
      <div id="dropArea" class="w-full border-2 border-dashed border-gray-400 dark:border-gray-500 rounded p-4 text-center cursor-pointer hover:border-red-500 transition">
        <p id="dropText" class="text-sm text-gray-600 dark:text-gray-400">Tarik dan lepaskan gambar/video di sini atau klik untuk memilih</p>
        <input type="file" name="media" id="fileInput" accept="image/*,video/mp4,video/webm" class="hidden">
      </div>
      <button type="submit" name="new_post" class="w-full bg-red-600 hover:bg-red-700 py-2 rounded text-white text-sm font-semibold">Posting</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const postModal = document.getElementById('postModal');
    const openPostBtn = document.getElementById('openPostModal');
    const closePostBtn = document.getElementById('closePostModal');
    const userBtn = document.getElementById('userBtn');
    const userDropdown = document.getElementById('userDropdown');
    const menuBtn = document.getElementById('menuBtn');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    const sideMenu = document.getElementById('sideMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const dropText = document.getElementById('dropText');
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    const header = document.getElementById('main-header');
    const mainContent = document.getElementById('main-content');

    function closeAllDropdowns() {
        if(userDropdown) userDropdown.classList.add('hidden');
    }

    openPostBtn?.addEventListener('click', () => postModal.classList.remove('hidden'));
    closePostBtn?.addEventListener('click', () => postModal.classList.add('hidden'));

    userBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
    });

    const openMenu = () => { menuOverlay?.classList.remove('hidden'); sideMenu?.classList.remove('-translate-x-full'); };
    const closeMenu = () => { menuOverlay?.classList.add('hidden'); sideMenu?.classList.add('-translate-x-full'); };
    menuBtn?.addEventListener('click', openMenu);
    closeMenuBtn?.addEventListener('click', closeMenu);
    menuOverlay?.addEventListener('click', closeMenu);

    window.addEventListener('click', function(e){
        closeAllDropdowns();
    });

    if (dropArea) {
        dropArea.addEventListener('click', () => fileInput.click());
        dropArea.addEventListener('dragover', (e) => { e.preventDefault(); dropArea.classList.add('border-red-500'); dropText.textContent = "Lepaskan untuk mengunggah file"; });
        dropArea.addEventListener('dragleave', () => { dropArea.classList.remove('border-red-500'); dropText.textContent = "Tarik dan lepaskan gambar/video di sini atau klik untuk memilih"; });
        const handleFiles = (files) => {
            if (files.length > 0) {
                const acceptedTypes = fileInput.accept.split(',');
                const fileType = files[0].type;
                let isValid = acceptedTypes.some(type => { if (type.includes('/*')) { return fileType.startsWith(type.replace('/*', '')); } return type.trim() === fileType; });
                if (isValid) { fileInput.files = files; dropText.textContent = `File dipilih: ${files[0].name}`; } else { dropText.textContent = "Format file tidak diizinkan."; }
            }
        };
        dropArea.addEventListener('drop', (e) => { e.preventDefault(); dropArea.classList.remove('border-red-500'); handleFiles(e.dataTransfer.files); });
        fileInput.addEventListener('change', () => { handleFiles(fileInput.files); });
    }

    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        themeToggleLightIcon?.classList.remove('hidden'); document.documentElement.classList.add('dark');
    } else {
        themeToggleDarkIcon?.classList.remove('hidden'); document.documentElement.classList.remove('dark');
    }
    themeToggleBtn?.addEventListener('click', function() {
        themeToggleDarkIcon.classList.toggle('hidden'); themeToggleLightIcon.classList.toggle('hidden');
        if (localStorage.getItem('color-theme')) {
            if (localStorage.getItem('color-theme') === 'light') { document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark'); }
            else { document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light'); }
        } else {
            if (document.documentElement.classList.contains('dark')) { document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light'); }
            else { document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark'); }
        }
    });
    
    if (header && mainContent) {
        const headerHeight = header.offsetHeight;
        mainContent.style.paddingTop = headerHeight + 'px';
    }
});
</script>

</body>
</html>