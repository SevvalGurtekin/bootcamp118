<?php
session_start();
// Eğer giriş yapılmışsa role göre yönlendir
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: views/admin_panel.php');
            exit();
        case 'teacher':
            header('Location: views/teacher_panel.php');
            exit();
        case 'parent':
            header('Location: views/parent_panel.php');
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Parlayan Yıldızlar - Yapay Zeka Destekli Özel Eğitim Platformu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); min-height: 100vh; display: flex; align-items: center; }
        .main-card { background: #fff; border-radius: 24px; box-shadow: 0 8px 32px 0 rgba(4,110,143,0.15); padding: 2.5rem 2rem 2rem 2rem; max-width: 500px; width: 100%; border: 3px solid #046E8F; margin: auto; }
        .main-title { color: #046E8F; font-weight: bold; text-align: center; margin-bottom: 1.5rem; font-size: 2rem; letter-spacing: 1px; }
        .main-desc { text-align: center; color: #028090; font-size: 1.1rem; margin-bottom: 2rem; }
        .btn-main { background: #046E8F; border: none; border-radius: 12px; padding: 0.75rem; font-weight: bold; font-size: 1.1rem; }
        .btn-main:hover { background: #028090; }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="main-title">Parlayan Yıldızlar</div>
        <div class="main-desc">
            Yapay zeka destekli özel eğitim platformuna hoş geldiniz.<br>
            Öğretmen, veli ve adminler için kişiselleştirilmiş eğitim ve gelişim takibi.
        </div>
        <div class="d-grid gap-3">
            <a href="views/login.php" class="btn btn-main">Giriş Yap</a>
            <a href="views/register_teacher.php" class="btn btn-outline-info" style="font-weight:bold;">Öğretmen Kaydı</a>
        </div>
        <div class="text-center mt-4" style="color:#888; font-size:0.95rem;">
            &copy; <?= date('Y') ?> Parlayan Yıldızlar
        </div>
    </div>
</body>
</html>
