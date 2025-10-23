<?php
session_start();
require_once "db.php";

$message = '';
$message_type = 'danger';

if (!isset($_SESSION['user'])) {
    // Kullanıcıyı oturum açma sayfasına yönlendir
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = "Geçersiz istek metodu.";
    header("Location: index.php");
    exit;
}

$trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
$selected_seat = filter_input(INPUT_POST, 'selected_seat', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user']['id'];

// Başarılı işlem sonrası yönlendirilecek yer
$redirect_location = $trip_id ? "details.php?id=" . $trip_id : 'index.php';

if (!$trip_id || !$selected_seat) {
    $message = "Sefer veya koltuk numarası eksik. Lütfen tekrar deneyin.";
    $_SESSION['purchase_message'] = $message;
    $_SESSION['purchase_message_type'] = 'danger';
    header("Location: $redirect_location");
    exit;
}

try {
    // 1. Sefer ve Kullanıcı bakiyesi bilgilerini çek
    $db->beginTransaction();

    $st_trip = $db->prepare("SELECT price, firm_id, seat_count FROM trips WHERE id = :trip_id");
    $st_trip->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
    $st_trip->execute();
    $trip = $st_trip->fetch(PDO::FETCH_ASSOC);
    $price = (float)($trip['price'] ?? 0);

    // KULLANICI BİLGİLERİNİ TEKRAR ÇEK (En güncel bakiye için)
    $st_user = $db->prepare("SELECT id, credit, role, username FROM users WHERE id = :user_id");
    $st_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $st_user->execute();
    $user = $st_user->fetch(PDO::FETCH_ASSOC);
    $user_credit = (float)($user['credit'] ?? 0);

    if (!$trip) {
        $message = "Satın alma başarısız: Sefer bulunamadı.";
    } elseif ($user_credit < $price) {
        $message = "Yetersiz bakiye. Bilet fiyatı: " . number_format($price, 2, ',', '.') . " TL. Mevcut Bakiye: " . number_format($user_credit, 2, ',', '.') . " TL.";
    } else {
        // 2. Koltuk doluluk kontrolü (Tekrar) - ACID garantisi için transaction içinde
        $st_check_seat = $db->prepare("SELECT id FROM tickets WHERE trip_id = :trip_id AND seat_number = :seat AND status = 'active'");
        $st_check_seat->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
        $st_check_seat->bindParam(':seat', $selected_seat, PDO::PARAM_INT);
        $st_check_seat->execute();

        if ($st_check_seat->fetch()) {
            $message = "Üzgünüz, bu koltuk sizden önce satıldı.";
        } else {
            // 3. Satın Alma İşlemleri (Bakiye Düşürme ve Bilet Ekleme)
            
            // a. Bakiye Düşürme
            $new_credit = $user_credit - $price;
            $st_update_credit = $db->prepare("UPDATE users SET credit = :new_credit WHERE id = :user_id");
            $st_update_credit->bindParam(':new_credit', $new_credit);
            $st_update_credit->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $st_update_credit->execute();

            // b. Bilet Ekleme
            $st_insert_ticket = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, purchase_time) VALUES (:user_id, :trip_id, :seat, datetime('now'))");
            $st_insert_ticket->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $st_insert_ticket->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
            $st_insert_ticket->bindParam(':seat', $selected_seat, PDO::PARAM_INT);
            $st_insert_ticket->execute();

            $db->commit();
            
            // Oturumdaki kullanıcı bilgilerini güncelle
            $_SESSION['user']['credit'] = $new_credit;

            $message = "Biletiniz başarıyla satın alındı! Koltuk: " . htmlspecialchars($selected_seat) . ", Fiyat: " . number_format($price, 2, ',', '.') . " TL.";
            $message_type = 'success';
            
            // Başarılı işlem sonrası ana sayfaya yönlendir, detay sayfasına değil (isteğe göre değiştirilebilir)
            $_SESSION['purchase_message'] = $message;
            $_SESSION['purchase_message_type'] = $message_type;
            header("Location: index.php");
            exit;
        }
    }
    
    // Hata durumunda detay sayfasına geri yönlendir ve mesajı taşı
    $_SESSION['purchase_message'] = $message;
    $_SESSION['purchase_message_type'] = 'danger';
    header("Location: $redirect_location");
    exit;

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $message = "Satın alma işlemi sırasında kritik bir hata oluştu: " . $e->getMessage();
    $_SESSION['purchase_message'] = $message;
    $_SESSION['purchase_message_type'] = 'danger';
    header("Location: $redirect_location");
    exit;
}
?>
