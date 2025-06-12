<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ### LOGIKA UTAMA HALAMAN INI ###
// 1. Tandai semua notifikasi yang belum dibaca sebagai "sudah dibaca"
$stmt_mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt_mark_read->bind_param("i", $user_id);
$stmt_mark_read->execute();
$stmt_mark_read->close();

// 2. Ambil semua riwayat notifikasi untuk ditampilkan
$stmt_notif = $conn->prepare(
    "SELECT n.*, u.username as actor_username, u.avatar_url as actor_avatar 
     FROM notifications n 
     JOIN users u ON n.actor_id = u.id 
     WHERE n.user_id = ? 
     ORDER BY n.created_at DESC LIMIT 50" // Batasi 50 notif terakhir
);
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_notif->close();

// Memanggil header setelah semua logika selesai
include 'templates/header.php';
?>

<main id="main-content">
    <div class="container mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">Notifikasi</h1>
        
        <div class="bg-white dark:bg-[#1e1e1e] rounded-lg shadow-lg">
            <?php if (empty($notifications)): ?>
                <div class="p-10 text-center text-gray-500">
                    <i class="fas fa-bell-slash fa-3x mb-4"></i>
                    <p>Anda belum memiliki notifikasi.</p>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                            // Tentukan link, teks, dan ikon berdasarkan tipe notifikasi
                            $link = '#'; $text = ''; $icon = ''; $icon_color = 'text-gray-500';
                            
                            if ($notif['type'] === 'follow') {
                                $link = 'profile.php?id=' . $notif['actor_id'];
                                $text = 'mulai mengikuti Anda.';
                                $icon = 'fas fa-user-plus'; $icon_color = 'text-blue-500';
                            } elseif ($notif['type'] === 'like') {
                                $link = 'post.php?id=' . $notif['target_id'];
                                $text = 'menyukai postingan Anda.';
                                $icon = 'fas fa-heart'; $icon_color = 'text-red-500';
                            } elseif ($notif['type'] === 'comment') {
                                $link = 'post.php?id=' . $notif['target_id'] . '#comments';
                                $text = 'mengomentari postingan Anda.';
                                $icon = 'fas fa-comment'; $icon_color = 'text-green-500';
                            }
                        ?>
                        <li class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-200">
                            <a href="<?php echo $link; ?>" class="p-4 flex items-start gap-4">
                                <div class="w-8 text-center mt-1 text-xl <?php echo $icon_color; ?>">
                                    <?php echo "<i class='$icon'></i>"; ?>
                                </div>
                                <div class="flex-1 text-sm">
                                    <img src="<?php echo htmlspecialchars($notif['actor_avatar']); ?>" class="w-10 h-10 rounded-full object-cover inline-block mr-2">
                                    <span class="text-gray-800 dark:text-gray-100">
                                        <strong class="font-semibold"><?php echo htmlspecialchars($notif['actor_username']); ?></strong>
                                        <?php echo $text; ?>
                                    </span>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo date("d F Y, H:i", strtotime($notif['created_at'])); ?></p>
                                </div>
                                <?php if($notif['is_read'] == 0): ?>
                                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-full mt-2" title="Baru"></div>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>