<?php
// yenisifre.php – Mindly Yeni Şifre Belirleme Sayfası

require_once 'db.php';

$hata      = '';
$basarili  = false;
$token     = trim($_GET['token'] ?? '');

// --- Token doğrula ---
$token_gecerli = false;
$token_eposta  = '';

if (!empty($token)) {
    $stmt = $pdo->prepare(
        "SELECT eposta FROM sifre_tokenler
         WHERE token = ?
           AND kullanildi = 0
           AND son_kullanim > NOW()"
    );
    $stmt->execute([$token]);
    $satir = $stmt->fetch();

    if ($satir) {
        $token_gecerli = true;
        $token_eposta  = $satir['eposta'];
    }
}

// --- Şifre değiştirme formu gönderildi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_sifre_kaydet'])) {
    $post_token  = trim($_POST['token'] ?? '');
    $yeni_sifre  = $_POST['yeni_sifre'] ?? '';
    $tekrar      = $_POST['yeni_sifre_tekrar'] ?? '';

    if (empty($yeni_sifre) || empty($tekrar)) {
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (strlen($yeni_sifre) < 8) {
        $hata = 'Şifre en az 8 karakter olmalıdır.';
    } elseif ($yeni_sifre !== $tekrar) {
        $hata = 'Şifreler eşleşmiyor.';
    } else {
        // Token'ı tekrar doğrula (POST üzerinden)
        $stmt = $pdo->prepare(
            "SELECT eposta FROM sifre_tokenler
             WHERE token = ?
               AND kullanildi = 0
               AND son_kullanim > NOW()"
        );
        $stmt->execute([$post_token]);
        $satir = $stmt->fetch();

        if (!$satir) {
            $hata = 'Bağlantı geçersiz veya süresi dolmuş. Lütfen yeniden talep edin.';
        } else {
            $eposta = $satir['eposta'];

            // Şifreyi güncelle
            $yeni_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE eposta = ?");
            $stmt->execute([$yeni_hash, $eposta]);

            // Token'ı kullanıldı olarak işaretle
            $stmt = $pdo->prepare("UPDATE sifre_tokenler SET kullanildi = 1 WHERE token = ?");
            $stmt->execute([$post_token]);

            $basarili = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yeni Şifre Belirle – Mindly</title>
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

      <?php if ($basarili): ?>
        <!-- BAŞARI -->
        <div class="text-center mb-4">
          <span style="font-size: 2.8rem;">✅</span>
        </div>
        <h2 class="kayit-title text-center">Şifren Güncellendi!</h2>
        <p class="kayit-sub text-center">
          Yeni şifrenle artık giriş yapabilirsin.
        </p>
        <a href="giris.php" class="btn btn-outline-light btn-lg w-100 mt-2">Giriş Yap</a>

      <?php elseif (!$token_gecerli): ?>
        <!-- GEÇERSİZ / SÜRESİ DOLMUŞ TOKEN -->
        <div class="text-center mb-4">
          <span style="font-size: 2.8rem;">⚠️</span>
        </div>
        <h2 class="kayit-title text-center">Bağlantı Geçersiz</h2>
        <p class="kayit-sub text-center">
          Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş (1 saat).
        </p>
        <a href="sifreyenileme.php" class="btn btn-outline-light btn-lg w-100 mt-2">
          Yeni Bağlantı İste
        </a>

      <?php else: ?>
        <!-- YENİ ŞİFRE FORMU -->
        <div class="text-center mb-4">
          <span style="font-size: 2.5rem;">🔑</span>
        </div>
        <h2 class="kayit-title text-center">Yeni Şifre Belirle</h2>
        <p class="kayit-sub text-center">
          <strong><?php echo htmlspecialchars($token_eposta); ?></strong> hesabı için
          yeni şifreni gir.
        </p>

        <?php if (!empty($hata)): ?>
          <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($hata); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="yenisifre.php" novalidate>
          <!-- Token'ı gizli alan olarak taşı -->
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

          <div class="mb-3">
            <label class="form-label">Yeni Şifre</label>
            <input type="password" name="yeni_sifre" class="form-control kayit-input"
              placeholder="En az 8 karakter" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Şifre Tekrar</label>
            <input type="password" name="yeni_sifre_tekrar" class="form-control kayit-input"
              placeholder="Şifreni tekrar gir" required>
          </div>

          <button type="submit" name="yeni_sifre_kaydet" class="btn btn-outline-light btn-lg w-100">
            Şifremi Güncelle
          </button>
        </form>

      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
