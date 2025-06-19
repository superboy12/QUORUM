<?php
// =================================================================
// BLOK 1: LOGIKA PHP UNTUK MEMPROSES FORM (TIDAK DIUBAH)
// =================================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_community'])) {
    $creator_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'public';
    $avatar_path = 'assets/default-community-avatar.png';

    // Validasi
    if (empty($name) || empty($description)) { $errors[] = "Nama dan Deskripsi Komunitas wajib diisi."; }
    if (strlen($name) < 3 || strlen($name) > 30) { $errors[] = "Nama komunitas harus antara 3 hingga 30 karakter."; }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) { $errors[] = "Nama komunitas hanya boleh berisi huruf, angka, dan underscore (_)."; }
    if (!in_array($type, ['public', 'private'])) { $errors[] = "Tipe komunitas tidak valid."; }

    if(empty($errors)) {
        $stmt_check = $conn->prepare("SELECT id FROM communities WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errors[] = "Nama komunitas r/".htmlspecialchars($name)." sudah digunakan.";
        }
        $stmt_check->close();
    }

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0 && empty($errors)) {
        if ($_FILES['avatar']['size'] > 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['avatar']['type'], $allowed_types) && $_FILES['avatar']['size'] <= 2 * 1024 * 1024) {
                $target_dir = "uploads/communities/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
                $filename = 'comm-' . $safe_name . '-' . uniqid() . '.' . pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
                $target_file = $target_dir . $filename;
                if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                    $avatar_path = $target_file;
                } else { $errors[] = "Gagal mengunggah avatar."; }
            } else { $errors[] = "Avatar harus berupa file gambar (JPG, PNG, GIF, WEBP) dan ukurannya maksimal 2MB."; }
        }
    }

    if (empty($errors)) {
        $stmt_comm = $conn->prepare("INSERT INTO communities (creator_id, name, description, type, avatar_url) VALUES (?, ?, ?, ?, ?)");
        $stmt_comm->bind_param("issss", $creator_id, $name, $description, $type, $avatar_path);
        $stmt_comm->execute();
        $new_community_id = $stmt_comm->insert_id;
        $stmt_comm->close();

        $role = 'admin';
        $status = 'approved';
        $stmt_member = $conn->prepare("INSERT INTO community_members (community_id, user_id, role, status) VALUES (?, ?, ?, ?)");
        $stmt_member->bind_param("iiss", $new_community_id, $creator_id, $role, $status);
        $stmt_member->execute();
        $stmt_member->close();

        header("Location: community.php?id=" . $new_community_id);
        exit;
    }
}

// Data untuk header
$user_id_header = $_SESSION['user_id'] ?? null;
$user_avatar_header = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$unread_count_header = 0;
if (isset($conn) && $user_id_header) {
    $stmt_header_notif = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_header_notif->bind_param("i", $user_id_header);
    $stmt_header_notif->execute();
    $unread_count_header = $stmt_header_notif->get_result()->fetch_assoc()['count'];
    $stmt_header_notif->close();
}
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buat Komunitas - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, spacing: { '20': '5rem' } } } }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style> .no-scrollbar::-webkit-scrollbar { display: none; } </style>
</head>
<body class="bg-gray-100 dark:bg-dark">

<header id="main-header" class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between border-b border-gray-200 dark:border-gray-800 fixed top-0 w-full z-30">
    <div class="flex items-center gap-4 flex-1">
        <a href="index.php" class="flex items-center gap-2 text-red-600 font-bold text-2xl">
            <i class="fa-solid fa-feather-pointed"></i>
            <span class="hidden sm:inline">Qurio</span>
        </a>
    </div>
    <div class="flex-1 flex justify-center px-4">
        <form action="search.php" method="get" class="relative w-full max-w-lg">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" name="q" placeholder="Cari di Qurio..." class="w-full bg-gray-200/70 dark:bg-gray-800 text-gray-900 dark:text-white rounded-full pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 transition-all" />
        </form>
    </div>
    <div class="flex flex-1 items-center justify-end gap-2 sm:gap-3">
        <button id="openPostModalBtn" class="bg-red-600 hover:bg-red-700 text-sm px-4 py-2 rounded-full text-white font-semibold flex items-center gap-2">
            <i class="fas fa-plus"></i><span class="hidden md:inline">Buat</span>
        </button>
        <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            <i id="theme-toggle-icon" class="fas fa-sun text-lg"></i>
        </button>
        <a href="notifications.php" class="text-gray-600 dark:text-gray-300 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors relative">
            <i class="fas fa-bell text-xl"></i>
            <?php if ($unread_count_header > 0): ?>
                <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="p-0.5 rounded-full hover:ring-2 hover:ring-red-500 transition-all">
            <img src="<?= htmlspecialchars($user_avatar_header); ?>" class="w-9 h-9 rounded-full object-cover">
        </a>
    </div>
</header>
<div id="postModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] hidden">
    </div>


<main class="w-full pt-20 py-12"> <div class="container mx-auto max-w-3xl px-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 md:p-8">
            <div class="text-center">
                <h1 class="text-2xl md:text-3xl font-bold mb-1 text-gray-900 dark:text-white">Buat Komunitas Baru</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Bangun ruang Anda sendiri dan undang orang lain untuk bergabung.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-800 dark:text-red-300 p-4 rounded-lg mb-6" role="alert">
                    <p class="font-bold mb-1">Oops, ada kesalahan:</p>
                    <ul class="list-disc list-inside text-sm">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="create_community.php" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Komunitas</label>
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm dark:bg-gray-700 dark:border-gray-600">r/</span>
                        <input type="text" name="name" id="name" required maxlength="30" class="flex-1 min-w-0 block w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-none rounded-r-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="PecintaKucingLampung" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                     <p class="text-xs text-gray-500 mt-1">Hanya huruf, angka, dan underscore. Tidak bisa diubah nanti.</p>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi Singkat</label>
                    <textarea name="description" id="description" rows="3" required class="block w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="Tempat berbagi foto, tips, dan cerita..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="flex items-center gap-4">
                    <img id="avatar-preview" src="assets/default-community-avatar.png" alt="Preview Avatar" class="w-16 h-16 rounded-lg object-cover bg-gray-100 dark:bg-gray-700">
                    <div class="flex-1">
                         <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Avatar Komunitas (Opsional)</label>
                         <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 dark:file:bg-red-900/50 file:text-red-700 dark:file:text-red-300 hover:file:bg-red-100 dark:hover:file:bg-red-900 cursor-pointer">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipe Privasi</label>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label for="public" class="flex p-4 border rounded-lg cursor-pointer transition-all hover:border-red-500 dark:border-gray-700 dark:hover:border-red-500 has-[:checked]:border-red-500 has-[:checked]:ring-2 has-[:checked]:ring-red-200 dark:has-[:checked]:ring-red-800">
                            <input id="public" name="type" type="radio" value="public" checked class="h-4 w-4 text-red-600 border-gray-300 focus:ring-red-500 mt-1">
                            <span class="ml-3 text-sm"><span class="font-bold text-gray-900 dark:text-white block">Publik <i class="fas fa-globe-asia ml-1"></i></span><span class="text-gray-500 dark:text-gray-400">Siapapun bisa melihat dan bergabung.</span></span>
                        </label>
                        <label for="private" class="flex p-4 border rounded-lg cursor-pointer transition-all hover:border-red-500 dark:border-gray-700 dark:hover:border-red-500 has-[:checked]:border-red-500 has-[:checked]:ring-2 has-[:checked]:ring-red-200 dark:has-[:checked]:ring-red-800">
                            <input id="private" name="type" type="radio" value="private" class="h-4 w-4 text-red-600 border-gray-300 focus:ring-red-500 mt-1">
                            <span class="ml-3 text-sm"><span class="font-bold text-gray-900 dark:text-white block">Privat <i class="fas fa-lock ml-1"></i></span><span class="text-gray-500 dark:text-gray-400">Hanya anggota terverifikasi yang bisa melihat.</span></span>
                        </label>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" name="create_community" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"><i class="fas fa-plus mr-2"></i>Buat Komunitas Saya</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // KODE INI HANYA UNTUK FUNGSI DI HALAMAN INI DAN HEADERNYA
    
    // --- Logika untuk Header (Tema & Modal Buat Postingan) ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');
    const postModal = document.getElementById('postModal');
    const openPostModalBtn = document.getElementById('openPostModalBtn');
    const closePostModalBtn = document.getElementById('closePostModalBtn');

    const applyTheme = () => {
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg';
        } else {
            document.documentElement.classList.remove('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg';
        }
    };
    applyTheme();
    themeToggleBtn?.addEventListener('click', function() {
        localStorage.setItem('color-theme', document.documentElement.classList.toggle('dark') ? 'dark' : 'light');
        applyTheme();
    });
    
    openPostModalBtn?.addEventListener('click', () => postModal?.classList.remove('hidden'));
    closePostModalBtn?.addEventListener('click', () => postModal?.classList.add('hidden'));
    postModal?.addEventListener('click', (e) => { if (e.target === postModal) postModal.classList.add('hidden'); });

    // --- Logika Khusus Halaman Ini (Preview Avatar) ---
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatar-preview');
    
    avatarInput?.addEventListener('change', function(event) {
        if (event.target.files && event.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }
    });
});
</script>

</body>
</html>