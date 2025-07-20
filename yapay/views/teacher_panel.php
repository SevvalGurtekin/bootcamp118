<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$teacher_user_id = $_SESSION['user_id'];
$msg = '';
$student_password = '';
$parent_password = '';
// Tanı listesini çek
$diagnoses = $pdo->query('SELECT * FROM diagnoses')->fetchAll();
// Öğretmen id'sini bul
$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$teacher_user_id]);
$teacher_id = $stmt->fetchColumn();
// Öğrenci ve veli ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $s_name = $_POST['s_name'];
    $s_surname = $_POST['s_surname'];
    $s_age = $_POST['s_age'];
    $diagnosis_id = $_POST['diagnosis_id'];
    $s_email = $_POST['s_email'];
    $parent_name = $_POST['p_name'];
    $parent_surname = $_POST['p_surname'];
    $parent_email = $_POST['p_email'];
    $parent_phone = $_POST['p_phone'];
    $student_password = substr(md5(uniqid()),0,8);
    $parent_password = substr(md5(uniqid()),0,8);
    $student_hash = password_hash($student_password, PASSWORD_DEFAULT);
    $parent_hash = password_hash($parent_password, PASSWORD_DEFAULT);
    // Veli zaten var mı?
    $check = $pdo->prepare('SELECT p.id as parent_id, p.user_id as parent_user_id FROM parents p JOIN users u ON p.user_id = u.id WHERE u.email=? AND u.role="parent"');
    $check->execute([$parent_email]);
    $parentRow = $check->fetch(PDO::FETCH_ASSOC);
    if ($parentRow) {
        // Veli zaten var, yeni parent kaydı açma
        $parent_id = $parentRow['parent_id'];
        $parent_user_id = $parentRow['parent_user_id'];
        $pdo->beginTransaction();
        try {
            // Öğrenci ekle (users + students)
            $pdo->prepare('INSERT INTO users (name, surname, email, password, role, status) VALUES (?, ?, ?, ?, "student", "active")')
                ->execute([$s_name, $s_surname, $s_email, $student_hash]);
            $student_user_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO students (user_id, age, diagnosis_id, teacher_id, parent_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$student_user_id, $s_age, $diagnosis_id, $teacher_id, $parent_id]);
            $student_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO teacher_student_parent (teacher_id, student_id, parent_id) VALUES (?, ?, ?)')
                ->execute([$teacher_id, $student_id, $parent_id]);
            $pdo->commit();
            $msg = 'Öğrenci mevcut veliye başarıyla eklendi!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
        }
    } else {
        // Veli yok, yeni user ve parent kaydı oluştur
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO users (name, surname, email, password, role, status) VALUES (?, ?, ?, ?, "parent", "active")')
                ->execute([$parent_name, $parent_surname, $parent_email, $parent_hash]);
            $parent_user_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO parents (user_id) VALUES (?)')->execute([$parent_user_id]);
            $parent_id = $pdo->lastInsertId();
            // Öğrenci ekle (users + students)
            $pdo->prepare('INSERT INTO users (name, surname, email, password, role, status) VALUES (?, ?, ?, ?, "student", "active")')
                ->execute([$s_name, $s_surname, $s_email, $student_hash]);
            $student_user_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO students (user_id, age, diagnosis_id, teacher_id, parent_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$student_user_id, $s_age, $diagnosis_id, $teacher_id, $parent_id]);
            $student_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO teacher_student_parent (teacher_id, student_id, parent_id) VALUES (?, ?, ?)')
                ->execute([$teacher_id, $student_id, $parent_id]);
            $pdo->commit();
            $msg = 'Öğrenci ve veli başarıyla eklendi!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}
// Öğrenci silme işlemi
if (isset($_GET['delete_student'])) {
    $student_id = intval($_GET['delete_student']);
    try {
        $pdo->beginTransaction();
        // Öğrencinin parent_id'sini bul
        $stuQ = $pdo->prepare('SELECT user_id, parent_id FROM students WHERE id=?');
        $stuQ->execute([$student_id]);
        $stuRow = $stuQ->fetch(PDO::FETCH_ASSOC);
        $stu_user_id = $stuRow['user_id'] ?? null;
        $parent_id = $stuRow['parent_id'] ?? null;
        // İlişki tablosundan sil
        $pdo->prepare('DELETE FROM teacher_student_parent WHERE student_id=?')->execute([$student_id]);
        // Notlardan sil
        $pdo->prepare('DELETE FROM student_notes WHERE student_id=?')->execute([$student_id]);
        // Students tablosundan sil
        $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$student_id]);
        // Users tablosundan sil (öğrenci)
        if ($stu_user_id) {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$stu_user_id]);
        }
        // Eğer parent_id varsa ve bu parent_id'ye bağlı başka öğrenci yoksa veliyi de sil
        if ($parent_id) {
            $otherStudentQ = $pdo->prepare('SELECT COUNT(*) FROM students WHERE parent_id=?');
            $otherStudentQ->execute([$parent_id]);
            $otherCount = $otherStudentQ->fetchColumn();
            if ($otherCount == 0) {
                // parent'ın user_id'sini bul
                $parentUserQ = $pdo->prepare('SELECT user_id FROM parents WHERE id=?');
                $parentUserQ->execute([$parent_id]);
                $parent_user_id = $parentUserQ->fetchColumn();
                // teacher_student_parent tablosundan parent ilişkilerini sil
                $pdo->prepare('DELETE FROM teacher_student_parent WHERE parent_id=?')->execute([$parent_id]);
                // parents tablosundan sil
                $pdo->prepare('DELETE FROM parents WHERE id=?')->execute([$parent_id]);
                // users tablosundan sil (veli)
                if ($parent_user_id) {
                    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$parent_user_id]);
                }
            }
        }
        $pdo->commit();
        $msg = 'Öğrenci ve veli bilgileri başarıyla silindi!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = 'Öğrenci silinemedi: ' . $e->getMessage();
    }
}
// Öğretmenin öğrencilerini ve velilerini çek
$students = $pdo->prepare('SELECT s.id, u.name, u.surname, s.age, d.name AS diagnosis, p.id AS parent_id, pu.name AS parent_name, pu.surname AS parent_surname FROM students s JOIN users u ON s.user_id = u.id JOIN diagnoses d ON s.diagnosis_id = d.id JOIN parents p ON s.parent_id = p.id JOIN users pu ON p.user_id = pu.id WHERE s.teacher_id = ?');
$students->execute([$teacher_id]);
$students = $students->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Öğretmen Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .panel-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 900px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .panel-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .btn-primary { background: #046E8F; border: none; font-weight: bold; font-size: 1.1rem; border-radius: 12px; transition: background 0.2s; }
        .btn-primary:hover { background: #028090; }
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
        <div class="panel-title">Öğretmen Paneli</div>
        <div class="mb-4">
            <h5>Öğrenci ve Veli Ekle</h5>
            <?php if($msg) echo '<div class="alert alert-info">'.$msg.'</div>'; ?>
            <?php if($student_password && $parent_password): ?>
                <div class="alert alert-success">Öğrenci Şifresi: <b><?= $student_password ?></b><br>Veli Şifresi: <b><?= $parent_password ?></b></div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="add_student" value="1">
                <div class="col-12"><h6 class="mb-2 mt-2">Öğrenci Bilgileri</h6></div>
                <div class="col-md-6">
                    <label for="s_name" class="form-label">Öğrenci Adı <span class="text-danger">*</span></label>
                    <input type="text" name="s_name" id="s_name" class="form-control" placeholder="Örneğin: Sevim" required>
                </div>
                <div class="col-md-6">
                    <label for="s_surname" class="form-label">Öğrenci Soyadı <span class="text-danger">*</span></label>
                    <input type="text" name="s_surname" id="s_surname" class="form-control" placeholder="Örneğin: Yıldız" required>
                </div>
                <div class="col-md-4">
                    <label for="s_age" class="form-label">Yaş <span class="text-danger">*</span></label>
                    <input type="number" name="s_age" id="s_age" class="form-control" placeholder="Örneğin: 8" min="1" required>
                </div>
                <div class="col-md-8">
                    <label for="s_email" class="form-label">Öğrenci E-posta <span class="text-danger">*</span></label>
                    <input type="email" name="s_email" id="s_email" class="form-control" placeholder="ogrenci@email.com" required>
                </div>
                <div class="col-md-12">
                    <label for="diagnosis_id" class="form-label">Tanı <span class="text-danger">*</span></label>
                    <select name="diagnosis_id" id="diagnosis_id" class="form-select" required>
                        <option value="">Tanı Seçiniz</option>
                        <?php foreach($diagnoses as $diag): ?>
                            <option value="<?= $diag['id'] ?>"><?= htmlspecialchars($diag['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><h6 class="mb-2 mt-4">Veli Bilgileri</h6></div>
                <div class="col-md-6">
                    <label for="p_name" class="form-label">Veli Adı <span class="text-danger">*</span></label>
                    <input type="text" name="p_name" id="p_name" class="form-control" placeholder="Örneğin: Ayşe" required>
                </div>
                <div class="col-md-6">
                    <label for="p_surname" class="form-label">Veli Soyadı <span class="text-danger">*</span></label>
                    <input type="text" name="p_surname" id="p_surname" class="form-control" placeholder="Örneğin: Demir" required>
                </div>
                <div class="col-md-6">
                    <label for="p_email" class="form-label">Veli E-posta <span class="text-danger">*</span></label>
                    <input type="email" name="p_email" id="p_email" class="form-control" placeholder="veli@email.com" required>
                </div>
                <div class="col-md-6">
                    <label for="p_phone" class="form-label">Veli Telefon <span class="text-danger">*</span></label>
                    <input type="text" name="p_phone" id="p_phone" class="form-control" placeholder="05xx xxx xx xx" required>
                </div>
                <div class="col-12"><button type="submit" class="btn btn-primary w-100 mt-3">Öğrenci ve Veli Ekle</button></div>
            </form>
        </div>
        <h5>Eklenen Öğrenciler</h5>
        <table class="table table-bordered align-middle">
            <thead><tr><th>Ad</th><th>Soyad</th><th>Yaş</th><th>Tanı</th><th>Veli</th><th>Detay</th><th>Sil</th></tr></thead>
            <tbody>
            <?php foreach($students as $stu): ?>
                <tr>
                    <td><?= htmlspecialchars($stu['name']) ?></td>
                    <td><?= htmlspecialchars($stu['surname']) ?></td>
                    <td><?= htmlspecialchars($stu['age']) ?></td>
                    <td><?= htmlspecialchars($stu['diagnosis']) ?></td>
                    <td><?= htmlspecialchars($stu['parent_name'].' '.$stu['parent_surname']) ?></td>
                    <td><a href="student_detail.php?student_id=<?= $stu['id'] ?>" class="btn btn-info btn-sm">Detay</a></td>
                    <td><a href="?delete_student=<?= $stu['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Öğrenciyi silmek istediğinize emin misiniz?')">Sil</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
