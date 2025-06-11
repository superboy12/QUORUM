<?php
require_once 'includes/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
function get_ai_prompt($text) {
    // Ambil isi dalam @ai(...) atau @ai ...
    if (preg_match('/@ai\s*\((.*?)\)/i', $text, $matches)) {
        return trim($matches[1]);
    } elseif (preg_match('/@ai\s+(.*)/i', $text, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function make_clickable_links($text) {
    $text = preg_replace("~(https?://[\w\-\.\?&=/%#]+)~i", '<a href="$1" targt="_blank" class="text-blue-400 underline">$1</a>', $text);
    return $text;
}
// Handle post baru
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_post'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $media_path = null;

    if (!empty($_FILES['media']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $filename = basename($_FILES["media"]["name"]);
        $target_file = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "", $filename);
        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowedTypes = ['jpg','jpeg','png','gif','mp4','webm','ogg'];
        if (!in_array($fileType, $allowedTypes)) {
            $error = "Format file tidak didukung.";
        } else {
            if (move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
                $media_path = $target_file;
            } else {
                $error = "Gagal upload file.";
            }
        }
    }

    if (empty($title)) {
        $error = "Judul harus diisi.";
    } elseif (empty($content) && !$media_path) {
        $error = "Isi post atau upload media minimal satu.";
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, media_path) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $title, $content, $media_path);
            $stmt->execute();
            $stmt->close();
            header("Location: index.php");
            exit;
        } else {
            $error = "Kesalahan pada server. Silakan coba lagi.";
        }
    }
}

// Handle action POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['like_post_id'])) {
        $post_id = (int)$_POST['like_post_id'];
        $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE post_id = post_id");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit;
    }
}

    if (isset($_POST['comment_post_id'])) {
    $post_id = (int)$_POST['comment_post_id'];
    $comment = trim($_POST['comment'] ?? '');
    $parent_id = $_POST['parent_id'] ?? null;
    }
    if (!empty($comment)) {
        // Simpan komentar user
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $post_id, $user_id, $comment, $parent_id);
        $stmt->execute();
        $stmt->close();

        // Cek apakah ada panggilan ke @ai
        if (stripos($comment, '@ai') !== false) {
    if (stripos($comment, '@ai') !== false) {
    // Ambil isi post
        require_once 'ai.php'; // Pastikan file ini tersedia

    $ai_prompt = get_ai_prompt($comment);
    if (!empty($ai_prompt)) {
        $ai_reply = get_ai_reply($ai_prompt);
        $ai_user_id = 9999;
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $post_id, $ai_user_id, $ai_reply, $parent_id);
        $stmt->execute();
        $stmt->close();
    }


    }
    header("Location: index.php");
    exit;
}
echo '<strong>'.($c['user_id'] == 0 ? 'AI' : htmlspecialchars($c['username'])).'</strong>: ';



    if (isset($_POST['delete_post_id'])) {
        $post_id = (int)$_POST['delete_post_id'];
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['like_comment_id'])) {
        $comment_id = (int)$_POST['like_comment_id'];
        $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE comment_id = comment_id");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['edit_post_id'])) {
        $post_id = (int)$_POST['edit_post_id'];
        $new_title = trim($_POST['edit_title'] ?? '');
        $new_content = trim($_POST['edit_content'] ?? '');

        if (!empty($new_title) && (!empty($new_content))) {
            $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $new_title, $new_content, $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['edit_comment_id'])) {
        $comment_id = (int)$_POST['edit_comment_id'];
        $new_content = trim($_POST['edit_comment_content'] ?? '');

        if (!empty($new_content)) {
            $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $new_content, $comment_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: index.php");
        exit;
    }

    if (isset($_POST['reply_comment_id'])) {
        $comment_id = (int)$_POST['reply_comment_id'];
        $reply_content = trim($_POST['reply_content'] ?? '');
        $parent_post_id = (int)$_POST['reply_post_id'];

        if (!empty($reply_content)) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $parent_post_id, $user_id, $reply_content, $comment_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: index.php");
        exit;
    }
}

include 'templates/header.php';

$result = $conn->query("SELECT posts.*, users.username, (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC");

function render_comments($conn, $post_id, $parent_id = null, $level = 0) {
    $stmt = $conn->prepare("
        SELECT comments.*, users.username,
        (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id) AS like_count
        FROM comments JOIN users ON comments.user_id = users.id
        WHERE comments.post_id = ? AND comments.parent_id ".($parent_id === null ? "IS NULL" : "= ?")."
        ORDER BY comments.created_at ASC
    ");

    if ($parent_id === null) {
        $stmt->bind_param("i", $post_id);
    } else {
        $stmt->bind_param("ii", $post_id, $parent_id);
    }

    $stmt->execute();
    $comments = $stmt->get_result();
    while ($c = $comments->fetch_assoc()) {
        echo '<div class="ml-'.($level * 4).' text-sm text-gray-300">';
        if ($c['user_id'] == 9999) {
    echo '<div class="p-2 rounded bg-[#2a2a40] text-sm text-purple-300 border-l-4 border-purple-500">';
    echo '<strong class="text-purple-400">NOURA</strong> <span class="text-xs bg-purple-600 text-white px-1 rounded">AI</span>: ';
    echo make_clickable_links(htmlspecialchars($c['content']));
    echo '</div>';
} else {
    echo '<strong>' . htmlspecialchars($c['username']) . '</strong>: ' . make_clickable_links(htmlspecialchars($c['content']));
}


        echo ' <span class="text-xs text-gray-500 ml-1">• '.htmlspecialchars($c['created_at']).'</span>';
        echo ' <form method="post" class="inline-block ml-2">';
        echo '<input type="hidden" name="like_comment_id" value="'.$c['id'].'">';
        echo '<button type="submit" class="text-xs text-pink-400">♥ '.$c['like_count'].'</button>';
        echo '</form>';

        if ($c['user_id'] == $_SESSION['user_id']) {
            echo '<button type="button" onclick="toggleEditComment('.$c['id'].')" class="text-xs text-yellow-300 ml-2">Edit</button>';
            echo '<form method="post" id="edit-comment-form-'.$c['id'].'" class="hidden mt-1 space-y-1">';
            echo '<input type="hidden" name="edit_comment_id" value="'.$c['id'].'">';
            echo '<textarea name="edit_comment_content" rows="2" class="w-full bg-[#2c2c2c] rounded px-2 py-1 text-sm text-white">'.htmlspecialchars($c['content']).'</textarea>';
            echo '<button type="submit" class="bg-yellow-500 text-white text-xs px-2 py-1 rounded">Simpan</button>';
            echo '</form>';
        }

        echo '<details class="ml-2 mt-1">';
        echo '<summary class="text-xs text-blue-400 cursor-pointer">Balas</summary>';
        echo '<form method="post" class="mt-1 space-y-1">';
        echo '<input type="hidden" name="reply_comment_id" value="'.$c['id'].'">';
        echo '<input type="hidden" name="reply_post_id" value="'.$post_id.'">';
        echo '<textarea name="reply_content" rows="1" class="w-full bg-[#2c2c2c] rounded px-2 py-1 text-sm text-white" placeholder="Balas komentar..."></textarea>';
        echo '<button type="submit" class="bg-gray-700 text-sm px-2 py-1 rounded text-white">Kirim</button>';
        echo '</form>';
        echo '</details>';
        echo '</div>';
        render_comments($conn, $post_id, $c['id'], $level + 1);
    }
    $stmt->close();
}
?>

<main class="p-4 max-w-4xl mx-auto space-y-4">
  <?php while ($row = $result->fetch_assoc()): ?>
    <article class="bg-[#1e1e1e] rounded-md p-4 space-y-2">
      <div class="flex items-center gap-2 text-sm text-gray-400">
        <strong><?= htmlspecialchars($row['username']) ?></strong>
        <span class="text-xs">• <?= htmlspecialchars($row['created_at']) ?></span>
      </div>
      <h2 class="text-lg font-semibold text-white"><?= htmlspecialchars($row['title'] ?? 'Tanpa Judul') ?></h2>

      <?php
        $content = htmlspecialchars($row['content']);
        $short = mb_strimwidth($content, 0, 300, "...");
        $is_long = mb_strlen($content) > 300;
      ?>

      <p class="text-sm whitespace-pre-line break-words">
        <span class="content-preview"><?= $is_long ? $short : $content ?></span>
        <?php if ($is_long): ?>
          <span class="hidden content-full"><?= $content ?></span>
          <button class="text-blue-400 text-xs mt-1 read-more-btn">Baca selengkapnya</button>
        <?php endif; ?>
      </p>

      <?php if ($row['media_path']): ?>
        <?php $ext = strtolower(pathinfo($row['media_path'], PATHINFO_EXTENSION)); ?>
        <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
          <img src="<?= htmlspecialchars($row['media_path']) ?>" class="w-full max-w-md rounded">
        <?php elseif (in_array($ext, ['mp4','webm','ogg'])): ?>
          <video controls class="w-full max-w-md">
            <source src="<?= htmlspecialchars($row['media_path']) ?>" type="video/<?= $ext ?>">
            Your browser does not support the video tag.
          </video>
        <?php endif; ?>
      <?php endif; ?>

      <div class="flex items-center gap-4 mt-2">
        <form method="post" class="inline">
          <input type="hidden" name="like_post_id" value="<?= $row['id'] ?>">
          <button type="submit" class="text-sm text-red-500 hover:underline"><i class="far fa-heart"></i> <?= $row['like_count'] ?> Like</button>
        </form>

        <?php if ($row['user_id'] == $user_id): ?>
          <form method="post" class="inline">
            <button type="button" onclick="toggleEditPost(<?= $row['id'] ?>)" class="text-sm text-yellow-400 hover:underline">Edit</button>
          </form>

          <form method="post" class="inline">
            <input type="hidden" name="delete_post_id" value="<?= $row['id'] ?>">
            <button type="submit" class="text-sm text-gray-400 hover:text-red-500"><i class="fas fa-trash"></i> Hapus</button>
          </form>

          <form method="post" id="edit-post-form-<?= $row['id'] ?>" class="hidden mt-2 space-y-1">
            <input type="hidden" name="edit_post_id" value="<?= $row['id'] ?>">
            <input type="text" name="edit_title" value="<?= htmlspecialchars($row['title']) ?>" class="w-full bg-[#2c2c2c] rounded px-2 py-1 text-white text-sm" required>
            <textarea name="edit_content" rows="3" class="w-full bg-[#2c2c2c] rounded px-2 py-1 text-white text-sm" required><?= htmlspecialchars($row['content']) ?></textarea>
            <button type="submit" class="bg-yellow-500 text-white text-sm px-3 py-1 rounded">Simpan Perubahan</button>
          </form>
        <?php endif; ?>
      </div>

      <form method="post" class="mt-2 space-y-1">
        <input type="hidden" name="comment_post_id" value="<?= $row['id'] ?>">
        <textarea name="comment" rows="1" placeholder="Tambahkan komentar..." class="w-full bg-[#2c2c2c] rounded px-3 py-2 text-sm text-white"></textarea>
        <button type="submit" class="bg-gray-700 text-sm px-3 py-1 rounded text-white">Kirim</button>
      </form>

      <div class="mt-2 space-y-1">
        <?php render_comments($conn, $row['id']); ?>
      </div>
    </article>
  <?php endwhile; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = document.querySelectorAll('.read-more-btn');
  buttons.forEach(btn => {
    btn.addEventListener('click', function () {
      const parent = btn.closest('p');
      const preview = parent.querySelector('.content-preview');
      const full = parent.querySelector('.content-full');

      preview.style.display = 'none';
      full.classList.remove('hidden');

      const closeBtn = document.createElement('button');
      closeBtn.className = 'text-blue-400 text-xs ml-2 close-btn';
      closeBtn.textContent = 'Tutup';
      parent.appendChild(closeBtn);
      btn.style.display = 'none';

      closeBtn.addEventListener('click', function () {
        preview.style.display = '';
        full.classList.add('hidden');
        closeBtn.remove();
        btn.style.display = '';
      });
    });
  });
});

function toggleEditPost(id) {
  const form = document.getElementById('edit-post-form-' + id);
  form.classList.toggle('hidden');
}

function toggleEditComment(id) {
  const form = document.getElementById('edit-comment-form-' + id);
  form.classList.toggle('hidden');
}
</script>