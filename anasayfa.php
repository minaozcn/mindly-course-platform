<?php

session_start();
require_once 'db.php';

if (!isset($_SESSION['kullanici_id'])) {
    header('Location: giris.php');
    exit;
}

$stmt = $pdo->prepare("SELECT ad_soyad, eposta, kayit_tarihi FROM kullanicilar WHERE id = ?");
$stmt->execute([$_SESSION['kullanici_id']]);
$kullanici = $stmt->fetch();

if (!$kullanici) {
    session_destroy();
    header('Location: giris.php');
    exit;
}

$kullanici_id = $_SESSION['kullanici_id'];
$ad_soyad     = $kullanici['ad_soyad'];
$ilk_harf     = mb_strtoupper(mb_substr($ad_soyad, 0, 1, 'UTF-8'), 'UTF-8');
$kayit_tarihi = date('d.m.Y', strtotime($kullanici['kayit_tarihi']));
$ilk_isim     = htmlspecialchars(explode(' ', $ad_soyad)[0]);

// ── Satın alınan kurs sayısı ──
$stmtKurs = $pdo->prepare("SELECT COUNT(*) FROM satinalma WHERE kullanici_id = ?");
$stmtKurs->execute([$kullanici_id]);
$aktif_kurs_sayisi = (int)$stmtKurs->fetchColumn();

// ── Tamamlanan ders (ilerleme tablosu varsa; yoksa 0) ──
$tamamlanan_ders = 0;
try {
    $stmtDers = $pdo->prepare("SELECT COALESCE(SUM(tamamlanan_ders), 0) FROM ilerleme WHERE kullanici_id = ? AND MONTH(guncelleme_tarihi) = MONTH(NOW()) AND YEAR(guncelleme_tarihi) = YEAR(NOW())");
    $stmtDers->execute([$kullanici_id]);
    $tamamlanan_ders = (int)$stmtDers->fetchColumn();
} catch (Exception $e) { $tamamlanan_ders = 0; }

// ── Gün serisi (ilerleme tablosu varsa; yoksa 0) ──
$seri = 0;
try {
    $stmtSeri = $pdo->prepare("SELECT COUNT(DISTINCT DATE(guncelleme_tarihi)) FROM ilerleme WHERE kullanici_id = ? AND guncelleme_tarihi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmtSeri->execute([$kullanici_id]);
    $seri = (int)$stmtSeri->fetchColumn();
} catch (Exception $e) { $seri = 0; }

// ── Satın alınan kurslar (ilerleme tablosu olmadan) ──
$satin_alinan_kurslar = [];
try {
    $stmtSatinalma = $pdo->prepare("
        SELECT k.id, k.ikon, k.kategori, k.baslik, k.ders_sayisi, k.seviye,
               ROUND(s.ilerleme * k.ders_sayisi / 100) AS tamamlanan, s.ilerleme AS yuzde,
               s.satin_alma_tar
        FROM satinalma s
        JOIN kurslar k ON k.id = s.kurs_id
        WHERE s.kullanici_id = ?
        ORDER BY s.satin_alma_tar DESC
        LIMIT 3
    ");
    $stmtSatinalma->execute([$kullanici_id]);
    $satin_alinan_kurslar = $stmtSatinalma->fetchAll();
} catch (Exception $e) {
    $satin_alinan_kurslar = [];
}

// ── En son devam edilen kurs ──
$devam_kurs = !empty($satin_alinan_kurslar) ? $satin_alinan_kurslar[0] : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ana Sayfa – Mindly</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="logo-wrap">
    <a href="anasayfa.php" class="logo">MINDLY</a>
    <p class="slogan">learn · grow · shine</p>
  </div>
  <nav class="nav-section">
    <p class="nav-label">Menü</p>
    <a href="anasayfa.php"    class="nav-item active"><span class="icon">🏠</span> Ana Sayfa</a>
    <a href="kurslar.php"     class="nav-item"><span class="icon">📚</span> Kurslarım</a>
    <a href="tum_kurslar.php" class="nav-item"><span class="icon">🔍</span> Keşfet</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-pill">
      <div class="avatar"><?= $ilk_harf ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($ad_soyad) ?></div>
        <div class="user-since">Üye: <?= $kayit_tarihi ?></div>
      </div>
    </div>
    <a href="cikis.php" style="text-decoration:none">
      <button class="btn-logout">🚪 Çıkış Yap</button>
    </a>
  </div>
</aside>

<!-- ── MAIN ── -->
<main class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <div class="greeting-label">Hoş geldin</div>
      <div class="greeting">Merhaba, <em><?= $ilk_isim ?></em> 👋</div>
      <div class="sub-greeting">Bugün ne öğrenmek istiyorsun?</div>
    </div>
    <div class="date-badge">📅 <?= date('d F Y', time()) ?></div>
  </div>

  <!-- İSTATİSTİKLER -->
  <div class="stats-row">
    <div class="stat-card blue">
      <div class="stat-label">Aktif Kurs</div>
      <div class="stat-value"><?= $aktif_kurs_sayisi ?></div>
      <div class="stat-sub"><?= $aktif_kurs_sayisi > 0 ? 'Devam ediyor' : 'Henüz kurs yok' ?></div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Tamamlanan Ders</div>
      <div class="stat-value"><?= $tamamlanan_ders ?></div>
      <div class="stat-sub">Bu ay</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Seri</div>
      <div class="stat-value"><?= $seri > 0 ? $seri . ' 🔥' : '—' ?></div>
      <div class="stat-sub"><?= $seri > 0 ? 'Son 30 günde aktif gün' : 'Derse başla!' ?></div>
    </div>
  </div>

  <!-- DEVAM ET / BOŞ DURUM -->
  <?php if ($devam_kurs): ?>
    <div class="continue-card">
      <div class="continue-icon"><?= $devam_kurs['ikon'] ?></div>
      <div>
        <div class="continue-badge">
          <span class="live-dot"></span> Kaldığın Yerden
        </div>
        <div class="continue-title"><?= htmlspecialchars($devam_kurs['baslik']) ?></div>
        <div class="continue-sub"><?= htmlspecialchars($devam_kurs['kategori']) ?> · <?= htmlspecialchars($devam_kurs['seviye']) ?></div>
      </div>
      <a href="kurslar.php" class="btn-continue">Devam Et →</a>
    </div>
  <?php else: ?>
    <div class="empty-state" style="animation: fadeUp .5s ease .28s both;">
      <span class="empty-icon">🎯</span>
      <p>Henüz hiç kurs almadın. İlk adımı atmaya hazır mısın?</p>
      <a href="tum_kurslar.php" class="btn-kesfet">Kurslara Göz At →</a>
    </div>
  <?php endif; ?>

  <!-- KURSLARIM -->
  <div class="section-head">
    <div class="section-title">
      <span class="section-line"></span> Kurslarım
    </div>
    <a href="kurslar.php" class="see-all">Tümünü Gör →</a>
  </div>

  <?php if (empty($satin_alinan_kurslar)): ?>
    <div style="background: var(--surface); border: .5px solid var(--border); border-radius: 18px; padding: 32px; text-align: center; margin-bottom: 32px; animation: fadeUp .5s ease .35s both;">
      <p style="color: var(--muted); font-size: .88rem; font-weight: 300; margin-bottom: 16px;">
        Kursların burada görünecek. Hadi bir tane seç!
      </p>
      <a href="tum_kurslar.php" class="btn-kesfet">Keşfet →</a>
    </div>
  <?php else: ?>
    <div class="courses-grid">
      <?php foreach ($satin_alinan_kurslar as $k):
        $yuzde = $k['ders_sayisi'] > 0
          ? min(100, round($k['tamamlanan'] / $k['ders_sayisi'] * 100))
          : 0;
      ?>
        <div class="course-card"
          onmousemove="this.style.setProperty('--mx',((event.clientX-this.getBoundingClientRect().left)/this.getBoundingClientRect().width*100)+'%');this.style.setProperty('--my',((event.clientY-this.getBoundingClientRect().top)/this.getBoundingClientRect().height*100)+'%')">
          <span class="course-emoji"><?= htmlspecialchars($k['ikon']) ?></span>
          <div class="course-cat"><?= htmlspecialchars($k['kategori']) ?></div>
          <div class="course-name"><?= htmlspecialchars($k['baslik']) ?></div>
          <div class="course-meta"><?= (int)$k['ders_sayisi'] ?> ders · <?= htmlspecialchars($k['seviye']) ?></div>
          <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $yuzde ?>%"></div>
          </div>
          <div class="progress-label">
            <span>İlerleme</span>
            <strong><?= $yuzde ?>%</strong>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<script>
  document.querySelectorAll('.stat-card, .course-card').forEach(card => {
    card.addEventListener('mousemove', e => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', ((e.clientX - r.left) / r.width  * 100) + '%');
      card.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
    });
  });
</script>

</body>
</html>