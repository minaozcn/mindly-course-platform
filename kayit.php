<?php

require_once 'db.php';
$hata    = '';
$basarili = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kayit'])) {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $eposta   = trim($_POST['eposta'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');
    $sifre    = $_POST['sifre'] ?? '';

    // --- Doğrulama ---
    if (empty($ad_soyad) || empty($eposta) || empty($telefon) || empty($sifre)) {
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($sifre) < 8) {
        $hata = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        // E-posta daha önce kayıtlı mı?
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);

        if ($stmt->fetch()) {
            $hata = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            // Şifreyi hashle ve kaydet
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO kullanicilar (ad_soyad, eposta, telefon, sifre_hash)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$ad_soyad, $eposta, $telefon, $sifre_hash]);

            $basarili = true;
            // header('Location: giris.php?kayit=1');
            // exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kayıt Ol – Mindly</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="bootstrap.bundle.min.js"></script>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
    <div>
      <a class="navbar-brand mb-0" href="Mindly.php">MINDLY</a>
      <p class="mb-0 slogan">learn · grow · shine</p>
    </div>
    <img src="images/open-mind.png" alt="Logo" width="60" height="60" class="d-inline-block align-text-top ms-auto">
  </div>
</nav>

<div class="hero d-flex align-items-center justify-content-center text-white">
  <div class="col-lg-4 col-md-6 col-10">
    <div class="kayit-card">

      <h2 class="kayit-title text-center">Hemen Başla</h2>
      <p class="kayit-sub text-center">Mindly'e ücretsiz üye ol, gelişimini başlat.</p>

      <?php if ($basarili): ?>
        <div class="alert alert-success" role="alert">
          Kaydınız başarıyla oluşturuldu! <a href="giris.php">Giriş yapabilirsiniz.</a>
        </div>
      <?php else: ?>

        <?php if (!empty($hata)): ?>
          <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($hata); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="kayit.php" novalidate>
          <div class="mb-3">
            <label class="form-label">Ad Soyad</label>
            <input type="text" name="ad_soyad" class="form-control kayit-input"
              placeholder="Adın Soyadın"
              value="<?php echo htmlspecialchars($_POST['ad_soyad'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">E-posta</label>
            <input type="email" name="eposta" class="form-control kayit-input"
              placeholder="ornek@mail.com"
              value="<?php echo htmlspecialchars($_POST['eposta'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Telefon</label>
            <input type="tel" name="telefon" class="form-control kayit-input"
              placeholder="05XX XXX XX XX"
              value="<?php echo htmlspecialchars($_POST['telefon'] ?? ''); ?>">
          </div>
          <div class="mb-4">
            <label class="form-label">Şifre</label>
            <input type="password" name="sifre" class="form-control kayit-input"
              placeholder="En az 8 karakter">
          </div>

          <button type="submit" name="kayit" class="btn btn-outline-light btn-lg w-100">Kayıt Ol</button>
        </form>

        <p class="kayit-login mt-3 text-center">
          Zaten üye misin? <a href="giris.php">Giriş yap</a>
        </p>

      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>