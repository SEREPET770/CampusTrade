<?php
date_default_timezone_set('Asia/Jakarta');
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$nama_login = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Mahasiswa';
$id_user_login = $_SESSION['id_user'] ?? 0;
$email_user = $_SESSION['email'] ?? '';
$nama_login = $_SESSION['nama'] ?? 'Mahasiswa';
require_once "../config/Koneksi.php";

// Fungsi Helper
function formatRupiah($angka)
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}
function formatTanggal($tanggal)
{
  return date('d M Y', strtotime($tanggal));
}

function waktuLalu($datetime)
{
  if (!$datetime) return 'Belum pernah online';

  $waktu_online = strtotime($datetime);
  $sekarang = time();
  $selisih = $sekarang - $waktu_online;

  if ($selisih < 60) {
    return 'Online baru saja';
  } elseif ($selisih < 3600) {
    $menit = floor($selisih / 60);
    return "Aktif $menit menit lalu";
  } elseif ($selisih < 86400) {
    $jam = floor($selisih / 3600);
    return "Aktif $jam jam lalu";
  } elseif ($selisih < 172800) {
    return 'Aktif kemarin';
  } else {
    $hari = floor($selisih / 86400);
    return "Aktif $hari hari lalu";
  }
}
// 1. Tangkap ID Produk dari URL
$id_produk = isset($_GET['id_produk']) ? intval($_GET['id_produk']) : 0;

if ($id_produk === 0) {
  echo "<script>alert('Produk tidak ditemukan!'); window.location.href='user_produk.php';</script>";
  exit();
}

/** @var mysqli $koneksi */

// 2. Query Detail Produk, Kategori, dan Penjual
$query_produk = "
    SELECT
    p.*,
    k.nama_kategori,
    u.nama AS nama_penjual,
    u.no_whatsapp,
    u.terakhir_online,
    u.id_user AS id_penjual
FROM produk p
JOIN kategori_barang k
ON p.id_kategori = k.id_kategori
JOIN users u
ON p.id_user = u.id_user
WHERE p.id_produk = ?
";
$stmt = $koneksi->prepare($query_produk);
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Cek dulu apakah produk ditemukan SEBELUM mengakses field-nya di bawah.
if (!$produk) {
  echo "<script>alert('Produk tidak ditemukan!'); window.location.href='user_produk.php';</script>";
  exit();
}

/* STATUS TOMBOL PEMBELIAN */

$bolehCheckout = false;
$pesanStatus = '';

if ($id_user_login == 0) {

  $pesanStatus = 'Silakan login terlebih dahulu.';
} elseif ($id_user_login == $produk['id_penjual']) {

  $pesanStatus = 'Produk milik Anda.';
} elseif ($produk['status_produk'] == 'terjual') {

  $pesanStatus = 'Produk telah terjual.';
} elseif ($produk['status_produk'] == 'dipesan') {

  $pesanStatus = 'Produk sedang diproses pembeli lain.';
} elseif ($produk['status_produk'] == 'tersedia') {

  $bolehCheckout = true;
} else {

  $pesanStatus = 'Produk tidak tersedia.';
}

// 3. Query Gambar Produk (Galeri)
$query_gambar = "SELECT image_path FROM gambar_produk WHERE id_produk = ?";
$stmt_gambar = $koneksi->prepare($query_gambar);
$stmt_gambar->bind_param("i", $id_produk);
$stmt_gambar->execute();
$gambar_list = $stmt_gambar->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_gambar->close();

// Gambar default jika kosong
$gambar_utama = !empty($gambar_list) ? "../uploads/produk/" . $gambar_list[0]['image_path'] : "../assets/img/no-image.png";

// 4. Query Produk Terkait (Kategori sama, ID berbeda, maks 4)
$query_terkait = "
    SELECT p.id_produk, p.nama_produk, p.harga, p.created_at,
           (SELECT gp.image_path FROM gambar_produk gp WHERE gp.id_produk = p.id_produk LIMIT 1) as foto
    FROM produk p
    WHERE p.id_kategori = ? AND p.id_produk != ? AND p.status_produk = 'tersedia'
    ORDER BY RAND() LIMIT 4
";
$stmt_terkait = $koneksi->prepare($query_terkait);
$stmt_terkait->bind_param("ii", $produk['id_kategori'], $id_produk);
$stmt_terkait->execute();
$produk_terkait = $stmt_terkait->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_terkait->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($produk['nama_produk']) ?> - CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/produk.css">
  <link rel="stylesheet" href="../assets/css/detail_produk.css">
</head>

<body>

  <header class="navbar">
    <div class="nav-left">
      <a href="user_dashboard.php" class="logo">CampusTrade</a>
      <nav class="nav-links">
        <a href="user_dashboard.php">Beranda</a>
        <a href="user_produk.php" class="active">Produk</a>
        <a href="#">Tentang Kami</a>
      </nav>
    </div>

    <form action="user_produk.php" method="GET" class="nav-search-form">
      <input type="text" name="search" placeholder="Cari produk di CampusTrade...">
      <button type="submit" class="nav-search-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>

    <div class="nav-right">
      <div class="profile-pill-wrapper">
        <div id="profilePillBtn" class="profile-pill">
          <span><?= htmlspecialchars($nama_login) ?></span>
        </div>

        <div id="profileDropdown" class="profile-dropdown-card">
          <div class="dropdown-header">
            <div class="avatar-large"></div>
            <div class="user-info-text">
              <h3><?= htmlspecialchars($nama_login) ?></h3>
              <p><?= htmlspecialchars($email_user) ?></p>
            </div>
          </div>

          <a href="#" class="btn-lihat-profil">Lihat Profil</a>

          <div class="dropdown-section-label">Pembeli</div>
          <ul class="dropdown-menu-list">
            <li><a href="pesanan_saya.php">Pesanan Saya</a></li>
          </ul>

          <div class="dropdown-section-label">Penjual</div>
          <ul class="dropdown-menu-list">
            <li><a href="../seller/dashboard.php">Dashboard Toko</a></li>
            <li><a href="../seller/produk.php">Produk Saya</a></li>
            <li><a href="../user/tambah_produk.php">Tambah Produk</a></li>
            <li><a href="../seller/pesanan_masuk.php">Pesanan Masuk</a></li>
            <li><a href="../seller/pembayaran_masuk.php">Verifikasi Pembayaran</a></li>
          </ul>

          <hr class="dropdown-divider">
          <a href="../auth/logout.php" class="btn-exit">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <main class="container dp-container">

    <nav class="breadcrumb">
      <a href="user_dashboard.php">Beranda</a>
      <span class="separator">&gt;</span>
      <a href="user_produk.php">Produk</a>
      <span class="separator">&gt;</span>
      <a href="user_produk.php?kategori=<?= $produk['id_kategori'] ?>"><?= htmlspecialchars($produk['nama_kategori']) ?></a>
      <span class="separator">&gt;</span>
      <span class="current"><?= htmlspecialchars($produk['nama_produk']) ?></span>
    </nav>

    <div class="dp-layout">

      <div class="dp-gallery">
        <div class="main-image-container">
          <img src="<?= $gambar_utama ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>" id="mainImage">
        </div>
        <?php if (count($gambar_list) > 1): ?>
          <div class="thumbnail-container">
            <?php foreach ($gambar_list as $index => $gb): ?>
              <img src="../uploads/produk/<?= htmlspecialchars($gb['image_path']) ?>"
                class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                alt="Thumbnail"
                onclick="changeImage(this)">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="dp-info">
        <h1 class="dp-title"><?= htmlspecialchars($produk['nama_produk']) ?></h1>
        <h2 class="dp-price"><?= formatRupiah($produk['harga']) ?></h2>

        <div class="dp-meta">
          <div class="meta-item">
            <span class="meta-label">Kondisi</span>
            <span class="meta-value badge-kondisi"><?= htmlspecialchars(ucfirst($produk['kondisi'] ?? 'Baru')) ?></span>
          </div>

          <?php if (strtolower($produk['nama_kategori']) == 'buku'): ?>
            <div class="meta-item">
              <span class="meta-label">Penulis/Penerbit</span>
              <span class="meta-value"><?= htmlspecialchars($produk['penulis_penerbit'] ?? '-') ?></span>
            </div>
          <?php else: ?>
            <div class="meta-item">
              <span class="meta-label">Brand</span>
              <span class="meta-value"><?= htmlspecialchars($produk['brand'] ?? '-') ?></span>
            </div>
          <?php endif; ?>

          <div class="meta-item">
            <span class="meta-label">Tanggal Upload</span>
            <span class="meta-value"><?= formatTanggal($produk['created_at']) ?></span>
          </div>
        </div>

        <div class="dp-description">
          <h3>Kelebihan</h3>
          <p><?= nl2br(htmlspecialchars($produk['kelebihan'] ?? '-')) ?></p>

          <h3 style="margin-top: 15px;">Kekurangan</h3>
          <p><?= nl2br(htmlspecialchars($produk['kekurangan'] ?? '-')) ?></p>
        </div>

        <div class="dp-description">
          <h3>Deskripsi Produk</h3>
          <div class="desc-content">
            <?= nl2br(htmlspecialchars($produk['deskripsi'] ?? 'Tidak ada deskripsi.')) ?>
          </div>
        </div>

        <div class="dp-seller">
          <div class="seller-avatar"></div>
          <div class="seller-details">
            <h4><?= htmlspecialchars($produk['nama_penjual']) ?></h4>
            <p><?= waktuLalu($produk['terakhir_online'] ?? null) ?> • Surabaya</p>
          </div>
        </div>
      </div>

      <div class="dp-action-sidebar">
        <div class="action-card">
          <h3 class="action-card-title">Informasi Transaksi</h3>
          <p class="chat-hint">
            Untuk memastikan kecocokan barang, silakan tanyakan detail kondisi produk atau lakukan negosiasi harga (nego) langsung dengan penjual melalui tombol chat di bawah.
          </p>

          <div class="action-footer">

            <button
              class="btn-chat"
              data-phone="<?= htmlspecialchars($produk['no_whatsapp'] ?? '') ?>"
              onclick="chatPenjual(this)">
              💬 Chat Penjual
            </button>

            <?php if ($bolehCheckout): ?>

              <a href="checkout.php?id_produk=<?= $produk['id_produk'] ?>" class="btn-checkout">Beli Sekarang</a>
            <?php else: ?>
              <button
                class="btn-disabled"
                disabled>
                <?= $pesanStatus ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </main>

  <script src="../assets/js/detail_produk.js"></script>
  <script src="../assets/js/user_dashboard.js"></script>
</body>

</html>