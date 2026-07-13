<?php
session_start();

// Login sesuai role (pola sama seperti user_dashboard.php)
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
  header("Location: ../auth/login.php");
  exit();
}

require_once "../config/Koneksi.php";

/** @var mysqli $koneksi */
$id_user = (int) $_SESSION['id_user'];

// =========================
// HELPER
// =========================
function rupiah(float $angka): string
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

function tanggalIndo(?string $tanggal): string
{
  if (!$tanggal) return '-';
  return date('d F Y', strtotime($tanggal));
}

// =========================
// 1. QUERY DATA DIRI LENGKAP
// =========================
$stmt = $koneksi->prepare("SELECT nama, email, nim, no_whatsapp, alamat, kota, kode_pos, foto_ktm,
                                   status_verifikasi, status_aktif, created_at, terakhir_online
                            FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profil) {
  header("Location: ../auth/login.php");
  exit();
}

$badge_verifikasi = [
  'terverifikasi' => ['text' => 'Terverifikasi', 'class' => 'success'],
  'menunggu'       => ['text' => 'Menunggu Verifikasi', 'class' => 'warning'],
  'ditolak'        => ['text' => 'Ditolak', 'class' => 'danger'],
  'kedaluwarsa'    => ['text' => 'Kedaluwarsa', 'class' => 'secondary'],
];
$bv = $badge_verifikasi[$profil['status_verifikasi']] ?? ['text' => ucfirst($profil['status_verifikasi']), 'class' => 'secondary'];

// =========================
// 2. QUERY PRODUK YANG DIMILIKI SAAT INI (semua status, bukan hanya tersedia)
// =========================
$stmt = $koneksi->prepare("
    SELECT p.id_produk, p.nama_produk, p.harga, p.status_produk, p.created_at,
           l.nama_lokasi,
           (SELECT gp.image_path FROM gambar_produk gp WHERE gp.id_produk = p.id_produk LIMIT 1) as foto
    FROM produk p
    LEFT JOIN lokasi l ON p.id_lokasi = l.id_lokasi
    WHERE p.id_user = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$produk_saya = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$badge_produk = [
  'menunggu' => ['text' => 'Menunggu', 'class' => 'warning'],
  'tersedia' => ['text' => 'Tersedia', 'class' => 'success'],
  'dipesan'  => ['text' => 'Dipesan',  'class' => 'info'],
  'terjual'  => ['text' => 'Terjual',  'class' => 'teal'],
  'ditolak'  => ['text' => 'Ditolak',  'class' => 'danger'],
];

// =========================
// 3. QUERY KEUNTUNGAN (pendapatan terkonfirmasi sebagai penjual)
// Sama seperti query di seller/dashboard.php, tidak dibuat ulang/duplikat logikanya.
// =========================
$stmt = $koneksi->prepare("
    SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE WHEN status_pembayaran = 'dibayar' THEN total_bayar END), 0) AS keuntungan
    FROM transaksi
    WHERE id_penjual = ?
");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_produk_terjual = 0;
foreach ($produk_saya as $p) {
  if ($p['status_produk'] === 'terjual') {
    $total_produk_terjual++;
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Saya — CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/user_dashboard.css">
  <link rel="stylesheet" href="../assets/css/profil.css">
</head>

<body>

  <header class="navbar">
    <div class="nav-left">
      <a href="user_dashboard.php" class="logo">CampusTrade</a>
      <nav class="nav-links">
        <a href="user_dashboard.php">Beranda</a>
        <a href="user_produk.php">Produk</a>
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

    <div class="profile-pill-wrapper">
      <div id="profilePillBtn" class="profile-pill">
        <span><?= htmlspecialchars($profil['nama']) ?></span>
      </div>

      <div id="profileDropdown" class="profile-dropdown-card">
        <div class="dropdown-header">
          <div class="avatar-large"></div>
          <div class="user-info-text">
            <h3><?= htmlspecialchars($profil['nama']) ?></h3>
            <p><?= htmlspecialchars($profil['email']) ?></p>
          </div>
        </div>

        <a href="profil.php" class="btn-lihat-profil">Lihat Profil</a>

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
  </header>

  <main class="container profil-container">

    <!-- ═══════════════════════════════════════
           HEADER PROFIL
      ════════════════════════════════════════ -->
    <div class="profil-header-card">
      <div class="profil-avatar-lg">
        <?= mb_strtoupper(mb_substr($profil['nama'], 0, 1)) ?>
      </div>
      <div class="profil-header-text">
        <h1><?= htmlspecialchars($profil['nama']) ?></h1>
        <p class="profil-nim">NIM: <?= htmlspecialchars($profil['nim']) ?></p>
        <span class="profil-badge profil-badge-<?= $bv['class'] ?>"><?= $bv['text'] ?></span>
      </div>
    </div>

    <!-- ═══════════════════════════════════════
           SECTION 1: DATA DIRI LENGKAP
      ════════════════════════════════════════ -->
    <section class="profil-section">
      <h2 class="section-title">Data Diri Lengkap</h2>
      <div class="profil-card">
        <table class="profil-info-table">
          <tr>
            <td>Nama Lengkap</td>
            <td>: <strong><?= htmlspecialchars($profil['nama']) ?></strong></td>
          </tr>
          <tr>
            <td>Email</td>
            <td>: <?= htmlspecialchars($profil['email']) ?></td>
          </tr>
          <tr>
            <td>NIM</td>
            <td>: <?= htmlspecialchars($profil['nim']) ?></td>
          </tr>
          <tr>
            <td>No. WhatsApp</td>
            <td>: <?= htmlspecialchars($profil['no_whatsapp']) ?></td>
          </tr>
          <tr>
            <td>Alamat</td>
            <td>: <?= !empty($profil['alamat']) ? nl2br(htmlspecialchars($profil['alamat'])) : '-' ?></td>
          </tr>
          <tr>
            <td>Kota</td>
            <td>: <?= !empty($profil['kota']) ? htmlspecialchars($profil['kota']) : '-' ?></td>
          </tr>
          <tr>
            <td>Kode Pos</td>
            <td>: <?= !empty($profil['kode_pos']) ? htmlspecialchars($profil['kode_pos']) : '-' ?></td>
          </tr>
          <tr>
            <td>Status Verifikasi</td>
            <td>:
              <span class="profil-badge profil-badge-<?= $bv['class'] ?>"><?= $bv['text'] ?></span>
            </td>
          </tr>
          <tr>
            <td>Bergabung Sejak</td>
            <td>: <?= tanggalIndo($profil['created_at']) ?></td>
          </tr>
          <tr>
            <td>Terakhir Online</td>
            <td>: <?= $profil['terakhir_online'] ? tanggalIndo($profil['terakhir_online']) : 'Belum pernah' ?></td>
          </tr>
        </table>

        <?php if (!empty($profil['foto_ktm'])): ?>
          <div class="profil-ktm-box">
            <p class="profil-ktm-label">Kartu Tanda Mahasiswa (KTM)</p>
            <img src="../uploads/ktm/<?= htmlspecialchars($profil['foto_ktm']) ?>"
              alt="Foto KTM" class="profil-ktm-thumb"
              onclick="bukaKTM(this.src)">
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ═══════════════════════════════════════
           SECTION 2: KEUNTUNGAN YANG DIPEROLEH
      ════════════════════════════════════════ -->
    <section class="profil-section">
      <h2 class="section-title">Keuntungan Saya</h2>
      <div class="profil-keuntungan-grid">
        <div class="profil-highlight-card">
          <div class="profil-highlight-icon">💰</div>
          <div>
            <div class="profil-highlight-label">Total Pendapatan Terkonfirmasi</div>
            <div class="profil-highlight-val"><?= rupiah((float) $ringkasan['keuntungan']) ?></div>
          </div>
        </div>
        <div class="profil-highlight-card">
          <div class="profil-highlight-icon">📦</div>
          <div>
            <div class="profil-highlight-label">Produk Terjual</div>
            <div class="profil-highlight-val"><?= (int) $total_produk_terjual ?></div>
          </div>
        </div>
        <div class="profil-highlight-card">
          <div class="profil-highlight-icon">🧾</div>
          <div>
            <div class="profil-highlight-label">Total Transaksi Sebagai Penjual</div>
            <div class="profil-highlight-val"><?= (int) $ringkasan['total_transaksi'] ?></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════
           SECTION 3: PRODUK YANG DIMILIKI SAAT INI
      ════════════════════════════════════════ -->
    <section class="profil-section">
      <h2 class="section-title">Produk yang Saya Miliki (<?= count($produk_saya) ?>)</h2>

      <?php if (empty($produk_saya)): ?>
        <div class="profil-card profil-empty-state">
          <p>Kamu belum memiliki produk. Yuk mulai jual barang bekasmu!</p>
          <a href="tambah_produk.php" class="btn-jual-sekarang">+ Tambah Produk</a>
        </div>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($produk_saya as $p):
            $foto = !empty($p['foto']) ? "../uploads/produk/" . htmlspecialchars($p['foto']) : "../assets/img/no-image.png";
            $bp   = $badge_produk[$p['status_produk']] ?? ['text' => ucfirst($p['status_produk']), 'class' => 'secondary'];
          ?>
            <div class="product-card profil-product-card">
              <span class="profil-badge profil-badge-<?= $bp['class'] ?> profil-badge-float"><?= $bp['text'] ?></span>
              <div class="card-image">
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>">
              </div>
              <div class="card-content">
                <h3 class="product-name"><?= htmlspecialchars($p['nama_produk']) ?></h3>
                <p class="product-price"><?= rupiah((float) $p['harga']) ?></p>
                <div class="product-meta">
                  <span class="location">📍 <?= !empty($p['nama_lokasi']) ? htmlspecialchars($p['nama_lokasi']) : 'Tidak Diketahui' ?></span>
                  <span class="date">Diupload <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

  </main>

  <!-- MODAL ZOOM KTM -->
  <div id="ktmModal" class="profil-modal-overlay" onclick="tutupKTM()">
    <div class="profil-modal-box" onclick="event.stopPropagation()">
      <button class="profil-modal-close" onclick="tutupKTM()">✕</button>
      <img id="ktmModalImg" src="" alt="Foto KTM">
    </div>
  </div>

  <script src="../assets/js/user_dashboard.js"></script>
  <script src="../assets/js/profil.js"></script>
</body>

</html>