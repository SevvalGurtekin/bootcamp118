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
// Silme işlemi
if (isset($_GET['delete_note'])) {
    $delete_note_id = intval($_GET['delete_note']);
    // Sadece bu velinin kendi eklediği notu silebilsin
    $pdo->prepare('DELETE FROM student_notes WHERE id = ? AND author_type = "parent" AND author_id = ?')
        ->execute([$delete_note_id, $parent_user_id]);
    $msg = 'Gözleminiz silindi!';
}
// Düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note_id'])) {
    $edit_note_id = intval($_POST['edit_note_id']);
    $edit_note = trim($_POST['edit_note']);
    if ($edit_note) {
        $pdo->prepare('UPDATE student_notes SET note = ? WHERE id = ? AND author_type = "parent" AND author_id = ?')
            ->execute([$edit_note, $edit_note_id, $parent_user_id]);
        $msg = 'Gözleminiz güncellendi!';
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Çocuk Detayı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .detail-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 900px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .panel-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .table thead { background: #b2ebf2; }
        @media (max-width: 600px) {
            .detail-card { padding: 1rem 0.5rem; }
            .panel-title { font-size: 1.3rem; }
            .table { font-size: 0.9rem; }
        }
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
            <thead><tr><th>Tarih</th><th>Kim</th><th>Tür</th><th>Not</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach($notes as $n): ?>
                <tr class="<?= $n['author_type'] === 'teacher' ? 'teacher-note' : 'parent-note' ?>">
                    <td><?= htmlspecialchars($n['created_at']) ?></td>
                    <td><?= htmlspecialchars($n['author_name'].' '.$n['author_surname']) ?> (<?= $n['author_type'] === 'teacher' ? 'Öğretmen' : 'Veli' ?>)</td>
                    <td><?= $n['type'] === 'gozlem' ? 'Gözlem' : 'Test' ?></td>
                    <td>
                        <?php if(isset($_GET['edit_note']) && $_GET['edit_note'] == $n['id'] && $n['author_type'] === 'parent' && $n['author_id'] == $parent_user_id): ?>
                            <form method="post" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="edit_note_id" value="<?= $n['id'] ?>">
                                <input type="text" name="edit_note" class="form-control" value="<?= htmlspecialchars($n['note']) ?>" required>
                                <button type="submit" class="btn btn-success btn-sm">Kaydet</button>
                                <a href="?student_id=<?= $student_id ?>" class="btn btn-secondary btn-sm">İptal</a>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($n['note']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($n['author_type'] === 'parent' && $n['author_id'] == $parent_user_id): ?>
                            <a href="?student_id=<?= $student_id ?>&delete_note=<?= $n['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu gözlemi silmek istediğinize emin misiniz?')">Sil</a>
                            <a href="?student_id=<?= $student_id ?>&edit_note=<?= $n['id'] ?>" class="btn btn-warning btn-sm">Düzenle</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 