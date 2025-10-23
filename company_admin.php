<?php
session_start();
require_once "db.php";

// Yetki kontrolü
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'company_admin') {
  http_response_code(403);
  die('Yetkisiz erişim.');
}

$firm_id = (int)($_SESSION['user']['firm_id'] ?? 0);
if (!$firm_id) {
  die('Firma bilgisi bulunamadı. Lütfen giriş yapın.');
}

$err = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Güvenli input alımı ve validasyon
  $dep = filter_input(INPUT_POST, 'departure', FILTER_SANITIZE_SPECIAL_CHARS);
  $arr = filter_input(INPUT_POST, 'arrival', FILTER_SANITIZE_SPECIAL_CHARS);
  $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
  $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_SPECIAL_CHARS);
  $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
  $seat = filter_input(INPUT_POST, 'seat', FILTER_VALIDATE_INT);

  // Genel boşluk kontrolü ve pozitif integer kontrolü
  if (empty($dep) || empty($arr) || empty($date) || empty($time) || $price === false || $price <= 0 || $seat === false || $seat <= 0) {
    $err = 'Tüm alanları doğru ve geçerli değerlerle doldurun.';
  } else {
    try {
        $st = $db->prepare("INSERT INTO trips (firm_id, departure, arrival, date, time, price, seat_count) VALUES (:firm_id, :dep, :arr, :date, :time, :price, :seat)");
        $st->bindParam(':firm_id', $firm_id, PDO::PARAM_INT);
        $st->bindParam(':dep', $dep, PDO::PARAM_STR);
        $st->bindParam(':arr', $arr, PDO::PARAM_STR);
        $st->bindParam(':date', $date, PDO::PARAM_STR);
        $st->bindParam(':time', $time, PDO::PARAM_STR);
        $st->bindParam(':price', $price, PDO::PARAM_STR); // REAL (float) tipi için STR kullanmak en güvenlisi
        $st->bindParam(':seat', $seat, PDO::PARAM_INT);
        $st->execute();
        $success_msg = 'Yeni sefer başarıyla eklendi!';
    } catch (PDOException $e) {
        $err = 'Veritabanı hatası: Sefer eklenemedi.';
    }
  }
}

// Mevcut seferleri çek
$trips = $db->prepare("SELECT * FROM trips WHERE firm_id = :firm_id ORDER BY date DESC, time DESC");
$trips->bindParam(':firm_id', $firm_id, PDO::PARAM_INT);
$trips->execute();
$trips = $trips->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Firma Paneli</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --bg: #121212;
    --card: #1e1e1e;
    --text: #e0e0e0;
    --primary: #00bcd4; /* Cyan/Teal */
}
body { 
    background: var(--bg); 
    color: var(--text); 
    font-family: 'Inter', sans-serif; 
    padding-top: 56px;
}
.navbar {
    background-color: #1e1e1e !important;
    border-bottom: 1px solid #333;
}
.nav-link, .navbar-brand { color: var(--text) !important; }
.nav-link:hover { color: var(--primary) !important; }

.container { 
    max-width: 900px; 
    margin-top: 40px; 
    margin-bottom: 40px;
}
.card { 
    border-radius: 15px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.4); 
    padding: 2rem; 
    background: var(--card); 
    border: 1px solid #333;
}
h1 { 
    font-weight: 700; 
    color: var(--primary); 
    margin-bottom: 25px; 
}
h2 {
    color: var(--primary);
    border-bottom: 1px solid #333;
    padding-bottom: 10px;
    margin-top: 2rem;
}
.form-card { 
    background-color: #2c2c2c; 
    border: 1px solid #444; 
    padding: 25px; 
    border-radius: 10px; 
    margin-bottom: 2rem; 
}
.form-control, .form-control:focus {
    background-color: #383838;
    border: 1px solid #555;
    color: var(--text);
    border-radius: 8px;
}
.form-control::placeholder {
    color: #888;
}
.btn-primary { 
    background-color: var(--primary); 
    border: none; 
    transition: background-color 0.3s; 
    font-weight: 600;
    border-radius: 8px;
}
.btn-primary:hover { 
    background-color: #00899c; 
}
.table {
    --bs-table-bg: #2c2c2c;
    --bs-table-color: var(--text);
    --bs-table-border-color: #444;
}
.table thead th {
    background-color: #333;
    color: var(--primary);
}
.table-responsive {
    border-radius: 10px;
    overflow-x: auto;
    border: 1px solid #444;
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
                <li class="nav-item"><a class="nav-link active" href="company_admin.php">Firma Panel</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
<div class="card">
<h1 class="text-center">Firma Yönetim Paneli</h1>

<div class="form-card">
    <h2 class="h4 mb-4 text-center" style="border-bottom: none; color: var(--text);">Yeni Sefer Ekle</h2>
    <?php if ($err): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
    <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Kalkış</label>
            <input type="text" name="departure" class="form-control" placeholder="Kalkış noktası" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Varış</label>
            <input type="text" name="arrival" class="form-control" placeholder="Varış noktası" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Tarih</label>
            <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Saat</label>
            <input type="time" name="time" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Fiyat (TL)</label>
            <input type="number" name="price" class="form-control" min="1" step="0.01" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Koltuk Sayısı</label>
            <input type="number" name="seat" class="form-control" min="1" required>
        </div>
        <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">Sefer Ekle</button>
        </div>
    </form>
</div>

<h2>Mevcut Seferler</h2>
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead>
<tr>
<th>ID</th>
<th>Kalkış</th>
<th>Varış</th>
<th>Tarih</th>
<th>Saat</th>
<th>Fiyat</th>
<th>Koltuk</th>
</tr>
</thead>
<tbody>
<?php if (empty($trips)): ?>
<tr><td colspan="7" class="text-center">Henüz sefer eklenmemiş.</td></tr>
<?php else: ?>
<?php foreach ($trips as $t): ?>
<tr>
<td><?php echo (int)$t['id']; ?></td>
<td><?php echo htmlspecialchars($t['departure']); ?></td>
<td><?php echo htmlspecialchars($t['arrival']); ?></td>
<td><?php echo htmlspecialchars($t['date']); ?></td>
<td><?php echo htmlspecialchars(date('H:i', strtotime($t['time']))); ?></td>
<td><?php echo number_format((float)$t['price'], 2, ',', '.'); ?> TL</td>
<td><?php echo (int)$t['seat_count']; ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
