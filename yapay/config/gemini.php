<?php
// Gemini API anahtarını buraya ekle
$GEMINI_API_KEY = 'AIzaSyCjPZXf012UGMDYsrv6bTELxuxW44i6wm0'; // <-- Buraya kendi anahtarını gir

function gemini_generate($prompt) {
    global $GEMINI_API_KEY;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    $data = [
        'contents' => [
            ['parts' => [ ['text' => $prompt] ] ]
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $GEMINI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return 'Yapay zeka servisine erişilemedi.';
    }
    $json = json_decode($result, true);
    curl_close($ch);
    return $json['candidates'][0]['content']['parts'][0]['text'] ?? 'Yapay zeka yanıtı alınamadı.';
} 