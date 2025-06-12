<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan user sudah login untuk melakukan aksi apapun
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    die("Anda harus login untuk melakukan aksi ini.");
}

$user_id = $_SESSION['user_id'];
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$redirect_url = strtok($redirect_url, '#');

// Keamanan: Pastikan request datang dari form submission dengan 'action'
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: index.php');
    exit();
}

$action = $_POST['action'];
$community_id = (int)($_POST['community_id'] ?? 0);

if ($community_id === 0) {
    http_response_code(400); // Bad Request
    die("ID Komunitas tidak valid.");
}

// Ambil data komunitas untuk verifikasi
$stmt_comm = $conn->prepare("SELECT creator_id, type FROM communities WHERE id = ?");
$stmt_comm->bind_param("i", $community_id);
$stmt_comm->execute();
$community = $stmt_comm->get_result()->fetch_assoc();
$stmt_comm->close();

if (!$community) {
    die("Komunitas tidak ditemukan.");
}

// Proses Aksi menggunakan switch-case agar lebih rapi
switch ($action) {
    case 'join_community':
        $status = ($community['type'] === 'public') ? 'approved' : 'pending';
        $role = 'member';

        $stmt = $conn->prepare("INSERT INTO community_members (community_id, user_id, role, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $community_id, $user_id, $role, $status);
        $stmt->execute();
        $stmt->close();
        break;

    case 'leave_community':
        $stmt = $conn->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $community_id, $user_id);
        $stmt->execute();
        $stmt->close();
        break;

    // ### PERBAIKAN: Menambahkan logika Hapus Komunitas ###
    case 'delete_community':
        // Verifikasi Keamanan: Cek apakah user yang login adalah pemilik komunitas
        if ($community['creator_id'] == $user_id) {
            // Jika benar pemilik, hapus komunitas
            // Karena ada ON DELETE CASCADE, semua anggota & postingan terkait akan ikut terhapus/diupdate
            $stmt_delete = $conn->prepare("DELETE FROM communities WHERE id = ?");
            $stmt_delete->bind_param("i", $community_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            // Setelah berhasil hapus, kembali ke halaman utama
            header("Location: index.php");
            exit();
        } else {
            // Jika bukan pemilik, tolak aksi
            http_response_code(403);
            die("Akses ditolak. Anda bukan pemilik komunitas ini.");
        }
        break;
    
    default:
        die("Aksi tidak dikenal.");
}

// Kembali ke halaman sebelumnya setelah aksi selesai
header("Location: " . $redirect_url);
exit();