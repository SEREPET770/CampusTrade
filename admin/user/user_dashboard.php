<?php
session_start();

// Login sesuai role
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
  // FIX: path asli salah (../admin/auth/login.php), yang benar:
  header("Location: ../auth/login.php");
  exit();
}

require_once "../config/Koneksi.php";

/** @var mysqli $koneksi */
$id_user = $_SESSION['id_user'];
$nama_user = $_SESSION['nama'];
$email_user = $_SESSION['email'];

// 1. Query Hitung Statistik Produk User
// Total Produk Saya
$query_total = "SELECT COUNT(*) as total FROM produk WHERE id_user = ?";
$stmt = $koneksi->prepare($query_total);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$count_total = $stmt->get_result()->fetch_assoc()['total'];

// Produk Aktif (Tersedia & Aktif)
$query_aktif = "SELECT COUNT(*) as total FROM produk WHERE id_user = ? AND status_produk = 'tersedia' AND status_aktif = 1";
$stmt = $koneksi->prepare($query_aktif);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$count_aktif = $stmt->get_result()->fetch_assoc()['total'];

// Produk Ditolak / Dalam Review (Berdasarkan status_produk 'ditolak')
$query_ditolak = "SELECT COUNT(*) as total FROM produk WHERE id_user = ? AND status_produk = 'ditolak'";
$stmt = $koneksi->prepare($query_ditolak);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$count_ditolak = $stmt->get_result()->fetch_assoc()['total'];


// 2. Query Ambil Katalog Produk Terkini (Untuk Grid Bawah)
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'semua';

// FIX: status_tayang = 1 (integer) tidak valid, kolom adalah ENUM('belum_bayar','aktif')
// Diganti: filter hanya berdasarkan status_produk = 'tersedia'
$query_katalog = "SELECT p.*, k.nama_kategori, l.nama_lokasi,
                  (SELECT image_path FROM gambar_produk WHERE id_produk = p.id_produk LIMIT 1) as image_path 
                  FROM produk p 
                  JOIN kategori_barang k ON p.id_kategori = k.id_kategori 
                  LEFT JOIN lokasi l ON p.id_lokasi = l.id_lokasi
                  WHERE 1=1 AND p.status_produk = 'tersedia'";

// Jika user memilih kategori tertentu (bukan 'semua')
if ($filter_kategori !== 'semua' && !empty($filter_kategori)) {
  $query_katalog .= " AND p.id_kategori = ?";
  $query_katalog .= " ORDER BY p.created_at DESC LIMIT 10";
  $stmt_katalog = $koneksi->prepare($query_katalog);
  $kat_id = intval($filter_kategori);
  $stmt_katalog->bind_param("i", $kat_id);
  $stmt_katalog->execute();
  $result_katalog = $stmt_katalog->get_result();
} else {
  // Jika memilih semua kategori
  $query_katalog .= " ORDER BY p.created_at DESC LIMIT 10";
  $result_katalog = $koneksi->query($query_katalog);
}

$query_kategori = "SELECT * FROM kategori_barang ORDER BY nama_kategori ASC";
$result_kategori = $koneksi->query($query_kategori);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard User — Campus Trade</title>
  <link rel="stylesheet" href="../assets/css/user_dashboard.css">
</head>

<body>

  <header class="navbar">
    <div class="nav-left">
      <a href="user_dashboard.php" class="logo">CampusTrade</a>
      <nav class="nav-links">
        <a href="user_dashboard.php" class="active">Beranda</a>
        <a href="user_produk.php">Produk</a>
        <a href="../user/tambah_produk.php">Tambah Produk</a>
      </nav>
    </div>

    <form action="user_produk.php" method="GET" class="nav-search-form">
      <input type="text" name="search" placeholder="Cari produk di CampusTrade..." value="<?= isset($search) ? htmlspecialchars($search) : '' ?>">
      <button type="submit" class="nav-search-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>

    <div class="profile-pill-wrapper">
      <div id="profilePillBtn" class="profile-pill">
        <span><?= htmlspecialchars($nama_user) ?></span>
      </div>

      <div id="profileDropdown" class="profile-dropdown-card">
        <div class="dropdown-header">
          <div class="avatar-large"></div>
          <div class="user-info-text">
            <h3><?= htmlspecialchars($nama_user) ?></h3>
            <p><?= htmlspecialchars($email_user) ?></p>
          </div>
        </div>

        <a href="profil.php" class="btn-lihat-profil">Lihat Profil</a>

        <!-- DIUBAH: menu lama diganti dua seksi (Pembeli & Penjual) -->
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

  <main class="container">

    <div class="hero-banner" style="background-image: url('../assets/images/BarangBekas1.jpg');">
      <div class="hero-overlay-card">
        <h2>Barang Bekas Layak Pakai</h2>
        <p>Jual Barang Tidak Terpakai Mu Atau Temukan Barang Yang Berkualitas dengan Harga Terbaik</p>
        <a href="../seller/tambah_produk.php" class="btn-jual-sekarang">Jual Barang Sekarang</a>
        <a href="#" class="link-pelajari">Pelajari cara kerjanya</a>
      </div>
    </div>

    <section class="katalog-section">
      <div class="katalog-header">
        <h2 class="section-title">Kategori Populer</h2>
        <form method="GET" action="user_dashboard.php" class="filter-kategori">
          <label for="kategoriDropdown">Filter:</label>
          <select name="kategori" id="kategoriDropdown" class="kategori-dropdown" onchange="this.form.submit()">
            <option value="semua" <?= $filter_kategori == 'semua' ? 'selected' : '' ?>>Semua Kategori</option>
            <?php if ($result_kategori && $result_kategori->num_rows > 0): ?>
              <?php while ($kat = $result_kategori->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($kat['id_kategori']) ?>" <?= $filter_kategori == $kat['id_kategori'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($kat['nama_kategori']) ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </form>
      </div>
      <div class="product-grid"> <?php if ($result_katalog && $result_katalog->num_rows > 0): ?>
          <?php while ($row = $result_katalog->fetch_assoc()):
                                      $foto = !empty($row['image_path']) ? "../uploads/produk/" . htmlspecialchars($row['image_path']) : "../assets/img/no-image.png";
          ?>

            <a href="detail_produk.php?id_produk=<?= $row['id_produk'] ?>" class="product-card" style="text-decoration: none; color: inherit;">

              <div class="card-image">
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($row['nama_produk']) ?>">
              </div>

              <div class="card-content">
                <h3 class="product-name"><?= htmlspecialchars($row['nama_produk']) ?></h3>
                <p class="product-price">Rp <?= number_format($row['harga'], 0, ',', '.') ?></p>
                <div class="product-meta">
                  <span class="location">📍 <?= !empty($row['nama_lokasi']) ? htmlspecialchars($row['nama_lokasi']) : 'Tidak Diketahui' ?></span>
                  <span class="date">Ditambahkan <?= isset($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : 'Baru saja' ?></span>
                </div>
              </div>

            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #888;">
            <p style="font-size: 16px; font-weight: 500;">Belum ada produk yang tersedia di kategori ini.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <script src="../assets/js/user_dashboard.js"></script>
</body>

</html>