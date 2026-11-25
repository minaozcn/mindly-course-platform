
<?php
require_once 'db.php';

$hata           = '';
$gonderildi     = false;
$eposta_gonderilen = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre_sifirla'])) {
    $eposta = trim($_POST['eposta'] ?? '');

    if (empty($eposta)) {
        $hata = 'Lütfen e-posta adresinizi girin.';
    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Lütfen geçerli bir e-posta adresi gir.';
    } else {
        // Kullanıcı mevcut mu kontrol et
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);
        $kullanici = $stmt->fetch();

        // Güvenlik: kullanıcı bulunamasa bile aynı mesajı göster (kullanıcı numaralandırma saldırısına karşı)
        if ($kullanici) {
            // Eski tokenleri temizle
            $stmt = $pdo->prepare("DELETE FROM sifre_tokenler WHERE eposta = ?");
            $stmt->execute([$eposta]);

            // Yeni token oluştur (1 saat geçerli)
            $token       = bin2hex(random_bytes(32));
            $son_kullanim = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare(
                "INSERT INTO sifre_tokenler (eposta, token, son_kullanim) VALUES (?, ?, ?)"
            );
            $stmt->execute([$eposta, $token, $son_kullanim]);

            error_log("Şifre sıfırlama token'ı [$eposta]: $token");
        }

        $gonderildi        = true;
        $eposta_gonderilen = $eposta;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şifremi Unuttum – Mindly</title>
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

    <?php if (!$gonderildi): ?>
    <div class="kayit-card" id="adim-1">
      <div class="text-center mb-4">
        <span style="font-size: 2.5rem;">🔒</span>
      </div>
      <h2 class="kayit-title text-center">Şifreni mi unuttun?</h2>
      <p class="kayit-sub text-center">
        Sorun yok! Hesabına kayıtlı e-posta adresini gir, sana sıfırlama bağlantısı gönderelim.
      </p>

      <?php if (!empty($hata)): ?>
        <div class="alert alert-danger" role="alert">
          <?php echo htmlspecialchars($hata); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="sifreyenileme.php" novalidate>
        <div class="mb-4">
          <label class="form-label">E-posta</label>
          <input type="email" name="eposta" class="form-control kayit-input"
            placeholder="ornek@mail.com"
            value="<?php echo htmlspecialchars($_POST['eposta'] ?? ''); ?>"
            required>
        </div>
        <button type="submit" name="sifre_sifirla" class="btn btn-outline-light btn-lg w-100">
          Sıfırlama Bağlantısı Gönder
        </button>
      </form>

      <p class="kayit-login mt-3 text-center">
        Aklına mı geldi? <a href="giris.php">Giriş yap</a>
      </p>
    </div>

    <?php else: ?>
    <div class="kayit-card text-center" id="adim-2">
      <div class="mb-4">
        <span style="font-size: 2.8rem;">✉️</span>
      </div>
      <h2 class="kayit-title">Bağlantı Gönderildi!</h2>
      <p class="kayit-sub">
        <strong><?php echo htmlspecialchars($eposta_gonderilen); ?></strong> adresine
        sıfırlama bağlantısı gönderdik. Gelen kutunu kontrol et.
      </p>
      <p style="font-size: 0.8rem; color: rgba(255,255,255,0.45); margin-top: 12px;">
        E-posta gelmediyse spam klasörünü kontrol et veya birkaç dakika bekle.
      </p>

      <form method="POST" action="sifreyenileme.php">
        <input type="hidden" name="eposta" value="<?php echo htmlspecialchars($eposta_gonderilen); ?>">
        <button type="submit" name="sifre_sifirla" class="btn btn-outline-light btn-lg w-100 mt-3">
          Tekrar Gönder
        </button>
      </form>

      <p class="kayit-login mt-3">
        <a href="giris.php">← Giriş sayfasına dön</a>
      </p>
    </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>