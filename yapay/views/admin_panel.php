<?php
include '../config/db.php';
session_start();
// Sadece admin erişebilir
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
// Kullanıcı ekleme işlemi
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = substr(md5(uniqid()),0,8); // Rastgele şifre
    $hash = md5($password);
    // E-posta daha önce kullanılmış mı kontrolü
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $msg = 'Bu e-posta adresi ile zaten kayıt yapılmış!';
    } else {
        $sql = "INSERT INTO users (name, surname, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssss', $name, $surname, $email, $hash, $role);
        if ($stmt->execute()) {
            // Burada mail gönderme fonksiyonu entegre edilecek
            $msg = 'Kullanıcı eklendi ve şifresi: ' . $password;
        } else {
            $msg = 'Hata: ' . $conn->error;
        }
    }
}
// Kullanıcı onaylama işlemi
if (isset($_GET['approve'])) {
    $uid = intval($_GET['approve']);
    // Kullanıcıyı bul
    $userQ = $conn->prepare("SELECT email, name FROM users WHERE id=?");
    $userQ->bind_param('i', $uid);
    $userQ->execute();
    $userQ->bind_result($email, $name);
    $userQ->fetch();
    $userQ->close();
    // 8 haneli rakamdan oluşan şifre üret
    $newpass = str_pad(strval(rand(0, 99999999)), 8, '0', STR_PAD_LEFT);
    $hash = md5($newpass);
    // Şifreyi güncelle ve kullanıcıyı aktif yap
    $sql = "UPDATE users SET status='active', password=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $hash, $uid);
    $stmt->execute();
    // Mail gönder
    $subject = 'Parlayan Yıldızlar Giriş Bilgileriniz';
    $message = "Merhaba $name,\n\nSisteme giriş için şifreniz: $newpass\nGiriş sayfası: http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php\n\nLütfen şifrenizi kimseyle paylaşmayınız.";
    $headers = 'From: no-reply@parlayanyildizlar.com' . "\r\n" .
        'Reply-To: no-reply@parlayanyildizlar.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
    @mail($email, $subject, $message, $headers);
    $msg = 'Kullanıcı onaylandı ve şifresi mail adresine gönderildi!';
    header('Location: admin_panel.php?msg='.urlencode($msg));
    exit();
}
// Kullanıcı reddetme işlemi
if (isset($_GET['reject'])) {
    $uid = intval($_GET['reject']);
    $sql = "UPDATE users SET status='inactive' WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    header('Location: admin_panel.php');
    exit();
}
// Kullanıcı silme işlemi
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    // Kullanıcının rolünü bul
    $roleQ = $conn->prepare("SELECT role FROM users WHERE id=?");
    $roleQ->bind_param('i', $uid);
    $roleQ->execute();
    $roleQ->bind_result($role);
    $roleQ->fetch();
    $roleQ->close();
    // Bağlı tablodan sil
    if ($role === 'teacher') {
        $conn->query("DELETE FROM teachers WHERE user_id=$uid");
    } elseif ($role === 'student') {
        $conn->query("DELETE FROM students WHERE user_id=$uid");
    } elseif ($role === 'parent') {
        $conn->query("DELETE FROM parents WHERE user_id=$uid");
    }
    // Son olarak users tablosundan sil
    $sql = "DELETE FROM users WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    header('Location: admin_panel.php');
    exit();
}
// Bekleyen kullanıcılar
$pending = $conn->query("SELECT u.*, t.card FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.status='pending'");
// Onaylanmış kullanıcılar
$active_users = $conn->query("SELECT u.*, t.card FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.status='active'");
// Mesajı GET ile göstermek için
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .card-preview {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border: 1.5px solid #b2ebf2;
            border-radius: 8px;
            background: #f1fafd;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .card-preview:hover {
            box-shadow: 0 0 0 3px #046E8F33;
        }
        .modal-img {
            max-width: 100%;
            max-height: 70vh;
            display: block;
            margin: 0 auto;
        }
        .exit-btn {
            position: absolute;
            right: 30px;
            top: 30px;
        }
    </style>
</head>
<body class="container py-4 position-relative">
    <a href="login.php" class="btn btn-outline-danger exit-btn">Çıkış</a>
    <h2>Admin Paneli</h2>
    <?php if($msg) echo '<div class="alert alert-info">'.$msg.'</div>'; ?>
    <h4>Kullanıcı Ekle</h4>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="İsim" required></div>
        <div class="col-md-3"><input type="text" name="surname" class="form-control" placeholder="Soyisim" required></div>
        <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="E-posta" required></div>
        <div class="col-md-2">
            <select name="role" class="form-select" required>
                <option value="teacher">Öğretmen</option>
                <option value="student">Öğrenci</option>
                <option value="parent">Veli</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="col-md-1"><button type="submit" name="add_user" class="btn btn-primary">Ekle</button></div>
    </form>
    <h4>Bekleyen Kullanıcılar</h4>
    <table class="table table-bordered align-middle">
        <tr><th>ID</th><th>İsim</th><th>Soyisim</th><th>E-posta</th><th>Rol</th><th>Kimlik Kartı</th><th>Onayla</th><th>Reddet</th></tr>
        <?php while($row = $pending->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['name'] ?></td>
            <td><?= $row['surname'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><?= $row['role'] ?></td>
            <td>
                <?php if($row['role'] === 'teacher' && !empty($row['card'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['card']) ?>" class="card-preview" alt="Kimlik Kartı" data-bs-toggle="modal" data-bs-target="#cardModal" data-img="../uploads/<?= htmlspecialchars($row['card']) ?>">
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><a href="?approve=<?= $row['id'] ?>" class="btn btn-success btn-sm">Onayla</a></td>
            <td><a href="?reject=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Reddet</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <h4>Onaylanmış Kullanıcılar</h4>
    <table class="table table-striped align-middle">
        <tr><th>ID</th><th>İsim</th><th>Soyisim</th><th>E-posta</th><th>Rol</th><th>Kimlik Kartı</th><th>Sil</th></tr>
        <?php while($row = $active_users->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['name'] ?></td>
            <td><?= $row['surname'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><?= $row['role'] ?></td>
            <td>
                <?php if($row['role'] === 'teacher' && !empty($row['card'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($row['card']) ?>" class="card-preview" alt="Kimlik Kartı" data-bs-toggle="modal" data-bs-target="#cardModal" data-img="../uploads/<?= htmlspecialchars($row['card']) ?>">
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Kullanıcıyı silmek istediğinize emin misiniz?')">Sil</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <!-- Modal -->
    <div class="modal fade" id="cardModal" tabindex="-1" aria-labelledby="cardModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="cardModalLabel">Kimlik Kartı</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
          </div>
          <div class="modal-body text-center">
            <img src="" id="modalCardImg" class="modal-img" alt="Kimlik Kartı Büyük">
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Küçük resme tıklanınca modalda büyük göster
    document.addEventListener('DOMContentLoaded', function() {
        var cardModal = document.getElementById('cardModal');
        var modalImg = document.getElementById('modalCardImg');
        var imgs = document.querySelectorAll('.card-preview[data-bs-toggle="modal"]');
        imgs.forEach(function(img) {
            img.addEventListener('click', function() {
                var src = img.getAttribute('data-img');
                modalImg.setAttribute('src', src);
            });
        });
        // Modal kapatıldığında resmi temizle
        cardModal.addEventListener('hidden.bs.modal', function () {
            modalImg.setAttribute('src', '');
        });
    });
    </script>
</body>
</html>
