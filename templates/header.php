<?php
// templates/header.php

// Pastikan sesi dimulai jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Sertakan koneksi database, gunakan path absolut dari lokasi file ini
if (!isset($conn)) { 
    require_once __DIR__ . '/../includes/db.php';
}

// =================================================================
// PENGAMBILAN DATA KHUSUS HEADER
// =================================================================
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Pengguna';
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$unread_count = 0;

if ($user_id) {
    // Hitung notifikasi yang belum dibaca
    $stmt_notif = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    $unread_count = $result_notif->fetch_assoc()['count'];
    $stmt_notif->close();
}
?>

<header id="main-header" class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md px-4 h-18 flex items-center justify-between border-b border-gray-200 dark:border-gray-800 fixed top-0 right-0 z-30 lg:left-72">
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
                <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                </span>
            <?php endif; ?>
        </a>

        <a href="profile.php?id=<?= htmlspecialchars($user_id); ?>" class="p-0.5 rounded-full hover:ring-2 hover:ring-red-500 transition-all">
            <img src="<?= htmlspecialchars($user_avatar); ?>" class="w-9 h-9 rounded-full object-cover">
        </a>
    </div>
</header>


<div id="postModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] hidden">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg w-full max-w-lg relative text-gray-900 dark:text-white mx-4 border border-gray-200 dark:border-gray-700">
        <button id="closePostModalBtn" class="absolute top-3 right-3 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>
        <h2 class="text-lg font-semibold mb-4">Buat Postingan Baru</h2>
        <form method="post" action="post_handler.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="new_post" value="1">
            <input type="text" name="title" placeholder="Judul..." class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-600" required>
            <textarea name="content" placeholder="Apa yang Anda pikirkan? (Opsional)" rows="4" class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 resize-none"></textarea>
            
            <div id="drop-area" class="border-2 border-dashed border-gray-400 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-red-500 dark:hover:border-red-500 transition-colors">
                <div id="drop-area-content">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-red-500">Pilih file</span> atau tarik dan lepaskan di sini
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PNG, JPG, GIF, WEBP, MP4, atau WEBM (MAX. 50MB)</p>
                </div>
                <input type="file" id="file-input" name="media" class="hidden" accept="image/png, image/jpeg, image/gif, image/webp, video/mp4, video/webm">
                <div id="preview-area" class="hidden mt-4 relative"></div>
            </div>
            
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 py-2.5 rounded-lg text-white text-sm font-semibold transition">Posting</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Elemen-elemen penting ---
    const postModal = document.getElementById('postModal');
    const openPostModalBtn = document.getElementById('openPostModalBtn');
    const closePostModalBtn = document.getElementById('closePostModalBtn');
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const previewArea = document.getElementById('preview-area');
    const dropAreaContent = document.getElementById('drop-area-content');
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');

    // --- LOGIKA MODAL POST ---
    const resetModalForm = () => {
        if(postModal) postModal.classList.add('hidden');
        if(previewArea) {
            previewArea.innerHTML = '';
            previewArea.classList.add('hidden');
        }
        if(dropAreaContent) dropAreaContent.classList.remove('hidden');
        if(fileInput) fileInput.value = '';
        if(postModal) postModal.querySelector('form')?.reset();
    };

    openPostModalBtn?.addEventListener('click', () => postModal.classList.remove('hidden'));
    closePostModalBtn?.addEventListener('click', resetModalForm);
    postModal?.addEventListener('click', (e) => {
        if (e.target === postModal) resetModalForm();
    });

    // --- LOGIKA DRAG-N-DROP UPLOAD ---
    if (dropArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropArea.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false));
        ['dragenter', 'dragover'].forEach(eventName => dropArea.addEventListener(eventName, () => dropArea.classList.add('border-red-500', 'bg-red-500/10'), false));
        ['dragleave', 'drop'].forEach(eventName => dropArea.addEventListener(eventName, () => dropArea.classList.remove('border-red-500', 'bg-red-500/10'), false));
        
        dropArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => handleFiles(fileInput.files));
        dropArea.addEventListener('drop', (e) => { handleFiles(e.dataTransfer.files); }, false);
    }
    
    function handleFiles(files) {
        if (!files || files.length === 0) return;
        let file = files[0];
        fileInput.files = files; // Assign file ke input
        previewArea.innerHTML = ''; 
        let previewElement;

        if (file.type.startsWith('image/')) {
            previewElement = document.createElement('img');
            previewElement.src = URL.createObjectURL(file);
            previewElement.className = 'max-h-48 rounded-lg mx-auto';
            previewElement.onload = () => URL.revokeObjectURL(previewElement.src);
        } else if (file.type.startsWith('video/')) {
            previewElement = document.createElement('video');
            previewElement.src = URL.createObjectURL(file);
            previewElement.className = 'max-h-48 rounded-lg mx-auto';
            previewElement.controls = true;
        } else {
            previewArea.innerHTML = `<p class="text-sm text-red-500">Tipe file tidak didukung.</p>`;
            fileInput.value = '';
            return;
        }

        const removeBtn = document.createElement('button');
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.className = 'absolute -top-2 -right-2 bg-red-600 text-white w-6 h-6 rounded-full text-xs flex items-center justify-center';
        removeBtn.type = 'button';
        removeBtn.onclick = () => {
            fileInput.value = '';
            previewArea.innerHTML = '';
            previewArea.classList.add('hidden');
            dropAreaContent.classList.remove('hidden');
        };

        previewArea.appendChild(previewElement);
        previewArea.appendChild(removeBtn);
        dropAreaContent.classList.add('hidden');
        previewArea.classList.remove('hidden');
    }

    // --- LOGIKA MODE TEMA (DARK/LIGHT) ---
    const applyTheme = () => {
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg';
        } else {
            document.documentElement.classList.remove('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg';
        }
    };

    applyTheme(); // Terapkan tema saat halaman dimuat

    themeToggleBtn?.addEventListener('click', function() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('color-theme', isDark ? 'dark' : 'light');
        applyTheme();
    });
});
</script>