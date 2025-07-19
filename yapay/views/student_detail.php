<?php


require_once '../config/db.php';
require_once '../config/gemini.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    echo 'Geçersiz öğrenci!';
    exit();
}
// Öğrenci ve veli bilgilerini çek
$stmt = $pdo->prepare('SELECT s.id, u.name, u.surname, s.age, d.name AS diagnosis, pu.name AS parent_name, pu.surname AS parent_surname FROM students s JOIN users u ON s.user_id = u.id JOIN diagnoses d ON s.diagnosis_id = d.id JOIN parents p ON s.parent_id = p.id JOIN users pu ON p.user_id = pu.id WHERE s.id = ?');
$stmt->execute([$student_id]);
$stu = $stmt->fetch();
if (!$stu) {
    echo 'Öğrenci bulunamadı!';
    exit();
}
// Gözlem ve test ekleme
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note']);
    $type = $_POST['type'];
    if ($note && in_array($type, ['gozlem','test'])) {
        $pdo->prepare('INSERT INTO student_notes (student_id, type, note, author_type, author_id, created_at) VALUES (?, ?, ?, "teacher", ?, NOW())')
            ->execute([$student_id, $type, $note, $_SESSION['user_id']]);
        $msg = 'Kayıt eklendi!';
    }
}
// Kayıt düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit'])) {
    $edit_note_id = intval($_POST['edit_note_id']);
    $edit_note = trim($_POST['edit_note']);
    $edit_type = $_POST['edit_type'];
    if ($edit_note && in_array($edit_type, ['gozlem','test'])) {
        $pdo->prepare('UPDATE student_notes SET type = ?, note = ? WHERE id = ? AND author_type = "teacher" AND author_id = ?')
            ->execute([$edit_type, $edit_note, $edit_note_id, $_SESSION['user_id']]);
        $msg = 'Kayıt güncellendi!';
    }
}
// Kayıt silme
if (isset($_GET['delete_note'])) {
    $delete_note_id = intval($_GET['delete_note']);
    $pdo->prepare('DELETE FROM student_notes WHERE id = ? AND author_type = "teacher" AND author_id = ?')
        ->execute([$delete_note_id, $_SESSION['user_id']]);
    $msg = 'Kayıt silindi!';
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
    "Gözlem ve Test Geçmişi (Hem Öğretmen Hem Veli):\n$note_summary\n" .
    "Lütfen bu öğrenci için gelişim raporu, güçlü ve gelişime açık yönler ve öğretmene özel öneriler üret. Raporu başlıklar halinde ve sade Türkçe ile yaz.";
$ai_report = gemini_generate($ai_prompt);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Öğrenci Detay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .detail-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 800px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .ai-box { background: #e0f7fa; border-left: 5px solid #028090; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="detail-card">
        <a href="teacher_panel.php" class="btn btn-outline-secondary float-end">Panele Dön</a>
        <h2>Öğrenci Detayı</h2>
        <div class="row mb-3">
            <div class="col-md-6">
                <b>Ad Soyad:</b> <?= htmlspecialchars($stu['name'].' '.$stu['surname']) ?><br>
                <b>Yaş:</b> <?= htmlspecialchars($stu['age']) ?><br>
                <b>Tanı:</b> <?= htmlspecialchars($stu['diagnosis']) ?><br>
                <b>Veli:</b> <?= htmlspecialchars($stu['parent_name'].' '.$stu['parent_surname']) ?><br>
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
        <h5>Gözlem/Test Ekle</h5>
        <?php if($msg) echo '<div class="alert alert-info">'.$msg.'</div>'; ?>
        <form method="post" class="row g-2 mb-4">
            <div class="col-md-3">
                <select name="type" class="form-select" required>
                    <option value="gozlem">Gözlem</option>
                    <option value="test">Test</option>
                </select>
            </div>
            <div class="col-md-7">
                <input type="text" name="note" class="form-control" placeholder="Gözlem veya test sonucu..." required>
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
                <tr class="<?= $n['author_type'] === 'teacher' ? 'table-warning' : 'table-info' ?>">
                    <td><?= htmlspecialchars($n['created_at']) ?></td>
                    <td><?= htmlspecialchars($n['author_name'].' '.$n['author_surname']) ?> (<?= $n['author_type'] === 'teacher' ? 'Öğretmen' : 'Veli' ?>)</td>
                    <td><?= $n['type'] === 'gozlem' ? 'Gözlem' : 'Test' ?></td>
                    <td><?= htmlspecialchars($n['note']) ?></td>
                    <td>
                        <?php if ($n['author_type'] === 'teacher' && $n['author_id'] == $_SESSION['user_id']): ?>
                            <a href="student_detail.php?student_id=<?= $student_id ?>&delete_note=<?= $n['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kaydı silmek istediğinize emin misiniz?')">Sil</a>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $n['id'] ?>">Düzenle</button>
                            <div class="modal fade" id="editModal<?= $n['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $n['id'] ?>" aria-hidden="true">
                              <div class="modal-dialog">
                                <div class="modal-content">
                                  <form method="post">
                                    <div class="modal-header">
                                      <h5 class="modal-title" id="editModalLabel<?= $n['id'] ?>">Kayıt Düzenle</h5>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                    </div>
                                    <div class="modal-body">
                                      <input type="hidden" name="edit_note_id" value="<?= $n['id'] ?>">
                                      <div class="mb-2">
                                        <select name="edit_type" class="form-select" required>
                                          <option value="gozlem" <?= $n['type']==='gozlem'?'selected':'' ?>>Gözlem</option>
                                          <option value="test" <?= $n['type']==='test'?'selected':'' ?>>Test</option>
                                        </select>
                                      </div>
                                      <div class="mb-2">
                                        <input type="text" name="edit_note" class="form-control" value="<?= htmlspecialchars($n['note']) ?>" required>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                      <button type="submit" name="save_edit" class="btn btn-primary">Kaydet</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 