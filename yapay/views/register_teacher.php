<?php
require_once '../config/db.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t_name = $_POST['t_name'];
    $t_surname = $_POST['t_surname'];
    $t_school = $_POST['t_school'];
    $t_branch = $_POST['t_branch'];
    $t_email = $_POST['t_email'];
    $t_pass = substr(md5(uniqid()),0,8);
    $t_hash = password_hash($t_pass, PASSWORD_DEFAULT);
    // Kimlik kartı yükleme
    $file_name = '';
    if (isset($_FILES['t_card']) && $_FILES['t_card']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['t_card']['name'], PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $file_name = 'teacher_' . time() . '_' . rand(1000,9999) . '.png';
            move_uploaded_file($_FILES['t_card']['tmp_name'], '../uploads/' . $file_name);
        } else {
            $msg = 'Kimlik kartı sadece PNG formatında olmalıdır!';
        }
    } else {
        $msg = 'Kimlik kartı yüklenmedi!';
    }
    // E-posta daha önce kullanılmış mı kontrolü
    if (!$msg) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$t_email]);
        if ($check->fetch()) {
            $msg = 'Bu e-posta adresi ile zaten bir kayıt yapılmış!';
        }
    }
    if (!$msg) {
        $stmt = $pdo->prepare("INSERT INTO users (name, surname, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', 'pending')");
        if ($stmt->execute([$t_name, $t_surname, $t_email, $t_hash])) {
            $teacher_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO teachers (user_id, school, branch, card) VALUES (?, ?, ?, ?)")
                ->execute([$teacher_id, $t_school, $t_branch, $file_name]);
            // Şifreyi ve mail gönderimini admin onayına bırakıyoruz
            $msg = '<b>Kayıt başarılı!</b><br>Kaydınız alınmıştır. Admin onayından sonra giriş bilgileriniz mail adresinize gönderilecektir.';
        } else {
            $msg = 'Hata: Kayıt işlemi başarısız.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Öğretmen Kayıt</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 500px;
            width: 100%;
            border: 3px solid #046E8F;
            position: relative;
        }
        .register-title {
            color: #046E8F;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            letter-spacing: 1px;
        }
        .btn-success {
            background: #046E8F;
            border: none;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .btn-success:hover {
            background: #028090;
        }
        .register-card input, .register-card select {
            border-radius: 10px;
            border: 1.5px solid #b2ebf2;
            background: #f1fafd;
        }
        .register-card .alert {
            border-radius: 10px;
            font-size: 1rem;
        }
        .register-card .login-link {
            color: #046E8F;
            font-weight: 500;
            text-decoration: underline;
        }
        .register-card .login-link:hover {
            color: #028090;
        }
    </style>
</head>
<body>
    <div class="register-card mx-auto">
        <div class="register-title">Öğretmen Kayıt</div>
        <?php if($msg) echo '<div class="alert alert-info text-center">'.$msg.'</div>'; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="row mb-3">
                <div class="col-md-6"><input type="text" name="t_name" class="form-control" placeholder="Adınız" required></div>
                <div class="col-md-6"><input type="text" name="t_surname" class="form-control" placeholder="Soyadınız" required></div>
            </div>
            <div class="mb-3"><input type="text" name="t_school" class="form-control" placeholder="Çalıştığınız Kurum" required></div>
            <div class="mb-3"><input type="text" name="t_branch" class="form-control" placeholder="Branşınız (örn. Sınıf Öğretmeni)" required></div>
            <div class="mb-3"><input type="email" name="t_email" class="form-control" placeholder="E-posta" required></div>
            <div class="mb-3">
                <label for="t_card" class="form-label">Öğretmen Kimlik Kartı (PNG)</label>
                <input type="file" name="t_card" id="t_card" class="form-control" accept="image/png" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Kayıt Ol</button>
        </form>
        <div class="mt-3 text-center">
            <a href="login.php" class="login-link">Girişe Dön</a>
        </div>
    </div>
</body>
</html> 