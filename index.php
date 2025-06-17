<?php
// =================================================================
// BLOK 1: SEMUA LOGIKA PHP (DATABASE, SESI, AJAX, FORM)
// =================================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FUNGSI BARU DARI ANDA UNTUK MENGHUBUNGI GEMINI AI
function get_ai_reply($prompt) {
    $api_key = "AIzaSyBt_8eub0TAUQPzRGSrCqkPZ-IOq-w4dMc"; // PENTING: Ganti dengan API key Anda
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$api_key";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return "Error saat menghubungi AI: " . curl_error($ch);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($result['error']['message'])) {
        return "Error dari API AI: " . $result['error']['message'];
    } else {
        return "Tidak ada respon yang valid dari AI. Mungkin ada masalah dengan API key atau format request.";
    }
}


// Handler untuk semua request AJAX (real-time)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda harus login.']); exit(); }
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    if (!$data || !isset($data['action'])) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']); exit(); }
    
    switch ($data['action']) {
        case 'like_post':
            $post_id = (int)($data['post_id'] ?? 0);
            if ($post_id === 0) { die(json_encode(['status' => 'error', 'message' => 'Post ID tidak valid.'])); }
            $stmt_check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
            $stmt_check->bind_param("ii", $post_id, $user_id);
            $stmt_check->execute();
            $is_liked = $stmt_check->get_result()->num_rows > 0;
            $stmt_check->close();
            if ($is_liked) {
                $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $post_id, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $post_id, $user_id);
            }
            $stmt->execute();
            $stmt->close();
            $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
            $stmt_count->bind_param("i", $post_id);
            $stmt_count->execute();
            $new_like_count = $stmt_count->get_result()->fetch_assoc()['count'];
            $stmt_count->close();
            echo json_encode(['status' => 'success', 'new_like_count' => $new_like_count, 'is_liked' => !$is_liked]);
            break;

        case 'add_comment':
            $post_id = (int)($data['post_id'] ?? 0);
            $comment_text = trim($data['comment_text'] ?? '');
            $parent_id = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
            if ($post_id > 0 && !empty($comment_text)) {
                $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_id);
                $stmt->execute();
                $new_comment_id = $stmt->insert_id;
                $stmt->close();
                $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + 5;
                $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 5 WHERE id = ?");
                $stmt_xp->bind_param("i", $user_id);
                $stmt_xp->execute();
                $stmt_xp->close();
                $ai_response_data = null;
                if (stripos($comment_text, '@ai') !== false) {
                    $question = trim(substr($comment_text, stripos($comment_text, '@ai') + 3));
                    if(empty($question)) {
                        $ai_content = "Anda memanggil saya, tetapi tidak ada pertanyaan. Ada yang bisa saya bantu?";
                    } else {
                        $ai_content = get_ai_reply($question); // Memanggil fungsi Gemini AI
                    }
                    $ai_user_id = 9999;
                    $stmt_ai = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
                    $stmt_ai->bind_param("iisi", $post_id, $ai_user_id, $ai_content, $new_comment_id);
                    $stmt_ai->execute();
                    $ai_comment_id = $stmt_ai->insert_id;
                    $stmt_ai->close();
                    $stmt_ai_new = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
                    $stmt_ai_new->bind_param("i", $ai_comment_id);
                    $stmt_ai_new->execute();
                    $ai_response_data = $stmt_ai_new->get_result()->fetch_assoc();
                    $stmt_ai_new->close();
                }
                $stmt_new = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
                $stmt_new->bind_param("i", $new_comment_id);
                $stmt_new->execute();
                $user_comment_data = $stmt_new->get_result()->fetch_assoc();
                $stmt_new->close();
                function make_clickable_links_ajax($text) { return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text); }
                if ($user_comment_data) { $user_comment_data['content_formatted'] = nl2br(htmlspecialchars(make_clickable_links_ajax($user_comment_data['content']))); }
                if ($ai_response_data) { $ai_response_data['content_formatted'] = nl2br(htmlspecialchars(make_clickable_links_ajax($ai_response_data['content']))); }
                echo json_encode(['status' => 'success', 'comment' => $user_comment_data, 'ai_reply' => $ai_response_data ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Komentar tidak boleh kosong.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
            break;
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        $redirect_url = strtok($redirect_url, '#');
        if (isset($_POST['new_post'])) {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            if(!empty($title) || !empty($content)) {
                $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $title, $content);
                $stmt->execute();
                $stmt->close();
                $_SESSION['user_xp'] = ($_SESSION['user_xp'] ?? 0) + 10;
                $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 10 WHERE id = ?");
                $stmt_xp->bind_param("i", $user_id);
                $stmt_xp->execute();
                $stmt_xp->close();
            }
            header("Location: " . $redirect_url);
            exit;
        }
    }
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';
$user_avatar = $_SESSION['user_avatar'] ?? 'assets/default-avatar.png';
$user_xp = $_SESSION['user_xp'] ?? 0;

function make_clickable_links($text) { return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text); }

$popular_communities_query = $conn->query("SELECT c.id, c.name, c.avatar_url, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count FROM communities c ORDER BY member_count DESC LIMIT 5");
$popular_communities = $popular_communities_query->fetch_all(MYSQLI_ASSOC);
$top_users_query = $conn->query("SELECT id, username, avatar_url, xp FROM users WHERE id != 9999 ORDER BY xp DESC LIMIT 5");
$top_users = $top_users_query->fetch_all(MYSQLI_ASSOC);
$stmt_joined = $conn->prepare("SELECT c.id, c.name, c.avatar_url FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE cm.user_id = ? AND cm.status = 'approved' ORDER BY c.name ASC");
$stmt_joined->bind_param("i", $user_id);
$stmt_joined->execute();
$joined_communities = $stmt_joined->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_joined->close();
$stmt_notif = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$unread_count = $stmt_notif->get_result()->fetch_assoc()['count'];
$stmt_notif->close();
$result = $conn->query("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(id) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(id) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = {$user_id}) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.community_id IS NULL ORDER BY p.created_at DESC");

function render_comments($conn, $post_id, $parent_id = null, $level = 0) {
    global $user_id;
    $sql = "SELECT c.*, u.username, u.avatar_url, 
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS like_count,
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked 
            FROM comments c JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ? AND c.parent_id ";
    $sql .= ($parent_id === null) ? "IS NULL" : "= ?";
    $sql .= " ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($sql);

    if ($parent_id === null) {
        $stmt->bind_param("ii", $user_id, $post_id);
    } else {
        $stmt->bind_param("iii", $user_id, $post_id, $parent_id);
    }
    
    $stmt->execute();
    $comments = $stmt->get_result();
    while ($c = $comments->fetch_assoc()) {
        echo '<div id="comment-'.$c['id'].'" class="flex items-start gap-3 ' . ($level > 0 ? 'ml-10' : '') . ' pt-4">';
            echo '<a href="profile.php?id='.$c['user_id'].'" class="flex-shrink-0">';
            if ($c['user_id'] == 9999) { 
                echo '<div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold text-sm">AI</div>';
            } else { 
                echo '<img src="'.htmlspecialchars($c['avatar_url']).'" alt="'.htmlspecialchars($c['username']).'" class="w-8 h-8 rounded-full object-cover">'; 
            }
            echo '</a>';
            echo '<div class="flex-1">';
                if ($c['user_id'] == 9999) {
                    echo '<div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-sm text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800/50"><strong class="text-purple-600 dark:text-purple-400">NOURA AI</strong><p class="mt-1 leading-relaxed">'.make_clickable_links(nl2br(htmlspecialchars($c['content']))).'</p></div>';
                } else {
                    echo '<div class="text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2"><a href="profile.php?id='.$c['user_id'].'" class="font-semibold text-gray-900 dark:text-white hover:underline">' . htmlspecialchars($c['username']) . '</a><p class="text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">' . make_clickable_links(nl2br(htmlspecialchars($c['content']))) . '</p></div>';
                }
                echo '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-4">';
                    echo '<span title="'.date("d M Y, H:i", strtotime($c['created_at'])).'">'.date("d M Y", strtotime($c['created_at'])).'</span>';
                    echo '<button type="button" data-comment-id="'.$c['id'].'" class="font-semibold hover:text-pink-400 transition comment-like-btn '.($c['user_liked'] ? 'text-pink-500' : '').'">Suka (<span class="comment-like-count">'.$c['like_count'].'</span>)</button>';
                    echo '<button type="button" data-target-form="reply-form-'.$c['id'].'" class="font-semibold reply-btn">Balas</button>';
                    if ($c['user_id'] == $user_id) { echo '<a href="edit_comment.php?id='.$c['id'].'" class="font-semibold">Edit</a>'; }
                echo '</div>';
                echo '<form class="comment-form hidden mt-2 flex items-center gap-2" id="reply-form-'.$c['id'].'">';
                    echo '<input type="hidden" name="post_id" value="'.$post_id.'">';
                    echo '<input type="hidden" name="parent_id" value="'.$c['id'].'">';
                    echo '<textarea name="comment_text" rows="1" class="comment-input flex-1 w-full bg-gray-200 dark:bg-gray-700 rounded-lg px-3 py-1.5 text-sm" placeholder="Balas kepada '.htmlspecialchars($c['username']).'..."></textarea>';
                    echo '<button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md font-semibold">Kirim</button>';
                echo '</form>';
            echo '</div></div>';
        render_comments($conn, $post_id, $c['id'], $level + 1);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Qurio - Beranda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    spacing: { '18': '4.5rem', '20': '5rem', '72': '18rem' },
                    colors: {
                        gray: { 900: '#111827', 800: '#1f2937', 700: '#374151', 200: '#e5e7eb', 100: '#f3f4f6' },
                        dark: '#0c0c0c'
                    },
                    transitionProperty: { 'width': 'width', 'margin': 'margin', 'left': 'left' }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #left-sidebar, #content-wrapper, #main-header { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #left-sidebar.collapsed .menu-text { opacity: 0; visibility: hidden; pointer-events: none; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark text-gray-800 dark:text-gray-200 font-sans">

<header id="main-header" class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md px-4 h-18 flex items-center justify-between border-b border-gray-200 dark:border-gray-800 fixed top-0 right-0 z-30 lg:left-72">
    <div class="flex-1 min-w-0">
        </div>
    <div class="flex-1 flex justify-center px-4 sm:px-8">
         <form action="search.php" method="get" class="relative w-full max-w-xl">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" name="q" placeholder="Cari di Qurio..." class="w-full bg-gray-200/70 dark:bg-gray-800 text-gray-900 dark:text-white rounded-full pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 transition-all" />
        </form>
    </div>
    <div class="flex-1 flex items-center justify-end gap-2 sm:gap-3">
        <button id="openPostModalBtn" class="bg-red-600 hover:bg-red-700 text-sm px-4 py-2 rounded-full text-white font-semibold flex items-center gap-2">
            <i class="fas fa-plus"></i><span class="hidden md:inline">Buat</span>
        </button>
        <button id="theme-toggle" type="button" class="text-gray-500 dark:text-gray-400 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            <i id="theme-toggle-icon" class="fas fa-sun text-lg"></i>
        </button>
        <a href="notifications.php" class="text-gray-600 dark:text-gray-300 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors relative">
            <i class="fas fa-bell text-xl"></i>
            <?php if ($unread_count > 0): ?>
                <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span></span>
            <?php endif; ?>
        </a>
        <a href="profile.php?id=<?= htmlspecialchars($user_id); ?>" class="p-0.5 rounded-full hover:ring-2 hover:ring-red-500 transition-all">
            <img src="<?= htmlspecialchars($user_avatar); ?>" class="w-9 h-9 rounded-full object-cover">
        </a>
    </div>
</header>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden"></div>

<aside id="left-sidebar" data-state="expanded" class="fixed top-0 left-0 h-full bg-white dark:bg-gray-900 z-50 transition-transform lg:transition-all duration-300 ease-in-out border-r border-gray-200 dark:border-gray-800 pt-4 w-72 -translate-x-full lg:translate-x-0">
    <div class="h-full flex flex-col">
        <div class="px-4 h-14 flex items-center justify-between flex-shrink-0">
            <a href="index.php" class="flex items-center gap-2 text-red-600 font-bold text-xl menu-text">
                <i class="fa-solid fa-feather-pointed"></i>
                <span>Qurio</span>
            </a>
            <button id="sidebar-toggle-btn" class="text-gray-600 dark:text-gray-300 text-lg w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto no-scrollbar space-y-4 pt-0">
            <a href="profile.php?id=<?= htmlspecialchars($user_id) ?>" class="flex items-center gap-4 group p-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover flex-shrink-0">
                <div class="overflow-hidden menu-text transition-opacity duration-200">
                    <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($user_xp) ?> XP</p>
                </div>
            </a>
            <div class="space-y-1">
                <a href="index.php" class="flex items-center gap-4 px-3 py-2.5 rounded-lg font-semibold text-sm text-red-600 dark:text-red-500">
                    <i class="fas fa-home fa-fw text-lg w-6 text-center"></i><span class="menu-text">Beranda</span>
                </a>
                <a href="profile.php?id=<?= htmlspecialchars($user_id) ?>" class="flex items-center gap-4 px-3 py-2.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors text-sm font-medium">
                    <i class="fas fa-user fa-fw text-lg w-6 text-center"></i><span class="menu-text">Profil Saya</span>
                </a>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700/50"></div>
            <div>
                <h3 class="px-3 mb-2 mt-2 text-xs font-semibold text-gray-400 uppercase tracking-wider menu-text">Komunitas</h3>
                <ul class="space-y-1">
                     <li><a href="create_community.php" class="flex items-center gap-4 px-3 py-2 text-sm rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"><i class="fas fa-plus-circle fa-fw text-lg w-6 text-center"></i><span class="menu-text">Buat Komunitas</span></a></li>
                     <li><a href="communities.php" class="flex items-center gap-4 px-3 py-2 text-sm rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"><i class="fas fa-compass fa-fw text-lg w-6 text-center"></i><span class="menu-text">Jelajahi</span></a></li>
                </ul>
                <ul class="space-y-1 mt-2">
                    <?php if (empty($joined_communities)): ?>
                        <li class="px-3 py-2 text-sm text-gray-500 menu-text">Anda belum bergabung.</li>
                    <?php endif; ?>
                    <?php foreach($joined_communities as $community): ?>
                    <li><a href="community.php?id=<?= $community['id'] ?>" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                        <img src="<?= htmlspecialchars($community['avatar_url']) ?>" class="w-6 h-6 rounded-md object-cover flex-shrink-0">
                        <span class="font-medium truncate menu-text"><?= htmlspecialchars($community['name']) ?></span>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            <a href="logout.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-500 dark:hover:text-red-500 transition-colors group">
                <i class="fas fa-sign-out-alt fa-fw text-lg group-hover:text-red-500 transition-colors w-6 text-center"></i><span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
</aside>

<div id="content-wrapper" class="pt-18 lg:ml-72">
    <div class="container mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <main class="lg:col-span-2 xl:col-span-3 space-y-6">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <article id="post-<?= $row['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700/50 overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-center gap-4 mb-4">
                                    <a href="profile.php?id=<?= $row['user_id'] ?>"><img src="<?= htmlspecialchars($row['avatar_url']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover"></a>
                                    <div>
                                        <a href="profile.php?id=<?= $row['user_id'] ?>" class="font-bold text-gray-800 dark:text-gray-100 hover:underline"><?= htmlspecialchars($row['username']) ?></a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= date("d F Y, H:i", strtotime($row['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <a href="post.php?id=<?= $row['id'] ?>" class="block"><h2 class="text-xl font-bold text-gray-900 dark:text-white hover:text-red-500 transition"><?= htmlspecialchars($row['title'] ?? 'Tanpa Judul') ?></h2></a>
                                    <div class="text-base text-gray-600 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed">
                                        <?= nl2br(make_clickable_links(htmlspecialchars($row['content']))) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700/50">
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-6">
                                        <button data-post-id="<?= $row['id'] ?>" class="like-btn flex items-center gap-2 text-sm hover:text-red-500 transition-colors <?= $row['user_liked'] ? 'text-red-500' : '' ?>"><i class="like-icon fas fa-heart text-lg"></i><span class="like-count font-medium"><?= $row['like_count'] ?></span></button>
                                        <a href="post.php?id=<?= $row['id'] ?>#comments" class="flex items-center gap-2 text-sm hover:text-blue-500 transition-colors"><i class="fas fa-comment-dots text-lg"></i><span class="font-medium"><?= $row['comment_count'] ?></span></a>
                                    </div>
                                    <?php if ($row['user_id'] == $user_id): ?>
                                    <div class="flex items-center gap-4">
                                        <a href="edit_post.php?id=<?= $row['id'] ?>" class="text-sm hover:text-yellow-500 transition-colors">Edit</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-section mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                                    <form class="comment-form flex items-start gap-3">
                                        <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-8 h-8 rounded-full object-cover">
                                        <input type="hidden" name="post_id" value="<?= $row['id'] ?>">
                                        <textarea name="comment_text" rows="1" placeholder="Tulis komentar..." class="comment-input flex-1 bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"></textarea>
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm self-start">Kirim</button>
                                    </form>
                                    <div class="comment-list mt-2" data-post-id="<?= $row['id'] ?>"><?php render_comments($conn, $row['id']); ?></div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-gray-500 p-10 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Selamat Datang di Qurio!</h2>
                        <p class="mt-2">Jadilah yang pertama membuat postingan.</p>
                    </div>
                <?php endif; ?>
            </main>

            <aside class="hidden lg:block xl:col-span-1">
                <div class="sticky top-24 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-sm mb-4 text-gray-600 dark:text-gray-400 uppercase tracking-wider">Komunitas Populer</h3>
                        <div class="space-y-4">
                            <?php foreach($popular_communities as $p_community): ?>
                            <a href="community.php?id=<?= $p_community['id'] ?>" class="flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($p_community['avatar_url']) ?>" alt="<?= htmlspecialchars($p_community['name']) ?>" class="w-10 h-10 rounded-lg object-cover">
                                <div>
                                    <h4 class="font-semibold group-hover:text-red-500 transition text-gray-800 dark:text-gray-100 text-sm">r/<?= htmlspecialchars($p_community['name']) ?></h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($p_community['member_count']) ?> anggota</p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-base mb-4 text-gray-800 dark:text-gray-100">🏆 Anggota Teraktif</h3>
                        <div class="space-y-4">
                            <?php foreach($top_users as $top_user): ?>
                            <a href="profile.php?id=<?= $top_user['id'] ?>" class="flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($top_user['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <h4 class="font-semibold text-sm group-hover:text-red-500 transition text-gray-800 dark:text-gray-100"><?= htmlspecialchars($top_user['username']) ?></h4>
                                    <p class="text-xs text-yellow-500 dark:text-yellow-400 font-semibold"><?= number_format($top_user['xp']) ?> XP</p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<div id="postModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] hidden">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg w-full max-w-md relative text-gray-900 dark:text-white mx-4 border border-gray-200 dark:border-gray-700">
        <button id="closePostModalBtn" class="absolute top-3 right-3 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>
        <h2 class="text-lg font-semibold mb-4">Buat Post Baru</h2>
        <form method="post" action="index.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="new_post" value="1">
            <input type="text" name="title" placeholder="Judul post..." class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-600" required>
            <textarea name="content" placeholder="Apa yang Anda pikirkan? (Opsional)" rows="5" class="w-full rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 resize-none"></textarea>
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 py-2.5 rounded-lg text-white text-sm font-semibold transition">Posting</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const header = document.getElementById('main-header');
    const sidebar = document.getElementById('left-sidebar');
    const contentWrapper = document.getElementById('content-wrapper');
    const toggleButton = document.getElementById('sidebar-toggle-btn');
    const overlay = document.getElementById('sidebar-overlay');
    const isDesktop = () => window.innerWidth >= 1024;

    const setSidebarState = (state, manual = false) => {
        if (!sidebar) return;
        
        if (isDesktop()) {
            overlay.classList.add('hidden'); 
            document.body.style.overflow = '';
            sidebar.classList.remove('-translate-x-full');
            
            if (state === 'expanded') {
                sidebar.classList.remove('lg:w-20'); sidebar.classList.add('lg:w-72');
                contentWrapper.classList.remove('lg:ml-20'); contentWrapper.classList.add('lg:ml-72');
                header.classList.remove('lg:left-20'); header.classList.add('lg:left-72');
                sidebar.classList.remove('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'expanded');
            } else { // 'collapsed'
                sidebar.classList.remove('lg:w-72'); sidebar.classList.add('lg:w-20');
                contentWrapper.classList.remove('lg:ml-72'); contentWrapper.classList.add('lg:ml-20');
                header.classList.remove('lg:left-72'); header.classList.add('lg:left-20');
                sidebar.classList.add('collapsed');
                if(manual) localStorage.setItem('sidebarState', 'collapsed');
            }
        } else { // Mobile view
            header.classList.remove('lg:left-72', 'lg:left-20');
            contentWrapper.classList.remove('lg:ml-72', 'lg:ml-20');
            if (state === 'expanded') {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else { // 'closed'
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }
    };
    
    toggleButton.addEventListener('click', (e) => {
        e.stopPropagation();
        const currentState = isDesktop() 
            ? (sidebar.classList.contains('lg:w-72') ? 'expanded' : 'collapsed')
            : (sidebar.classList.contains('-translate-x-full') ? 'closed' : 'expanded');
        const newState = isDesktop() ? (currentState === 'expanded' ? 'collapsed' : 'expanded') : (currentState === 'closed' ? 'expanded' : 'closed');
        setSidebarState(newState, true);
    });
    
    overlay.addEventListener('click', () => setSidebarState('closed'));
    let initialState = localStorage.getItem('sidebarState') || 'expanded';
    setSidebarState(isDesktop() ? initialState : 'closed');
    window.addEventListener('resize', () => {
        let currentState = localStorage.getItem('sidebarState') || 'expanded';
        setSidebarState(isDesktop() ? currentState : 'closed');
    });

    const postModal = document.getElementById('postModal');
    const openPostModalBtn = document.getElementById('openPostModalBtn');
    const closePostModalBtn = document.getElementById('closePostModalBtn'); 
    if(openPostModalBtn) openPostModalBtn.addEventListener('click', () => postModal.classList.remove('hidden'));
    if(closePostModalBtn) closePostModalBtn.addEventListener('click', () => postModal.classList.add('hidden'));
    if(postModal) postModal.addEventListener('click', (e) => { if (e.target === postModal) postModal.classList.add('hidden'); });

    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');
    const applyTheme = () => {
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg';
        } else {
            document.documentElement.classList.remove('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg';
        }
    };
    applyTheme();
    if(themeToggleBtn) themeToggleBtn.addEventListener('click', function() {
        localStorage.setItem('color-theme', document.documentElement.classList.toggle('dark') ? 'light' : 'dark');
        applyTheme();
    });

    document.body.addEventListener('click', function(e) {
        const likeButton = e.target.closest('.like-btn');
        if (likeButton) {
             e.preventDefault();
            const postId = likeButton.dataset.postId;
            const countSpan = likeButton.querySelector('.like-count');
            const isCurrentlyLiked = likeButton.classList.contains('text-red-500');
            const currentCount = parseInt(countSpan.textContent);
            likeButton.classList.toggle('text-red-500');
            countSpan.textContent = isCurrentlyLiked ? currentCount - 1 : currentCount + 1;
            fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'like_post', post_id: postId }) })
            .then(response => response.json()).then(data => {
                if (data.status !== 'success') {
                    likeButton.classList.toggle('text-red-500', isCurrentlyLiked); countSpan.textContent = currentCount;
                } else {
                    countSpan.textContent = data.new_like_count; likeButton.classList.toggle('text-red-500', data.is_liked);
                }
            }).catch(error => { console.error('Error:', error); likeButton.classList.toggle('text-red-500', isCurrentlyLiked); countSpan.textContent = currentCount; });
        }
    });

    document.body.addEventListener('submit', function(e) {
        if (e.target.matches('.comment-form')) {
            e.preventDefault();
            const form = e.target;
            const postId = form.querySelector('input[name="post_id"]').value;
            const commentTextarea = form.querySelector('textarea[name="comment_text"]');
            const commentText = commentTextarea.value.trim();
            const parentIdInput = form.querySelector('input[name="parent_id"]');
            const parentId = parentIdInput ? parentIdInput.value : null;
            if (commentText === '') return;
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true; submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'add_comment', post_id: postId, comment_text: commentText, parent_id: parentId }) })
            .then(response => { if (!response.ok) { throw new Error('Network response was not ok: ' + response.statusText); } return response.json(); })
            .then(data => {
                if (data.status === 'success') {
                    const userComment = data.comment;
                    const newCommentHtml = `<div id="comment-${userComment.id}" class="flex items-start gap-3 ${parentId ? 'ml-10' : ''} pt-4" style="opacity:0; transform: translateY(-10px); animation: fadeIn 0.5s forwards;"><a href="profile.php?id=${userComment.user_id}" class="flex-shrink-0"><img src="${userComment.avatar_url}" alt="${userComment.username}" class="w-8 h-8 rounded-full object-cover"></a><div class="flex-1"><div class="text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2"><a href="profile.php?id=${userComment.user_id}" class="font-semibold text-gray-900 dark:text-white hover:underline">${userComment.username}</a><p class="text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">${userComment.content_formatted}</p></div><div class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-4"><span title="${userComment.created_at}">${new Date(userComment.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</span><button type="button" data-comment-id="${userComment.id}" class="font-semibold hover:text-pink-400 transition comment-like-btn">Suka (0)</button><button type="button" data-target-form="reply-form-${userComment.id}" class="font-semibold reply-btn">Balas</button></div><form class="comment-form hidden mt-2 flex items-center gap-2" id="reply-form-${userComment.id}"><input type="hidden" name="post_id" value="${postId}"><input type="hidden" name="parent_id" value="${userComment.id}"><textarea name="comment_text" rows="1" class="comment-input flex-1 w-full bg-gray-200 dark:bg-gray-700 rounded-lg px-3 py-1.5 text-sm" placeholder="Balas kepada ${userComment.username}..."></textarea><button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md font-semibold">Kirim</button></form></div></div>`;
                    if (parentId) {
                        const parentCommentDiv = document.getElementById(`comment-${parentId}`); parentCommentDiv.insertAdjacentHTML('beforeend', newCommentHtml); form.classList.add('hidden'); form.reset();
                    } else {
                        const commentListContainer = document.querySelector(`.comment-list[data-post-id="${postId}"]`); 
                        if(commentListContainer) { commentListContainer.insertAdjacentHTML('beforeend', newCommentHtml); }
                        form.reset(); commentTextarea.style.height = 'auto';
                    }
                    if (data.ai_reply) {
                        const aiReply = data.ai_reply;
                        const aiCommentHtml = `<div id="comment-${aiReply.id}" class="flex items-start gap-3 ml-10 pt-4" style="opacity:0; transform: translateY(-10px); animation: fadeIn 1s forwards .5s;"><div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">AI</div><div class="flex-1"><div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-sm text-purple-800 dark:text-purple-300"><strong class="text-purple-600 dark:text-purple-400">NOURA AI</strong><p class="mt-1">${aiReply.content_formatted}</p></div></div></div>`;
                        const userCommentDiv = document.getElementById(`comment-${userComment.id}`);
                        if(userCommentDiv) { userCommentDiv.insertAdjacentHTML('afterend', aiCommentHtml); }
                    }
                } else { alert('Gagal: ' + (data.message || 'Terjadi kesalahan.')); }
            })
            .catch(error => { console.error('Error:', error); alert('Terjadi kesalahan koneksi.'); })
            .finally(() => { submitButton.disabled = false; submitButton.textContent = 'Kirim'; });
        }
    });
});
</script>

</body>
</html>