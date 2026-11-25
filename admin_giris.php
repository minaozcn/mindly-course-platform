<?php
session_start();

require_once __DIR__ . '/config.php';

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici = trim($_POST['kullanici'] ?? '');
    $sifre     = trim($_POST['sifre'] ?? '');

    if ($kullanici === ADMIN_USER && $sifre === ADMIN_PASS) {
        $_SESSION['admin_giris'] = true;
        header('Location: admin_panel.php');
        exit;
    } else {
        $hata = 'Kullanıcı adı veya şifre hatalı.';
    }
}

if (!empty($_SESSION['admin_giris'])) {
    header('Location: admin_panel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Girişi – Mindly</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="giris-page">

<div class="card">
  <div class="brand">
    <div class="brand-logo">MINDLY</div>
    <div class="brand-sub">learn · grow · shine</div>
  </div>

  <div style="text-align:center">
    <span class="badge">Admin Paneli</span>
  </div>

  <?php if ($hata): ?>
    <div class="error-msg">⚠️ <?= htmlspecialchars($hata) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label for="kullanici">Kullanıcı Adı</label>
      <input type="text" id="kullanici" name="kullanici"
             placeholder="admin" autocomplete="off" required>
    </div>
    <div class="field">
      <label for="sifre">Şifre</label>
      <input type="password" id="sifre" name="sifre"
             placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-giris">Giriş Yap →</button>
  </form>

  <a href="anasayfa.php" class="back-link">← Ana Sayfaya Dön</a>
</div>

</body>
</html>