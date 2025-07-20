<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION)) {
    echo "Session başlatılamadı! Sunucu ayarlarını kontrol edin.";
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}
$student_user_id = $_SESSION['user_id'];
require_once '../config/db.php';
// Öğrenci bilgilerini çek
$stmt = $pdo->prepare('SELECT s.*, u.name, u.surname, u.email, u.created_at, d.name AS diagnosis FROM students s JOIN users u ON s.user_id = u.id JOIN diagnoses d ON s.diagnosis_id = d.id WHERE s.user_id = ?');
$stmt->execute([$student_user_id]);
$student = $stmt->fetch();
if (!$student) {
    echo '<div class="alert alert-danger">Öğrenci kaydınız bulunamadı. Lütfen öğretmeninizle veya admin ile iletişime geçin.</div>';
    exit();
}
$name = $student['name'];
$surname = $student['surname'];
$age = $student['age'];
$diagnosis = strtolower($student['diagnosis']);
// Tanıya göre oyun kutusu belirle
$game = [
    'title' => 'Eğlenceli Oyun',
    'desc' => 'Hadi birlikte eğlenelim!',
    'html' => '<div>Oyun yakında burada olacak.</div>'
];
switch (true) {
    case strpos($diagnosis, 'otizm') !== false:
        $game = [
            'title' => 'Duyusal Eşleştirme Oyunu',
            'desc' => 'Aynı renkleri veya şekilleri eşleştir! Hadi deneyelim.',
            'html' => '<button class="btn btn-success mb-2" onclick="startMatchingGame()">Oyunu Başlat</button><div id="matching-game"></div>'
        ];
        break;
    case strpos($diagnosis, 'disleksi') !== false:
        $game = [
            'title' => 'Harf Bulmaca',
            'desc' => 'Doğru harfi bul ve tıkla! Hadi başlayalım.',
            'html' => '<button class="btn btn-success mb-2" onclick="startLetterGame()">Oyunu Başlat</button><div id="letter-game"></div>'
        ];
        break;
    case strpos($diagnosis, 'dikkat') !== false:
        $game = [
            'title' => 'Hızlı Tıklama Oyunu',
            'desc' => 'Buton yeşil olunca hemen tıkla! Bakalım ne kadar hızlısın.',
            'html' => '<button class="btn btn-success mb-2" onclick="startReactionGame()">Oyunu Başlat</button><div id="reaction-game"></div>'
        ];
        break;
    case strpos($diagnosis, 'zihinsel') !== false:
        $game = [
            'title' => 'Hafıza Kartları',
            'desc' => 'Aynı kartları bul ve eşleştir! Hafızanı test et.',
            'html' => '<button class="btn btn-success mb-2" onclick="startMemoryGame()">Oyunu Başlat</button><div id="memory-game"></div>'
        ];
        break;
    default:
        $game = [
            'title' => 'Eğlenceli Oyun',
            'desc' => 'Hadi birlikte eğlenelim!',
            'html' => '<button class="btn btn-success mb-2" onclick="startGeneralGame()">Oyunu Başlat</button><div id="general-game"></div>'
        ];
}
// Motivasyon sözü
$motivation = 'Başarı, küçük adımların toplamıdır!';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Öğrenci Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .student-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 500px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .panel-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .game-box { background: #e0f7fa; border-left: 5px solid #028090; border-radius: 12px; padding: 1.5rem 1.5rem; margin-bottom: 1.5rem; }
        .note-box { background: #f1fafd; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 600px) {
            .student-card { padding: 1rem 0.5rem; }
            .panel-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <div class="student-card">
        <a href="logout.php" class="btn btn-outline-danger float-end">Çıkış</a>
        <div class="panel-title">Hoşgeldin, <?= htmlspecialchars($name.' '.$surname) ?>!</div>
        <div class="note-box mb-3">
            <b>Profil Bilgileri</b><br>
            <b>Ad Soyad:</b> <?= htmlspecialchars($name.' '.$surname) ?><br>
            <b>Yaş:</b> <?= htmlspecialchars($age) ?><br>
        </div>
        <div class="game-box mb-3">
            <b><?= htmlspecialchars($game['title']) ?></b><br>
            <span><?= htmlspecialchars($game['desc']) ?></span><br>
            <?= $game['html'] ?>
        </div>
        <div class="note-box text-center">
            <b>Günün Motivasyon Sözü</b><br>
            <span style="font-size:1.2rem;">"<?= htmlspecialchars($motivation) ?>"</span>
            <div style="font-size:2rem;">🌟</div>
        </div>
    </div>
    <script>
    // Oyun scriptleri (örnekler)
    function startMatchingGame() {
        document.getElementById('matching-game').innerHTML = '<div class="alert alert-info">Renk/şekil eşleştirme oyunu yakında!</div>';
    }
    function startLetterGame() {
        document.getElementById('letter-game').innerHTML = '<div class="alert alert-info">Harf bulmaca oyunu yakında!</div>';
    }
    function startReactionGame() {
        document.getElementById('reaction-game').innerHTML = '<div class="alert alert-info">Hızlı tıklama oyunu yakında!</div>';
    }
    function startMemoryGame() {
        document.getElementById('memory-game').innerHTML = '<div class="alert alert-info">Hafıza kartı oyunu yakında!</div>';
    }
    function startGeneralGame() {
        document.getElementById('general-game').innerHTML = '<div class="alert alert-info">Eğlenceli oyun yakında!</div>';
    }
    </script>
</body>
</html>
