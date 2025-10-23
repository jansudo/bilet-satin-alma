<?php
session_start();
require_once "db.php";
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Güvenli input alımı ve sanitizasyon
    $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($code)) {
        $msg = 'Lütfen bir kupon kodu girin.';
        $msg_type = 'danger';
    } else {
        // SQL Injection koruması: Prepared Statement
        $st = $db->prepare("SELECT * FROM coupons WHERE code = :code AND expiry_date >= DATE('now')");
        $st->bindParam(':code', $code, PDO::PARAM_STR);
        $st->execute();
        $c = $st->fetch(PDO::FETCH_ASSOC);

        if ($c) {
            $msg = 'Kupon geçerli: ' . intval($c['discount_percent']) . '% indirim.';
            $msg_type = 'success';
        } else {
            $msg = 'Kupon geçersiz, süresi dolmuş veya bulunamadı.';
            $msg_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kupon Doğrulama | Yolcu Platformu</title>
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
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
.coupon-box { 
    max-width: 500px; 
    padding: 30px; 
    background: var(--card); 
    border-radius: 15px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.4); 
    border: 1px solid #333;
}
h1 { 
    text-align: center; 
    font-weight: 700; 
    color: var(--primary); 
    margin-bottom: 25px; 
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
    border: none; 
    width: 100%; 
    padding: 10px; 
    font-size: 16px; 
    font-weight: 600;
    transition: background-color 0.3s; 
    border-radius: 8px;
}
.btn-primary:hover { 
    background-color: #00899c; 
}
.message { 
    text-align: center; 
    margin-top: 20px; 
    padding: 15px;
    border-radius: 10px;
    font-weight: 600;
}
.alert-success { background-color: #1a4f32; border-color: #1a4f32; color: #a4e6b1; }
.alert-danger { background-color: #5b2020; border-color: #5b2020; color: #f59f9f; }
</style>
</head>
<body>
<div class="coupon-box">
<h1>Kupon Kodunu Kontrol Et</h1>
<form method="post">
<div class="mb-3">
<label for="code" class="form-label">Kupon Kodu</label>
<input type="text" name="code" id="code" class="form-control form-control-lg" placeholder="Örn: INDIRIM50" required>
</div>
<button type="submit" class="btn btn-primary">Kontrol Et</button>
</form>

<?php if ($msg): ?>
<div class="message alert alert-<?php echo $msg_type; ?>">
<?php echo htmlspecialchars($msg); ?>
</div>
<?php endif; ?>
</div>
</body>
</html>
