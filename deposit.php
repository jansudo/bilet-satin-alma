<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user']['id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Güvenli input alımı ve validasyon
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    if ($amount === false || $amount <= 0) {
        $message = "Geçerli bir pozitif bakiye miktarı girin.";
        $message_type = 'danger';
    } else {
        try {
            // İşlem güvenliği için transaction başlat
            $db->beginTransaction();

            // Kullanıcı bakiyesini güncelle
            $st = $db->prepare("UPDATE users SET credit = credit + :amount WHERE id = :user_id");
            $st->bindParam(':amount', $amount, PDO::PARAM_STR);
            $st->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $st->execute();

            // Oturumdaki bakiye bilgisini güncelle
            $_SESSION['user']['credit'] += $amount;

            $db->commit();
            $message = number_format($amount, 2, ',', '.') . " TL bakiyeniz başarıyla eklendi.";
            $message_type = 'success';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = "Bakiye yükleme sırasında bir hata oluştu.";
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bakiye Yükle | Yolcu Platformu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --bg: #121212;
    --card: #1e1e1e;
    --text: #e0e0e0;
    --primary: #00bcd4; /* Cyan/Teal */
}
body {
    background-color: var(--bg);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    padding-top: 56px;
}
.navbar {
    background-color: #1e1e1e !important;
    border-bottom: 1px solid #333;
}
.nav-link { color: var(--text) !important; }
.nav-link:hover { color: var(--primary) !important; }

.container {
    max-width: 600px;
    margin-top: 50px;
}
.deposit-card {
    background: var(--card);
    border: 1px solid #333;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
}
.form-control, .form-control:focus {
    background-color: #2c2c2c;
    border: 1px solid #444;
    color: var(--text);
    border-radius: 8px;
}
.form-control::placeholder {
    color: #888;
}
.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
    transition: background-color 0.3s;
    font-weight: 600;
    border-radius: 8px;
}
.btn-primary:hover {
    background-color: #00899c;
    border-color: #00899c;
}
.alert-success { background-color: #1a4f32; border-color: #1a4f32; color: #a4e6b1; }
.alert-danger { background-color: #5b2020; border-color: #5b2020; color: #f59f9f; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php" style="color: var(--primary) !important; font-weight: bold;">Yolcu Platformu</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Ana Sayfa</a></li>
                <li class="nav-item"><a class="nav-link active" href="deposit.php">Bakiye Ekle</a></li>
                <li class="nav-item"><a class="nav-link" href="account.php">Hesabım</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
    <div class="deposit-card">
        <h1 class="h3 mb-4 text-center" style="color: var(--primary);">Bakiye Yükle</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <p class="h4">Mevcut Bakiyeniz: 
                <span style="color: var(--primary); font-weight: bold;">
                    <?php echo number_format((float)$_SESSION['user']['credit'], 2, ',', '.'); ?> TL
                </span>
            </p>
        </div>

        <form method="post">
            <div class="mb-3">
                <label for="amount" class="form-label">Yüklenecek Miktar (TL)</label>
                <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control form-control-lg" placeholder="Örn: 100.00" required>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Ödeme Yöntemi</label>
                <select class="form-select" disabled>
                    <option>Sanal Kredi Kartı (Simülasyon)</option>
                </select>
                <small class="form-text text-muted">Bu bir simülasyon ortamıdır, gerçek bir ödeme yapılmayacaktır.</small>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Bakiyeyi Yükle</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
