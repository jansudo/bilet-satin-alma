<?php
session_start();
require_once "db.php";

// Varsayılan olarak formdaki tarih alanını bugünün tarihiyle doldururuz.
$default_date = date('Y-m-d');

// Kullanıcıdan gelen inputları temizle
$departure = filter_input(INPUT_GET, 'departure', FILTER_SANITIZE_SPECIAL_CHARS);
$arrival = filter_input(INPUT_GET, 'arrival', FILTER_SANITIZE_SPECIAL_CHARS);
$date_input = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);

$trips = [];
$error = '';
$search_performed = false; // Arama formunun kullanılıp kullanılmadığını izler

// Sayfa yüklendiğinde VEYA arama formunun GET isteğiyle gönderildiğinde listeleme yapılır.
try {
    // Güvenli arama için temel SQL yapısı
    $sql = "
        SELECT 
            t.*, 
            f.name AS firm_name 
        FROM trips t
        JOIN firms f ON t.firm_id = f.id
        WHERE 1=1
    ";
    $params = [];
    
    // --- ARAMA FİLTRELERİ ---

    // Eğer kalkış, varış girilmişse veya tarih bugünden farklıysa, bu bir arama olarak kabul edilir.
    if (!empty($departure) || !empty($arrival) || (!empty($date_input) && $date_input != $default_date)) {
        $search_performed = true;
    }

    // Kalkış noktası filtresi (Büyük/küçük harf duyarsız arama)
    if (!empty($departure)) {
        // LOWER() kullanımı, büyük/küçük harf duyarsız arama sağlar
        $sql .= " AND LOWER(t.departure) LIKE LOWER(:departure)";
        $params[':departure'] = '%' . $departure . '%';
    }

    // Varış noktası filtresi (Büyük/küçük harf duyarsız arama)
    if (!empty($arrival)) {
        $sql .= " AND LOWER(t.arrival) LIKE LOWER(:arrival)";
        $params[':arrival'] = '%' . $arrival . '%';
    }

    // Tarih filtresi
    if (!empty($date_input)) {
        // Eğer arama kutusuna bir tarih girilmişse, sadece o tarihteki seferleri göster
        $sql .= " AND t.date = :date_input";
        $params[':date_input'] = $date_input;
    } else {
        // Eğer tarih alanı boşsa VEYA ilk yüklemede, bugünden itibaren olan tüm seferleri göster
        $sql .= " AND t.date >= DATE('now')";
    }
    
    // Sonuçları tarihe ve saate göre sırala
    $sql .= " ORDER BY t.date ASC, t.time ASC";

    $st = $db->prepare($sql);
    $st->execute($params);
    $trips = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Sefer aramasında veya listelemede bir hata oluştu: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ana Sayfa | Yolcu Platformu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    --bg: #0a0a0a; /* Daha koyu arka plan */
    --card: #1c1c1c; /* Koyu gri kart */
    --text: #ffffff; /* Ana metin beyaz */
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
    background-color: var(--card) !important;
    border-bottom: 1px solid #333;
}
.navbar-brand, .nav-link {
    color: var(--text) !important;
    transition: color 0.3s;
}
.nav-link:hover {
    color: var(--primary) !important;
}
.container {
    padding-top: 30px;
    padding-bottom: 30px;
}
.search-card, .trip-card {
    background: var(--card);
    border: 1px solid #333;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
    margin-bottom: 2rem;
}
.form-control, .form-control:focus {
    background-color: #2c2c2c;
    border: 1px solid #444;
    color: var(--text);
    border-radius: 8px;
    box-shadow: none !important;
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
.trip-item {
    border-bottom: 1px solid #333;
    padding: 15px 0;
    transition: background-color 0.3s;
}
.trip-item:last-child {
    border-bottom: none;
}
.trip-item:hover {
    background-color: #252525;
}
.detail-link {
    color: var(--link);
    text-decoration: none;
    font-weight: 600;
}
.detail-link:hover {
    color: var(--primary);
}
.alert-warning {
    background-color: #4a3e1c;
    border-color: #4a3e1c;
    color: #ffeb9c;
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
                <?php if (isset($_SESSION['user'])): ?>
                    <?php 
                        // Sadece rolü user olmayanlar için panel linklerini göster (Admin, Company Admin)
                        if ($_SESSION['user']['role'] !== 'user'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo ($_SESSION['user']['role'] === 'admin' ? 'admin.php' : 'company_admin.php'); ?>">Panel</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="account.php">Hesabım</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Giriş Yap</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Kayıt Ol</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="search-card p-4 p-md-5">
        <h1 class="h3 mb-4 text-center" style="color: var(--primary);">Otobüs Seferi Ara</h1>
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="departure" class="form-label">Kalkış Noktası</label>
                <input type="text" name="departure" id="departure" class="form-control" value="<?php echo htmlspecialchars($departure ?? ''); ?>" placeholder="Örn: İstanbul">
            </div>
            <div class="col-md-4">
                <label for="arrival" class="form-label">Varış Noktası</label>
                <input type="text" name="arrival" id="arrival" class="form-control" value="<?php echo htmlspecialchars($arrival ?? ''); ?>" placeholder="Örn: Ankara">
            </div>
            <div class="col-md-3">
                <label for="date" class="form-label">Tarih</label>
                <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($date_input ?? $default_date); ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Ara</button>
            </div>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h2 class="h4 mb-3" style="color: var(--primary);">
        <?php 
            if ($search_performed) {
                echo 'Arama Sonuçları';
            } else {
                echo 'Yaklaşan Seferler'; // Sayfa ilk yüklendiğinde veya boş arama yapıldığında
            }
        ?> 
        (<?php echo count($trips); ?>)
    </h2>
    <div class="trip-card p-4">
        <?php if (empty($trips)): ?>
            <div class="alert alert-warning text-center m-0">Kriterlerinize uygun sefer bulunamadı veya hiç sefer mevcut değil.</div>
        <?php else: ?>
            <?php foreach ($trips as $trip): ?>
                <div class="row trip-item align-items-center">
                    <div class="col-lg-3 col-md-6 mb-2 mb-md-0">
                        <span class="badge bg-info text-dark me-2">Firma</span>
                        <strong><?php echo htmlspecialchars($trip['firm_name']); ?></strong>
                    </div>
                    <div class="col-lg-5 col-md-6 mb-2 mb-md-0">
                        <strong><?php echo htmlspecialchars($trip['departure']); ?></strong> 
                        <span class="text-muted">→</span> 
                        <strong><?php echo htmlspecialchars($trip['arrival']); ?></strong>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-2 mb-lg-0">
                        <span class="badge bg-secondary me-1">Tarih/Saat</span>
                        <?php echo htmlspecialchars($trip['date']); ?> | <?php echo htmlspecialchars(date('H:i', strtotime($trip['time']))); ?>
                    </div>
                    <div class="col-lg-2 col-md-6 text-lg-end text-start">
                        <span class="h5 me-2" style="color: var(--primary);"><?php echo number_format((float)$trip['price'], 2, ',', '.'); ?> TL</span>
                        <a href="details.php?id=<?php echo (int)$trip['id']; ?>" class="detail-link">Detay</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>