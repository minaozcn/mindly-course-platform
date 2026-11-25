-- Mindly – database schema
-- Reconstructed from the queries in the PHP source code.
-- Import in phpMyAdmin (Import tab) or: mysql -u root < database/mindly.sql

CREATE DATABASE IF NOT EXISTS mindly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mindly;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kullanicilar (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ad_soyad      VARCHAR(100) NOT NULL,
  eposta        VARCHAR(150) NOT NULL UNIQUE,
  telefon       VARCHAR(20)  NULL,
  sifre_hash    VARCHAR(255) NOT NULL,
  kayit_tarihi  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kurslar (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  baslik       VARCHAR(200)  NOT NULL,
  kategori     VARCHAR(100)  NOT NULL,
  seviye       ENUM('Başlangıç','Orta','İleri') NOT NULL,
  ikon         VARCHAR(16)   NOT NULL DEFAULT '📚',
  ders_sayisi  INT           NOT NULL DEFAULT 0,
  sure_saat    INT           NOT NULL DEFAULT 0,
  ogrenci_say  INT           NOT NULL DEFAULT 0,
  fiyat        DECIMAL(10,2) NOT NULL DEFAULT 0,
  aciklama     TEXT          NULL,
  video_url    VARCHAR(255)  NULL,
  aktif        TINYINT(1)    NOT NULL DEFAULT 1,
  olusturma    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS satinalma (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id    INT NOT NULL,
  kurs_id         INT NOT NULL,
  ilerleme        INT NOT NULL DEFAULT 0,
  durum           ENUM('devam','tamamlandi') NOT NULL DEFAULT 'devam',
  satin_alma_tar  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kullanici_kurs (kullanici_id, kurs_id),
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (kurs_id)      REFERENCES kurslar(id)      ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sifre_tokenler (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  eposta        VARCHAR(150) NOT NULL,
  token         CHAR(64)     NOT NULL UNIQUE,
  son_kullanim  DATETIME     NOT NULL,
  kullanildi    TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- One row per completed lesson (used for the dashboard statistics)
CREATE TABLE IF NOT EXISTS ilerleme (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id       INT NOT NULL,
  kurs_id            INT NOT NULL,
  tamamlanan_ders    INT NOT NULL DEFAULT 1,
  guncelleme_tarihi  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (kurs_id)      REFERENCES kurslar(id)      ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample courses
INSERT INTO kurslar (id, baslik, kategori, seviye, ikon, ders_sayisi, sure_saat, ogrenci_say, fiyat, aciklama) VALUES
(1, 'Duygusal Zekâ ve Empati', 'Duygusal Zekâ', 'Başlangıç', '🧠', 24, 8, 82400, 299.00, 'İnsanların ne hissettiğini sadece duymak değil, gerçekten anlamak mı istiyorsun? Duygusal radarını aç, vibe''ları yakala.'),
(2, 'Etkili İletişim Becerileri', 'İletişim Becerileri', 'Orta', '💬', 18, 6, 61200, 349.00, 'Kendini ifade ediyorsun ama anlaşılmıyor mu? Bu kursta "ne dediğin" kadar "nasıl dediğin" de level atlıyor.'),
(3, 'Sınır Koyma ve Kendini Değerlendirme', 'Sınır Koyma', 'İleri', '⛔', 20, 7, 45700, 399.00, '"Hayır" demek suç değil. Enerjini korumayı öğren, kendini başkalarının değil kendi gözünden değerlendir.'),
(4, 'Özgüven ve Kendini Tanıma', 'Özgüven Geliştirme', 'Başlangıç', '🥰', 16, 5, 38900, 249.00, 'Kendinle barış yapma zamanı. Kim olduğunu keşfet, özgüvenini dışarıdan değil içeriden build et.'),
(5, 'Odaklanma ve Konsantrasyon Becerileri', 'Odak ve Konsantrasyon', 'Orta', '🤔', 14, 4, 29300, 279.00, 'Dikkatini yönet, derin odak moduna gir. Pomodoro''dan nörolojiye, verimliliğin bilimini öğren.'),
(6, 'Hedef Belirleme ve Planlama', 'Hedef Belirleme', 'İleri', '☁️', 12, 4, 21500, 319.00, 'Hayal kurmak güzel, ama planlamak gerekli. SMART hedefler kur, yol haritanı çiz, aksiyona geç.'),
(7, 'Zaman Yönetimi ve Öncelik Belirleme', 'Zaman Yönetimi', 'Orta', '⏱️', 22, 7, 78600, 299.00, '24 saat herkese eşit. Ama bazıları daha akıllıca kullanıyor. Önceliklendirmeyi öğren, zamanına sahip çık.'),
(8, 'Büyüme Zihniyeti ve Alışkanlık', 'Kişisel Gelişim', 'Başlangıç', '🌱', 26, 9, 54100, 329.00, 'Beyin değişebilir. Sen değişebilirsin. Küçük alışkanlıklar büyük dönüşümler yaratır — bilimsel kanıtlarla.');
