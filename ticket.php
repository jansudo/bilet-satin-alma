<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header('Location: login.php');
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

// Güvenli ID alımı
$trip_id = filter_input(INPUT_GET, 'trip_id', FILTER_VALIDATE_INT);
if (!$trip_id) {
    die('Geçerli bir Sefer ID gerekli.');
}

// 1. Sefer bilgisini çek
$st = $db->prepare("SELECT price, seat_count, departure, arrival FROM trips WHERE id = :trip_id");
$st->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$st->execute();
$trip = $st->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    die('Sefer bulunamadı.');
}

$seat_count = (int)$trip['seat_count'];
$price = (float)$trip['price'];

// 2. Dolu koltukları çek
$st = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = :trip_id AND status = 'active'");
$st->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$st->execute();
$occupied = $st->fetchAll(PDO::FETCH_COLUMN);
$occupied_map = array_flip(array_map('intval', $occupied));

if (count($occupied) >= $seat_count) {
    die('Bu seferde boş koltuk kalmamış. Lütfen geri dönün.');
}

// 3. İlk boş koltuğu bul
$seat_number = null;
for ($i = 1; $i <= $seat_count; $i++) {
    if (!isset($occupied_map[$i])) { 
        $seat_number = $i; 
        break; 
    }
}
if (!$seat_number) {
    // Teorik olarak bu satıra düşmemeli, ama güvenlik için
    die('Koltuk atama hatası. Lütfen tekrar deneyin.');
}


// 4. Kullanıcı bakiyesini çek ve kontrol et
$st = $db->prepare("SELECT credit FROM users WHERE id = :user_id");
$st->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$st->execute();
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['credit'] < $price) {
    // Yetersiz bakiye durumunda kullanıcıyı bakiye ekleme sayfasına yönlendirilebilir
    $required_credit = $price - (float)$user['credit'];
    die("Yetersiz bakiye! Bilet fiyatı: " . number_format($price, 2) . " TL. Hesabınızda " . number_format((float)$user['credit'], 2) . " TL var. Lütfen " . number_format($required_credit, 2) . " TL daha yükleyin. <a href='deposit.php'>Bakiye Yükle</a>");
}

// 5. İşlemleri başlat (ACID için Transaction)
try {
    $db->beginTransaction();

    // Koltuğu yeniden kontrol et ve rezerve et (Race condition önleme)
    $check_seat = $db->prepare("SELECT 1 FROM tickets WHERE trip_id = :trip_id AND seat_number = :seat_number AND status = 'active'");
    $check_seat->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
    $check_seat->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $check_seat->execute();
    if ($check_seat->fetch()) {
        $db->rollBack();
        die('Seçilen koltuk kısa süre önce satıldı. Lütfen işlemi tekrar deneyin.');
    }

    // Bilet Ekleme
    $ins = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, status, purchase_time) 
                         VALUES (:user_id, :trip_id, :seat_number, 'active', datetime('now'))");
    $ins->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $ins->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
    $ins->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $ins->execute();
    $ticket_id = $db->lastInsertId();

    // Bakiye Güncelleme (Negatif bakiye kontrolü ile)
    $upd = $db->prepare("UPDATE users SET credit = credit - :price WHERE id = :user_id AND credit >= :price_check");
    $upd->bindParam(':price', $price, PDO::PARAM_STR); 
    $upd->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $upd->bindParam(':price_check', $price, PDO::PARAM_STR);
    $upd->execute();
    
    // Etkilenen satır sayısını kontrol et (Bakiye yetersizliği hatasına karşı)
    if ($upd->rowCount() === 0) {
        $db->rollBack();
        die("Bakiye güncelleme hatası. İşlem tamamlanamadı. (Tekrar yetersiz bakiye?)");
    }

    // Başarılı Commit
    $db->commit();

    // Oturumdaki bakiye bilgisini güncelle (UX için)
    $_SESSION['user']['credit'] = (float)$user['credit'] - $price;

    // Başarılı yönlendirme
    header('Location: pdf.php?id=' . urlencode($ticket_id));
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // Hata mesajını göster
    die('Bilet alımında beklenmedik bir hata oluştu. Lütfen sistem yöneticisine başvurun. Hata: ' . htmlspecialchars($e->getMessage()));
}
