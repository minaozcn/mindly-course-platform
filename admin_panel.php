<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['admin_giris'])) {
    header('Location: admin_giris.php');
    exit;
}

$mesaj = '';
$hata  = '';
$aktif_sekme = $_GET['sekme'] ?? 'kurslar';

// ── Video Yükleme Yardımcısı ──
function videoyukle(string $input_adi): string {
    if (empty($_FILES[$input_adi]['name'])) return '';
    $dosya     = $_FILES[$input_adi];
    $izin_mime = ['video/mp4','video/webm','video/ogg','video/quicktime'];
    $izin_ext  = ['mp4','webm','ogv','ogg','mov'];
    $ext       = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
    if ($dosya['error'] !== UPLOAD_ERR_OK)      throw new Exception('Yükleme hatası: ' . $dosya['error']);
    if (!in_array($dosya['type'], $izin_mime))  throw new Exception('Geçersiz dosya türü. Sadece MP4, WebM, MOV.');
    if (!in_array($ext, $izin_ext))             throw new Exception('Geçersiz uzantı.');
    if ($dosya['size'] > 500 * 1024 * 1024)     throw new Exception('Dosya 500 MB\'dan büyük olamaz.');
    $klasor = __DIR__ . '/uploads/videolar/';
    if (!is_dir($klasor)) mkdir($klasor, 0755, true);
    $yeni_ad = uniqid('video_', true) . '.' . $ext;
    if (!move_uploaded_file($dosya['tmp_name'], $klasor . $yeni_ad))
        throw new Exception('Dosya taşınamadı. Klasör izinlerini kontrol edin.');
    return 'uploads/videolar/' . $yeni_ad;
}

// ── Kurs Ekle ──
if (isset($_POST['kurs_ekle'])) {
    $baslik      = trim($_POST['baslik']      ?? '');
    $kategori    = trim($_POST['kategori']    ?? '');
    $seviye      = trim($_POST['seviye']      ?? '');
    $ders_sayisi = (int)($_POST['ders_sayisi'] ?? 0);
    $fiyat       = (float)($_POST['fiyat']    ?? 0);
    $aciklama    = trim($_POST['aciklama']    ?? '');
    $ikon        = trim($_POST['ikon']        ?? '📚');

    if ($baslik && $kategori && $seviye && $ders_sayisi > 0) {
        try {
            $video_url = videoyukle('video_dosya');
            $pdo->prepare("INSERT INTO kurslar (baslik, kategori, seviye, ders_sayisi, fiyat, aciklama, ikon, video_url) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$baslik, $kategori, $seviye, $ders_sayisi, $fiyat, $aciklama, $ikon, $video_url]);
            $mesaj = '✅ "' . htmlspecialchars($baslik) . '" eklendi!';
        } catch (Exception $e) { $hata = '❌ ' . $e->getMessage(); }
    } else { $hata = '❌ Lütfen tüm zorunlu alanları doldurun.'; }
    $aktif_sekme = 'kurslar';
}

// ── Kurs Sil ──
if (isset($_POST['kurs_sil'])) {
    $kurs_id = (int)($_POST['kurs_id'] ?? 0);
    if ($kurs_id > 0) {
        try {
            $pdo->prepare("DELETE FROM satinalma WHERE kurs_id = ?")->execute([$kurs_id]);
            $pdo->prepare("DELETE FROM kurslar WHERE id = ?")->execute([$kurs_id]);
            $mesaj = '🗑️ Kurs başarıyla silindi.';
        } catch (Exception $e) { $hata = '❌ ' . $e->getMessage(); }
    }
    $aktif_sekme = 'kurslar';
}

// ── Kurs Güncelle ──
if (isset($_POST['kurs_guncelle'])) {
    $kurs_id     = (int)($_POST['kurs_id']     ?? 0);
    $baslik      = trim($_POST['baslik']        ?? '');
    $kategori    = trim($_POST['kategori']      ?? '');
    $seviye      = trim($_POST['seviye']        ?? '');
    $ders_sayisi = (int)($_POST['ders_sayisi']  ?? 0);
    $fiyat       = (float)($_POST['fiyat']      ?? 0);
    $aciklama    = trim($_POST['aciklama']      ?? '');
    $ikon        = trim($_POST['ikon']          ?? '📚');

    if ($kurs_id > 0 && $baslik && $kategori && $seviye && $ders_sayisi > 0) {
        try {
            // Yeni video yüklendiyse işle, yoksa mevcut yolu koru
            if (!empty($_FILES['video_dosya']['name'])) {
                $video_url = videoyukle('video_dosya');
                // Eski dosyayı sil
                $eski = $pdo->prepare("SELECT video_url FROM kurslar WHERE id=?");
                $eski->execute([$kurs_id]);
                $eski_yol = $eski->fetchColumn();
                if ($eski_yol && file_exists(__DIR__ . '/' . $eski_yol)) {
                    @unlink(__DIR__ . '/' . $eski_yol);
                }
            } else {
                $mevcut = $pdo->prepare("SELECT video_url FROM kurslar WHERE id=?");
                $mevcut->execute([$kurs_id]);
                $video_url = $mevcut->fetchColumn() ?: '';
            }
            $pdo->prepare("UPDATE kurslar SET baslik=?, kategori=?, seviye=?, ders_sayisi=?, fiyat=?, aciklama=?, ikon=?, video_url=? WHERE id=?")
                ->execute([$baslik, $kategori, $seviye, $ders_sayisi, $fiyat, $aciklama, $ikon, $video_url, $kurs_id]);
            $mesaj = '✅ "' . htmlspecialchars($baslik) . '" güncellendi!';
        } catch (Exception $e) { $hata = '❌ ' . $e->getMessage(); }
    } else { $hata = '❌ Lütfen tüm zorunlu alanları doldurun.'; }
    $aktif_sekme = 'kurslar';
}

// Düzenleme modunda hangi kurs?
$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle_kurs = null;

// ── Kullanıcı Ekle ──
if (isset($_POST['kullanici_ekle'])) {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $eposta   = trim($_POST['eposta']   ?? '');
    $sifre    = trim($_POST['sifre']    ?? '');

    if ($ad_soyad && $eposta && $sifre) {
        try {
            $mevcut = $pdo->prepare("SELECT id FROM kullanicilar WHERE eposta = ?");
            $mevcut->execute([$eposta]);
            if ($mevcut->fetch()) {
                $hata = '❌ Bu e-posta adresi zaten kayıtlı.';
            } else {
                $pdo->prepare("INSERT INTO kullanicilar (ad_soyad, eposta, sifre_hash) VALUES (?,?,?)")
                    ->execute([$ad_soyad, $eposta, password_hash($sifre, PASSWORD_DEFAULT)]);
                $mesaj = '✅ "' . htmlspecialchars($ad_soyad) . '" eklendi!';
            }
        } catch (Exception $e) { $hata = '❌ ' . $e->getMessage(); }
    } else { $hata = '❌ Lütfen tüm alanları doldurun.'; }
    $aktif_sekme = 'kullanicilar';
}

// ── Kullanıcı Sil ──
if (isset($_POST['kullanici_sil'])) {
    $uid = (int)($_POST['kullanici_id'] ?? 0);
    if ($uid > 0) {
        try {
            $pdo->prepare("DELETE FROM satinalma WHERE kullanici_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM kullanicilar WHERE id = ?")->execute([$uid]);
            $mesaj = '🗑️ Kullanıcı başarıyla silindi.';
        } catch (Exception $e) { $hata = '❌ ' . $e->getMessage(); }
    }
    $aktif_sekme = 'kullanicilar';
}

// ── Çıkış ──
if (isset($_POST['admin_cikis'])) {
    unset($_SESSION['admin_giris']);
    header('Location: admin_giris.php');
    exit;
}

// ── Verileri Çek ──
$kurslar = [];
try {
    $kurslar = $pdo->query("
        SELECT k.*, COUNT(s.id) AS satin_sayisi
        FROM kurslar k LEFT JOIN satinalma s ON s.kurs_id = k.id
        GROUP BY k.id ORDER BY k.id DESC
    ")->fetchAll();
} catch (Exception $e) {}

if ($duzenle_id > 0) {
    foreach ($kurslar as $k) {
        if ((int)$k['id'] === $duzenle_id) { $duzenle_kurs = $k; break; }
    }
    $aktif_sekme = 'kurslar';
}

$kullanicilar = [];
try {
    $kullanicilar = $pdo->query("
        SELECT u.*, COUNT(s.id) AS satin_sayisi
        FROM kullanicilar u LEFT JOIN satinalma s ON s.kullanici_id = u.id
        GROUP BY u.id ORDER BY u.kayit_tarihi DESC
    ")->fetchAll();
} catch (Exception $e) {}

$toplam_kurs      = count($kurslar);
$toplam_kullanici = count($kullanicilar);
$toplam_satinalma = 0;
try { $toplam_satinalma = (int)$pdo->query("SELECT COUNT(*) FROM satinalma")->fetchColumn(); } catch (Exception $e) {}

$seviye_secenekleri   = ['Başlangıç', 'Orta', 'İleri'];
$kategori_secenekleri = ['Programlama', 'Tasarım', 'Pazarlama', 'Kişisel Gelişim', 'Dil', 'Matematik', 'Müzik', 'Diğer'];
$ikon_secenekleri     = ['📚','💻','🎨','📊','🎯','🔬','🎵','🌍','✏️','🧠','🚀','💡'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Paneli – Mindly</title>
  <link rel="stylesheet" href="admin.css">
  <style>
    /* ── Dosya Yükleme ── */
    .file-label {
      display: flex; align-items: center; gap: 8px;
      padding: .55rem 1rem; border-radius: 8px; cursor: pointer;
      border: 1.5px dashed #c0bfd0; background: #f7f6fc;
      font-size: .83rem; color: #555; font-weight: 500;
      transition: border-color .18s, background .18s;
    }
    .file-label:hover { border-color: #7c3aed; background: #f3f0ff; color: #7c3aed; }
    .file-label input[type="file"] { display: none; }
    .dosya-adi {
      margin-top: 5px; font-size: .75rem; color: #7c3aed;
      font-weight: 500; min-height: 1rem; padding-left: 2px;
    }
    .video-mevcut {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
      padding: .45rem .8rem; border-radius: 8px;
      background: #f0fdf4; border: 1px solid #bbf7d0;
      font-size: .8rem; color: #15803d; font-weight: 500;
      margin-bottom: 2px;
    }
    /* ── İptal Butonu ── */
    .btn-iptal {
      display: inline-flex; align-items: center; justify-content: center;
      padding: .6rem 1.1rem; border-radius: 8px;
      background: #f1f0f5; color: #666;
      border: 1px solid #ddd; font-size: .85rem; font-weight: 500;
      text-decoration: none; transition: background .15s, color .15s;
      white-space: nowrap;
    }
    .btn-iptal:hover { background: #e8e6f0; color: #333; }
  </style>
</head>
<body class="panel-page">

<aside class="sidebar">
  <div class="logo-wrap">
    <a href="anasayfa.php" class="logo">MINDLY</a>
    <p class="slogan">learn · grow · shine</p>
  </div>
  <span class="admin-badge">⚙️ Admin Paneli</span>
  <nav>
    <p class="nav-label">Yönetim</p>
    <a href="?sekme=kurslar"     class="nav-item <?= $aktif_sekme==='kurslar'     ? 'active' : '' ?>"><span>📋</span> Kurs Yönetimi</a>
    <a href="?sekme=kullanicilar" class="nav-item <?= $aktif_sekme==='kullanicilar' ? 'active' : '' ?>"><span>👥</span> Kullanıcılar</a>
  </nav>
  <div class="sidebar-bottom">
    <form method="POST">
      <button type="submit" name="admin_cikis" class="btn-logout">🚪 Çıkış Yap</button>
    </form>
  </div>
</aside>

<main class="main">

  <div class="page-head">
    <div class="page-title">Yönetim <span>Paneli</span></div>
    <div class="page-sub">
      <?= $aktif_sekme === 'kurslar' ? 'Kurs ekleyebilir veya mevcut kursları kaldırabilirsiniz.' : 'Kullanıcı hesaplarını yönetin.' ?>
    </div>
  </div>

  <?php if ($mesaj): ?>
    <div class="alert alert-success"><?= $mesaj ?></div>
  <?php endif; ?>
  <?php if ($hata): ?>
    <div class="alert alert-error"><?= $hata ?></div>
  <?php endif; ?>

  <!-- İSTATİSTİKLER -->
  <div class="stats-row">
    <div class="stat-card purple">
      <div class="stat-label">Toplam Kurs</div>
      <div class="stat-value"><?= $toplam_kurs ?></div>
      <div class="stat-sub">Sistemde kayıtlı</div>
    </div>
    <div class="stat-card pink">
      <div class="stat-label">Kullanıcı</div>
      <div class="stat-value"><?= $toplam_kullanici ?></div>
      <div class="stat-sub">Kayıtlı üye</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Satın Alma</div>
      <div class="stat-value"><?= $toplam_satinalma ?></div>
      <div class="stat-sub">Toplam işlem</div>
    </div>
  </div>

  <!-- SEKMELER -->
  <div class="tabs">
    <a href="?sekme=kurslar"      class="tab-btn <?= $aktif_sekme==='kurslar'      ? 'active' : '' ?>">📋 Kurs Yönetimi</a>
    <a href="?sekme=kullanicilar" class="tab-btn <?= $aktif_sekme==='kullanicilar' ? 'active' : '' ?>">👥 Kullanıcı Yönetimi</a>
  </div>

  <!-- ══ KURSLAR SEKMESİ ══ -->
  <?php if ($aktif_sekme === 'kurslar'): ?>
  <div class="layout-grid">

    <div class="form-card">
      <?php if ($duzenle_kurs): ?>
        <div class="card-title">✏️ Kursu Düzenle</div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="kurs_id" value="<?= $duzenle_kurs['id'] ?>">
          <div class="field">
            <label>Kurs Başlığı <span class="required-star">*</span></label>
            <input type="text" name="baslik" value="<?= htmlspecialchars($duzenle_kurs['baslik']) ?>" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Kategori <span class="required-star">*</span></label>
              <select name="kategori" required>
                <option value="">Seç...</option>
                <?php foreach ($kategori_secenekleri as $kat): ?>
                  <option value="<?= $kat ?>" <?= $kat===$duzenle_kurs['kategori'] ? 'selected' : '' ?>><?= $kat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Seviye <span class="required-star">*</span></label>
              <select name="seviye" required>
                <option value="">Seç...</option>
                <?php foreach ($seviye_secenekleri as $s): ?>
                  <option value="<?= $s ?>" <?= $s===$duzenle_kurs['seviye'] ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Ders Sayısı <span class="required-star">*</span></label>
              <input type="number" name="ders_sayisi" min="1" value="<?= (int)$duzenle_kurs['ders_sayisi'] ?>" required>
            </div>
            <div class="field">
              <label>Fiyat (₺)</label>
              <input type="number" name="fiyat" min="0" step="0.01" value="<?= htmlspecialchars($duzenle_kurs['fiyat']) ?>">
            </div>
          </div>
          <div class="field">
            <label>Açıklama</label>
            <textarea name="aciklama"><?= htmlspecialchars($duzenle_kurs['aciklama'] ?? '') ?></textarea>
          </div>
          <div class="field">
            <label>Video Dosyası <span style="font-size:.72rem;color:#aaa;font-weight:400">(MP4, WebM, MOV — maks. 500 MB)</span></label>
            <?php if (!empty($duzenle_kurs['video_url'])): ?>
              <div class="video-mevcut">
                <span>✅ Mevcut video var</span>
                <span style="font-size:.72rem;color:#aaa"><?= htmlspecialchars(basename($duzenle_kurs['video_url'])) ?></span>
              </div>
              <label class="file-label" style="margin-top:6px">
                <input type="file" name="video_dosya" accept="video/mp4,video/webm,video/ogg,video/quicktime" onchange="dosyaSecildi(this,'duzenle-dosya-adi')">
                <span>📁 Videoyu Değiştir (opsiyonel)</span>
              </label>
            <?php else: ?>
              <label class="file-label">
                <input type="file" name="video_dosya" accept="video/mp4,video/webm,video/ogg,video/quicktime" onchange="dosyaSecildi(this,'duzenle-dosya-adi')">
                <span>📁 Video Seç</span>
              </label>
            <?php endif; ?>
            <div class="dosya-adi" id="duzenle-dosya-adi"></div>
          </div>
          <div class="field">
            <label>İkon</label>
            <input type="hidden" name="ikon" id="secilenIkon" value="<?= htmlspecialchars($duzenle_kurs['ikon']) ?>">
            <div class="ikon-grid">
              <?php foreach ($ikon_secenekleri as $ikon): ?>
                <button type="button" class="ikon-btn <?= $ikon===$duzenle_kurs['ikon'] ? 'selected' : '' ?>"
                        onclick="ikonSec(this,'<?= $ikon ?>')"><?= $ikon ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex;gap:.6rem;margin-top:.5rem">
            <button type="submit" name="kurs_guncelle" class="btn-ekle" style="flex:1">💾 Güncelle</button>
            <a href="?sekme=kurslar" class="btn-iptal">✕ İptal</a>
          </div>
        </form>
      <?php else: ?>
        <div class="card-title">➕ Yeni Kurs Ekle</div>
        <form method="POST" enctype="multipart/form-data">
          <div class="field">
            <label>Kurs Başlığı <span class="required-star">*</span></label>
            <input type="text" name="baslik" placeholder="Örn: Python ile Web Geliştirme" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Kategori <span class="required-star">*</span></label>
              <select name="kategori" required>
                <option value="">Seç...</option>
                <?php foreach ($kategori_secenekleri as $kat): ?>
                  <option value="<?= $kat ?>"><?= $kat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Seviye <span class="required-star">*</span></label>
              <select name="seviye" required>
                <option value="">Seç...</option>
                <?php foreach ($seviye_secenekleri as $s): ?>
                  <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Ders Sayısı <span class="required-star">*</span></label>
              <input type="number" name="ders_sayisi" min="1" placeholder="24" required>
            </div>
            <div class="field">
              <label>Fiyat (₺)</label>
              <input type="number" name="fiyat" min="0" step="0.01" placeholder="299.00">
            </div>
          </div>
          <div class="field">
            <label>Açıklama</label>
            <textarea name="aciklama" placeholder="Kurs hakkında kısa bir açıklama..."></textarea>
          </div>
          <div class="field">
            <label>Video Dosyası <span style="font-size:.72rem;color:#aaa;font-weight:400">(MP4, WebM, MOV — maks. 500 MB)</span></label>
            <label class="file-label">
              <input type="file" name="video_dosya" accept="video/mp4,video/webm,video/ogg,video/quicktime" onchange="dosyaSecildi(this,'ekle-dosya-adi')">
              <span>📁 Video Seç</span>
            </label>
            <div class="dosya-adi" id="ekle-dosya-adi"></div>
          </div>
          <div class="field">
            <label>İkon</label>
            <input type="hidden" name="ikon" id="secilenIkon" value="📚">
            <div class="ikon-grid">
              <?php foreach ($ikon_secenekleri as $ikon): ?>
                <button type="button" class="ikon-btn <?= $ikon==='📚' ? 'selected' : '' ?>"
                        onclick="ikonSec(this,'<?= $ikon ?>')"><?= $ikon ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="submit" name="kurs_ekle" class="btn-ekle">+ Kursu Ekle</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="table-card">
      <div class="table-head">
        <div class="card-title" style="margin:0">📋 Mevcut Kurslar</div>
        <span class="item-count"><?= $toplam_kurs ?> kurs</span>
      </div>
      <div class="table-scroll">
        <?php if (empty($kurslar)): ?>
          <div class="empty-table">Henüz hiç kurs eklenmemiş.</div>
        <?php else: ?>
          <table>
            <thead>
              <tr><th></th><th>Kurs</th><th>Seviye</th><th>Ders</th><th>Satış</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($kurslar as $k):
                $sc = match($k['seviye']) { 'Başlangıç'=>'baslangic','Orta'=>'orta','İleri'=>'ileri',default=>'orta' };
              ?>
              <tr>
                <td class="td-ikon"><?= htmlspecialchars($k['ikon']) ?></td>
                <td class="td-baslik">
                  <?= htmlspecialchars($k['baslik']) ?>
                  <small><?= htmlspecialchars($k['kategori']) ?></small>
                </td>
                <td><span class="seviye-badge <?= $sc ?>"><?= htmlspecialchars($k['seviye']) ?></span></td>
                <td><?= (int)$k['ders_sayisi'] ?></td>
                <td><?= (int)$k['satin_sayisi'] ?></td>
                <td style="display:flex;gap:.35rem;align-items:center">
                  <a href="?sekme=kurslar&duzenle=<?= $k['id'] ?>" class="btn-duzenle">✏️ Düzenle</a>
                  <form method="POST" onsubmit="return confirm('\"<?= addslashes($k['baslik']) ?>\" kursunu silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz.')">
                    <input type="hidden" name="kurs_id" value="<?= $k['id'] ?>">
                    <button type="submit" name="kurs_sil" class="btn-sil">🗑 Sil</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endif; ?>

  <!-- ══ KULLANICILAR SEKMESİ ══ -->
  <?php if ($aktif_sekme === 'kullanicilar'): ?>
  <div class="layout-grid">

    <div class="form-card">
      <div class="card-title">➕ Yeni Kullanıcı Ekle</div>
      <form method="POST">
        <div class="field">
          <label>Ad Soyad <span class="required-star">*</span></label>
          <input type="text" name="ad_soyad" placeholder="Örn: Ahmet Yılmaz" required>
        </div>
        <div class="field">
          <label>E-posta <span class="required-star">*</span></label>
          <input type="email" name="eposta" placeholder="ornek@mail.com" required>
        </div>
        <div class="field">
          <label>Şifre <span class="required-star">*</span></label>
          <input type="password" name="sifre" placeholder="En az 6 karakter" required>
        </div>
        <button type="submit" name="kullanici_ekle" class="btn-ekle">+ Kullanıcı Ekle</button>
      </form>
    </div>

    <div class="table-card">
      <div class="table-head">
        <div class="card-title" style="margin:0">👥 Kayıtlı Kullanıcılar</div>
        <span class="item-count"><?= $toplam_kullanici ?> kullanıcı</span>
      </div>
      <div class="table-scroll">
        <?php if (empty($kullanicilar)): ?>
          <div class="empty-table">Henüz kayıtlı kullanıcı bulunmuyor.</div>
        <?php else: ?>
          <table>
            <thead>
              <tr><th>Kullanıcı</th><th>E-posta</th><th>Kayıt</th><th>Kurs</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($kullanicilar as $u):
                $ilk = mb_strtoupper(mb_substr($u['ad_soyad'], 0, 1, 'UTF-8'), 'UTF-8');
                $tarih = date('d.m.Y', strtotime($u['kayit_tarihi']));
              ?>
              <tr>
                <td>
                  <div class="td-user">
                    <div class="user-avatar"><?= $ilk ?></div>
                    <span><?= htmlspecialchars($u['ad_soyad']) ?></span>
                  </div>
                </td>
                <td style="color:var(--muted);font-size:.79rem"><?= htmlspecialchars($u['eposta']) ?></td>
                <td style="color:var(--muted);font-size:.79rem"><?= $tarih ?></td>
                <td><?= (int)$u['satin_sayisi'] ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('\"<?= addslashes($u['ad_soyad']) ?>\" kullanıcısını silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz.')">
                    <input type="hidden" name="kullanici_id" value="<?= $u['id'] ?>">
                    <button type="submit" name="kullanici_sil" class="btn-sil">🗑 Sil</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endif; ?>

</main>

<style>
  .btn-duzenle {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    padding: .32rem .7rem;
    border-radius: .4rem;
    background: #f0f4ff;
    color: #4f46e5;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #c7d2fe;
    transition: background .15s;
    white-space: nowrap;
  }
  .btn-duzenle:hover { background: #e0e7ff; }
</style>
<script>
  function ikonSec(el, ikon) {
    document.querySelectorAll('.ikon-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('secilenIkon').value = ikon;
  }

  function dosyaSecildi(input, hedefId) {
    const el = document.getElementById(hedefId);
    if (!el) return;
    if (input.files && input.files[0]) {
      const f = input.files[0];
      const mb = (f.size / 1024 / 1024).toFixed(1);
      el.textContent = '✔ ' + f.name + ' (' + mb + ' MB)';
    } else {
      el.textContent = '';
    }
  }
</script>
</body>
</html>