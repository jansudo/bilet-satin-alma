<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    die("Giriş yapmalısınız");
}

$ticket_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user']['id'];

if (!$ticket_id) {
    die("Bilet ID gerekli");
}

// Bilet ve kullanıcı kontrolü
$stmt = $db->prepare("
    SELECT 
        t.*, tr.departure, tr.arrival, tr.date, tr.time, tr.price, 
        f.name as firm_name 
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN firms f ON tr.firm_id = f.id
    WHERE t.id = :ticket_id AND t.user_id = :user_id
");
$stmt->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Bilet bulunamadı veya bu bilete erişim yetkiniz yok.");
}

$formatted_price = number_format((float)$ticket['price'], 2, ',', '.');
$departure_time = date('H:i', strtotime($ticket['time']));
$departure_date = date('d/m/Y', strtotime($ticket['date']));

// FPDF desteği olmadığından HTML/Yazdırma görünümünü kullanıyoruz
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bilet #<?php echo (int)$ticket['id']; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --bg: #121212;
    --card: #1e1e1e;
    --text: #e0e0e0;
    --primary: #00bcd4; /* Cyan/Teal */
}
body { 
    font-family: 'Inter', sans-serif; 
    background-color: var(--bg); 
    color: var(--text);
}
.ticket-box { 
    max-width: 600px; 
    margin: 50px auto; 
    padding: 35px; 
    background: var(--card); 
    border-radius: 15px; 
    box-shadow: 0 5px 20px rgba(0,0,0,0.5); 
    border-top: 10px solid var(--primary);
    position: relative;
    overflow: hidden;
    z-index: 1;
}
h1 { 
    text-align: center; 
    font-weight: 700; 
    margin-bottom: 25px; 
    color: var(--primary);
}
.ticket-details p { 
    font-size: 17px; 
    margin: 12px 0; 
    padding: 5px 0;
    border-bottom: 1px dashed #333;
}
.ticket-details p:last-child { border-bottom: none; }
.btn-container { text-align: center; margin-top: 30px; }
.btn-print { 
    background-color: #28a745; 
    border-color: #28a745; 
    transition: background-color 0.3s; 
    font-weight: 600;
}
.btn-print:hover { background-color: #1e7e34; }
.btn-secondary { font-weight: 600; }

@media print {
    .btn-container { display: none; }
    body { background: none; color: #000; }
    .ticket-box { box-shadow: none; border: 1px solid #000; background: #fff; color: #000; }
    .ticket-details p { border-bottom: 1px solid #ccc; }
    h1 { color: #000; }
}
</style>
</head>
<body>
<div class="ticket-box">
<h1>🚌 Otobüs Bileti</h1>
<div class="ticket-details">
    <p><strong>Firma:</strong> <?php echo htmlspecialchars($ticket['firm_name']); ?></p>
    <p><strong>Kalkış:</strong> <?php echo htmlspecialchars($ticket['departure']); ?></p>
    <p><strong>Varış:</strong> <?php echo htmlspecialchars($ticket['arrival']); ?></p>
    <p><strong>Tarih/Saat:</strong> <?php echo htmlspecialchars($departure_date); ?> <?php echo htmlspecialchars($departure_time); ?></p>
    <p><strong>Koltuk Numarası:</strong> <span class="badge bg-primary fs-5" style="background-color: var(--primary) !important; color: #000;">NO: <?php echo (int)$ticket['seat_number']; ?></span></p>
    <p><strong>Fiyat:</strong> <?php echo htmlspecialchars($formatted_price); ?> TL</p>
    <p><strong>Durum:</strong> <span class="badge bg-success">AKTİF</span></p>
    <p><strong>Bilet ID:</strong> <?php echo (int)$ticket['id']; ?></p>
</div>
<div class="btn-container">
<button onclick="window.print()" class="btn btn-print btn-lg me-3">🖨️ Yazdır/PDF İndir</button>
<a href="account.php" class="btn btn-secondary btn-lg">Hesabıma Dön</a>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
