<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: login.php');
    exit();
}
$parent_user_id = $_SESSION['user_id'];
// Veli id'sini bul
$stmt = $pdo->prepare('SELECT id FROM parents WHERE user_id = ?');
$stmt->execute([$parent_user_id]);
$parent_id = $stmt->fetchColumn();
// Velinin çocuklarını çek
$children = $pdo->prepare('SELECT s.id, u.name, u.surname, s.age, d.name AS diagnosis, t.id AS teacher_id, tu.name AS teacher_name, tu.surname AS teacher_surname FROM students s JOIN users u ON s.user_id = u.id JOIN diagnoses d ON s.diagnosis_id = d.id JOIN teachers t ON s.teacher_id = t.id JOIN users tu ON t.user_id = tu.id WHERE s.parent_id = ?');
$children->execute([$parent_id]);
$children = $children->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Veli Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .panel-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 900px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .panel-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .table thead { background: #b2ebf2; }
        @media (max-width: 600px) {
            .panel-card { padding: 1rem 0.5rem; }
            .panel-title { font-size: 1.3rem; }
            .table { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="panel-card">
        <a href="logout.php" class="btn btn-outline-danger float-end">Çıkış</a>
        <div class="panel-title">Veli Paneli</div>
        <div class="alert alert-info">
            Hoşgeldiniz! Aşağıda çocuğunuzun/çocuklarınızın bilgileri ve öğretmenleri görünmektedir.
        </div>
        <h5>Çocuklarım</h5>
        <?php if (count($children) > 0): ?>
            <table class="table table-bordered align-middle">
                <thead><tr><th>Ad</th><th>Soyad</th><th>Yaş</th><th>Tanı</th><th>Öğretmen</th><th>Detay</th></tr></thead>
                <tbody>
                <?php foreach($children as $child): ?>
                    <tr>
                        <td><?= htmlspecialchars($child['name']) ?></td>
                        <td><?= htmlspecialchars($child['surname']) ?></td>
                        <td><?= htmlspecialchars($child['age']) ?></td>
                        <td><?= htmlspecialchars($child['diagnosis']) ?></td>
                        <td><?= htmlspecialchars($child['teacher_name'].' '.$child['teacher_surname']) ?></td>
                        <td><a href="parent_student_detail.php?student_id=<?= $child['id'] ?>" class="btn btn-info btn-sm">Detay</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                Henüz çocuğunuz sisteme eklenmemiş. Öğretmeninizle iletişime geçin.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
