<?php
require_once 'includes/db.php';
session_start();

// Jika pengguna sudah login, langsung arahkan ke halaman utama
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$username_value = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';
    $username_value = $username; // Simpan untuk ditampilkan kembali di form

    if (empty($username) || empty($password)) {
        $errors[] = "Username dan password harus diisi.";
    }

    if (empty($errors)) {
        // Query sudah benar, mengambil semua data yang diperlukan
        $stmt = $conn->prepare("SELECT id, username, password, avatar_url, xp FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Login sukses
                
                // Keamanan: Membuat session ID baru setelah login
                session_regenerate_id(true); 
                
                // Menyimpan data user ke session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // ### PERBAIKAN UTAMA ADA DI DUA BARIS INI ###
                // Menyimpan avatar dan XP ke session agar tidak hilang
                $_SESSION['user_avatar'] = $user['avatar_url'];
                $_SESSION['user_xp'] = $user['xp'];
                // ### AKHIR PERBAIKAN ###
                
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Password yang Anda masukkan salah.";
            }
        } else {
            $errors[] = "Username tidak ditemukan.";
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
    <title>Login - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="stylesheet" href="style.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="w-full max-w-md p-8 space-y-6 bg-white dark:bg-[#1e1e1e] rounded-xl shadow-lg">
            <div class="text-center">
                <a href="index.php" class="text-red-600 font-bold text-3xl">Qurio</a>
                <h2 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">Selamat Datang Kembali</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Masuk ke akun Anda untuk melanjutkan</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-md" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input 
                        type="text" 
                        id="username"
                        name="username" 
                        class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white" 
                        value="<?= htmlspecialchars($username_value) ?>"
                        placeholder="Masukkan username Anda"
                        required
                        autofocus
                    >
                </div>

                <div>
                    <label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input 
                        type="password" 
                        id="password"
                        name="password" 
                        class="mt-1 block w-full px-3 py-2 bg-gray-50 dark:bg-[#2c2c2c] border border-gray-300 dark:border-gray-600 rounded-md text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 text-gray-900 dark:text-white"
                        placeholder="Masukkan password Anda"
                        required
                    >
                </div>

                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Masuk
                </button>
            </form>
            
            <div class="text-sm text-center text-gray-600 dark:text-gray-400">
                <p>Belum punya akun? <a href="register.php" class="font-medium text-red-600 hover:text-red-500">Daftar sekarang</a></p>
            </div>
        </div>
    </div>
</body>
</html>