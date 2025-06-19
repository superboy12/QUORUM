<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Qurio - Forum</title>
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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark text-gray-800 dark:text-gray-200 font-sans">

<?php 
// Kita panggil komponen header navigasi yang sesungguhnya di sini
require_once __DIR__ . '/header_component.php'; 
?>