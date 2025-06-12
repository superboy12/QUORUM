<?php
require_once 'includes/db.php';
session_start();

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    // Validasi sederhana
    if (!$fullname || !$email || !$username || !$password || !$confirm_password) {
        $errors[] = "Semua field harus diisi.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email tidak valid.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password harus sama.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }

    // Cek username/email sudah ada atau belum
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Username atau email sudah digunakan.";
        }
        $stmt->close();
    }

    // Kalau gak ada error, insert ke database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (fullname, email, username, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $email, $username, $hashed_password);
        if ($stmt->execute()) {
            // Registrasi berhasil, redirect ke login
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
    <link rel="stylesheet" href="css/style.css">
    <script src="js/script.js" defer></script>
</head>
<body>
    <div class="container">
        <div class="form-container fade-in">
            <h2>Daftar Akun</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="messages-container">
                    <?php foreach ($errors as $error): ?>
                        <div class="message error slide-up">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="register.php" onsubmit="return validateRegisterForm()" class="post-form">
                <div class="form-group">
                    <label for="fullname">Nama Lengkap</label>
                    <input 
                        type="text" 
                        id="fullname"
                        name="fullname" 
                        class="form-input" 
                        value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                        placeholder="Masukkan nama lengkap Anda"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email"
                        name="email" 
                        class="form-input" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="contoh@email.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username"
                        name="username" 
                        class="form-input" 
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        placeholder="Pilih username unik"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password"
                        name="password" 
                        class="form-input"
                        placeholder="Minimal 6 karakter"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input 
                        type="password" 
                        id="confirm_password"
                        name="confirm_password" 
                        class="form-input"
                        placeholder="Ulangi password Anda"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-full-width">
                    Daftar Sekarang
                </button>
            </form>
            
            <div class="form-footer">
                <p>Sudah punya akun? <a href="login.php" class="link-primary">Login di sini</a></p>
            </div>
        </div>
    </div>

    <style>
        /* Additional styles for better form layout */
        .form-container {
            animation: slideUp 0.6s ease-out;
        }
        
        .messages-container {
            margin-bottom: var(--space-lg);
        }
        
        .btn-full-width {
            width: 100%;
            margin-top: var(--space-md);
            padding: var(--space-lg) var(--space-xl);
            font-size: 1.1rem;
        }
        
        .form-footer {
            text-align: center;
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .form-footer p {
            margin: 0;
            color: var(--secondary-text);
        }
        
        .link-primary {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition-normal);
        }
        
        .link-primary:hover {
            color: var(--accent-text);
            text-shadow: 0 0 8px rgba(255, 107, 107, 0.3);
        }
        
        /* Form input enhancements */
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1), var(--shadow-md);
        }
        
        .form-input::placeholder {
            color: var(--muted-text);
            font-style: italic;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .form-container {
                margin: var(--space-md);
                padding: var(--space-lg);
            }
            
            .btn-full-width {
                padding: var(--space-md) var(--space-lg);
                font-size: 1rem;
            }
        }
    </style>
</body>
</html>