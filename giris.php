<?php


session_start();
require_once 'db.php';

$hata    = '';
$basarili = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['giris'])) {
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre  = $_POST['sifre'] ?? '';

    if (empty($eposta) || empty($sifre)) {
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi girin.';
    } else {
        $stmt = $pdo->prepare("SELECT id, ad_soyad, sifre_hash FROM kullanicilar WHERE eposta = ?");
        $stmt->execute([$eposta]);
        $kullanici = $stmt->fetch();

        if ($kullanici && password_verify($sifre, $kullanici['sifre_hash'])) {
            // Oturum aç
            session_regenerate_id(true); // Oturum sabitleme saldırısına karşı
            $_SESSION['kullanici_id']      = $kullanici['id'];
            $_SESSION['kullanici_ad']      = $kullanici['ad_soyad'];

            $basarili = true;
            // header('Location: anasayfa.php');
            // exit;
        } else {
            // Güvenlik: hangi alanın yanlış olduğunu belirtme
            $hata = 'E-posta veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş Yap – Mindly</title>
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

      <h2 class="kayit-title text-center">Tekrar Hoşgeldin 👋</h2>
      <p class="kayit-sub text-center">Hesabına giriş yap, kaldığın yerden devam et.</p>

      <?php if ($basarili): ?>
    <div class="alert alert-success" role="alert">
        Hoş geldin, <strong><?php echo htmlspecialchars($_SESSION['kullanici_ad']); ?></strong>!
        Yönlendiriliyorsunuz...
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "anasayfa.php";
        }, 2000); // 2 saniye sonra yönlendirir
    </script>
<?php else: ?>

        <?php if (!empty($hata)): ?>
          <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($hata); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="giris.php" novalidate>
          <div class="mb-3">
            <label class="form-label">E-posta</label>
            <input type="email" name="eposta" class="form-control kayit-input"
              placeholder="ornek@mail.com"
              value="<?php echo htmlspecialchars($_POST['eposta'] ?? ''); ?>"
              required>
          </div>

          <div class="mb-4">
            <label class="form-label">Şifre</label>
            <input type="password" name="sifre" class="form-control kayit-input"
              placeholder="Şifren" required>
            <div class="text-end mt-1">
              <a href="sifreyenileme.php" style="font-size: 0.8rem; color: rgba(255,255,255,0.7);">Şifremi unuttum</a>
            </div>
          </div>

          <button type="submit" name="giris" class="btn btn-outline-light btn-lg w-100">Giriş Yap</button>
        </form>

        <p class="kayit-login mt-3 text-center">
          Hesabın yok mu? <a href="kayit.php">Kayıt ol</a>
        </p>

      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
