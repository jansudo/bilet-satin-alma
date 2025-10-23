<?php
// db.php: Veritabanı Bağlantı Dosyası
try {
    // SQLite veritabanı bağlantısı. 
    // Eğer 'otobus.db' dosyası yoksa, PDO otomatik olarak oluşturacaktır.
    $db = new PDO('sqlite:otobus.db');
    
    // Hata modunu istisnalar (exceptions) fırlatacak şekilde ayarla
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Veritabanı tablosu oluşturma (Eğer yoksa)
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user', -- 'user', 'company_admin', 'admin'
            credit REAL DEFAULT 0.00,
            firm_id INTEGER DEFAULT NULL, -- EKLENDİ: Firma adminlerini firmaya bağlamak için
            FOREIGN KEY (firm_id) REFERENCES firms(id)
        );
        CREATE TABLE IF NOT EXISTS firms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            firm_id INTEGER NOT NULL,
            departure TEXT NOT NULL,
            arrival TEXT NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            price REAL NOT NULL,
            seat_count INTEGER DEFAULT 45, -- Otobüs koltuk kapasitesi (2+1 düzen için 45)
            FOREIGN KEY (firm_id) REFERENCES firms(id)
        );
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            trip_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL,
            purchase_time DATETIME NOT NULL,
            status TEXT DEFAULT 'active', -- 'active' veya 'cancelled'
            UNIQUE (trip_id, seat_number), -- Bir koltuk bir seferde sadece bir kez satılabilir
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (trip_id) REFERENCES trips(id)
        );
        CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            discount_percent INTEGER NOT NULL,
            expiry_date DATE
        );
    ");

    // Deneme verileri ekleme (Sadece tablolarda veri yoksa)
    $firm_count = $db->query("SELECT COUNT(*) FROM firms")->fetchColumn();
    if ($firm_count == 0) {
        $db->exec("INSERT INTO firms (name) VALUES ('Hızlı Seyahat'), ('Güneş Turizm')");
        
        // Örnek admin ve kullanıcı ekleme
        $db->exec("INSERT INTO users (username, password, role, credit) VALUES ('admin', '" . password_hash('123', PASSWORD_DEFAULT) . "', 'admin', 1000.00)");
        $db->exec("INSERT INTO users (username, password, role, credit) VALUES ('user1', '" . password_hash('123', PASSWORD_DEFAULT) . "', 'user', 50.00)");
        
        // DÜZELTME: Firma adminleri eklendi (firma_id 1 ve 2 için)
        $db->exec("INSERT INTO users (username, password, role, firm_id) VALUES ('firma_admin_hs', '" . password_hash('123', PASSWORD_DEFAULT) . "', 'company_admin', 1)");
        $db->exec("INSERT INTO users (username, password, role, firm_id) VALUES ('firma_admin_gt', '" . password_hash('123', PASSWORD_DEFAULT) . "', 'company_admin', 2)");
        
        // Örnek seferler ekleme (Gelecek tarihler için)
        $next_day = date('Y-m-d', strtotime('+1 day'));
        $db->exec("INSERT INTO trips (firm_id, departure, arrival, date, time, price, seat_count) VALUES (1, 'İstanbul', 'Ankara', '$next_day', '10:00:00', 350.50, 45)");
        $db->exec("INSERT INTO trips (firm_id, departure, arrival, date, time, price, seat_count) VALUES (2, 'İzmir', 'İstanbul', '$next_day', '14:30:00', 400.00, 40)");
        
        // details.php?id=1'in çalıştığını göstermek için 1. seferin ID'sini kontrol et.
        $trip_id_1 = $db->query("SELECT id FROM trips WHERE departure = 'İstanbul' AND arrival = 'Ankara' AND date = '$next_day'")->fetchColumn();
        if ($trip_id_1) {
            // Örnek bilet ekleme (Koltuk 1 ve 5 dolu olsun)
            // Bu koltuklar details.php'de dolu olarak görünmelidir.
            $db->exec("INSERT INTO tickets (user_id, trip_id, seat_number, purchase_time) VALUES (2, $trip_id_1, 1, datetime('now'))");
            $db->exec("INSERT INTO tickets (user_id, trip_id, seat_number, purchase_time) VALUES (2, $trip_id_1, 5, datetime('now'))");
        }
    }

} catch (PDOException $e) {
    // Uygulama seviyesinde hata yönetimi için $db değişkenini null yap
    $db = null;
    // Hata mesajını index.php'nin yakalaması için bir hata fırlat
    throw new PDOException("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>