<?php
session_start();
require_once "db.php";

$message = '';
$message_type = '';
$trip = null; 

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    // ID yoksa ana sayfaya yönlendir
    header('Location: index.php');
    exit;
}

try {
    // 1. Sefer ve Firma bilgilerini çek
    $st = $db->prepare("SELECT trips.*, firms.name AS firm_name FROM trips JOIN firms ON trips.firm_id = firms.id WHERE trips.id = :id");
    $st->bindParam(':id', $id, PDO::PARAM_INT);
    $st->execute();
    $trip = $st->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $message = 'Aradığınız sefer bulunamadı veya silinmiş.';
        $message_type = 'danger';
    } else {
        // Koltuk doluluğunu hesapla ve satılan koltuk numaralarını çek
        $st_seats = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = :id AND status = 'active'");
        $st_seats->bindParam(':id', $id, PDO::PARAM_INT);
        $st_seats->execute();
        $sold_seats = $st_seats->fetchAll(PDO::FETCH_COLUMN);

        // Satılan koltuk numaralarını bir diziye çevir (Integer olarak)
        $sold_seats_array = array_map('intval', $sold_seats); 
        
        $available_seats = (int)$trip['seat_count'] - count($sold_seats_array);
        $is_full = $available_seats <= 0;

        $display_date = date('d/m/Y', strtotime($trip['date']));
        $display_time = date('H:i', strtotime($trip['time']));
    }
} catch (PDOException $e) {
    $message = "Veritabanı hatası: Sefer detayları yüklenemedi. " . $e->getMessage();
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sefer Detayı | Yolcu Platformu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    --bg: #0a0a0a; 
    --card: #1c1c1c; 
    --text: #ffffff; 
    --primary: #00bcd4; 
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
}
body { 
    background-color: var(--bg); 
    color: var(--text); 
    font-family: 'Inter', sans-serif; 
    padding-top: 56px;
}
.navbar { background-color: var(--card) !important; border-bottom: 1px solid #333; }
.container { max-width: 900px; margin-top: 50px; margin-bottom: 50px; }
.card { 
    border-radius: 15px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.6); 
    background: var(--card); 
    border: 1px solid #333;
}
h1 { font-weight: 700; color: var(--primary); }
.list-group-item { background-color: #2c2c2c; border: 1px solid #444; color: var(--text); }
.list-group-item strong { color: var(--primary); font-weight: 600; }
.btn-action { border-radius: 8px; padding: 10px 0; }
.alert-danger { background-color: #5b2020; border-color: #5b2020; color: #f59f9f; }
.alert-warning { background-color: #4a3e1c; border-color: #4a3e1c; color: #ffeb9c; }
.alert-link { color: var(--primary) !important; font-weight: bold; }

/* KOLTUK DÜZENİ STİLLERİ */
.bus-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    background-color: #2c2c2c;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}
.seating-area {
    display: flex;
    gap: 20px;
    justify-content: center;
    width: 100%;
}
/* Koltukları yan yana ve alt alta düzgün sıralamak için */
.column-2 {
    display: flex;
    flex-direction: column; 
    gap: 10px; /* Satırlar arası boşluk */
}
.column-1 {
    display: flex;
    flex-direction: column;
    gap: 10px; /* Satırlar arası boşluk */
}

.seat-row {
    display: flex;
    gap: 10px; /* Koltuklar arası boşluk */
}

.seat {
    width: 40px;
    height: 40px;
    border-radius: 5px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.seat.available {
    background-color: var(--success);
    color: var(--text);
}
.seat.available:hover {
    background-color: #218838;
    box-shadow: 0 0 10px var(--success);
}

.seat.selected {
    background-color: var(--primary);
    color: var(--bg);
    border: 3px solid var(--warning);
    transform: scale(1.1);
}

.seat.occupied {
    background-color: var(--danger);
    color: var(--text);
    cursor: not-allowed;
    opacity: 0.8;
}

.driver-info {
    width: 100px;
    height: 40px;
    background-color: #555;
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 5px;
    margin-bottom: 20px;
    font-weight: bold;
}

/* Mobil için düzenleme */
@media (max-width: 768px) {
    .seating-area {
        flex-direction: column;
    }
    .column-2, .column-1 {
        flex-basis: 100%;
        max-height: none;
        flex-direction: row; /* Mobil cihazlarda yatayda sırala */
        flex-wrap: wrap; /* Yatay sığmazsa alt satıra geçsin */
        justify-content: center;
    }
    .bus-layout { padding: 10px; }
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php" style="color: var(--primary) !important; font-weight: bold;">Yolcu Platformu</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Ana Sayfa</a></li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li class="nav-item"><a class="nav-link" href="account.php">Hesabım</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Çıkış Yap</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Giriş Yap</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
<div class="card p-4 p-md-5">
<h1 class="text-center mb-4">Sefer Detayı: <?php echo htmlspecialchars($trip['departure'] ?? ''); ?> - <?php echo htmlspecialchars($trip['arrival'] ?? ''); ?></h1>

<?php 
    // Satın alma işleminden gelen mesaj varsa göster
    if (isset($_SESSION['purchase_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['purchase_message_type'] ?? 'info'; ?> text-center mb-4">
            <?php echo $_SESSION['purchase_message']; ?>
        </div>
        <?php 
        unset($_SESSION['purchase_message']);
        unset($_SESSION['purchase_message_type']);
    endif;
    // Kendi hataları varsa göster
    if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> text-center mb-4">
            <?php echo $message; ?>
        </div>
<?php endif; ?>

<?php if ($trip): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item"><strong>Firma:</strong> <?php echo htmlspecialchars($trip['firm_name']); ?></li>
                <li class="list-group-item"><strong>Güzergah:</strong> <?php echo htmlspecialchars($trip['departure']); ?> → <?php echo htmlspecialchars($trip['arrival']); ?></li>
            </ul>
        </div>
        <div class="col-md-6">
            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item"><strong>Tarih/Saat:</strong> <?php echo htmlspecialchars($display_date); ?> | <?php echo htmlspecialchars($display_time); ?></li>
                <li class="list-group-item">
                    <strong>Fiyat:</strong> 
                    <span class="h5" style="color: var(--primary);"><?php echo number_format((float)$trip['price'], 2, ',', '.'); ?> TL</span>
                </li>
            </ul>
        </div>
    </div>

    <h2 class="h4 text-center mb-3" style="color: var(--primary);">Koltuk Seçimi (Toplam: <?php echo (int)$trip['seat_count']; ?>)</h2>

    <div class="bus-layout">
        <div class="driver-info"><i class="fas fa-steering-wheel me-2"></i> ŞOFÖR</div>
        <div class="seating-area">
            
            <?php
            $seat_counter_col2 = 1;
            $col2_seats = [];
            $col1_seats = [];

            // Koltuk numaralarını 2'li ve 1'li kolonlara ayır
            for ($i = 1; $i <= (int)$trip['seat_count']; $i++) {
                if ($i % 3 === 0) {
                    $col1_seats[] = $i; // Tekli (3, 6, 9...)
                } else {
                    $col2_seats[] = $i; // Çiftli (1, 2, 4, 5, 7, 8...)
                }
            }
            ?>

            <div class="column-2">
                <?php 
                // 2'li koltukları satır satır yerleştir
                for ($i = 0; $i < count($col2_seats); $i += 2): 
                ?>
                    <div class="seat-row">
                        <?php 
                        // 1. Koltuk (Sol)
                        $seat_num1 = $col2_seats[$i];
                        $is_sold1 = in_array($seat_num1, $sold_seats_array);
                        $status_class1 = $is_sold1 ? 'occupied' : 'available';
                        ?>
                        <div class="seat <?php echo $status_class1; ?>" 
                             data-seat="<?php echo $seat_num1; ?>" 
                             data-status="<?php echo $status_class1; ?>"
                             id="seat-<?php echo $seat_num1; ?>">
                            <?php echo $seat_num1; ?>
                        </div>

                        <?php 
                        // 2. Koltuk (Sağ)
                        if (isset($col2_seats[$i + 1])):
                            $seat_num2 = $col2_seats[$i + 1];
                            $is_sold2 = in_array($seat_num2, $sold_seats_array);
                            $status_class2 = $is_sold2 ? 'occupied' : 'available';
                        ?>
                            <div class="seat <?php echo $status_class2; ?>" 
                                 data-seat="<?php echo $seat_num2; ?>" 
                                 data-status="<?php echo $status_class2; ?>"
                                 id="seat-<?php echo $seat_num2; ?>">
                                <?php echo $seat_num2; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="column-1">
                <?php 
                // Tekli koltukları satır satır yerleştir
                foreach ($col1_seats as $seat_num):
                ?>
                    <div class="seat-row">
                        <?php
                        $is_sold = in_array($seat_num, $sold_seats_array);
                        $status_class = $is_sold ? 'occupied' : 'available';
                        ?>
                        <div class="seat <?php echo $status_class; ?>" 
                             data-seat="<?php echo $seat_num; ?>" 
                             data-status="<?php echo $status_class; ?>"
                             id="seat-<?php echo $seat_num; ?>">
                            <?php echo $seat_num; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <span class="badge bg-success me-2">Boş</span>
        <span class="badge bg-danger me-2">Dolu</span>
        <span class="badge" style="background-color: var(--primary); border: 1px solid var(--warning);">Seçili</span>
    </div>

    <form id="purchaseForm" action="purchase.php" method="POST" class="mt-4">
        <input type="hidden" name="trip_id" value="<?php echo (int)$trip['id']; ?>">
        <input type="hidden" name="selected_seat" id="selected_seat" value="">

        <div class="alert alert-info text-center" id="statusMessage">Lütfen koltuğunuzu seçin.</div>

        <?php if (!isset($_SESSION['user'])): ?>
            <div class="alert alert-warning text-center">Bilet almak için lütfen <a href="login.php" class="alert-link">giriş yapın</a>.</div>
            <button class="btn btn-primary w-100 btn-action" disabled>Bilet Satın Al</button>
        <?php else: ?>
            <button type="submit" class="btn btn-primary w-100 btn-action" id="purchaseButton" disabled>
                <i class="fas fa-ticket-alt me-2"></i> Seçilen Koltuğu Satın Al
            </button>
        <?php endif; ?>
    </form>

<?php endif; ?>

<a href="index.php" class="btn btn-secondary w-100 btn-action mt-3">Ana Sayfaya Dön</a>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat.available');
    const selectedSeatInput = document.getElementById('selected_seat');
    const statusMessage = document.getElementById('statusMessage');
    const purchaseButton = document.getElementById('purchaseButton');

    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            // Sadece boş (available) koltuklar tıklanabilir
            if (this.dataset.status === 'available') {
                const seatNumber = this.dataset.seat;
                
                // Eğer zaten seçiliyse, seçimi kaldır
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    selectedSeatInput.value = '';
                    statusMessage.textContent = 'Lütfen koltuğunuzu seçin.';
                    if (purchaseButton) purchaseButton.disabled = true;
                } else {
                    // Tüm seçimi kaldır
                    document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
                    
                    // Yeni seçimi yap
                    this.classList.add('selected');
                    selectedSeatInput.value = seatNumber;
                    statusMessage.innerHTML = `<strong>${seatNumber} numaralı koltuk</strong> seçildi. Satın almak için butona tıklayın.`;
                    if (purchaseButton) purchaseButton.disabled = false;
                }
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>