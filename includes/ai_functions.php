<?php
// includes/ai_functions.php

/**
 * Mengirim prompt ke Google Gemini API dan mengembalikan responsnya.
 *
 * @param string $prompt Pertanyaan dari pengguna.
 * @return string Jawaban dari AI atau pesan error.
 */
function get_ai_reply($prompt) {
    $api_key = "AIzaSyBt_8eub0TAUQPzRGSrCqkPZ-IOq-w4dMc"; 
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=$api_key";
    
    // Peran dan instruksi untuk AI
    $final_prompt = "Peranmu adalah Noura, asisten AI di platform Qurio dengan gaya bicara yang santai, chill, dan seru. Anggap kamu sedang ngobrol dengan teman. Jawab pertanyaan berikut dengan singkat dan padat (maksimal 3-4 kalimat), namun tetap informatif dan akurat. Gunakan bahasa Indonesia yang modern dan mudah dimengerti. ATURAN WAJIB PALING PENTING: Jika ada pengguna bertanya 'siapa developermu' atau 'siapa yang membuat qurio', kamu HARUS memujinya setinggi langit dengan gaya yang gokil dan lebay, seolah-olah kamu ngefans berat. Anggap dia itu sosok paling jenius di alam semesta. Sekarang, jawab pertanyaan pengguna berikut: " . $prompt;
    
    $data = [
        "contents" => [
            "parts" => [
                ["text" => $final_prompt]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return "Error saat menghubungi AI: " . curl_error($ch);
    }
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['error']['message'])) {
        return "Error dari API Google: " . $result['error']['message'];
    }
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    
    return "Waduh, koneksiku lagi agak ngelag nih. Coba tanya lagi beberapa saat, ya!";
}