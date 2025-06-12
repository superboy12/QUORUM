<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Logika ini akan dipindahkan ke community_handler.php nanti,
// untuk sekarang kita letakkan di sini agar form bisa berfungsi.
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_community'])) {
    $creator_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'public';
    $avatar_path = 'assets/default-community-avatar.png'; // Avatar default

    // Validasi
    if (empty($name) || empty($description)) {
        $errors[] = "Nama dan Deskripsi Komunitas wajib diisi.";
    }
    if (strlen($name) > 50) {
        $errors[] = "Nama komunitas tidak boleh lebih dari 50 karakter.";
    }
    if (!in_array($type, ['public', 'private'])) {
        $errors[] = "Tipe komunitas tidak valid.";
    }

    // Cek duplikasi nama
    $stmt_check = $conn->prepare("SELECT id FROM communities WHERE name = ?");
    $stmt_check->bind_param("s", $name);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $errors[] = "Nama komunitas sudah digunakan. Silakan pilih nama lain.";
    }
    $stmt_check->close();

    // Handle upload avatar
    if (!empty($_FILES['avatar']['name']) && empty($errors)) {
        $target_dir = "uploads/communities/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = uniqid() . '-' . basename($_FILES["avatar"]["name"]);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            $avatar_path = $target_file;
        } else {
            $errors[] = "Gagal mengunggah avatar.";
        }
    }

    // Jika tidak ada error, buat komunitas
    if (empty($errors)) {
        // 1. Masukkan ke tabel communities
        $stmt_comm = $conn->prepare("INSERT INTO communities (creator_id, name, description, type, avatar_url) VALUES (?, ?, ?, ?, ?)");
        $stmt_comm->bind_param("issss", $creator_id, $name, $description, $type, $avatar_path);
        $stmt_comm->execute();
        $new_community_id = $stmt_comm->insert_id;
        $stmt_comm->close();

        // 2. Jadikan pembuat sebagai admin di tabel community_members
        $role = 'admin';
        $stmt_member = $conn->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, ?)");
        $stmt_member->bind_param("iis", $new_community_id, $creator_id, $role);
        $stmt_member->execute();
        $stmt_member->close();

        // Redirect ke halaman komunitas yang baru dibuat
        header("Location: community.php?id=" . $new_community_id);
        exit;
    }
}

include 'templates/header.php';
?>

<main id="main-content">
    <div class="container mx-auto max-w-2xl px-4 py-8">
        <div class="bg-white dark:bg-[#1e1e1e] rounded-xl shadow-lg p-6 md:p-8">
            <h1 class="text-2xl font-bold mb-1 text-gray-900 dark:text-white">Buat Komunitas Baru</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Bangun ruang Anda sendiri dan undang orang lain untuk bergabung.</p>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-md mb-6" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="create_community.php" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Komunitas</label>
                    <input type="text" name="name" id="name" required maxlength="50" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="Contoh: Pecinta Kucing Lampung">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi Singkat</label>
                    <textarea name="description" id="description" rows="3" required class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="Jelaskan tentang komunitas Anda..."></textarea>
                </div>
                <div>
                    <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Avatar Komunitas (Opsional)</label>
                    <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 dark:file:bg-red-900/50 file:text-red-700 dark:file:text-red-300 hover:file:bg-red-100 dark:hover:file:bg-red-900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipe Privasi</label>
                    <div class="mt-2 space-y-2">
                        <div class="flex items-center">
                            <input id="public" name="type" type="radio" value="public" checked class="h-4 w-4 text-red-600 border-gray-300 dark:border-gray-600 focus:ring-red-500">
                            <label for="public" class="ml-3 block text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">Publik</span>
                                <span class="text-gray-500 dark:text-gray-400 block">Siapapun bisa melihat dan bergabung.</span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input id="private" name="type" type="radio" value="private" class="h-4 w-4 text-red-600 border-gray-300 dark:border-gray-600 focus:ring-red-500">
                            <label for="private" class="ml-3 block text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">Privat</span>
                                <span class="text-gray-500 dark:text-gray-400 block">Hanya anggota yang disetujui yang bisa melihat dan bergabung.</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" name="create_community" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Buat Komunitas Saya
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

</body>
</html>