<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Anda harus login.");
}

$current_user_id = $_SESSION['user_id'];
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $action = $_POST['action'];
    $profile_user_id = (int)($_POST['profile_user_id'] ?? 0);

    if ($profile_user_id === 0 || $profile_user_id === $current_user_id) {
        die("Aksi tidak valid.");
    }

    // Aksi Follow Langsung
    if ($action === 'follow') {
        // Langsung masukkan ke tabel followers
        $stmt = $conn->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE follower_id=follower_id");
        $stmt->bind_param("ii", $current_user_id, $profile_user_id);
        $stmt->execute();
        $stmt->close();
        
        // Buat notifikasi 'follow'
        $type = 'follow';
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, target_id) VALUES (?, ?, ?, ?)");
        $stmt_notif->bind_param("iisi", $profile_user_id, $current_user_id, $type, $current_user_id);
        $stmt_notif->execute();
        $stmt_notif->close();
    }
    // Aksi Unfollow Langsung
    elseif ($action === 'unfollow') {
        $stmt = $conn->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
        $stmt->bind_param("ii", $current_user_id, $profile_user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . $redirect_url);
    exit();

} else {
    header('Location: index.php');
    exit();
}