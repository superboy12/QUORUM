<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung arahkan ke index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
// Simpan nilai input untuk ditampilkan kembali jika ada error
$fullname_val = '';
$email_val = '';
$username_val = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    // Simpan nilai untuk diisi kembali ke form
    $fullname_val = $fullname;
    $email_val = $email;
    $username_val = $username;

    // Validasi
    if (empty($fullname) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
        $errors[] = "Semua kolom wajib diisi.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password tidak cocok.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password minimal harus 6 karakter.";
    }

    // Cek duplikasi username/email jika tidak ada error validasi sebelumnya
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username atau email sudah terdaftar.";
        }
        $stmt->close();
    }

    // Jika tidak ada error sama sekali, proses registrasi
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Default avatar dan bio bisa diatur di sini atau langsung dari struktur DB
        $default_avatar = 'assets/default-avatar.png';
        $default_bio = 'Selamat datang di profil saya!';

        $stmt = $conn->prepare("INSERT INTO users (fullname, email, username, password, avatar_url, bio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $fullname, $email, $username, $hashed_password, $default_avatar, $default_bio);
        
        if ($stmt->execute()) {
            // Registrasi berhasil, set session notifikasi dan redirect ke login
            $_SESSION['success_message'] = "Registrasi berhasil! Silakan login.";
            header("Location: login.php");
            exit;
        } else {
            $errors[] = "Gagal menyimpan data: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="stylesheet" href="style.css" />
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="w-full max-w-md p-8 space-y-6 bg-white dark:bg-[#1e1e1e] rounded-xl shadow-lg">
            <div class="text-center">
                <a href="index.php" class="text-red-600 font-bold text-3xl">Qurio</a>
                <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">Buat Akun Baru</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Bergabunglah dengan komunitas kami</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-md" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="register.php" class="space-y-4">
                <div>
                    <label for="fullname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Lengkap</label>
                    <input type="text" id="fullname" name="fullname" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" value="<?= htmlspecialchars($fullname_val) ?>" placeholder="Masukkan nama lengkap" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" value="<?= htmlspecialchars($email_val) ?>" placeholder="contoh@email.com" required>
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input type="text" id="username" name="username" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" value="<?= htmlspecialchars($username_val) ?>" placeholder="Pilih username unik" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input type="password" id="password" name="password" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="Minimal 6 karakter" required>
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" placeholder="Ulangi password Anda" required>
                </div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Daftar Sekarang
                </button>
            </form>
            
            <div class="text-sm text-center text-gray-600 dark:text-gray-400">
                <p>Sudah punya akun? <a href="login.php" class="font-medium text-red-600 hover:text-red-500">Login di sini</a></p>
            </div>
        </div>
    </div>
</body>
</html>