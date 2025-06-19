<?php
// post_handler.php

// Hanya jalankan jika method adalah POST dan ada aksi 'new_post'
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_post'])) {
    
    // Pastikan sesi sudah dimulai dan pengguna sudah login
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        // Jika tidak ada sesi, hentikan eksekusi atau redirect ke login
        header("Location: login.php");
        exit;
    }

    // Butuh koneksi database untuk memproses data
    require_once 'includes/db.php'; 

    $user_id = $_SESSION['user_id'];
    // Ambil URL halaman sebelumnya untuk redirect kembali setelah posting
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $redirect_url = strtok($redirect_url, '#'); // Hapus anchor dari URL

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $media_path = null;

    // Proses file upload jika ada
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) { 
            mkdir($target_dir, 0755, true); 
        }

        $file_extension = strtolower(pathinfo($_FILES["media"]["name"], PATHINFO_EXTENSION));
        $file_name = uniqid('media-', true) . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

        // Validasi tipe dan ukuran file (maks 50MB)
        if (in_array($file_extension, $allowed_types) && $_FILES["media"]["size"] <= 50 * 1024 * 1024) { 
            if (move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
                $media_path = $target_file;
            }
        }
    }

    // Hanya masukkan ke database jika ada judul, konten, atau media
    if (!empty($title) || !empty($content) || $media_path !== null) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, media_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $content, $media_path);
        $stmt->execute();
        $stmt->close();

        // Update XP pengguna karena telah membuat post
        $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + 15;
        $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 15 WHERE id = ?");
        $stmt_xp->bind_param("i", $user_id);
        $stmt_xp->execute();
        $stmt_xp->close();
    }
    
    // Tutup koneksi dan redirect
    $conn->close();
    header("Location: " . $redirect_url);
    exit;
}