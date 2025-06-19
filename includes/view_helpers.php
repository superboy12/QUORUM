<?php
// includes/view_helpers.php

/**
 * Mengubah URL dalam teks menjadi link HTML yang bisa diklik.
 *
 * @param string $text Teks asli.
 * @return string Teks dengan link HTML.
 */
function make_clickable_links($text) {
    return preg_replace('~(https?://\S+)~i', '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>', $text);
}

/**
 * Menampilkan komentar secara rekursif, lengkap dengan HTML-nya.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $post_id ID dari post.
 * @param int|null $parent_id ID dari komentar induk (untuk balasan).
 * @param int $level Tingkat kedalaman balasan (untuk indentasi).
 */
function render_comments($conn, $post_id, $parent_id = null, $level = 0) {
    global $user_id; // Menggunakan user_id global yang sudah didefinisikan di script pemanggil

    $sql = "SELECT c.*, u.username, u.avatar_url, 
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS like_count, 
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ? AND c.parent_id ".($parent_id === null ? "IS NULL" : "= ?")." 
            ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    if ($parent_id === null) {
        $stmt->bind_param("ii", $user_id, $post_id);
    } else {
        $stmt->bind_param("iii", $user_id, $post_id, $parent_id);
    }
    $stmt->execute();
    $comments = $stmt->get_result();

    while ($c = $comments->fetch_assoc()) {
        echo '<div id="comment-'.$c['id'].'" class="comment-item flex items-start gap-3 ' . ($level > 0 ? 'ml-10' : '') . ' pt-4">';
            echo '<div class="flex-shrink-0">';
            // Cek apakah ini komentar dari AI (user_id 9999)
            if ($c['user_id'] == 9999) {
                echo '<div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-lg">AI</div>';
            } else {
                echo '<a href="profile.php?id='.$c['user_id'].'"><img src="'.htmlspecialchars($c['avatar_url']).'" alt="'.htmlspecialchars($c['username']).'" class="w-8 h-8 rounded-full object-cover"></a>';
            }
            echo '</div>';
            
            echo '<div class="flex-1">';
                // Tampilan khusus untuk komentar AI
                if ($c['user_id'] == 9999) {
                    echo '<div class="comment-content text-sm bg-purple-50 dark:bg-gray-800 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50">';
                        echo '<div class="flex items-center gap-2 mb-2"><strong class="text-purple-600 dark:text-purple-400 font-bold">NOURA</strong><i class="fas fa-check-circle text-purple-500" title="Verified AI Assistant"></i></div>';
                        echo '<p class="comment-text-display text-gray-700 dark:text-gray-300 leading-relaxed">' . make_clickable_links(nl2br(htmlspecialchars($c['content']))) . '</p>';
                    echo '</div>';
                } else {
                    // Tampilan untuk komentar pengguna biasa
                    echo '<div class="comment-content text-sm bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2">';
                        echo '<a href="profile.php?id='.$c['user_id'].'" class="font-semibold text-gray-900 dark:text-white hover:underline">' . htmlspecialchars($c['username']) . '</a>';
                        echo '<p class="comment-text-display text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">' . make_clickable_links(nl2br(htmlspecialchars($c['content']))) . '</p>';
                    echo '</div>';
                }

                // Form Edit, hanya muncul jika komentar milik pengguna yang sedang login
                if ($c['user_id'] == $user_id) {
                    echo '<form class="edit-comment-form hidden mt-2 space-y-2">';
                        echo '<input type="hidden" name="comment_id" value="'.$c['id'].'">';
                        echo '<textarea name="edit_content" rows="2" class="w-full bg-gray-200 dark:bg-gray-700 rounded-md p-2 text-sm">'.htmlspecialchars($c['content']).'</textarea>';
                        echo '<div class="flex items-center gap-2"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded-md font-semibold">Simpan</button><button type="button" class="cancel-edit-btn text-xs text-gray-500 hover:underline">Batal</button></div>';
                    echo '</form>';
                }

                // Tombol Aksi (Suka, Balas, Edit, Hapus)
                echo '<div class="comment-actions text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-4">';
                    echo '<span title="'.date("d M Y, H:i", strtotime($c['created_at'])).'">'.date("d M Y", strtotime($c['created_at'])).'</span>';
                    
                    // Tombol Suka dan Balas tidak ada untuk komentar AI
                    if ($c['user_id'] != 9999) {
                        echo '<button type="button" data-comment-id="'.$c['id'].'" class="font-semibold hover:text-pink-400 transition comment-like-btn '.($c['user_liked'] ? 'text-pink-500' : '').'">Suka (<span class="comment-like-count">'.$c['like_count'].'</span>)</button>';
                        echo '<button type="button" data-target-form="reply-form-'.$c['id'].'" class="font-semibold reply-btn">Balas</button>';
                    }

                    // Tombol Edit dan Hapus hanya untuk pemilik komentar
                    if ($c['user_id'] == $user_id) {
                        echo '<button type="button" class="font-semibold edit-comment-btn">Edit</button>';
                        echo '<button type="button" class="font-semibold delete-comment-btn" data-comment-id="'.$c['id'].'">Hapus</button>';
                    }
                echo '</div>';
                
                // Form Balas yang tersembunyi
                echo '<form class="comment-form hidden mt-2 flex items-center gap-2" id="reply-form-'.$c['id'].'">';
                    echo '<input type="hidden" name="post_id" value="'.$post_id.'">';
                    echo '<input type="hidden" name="parent_id" value="'.$c['id'].'">';
                    echo '<textarea name="comment_text" rows="1" class="comment-input flex-1 w-full bg-gray-200 dark:bg-gray-700 rounded-lg px-3 py-1.5 text-sm" placeholder="Balas kepada '.htmlspecialchars($c['username']).'..."></textarea>';
                    echo '<button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-md font-semibold">Kirim</button>';
                echo '</form>';
                
            echo '</div>'; // penutup .flex-1
        echo '</div>'; // penutup .comment-item

        // Panggilan rekursif untuk menampilkan balasan dari komentar ini
        render_comments($conn, $post_id, $c['id'], $level + 1);
    }
    $stmt->close();
}