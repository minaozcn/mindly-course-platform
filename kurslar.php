<?php

session_start();
require_once 'db.php';

if (!isset($_SESSION['kullanici_id'])) {
    header('Location: giris.php');
    exit;
}

$kullanici_id = $_SESSION['kullanici_id'];
$flash        = '';
$flash_tip    = '';

// ── Kurstan Çıkma (DELETE) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kurs_birak'])) {
    $kurs_id = (int)($_POST['kurs_id'] ?? 0);

    if ($kurs_id > 0) {
  
        $stmtN = $pdo->prepare(
            "SELECT k.baslik FROM kurslar k
             JOIN satinalma s ON s.kurs_id = k.id
             WHERE s.kullanici_id = ? AND k.id = ?"
        );
        $stmtN->execute([$kullanici_id, $kurs_id]);
        $bilgi = $stmtN->fetch();

        if ($bilgi) {
            // *** DELETE sorgusu ***
            $stmtD = $pdo->prepare(
                "DELETE FROM satinalma WHERE kullanici_id = ? AND kurs_id = ?"
            );
            $stmtD->execute([$kullanici_id, $kurs_id]);

            $flash    = '«' . htmlspecialchars($bilgi['baslik']) . '» kaydı silindi.';
            $flash_tip = 'bilgi';
        }
    }
}

// ── Ders Tamamlama (UPDATE) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ders_tamamla'])) {
    $kurs_id = (int)($_POST['kurs_id'] ?? 0);

    $stmtP = $pdo->prepare(
        "SELECT k.baslik, k.ders_sayisi, s.ilerleme FROM satinalma s
         JOIN kurslar k ON k.id = s.kurs_id
         WHERE s.kullanici_id = ? AND s.kurs_id = ? AND s.durum = 'devam'"
    );
    $stmtP->execute([$kullanici_id, $kurs_id]);
    $p = $stmtP->fetch();

    if ($p && $p['ders_sayisi'] > 0) {
        $ders_sayisi = (int)$p['ders_sayisi'];
        $tamamlanan  = min($ders_sayisi, (int)round($p['ilerleme'] * $ders_sayisi / 100) + 1);
        $yeni_yuzde  = (int)round($tamamlanan / $ders_sayisi * 100);
        $yeni_durum  = $tamamlanan >= $ders_sayisi ? 'tamamlandi' : 'devam';

        $pdo->prepare("UPDATE satinalma SET ilerleme = ?, durum = ? WHERE kullanici_id = ? AND kurs_id = ?")
            ->execute([$yeni_yuzde, $yeni_durum, $kullanici_id, $kurs_id]);

        // Dashboard istatistikleri için ders kaydı (ilerleme tablosu)
        try {
            $pdo->prepare("INSERT INTO ilerleme (kullanici_id, kurs_id, tamamlanan_ders, guncelleme_tarihi) VALUES (?, ?, 1, NOW())")
                ->execute([$kullanici_id, $kurs_id]);
        } catch (Exception $e) { /* ilerleme tablosu yoksa sadece yüzde güncellenir */ }

        $flash     = $yeni_durum === 'tamamlandi'
            ? '🎉 «' . htmlspecialchars($p['baslik']) . '» kursunu tamamladın!'
            : '✅ Ders tamamlandı: ' . $tamamlanan . ' / ' . $ders_sayisi;
        $flash_tip = 'bilgi';
    }
}

// ── Kullanıcı Bilgisi ────────────────────────────────────────
$stmtU = $pdo->prepare("SELECT ad_soyad, kayit_tarihi FROM kullanicilar WHERE id = ?");
$stmtU->execute([$kullanici_id]);
$kullanici = $stmtU->fetch();

if (!$kullanici) { session_destroy(); header('Location: giris.php'); exit; }

$ad_soyad    = $kullanici['ad_soyad'];
$ilk_harf    = mb_strtoupper(mb_substr($ad_soyad, 0, 1, 'UTF-8'), 'UTF-8');
$kayit_tarihi = date('d.m.Y', strtotime($kullanici['kayit_tarihi']));


$izin_sira = [
    's.satin_alma_tar DESC' => 'En Yeni',
    's.satin_alma_tar ASC'  => 'En Eski',
    'k.baslik ASC'          => 'A–Z',
    's.ilerleme DESC'       => 'En İleri',
];
$sira = array_key_exists($_GET['sira'] ?? '', $izin_sira)
        ? $_GET['sira'] : 's.satin_alma_tar DESC';

// *** SELECT + ORDER BY — satın alınan tüm kurslar ***
$stmtT = $pdo->prepare(
    "SELECT k.*, s.ilerleme, s.durum, s.satin_alma_tar
     FROM satinalma s
     JOIN kurslar k ON k.id = s.kurs_id
     WHERE s.kullanici_id = ?
     ORDER BY $sira"
);
$stmtT->execute([$kullanici_id]);
$tum_kurslar = $stmtT->fetchAll();

// Devam eden / tamamlanan ayrımı
$devam_kurslar      = array_filter($tum_kurslar, fn($k) => $k['durum'] === 'devam');
$tamamlanan_kurslar = array_filter($tum_kurslar, fn($k) => $k['durum'] === 'tamamlandi');

// *** SELECT + LIMIT — en yüksek ilerleme (öne çıkan kart için) ***
$stmtF = $pdo->prepare(
    "SELECT k.*, s.ilerleme, s.satin_alma_tar
     FROM satinalma s
     JOIN kurslar k ON k.id = s.kurs_id
     WHERE s.kullanici_id = ? AND s.durum = 'devam'
     ORDER BY s.ilerleme DESC
     LIMIT 1"
);
$stmtF->execute([$kullanici_id]);
$one_cikan = $stmtF->fetch();

$seviye_class = ['Başlangıç' => 'basla', 'Orta' => 'orta', 'İleri' => 'ileri'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kurslarım – Mindly</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  /* Orijinal kurslar.php CSS'i aynen korunuyor */
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  html { scroll-behavior:smooth; }
  :root {
    --bg-grad:  linear-gradient(135deg,#1a0a2e 0%,#6b2d8b 40%,#c45fa0 70%,#f0c4d4 100%);
    --surface:  rgba(255,255,255,0.08);
    --surface2: rgba(255,255,255,0.13);
    --border:   rgba(255,255,255,0.15);
    --border2:  rgba(240,196,212,0.35);
    --blush:    #f0c4d4;
    --cyan:     #00d4ff;
    --green:    #34d399;
    --amber:    #fbbf24;
    --text:     #fff;
    --muted:    rgba(255,255,255,0.45);
    --font-head:'Playfair Display',serif;
    --font-syne:'Syne',sans-serif;
    --font-body:'DM Sans',sans-serif;
    --sidebar-w:240px;
  }
  body { font-family:var(--font-body); background:var(--bg-grad); background-attachment:fixed; min-height:100vh; color:var(--text); }
  ::-webkit-scrollbar { width:5px; }
  ::-webkit-scrollbar-thumb { background:rgba(240,196,212,0.25); border-radius:99px; }

  /* SIDEBAR */
  .sidebar { position:fixed; top:0; left:0; width:var(--sidebar-w); height:100vh; background:rgba(26,10,46,0.55); border-right:.5px solid var(--border); backdrop-filter:blur(18px); display:flex; flex-direction:column; padding:30px 20px; z-index:200; }
  .logo { font-family:var(--font-head); font-size:1.45rem; font-weight:700; letter-spacing:5px; color:var(--text); text-decoration:none; display:block; }
  .slogan { font-size:9px; letter-spacing:3px; color:rgba(240,196,212,0.65); font-weight:300; margin-top:3px; }
  .nav-section { margin-top:38px; flex:1; }
  .nav-label { font-size:9px; letter-spacing:3px; text-transform:uppercase; color:var(--muted); padding-left:12px; margin-bottom:10px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:12px; color:var(--muted); text-decoration:none; font-size:.87rem; font-weight:400; border:.5px solid transparent; margin-bottom:4px; transition:all .2s; }
  .nav-item:hover { background:var(--surface); color:var(--text); border-color:var(--border); }
  .nav-item.active { background:rgba(240,196,212,0.15); color:var(--blush); border-color:var(--border2); }
  .nav-item .icon { font-size:1rem; width:20px; text-align:center; flex-shrink:0; }
  .sidebar-bottom { margin-top:auto; }
  .user-pill { display:flex; align-items:center; gap:10px; padding:12px 14px; background:var(--surface); border:.5px solid var(--border); border-radius:14px; margin-bottom:10px; }
  .avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#c45fa0,#6b2d8b); display:flex; align-items:center; justify-content:center; font-family:var(--font-syne); font-weight:700; font-size:1rem; flex-shrink:0; border:1.5px solid rgba(240,196,212,0.5); }
  .user-name { font-size:.82rem; font-weight:600; }
  .user-since { font-size:.68rem; color:var(--muted); margin-top:1px; }
  .btn-logout { width:100%; padding:9px; background:transparent; border:.5px solid var(--border); border-radius:12px; color:var(--muted); font-family:var(--font-body); font-size:.82rem; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:6px; }
  .btn-logout:hover { background:rgba(255,80,80,0.12); color:#ff8080; border-color:rgba(255,80,80,0.35); }

  /* MAIN */
  .main { margin-left:var(--sidebar-w); padding:40px 44px; min-height:100vh; }

  /* FLASH */
  .flash { border-radius:14px; padding:14px 20px; font-size:.88rem; margin-bottom:24px; animation:fadeUp .3s ease both; }
  .flash.bilgi { background:rgba(0,212,255,0.1); border:.5px solid rgba(0,212,255,0.3); color:var(--cyan); }

  /* PAGE HEADER */
  .page-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:32px; animation:fadeUp .45s ease both; flex-wrap:wrap; gap:16px; }
  .page-eyebrow { font-size:9px; letter-spacing:3px; text-transform:uppercase; color:rgba(240,196,212,0.65); font-weight:300; margin-bottom:8px; }
  .page-title { font-family:var(--font-head); font-size:2.2rem; font-weight:700; line-height:1.15; }
  .page-title em { font-style:italic; color:var(--blush); }
  .page-sub { font-size:.85rem; color:var(--muted); margin-top:6px; font-weight:300; }

  /* SORT */
  .sort-wrap { display:flex; align-items:center; gap:10px; }
  .sort-label { font-size:.78rem; color:var(--muted); }
  .sort-select { padding:9px 14px; background:var(--surface); border:.5px solid var(--border); border-radius:99px; color:var(--text); font-family:var(--font-body); font-size:.82rem; cursor:pointer; outline:none; }
  .sort-select:focus { border-color:var(--border2); }

  /* ÖZET BANDALAR */
  .summary-row { display:flex; gap:12px; margin-bottom:28px; animation:fadeUp .45s ease .06s both; flex-wrap:wrap; }
  .summary-pill { display:flex; align-items:center; gap:9px; background:var(--surface); border:.5px solid var(--border); border-radius:99px; padding:8px 18px; font-size:.8rem; color:var(--muted); backdrop-filter:blur(6px); white-space:nowrap; }
  .summary-pill .dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
  .dot-blue { background:var(--cyan); }
  .dot-green { background:var(--green); }
  .summary-pill strong { color:var(--text); font-weight:600; }

  /* ÖNCÜ BANNER */
  .featured-card { background:linear-gradient(135deg,rgba(107,45,139,0.45) 0%,rgba(196,95,160,0.3) 100%); border:.5px solid rgba(240,196,212,0.3); border-radius:22px; padding:30px 34px; display:flex; align-items:center; gap:28px; margin-bottom:28px; backdrop-filter:blur(10px); position:relative; overflow:hidden; animation:fadeUp .45s ease .14s both; transition:transform .25s,border-color .25s; }
  .featured-card:hover { transform:translateY(-4px); border-color:rgba(240,196,212,0.5); }
  .featured-card::before { content:''; position:absolute; right:-60px; top:-60px; width:220px; height:220px; border-radius:50%; background:radial-gradient(circle,rgba(240,196,212,0.12) 0%,transparent 70%); pointer-events:none; }
  .featured-emoji { font-size:3.4rem; flex-shrink:0; }
  .featured-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(240,196,212,0.15); border:.5px solid rgba(240,196,212,0.35); border-radius:99px; padding:3px 12px; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:var(--blush); font-weight:500; margin-bottom:9px; }
  .live-dot { width:6px; height:6px; border-radius:50%; background:var(--blush); animation:blink 2s infinite; }
  .featured-title { font-family:var(--font-head); font-size:1.35rem; font-weight:700; margin-bottom:5px; }
  .featured-sub { font-size:.82rem; color:var(--muted); font-weight:300; }
  .featured-right { margin-left:auto; text-align:right; flex-shrink:0; }
  .featured-pct { font-family:var(--font-syne); font-size:2.6rem; font-weight:800; color:var(--blush); line-height:1; margin-bottom:6px; }
  .featured-pct-label { font-size:.72rem; color:var(--muted); margin-bottom:14px; }
  .btn-resume { background:rgba(240,196,212,0.2); color:var(--blush); border:.5px solid rgba(240,196,212,0.45); border-radius:12px; padding:11px 26px; font-family:var(--font-body); font-size:.85rem; font-weight:600; cursor:pointer; letter-spacing:.5px; transition:all .2s; }
  .btn-resume:hover { background:rgba(240,196,212,0.35); color:#fff; }

  /* SECTION BAŞLIĞI */
  .section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
  .section-title { font-family:var(--font-head); font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:10px; }
  .section-line { display:inline-block; width:22px; height:1.5px; background:var(--blush); opacity:.55; }
  .section-count { font-family:var(--font-syne); font-size:.72rem; color:var(--muted); font-weight:400; }

  /* KURS GRID */
  .courses-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-bottom:36px; }
  .course-card { background:rgba(255,255,255,0.07); border:.5px solid var(--border); border-radius:20px; padding:26px; transition:all .25s; backdrop-filter:blur(4px); position:relative; overflow:hidden; animation:fadeUp .5s ease both; display:flex; flex-direction:column; }
  .course-card::after { content:''; position:absolute; inset:0; background:radial-gradient(circle at var(--mx,50%) var(--my,50%),rgba(240,196,212,0.08) 0%,transparent 60%); opacity:0; transition:opacity .3s; pointer-events:none; }
  .course-card:hover { background:rgba(255,255,255,0.12); border-color:var(--border2); transform:translateY(-5px); }
  .course-card:hover::after { opacity:1; }

  .completed-badge { position:absolute; top:16px; right:16px; background:rgba(52,211,153,0.18); border:.5px solid rgba(52,211,153,0.45); border-radius:99px; padding:3px 10px; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:var(--green); font-weight:600; }
  .card-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
  .course-emoji { font-size:2.1rem; }
  .level-tag { font-size:9px; letter-spacing:2px; text-transform:uppercase; font-weight:600; padding:3px 10px; border-radius:99px; border:.5px solid; }
  .level-tag.basla { color:var(--green); border-color:rgba(52,211,153,0.4); background:rgba(52,211,153,0.1); }
  .level-tag.orta  { color:var(--amber); border-color:rgba(251,191,36,0.4);  background:rgba(251,191,36,0.1); }
  .level-tag.ileri { color:#f87171;      border-color:rgba(248,113,113,0.4); background:rgba(248,113,113,0.1); }

  .course-cat { font-size:9px; letter-spacing:2.5px; text-transform:uppercase; color:var(--blush); font-weight:500; margin-bottom:5px; }
  .course-name { font-family:var(--font-head); font-size:1.05rem; font-weight:700; line-height:1.35; margin-bottom:5px; }
  .course-desc { font-size:.78rem; color:var(--muted); line-height:1.55; font-weight:300; margin-bottom:16px; flex:1; }
  .course-meta-row { display:flex; gap:14px; margin-bottom:16px; }
  .meta-chip { font-size:.72rem; color:var(--muted); display:flex; align-items:center; gap:4px; }
  .progress-bar { background:rgba(255,255,255,0.1); border-radius:99px; height:5px; overflow:hidden; }
  .progress-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#c45fa0,#f0c4d4); }
  .progress-label { display:flex; justify-content:space-between; font-size:.7rem; color:var(--muted); margin-top:7px; }
  .progress-label strong { color:var(--blush); font-weight:600; }

  /* SİL BUTONU */
  .btn-birak {
    width:100%; padding:9px; border-radius:12px; margin-top:12px;
    background:transparent; color:rgba(248,113,113,0.6);
    border:.5px solid rgba(248,113,113,0.25);
    font-family:var(--font-body); font-size:.78rem; font-weight:500;
    cursor:pointer; letter-spacing:.3px; transition:all .2s;
  }
  .btn-birak:hover { background:rgba(248,113,113,0.12); color:#f87171; border-color:rgba(248,113,113,0.45); }

  /* BOŞ DURUM */
  .empty-state { text-align:center; padding:70px 20px; animation:fadeUp .5s ease both; }
  .empty-icon { font-size:3.5rem; margin-bottom:16px; }
  .empty-title { font-family:var(--font-head); font-size:1.3rem; font-weight:700; margin-bottom:8px; }
  .empty-sub { font-size:.85rem; color:var(--muted); font-weight:300; margin-bottom:24px; }
  .btn-discover { display:inline-block; background:rgba(240,196,212,0.18); color:var(--blush); border:.5px solid rgba(240,196,212,0.4); border-radius:14px; padding:12px 28px; font-family:var(--font-body); font-size:.87rem; font-weight:600; text-decoration:none; letter-spacing:.5px; transition:all .2s; }
  .btn-discover:hover { background:rgba(240,196,212,0.32); color:#fff; }

  /* VIDEO BUTONU */
  .btn-izle {
    width:100%; padding:10px; border-radius:12px; margin-top:10px;
    background:linear-gradient(135deg,rgba(196,95,160,0.35),rgba(107,45,139,0.35));
    color:var(--blush); border:.5px solid rgba(240,196,212,0.4);
    font-family:var(--font-body); font-size:.84rem; font-weight:600;
    cursor:pointer; letter-spacing:.4px; transition:all .2s;
    display:flex; align-items:center; justify-content:center; gap:7px;
  }
  .btn-izle:hover { background:linear-gradient(135deg,rgba(196,95,160,0.55),rgba(107,45,139,0.55)); border-color:rgba(240,196,212,0.7); color:#fff; }
  .btn-izle:disabled { opacity:.4; cursor:not-allowed; }

  /* VIDEO MODAL */
  .modal-overlay {
    position:fixed; inset:0; z-index:1000;
    background:rgba(10,4,20,0.85); backdrop-filter:blur(10px);
    display:flex; align-items:center; justify-content:center;
    padding:20px; opacity:0; pointer-events:none; transition:opacity .25s;
  }
  .modal-overlay.open { opacity:1; pointer-events:all; }
  .modal-box {
    background:linear-gradient(145deg,rgba(26,10,46,0.97),rgba(50,20,75,0.97));
    border:.5px solid rgba(240,196,212,0.3); border-radius:24px;
    width:100%; max-width:820px; overflow:hidden;
    transform:translateY(30px) scale(.97); transition:transform .28s ease;
    box-shadow:0 40px 100px rgba(0,0,0,0.6);
  }
  .modal-overlay.open .modal-box { transform:translateY(0) scale(1); }
  .modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:18px 24px; border-bottom:.5px solid rgba(255,255,255,0.08);
  }
  .modal-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; display:flex; align-items:center; gap:10px; }
  .modal-close {
    width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.08);
    border:.5px solid rgba(255,255,255,0.12); color:var(--muted);
    font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:all .18s; line-height:1;
  }
  .modal-close:hover { background:rgba(248,113,113,0.2); color:#f87171; border-color:rgba(248,113,113,0.35); }
  .modal-video-wrap {
    position:relative; background:#000; aspect-ratio:16/9;
  }
  .modal-video-wrap video {
    width:100%; height:100%; display:block; outline:none;
  }
  .modal-no-video {
    aspect-ratio:16/9; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:12px;
    color:var(--muted); font-size:.88rem;
  }
  .modal-no-video span { font-size:2.5rem; }
  .modal-footer { padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
  .modal-meta { font-size:.78rem; color:var(--muted); display:flex; gap:16px; }

  @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.25} }

  @media(max-width:768px) {
    .sidebar { display:none; }
    .main { margin-left:0; padding:24px 18px; }
    .page-header { flex-direction:column; align-items:flex-start; }
    .featured-card { flex-direction:column; align-items:flex-start; }
    .featured-right { margin-left:0; text-align:left; }
    .summary-row { flex-wrap:wrap; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div>
    <a href="anasayfa.php" class="logo">MINDLY</a>
    <p class="slogan">learn · grow · shine</p>
  </div>
  <nav class="nav-section">
    <p class="nav-label">Menü</p>
    <a href="anasayfa.php"    class="nav-item"><span class="icon">🏠</span> Ana Sayfa</a>
    <a href="kurslar.php"     class="nav-item active"><span class="icon">📚</span> Kurslarım</a>
    <a href="tum_kurslar.php" class="nav-item"><span class="icon">🔍</span> Keşfet</a>
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

  <!-- FLASH -->
  <?php if ($flash): ?>
    <div class="flash <?= $flash_tip ?>"><?= $flash ?></div>
  <?php endif; ?>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <div class="page-eyebrow">Öğrenme Yolculuğun</div>
      <div class="page-title">Kurslarım <em>&amp; Gelişimim</em></div>
      <div class="page-sub">Devam eden, tamamlanan kursların.</div>
    </div>
    <form method="GET" action="kurslar.php" class="sort-wrap">
      <span class="sort-label">Sırala:</span>
      <select name="sira" class="sort-select" onchange="this.form.submit()">
        <?php foreach ($izin_sira as $val => $lbl): ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $sira===$val?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($tum_kurslar)): ?>
  <!-- BOŞ DURUM -->
  <div class="empty-state">
    <div class="empty-icon">📚</div>
    <div class="empty-title">Henüz kurs yok</div>
    <div class="empty-sub">Keşfet sayfasına git, ilk kursuna kaydol.</div>
    <a href="tum_kurslar.php" class="btn-discover">Kursları Keşfet →</a>
  </div>

  <?php else: ?>

  <!-- ÖZET BANDALAR -->
  <div class="summary-row">
    <div class="summary-pill">
      <span class="dot dot-blue"></span>
      <strong><?= count($devam_kurslar) ?></strong> Devam Ediyor
    </div>
    <div class="summary-pill">
      <span class="dot dot-green"></span>
      <strong><?= count($tamamlanan_kurslar) ?></strong> Tamamlandı
    </div>
    <div class="summary-pill">
      📚 <strong><?= count($tum_kurslar) ?></strong> Toplam Kurs
    </div>
  </div>

  <!-- ÖNE ÇIKAN BANNER (en yüksek ilerlemeli devam eden kurs) -->
  <?php if ($one_cikan): ?>
  <div class="featured-card">
    <div class="featured-emoji"><?= $one_cikan['ikon'] ?></div>
    <div>
      <div class="featured-badge"><span class="live-dot"></span> Kaldığın Yerden</div>
      <div class="featured-title"><?= htmlspecialchars($one_cikan['baslik']) ?></div>
      <div class="featured-sub"><?= htmlspecialchars($one_cikan['kategori']) ?> · <?= $one_cikan['ders_sayisi'] ?> ders</div>
    </div>
    <div class="featured-right">
      <div class="featured-pct"><?= $one_cikan['ilerleme'] ?>%</div>
      <div class="featured-pct-label">tamamlandı</div>
    </div>
  </div>
  <?php endif; ?>

  
  <?php if (!empty($devam_kurslar)): ?>
  <div class="section-head">
    <div class="section-title">
      <span class="section-line"></span>
      Devam Ediyor
      <span class="section-count"><?= count($devam_kurslar) ?> kurs</span>
    </div>
  </div>
  <div class="courses-grid">
    <?php foreach ($devam_kurslar as $k): ?>
    <div class="course-card">
      <div class="card-top">
        <span class="course-emoji"><?= $k['ikon'] ?></span>
        <span class="level-tag <?= $seviye_class[$k['seviye']] ?? 'basla' ?>">
          <?= htmlspecialchars($k['seviye']) ?>
        </span>
      </div>
      <div class="course-cat"><?= htmlspecialchars($k['kategori']) ?></div>
      <div class="course-name"><?= htmlspecialchars($k['baslik']) ?></div>
      <div class="course-desc"><?= htmlspecialchars($k['aciklama']) ?></div>
      <div class="course-meta-row">
        <span class="meta-chip">📖 <?= $k['ders_sayisi'] ?> ders</span>
        <span class="meta-chip">⏱ ~<?= $k['sure_saat'] ?> saat</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $k['ilerleme'] ?>%"></div>
      </div>
      <div class="progress-label">
        <span>İlerleme</span>
        <strong><?= $k['ilerleme'] ?>%</strong>
      </div>
      <?php if (!empty($k['video_url'])): ?>
      <button class="btn-izle" onclick="videoAc(<?= $k['id'] ?>, <?= htmlspecialchars(json_encode($k['baslik'])) ?>, <?= htmlspecialchars(json_encode($k['video_url'])) ?>, <?= htmlspecialchars(json_encode($k['ikon'])) ?>)">
        ▶ Kursu İzle
      </button>
      <?php else: ?>
      <button class="btn-izle" disabled title="Video henüz yüklenmedi">⏳ Video Yakında</button>
      <?php endif; ?>
      <form method="POST" action="kurslar.php?sira=<?= urlencode($sira) ?>">
        <input type="hidden" name="kurs_id" value="<?= $k['id'] ?>">
        <button type="submit" name="ders_tamamla" class="btn-izle" style="margin-top:8px">
          ✓ Bir Dersi Tamamla
        </button>
      </form>
      <!-- *** DELETE tetikleyici form *** -->
      <form method="POST" action="kurslar.php?sira=<?= urlencode($sira) ?>"
            onsubmit="return confirm('«<?= addslashes(htmlspecialchars($k['baslik'])) ?>» kaydını silmek istediğine emin misin?')">
        <input type="hidden" name="kurs_id" value="<?= $k['id'] ?>">
        <button type="submit" name="kurs_birak" class="btn-birak">
          Kaydı Sil
        </button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- TAMAMLANAN KURSLAR -->
  <?php if (!empty($tamamlanan_kurslar)): ?>
  <div class="section-head">
    <div class="section-title">
      <span class="section-line"></span>
      Tamamlandı ✅
      <span class="section-count"><?= count($tamamlanan_kurslar) ?> kurs</span>
    </div>
  </div>
  <div class="courses-grid">
    <?php foreach ($tamamlanan_kurslar as $k): ?>
    <div class="course-card">
      <div class="completed-badge">✓ Tamamlandı</div>
      <div class="card-top">
        <span class="course-emoji"><?= $k['ikon'] ?></span>
        <span class="level-tag <?= $seviye_class[$k['seviye']] ?? 'basla' ?>">
          <?= htmlspecialchars($k['seviye']) ?>
        </span>
      </div>
      <div class="course-cat"><?= htmlspecialchars($k['kategori']) ?></div>
      <div class="course-name"><?= htmlspecialchars($k['baslik']) ?></div>
      <div class="course-desc"><?= htmlspecialchars($k['aciklama']) ?></div>
      <div class="course-meta-row">
        <span class="meta-chip">📖 <?= $k['ders_sayisi'] ?> ders</span>
        <span class="meta-chip">⏱ ~<?= $k['sure_saat'] ?> saat</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:100%"></div>
      </div>
      <div class="progress-label">
        <span>İlerleme</span>
        <strong style="color:var(--green)">100% 🎉</strong>
      </div>
      <?php if (!empty($k['video_url'])): ?>
      <button class="btn-izle" onclick="videoAc(<?= $k['id'] ?>, <?= htmlspecialchars(json_encode($k['baslik'])) ?>, <?= htmlspecialchars(json_encode($k['video_url'])) ?>, <?= htmlspecialchars(json_encode($k['ikon'])) ?>)">
        ▶ Kursu Tekrar İzle
      </button>
      <?php else: ?>
      <button class="btn-izle" disabled>⏳ Video Yakında</button>
      <?php endif; ?>
      <!-- DELETE — tamamlanan kursu da kaldırabilir -->
      <form method="POST" action="kurslar.php?sira=<?= urlencode($sira) ?>"
            onsubmit="return confirm('Tamamlanan kursu da silmek istediğine emin misin?')">
        <input type="hidden" name="kurs_id" value="<?= $k['id'] ?>">
        <button type="submit" name="kurs_birak" class="btn-birak">Kaydı Sil</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>

</main>

<!-- ══ VİDEO MODAL ══ -->
<div class="modal-overlay" id="videoModal" onclick="modalDis(event)">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modalBaslik">📚 Kurs Videosu</div>
      <button class="modal-close" onclick="modalKapat()" aria-label="Kapat">✕</button>
    </div>
    <div class="modal-video-wrap" id="modalVideoWrap">
      <!-- video buraya dinamik eklenir -->
    </div>
    <div class="modal-footer">
      <div class="modal-meta" id="modalMeta"></div>
    </div>
  </div>
</div>

<script>
  /* ── Kurs kartı hover efekti ── */
  document.querySelectorAll('.course-card').forEach((el, i) => {
    el.style.animationDelay = (0.08 + i * 0.07) + 's';
    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect();
      el.style.setProperty('--mx', ((e.clientX - r.left) / r.width  * 100) + '%');
      el.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
    });
  });

  /* ── Video Modal ── */
  const modal    = document.getElementById('videoModal');
  const videoWrap = document.getElementById('modalVideoWrap');
  const modalBaslik = document.getElementById('modalBaslik');
  const modalMeta   = document.getElementById('modalMeta');

  function videoAc(id, baslik, url, ikon) {
    // Başlık
    modalBaslik.textContent = ikon + ' ' + baslik;

    // Video elementi temizle ve yeniden oluştur
    videoWrap.innerHTML = '';
    const vid = document.createElement('video');
    vid.src = url;
    vid.controls = true;
    vid.autoplay = true;
    vid.style.width = '100%';
    vid.style.height = '100%';
    vid.style.display = 'block';
    vid.style.outline = 'none';
    vid.setAttribute('controlsList', 'nodownload');
    videoWrap.appendChild(vid);

    // Meta
    modalMeta.textContent = 'Video oynatılıyor...';

    // Aç
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function modalKapat() {
    modal.classList.remove('open');
    document.body.style.overflow = '';
    // Videoyu durdur
    const vid = videoWrap.querySelector('video');
    if (vid) { vid.pause(); vid.src = ''; }
  }

  function modalDis(e) {
    // Overlay'e (arkaplan) tıklanınca kapat, modal-box'a değil
    if (e.target === modal) modalKapat();
  }

  // ESC ile kapat
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') modalKapat();
  });
</script>
</body>
</html>