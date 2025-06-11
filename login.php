<?php
require_once 'includes/db.php';
session_start();

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';

    if (!$username || !$password) {
        $errors[] = "Username dan password harus diisi.";
    }

    if (empty($errors)) {
        // Cari user berdasarkan username
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Login sukses, set session dan redirect
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                header("Location: index.php"); // halaman beranda
                exit;
            } else {
                $errors[] = "Password salah.";
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
    <title>Login - Ngorum</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container fade-in">
            <div class="login-header">
                <h2>Selamat Datang Kembali</h2>
                <p class="login-subtitle">Masuk ke akun Anda untuk melanjutkan</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="messages-container">
                    <?php foreach ($errors as $error): ?>
                        <div class="message error slide-up">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" class="post-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username"
                        name="username" 
                        class="form-input" 
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        placeholder="Masukkan username Anda"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password"
                        name="password" 
                        class="form-input"
                        placeholder="Masukkan password Anda"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-full-width">
                    Masuk
                </button>
            </form>
            
            <div class="form-footer">
                <p>Belum punya akun? <a href="register.php" class="link-primary">Daftar sekarang</a></p>
            </div>
            