<?php
session_start();
require_once "db.php";

// Admin kontrolü
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo "<!DOCTYPE html><html lang='tr'><head><meta charset='UTF-8'><title>Erişim Reddedildi</title>
  <style>
  :root { --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --primary: #00bcd4; }
  body{font-family:'Inter', sans-serif;background:var(--bg);color:var(--text);text-align:center;padding-top:10%;}
  .card{display:inline-block;background:var(--card);padding:2rem 3rem;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.5);border:1px solid #333;}
  a{display:inline-block;margin-top:1rem;color:var(--primary);text-decoration:none;}
  </style></head><body>
  <div class='card'><h1>403 - Yetkisiz Erişim</h1><p>Bu sayfayı görüntüleme yetkiniz yok.</p><a href='index.php'>Ana Sayfa</a></div>
  </body></html>";
  exit;
}

$err = '';
$success_msg = '';

// YENİ EKLENDİ: Admin için Sefer Ekleme (POST) İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_trip') {
  // Güvenli input alımı ve validasyon
  $dep = filter_input(INPUT_POST, 'departure', FILTER_SANITIZE_SPECIAL_CHARS);
  $arr = filter_input(INPUT_POST, 'arrival', FILTER_SANITIZE_SPECIAL_CHARS);
  $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
  $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_SPECIAL_CHARS);
  $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
  $seat = filter_input(INPUT_POST, 'seat', FILTER_VALIDATE_INT);
  // Admin, firma_id'yi formdan seçmeli
  $firm_id = filter_input(INPUT_POST, 'firm_id', FILTER_VALIDATE_INT);

  // Genel boşluk kontrolü ve pozitif integer kontrolü
  if (empty($dep) || empty($arr) || empty($date) || empty($time) || empty($firm_id) || $firm_id <= 0 || $price === false || $price <= 0 || $seat === false || $seat <= 0) {
    $err = 'Tüm alanları doğru ve geçerli değerlerle doldurun.';
  } else {
    try {
        $st = $db->prepare("INSERT INTO trips (firm_id, departure, arrival, date, time, price, seat_count) VALUES (:firm_id, :dep, :arr, :date, :time, :price, :seat)");
        // :firm_id POST'tan alınır
        $st->bindParam(':firm_id', $firm_id, PDO::PARAM_INT);
        $st->bindParam(':dep', $dep, PDO::PARAM_STR);
        $st->bindParam(':arr', $arr, PDO::PARAM_STR);
        $st->bindParam(':date', $date, PDO::PARAM_STR);
        $st->bindParam(':time', $time, PDO::PARAM_STR);
        $st->bindParam(':price', $price, PDO::PARAM_STR); 
        $st->bindParam(':seat', $seat, PDO::PARAM_INT);
        $st->execute();
        $success_msg = 'Yeni sefer başarıyla eklendi!';
    } catch (PDOException $e) {
        $err = 'Veritabanı hatası: Sefer eklenemedi.';
    }
  }
}


// --- Mevcut Veritabanı Sorguları ---
$firms   = $db->query("SELECT id, name FROM firms ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$users   = $db->query("SELECT id, username, role, credit, firm_id FROM users ORDER BY credit DESC")->fetchAll(PDO::FETCH_ASSOC);
$coupons = $db->query("SELECT id, code, discount_percent, expiry_date FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// YENİ EKLENDİ: Tüm seferleri listele
$trips = $db->query("
    SELECT t.*, f.name AS firm_name 
    FROM trips t
    JOIN firms f ON t.firm_id = f.id
    ORDER BY t.date DESC, t.time DESC
")->fetchAll(PDO::FETCH_ASSOC);

// firm_id'leri firma isimleriyle eşleştirmek için bir harita oluştur
$firm_map = [];
foreach ($firms as $f) {
    $firm_map[$f['id']] = $f['name'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale-1">
<title>Admin Paneli | Yolcu Platformu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --bg: #121212;
  --card: #1e1e1e;
  --text: #e0e0e0;
  --primary: #00bcd4;
  --accent: #1e88e5;
}
body {
  background: var(--bg);
  color: var(--text);
  font-family: "Inter", sans-serif;
  padding-top: 56px;
}
.navbar {
    background-color: #1e1e1e !important;
    border-bottom: 1px solid #333;
}
.nav-link, .navbar-brand { color: var(--text) !important; }
.nav-link:hover { color: var(--primary) !important; }

.container {
  max-width: 1200px;
  margin-top: 2rem;
  margin-bottom: 2rem;
}
.content-card {
  background: var(--card);
  border: 1px solid #333;
  border-radius: 15px;
  padding: 2rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
}
h1 {
  color: var(--primary);
  margin-bottom: 1.5rem;
  font-weight: 700;
}
h2 {
  margin-top: 2rem;
  margin-bottom: 1rem;
  font-size: 1.5rem;
  color: var(--primary);
  border-bottom: 1px solid #333;
  padding-bottom: 0.5rem;
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
.list-card {
  background: #2c2c2c;
  border: 1px solid #444;
  border-radius: 10px;
  padding: 1rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.list-card h3 {
  margin: 0 0 .5rem;
  color: var(--primary);
  font-size: 1.25rem;
}
ul.coupon-list {
    list-style: none;
    padding: 0;
}
ul.coupon-list li {
    background: #2c2c2c;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 6px;
    border-left: 4px solid var(--primary);
    border: 1px solid #444;
}

/* YENİ EKLENDİ: Sefer Ekleme Formu Stilleri */
.form-card { 
    background-color: #2c2c2c; 
    border: 1px solid #444; 
    padding: 25px; 
    border-radius: 10px; 
    margin-bottom: 2rem; 
}
.form-control, .form-control:focus, .form-select, .form-select:focus {
    background-color: #383838;
    border: 1px solid #555;
    color: var(--text);
    border-radius: 8px;
}
.form-control::placeholder { color: #888; }
.btn-primary { 
    background-color: var(--primary); 
    border: none; 
    transition: background-color 0.3s; 
    font-weight: 600;
    border-radius: 8px;
    color: #000;
}
.btn-primary:hover { 
    background-color: #00899c; 
    color: #fff;
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
                <li class="nav-item"><a class="nav-link active" href="admin.php">Admin Panel</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
<div class="content-card">
<h1>Sistem Yönetim Paneli</h1>

<div class="form-card">
    <h2 class="h4 mb-4 text-center" style="border-bottom: none; color: var(--text);">Yeni Sefer Ekle (Admin)</h2>
    <?php if ($err): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
    <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="add_trip">
        
        <div class="col-md-6">
            <label class="form-label">Firma</label>
            <select name="firm_id" class="form-select" required>
                <option value="">-- Firma Seçin --</option>
                <?php foreach ($firms as $f): ?>
                    <option value="<?php echo (int)$f['id']; ?>">
                        <?php echo htmlspecialchars($f['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
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


<h2>Firmalar (<?php echo count($firms); ?>)</h2>
<?php if (empty($firms)): ?>
<div class="alert alert-warning">Henüz kayıtlı firma yok.</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($firms as $f): ?>
<div class="col-md-4 col-lg-3">
<div class="list-card">
<h3><?php echo htmlspecialchars($f['name']); ?></h3>
<p class="text-muted">ID: <?php echo (int)$f['id']; ?></p>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Tüm Seferler (<?php echo count($trips); ?>)</h2>
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead>
<tr>
<th>Firma</th>
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
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['firm_name']); ?></span></td>
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


<h2>Kullanıcılar (<?php echo count($users); ?>)</h2>
<?php if (empty($users)): ?>
<div class="alert alert-warning">Henüz kullanıcı yok.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead>
<tr>
<th>ID</th>
<th>Kullanıcı Adı</th>
<th>Rol</th>
<th>Bakiye (TL)</th>
<th>İlişkili Firma</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
<td><?php echo (int)$u['id']; ?></td>
<td><?php echo htmlspecialchars($u['username']); ?></td>
<td>
    <?php 
        $role = $u['role'];
        $role_class = 'bg-success'; // user
        if ($role === 'admin') $role_class = 'bg-danger';
        elseif ($role === 'company_admin') $role_class = 'bg-warning text-dark'; 
    ?>
    <span class="badge <?php echo $role_class; ?>"><?php echo htmlspecialchars($u['role']); ?></span>
</td>
<td><strong style="color: var(--primary);"><?php echo number_format((float)$u['credit'], 2, ',', '.'); ?> TL</strong></td>
<td>
    <?php 
        if ($u['firm_id'] && isset($firm_map[$u['firm_id']])) {
            echo htmlspecialchars($firm_map[$u['firm_id']]);
        } else {
            echo 'N/A';
        }
    ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<h2>Kuponlar (<?php echo count($coupons); ?>)</h2>
<?php if (empty($coupons)): ?>
<div class="alert alert-warning">Henüz kupon bulunmuyor.</div>
<?php else: ?>
<ul class="coupon-list">
<?php foreach ($coupons as $c): ?>
<li>
    <strong><?php echo htmlspecialchars($c['code']); ?></strong> – 
    %<?php echo (int)$c['discount_percent']; ?> indirim 
    (Süre Sonu: <span class="text-warning"><?php echo htmlspecialchars($c['expiry_date'] ?? 'Yok'); ?></span>)
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>