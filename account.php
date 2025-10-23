<?php
session_start();
require_once "db.php";

// Kullanıcı oturumu kontrolü
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
  header('Location: login.php');
  exit;
}

$user_id = (int) $_SESSION['user']['id'];

// Kullanıcı bilgisini güncelle (en güncel bakiye için)
$stm = $db->prepare("SELECT id, username, role, credit FROM users WHERE id = :user_id");
$stm->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stm->execute();
$user = $stm->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  session_destroy();
  header('Location: login.php');
  exit;
}
$_SESSION['user'] = $user;

// Biletleri çek
$st = $db->prepare("
SELECT 
    t.id, t.seat_number, t.status, 
    tr.departure, tr.arrival, tr.date, tr.time, tr.price, 
    f.name AS firm_name
FROM tickets t
JOIN trips tr ON t.trip_id = tr.id
JOIN firms f ON tr.firm_id = f.id
WHERE t.user_id = :user_id
ORDER BY tr.date DESC, tr.time DESC
");
$st->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$st->execute();
$tickets = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hesabım | Yolcu Platformu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --bg: #121212;
    --card: #1e1e1e;
    --text: #e0e0e0;
    --primary: #00bcd4; /* Cyan/Teal */
    --link: #80deea;
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
.nav-link, .navbar-brand { color: var(--text) !important; }
.nav-link:hover { color: var(--primary) !important; }

.container {
  max-width: 1000px;
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
.user-info {
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #333;
}
.user-info h2 {
  color: var(--primary);
  font-weight: 700;
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
.btn-pdf {
  background-color: var(--primary);
  border-color: var(--primary);
  transition: background-color 0.3s;
  font-weight: 600;
  border-radius: 6px;
}
.btn-pdf:hover {
  background-color: #00899c;
  border-color: #00899c;
}
/* Responsive Table Headers */
@media (max-width: 768px) {
  .table thead { display: none; }
  .table tr { 
    display: block; 
    margin-bottom: 10px; 
    background: #252525;
    border: 1px solid #444;
    border-radius: 8px;
  }
  .table td {
    display: block;
    text-align: right;
    padding-left: 50%;
    position: relative;
    border-bottom: none !important;
  }
  .table td::before {
    content: attr(data-label);
    position: absolute;
    left: 15px;
    font-weight: bold;
    color: var(--primary);
    text-align: left;
  }
}
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
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admin.php">Admin Panel</a></li>
                <?php elseif ($_SESSION['user']['role'] === 'company_admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="company_admin.php">Firma Panel</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['user']['role'] === 'user'): ?>
                    <li class="nav-item"><a class="nav-link" href="deposit.php">Bakiye Ekle</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link active" href="account.php">Hesabım</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
<div class="content-card">
<div class="user-info">
<h2>Hoş Geldiniz, <?php echo htmlspecialchars($user['username']); ?></h2>
<p class="h5">
    <strong>Rol:</strong> 
    <span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span>
</p>
<p class="h5">
    <strong>Bakiye:</strong> 
    <span style="color: var(--primary); font-weight: bold;">
        <?php echo number_format((float)$user['credit'], 2, ',', '.'); ?> TL
    </span>
</p>
</div>

<h3>Satın Alınan Biletlerim</h3>
<?php if (empty($tickets)): ?>
<div class="alert alert-info text-center mt-3" style="background-color: #2c2c2c; border-color: #444; color: var(--primary);">
    Henüz satın alınmış biletiniz bulunmuyor.
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle">
<thead>
<tr>
<th>Firma</th>
<th>Kalkış</th>
<th>Varış</th>
<th>Tarih</th>
<th>Saat</th>
<th>Koltuk</th>
<th>Fiyat</th>
<th>Durum</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php foreach ($tickets as $t): ?>
<tr>
<td data-label="Firma"><?php echo htmlspecialchars($t['firm_name']); ?></td>
<td data-label="Kalkış"><?php echo htmlspecialchars($t['departure']); ?></td>
<td data-label="Varış"><?php echo htmlspecialchars($t['arrival']); ?></td>
<td data-label="Tarih"><?php echo htmlspecialchars($t['date']); ?></td>
<td data-label="Saat"><?php echo htmlspecialchars(date('H:i', strtotime($t['time']))); ?></td>
<td data-label="Koltuk"><span class="badge bg-info text-dark"><?php echo (int)$t['seat_number']; ?></span></td>
<td data-label="Fiyat"><?php echo number_format((float)$t['price'], 2, ',', '.'); ?> TL</td>
<td data-label="Durum">
    <span class="badge <?php echo ($t['status'] === 'active' ? 'bg-success' : 'bg-danger'); ?>">
        <?php echo htmlspecialchars($t['status']); ?>
    </span>
</td>
<td data-label="İşlem">
    <a class="btn btn-pdf btn-sm" href="pdf.php?id=<?php echo (int)$t['id']; ?>" target="_blank">PDF</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
