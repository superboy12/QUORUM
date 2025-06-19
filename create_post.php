<?php
// =================================================================
// BLOK 1: INISIALISASI & LOGIKA FORM
// =================================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Ambil ID komunitas dari URL, ini WAJIB ada.
$community_id = isset($_GET['community_id']) ? (int)$_GET['community_id'] : 0;
if ($community_id === 0) {
    die("Error: Komunitas tidak ditentukan.");
}

// Ambil nama komunitas untuk ditampilkan di judul
$stmt_comm = $conn->prepare("SELECT name FROM communities WHERE id = ?");
$stmt_comm->bind_param("i", $community_id);
$stmt_comm->execute();
$community = $stmt_comm->get_result()->fetch_assoc();
$stmt_comm->close();
if (!$community) { die("Error: Komunitas tidak ditemukan."); }

// =================================================================
// LOGIKA UNTUK MEMPROSES FORM POSTINGAN BARU
// =================================================================
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_post_in_community'])) {
    
    $title = trim($_POST['title']);
    $content = trim($_POST['content'] ?? '');
    $media_path = null;

    if (empty($title)) {
        $errors[] = "Judul postingan tidak boleh kosong.";
    }

    if (empty($errors)) {
        if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
            $file_info = pathinfo($_FILES["media"]["name"]);
            $file_extension = strtolower($file_info['extension']);
            $file_name = 'media-' . $user_id . '-' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $file_name;
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
            if (in_array($file_extension, $allowed_types) && $_FILES["media"]["size"] <= 50 * 1024 * 1024) {
                if (!move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
                     $errors[] = "Gagal mengunggah media.";
                } else {
                    $media_path = $target_file;
                }
            } else {
                $errors[] = "Tipe atau ukuran file tidak diizinkan.";
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, media_path, community_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $title, $content, $media_path, $community_id);
            $stmt->execute();
            $stmt->close();
            header("Location: community.php?id=" . $community_id);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buat Postingan di r/<?= htmlspecialchars($community['name']) ?> - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, spacing: { '20': '5rem' } } } }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style> .no-scrollbar::-webkit-scrollbar { display: none; } </style>
</head>
<body class="bg-gray-100 dark:bg-dark">

<?php require_once 'templates/header.php'; ?>

<main class="w-full pt-20 py-12">
    <div class="container mx-auto max-w-3xl px-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 md:p-8">
            <h1 class="text-2xl md:text-3xl font-bold mb-1 text-gray-900 dark:text-white">Buat Postingan</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">di komunitas <a href="community.php?id=<?= $community_id ?>" class="font-semibold text-red-600 hover:underline">r/<?= htmlspecialchars($community['name']) ?></a></p>

            <?php if (!empty($errors)): ?>
                <?php endif; ?>

            <form method="post" action="create_post.php?community_id=<?= $community_id ?>" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="new_post_in_community" value="1">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Judul</label>
                    <input type="text" name="title" id="title" class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
                </div>
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Konten (Opsional)</label>
                    <textarea name="content" id="content" rows="6" class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
                </div>
                <div>
                    <label for="media" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gambar/Video (Opsional)</label>
                    <input type="file" name="media" id="media" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 dark:file:bg-red-900/50 file:text-red-700 dark:file:text-red-300 hover:file:bg-red-100 dark:hover:file:bg-red-900 cursor-pointer">
                </div>
                <div class="pt-4 flex justify-end gap-4">
                    <a href="community.php?id=<?= $community_id ?>" class="text-gray-600 bg-gray-200 hover:bg-gray-300 font-bold py-2 px-6 rounded-full transition">Batal</a>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-full transition-colors duration-200">
                        <i class="fas fa-paper-plane mr-2"></i>Posting
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
    // Tidak perlu JS kompleks, karena semua logika ada di PHP
</script>
</body>
</html>