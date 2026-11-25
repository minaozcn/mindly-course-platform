<?php

session_start();
require_once 'db.php';

if (!isset($_SESSION['kullanici_id'])) {
    header('Location: giris.php');
    exit;
}

$kullanici_id = $_SESSION['kullanici_id'];
$flash        = '';
$flash_tip    = '';  // 'basari' | 'hata'


$izin_seviye = ['', 'Başlangıç', 'Orta', 'İleri'];
$izin_sira   = [
    'ogrenci_say DESC' => 'En Popüler',
    'olusturma DESC'   => 'En Yeni',
    'baslik ASC'       => 'A–Z',
    'sure_saat ASC'    => 'En Kısa',
    'fiyat ASC'        => 'Fiyat: Düşükten Yükseğe',
    'fiyat DESC'       => 'Fiyat: Yüksekten Düşüğe',
];

$seviye_filtre = in_array($_GET['seviye'] ?? '', $izin_seviye) ? ($_GET['seviye'] ?? '') : '';
$sira          = array_key_exists($_GET['sira'] ?? '', $izin_sira) ? $_GET['sira'] : 'ogrenci_say DESC';
$arama         = trim($_GET['ara'] ?? '');
$limit         = 8;
$sayfa         = max(1, (int)($_GET['sayfa'] ?? 1));
$offset        = ($sayfa - 1) * $limit;

$where   = "WHERE k.aktif = 1";
$params  = [];

if ($seviye_filtre) {
    $where  .= " AND k.seviye = ?";
    $params[] = $seviye_filtre;
}

if ($arama !== '') {
    $where  .= " AND (k.baslik LIKE ? OR k.kategori LIKE ? OR k.aciklama LIKE ?)";
    $like    = '%' . $arama . '%';
    $params  = array_merge($params, [$like, $like, $like]);
}

$sql_toplam = "SELECT COUNT(*) FROM kurslar k $where";
$stmtT = $pdo->prepare($sql_toplam);
$stmtT->execute($params);
$toplam       = $stmtT->fetchColumn();
$toplam_sayfa = ceil($toplam / $limit);

$sql = "SELECT k.*,
               (SELECT id FROM satinalma
                WHERE kullanici_id = ? AND kurs_id = k.id
                LIMIT 1) AS satinalindi
        FROM kurslar k
        $where
        ORDER BY $sira
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$kullanici_id], $params, [$limit, $offset]));
$kurslar = $stmt->fetchAll();

$stmtU = $pdo->prepare("SELECT ad_soyad, kayit_tarihi FROM kullanicilar WHERE id = ?");
$stmtU->execute([$kullanici_id]);
$kullanici    = $stmtU->fetch();
$ad_soyad     = $kullanici['ad_soyad'];
$ilk_harf     = mb_strtoupper(mb_substr($ad_soyad, 0, 1, 'UTF-8'), 'UTF-8');
$kayit_tarihi = date('d.m.Y', strtotime($kullanici['kayit_tarihi']));

$seviye_class = ['Başlangıç' => 'basla', 'Orta' => 'orta', 'İleri' => 'ileri'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keşfet – Mindly</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
/* ── ARAMA KUTUSU ── */
.search-wrap {
  position: relative;
  flex: 1;
  min-width: 220px;
  max-width: 420px;
}
.search-icon {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  font-size: .9rem;
  pointer-events: none;
}
.search-input {
  width: 100%;
  padding: 9px 38px 9px 36px;
  background: rgba(255,255,255,0.08);
  border: .5px solid rgba(255,255,255,0.18);
  border-radius: 99px;
  color: #fff;
  font-family: 'DM Sans', sans-serif;
  font-size: .85rem;
  outline: none;
  transition: border-color .2s, background .2s;
}
.search-input::placeholder { color: rgba(255,255,255,0.35); }
.search-input:focus {
  border-color: rgba(240,196,212,0.55);
  background: rgba(255,255,255,0.12);
}
.search-clear {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(255,255,255,0.4);
  text-decoration: none;
  font-size: .8rem;
  line-height: 1;
  transition: color .15s;
}
.search-clear:hover { color: #fff; }
/* boş durum */
.empty-search {
  text-align: center;
  padding: 60px 20px;
  color: rgba(255,255,255,0.7);
  animation: fadeUp .35s ease both;
}
/* filter-bar flex ayarı */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
</style>
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
    <a href="anasayfa.php"    class="nav-item"><span class="icon">🏠</span> Ana Sayfa</a>
    <a href="kurslar.php"     class="nav-item"><span class="icon">📚</span> Kurslarım</a>
    <a href="tum_kurslar.php" class="nav-item active"><span class="icon">🔍</span> Keşfet</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-pill">
      <div class="avatar"><?= htmlspecialchars($ilk_harf) ?></div>
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

  <div class="page-header">
    <div>
      <div class="page-eyebrow">Tüm Kurslar</div>
      <div class="page-title">Keşfet <em>&amp; Büyü</em></div>
      <div class="page-sub"><?= $toplam ?> kurs seni bekliyor. Birini seç, gelişimini başlat.</div>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?= $flash_tip ?>"><?= $flash ?></div>
  <?php endif; ?>

  <!-- FİLTRE ÇUBUĞU -->
  <form method="GET" action="tum_kurslar.php" id="filter-form">
    <div class="filter-bar">

      <!-- ARAMA KUTUSU -->
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          name="ara"
          class="search-input"
          placeholder="Kurs, kategori veya anahtar kelime ara…"
          value="<?= htmlspecialchars($arama) ?>"
          autocomplete="off"
        >
        <?php if ($arama !== ''): ?>
          <a href="?seviye=<?= urlencode($seviye_filtre) ?>&sira=<?= urlencode($sira) ?>" class="search-clear" title="Aramayı temizle">✕</a>
        <?php endif; ?>
      </div>

      <span class="filter-label">Seviye:</span>
      <select name="seviye" class="filter-select" onchange="this.form.submit()">
        <option value=""          <?= $seviye_filtre===''          ?'selected':'' ?>>Tümü</option>
        <option value="Başlangıç" <?= $seviye_filtre==='Başlangıç' ?'selected':'' ?>>Başlangıç</option>
        <option value="Orta"      <?= $seviye_filtre==='Orta'      ?'selected':'' ?>>Orta</option>
        <option value="İleri"     <?= $seviye_filtre==='İleri'     ?'selected':'' ?>>İleri</option>
      </select>

      <span class="filter-label">Sırala:</span>
      <select name="sira" class="filter-select" onchange="this.form.submit()">
        <?php foreach ($izin_sira as $val => $lbl): ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $sira===$val?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>

      <span class="filter-count">
        <?php if ($arama !== ''): ?>
          <strong style="color:var(--blush)">"<?= htmlspecialchars($arama) ?>"</strong> için
        <?php endif; ?>
        <?= $toplam ?> sonuç · Sayfa <?= $sayfa ?>/<?= $toplam_sayfa ?>
      </span>
    </div>
  </form>

  <?php if ($toplam === 0 && $arama !== ''): ?>
  <div class="empty-search">
    <div style="font-size:2.5rem">🔍</div>
    <div style="font-size:1.05rem;font-weight:600;margin-top:10px">Sonuç bulunamadı</div>
    <div style="font-size:.85rem;color:var(--muted);margin-top:6px">
      «<?= htmlspecialchars($arama) ?>» için eşleşen kurs yok. Farklı bir anahtar kelime deneyin.
    </div>
    <a href="tum_kurslar.php" style="display:inline-block;margin-top:14px;color:var(--blush);font-size:.85rem;">← Tüm kurslara dön</a>
  </div>
  <?php endif; ?>

  <!-- KURSLAR -->
  <div class="courses-grid">
    <?php foreach ($kurslar as $k): ?>
    <div class="course-card">

      <!-- Üst: emoji + seviye etiketi -->
      <div class="card-top">
        <span class="course-emoji"><?= $k['ikon'] ?></span>
        <span class="level-tag <?= $seviye_class[$k['seviye']] ?? 'basla' ?>">
          <?= htmlspecialchars($k['seviye']) ?>
        </span>
      </div>

      <!-- İçerik -->
      <div class="course-cat"><?= htmlspecialchars($k['kategori']) ?></div>
      <div class="course-name"><?= htmlspecialchars($k['baslik']) ?></div>
      <div class="course-desc"><?= htmlspecialchars($k['aciklama']) ?></div>

      <!-- Meta: ders / süre / öğrenci -->
      <div class="course-meta-row">
        <span class="meta-chip">📖 <?= $k['ders_sayisi'] ?> ders</span>
        <span class="meta-chip">👥 <?= number_format($k['ogrenci_say'], 0, ',', '.') ?></span>
      </div>

      <!-- Alt: fiyat + buton -->
      <div class="course-footer">
        <?php if ($k['satinalindi']): ?>
          <!-- Zaten kayıtlı: fiyat gösterme, yeşil buton -->
          <span class="course-price price-free">✓ Kayıtlısın</span>
        <?php else: ?>
          <!-- Fiyat -->
          <div class="course-price">
            <sup>₺</sup><?= number_format((float)$k['fiyat'], 2, ',', '.') ?>
          </div>
          <!-- Satın Al -->
          <a href="satin_al.php?kurs_id=<?= (int)$k['id'] ?>" class="btn-satin-al">
            🛒 Satın Al
          </a>
        <?php endif; ?>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

  <!-- SAYFALAMA -->
  <?php if ($toplam_sayfa > 1): ?>
  <div class="pagination">
    <?php if ($sayfa > 1): ?>
      <a href="?seviye=<?= urlencode($seviye_filtre) ?>&sira=<?= urlencode($sira) ?>&ara=<?= urlencode($arama) ?>&sayfa=<?= $sayfa-1 ?>">‹ Önceki</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
      <?php if ($i === $sayfa): ?>
        <span><?= $i ?></span>
      <?php else: ?>
        <a href="?seviye=<?= urlencode($seviye_filtre) ?>&sira=<?= urlencode($sira) ?>&ara=<?= urlencode($arama) ?>&sayfa=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($sayfa < $toplam_sayfa): ?>
      <a href="?seviye=<?= urlencode($seviye_filtre) ?>&sira=<?= urlencode($sira) ?>&ara=<?= urlencode($arama) ?>&sayfa=<?= $sayfa+1 ?>">Sonraki ›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</main>

<script>
  document.querySelectorAll('.course-card').forEach((el, i) => {
    el.style.animationDelay = (0.06 + i * 0.06) + 's';
    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect();
      el.style.setProperty('--mx', ((e.clientX - r.left) / r.width  * 100) + '%');
      el.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
    });
  });

  // Arama kutusunda Enter → formu gönder
  const searchInput = document.querySelector('.search-input');
  if (searchInput) {
    searchInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('filter-form').submit();
      }
    });
  }
</script>
</body>
</html>