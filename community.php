<?php
// =================================================================
// BLOK 1: INISIALISASI DAN PENGAMBILAN DATA
// =================================================================
require_once 'includes/db.php';
require_once 'includes/view_helpers.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Ambil ID Komunitas dari URL, wajib ada.
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($community_id === 0) {
    header("Location: communities.php");
    exit;
}

// Ambil data detail komunitas
$stmt_comm = $conn->prepare("SELECT c.*, u.username as creator_name, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND status = 'approved') as member_count FROM communities c JOIN users u ON c.creator_id = u.id WHERE c.id = ?");
$stmt_comm->bind_param("i", $community_id);
$stmt_comm->execute();
$community = $stmt_comm->get_result()->fetch_assoc();
$stmt_comm->close();

if (!$community) {
    http_response_code(404);
    require_once 'templates/header.php';
    echo '<main class="w-full pt-20 py-12"><div class="container mx-auto text-center p-10 text-gray-500"><h1 class="text-2xl font-bold">404 - Tidak Ditemukan</h1><p>Komunitas yang Anda cari tidak ada.</p></div></main></body></html>';
    exit();
}

// Cek status keanggotaan user
$membership_status = null;
$is_member = false;
$stmt_member = $conn->prepare("SELECT status FROM community_members WHERE community_id = ? AND user_id = ?");
$stmt_member->bind_param("ii", $community_id, $user_id);
$stmt_member->execute();
$member_result = $stmt_member->get_result();
if ($member_result->num_rows > 0) {
    $membership_status = $member_result->fetch_assoc()['status'];
    if ($membership_status === 'approved') {
        $is_member = true;
    }
}
$stmt_member->close();

$can_view_content = ($community['type'] === 'public' || $is_member);

// Ambil postingan HANYA jika user boleh melihat
$posts = [];
if ($can_view_content) {
    $stmt_posts = $conn->prepare("SELECT p.*, u.username, u.avatar_url, (SELECT COUNT(*) FROM likes WHERE likes.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM comments WHERE comments.post_id = p.id) AS comment_count, (SELECT COUNT(id) FROM likes WHERE post_id = p.id AND user_id = ?) AS user_liked FROM posts p JOIN users u ON p.user_id = u.id WHERE p.community_id = ? ORDER BY p.created_at DESC");
    $stmt_posts->bind_param("ii", $user_id, $community_id);
    $stmt_posts->execute();
    $posts = $stmt_posts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_posts->close();
}
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>r/<?= htmlspecialchars($community['name']) ?> - Qurio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, spacing: { '20': '5rem' } } } }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"/>
    <style> .no-scrollbar::-webkit-scrollbar { display: none; } @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }</style>
</head>
<body class="bg-gray-100 dark:bg-dark">

<?php 
// Memanggil komponen header terpusat
require_once 'templates/header.php'; 
?>

<main class="w-full pt-20">
    <div class="bg-white dark:bg-gray-800 shadow-md">
        <div class="h-32 md:h-48 bg-gray-300 dark:bg-gray-700 bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($community['banner_url'] ?? 'assets/default-banner.jpg') ?>');"></div>
        <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end -mt-12 md:-mt-16 space-x-4">
                <img src="<?= htmlspecialchars($community['avatar_url']) ?>" class="w-24 h-24 md:w-32 md:h-32 rounded-2xl object-cover border-4 border-white dark:border-gray-800 flex-shrink-0 shadow-lg">
                <div class="flex-grow flex flex-col sm:flex-row justify-between items-start sm:items-end w-full pt-10 sm:pt-0">
                    <div class="pb-3">
                        <h1 class="text-2xl md:text-4xl font-bold text-gray-900 dark:text-white">r/<?= htmlspecialchars($community['name']) ?></h1>
                    </div>
                    <div class="pb-3 flex-shrink-0">
                        <button id="join-leave-btn" data-community-id="<?= $community['id'] ?>" class="font-bold py-2 px-6 rounded-full transition-all duration-200 w-40 text-center"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        <div class="lg:col-span-2 space-y-5">
            <?php if ($can_view_content): ?>
                <?php if ($is_member): ?>
                    <a href="create_post.php?community_id=<?= $community['id'] ?>" class="block w-full bg-red-600 hover:bg-red-700 text-white font-bold text-center py-3 px-4 rounded-lg shadow-lg transition-transform hover:scale-[1.02]">
                        <i class="fas fa-plus mr-2"></i>Buat Postingan Baru
                    </a>
                <?php endif; ?>
                
                <?php if (empty($posts)): ?>
                    <div class="text-center text-gray-500 py-16 bg-white dark:bg-gray-800 rounded-lg shadow-sm"><i class="fas fa-stream fa-3x mb-4"></i><p>Jadilah yang pertama membuat postingan di komunitas ini.</p></div>
                <?php else: ?>
                    <?php foreach($posts as $post): ?>
                        <article id="post-<?= $post['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="p-6">
                                <div class="flex items-center gap-4 mb-4">
                                    <a href="profile.php?id=<?= $post['user_id'] ?>"><img src="<?= htmlspecialchars($post['avatar_url']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover"></a>
                                    <div>
                                        <a href="profile.php?id=<?= $post['user_id'] ?>" class="font-bold text-gray-800 dark:text-gray-100 hover:underline"><?= htmlspecialchars($post['username']) ?></a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= date("d F Y, H:i", strtotime($post['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <a href="post.php?id=<?= $post['id'] ?>" class="block"><h2 class="text-xl font-bold text-gray-900 dark:text-white hover:text-red-500 transition"><?= htmlspecialchars($post['title'] ?? 'Tanpa Judul') ?></h2></a>
                                    <div class="text-base text-gray-600 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed"><?= nl2br(make_clickable_links(htmlspecialchars($post['content']))) ?></div>
                                    <?php if ($post['media_path']): ?>
                                        <div class="mt-4 rounded-lg overflow-hidden"><a href="<?= htmlspecialchars($post['media_path']) ?>" target="_blank"><img src="<?= htmlspecialchars($post['media_path']) ?>" class="max-w-full h-auto"></a></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700/50">
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-6">
                                        <button data-post-id="<?= $post['id'] ?>" class="like-btn flex items-center gap-2 text-sm hover:text-red-500 transition-colors <?= $post['user_liked'] ? 'text-red-500' : '' ?>"><i class="like-icon fas fa-heart text-lg"></i><span class="like-count font-medium"><?= $post['like_count'] ?></span></button>
                                        <button data-post-id="<?= $post['id'] ?>" class="toggle-comments-btn flex items-center gap-2 text-sm hover:text-blue-500 transition-colors"><i class="fas fa-comment-dots text-lg"></i><span class="font-medium"><?= $post['comment_count'] ?></span></button>
                                    </div>
                                </div>
                                <div class="comment-section hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50"></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-gray-500 py-20 bg-white dark:bg-gray-800 rounded-lg shadow-sm"><i class="fas fa-lock fa-3x mb-4"></i><h2 class="text-xl font-bold">Komunitas ini Privat</h2><p class="mt-2">Minta untuk bergabung agar bisa melihat dan membuat postingan.</p></div>
            <?php endif; ?>
        </div>
        <aside class="hidden lg:block space-y-6 lg:sticky lg:top-24">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
                <h3 class="font-bold text-lg mb-2 text-gray-900 dark:text-white">Tentang r/<?= htmlspecialchars($community['name']) ?></h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 border-b dark:border-gray-700 pb-3 mb-3"><?= htmlspecialchars($community['description']) ?></p>
                <div class="text-sm space-y-2">
                    <div class="flex items-center gap-2"><i class="fas fa-users fa-fw text-gray-400"></i> <strong id="member-count"><?= number_format($community['member_count']) ?></strong> Anggota</div>
                    <div class="flex items-center gap-2"><i class="fas fa-birthday-cake fa-fw text-gray-400"></i> Dibuat <?= date("d F Y", strtotime($community['created_at'])) ?></div>
                </div>
            </div>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- TIDAK ADA LOGIKA SIDEBAR DI SINI ---

    // --- LOGIKA TOMBOL JOIN/LEAVE ---
    const joinLeaveBtn = document.getElementById('join-leave-btn');
    const memberCountSpan = document.getElementById('member-count');
    const communityId = joinLeaveBtn?.dataset.communityId;
    let currentMembershipStatus = '<?= $membership_status ?>';

    const updateButtonState = (status, memberCount) => {
        if (!joinLeaveBtn) return;
        let textSpan = joinLeaveBtn.querySelector('.join-leave-text');
        if (!textSpan) {
            textSpan = document.createElement('span');
            textSpan.className = 'join-leave-text';
        }
        joinLeaveBtn.innerHTML = '';
        joinLeaveBtn.appendChild(textSpan);

        if (status === 'approved') {
            joinLeaveBtn.className = 'font-bold py-2 px-6 rounded-full transition-all duration-200 w-40 text-center bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300';
            textSpan.innerHTML = '<i class="fas fa-check mr-2"></i>Bergabung';
            joinLeaveBtn.disabled = false;
        } else if (status === 'pending') {
            joinLeaveBtn.className = 'font-bold py-2 px-6 rounded-full transition-all duration-200 w-40 text-center bg-gray-500 text-white cursor-not-allowed';
            textSpan.innerHTML = '<i class="fas fa-clock mr-2"></i>Diminta';
            joinLeaveBtn.disabled = true;
        } else {
            joinLeaveBtn.className = 'font-bold py-2 px-6 rounded-full transition-all duration-200 w-40 text-center bg-red-600 text-white hover:bg-red-700';
            textSpan.innerHTML = `<i class="fas fa-plus mr-2"></i><?= $community['type'] === 'public' ? 'Gabung' : 'Minta Bergabung' ?>`;
            joinLeaveBtn.disabled = false;
        }
        if (memberCountSpan && memberCount !== undefined) {
            memberCountSpan.textContent = new Intl.NumberFormat().format(memberCount);
        }
    };
    updateButtonState(currentMembershipStatus, <?= $community['member_count'] ?>);
    
    joinLeaveBtn?.addEventListener('click', function() {
        this.disabled = true;
        fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'toggle_join_community', community_id: communityId }) })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                currentMembershipStatus = data.new_status;
                updateButtonState(data.new_status, data.new_member_count);
            } else { alert(data.message || 'Terjadi kesalahan.'); this.disabled = false; }
        }).catch(error => { console.error('Error:', error); this.disabled = false; });
    });

    // --- LOGIKA INTERAKSI POST (LIKE & KOMENTAR) ---
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
            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'like_post', post_id: postId }) })
            .then(res => res.json()).then(data => { if(data.status !== 'success') { likeButton.classList.toggle('text-red-500', isCurrentlyLiked); countSpan.textContent = currentCount; } else { countSpan.textContent = data.new_like_count; likeButton.classList.toggle('text-red-500', data.is_liked); } });
        }
        
        const toggleCommentsBtn = e.target.closest('.toggle-comments-btn');
        if (toggleCommentsBtn) {
            const postId = toggleCommentsBtn.dataset.postId;
            const commentSection = toggleCommentsBtn.closest('article').querySelector('.comment-section');
            const isHidden = commentSection.classList.toggle('hidden');
            if (!isHidden && !commentSection.hasAttribute('data-loaded')) {
                commentSection.innerHTML = `<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>`;
                fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'get_comments', post_id: postId }) })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const userAvatar = "<?= htmlspecialchars($_SESSION['user_avatar'] ?? 'assets/default-avatar.png') ?>";
                        const formHTML = `<form class="comment-form flex items-start gap-3 mb-4"><img src="${userAvatar}" class="w-8 h-8 rounded-full object-cover"><input type="hidden" name="post_id" value="${postId}"><textarea name="comment_text" rows="1" placeholder="Tulis komentar..." class="comment-input flex-1 bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" oninput="this.style.height='auto';this.style.height=(this.scrollHeight)+'px';"></textarea><button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm self-start">Kirim</button></form>`;
                        commentSection.innerHTML = formHTML + `<div class="comment-list mt-2" data-post-id="${postId}">${data.html}</div>`;
                        commentSection.setAttribute('data-loaded', 'true');
                    } else { commentSection.innerHTML = `<p class="text-xs text-red-500">Gagal memuat komentar.</p>`; }
                });
            }
        }
        const replyButton = e.target.closest('.reply-btn');
        if (replyButton) {
            const formId = replyButton.dataset.targetForm;
            const form = document.getElementById(formId);
            if (form) { form.classList.toggle('hidden'); if (!form.classList.contains('hidden')) form.querySelector('textarea').focus(); }
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
            fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'add_comment', post_id: postId, comment_text: commentText, parent_id: parentId }) })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const userComment = data.comment;
                    const newCommentHtml = `<div id="comment-${userComment.id}" class="comment-item flex items-start gap-3 ${parentId ? 'ml-10' : ''} pt-4" style="animation: fadeIn 0.5s forwards;"><div class="flex-shrink-0"><a href="profile.php?id=${userComment.user_id}"><img src="${userComment.avatar_url}" alt="${userComment.username}" class="w-8 h-8 rounded-full object-cover"></a></div><div class="flex-1"><div class="comment-content text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2"><a href="profile.php?id=${userComment.user_id}" class="font-semibold text-gray-900 dark:text-white hover:underline">${userComment.username}</a><p class="comment-text-display text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">${userComment.content_formatted}</p></div><div class="comment-actions text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-4"><span>Baru saja</span><button type="button" data-comment-id="${userComment.id}" class="font-semibold hover:text-pink-400 transition comment-like-btn">Suka (0)</button><button type="button" data-target-form="reply-form-${userComment.id}" class="font-semibold reply-btn">Balas</button></div><form class="comment-form hidden mt-2 flex items-center gap-2" id="reply-form-${userComment.id}"><input type="hidden" name="post_id" value="${postId}"><input type="hidden" name="parent_id" value="${userComment.id}"><textarea name="comment_text" rows="1" class="comment-input flex-1 w-full bg-gray-200 dark:bg-gray-700 rounded-lg px-3 py-1.5 text-sm" placeholder="Balas kepada ${userComment.username}..."></textarea><button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md font-semibold">Kirim</button></form></div></div>`;
                    const targetList = parentId ? document.getElementById(`comment-${parentId}`).querySelector('.flex-1') : form.closest('.comment-section').querySelector('.comment-list');
                    if (parentId) { targetList.insertAdjacentHTML('beforeend', newCommentHtml); } else { targetList.insertAdjacentHTML('afterbegin', newCommentHtml); }
                    form.reset(); commentTextarea.style.height = 'auto';
                    if (data.ai_reply) {
                        const aiReply = data.ai_reply;
                        const aiCommentHtml = `<div id="comment-${aiReply.id}" class="comment-item flex items-start gap-3 ml-10 pt-4" style="animation: fadeIn 1s forwards .5s;"><div class="flex-shrink-0"><div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-lg">AI</div></div><div class="flex-1"><div class="comment-content text-sm bg-purple-50 dark:bg-gray-800 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50"><div class="flex items-center gap-2 mb-2"><strong class="font-bold text-purple-600 dark:text-purple-400">NOURA</strong><i class="fas fa-check-circle text-purple-500" title="Verified AI Assistant"></i></div><p class="text-gray-700 dark:text-gray-300 leading-relaxed">${aiReply.content_formatted}</p></div></div></div>`;
                        const userCommentDiv = document.getElementById(`comment-${userComment.id}`);
                        if(userCommentDiv) { userCommentDiv.insertAdjacentHTML('afterend', aiCommentHtml); }
                    }
                } else { alert('Gagal: ' + (data.message || 'Terjadi kesalahan.')); }
            }).catch(error => { console.error('Error:', error); alert('Terjadi kesalahan koneksi.'); }).finally(() => { submitButton.disabled = false; submitButton.textContent = 'Kirim'; });
        }
    });
});
</script>

</body>
</html>