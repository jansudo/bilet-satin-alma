# Otobüs Bilet Satın Alma Platformu

## Projenin Amacı

Bu proje, web tabanlı bir otobüs bilet satış sistemidir. Kullanıcıların sefer aramasına, bilet satın almasına ve bakiyelerini yönetmesine olanak tanır. Sistem; normal kullanıcılar, firma yöneticileri (company_admin) ve sistem yöneticisi (admin) olmak üzere üç farklı rolü destekler.

## Kullanılan Teknolojiler

- Backend: PHP 8.2

- Veritabanı: SQLite

- Web Sunucusu: Apache

- Frontend: HTML, Bootstrap 5, JavaScript

- Containerization: Docker, Docker Compose

## Kurulum ve Çalıştırma

Projeyi çalıştırmak için Docker ve Docker Compose'un yüklü olması gerekmektedir.

Proje dosyalarının bulunduğu ana dizinde terminali açın.

Aşağıdaki komutu çalıştırarak Docker container'larını oluşturun ve başlatın:

```bash
git clone https://github.com/jansudo/bilet-satin-alma/
cd bilet-satin-alma
```

```bash
docker-compose up -d --build
```

Kurulum tamamlandığında, uygulamaya erişmek için tarayıcınızdan http://localhost:8080 adresini ziyaret edin.
