<?php
require_once '../config/db.php';
require_once '../config/gemini.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: login.php');
    exit();
}
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$parent_user_id = $_SESSION['user_id'];
if (!$student_id) {
    echo 'Geçersiz öğrenci!';
    exit();
}
// Veli id'sini bul
$stmt = $pdo->prepare('SELECT id FROM parents WHERE user_id = ?');
$stmt->execute([$parent_user_id]);
$parent_id = $stmt->fetchColumn();
// Öğrenci ve veli bilgilerini çek (sadece velinin kendi çocuğu)
$stmt = $pdo->prepare('SELECT s.id, u.name, u.surname, s.age, d.name AS diagnosis, t.id AS teacher_id, tu.name AS teacher_name, tu.surname AS teacher_surname FROM students s JOIN users u ON s.user_id = u.id JOIN diagnoses d ON s.diagnosis_id = d.id JOIN teachers t ON s.teacher_id = t.id JOIN users tu ON t.user_id = tu.id WHERE s.id = ? AND s.parent_id = ?');
$stmt->execute([$student_id, $parent_id]);
$stu = $stmt->fetch();
if (!$stu) {
    echo 'Bu öğrenciye erişim yetkiniz yok!';
    exit();
}
// Veli gözlem ekleme
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note']);
    if ($note) {
        $pdo->prepare('INSERT INTO student_notes (student_id, type, note, author_type, author_id, created_at) VALUES (?, "gozlem", ?, "parent", ?, NOW())')
            ->execute([$student_id, $note, $parent_user_id]);
        $msg = 'Gözleminiz eklendi!';
    }
}
// Tüm gözlem ve testleri çek (hem öğretmen hem veli)
$notes = $pdo->prepare('SELECT sn.*, u.name AS author_name, u.surname AS author_surname FROM student_notes sn LEFT JOIN users u ON sn.author_id = u.id WHERE sn.student_id = ? ORDER BY sn.created_at DESC');
$notes->execute([$student_id]);
$notes = $notes->fetchAll();
// Gelişim grafiği için veri hazırla
$chart_labels = [];
$chart_data = [];
foreach (array_reverse($notes) as $i => $n) {
    $chart_labels[] = date('d.m', strtotime($n['created_at']));
    if ($n['type'] === 'test' && is_numeric($n['note'])) {
        $chart_data[] = (float)$n['note'];
    } else {
        $chart_data[] = 1;
    }
}
$chart_labels = array_reverse($chart_labels);
$chart_data = array_reverse($chart_data);
$chart_url = 'https://quickchart.io/chart?c=' . urlencode(json_encode([
    'type' => 'line',
    'data' => [
        'labels' => $chart_labels,
        'datasets' => [[
            'label' => 'Gelişim',
            'data' => $chart_data,
            'fill' => false,
            'borderColor' => '#028090',
            'backgroundColor' => '#028090',
        ]]
    ]
]));
// --- Gemini API ile otomatik öneri ve gelişim raporu ---
$note_summary = "";
foreach ($notes as $n) {
    $author = $n['author_type'] === 'teacher' ? 'Öğretmen' : 'Veli';
    $note_summary .= "$author ({$n['author_name']} {$n['author_surname']}): " . ($n['type'] === 'gozlem' ? 'Gözlem: ' : 'Test: ') . $n['note'] . ' (' . $n['created_at'] . ")\n";
}
$ai_prompt = "Aşağıda bir öğrencinin özel eğitim gelişim verileri bulunmaktadır.\n" .
    "Adı Soyadı: {$stu['name']} {$stu['surname']}\n" .
    "Yaşı: {$stu['age']}\n" .
    "Tanısı: {$stu['diagnosis']}\n" .
    "Öğretmen: {$stu['teacher_name']} {$stu['teacher_surname']}\n" .
    "Gözlem ve Test Geçmişi (Hem Öğretmen Hem Veli):\n$note_summary\n" .
    "Lütfen bu öğrenci için gelişim raporu, güçlü ve gelişime açık yönler ve veliye özel öneriler üret. Raporu başlıklar halinde ve sade Türkçe ile yaz.";
$ai_report = gemini_generate($ai_prompt);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Öğrenci Detay - Veli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .detail-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 800px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .ai-box { background: #e0f7fa; border-left: 5px solid #028090; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
        .teacher-note { background: #fff3cd; border-left: 3px solid #ffc107; }
        .parent-note { background: #d1ecf1; border-left: 3px solid #17a2b8; }
    </style>
</head>
<body>
    <div class="detail-card">
        <a href="parent_panel.php" class="btn btn-outline-secondary float-end">Panele Dön</a>
        <h2>Çocuğumun Detayı</h2>
        <div class="row mb-3">
            <div class="col-md-6">
                <b>Ad Soyad:</b> <?= htmlspecialchars($stu['name'].' '.$stu['surname']) ?><br>
                <b>Yaş:</b> <?= htmlspecialchars($stu['age']) ?><br>
                <b>Tanı:</b> <?= htmlspecialchars($stu['diagnosis']) ?><br>
                <b>Öğretmen:</b> <?= htmlspecialchars($stu['teacher_name'].' '.$stu['teacher_surname']) ?><br>
            </div>
            <div class="col-md-6">
                <b>Gelişim Grafiği:</b><br>
                <img src="<?= $chart_url ?>" alt="Gelişim Grafiği" class="img-fluid rounded">
            </div>
        </div>
        <div class="ai-box">
            <b>Yapay Zeka Gelişim Raporu ve Öneriler:</b><br>
            <pre style="white-space: pre-wrap; background: none; border: none; padding: 0; margin: 0; font-family: inherit;"><?= htmlspecialchars($ai_report) ?></pre>
        </div>
        <h5>Gözlem Ekle</h5>
        <?php if($msg) echo '<div class="alert alert-info">'.$msg.'</div>'; ?>
        <form method="post" class="row g-2 mb-4">
            <div class="col-md-10">
                <input type="text" name="note" class="form-control" placeholder="Çocuğunuzla ilgili gözleminizi yazın..." required>
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_note" class="btn btn-primary w-100">Ekle</button>
            </div>
        </form>
        <h5>Gözlem ve Testler</h5>
        <table class="table table-striped">
            <thead><tr><th>Tarih</th><th>Kim</th><th>Tür</th><th>Not</th></tr></thead>
            <tbody>
            <?php foreach($notes as $n): ?>
                <tr class="<?= $n['author_type'] === 'teacher' ? 'teacher-note' : 'parent-note' ?>">
                    <td><?= htmlspecialchars($n['created_at']) ?></td>
                    <td><?= htmlspecialchars($n['author_name'].' '.$n['author_surname']) ?> (<?= $n['author_type'] === 'teacher' ? 'Öğretmen' : 'Veli' ?>)</td>
                    <td><?= $n['type'] === 'gozlem' ? 'Gözlem' : 'Test' ?></td>
                    <td><?= htmlspecialchars($n['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 