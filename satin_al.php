<?php


session_start();
require_once 'db.php';

if (!isset($_SESSION['kullanici_id'])) {
    header('Location: giris.php'); exit;
}

$kullanici_id = $_SESSION['kullanici_id'];
$hata         = '';
$basarili     = false;


$kurs_id = (int)($_GET['kurs_id'] ?? $_POST['kurs_id'] ?? 0);
if ($kurs_id <= 0) { header('Location: tum_kurslar.php'); exit; }

// Kurs bilgisi veritabanından (admin panelinden eklenen kurslar dahil)
$stmtK = $pdo->prepare("SELECT * FROM kurslar WHERE id = ? AND aktif = 1");
$stmtK->execute([$kurs_id]);
$kurs = $stmtK->fetch();
if (!$kurs) { header('Location: tum_kurslar.php'); exit; }

// Zaten satın alındıysa
$stmtC = $pdo->prepare("SELECT id FROM satinalma WHERE kullanici_id = ? AND kurs_id = ?");
$stmtC->execute([$kullanici_id, $kurs_id]);
if ($stmtC->fetch()) { header('Location: kurslar.php'); exit; }

// Kullanıcı bilgileri
$stmtU = $pdo->prepare("SELECT ad_soyad, eposta, kayit_tarihi FROM kullanicilar WHERE id = ?");
$stmtU->execute([$kullanici_id]);
$kullanici    = $stmtU->fetch();
$ad_soyad     = $kullanici['ad_soyad'];
$ilk_harf     = mb_strtoupper(mb_substr($ad_soyad, 0, 1, 'UTF-8'), 'UTF-8');
$kayit_tarihi = date('d.m.Y', strtotime($kullanici['kayit_tarihi']));

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['odeme_yap'])) {
    $kart_ad  = trim($_POST['kart_ad']  ?? '');
    $kart_no  = preg_replace('/\s+/', '', $_POST['kart_no'] ?? '');
    $kart_ay  = trim($_POST['kart_ay']  ?? '');
    $kart_yil = trim($_POST['kart_yil'] ?? '');
    $kart_cvv = trim($_POST['kart_cvv'] ?? '');

    if (empty($kart_ad) || empty($kart_no) || empty($kart_ay) || empty($kart_yil) || empty($kart_cvv)) {
        $hata = 'Lütfen tüm kart bilgilerini doldurun.';
    } elseif (!preg_match('/^\d{16}$/', $kart_no)) {
        $hata = 'Kart numarası 16 haneli olmalıdır.';
    } elseif (!preg_match('/^\d{3,4}$/', $kart_cvv)) {
        $hata = 'CVV 3 veya 4 haneli olmalıdır.';
    } else {
        // satinalma tablosu: id, kullanici_id, kurs_id, ilerleme, durum, satin_alma_tar
        $stmtI = $pdo->prepare(
            "INSERT INTO satinalma (kullanici_id, kurs_id, ilerleme, durum, satin_alma_tar)
             VALUES (?, ?, 0, 'devam', NOW())"
        );
        $stmtI->execute([$kullanici_id, $kurs_id]);
        $basarili = true;
    }
}

$seviye_class = ['Başlangıç' => 'basla', 'Orta' => 'orta', 'İleri' => 'ileri'];
$fiyat_goster = number_format($kurs['fiyat'], 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Satın Al – <?= htmlspecialchars($kurs['baslik']) ?> | Mindly</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg-grad:   linear-gradient(135deg,#1a0a2e 0%,#6b2d8b 40%,#c45fa0 70%,#f0c4d4 100%);
    --surface:   rgba(255,255,255,0.08);
    --surface2:  rgba(255,255,255,0.13);
    --border:    rgba(255,255,255,0.15);
    --border2:   rgba(240,196,212,0.35);
    --blush:     #f0c4d4;
    --green:     #34d399;
    --amber:     #fbbf24;
    --text:      #fff;
    --muted:     rgba(255,255,255,0.45);
    --font-head: 'Playfair Display', serif;
    --font-syne: 'Syne', sans-serif;
    --font-body: 'DM Sans', sans-serif;
    --sidebar-w: 240px;
  }

  html { scroll-behavior: smooth; }
  body {
    font-family: var(--font-body);
    background: var(--bg-grad);
    background-attachment: fixed;
    min-height: 100vh;
    color: var(--text);
  }
  ::-webkit-scrollbar { width: 5px; }
  ::-webkit-scrollbar-thumb { background: rgba(240,196,212,0.25); border-radius: 99px; }

  /* ── SIDEBAR ── */
  .sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w); height: 100vh;
    background: rgba(26,10,46,0.55);
    border-right: 0.5px solid var(--border);
    backdrop-filter: blur(18px);
    display: flex; flex-direction: column;
    padding: 30px 20px; z-index: 200;
  }
  .logo { font-family: var(--font-head); font-size: 1.45rem; font-weight: 700; letter-spacing: 5px; color: var(--text); text-decoration: none; display: block; }
  .slogan { font-size: 9px; letter-spacing: 3px; color: rgba(240,196,212,0.65); font-weight: 300; margin-top: 3px; }
  .nav-section { margin-top: 38px; flex: 1; }
  .nav-label { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); padding-left: 12px; margin-bottom: 10px; }
  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 12px;
    color: var(--muted); text-decoration: none; font-size: .87rem;
    border: 0.5px solid transparent; margin-bottom: 4px;
    transition: background .2s, color .2s;
  }
  .nav-item:hover { background: var(--surface); color: var(--text); border-color: var(--border); }
  .nav-item.active { background: rgba(240,196,212,0.15); color: var(--blush); border-color: var(--border2); }
  .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
  .sidebar-bottom { margin-top: auto; }
  .user-pill {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; background: var(--surface);
    border: 0.5px solid var(--border); border-radius: 14px; margin-bottom: 10px;
  }
  .avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #c45fa0, #6b2d8b);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-syne); font-weight: 700; font-size: 1rem;
    flex-shrink: 0; border: 1.5px solid rgba(240,196,212,0.5);
  }
  .user-name { font-size: .82rem; font-weight: 600; }
  .user-since { font-size: .68rem; color: var(--muted); margin-top: 1px; }
  .btn-logout {
    width: 100%; padding: 9px; background: transparent;
    border: 0.5px solid var(--border); border-radius: 12px;
    color: var(--muted); font-family: var(--font-body); font-size: .82rem;
    cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
  }
  .btn-logout:hover { background: rgba(255,80,80,0.12); color: #ff8080; border-color: rgba(255,80,80,0.35); }

  /* ── MAIN ── */
  .main { margin-left: var(--sidebar-w); padding: 40px 44px; min-height: 100vh; }

  /* breadcrumb */
  .breadcrumb { display: flex; align-items: center; gap: 7px; font-size: .75rem; color: var(--muted); margin-bottom: 24px; }
  .breadcrumb a { color: var(--muted); text-decoration: none; }
  .breadcrumb a:hover { color: var(--blush); }
  .breadcrumb span { color: rgba(255,255,255,0.25); }

  /* page header */
  .page-header { margin-bottom: 28px; }
  .page-eyebrow { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: rgba(240,196,212,0.65); font-weight: 300; margin-bottom: 8px; }
  .page-title { font-family: var(--font-head); font-size: 2.2rem; font-weight: 700; line-height: 1.15; }
  .page-title em { font-style: italic; color: var(--blush); }

  /* ── CHECKOUT GRID ── */
  .checkout-grid { display: grid; grid-template-columns: 1fr 420px; gap: 20px; align-items: start; }

  /* ── KURS KARTI (sol) — tum_kurslar.php kart stili ── */
  .panel {
    background: rgba(255,255,255,0.07);
    border: 0.5px solid var(--border);
    border-radius: 20px;
    backdrop-filter: blur(4px);
    padding: 26px;
  }

  .kurs-hero { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
  .kurs-ikon { font-size: 2.1rem; }
  .kurs-kategori { font-size: 9px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--blush); font-weight: 500; margin-bottom: 5px; }
  .kurs-baslik { font-family: var(--font-head); font-size: 1.2rem; font-weight: 700; line-height: 1.35; margin-bottom: 8px; }

  .level-tag { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; font-weight: 600; padding: 3px 10px; border-radius: 99px; border: 0.5px solid; }
  .level-tag.basla { color: var(--green);  border-color: rgba(52,211,153,0.4);  background: rgba(52,211,153,0.1); }
  .level-tag.orta  { color: var(--amber);  border-color: rgba(251,191,36,0.4);  background: rgba(251,191,36,0.1); }
  .level-tag.ileri { color: #f87171;       border-color: rgba(248,113,113,0.4); background: rgba(248,113,113,0.1); }

  .kurs-aciklama { font-size: .78rem; color: var(--muted); line-height: 1.55; font-weight: 300; margin-bottom: 16px; }

  .kurs-meta { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
  .meta-chip { font-size: .72rem; color: var(--muted); display: flex; align-items: center; gap: 4px; }

  /* dahil listesi */
  .dahil-liste {
    background: rgba(255,255,255,0.05); border: 0.5px solid var(--border);
    border-radius: 12px; padding: 16px 18px; margin-bottom: 20px;
  }
  .dahil-baslik { font-size: 8px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
  .dahil-item { display: flex; align-items: center; gap: 8px; font-size: .78rem; color: var(--muted); padding: 5px 0; }
  .dahil-item + .dahil-item { border-top: 0.5px solid rgba(255,255,255,0.06); }
  .ck { color: var(--green); }

  /* fiyat kutusu */
  .fiyat-kutu {
    background: rgba(255,255,255,0.06); border: 0.5px solid var(--border2);
    border-radius: 14px; padding: 18px 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
  }
  .fiyat-label { font-size: .72rem; color: var(--muted); margin-bottom: 4px; }
  .fiyat-tutar {
    font-family: var(--font-syne); font-size: 1.9rem; font-weight: 800;
    color: var(--blush); letter-spacing: .5px;
  }
  .fiyat-alt { font-size: .68rem; color: var(--muted); margin-top: 3px; }
  .fiyat-notlar { font-size: .71rem; color: var(--muted); line-height: 2; text-align: right; }

  /* ── ÖDEME FORMU (sağ) ── */
  .odeme-panel {
    background: rgba(255,255,255,0.07);
    border: 0.5px solid var(--border);
    border-radius: 20px;
    backdrop-filter: blur(4px);
    position: sticky; top: 24px;
    overflow: hidden;
  }
  .odeme-header {
    background: rgba(255,255,255,0.06);
    border-bottom: 0.5px solid var(--border);
    padding: 16px 24px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .odeme-header-title { font-family: var(--font-syne); font-size: .95rem; font-weight: 700; }
  .ssl-pill {
    display: flex; align-items: center; gap: 5px;
    background: rgba(52,211,153,0.10); border: 0.5px solid rgba(52,211,153,0.25);
    border-radius: 99px; padding: 4px 10px; font-size: .67rem; color: var(--green);
  }
  .ssl-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--green); animation: blink 2s infinite; }
  .odeme-body { padding: 22px 24px; }

  /* hata mesajı */
  .hata-kutu {
    display: flex; align-items: center; gap: 8px;
    padding: 11px 14px; border-radius: 10px;
    background: rgba(248,113,113,0.10); border: 0.5px solid rgba(248,113,113,0.30);
    color: #f87171; font-size: .82rem; margin-bottom: 14px;
  }

  /* form */
  .form-bolum-baslik {
    font-size: 8px; letter-spacing: 3px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 12px;
    padding-bottom: 8px; border-bottom: 0.5px solid var(--border);
  }
  .fg { margin-bottom: 11px; }
  .fl { display: block; font-size: .72rem; color: var(--muted); margin-bottom: 5px; }
  .fc {
    width: 100%; padding: 10px 13px;
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: 10px; color: var(--text);
    font-family: var(--font-body); font-size: .85rem; outline: none;
    transition: border-color .18s, background .18s;
  }
  .fc:focus { border-color: var(--border2); background: var(--surface2); }
  .fc::placeholder { color: rgba(255,255,255,0.18); }
  .uc-kolon { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 9px; }

  /* özet */
  .ozet {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: 10px; padding: 13px 15px; margin: 14px 0;
  }
  .ozet-satir { display: flex; justify-content: space-between; font-size: .80rem; }
  .ozet-satir + .ozet-satir { margin-top: 8px; padding-top: 8px; border-top: 0.5px solid rgba(255,255,255,0.06); }
  .ozet-key { color: var(--muted); }
  .ozet-val { font-weight: 500; }
  .ozet-satir.toplam .ozet-key { color: var(--text); font-weight: 600; }
  .ozet-satir.toplam .ozet-val { font-family: var(--font-syne); font-weight: 700; color: var(--blush); font-size: .95rem; }

  /* ödeme butonu — tum_kurslar.php ile aynı */
  .btn-ode {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #5b21b6, #c45fa0);
    border: none; border-radius: 10px;
    color: #fff; font-family: var(--font-body); font-weight: 700; font-size: .88rem;
    cursor: pointer; letter-spacing: .3px;
    box-shadow: 0 6px 20px rgba(124,58,237,0.30);
    transition: opacity .18s, transform .15s;
  }
  .btn-ode:hover { opacity: .85; transform: translateY(-2px); }
  .btn-ode:active { transform: translateY(0); }

  .guven-notlar { display: flex; justify-content: center; gap: 14px; margin-top: 12px; flex-wrap: wrap; }
  .guven-item { font-size: .67rem; color: var(--muted); }

  .geri-link {
    display: block; text-align: center; margin-top: 12px;
    font-size: .75rem; color: var(--muted); text-decoration: none;
    transition: color .15s;
  }
  .geri-link:hover { color: var(--blush); }

  /* ── BAŞARI EKRANI ── */
  .basari-wrap { grid-column: 1/-1; display: flex; justify-content: center; }
  .basari-kart {
    max-width: 500px; width: 100%;
    background: rgba(255,255,255,0.07); border: 0.5px solid var(--border);
    border-radius: 20px; overflow: hidden; text-align: center;
    backdrop-filter: blur(4px);
  }
  .basari-ust {
    padding: 40px 32px 28px;
    background: rgba(255,255,255,0.05);
    border-bottom: 0.5px solid var(--border);
  }
  .basari-emoji { font-size: 3.2rem; display: block; margin-bottom: 14px; }
  .basari-baslik { font-family: var(--font-head); font-size: 1.8rem; font-weight: 700; margin-bottom: 8px; }
  .basari-metin { color: var(--muted); font-size: .87rem; line-height: 1.65; }
  .basari-kurs-adi { color: var(--blush); font-weight: 600; }
  .basari-alt { padding: 26px 32px; }
  .makbuz {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; text-align: left;
  }
  .makbuz-satir { display: flex; justify-content: space-between; font-size: .80rem; color: var(--muted); }
  .makbuz-satir + .makbuz-satir { margin-top: 7px; }
  .makbuz-val { color: var(--text); font-weight: 500; }
  .btn-kurslara {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 12px 26px;
    background: linear-gradient(135deg, #5b21b6, #c45fa0);
    border-radius: 10px; color: #fff; text-decoration: none;
    font-family: var(--font-body); font-weight: 700; font-size: .88rem;
    box-shadow: 0 6px 20px rgba(124,58,237,0.28);
    transition: opacity .18s, transform .15s;
  }
  .btn-kurslara:hover { opacity: .85; transform: translateY(-2px); }

  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
  @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

  @media(max-width:960px) {
    .checkout-grid { grid-template-columns: 1fr; }
    .odeme-panel { position: static; }
  }
  @media(max-width:768px) {
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 24px 18px; }
    .page-title { font-size: 1.8rem; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div>
    <a href="tum_kurslar.php" class="logo">MINDLY</a>
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

<!-- MAIN -->
<main class="main">

  <nav class="breadcrumb">
    <a href="tum_kurslar.php">Keşfet</a>
    <span>›</span>
    <a href="tum_kurslar.php"><?= htmlspecialchars($kurs['kategori']) ?></a>
    <span>›</span>
    Satın Al
  </nav>

  <div class="page-header">
    <div class="page-eyebrow">Ödeme Sayfası</div>
    <div class="page-title">Hemen Başla, <em>Büyü</em></div>
  </div>

  <div class="checkout-grid">

  <?php if ($basarili): ?>
  <!-- BAŞARI EKRANI -->
  <div class="basari-wrap">
    <div class="basari-kart">
      <div class="basari-ust">
        <span class="basari-emoji">🎉</span>
        <div class="basari-baslik">Ödeme Başarılı!</div>
        <p class="basari-metin">
          <span class="basari-kurs-adi">«<?= htmlspecialchars($kurs['baslik']) ?>»</span><br>
          kursuna başarıyla kaydoldun. Hemen öğrenmeye başlayabilirsin!
        </p>
      </div>
      <div class="basari-alt">
        <div class="makbuz">
          <div class="makbuz-satir"><span>Kurs</span><span class="makbuz-val"><?= htmlspecialchars($kurs['baslik']) ?></span></div>
          <div class="makbuz-satir"><span>Ödenen Tutar</span><span class="makbuz-val" style="color:var(--blush)"><?= $fiyat_goster ?> ₺</span></div>
          <div class="makbuz-satir"><span>Tarih</span><span class="makbuz-val"><?= date('d.m.Y H:i') ?></span></div>
          <div class="makbuz-satir"><span>Durum</span><span class="makbuz-val" style="color:var(--green)">✓ Onaylandı</span></div>
        </div>
        <a href="kurslar.php" class="btn-kurslara">📚 Kurslarıma Git →</a>
      </div>
    </div>
  </div>

  <?php else: ?>


  <div class="panel">

    <div class="kurs-hero">
      <span class="kurs-ikon"><?= $kurs['ikon'] ?></span>
      <div>
        <div class="kurs-kategori"><?= htmlspecialchars($kurs['kategori']) ?></div>
        <div class="kurs-baslik"><?= htmlspecialchars($kurs['baslik']) ?></div>
        <span class="level-tag <?= $seviye_class[$kurs['seviye']] ?? 'basla' ?>">
          <?= htmlspecialchars($kurs['seviye']) ?>
        </span>
      </div>
    </div>

    <p class="kurs-aciklama"><?= htmlspecialchars($kurs['aciklama']) ?></p>

    <div class="kurs-meta">
      <span class="meta-chip">📖 <?= $kurs['ders_sayisi'] ?> ders</span>
      <span class="meta-chip">⏱ ~<?= $kurs['sure_saat'] ?> saat</span>
      <span class="meta-chip">👥 <?= number_format($kurs['ogrenci_say'], 0, ',', '.') ?> öğrenci</span>
    </div>

    <div class="dahil-liste">
      <div class="dahil-baslik">Bu kursa dahil</div>
      <div class="dahil-item"><span class="ck">✓</span> Ömür boyu erişim</div>
      <div class="dahil-item"><span class="ck">✓</span> Tamamlama sertifikası</div>
      <div class="dahil-item"><span class="ck">✓</span> Mobil ve masaüstü uyumlu</div>
      <div class="dahil-item"><span class="ck">✓</span> Topluluk forumu erişimi</div>
      <div class="dahil-item"><span class="ck">✓</span> İndirilebilir kaynaklar</div>
    </div>

    <div class="fiyat-kutu">
      <div>
        <div class="fiyat-label">Toplam Tutar</div>
        <div class="fiyat-tutar">₺<?= $fiyat_goster ?></div>
        <div class="fiyat-alt">KDV dahil · Tek seferlik ödeme</div>
      </div>
      <div class="fiyat-notlar">
        🔒 Güvenli ödeme<br>
        💳 Tüm kartlar geçerli<br>
        🔄 7 gün iade garantisi
      </div>
    </div>
  </div>

 
  <div class="odeme-panel">
    <div class="odeme-header">
      <div class="odeme-header-title">💳 Ödeme Bilgileri</div>
      <div class="ssl-pill"><span class="ssl-dot"></span> SSL Korumalı</div>
    </div>
    <div class="odeme-body">

      <?php if (!empty($hata)): ?>
        <div class="hata-kutu">⚠️ <?= htmlspecialchars($hata) ?></div>
      <?php endif; ?>

      <form method="POST" action="satin_al.php?kurs_id=<?= $kurs_id ?>" novalidate>
        <input type="hidden" name="kurs_id" value="<?= $kurs_id ?>">

        <div class="form-bolum-baslik">Kart Bilgileri</div>

        <div class="fg">
          <label class="fl">Kart Üzerindeki Ad Soyad</label>
          <input type="text" name="kart_ad" class="fc" placeholder="Ad Soyad"
            value="<?= htmlspecialchars($_POST['kart_ad'] ?? '') ?>"
            maxlength="40" autocomplete="cc-name">
        </div>

        <div class="fg">
          <label class="fl">Kart Numarası</label>
          <input type="text" name="kart_no" id="iNo" class="fc"
            placeholder="0000  0000  0000  0000"
            value="<?= htmlspecialchars($_POST['kart_no'] ?? '') ?>"
            maxlength="22" inputmode="numeric" autocomplete="cc-number">
        </div>

        <div class="uc-kolon">
          <div class="fg">
            <label class="fl">Ay</label>
            <input type="text" name="kart_ay" class="fc" placeholder="AA"
              value="<?= htmlspecialchars($_POST['kart_ay'] ?? '') ?>"
              maxlength="2" inputmode="numeric" autocomplete="cc-exp-month">
          </div>
          <div class="fg">
            <label class="fl">Yıl</label>
            <input type="text" name="kart_yil" class="fc" placeholder="YYYY"
              value="<?= htmlspecialchars($_POST['kart_yil'] ?? '') ?>"
              maxlength="4" inputmode="numeric" autocomplete="cc-exp-year">
          </div>
          <div class="fg">
            <label class="fl">CVV</label>
            <input type="password" name="kart_cvv" class="fc" placeholder="•••"
              maxlength="4" inputmode="numeric" autocomplete="cc-csc">
          </div>
        </div>

        <div class="ozet">
          <div class="ozet-satir">
            <span class="ozet-key"><?= htmlspecialchars($kurs['baslik']) ?></span>
            <span class="ozet-val"><?= $fiyat_goster ?> ₺</span>
          </div>
          <div class="ozet-satir">
            <span class="ozet-key">KDV (%18)</span>
            <span class="ozet-val">Dahil</span>
          </div>
          <div class="ozet-satir toplam">
            <span class="ozet-key">Toplam</span>
            <span class="ozet-val"><?= $fiyat_goster ?> ₺</span>
          </div>
        </div>

        <button type="submit" name="odeme_yap" class="btn-ode">
          🔒 <?= $fiyat_goster ?> ₺ Güvenli Öde
        </button>

        <div class="guven-notlar">
          <span class="guven-item">🔒 SSL şifreleme</span>
          <span class="guven-item">💳 Kart bilgisi saklanmaz</span>
          <span class="guven-item">🔄 7 gün iade</span>
        </div>

        <a href="tum_kurslar.php" class="geri-link">← Kurslara Geri Dön</a>
      </form>
    </div>
  </div>

  <?php endif; ?>
  </div>
</main>


<script>
const iNo = document.getElementById('iNo');
iNo && iNo.addEventListener('input', function () {
  let raw = this.value.replace(/\D/g, '').substring(0, 16);
  this.value = raw.replace(/(.{4})/g, '$1  ').trim();
});
</script>
</body>
</html>