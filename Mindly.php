<?php
session_start();
require_once 'db.php';

$kayit_hatasi   = '';
$kayit_basarili = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kayit'])) {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $eposta   = trim($_POST['eposta']   ?? '');
    $telefon  = trim($_POST['telefon']  ?? '');
    $sifre    = $_POST['sifre']         ?? '';

    if (empty($ad_soyad) || empty($eposta) || empty($telefon) || empty($sifre)) {
        $kayit_hatasi = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $kayit_hatasi = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($sifre) < 8) {
        $kayit_hatasi = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);
        if ($stmt->fetch()) {
            $kayit_hatasi = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO kullanicilar (ad_soyad, eposta, telefon, sifre_hash)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$ad_soyad, $eposta, $telefon, $sifre_hash]);
            $kayit_basarili = true;
        }
    }
}

$kurslar = [
    ['id'=>1,'ikon'=>'🧠','kategori'=>'Duygusal Zekâ',         'baslik'=>'Duygusal Zekâ ve Empati',               'aciklama'=>"İnsanların ne hissettiğini sadece duymak değil, gerçekten anlamak mı istiyorsun? Duygusal radarını aç, vibe'ları yakala.",'ogrenci'=>'82.400','seviye'=>'Başlangıç','seviye_class'=>'level-beginner','fiyat'=>299.00],
    ['id'=>2,'ikon'=>'💬','kategori'=>'İletişim Becerileri',   'baslik'=>'Etkili İletişim Becerileri',            'aciklama'=>'Kendini ifade ediyorsun ama anlaşılmıyor mu? Bu kursta "ne dediğin" kadar "nasıl dediğin" de level atlıyor.',          'ogrenci'=>'61.200','seviye'=>'Orta',      'seviye_class'=>'level-intermediate','fiyat'=>349.00],
    ['id'=>3,'ikon'=>'⛔','kategori'=>'Sınır Koyma',           'baslik'=>'Sınır Koyma ve Kendini Değerlendirme', 'aciklama'=>'"Hayır" demek suç değil. Enerjini korumayı öğren, kendini başkalarının değil kendi gözünden değerlendir.',           'ogrenci'=>'45.700','seviye'=>'İleri',     'seviye_class'=>'level-advanced','fiyat'=>399.00],
    ['id'=>4,'ikon'=>'🥰','kategori'=>'Özgüven Geliştirme',   'baslik'=>'Özgüven ve Kendini Tanıma',            'aciklama'=>'Kendinle barış yapma zamanı. Kim olduğunu keşfet, özgüvenini dışarıdan değil içeriden build et.',                    'ogrenci'=>'38.900','seviye'=>'Başlangıç','seviye_class'=>'level-beginner','fiyat'=>249.00],
    ['id'=>5,'ikon'=>'🤔','kategori'=>'Odak ve Konsantrasyon', 'baslik'=>'Odaklanma ve Konsantrasyon Becerileri','aciklama'=>"Dikkatini yönet, derin odak moduna gir. Pomodoro'dan nörolojiye, verimliliğin bilimini öğren.",'ogrenci'=>'29.300','seviye'=>'Orta','seviye_class'=>'level-intermediate','fiyat'=>279.00],
    ['id'=>6,'ikon'=>'☁️','kategori'=>'Hedef Belirleme',       'baslik'=>'Hedef Belirleme ve Planlama',          'aciklama'=>'Hayal kurmak güzel, ama planlamak gerekli. SMART hedefler kur, yol haritanı çiz, aksiyona geç.',                     'ogrenci'=>'21.500','seviye'=>'İleri',     'seviye_class'=>'level-advanced','fiyat'=>319.00],
    ['id'=>7,'ikon'=>'⏱️','kategori'=>'Zaman Yönetimi',        'baslik'=>'Zaman Yönetimi ve Öncelik Belirleme', 'aciklama'=>'24 saat herkese eşit. Ama bazıları daha akıllıca kullanıyor. Önceliklendirmeyi öğren, zamanına sahip çık.',          'ogrenci'=>'78.600','seviye'=>'Orta',      'seviye_class'=>'level-intermediate','fiyat'=>299.00],
    ['id'=>8,'ikon'=>'🌱','kategori'=>'Kişisel Gelişim',       'baslik'=>'Büyüme Zihniyeti ve Alışkanlık',      'aciklama'=>'Beyin değişebilir. Sen değişebilirsin. Küçük alışkanlıklar büyük dönüşümler yaratır — bilimsel kanıtlarla.',          'ogrenci'=>'54.100','seviye'=>'Başlangıç','seviye_class'=>'level-beginner','fiyat'=>329.00],
];

$timeline = [
    ['yil' => '2020', 'baslik' => 'Kuruluş',               'aciklama' => "Ankara'da 12 öğrenciyle başlayan küçük bir online eğitim girişimi."],
    ['yil' => '2021', 'baslik' => 'İlk 10.000 Öğrenci',    'aciklama' => "Türkiye'nin her ilinden öğrenciye ulaşarak büyümemizi hızlandırdık."],
    ['yil' => '2022', 'baslik' => 'Uluslararası Genişleme', 'aciklama' => "12 ülkede 100.000+ öğrenciye ulaştık."],
    ['yil' => '2026', 'baslik' => 'Her Gün Büyüyen Bir Topluluk', 'aciklama' => "Siz de aramıza katılın."],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mindly – Online Kişisel Gelişim Akademisi</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="bootstrap.bundle.min.js" defer></script>
</head>
<body>


<nav class="navbar fixed-top">
  <div class="container d-flex align-items-center justify-content-between">
    <div>
      <a class="navbar-brand mb-0" href="Mindly.php">MINDLY</a>
      <p class="slogan mb-0">learn · grow · shine</p>
    </div>
    <div class="d-flex align-items-center gap-3">
      <a href="giris.php" class="btn-giris">Giriş Yap</a>
      <img src="images/open-mind.png" alt="Mindly" width="48" height="48">
    </div>
  </div>
</nav>


<section class="hero" id="hero">
  <div class="container">
    <div class="row align-items-center g-5">

      <div class="col-lg-6">
        <div class="hero-label">Online Kişisel Gelişim Akademisi</div>
        <h1 class="hero-title">MINDLY</h1>
        <p class="hero-sub">learn · grow · shine</p>
        <p class="hero-desc">Kendini tanı, büyü, parla.</p>
        <div class="d-flex flex-wrap gap-2" style="animation: fadeUp 0.5s ease 0.26s both;">
          <a class="hero-nav-link" href="#hakkimizda">Biz Kimiz?</a>
          <a class="hero-nav-link" href="#kurslar">Kurslarımız</a>
          <a class="hero-nav-link" href="#iletisim">İletişim</a>
        </div>
      </div>

      <div class="col-lg-5 offset-lg-1">
        <div class="kayit-card">
          <div class="kayit-card-label">Ücretsiz Üyelik</div>
          <h2 class="kayit-title">Hemen Başla</h2>
          <p class="kayit-sub">Mindly'e üye ol, gelişimini bugün başlat.</p>

          <?php if ($kayit_basarili): ?>
            <div class="alert alert-success" role="alert">
              Kayıt başarılı! <a href="giris.php">Giriş yapın →</a>
            </div>
          <?php else: ?>
            <?php if (!empty($kayit_hatasi)): ?>
              <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($kayit_hatasi) ?>
              </div>
            <?php endif; ?>
            <form method="POST" action="Mindly.php" novalidate>
              <div class="mb-3">
                <label class="form-label">Ad Soyad</label>
                <input type="text" name="ad_soyad" class="form-control kayit-input"
                  placeholder="Adın Soyadın"
                  value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">E-posta</label>
                <input type="email" name="eposta" class="form-control kayit-input"
                  placeholder="ornek@mail.com"
                  value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Telefon</label>
                <input type="tel" name="telefon" class="form-control kayit-input"
                  placeholder="05XX XXX XX XX"
                  value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>">
              </div>
              <div class="mb-4">
                <label class="form-label">Şifre</label>
                <input type="password" name="sifre" class="form-control kayit-input"
                  placeholder="En az 8 karakter">
              </div>
              <button type="submit" name="kayit" class="btn-kayit">Kayıt Ol →</button>
            </form>
            <p class="kayit-login">Zaten üye misin? <a href="giris.php">Giriş yap</a></p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- HAKKIMIZDA -->
<section class="section-block" id="hakkimizda">
  <div class="container">
    <div class="row mb-5">
      <div class="col-lg-8">
        <div class="section-eyebrow">Platform</div>
        <span class="section-divider"></span>
        <h2 class="section-heading">Merhaba, biz <em>MINDLY</em> 👋</h2>
      </div>
    </div>
    <div class="row g-4 align-items-start">
      <div class="col-lg-7">
        <div class="about-card">
          <p class="about-text">
            Bir grup öğretmen, psikolog ve <strong>"keşke bunu lisede öğretseydiler"</strong> diyen yetişkinden oluşuyoruz.
            2021 yılında Ankara'da küçük bir ekiple yola çıktık. Amacımız tek bir soruya cevap bulmaktı:
            <strong>Gençlere kimse stresle nasıl başa çıkacağını öğretmiyor, neden?</strong>
          </p>
          <p class="about-text mt-3">
            O günden bu yana <strong>100.000'den fazla öğrenciye</strong> ulaştık.
            <strong>MINDLY</strong>, sadece bir eğitim platformu değil, aynı zamanda bir topluluk.
          </p>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-4"><div class="stat-pill blue"><div class="stat-pill-val">100K+</div><div class="stat-pill-label">Öğrenci</div></div></div>
          <div class="col-4"><div class="stat-pill pink"><div class="stat-pill-val">12</div><div class="stat-pill-label">Ülke</div></div></div>
          <div class="col-4"><div class="stat-pill green"><div class="stat-pill-val">8</div><div class="stat-pill-label">Kurs</div></div></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="section-eyebrow mb-3">Evrimiz 📈</div>
        <div class="timeline">
          <?php foreach ($timeline as $item): ?>
            <div class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-year"><?= htmlspecialchars($item['yil']) ?></div>
              <div class="timeline-content">
                <h5><?= htmlspecialchars($item['baslik']) ?></h5>
                <p><?= htmlspecialchars($item['aciklama']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- KURSLAR -->
<section class="section-block" id="kurslar">
  <div class="container">
    <div class="row mb-2">
      <div class="col-lg-8">
        <div class="section-eyebrow">Eğitimler</div>
        <span class="section-divider"></span>
        <h2 class="section-heading mb-3">Kurslarımız</h2>
        <p class="courses-intro">
          40.000'den fazla öğrenciye ulaşarak dünya genelinde kabul görmüş içeriklerle öğrenmeyi herkes için erişilebilir kılıyoruz.
        </p>
      </div>
    </div>
    <div class="row g-4">
      <?php foreach ($kurslar as $i => $kurs): ?>
        <div class="col-sm-6 col-xl-3" style="animation: fadeUp 0.5s ease <?= 0.06 * $i ?>s both;">
          <div class="course-card"
            onmousemove="this.style.setProperty('--mx', ((event.clientX - this.getBoundingClientRect().left)/this.getBoundingClientRect().width*100)+'%');this.style.setProperty('--my', ((event.clientY - this.getBoundingClientRect().top)/this.getBoundingClientRect().height*100)+'%')">
            <span class="course-icon"><?= $kurs['ikon'] ?></span>
            <div class="course-category"><?= htmlspecialchars($kurs['kategori']) ?></div>
            <div class="course-title"><?= htmlspecialchars($kurs['baslik']) ?></div>
            <div class="course-desc"><?= htmlspecialchars($kurs['aciklama']) ?></div>
            <div class="course-meta-row">
              <span class="course-students">👤 <?= htmlspecialchars($kurs['ogrenci']) ?></span>
              <span class="course-level <?= $kurs['seviye_class'] ?>"><?= htmlspecialchars($kurs['seviye']) ?></span>
            </div>
            <div class="course-footer">
              <div class="course-price"><sup>₺</sup><?= number_format($kurs['fiyat'], 2, ',', '.') ?></div>
              <a href="giris.php" class="btn-satin-al">🛒 Satın Al</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- İLETİŞİM -->
<section class="section-block" id="iletisim">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <div class="section-eyebrow">İletişim</div>
        <span class="section-divider"></span>
        <h2 class="section-heading mb-3">Bizimle<br><em>İletişime Geçin</em></h2>
        <p style="color: var(--muted); font-size: 0.9rem; font-weight: 300; line-height: 1.7;">
          Sorularınız, iş birliği teklifleriniz veya eğitim hakkında bilgi almak için bize ulaşabilirsiniz.
        </p>
      </div>
      <div class="col-lg-6 offset-lg-1">
        <div class="iletisim-card">
          <a href="mailto:ozcnmina@gmail.com" class="iletisim-link">
            <span class="icon">📧</span>
            <div><strong>E-posta</strong>ozcnmina@gmail.com</div>
          </a>
          <a href="https://www.linkedin.com/in/minaozcan" target="_blank" class="iletisim-link">
            <span class="icon">💼</span>
            <div><strong>LinkedIn</strong>linkedin.com/in/minaozcan</div>
          </a>
          <a href="https://github.com/minaozcn" target="_blank" class="iletisim-link">
            <span class="icon">💻</span>
            <div><strong>GitHub</strong>github.com/minaozcn</div>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
  <div class="container">
    <p>© 2026 Mindly — learn · grow · shine</p>
  </div>
</footer>

</body>
</html>