<?php
include '../config/db.php';
session_start();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $sql = "SELECT * FROM users WHERE email=? AND password=? AND status='active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $_SESSION['user'] = $user;
        // Rol bazlı yönlendirme
        switch($user['role']) {
            case 'admin':
                header('Location: admin_panel.php'); exit;
            case 'teacher':
                header('Location: teacher_panel.php'); exit;
            case 'student':
                header('Location: student_panel.php'); exit;
            case 'parent':
                header('Location: parent_panel.php'); exit;
        }
    } else {
        $msg = 'Hatalı giriş veya onaylanmamış kullanıcı!';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 400px;
            width: 100%;
            border: 3px solid #046E8F;
            position: relative;
        }
        .login-title {
            color: #046E8F;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            letter-spacing: 1px;
        }
        .btn-primary {
            background: #046E8F;
            border: none;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #028090;
        }
        .login-card input[type="email"],
        .login-card input[type="password"] {
            border-radius: 10px;
            border: 1.5px solid #b2ebf2;
            background: #f1fafd;
        }
        .login-card .alert {
            border-radius: 10px;
            font-size: 1rem;
        }
        .login-card .register-link {
            color: #046E8F;
            font-weight: 500;
            text-decoration: underline;
        }
        .login-card .register-link:hover {
            color: #028090;
        }
        .login-illustration {
            width: 80px;
            height: 80px;
            display: block;
            margin: 0 auto 1rem auto;
        }
    </style>
</head>
<body>
    <div class="login-card mx-auto">
        <svg class="login-illustration" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="32" cy="32" r="32" fill="#046E8F"/>
            <ellipse cx="32" cy="44" rx="18" ry="8" fill="#b2ebf2"/>
            <circle cx="32" cy="28" r="12" fill="#fff"/>
            <ellipse cx="32" cy="28" rx="6" ry="8" fill="#046E8F"/>
        </svg>
        <div class="login-title">Parlayan yıldızlar giriş paneli</div>
        <?php if($msg) echo '<div class="alert alert-danger text-center">'.$msg.'</div>'; ?>
        <form method="post">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="E-posta" required autofocus>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Şifre" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-2">Giriş Yap</button>
        </form>
        <div class="mt-2 text-center">
            <a href="register_teacher.php" class="register-link">Öğretmen misiniz? Kayıt olun</a>
        </div>
    </div>
</body>
</html>
