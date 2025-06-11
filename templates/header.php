<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ngorum</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-[#121212] text-white font-sans">

<header class="bg-[#1e1e1e] px-4 py-3 flex items-center justify-between border-b border-gray-700">
  <div class="text-red-600 font-bold text-xl">Ambarum</div>

  <div class="flex items-center gap-4">
    <!-- Search -->
    <form action="search.php" method="get" class="relative">
      <input type="text" name="q" placeholder="Cari..." class="bg-[#2c2c2c] text-white text-sm rounded px-3 py-1 focus:outline-none" />
    </form>

    <!-- Notifikasi -->
    <div class="relative">
      <button id="notifBtn" class="text-white hover:text-red-500 text-lg">
        <i class="fas fa-bell"></i>
      </button>
      <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-64 bg-[#2c2c2c] border border-gray-700 rounded shadow-lg z-50">
        <div class="p-3 text-sm text-gray-300">Belum ada notifikasi baru.</div>
      </div>
    </div>

    <!-- Tombol Buat Post -->
    <button id="openPostModal" class="bg-red-600 hover:bg-red-700 text-sm px-3 py-1 rounded text-white font-semibold">
      <i class="fas fa-plus"></i> Buat Post
    </button>

    <!-- Logout -->
    <a href="logout.php" class="text-sm text-red-500 hover:underline">Logout</a>
  </div>
</header>

<!-- Modal Post -->
<div id="postModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
  <div class="bg-[#1e1e1e] p-6 rounded-lg w-full max-w-md relative">
    <button id="closePostModal" class="absolute top-2 right-2 text-gray-400 hover:text-white">
      <i class="fas fa-times"></i>
    </button>
    <h2 class="text-lg font-semibold mb-3">Buat Post Baru</h2>
    <form method="post" action="index.php" enctype="multipart/form-data" class="space-y-3" id="postForm">
      <input type="hidden" name="new_post" value="1" />
      <input type="text" name="title" placeholder="Judul post..." 
             class="w-full rounded bg-[#2c2c2c] text-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-600" />

      <textarea name="content" placeholder="Apa yang Anda pikirkan?" rows="4" 
                class="w-full rounded bg-[#2c2c2c] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 resize-none"></textarea>
      
      <!-- Drag & Drop Upload -->
      <div id="dropArea" 
           class="w-full border-2 border-dashed border-gray-500 rounded p-4 text-center cursor-pointer hover:border-red-500 transition">
        <p id="dropText" class="text-sm text-gray-400">Tarik dan lepaskan gambar di sini atau klik untuk memilih</p>
        <input type="file" name="media" id="fileInput" accept="image/*" class="hidden">
      </div>

      <button type="submit" name="new_post" 
              class="w-full bg-red-600 hover:bg-red-700 py-2 rounded text-white text-sm font-semibold">
        Posting
      </button>
    </form>
  </div>
</div>

<!-- Script Modal dan Notifikasi -->
<script>
  const postModal = document.getElementById('postModal');
  const openPostBtn = document.getElementById('openPostModal');
  const closePostBtn = document.getElementById('closePostModal');
  const notifBtn = document.getElementById('notifBtn');
  const notifDropdown = document.getElementById('notifDropdown');

  openPostBtn.addEventListener('click', () => postModal.classList.remove('hidden'));
  closePostBtn.addEventListener('click', () => postModal.classList.add('hidden'));
  notifBtn.addEventListener('click', () => notifDropdown.classList.toggle('hidden'));
</script>

<!-- Script Drag & Drop Upload -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const dropText = document.getElementById('dropText');

    dropArea.addEventListener('click', () => fileInput.click());

    dropArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropArea.classList.add('border-red-500');
      dropText.textContent = "Lepaskan untuk mengunggah gambar";
    });

    dropArea.addEventListener('dragleave', () => {
      dropArea.classList.remove('border-red-500');
      dropText.textContent = "Tarik dan lepaskan gambar di sini atau klik untuk memilih";
    });

    dropArea.addEventListener('drop', (e) => {
      e.preventDefault();
      dropArea.classList.remove('border-red-500');
      const files = e.dataTransfer.files;

      if (files.length > 0 && files[0].type.startsWith('image/')) {
        fileInput.files = files;
        dropText.textContent = `Gambar dipilih: ${files[0].name}`;
      } else {
        dropText.textContent = "Hanya gambar yang diperbolehkan";
      }
    });
  });
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("postForm"); // Ganti sesuai ID form
    const input = document.querySelector("textarea[name='content']"); // Ganti sesuai ID input/textarea
    const output = document.getElementById("aiResponse"); // Buat elemen ini untuk menampilkan jawaban

    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const text = input.value;
        const match = text.match(/@AI\((.*?)\)/i);
        if (match) {
            const question = match[1];
            const res = await fetch("ai.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message: question })
            });
            const data = await res.json();
            output.innerText = "🤖 " + data.reply;
        }
    });
});
</script>
<div id="aiResponse" style="margin-top: 10px; color: green; font-style: italic;"></div>


