<?php
function get_ai_reply($prompt) {
    $api_key = "AIzaSyBt_8eub0TAUQPzRGSrCqkPZ-IOq-w4dMc"; // Ganti dengan API key kamu
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return "Error: " . curl_error($ch);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? "Tidak ada respon dari AI.";
    return $text;
}

// Opsional: handle POST request jika ingin akses via AJAX juga
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['ajax'])) {
    $input = json_decode(file_get_contents("php://input"), true);
    $message = $input["message"] ?? "";
    $reply = get_ai_reply($message);
    echo json_encode(["reply" => $reply]);
}
