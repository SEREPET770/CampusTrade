<?php
session_start();

require_once "config/Koneksi.php";

/** @var mysqli $koneksi */

// Status login (landing page tetap bisa diakses tanpa login,
// tapi navbar & CTA menyesuaikan jika pengunjung sudah login)
$is_login   = isset($_SESSION['id_user']) && $_SESSION['role'] === 'user';
$nama_user  = $_SESSION['nama'] ?? '';
$email_user = $_SESSION['email'] ?? '';

// 1. Query Kategori (untuk section "Kategori Populer")
$query_kategori = "SELECT * FROM kategori_barang ORDER BY nama_kategori ASC";
$result_kategori = $koneksi->query($query_kategori);

$kategori_icons = [
  'Elektronik' => '💻',
  'Fashion' => '👗',
  'Buku' => '📚',
  'Alat Tulis' => '✏️',
];

// 2. Query Produk Terbaru (maksimal 8), sama seperti pola user_dashboard.php
$query_terbaru = "SELECT p.*, k.nama_kategori, l.nama_lokasi,
                  (SELECT image_path FROM gambar_produk WHERE id_produk = p.id_produk LIMIT 1) as image_path
                  FROM produk p
                  JOIN kategori_barang k ON p.id_kategori = k.id_kategori
                  LEFT JOIN lokasi l ON p.id_lokasi = l.id_lokasi
                  WHERE p.status_produk = 'tersedia'
                  ORDER BY p.created_at DESC
                  LIMIT 8";
$result_terbaru = $koneksi->query($query_terbaru);

// CTA menyesuaikan status login
$cta_jual_link = $is_login ? "user/tambah_produk.php" : "auth/register.php";
$cta_jual_text = $is_login ? "Jual Barang Sekarang" : "Daftar Sekarang";
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CampusTrade — Marketplace Barang Bekas Mahasiswa</title>
  <link rel="stylesheet" href="assets/css/user_dashboard.css">
  <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body>

  <header class="navbar">
    <div class="nav-left">
      <a href="index.php" class="logo">CampusTrade</a>
      <nav class="nav-links" id="navLinks">
        <a href="index.php" class="active">Beranda</a>
        <a href="produk_publik.php">Produk</a>
        <a href="#tentang-kami">Tentang Kami</a>
        <?php if (!$is_login): ?>
          <a href="auth/login.php" class="nav-guest-link">Login</a>
          <a href="auth/register.php" class="nav-guest-link">Daftar</a>
        <?php endif; ?>
      </nav>
    </div>

    <form action="produk_publik.php" method="GET" class="nav-search-form">
      <input type="text" name="search" placeholder="Cari produk di CampusTrade...">
      <button type="submit" class="nav-search-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>

    <?php if ($is_login): ?>
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

          <a href="#" class="btn-lihat-profil">Lihat Profil</a>

          <div class="dropdown-section-label">Pembeli</div>
          <ul class="dropdown-menu-list">
            <li><a href="user/pesanan_saya.php">Pesanan Saya</a></li>
          </ul>

          <div class="dropdown-section-label">Penjual</div>
          <ul class="dropdown-menu-list">
            <li><a href="seller/dashboard.php">Dashboard Toko</a></li>
            <li><a href="seller/produk.php">Produk Saya</a></li>
            <li><a href="user/tambah_produk.php">Tambah Produk</a></li>
            <li><a href="seller/pesanan_masuk.php">Pesanan Masuk</a></li>
            <li><a href="seller/pembayaran_masuk.php">Verifikasi Pembayaran</a></li>
          </ul>

          <hr class="dropdown-divider">
          <a href="auth/logout.php" class="btn-exit">Logout</a>
        </div>
      </div>
    <?php else: ?>
      <div class="nav-guest-actions">
        <a href="auth/login.php" class="btn-nav-login">Login</a>
        <a href="auth/register.php" class="btn-nav-daftar">Daftar</a>
      </div>
    <?php endif; ?>

    <button class="nav-toggle" id="navToggle" aria-label="Buka menu">
      <span></span><span></span><span></span>
    </button>
  </header>

  <main class="container">

    <!-- HERO SECTION -->
    <div class="hero-banner" style="background-image: url('assets/images/BarangBekas1.jpg');">
      <div class="hero-overlay-card">
        <h2>Jual dan Temukan Barang Bekas Berkualitas untuk Mahasiswa</h2>
        <p>CampusTrade merupakan marketplace barang bekas mahasiswa yang mempertemukan penjual dan pembeli dalam satu platform yang terverifikasi sebagai Mahasiswa</p>
        <a href="produk_publik.php" class="btn-jual-sekarang">Lihat Produk</a>
        <a href="<?= $cta_jual_link ?>" class="link-pelajari"><?= $cta_jual_text ?></a>
      </div>
    </div>

    <!-- KATEGORI POPULER -->
    <section class="katalog-section">
      <div class="katalog-header">
        <h2 class="section-title">Kategori Populer</h2>
      </div>

      <div class="kategori-grid">
        <?php if ($result_kategori && $result_kategori->num_rows > 0): ?>
          <?php while ($kat = $result_kategori->fetch_assoc()):
            $icon = $kategori_icons[$kat['nama_kategori']] ?? '🏷️';
          ?>
            <a href="produk_publik.php?kategori=<?= $kat['id_kategori'] ?>" class="kategori-card">
              <div class="kategori-icon"><?= htmlspecialchars($icon) ?></div>
              <span class="kategori-name"><?= htmlspecialchars($kat['nama_kategori']) ?></span>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="kategori-empty">Belum ada kategori tersedia.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- PRODUK TERBARU -->
    <section class="katalog-section">
      <div class="katalog-header">
        <h2 class="section-title">Produk Terbaru</h2>
      </div>

      <div class="product-grid">
        <?php if ($result_terbaru && $result_terbaru->num_rows > 0): ?>
          <?php while ($row = $result_terbaru->fetch_assoc()):
            $foto = !empty($row['image_path']) ? "uploads/produk/" . htmlspecialchars($row['image_path']) : "assets/img/no-image.png";
          ?>
            <a href="detail_produk_publik.php?id_produk=<?= $row['id_produk'] ?>" class="product-card" style="text-decoration: none; color: inherit;">
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
            <p style="font-size: 16px; font-weight: 500;">Belum ada produk yang tersedia saat ini.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- TENTANG KAMI -->
    <section class="info-section" id="tentang-kami">
      <h2 class="section-title" style="text-align: center;">Tentang Kami</h2>
      <br>
      <p class="tentang-text">
        CampusTrade adalah marketplace barang bekas khusus mahasiswa. Kami mempertemukan mahasiswa yang ingin
        menjual barang tidak terpakai dengan mahasiswa lain yang sedang mencari barang berkualitas dengan harga
        terjangkau. Semua transaksi dilakukan langsung antar sesama mahasiswa dalam satu platform yang aman,
        transparan, dan mudah digunakan.
      </p>
    </section>

    <!-- CARA KERJA -->
    <section class="info-section" id="cara-kerja">
      <h2 class="section-title" style="text-align: center;">Cara Kerja</h2><br>
      <div class="cara-kerja-grid">
        <div class="cara-kerja-step">
          <div class="cara-kerja-icon">📝</div>
          <h3>1. Daftar Akun</h3>
          <p>Buat akun menggunakan data mahasiswa kamu, gratis dan cepat.</p>
        </div>
        <div class="cara-kerja-arrow">&rarr;</div>
        <div class="cara-kerja-step">
          <div class="cara-kerja-icon">📤</div>
          <h3>2. Upload Produk</h3>
          <p>Unggah foto dan detail barang bekas yang ingin kamu jual.</p>
        </div>
        <div class="cara-kerja-arrow">&rarr;</div>
        <div class="cara-kerja-step">
          <div class="cara-kerja-icon">🤝</div>
          <h3>3. Temukan Pembeli</h3>
          <p>Pembeli akan menghubungi kamu langsung melalui chat.</p>
        </div>
        <div class="cara-kerja-arrow">&rarr;</div>
        <div class="cara-kerja-step">
          <div class="cara-kerja-icon">✅</div>
          <h3>4. Selesaikan Transaksi</h3>
          <p>Sepakati harga dan selesaikan transaksi dengan aman.</p>
        </div>
      </div>
    </section>

    <!-- KENAPA CAMPUSTRADE -->
    <section class="info-section">
      <h2 class="section-title" style="text-align: center;">Kenapa CampusTrade?</h2><br>
      <div class="keunggulan-grid">
        <div class="keunggulan-card">
          <div class="keunggulan-icon">🎓</div>
          <h3>Khusus Mahasiswa</h3>
          <p>Platform dirancang khusus untuk kebutuhan jual beli antar mahasiswa.</p>
        </div>
        <div class="keunggulan-card">
          <div class="keunggulan-icon">📦</div>
          <h3>Barang Bekas Berkualitas</h3>
          <p>Barang yang dijual telah melalui proses verifikasi sebelum tayang.</p>
        </div>
        <div class="keunggulan-card">
          <div class="keunggulan-icon">💰</div>
          <h3>Harga Terjangkau</h3>
          <p>Dapatkan barang berkualitas dengan harga bersahabat untuk kantong mahasiswa.</p>
        </div>
      </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="cta-section">
      <h2>Mulai Jual dan Temukan Barang Bekas Berkualitas Hari Ini</h2>
      <div class="cta-buttons">
        <?php if (!$is_login): ?>
          <a href="auth/register.php" class="btn-cta-primary">Daftar</a>
          <a href="auth/login.php" class="btn-cta-secondary">Login</a>
        <?php else: ?>
          <a href="user/tambah_produk.php" class="btn-cta-primary">Jual Barang</a>
          <a href="produk_publik.php" class="btn-cta-secondary">Lihat Produk</a>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <footer class="landing-footer">
    <div class="footer-content">
      <div class="footer-brand">
        <span class="footer-logo">CampusTrade</span>
        <p>Marketplace barang bekas mahasiswa.</p>
      </div>
      <div class="footer-menu">
        <a href="index.php">Beranda</a>
        <a href="produk_publik.php">Produk</a>
        <a href="#tentang-kami">Tentang Kami</a>
      </div>
      <div class="footer-kontak">
        <h4>Kontak</h4>
        <p>No WA= 085792448847</p>
      </div>
    </div>
    <div class="footer-copyright">
      &copy; <?php echo date('Y'); ?> CampusTrade. Seluruh hak cipta dilindungi.
    </div>
  </footer>

  <script src="assets/js/landing.js"></script>
</body>

</html>