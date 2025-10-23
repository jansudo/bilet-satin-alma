<?php
session_start();
require_once "db.php";

$error_message = '';
$success_message = '';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error_message = "Tüm alanlar zorunludur.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Şifreler eşleşmiyor.";
    } else {
        try {
            // Kullanıcı adının benzersizliğini kontrol et
            $st_check = $db->prepare("SELECT id FROM users WHERE username = :username");
            $st_check->bindParam(':username', $username);
            $st_check->execute();
            if ($st_check->fetch()) {
                $error_message = "Bu kullanıcı adı zaten kullanılıyor.";
            } else {
                // Yeni kullanıcıyı kaydet
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $st_insert = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'user')");
                $st_insert->bindParam(':username', $username);
                $st_insert->bindParam(':password', $hashed_password);
                $st_insert->execute();

                $success_message = "Kayıt başarılı! Şimdi <a href='login.php' class='alert-link'>giriş yapabilirsiniz</a>.";
            }
        } catch (PDOException $e) {
            $error_message = "Kayıt sırasında bir veritabanı hatası oluştu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol | Yolcu Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    :root {
        --bg: #0a0a0a;
        --card: #1c1c1c;
        --text: #ffffff;
        --primary: #00bcd4;
    }
    body { 
        background-color: var(--bg); 
        color: var(--text); 
        font-family: 'Inter', sans-serif; 
        padding-top: 56px;
    }
    .navbar { background-color: var(--card) !important; border-bottom: 1px solid #333; }
    .container { max-width: 450px; margin-top: 50px; }
    .card { 
        border-radius: 15px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.8); 
        background: var(--card); 
        border: 1px solid #333;
    }
    .form-control { 
        background-color: #2c2c2c; 
        border: 1px solid #444; 
        color: var(--text); 
    }
    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
        font-weight: 600;
        border-radius: 8px;
    }
    .btn-primary:hover {
        background-color: #00899c;
        border-color: #00899c;
    }
    .alert-danger { background-color: #5b2020; border-color: #5b2020; color: #f59f9f; }
    .alert-success { background-color: #1e4d35; border-color: #1e4d35; color: #a3ff9e; }
    .alert-link { color: var(--primary) !important; font-weight: bold; }
    .nav-link, .navbar-brand { color: var(--text) !important; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php" style="color: var(--primary) !important; font-weight: bold;">Yolcu Platformu</a>
    </div>
</nav>

<div class="container">
    <div class="card p-4 p-md-5">
        <h1 class="h3 text-center mb-4" style="color: var(--primary);">Kayıt Ol</h1>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Kullanıcı Adı</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Kayıt Ol</button>
        </form>
        
        <p class="text-center mt-3 text-muted">
            Zaten hesabınız var mı? <a href="login.php" style="color: var(--primary); text-decoration: none;">Giriş Yap</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
