<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION)) {
    echo "Session başlatılamadı! Sunucu ayarlarını kontrol edin.";
    exit();
}
require_once '../config/db.php';
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: admin_panel.php');
            break;
        case 'teacher':
            header('Location: teacher_panel.php');
            break;
        case 'parent':
            header('Location: parent_panel.php');
            break;
        default:
            header('Location: login.php');
    }
    exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT id, name, surname, email, password, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['surname'] = $user['surname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                switch($user['role']) {
                    case 'admin':
                        header('Location: admin_panel.php');
                        break;
                    case 'teacher':
                        header('Location: teacher_panel.php');
                        break;
                    case 'parent':
                        header('Location: parent_panel.php');
                        break;
                    case 'student':
                        header('Location: student_panel.php');
                        break;
                    default:
                        header('Location: login.php');
                }
                exit();
            } else {
                $error = 'Hesabınız henüz onaylanmamış. Lütfen admin ile iletişime geçin.';
            }
        } else {
            $error = 'Geçersiz email veya şifre!';
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun!';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş Yap</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; }
        .login-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 400px; width: 100%; border: 3px solid #046E8F; margin: 2rem auto; }
        .login-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .btn-login { background: #046E8F; border: none; border-radius: 12px; padding: 0.75rem; font-weight: bold; }
        .btn-login:hover { background: #028090; }
        @media (max-width: 600px) {
            .login-card { padding: 1rem 0.5rem; }
            .login-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-title">Giriş Yap</div>
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-login w-100">Giriş Yap</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="register_teacher.php" class="text-decoration-none">Öğretmen misiniz? Kayıt olun</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
