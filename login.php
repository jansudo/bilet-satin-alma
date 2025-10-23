<?php
session_start();
require_once "db.php";

$error_message = '';

if (isset($_SESSION['user'])) {
    // Zaten giriş yapmışsa ana sayfaya yönlendir
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($username) || empty($password)) {
        $error_message = "Kullanıcı adı ve şifre zorunludur.";
    } else {
        try {
            $st = $db->prepare("SELECT * FROM users WHERE username = :username");
            $st->bindParam(':username', $username);
            $st->execute();
            $user = $st->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Şifre doğru, oturum oluştur
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'firm_id' => $user['firm_id']
                ];
                // Giriş başarılı, ana sayfaya yönlendir
                header('Location: index.php');
                exit;
            } else {
                $error_message = "Kullanıcı adı veya şifre hatalı.";
            }
        } catch (PDOException $e) {
            $error_message = "Giriş sırasında bir veritabanı hatası oluştu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap | Yolcu Platformu</title>
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
        <h1 class="h3 text-center mb-4" style="color: var(--primary);">Giriş Yap</h1>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Kullanıcı Adı</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Giriş Yap</button>
        </form>
        
        <p class="text-center mt-3 text-muted">
            Hesabınız yok mu? <a href="register.php" style="color: var(--primary); text-decoration: none;">Kayıt Ol</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
