# 1. Temel imajı seç (PHP 8.2 ve Apache sunucusu)
FROM php:8.2-apache

# 2. DÜZELTME: Paket listesini güncelle ve SQLite için gerekli kütüphaneleri kur
# Bu komut, pdo_sqlite eklentisinin kurulabilmesi için gereklidir.
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. Gerekli PHP eklentilerini kur (SQLite veritabanı bağlantısı için)
RUN docker-php-ext-install pdo pdo_sqlite

# 4. Apache 'rewrite' modülünü etkinleştir
RUN a2enmod rewrite

# 5. Proje dosyalarını kopyala
COPY . /var/www/html/

# 6. SQLite veritabanı dosyası için yazma izni ver
RUN chown -R www-data:www-data /var/www/html

# 7. Sunucunun 80 portunu dinlediğini belirt
EXPOSE 80