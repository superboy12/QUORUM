<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$alert_message = '';

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $logged_in_user_id;
if ($profile_id === 0) {
    die("ID Profil tidak valid.");
}

$is_own_profile = ($profile_id == $logged_in_user_id);

// Handle semua form POST jika ini adalah profil milik sendiri
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle update profil (username dan bio)
    if (isset($_POST['update_profile'])) {
        $new_username = trim($_POST['username']);
        $new_bio = trim($_POST['bio']);

        if (!empty($new_username) && strlen($new_username) >= 3) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $new_bio, $logged_in_user_id);
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username; // Update session username
                $alert_message = "Profil berhasil diperbarui!";
            } else {
                $alert_message = "Error: Username tersebut mungkin sudah digunakan.";
            }
            $stmt->close();
        } else {
            $alert_message = "Error: Username harus memiliki minimal 3 karakter.";
        }
    }

    // Handle update avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['avatar']['type'], $allowed_types)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = uniqid() . '-' . basename($_FILES['avatar']['name']);
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
                // 1. Update database
                $stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->bind_param("si", $target_path, $logged_in_user_id);
                $stmt->execute();
                $stmt->close();

                // ### 2. PERBAIKAN UTAMA ADA DI SINI ###
                // Update session agar perubahan langsung terlihat di seluruh situs
                $_SESSION['user_avatar'] = $target_path; 
                
                $alert_message = "Foto profil berhasil diperbarui!";
            } else {
                $alert_message = "Error saat mengunggah file.";
            }
        } else {
            $alert_message = "Error: Hanya file JPG, PNG, dan GIF yang diizinkan.";
        }
    }

    // Redirect setelah semua aksi selesai untuk menghindari resubmit
    header("Location: profile.php?id=" . $profile_id . "&alert=" . urlencode($alert_message));
    exit();
}


// --- Mengambil semua data yang diperlukan untuk ditampilkan ---
if (isset($_GET['alert'])) { $alert_message = htmlspecialchars(urldecode($_GET['alert'])); }

$stmt_user = $conn->prepare("SELECT id, username, email, avatar_url, xp, created_at, bio FROM users WHERE id = ?");
$stmt_user->bind_param("i", $profile_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
if (!$user) { die("Pengguna tidak ditemukan."); }

$stmt_posts = $conn->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$stmt_posts->bind_param("i", $profile_id);
$stmt_posts->execute();
$posts = $stmt_posts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_posts->close();
$post_count = count($posts);

$stmt_followers = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
$stmt_followers->bind_param("i", $profile_id); $stmt_followers->execute();
$follower_count = $stmt_followers->get_result()->fetch_assoc()['count'];
$stmt_followers->close();

$stmt_following = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE follower_id = ?");
$stmt_following->bind_param("i", $profile_id); $stmt_following->execute();
$following_count = $stmt_following->get_result()->fetch_assoc()['count'];
$stmt_following->close();

$is_following = false;
if (!$is_own_profile) {
    $stmt_follow_check = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
    $stmt_follow_check->bind_param("ii", $logged_in_user_id, $profile_id);
    $stmt_follow_check->execute();
    if ($stmt_follow_check->get_result()->num_rows > 0) { $is_following = true; }
    $stmt_follow_check->close();
}

// Memanggil file header setelah semua data siap
include 'templates/header.php';
?>

<main id="main-content">
    <div class="max-w-3xl mx-auto">
        <?php if ($alert_message): ?>
            <div class="bg-red-600 text-white p-3 rounded-md mb-6 text-center mx-4 sm:mx-0">
                <?php echo $alert_message; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-[#1e1e1e] shadow-md rounded-b-lg">
            <div class="h-32 md:h-48 bg-gray-200 dark:bg-gray-800"></div>
            <div class="p-4 sm:p-6 -mt-16">
                <div class="flex items-end gap-4">
                    <form id="avatarForm" action="profile.php?id=<?php echo $profile_id; ?>" method="post" enctype="multipart/form-data" class="relative <?php echo $is_own_profile ? 'cursor-pointer' : ''; ?>" <?php if ($is_own_profile) echo "onclick=\"document.getElementById('avatarInput').click();\""; ?>>
                        <input type="file" name="avatar" id="avatarInput" class="hidden" onchange="document.getElementById('avatarForm').submit();" <?php echo $is_own_profile ? '' : 'disabled'; ?>>
                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white dark:border-[#1e1e1e]">
                        <?php if ($is_own_profile): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center text-white opacity-0 hover:opacity-100 transition-opacity">
                            <i class="fas fa-camera fa-2x"></i>
                        </div>
                        <?php endif; ?>
                    </form>
                    
                    <div class="flex-grow pb-2">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Bergabung <?php echo date("F Y", strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="flex-shrink-0 pb-2">
                        <?php if ($is_own_profile): ?>
                            <button id="openEditModalBtn" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 font-bold py-2 px-4 rounded-full transition"><i class="fas fa-pencil-alt mr-2"></i>Edit Profil</button>
                        <?php else: ?>
                            <form action="follow_handler.php" method="POST">
                                <input type="hidden" name="profile_user_id" value="<?php echo $user['id']; ?>">
                                <?php if ($is_following): ?>
                                    <input type="hidden" name="action" value="unfollow">
                                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-full transition"><i class="fas fa-user-check mr-2"></i>Following</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="follow">
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-full transition"><i class="fas fa-user-plus mr-2"></i>Follow</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex justify-start gap-6 mt-4 text-sm">
                    <div><strong class="text-gray-900 dark:text-white"><?php echo $post_count; ?></strong> <span class="text-gray-500 dark:text-gray-400">Postingan</span></div>
                    <div><strong class="text-gray-900 dark:text-white"><?php echo $follower_count; ?></strong> <span class="text-gray-500 dark:text-gray-400">Followers</span></div>
                    <div><strong class="text-gray-900 dark:text-white"><?php echo $following_count; ?></strong> <span class="text-gray-500 dark:text-gray-400">Following</span></div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <div class="border-b border-gray-200 dark:border-gray-700"><nav class="flex space-x-4" aria-label="Tabs"><button class="tab-btn active" data-tab="posts">Postingan</button><button class="tab-btn" data-tab="about">Tentang</button></nav></div>
            <div class="mt-6">
                <div id="posts-content" class="tab-content space-y-4">
                    <?php if (empty($posts)): ?>
                        <div class="text-center text-gray-500 py-10"><p>Pengguna ini belum membuat postingan.</p></div>
                    <?php else: ?>
                        <?php foreach($posts as $post): ?>
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="block bg-white dark:bg-[#1e1e1e] p-4 rounded-lg shadow-md hover:shadow-xl transition"><h3 class="font-semibold text-lg text-gray-900 dark:text-white"><?php echo htmlspecialchars($post['title']); ?></h3><p class="text-gray-600 dark:text-gray-300 text-sm mt-1"><?php echo htmlspecialchars(mb_strimwidth($post['content'], 0, 150, "...")); ?></p><p class="text-xs text-gray-400 dark:text-gray-500 mt-2"><?php echo date("d F Y", strtotime($post['created_at'])); ?></p></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="about-content" class="tab-content hidden bg-white dark:bg-[#1e1e1e] p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-bold">Bio</h3><p class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($user['bio'] ?? 'Tidak ada bio.')); ?></p>
                    <h3 class="text-lg font-bold mt-6">Info Lainnya</h3><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Email: <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($is_own_profile): ?>
<div id="editProfileModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
  <div class="bg-white dark:bg-[#1e1e1e] p-6 rounded-lg w-full max-w-lg relative text-gray-900 dark:text-white">
    <button id="closeEditModalBtn" class="absolute top-3 right-3 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-2xl">&times;</button>
    <h2 class="text-xl font-semibold mb-4">Edit Profil</h2>
    <form method="post" action="profile.php?id=<?php echo $profile_id; ?>" class="space-y-4">
        <div><label for="username" class="block text-sm font-medium text-gray-500 dark:text-gray-400">Username</label><input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="mt-1 w-full bg-gray-100 dark:bg-[#2c2c2c] rounded-md px-3 py-2 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 focus:ring-red-500 focus:border-red-500"></div>
        <div><label for="bio" class="block text-sm font-medium text-gray-500 dark:text-gray-400">Bio</label><textarea name="bio" id="bio" rows="4" class="mt-1 w-full bg-gray-100 dark:bg-[#2c2c2c] rounded-md px-3 py-2 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 focus:ring-red-500 focus:border-red-500"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea></div>
        <div class="flex justify-end pt-4"><button type="submit" name="update_profile" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-full">Simpan Perubahan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
    .tab-btn { padding: 0.5rem 1rem; cursor: pointer; border-bottom: 2px solid transparent; color: #9ca3af; /* gray-400 */ }
    .tab-btn.active { border-bottom-color: #ef4444; /* red-500 */ color: #111827; /* gray-900 */ }
    .dark .tab-btn.active { color: #f9fafb; /* gray-50 */ }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn'); const tabContents = document.querySelectorAll('.tab-content');
    tabButtons.forEach(button => { button.addEventListener('click', () => { tabButtons.forEach(btn => btn.classList.remove('active')); button.classList.add('active'); tabContents.forEach(content => content.classList.add('hidden')); const tabId = button.getAttribute('data-tab'); document.getElementById(tabId + '-content').classList.remove('hidden'); }); });
    const openEditModalBtn = document.getElementById('openEditModalBtn'); const closeEditModalBtn = document.getElementById('closeEditModalBtn'); const editProfileModal = document.getElementById('editProfileModal');
    openEditModalBtn?.addEventListener('click', () => { editProfileModal.classList.remove('hidden'); });
    closeEditModalBtn?.addEventListener('click', () => { editProfileModal.classList.add('hidden'); });
    editProfileModal?.addEventListener('click', (event) => { if (event.target === editProfileModal) { editProfileModal.classList.add('hidden'); } });
});
</script>

</body>
</html>