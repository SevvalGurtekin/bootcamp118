<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}
$student_user_id = $_SESSION['user_id'];

// Öğrenci bilgilerini çek
$stmt = $pdo->prepare('SELECT s.*, u.name, u.surname, u.email, u.created_at, d.name AS diagnosis, t.id AS teacher_id, tu.name AS teacher_name, tu.surname AS teacher_surname, p.id AS parent_id, pu.name AS parent_name, pu.surname AS parent_surname
FROM students s
JOIN users u ON s.user_id = u.id
JOIN diagnoses d ON s.diagnosis_id = d.id
LEFT JOIN teachers t ON s.teacher_id = t.id
LEFT JOIN users tu ON t.user_id = tu.id
LEFT JOIN parents p ON s.parent_id = p.id
LEFT JOIN users pu ON p.user_id = pu.id
WHERE s.user_id = ?');
$stmt->execute([$student_user_id]);
$student = $stmt->fetch();

// Gözlem ve testleri çek
$notes = $pdo->prepare('SELECT sn.*, u.name AS author_name, u.surname AS author_surname, sn.author_type FROM student_notes sn JOIN users u ON sn.author_id = u.id WHERE sn.student_id = ? ORDER BY sn.created_at DESC');
$notes->execute([$student['id']]);
$notes = $notes->fetchAll();

// AI etkinlik önerisi (örnek prompt)
require_once '../config/gemini.php';
$note_summary = '';
foreach ($notes as $n) {
    $note_summary .= "- {$n['type']}: {$n['note']} ({$n['author_type']} - {$n['author_name']} {$n['author_surname']})\n";
}
$ai_prompt = "Aşağıda bir öğrencinin özel eğitim gelişim verileri bulunmaktadır.\n" .
    "Adı Soyadı: {$student['name']} {$student['surname']}\n" .
    "Yaşı: {$student['age']}\n" .
    "Tanısı: {$student['diagnosis']}\n" .
    "Gözlem ve Test Geçmişi (Hem Öğretmen Hem Veli):\n$note_summary\n" .
    "Lütfen bu öğrenci için gelişimini destekleyecek, eğlenceli ve yaratıcı 3 etkinlik öner. Her etkinliği başlık ve açıklama ile yaz. Türkçe, sade ve uygulanabilir olsun.";
$ai_activities = gemini_generate($ai_prompt);

// Başarılar (örnek)
$achievements = [
    ['title' => 'İlk Gözlem', 'desc' => 'İlk gözlemini başarıyla ekledin!', 'icon' => '🎉'],
    ['title' => 'Etkinlik Tamamlandı', 'desc' => 'Bir AI etkinliğini tamamladın!', 'icon' => '🏅'],
    // ... daha fazla rozet eklenebilir
];

// Kendi notları (isteğe bağlı)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['my_note'])) {
    $my_note = trim($_POST['my_note']);
    $_SESSION['my_note'] = $my_note;
}
$my_note = $_SESSION['my_note'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Öğrenci Paneli</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .student-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 900px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .panel-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .ai-box { background: #e0f7fa; border-left: 5px solid #028090; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
        .achievement { background: #fff3cd; border-left: 5px solid #ffc107; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; }
        .achievement-icon { font-size: 2rem; margin-right: 1rem; }
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
        <div class="panel-title">Hoşgeldin, <?= htmlspecialchars($student['name']) ?>!</div>
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="note-box">
                    <b>Profil Bilgileri</b><br>
                    <b>Ad Soyad:</b> <?= htmlspecialchars($student['name'].' '.$student['surname']) ?><br>
                    <b>Yaş:</b> <?= htmlspecialchars($student['age']) ?><br>
                    <b>Tanı:</b> <?= htmlspecialchars($student['diagnosis']) ?><br>
                    <b>Öğretmen:</b> <?= htmlspecialchars($student['teacher_name'].' '.$student['teacher_surname']) ?><br>
                    <b>Veli:</b> <?= htmlspecialchars($student['parent_name'].' '.$student['parent_surname']) ?><br>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="note-box">
                    <b>Başarılarım</b>
                    <?php foreach($achievements as $ach): ?>
                        <div class="achievement">
                            <span class="achievement-icon"><?= $ach['icon'] ?></span>
                            <div>
                                <b><?= htmlspecialchars($ach['title']) ?></b><br>
                                <span><?= htmlspecialchars($ach['desc']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="ai-box">
            <b>Yapay Zeka Etkinlik Önerileri:</b><br>
            <pre style="white-space: pre-wrap; background: none; border: none; padding: 0; margin: 0; font-family: inherit;"><?= htmlspecialchars($ai_activities) ?></pre>
        </div>
        <div class="note-box">
            <b>Gözlem ve Testler</b>
            <?php if (count($notes) > 0): ?>
                <ul>
                <?php foreach($notes as $n): ?>
                    <li>
                        <b><?= htmlspecialchars($n['type']) ?>:</b>
                        <?= htmlspecialchars($n['note']) ?>
                        <small>(<?= htmlspecialchars($n['author_type']) ?> - <?= htmlspecialchars($n['author_name'].' '.$n['author_surname']) ?>)</small>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="alert alert-warning">Henüz gözlem veya test eklenmemiş.</div>
            <?php endif; ?>
        </div>
        <div class="note-box">
            <b>Kendi Notlarım</b>
            <form method="post" class="mb-2">
                <textarea name="my_note" class="form-control" rows="2" placeholder="Bugün ne hissediyorsun? Hedefin ne?"><?= htmlspecialchars($my_note) ?></textarea>
                <button type="submit" class="btn btn-primary mt-2">Kaydet</button>
            </form>
            <?php if($my_note): ?>
                <div class="alert alert-info">Notun: <?= htmlspecialchars($my_note) ?></div>
            <?php endif; ?>
        </div>
        <div class="note-box text-center">
            <b>Günün Motivasyon Sözü</b><br>
            <span style="font-size:1.2rem;">"Başarı, küçük adımların toplamıdır!"</span>
            <div style="font-size:2rem;">🌟</div>
        </div>
    </div>
</body>
</html>
