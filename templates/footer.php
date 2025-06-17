<hr>
<footer>
    <p>&copy; 2025 Forum Komunitas</p>
</footer>
</body>
</html>
</main> </div> </div> <div id="postModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-[#1e1e1e] p-6 rounded-lg w-full max-w-md relative text-gray-900 dark:text-white">
        <button id="closePostModal" class="absolute top-2 right-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><i class="fas fa-times"></i></button>
        <h2 class="text-lg font-semibold mb-3">Buat Post Baru</h2>
        <form method="post" action="index.php" enctype="multipart/form-data" class="space-y-3" id="postForm">
            <input type="hidden" name="new_post" value="1" />
            <input type="text" name="title" placeholder="Judul post..." class="w-full rounded bg-gray-100 dark:bg-[#2c2c2c] text-gray-900 dark:text-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-600" />
            <textarea name="content" placeholder="Apa yang Anda pikirkan?" rows="4" class="w-full rounded bg-gray-100 dark:bg-[#2c2c2c] text-gray-900 dark:text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 resize-none"></textarea>
            <div id="dropArea" class="w-full border-2 border-dashed border-gray-400 dark:border-gray-500 rounded p-4 text-center cursor-pointer hover:border-red-500 transition">
                <p id="dropText" class="text-sm text-gray-600 dark:text-gray-400">Tarik dan lepaskan gambar/video di sini atau klik untuk memilih</p>
                <input type="file" name="media" id="fileInput" accept="image/*,video/mp4,video/webm" class="hidden">
            </div>
            <button type="submit" name="new_post" class="w-full bg-red-600 hover:bg-red-700 py-2 rounded text-white text-sm font-semibold">Posting</button>
        </form>
    </div>
</div>

<script>
// Fungsi toggle yang spesifik untuk halaman ini
function toggleEditPost(id) { document.getElementById('edit-post-form-' + id).classList.toggle('hidden'); }
function toggleEditComment(id) { document.getElementById('edit-comment-form-' + id).classList.toggle('hidden'); }

// Listener utama yang dijalankan setelah semua halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Logika Menu & Header ---
    const menuBtn = document.getElementById('menuBtn');
    const sideMenu = document.getElementById('sideMenu');

    const applyMenuState = (state) => {
        const mainHeader = document.getElementById('main-header');
        if (state === 'closed') {
            sideMenu.classList.add('w-0', 'p-0', 'opacity-0');
            sideMenu.classList.remove('w-64', 'p-4');
            if (mainHeader) mainHeader.style.left = '0px';
        } else {
            sideMenu.classList.remove('w-0', 'p-0', 'opacity-0');
            sideMenu.classList.add('w-64', 'p-4');
            if (mainHeader) mainHeader.style.left = '256px';
        }
    };

    const currentMenuState = localStorage.getItem('menuState') || 'open';
    applyMenuState(currentMenuState);

    menuBtn?.addEventListener('click', () => {
        const newState = sideMenu.classList.contains('w-0') ? 'open' : 'closed';
        localStorage.setItem('menuState', newState);
        applyMenuState(newState);
    });

    // --- Logika Elemen Header Lainnya ---
    const userBtn = document.getElementById('userBtn');
    const userDropdown = document.getElementById('userDropdown');
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    const header = document.getElementById('main-header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        const headerHeight = header.offsetHeight;
        mainContent.style.paddingTop = headerHeight + 'px';
    }

    userBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
    });

    window.addEventListener('click', function(e){
        if (userBtn && userDropdown && !userBtn.contains(e.target) && !userDropdown.contains(e.target)){
            userDropdown.classList.add('hidden');
        }
    });

    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        themeToggleLightIcon?.classList.remove('hidden'); document.documentElement.classList.add('dark');
    } else {
        themeToggleDarkIcon?.classList.remove('hidden'); document.documentElement.classList.remove('dark');
    }
    
    themeToggleBtn?.addEventListener('click', function() {
        themeToggleDarkIcon.classList.toggle('hidden'); themeToggleLightIcon.classList.toggle('hidden');
        if (localStorage.getItem('color-theme')) {
            if (localStorage.getItem('color-theme') === 'light') { document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark'); }
            else { document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light'); }
        } else {
            if (document.documentElement.classList.contains('dark')) { document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light'); }
            else { document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark'); }
        }
    });

    // --- Logika Modal & Drag-Drop ---
    const postModal = document.getElementById('postModal');
    const openPostBtn = document.getElementById('openPostModal');
    const closePostBtn = document.getElementById('closePostModal');
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const dropText = document.getElementById('dropText');

    openPostBtn?.addEventListener('click', () => postModal.classList.remove('hidden'));
    closePostBtn?.addEventListener('click', () => postModal.classList.add('hidden'));

    if (dropArea) {
        dropArea.addEventListener('click', () => fileInput.click());
        dropArea.addEventListener('dragover', (e) => { e.preventDefault(); dropArea.classList.add('border-red-500'); dropText.textContent = "Lepaskan untuk mengunggah file"; });
        dropArea.addEventListener('dragleave', () => { dropArea.classList.remove('border-red-500'); dropText.textContent = "Tarik dan lepaskan gambar/video di sini atau klik untuk memilih"; });
        const handleFiles = (files) => {
            if (files.length > 0) {
                const acceptedTypes = fileInput.accept.split(',');
                const fileType = files[0].type;
                let isValid = acceptedTypes.some(type => { if (type.includes('/*')) { return fileType.startsWith(type.replace('/*', '')); } return type.trim() === fileType; });
                if (isValid) { fileInput.files = files; dropText.textContent = `File dipilih: ${files[0].name}`; } else { dropText.textContent = "Format file tidak diizinkan."; }
            }
        };
        dropArea.addEventListener('drop', (e) => { e.preventDefault(); dropArea.classList.remove('border-red-500'); handleFiles(e.dataTransfer.files); });
        fileInput.addEventListener('change', () => { handleFiles(fileInput.files); });
    }

    // --- Logika Suka (Like) ---
    document.addEventListener('click', function(e) {
        const likeButton = e.target.closest('.like-btn');
        if (likeButton) {
            e.preventDefault();
            const postId = likeButton.dataset.postId;
            const countSpan = likeButton.querySelector('.like-count');
            const isCurrentlyLiked = likeButton.classList.contains('text-red-500');
            const currentCount = parseInt(countSpan.textContent);
            
            likeButton.classList.toggle('text-red-500');
            countSpan.textContent = isCurrentlyLiked ? currentCount - 1 : currentCount + 1;

            fetch('action_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'like_post', post_id: postId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    likeButton.classList.toggle('text-red-500', isCurrentlyLiked);
                    countSpan.textContent = currentCount;
                } else {
                    countSpan.textContent = data.new_like_count;
                    likeButton.classList.toggle('text-red-500', data.is_liked);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                likeButton.classList.toggle('text-red-500', isCurrentlyLiked);
                countSpan.textContent = currentCount;
            });
        }
    });
});
</script>

</body>
</html>