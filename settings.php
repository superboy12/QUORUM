<?php
function get_ai_reply($prompt) {
    // Ganti dengan API key kamu dari Google AI Studio
    $api_key = "AIzaSyBt_8eub0TAUQPzRGSrCqkPZ-IOq-w4dMc";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$api_key";

    // ==============================================================================
    // ### PROMPT ENGINEERING V2 - INSTRUKSI LEBIH TEGAS & DENGAN CONTOH ###
    // ==============================================================================
    // Kita berikan contoh format jawaban yang kita inginkan (few-shot prompting).
    $system_instruction = <<<EOT
    PERAN: Anda adalah Asisten AI bernama NOURA.

    ATURAN MUTLAK:
    1. Anda HARUS menjawab pertanyaan pengguna dalam **SATU PARAGRAF TUNGGAL** yang mengalir.
    2. Jawaban harus singkat, padat, detail, dan informatif.
    3. DILARANG KERAS menggunakan bullet points, list bernomor, atau memecah jawaban menjadi beberapa paragraf. Seluruh respon harus berupa satu blok teks yang menyatu.

    CONTOH FORMAT JAWABAN YANG BENAR:
    Pertanyaan Pengguna: "Siapa penemu bola lampu?"
    Jawaban Anda: "Penemu bola lampu pijar yang paling dikenal luas dan berhasil secara komersial adalah Thomas Alva Edison, seorang inovator dan pengusaha asal Amerika Serikat. Meskipun banyak ilmuwan lain yang telah berkontribusi pada pengembangan teknologi pencahayaan sebelumnya, Edison berhasil menciptakan versi yang praktis, tahan lama, dan dapat diproduksi massal pada tahun 1879, yang kemudian ia patenkan dan menjadi dasar bagi sistem distribusi listrik modern."

    ---
    Sekarang, jawab pertanyaan pengguna berikut sesuai dengan SEMUA aturan di atas.

    Pertanyaan Pengguna: "$prompt"
    ---

    Jawaban Anda (DALAM SATU PARAGRAF):
    EOT;

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $system_instruction]
                ]
            ]
        ],
        "generationConfig" => [
            // Kita turunkan suhu agar AI lebih patuh pada instruksi dan tidak terlalu kreatif
            "temperature" => 0.5,
            "maxOutputTokens" => 1024,
        ]
    ];
    
    // Untuk debugging, Anda bisa uncomment baris di bawah ini untuk melihat prompt lengkap yang dikirim
    // var_dump($system_instruction); die();

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return "Error cURL: " . curl_error($ch);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        return "Error dari API: " . $result['error']['message'];
    }

    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? "Maaf, saya tidak dapat memberikan respon saat ini.";
    return trim($text);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['ajax'])) {
    $input = json_decode(file_get_contents("php://input"), true);
    $message = $input["message"] ?? "";
    $reply = get_ai_reply($message);
    echo json_encode(["reply" => $reply]);
}