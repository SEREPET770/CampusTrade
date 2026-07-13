<?php
session_start();
require_once "../config/Koneksi.php";

// Cek apakah user sudah login
if (!isset($_SESSION['id_user'])) {
  header("Location: login.php");
  exit;
}

$id_user = $_SESSION['id_user'];
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Mahasiswa';
$foto_user = isset($_SESSION['foto']) && !empty($_SESSION['foto']) ? $_SESSION['foto'] : 'default-avatar.png';

// Fungsi format Rupiah
function formatRupiah($angka)
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Ambil data produk milik user beserta relasi kategori dan lokasi
$query = "SELECT p.*, k.nama_kategori, l.nama_lokasi 
          FROM produk p 
          LEFT JOIN kategori_barang k ON p.id_kategori = k.id_kategori 
          LEFT JOIN lokasi l ON p.id_lokasi = l.id_lokasi 
          WHERE p.id_user = ? 
          ORDER BY p.created_at DESC";

/**
 * @var mysqli $koneksi
 */
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$produk_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Produk - CampusTrade</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/kelola_produk.css">
</head>

<body>

  <header class="top-header">
    <div class="header-left">
      <a href="user_dashboard.php" class="btn-back">
        <svg viewBox="0 0 24 24">
          <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
        </svg>
        Kelola Produk
      </a>
    </div>

    <div class="header-right">
      <div class="profile-pill-wrapper" id="profileWrapper">
        <div class="profile-pill" id="profileBtn">
          <span><?= htmlspecialchars($nama_user) ?></span>
        </div>

        <div class="profile-dropdown-card" id="profileDropdown">
          <div class="dropdown-header">
            <img src="<?= htmlspecialchars($foto_user) ?>" alt="Avatar" class="avatar-large">
            <div class="user-info-text">
              <h3><?= htmlspecialchars($nama_user) ?></h3>
              <p>Mahasiswa</p>
            </div>
          </div>
          <a href="profil.php" class="btn-lihat-profil">Lihat Profil</a>
          <hr class="dropdown-divider">
          <ul class="dropdown-menu-list">
            <li><a href="user_dashboard.php">Beranda</a></li>
            <li><a href="kelola_produk.php" class="active-item">Kelola Produk</a></li>
            <li><a href="pesanan_saya.php">Pesanan Saya</a></li>
          </ul>
          <hr class="dropdown-divider">
          <a href="../auth/logout.php" class="btn-exit">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="page-title-section">
      <h1>Kelola Barang yang Anda Jual</h1>
    </div>

    <div class="controls-section">
      <div class="controls-left">
        <div class="search-box">
          <svg viewBox="0 0 24 24">
            <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
          </svg>
          <input type="text" id="searchInput" placeholder="Cari Barang (Nama, Brand, Penulis, Lokasi)...">
        </div>

        <div class="filter-box">
          <select id="statusFilter">
            <option value="semua">Semua Status</option>
            <option value="menunggu">Menunggu</option>
            <option value="tersedia">Tersedia</option>
            <option value="ditolak">Ditolak</option>
            <option value="terjual">Terjual</option>
          </select>
        </div>
      </div>

      <div class="controls-right">
        <a href="tambah_produk.php" class="btn-tambah">+ Tambah Produk</a>
      </div>
    </div>

    <div class="table-responsive">
      <table class="product-table" id="productTable">
        <thead>
          <tr>
            <th>Nama Produk</th>
            <th>Kategori</th>
            <th>Lokasi / COD</th>
            <th>Harga Jual</th>
            <th>Brand / Penulis</th>
            <th>Kelebihan</th>
            <th>Kekurangan</th>
            <th>Deskripsi Singkat</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($produk_list)): ?>
            <tr id="emptyRow">
              <td colspan="10" class="empty-state">Belum ada produk yang diunggah.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($produk_list as $p):
              $brand = isset($p['brand']) ? $p['brand'] : '';
              $penulis = isset($p['penulis']) ? $p['penulis'] : '';
              $kelebihan = isset($p['kelebihan']) ? $p['kelebihan'] : '';
              $kekurangan = isset($p['kekurangan']) ? $p['kekurangan'] : '';
              $deskripsi = isset($p['deskripsi']) ? $p['deskripsi'] : '';
              $nama_produk = isset($p['nama_produk']) ? $p['nama_produk'] : '';
              $nama_lokasi = isset($p['nama_lokasi']) ? $p['nama_lokasi'] : '';
              $nama_kategori = isset($p['nama_kategori']) ? $p['nama_kategori'] : '';
              $harga = isset($p['harga']) ? $p['harga'] : 0;
              $status = isset($p['status_produk']) ? strtolower($p['status_produk']) : 'menunggu';

              $searchData = strtolower($nama_produk . ' ' . $brand . ' ' . $penulis . ' ' . $nama_lokasi);
            ?>
              <tr class="product-row" data-id="<?= $p['id_produk'] ?>" data-search="<?= htmlspecialchars($searchData) ?>" data-status="<?= htmlspecialchars($status) ?>">
                <td data-label="Nama Produk">
                  <span class="view-text"><strong><?= htmlspecialchars($nama_produk) ?></strong></span>
                  <input type="text" class="edit-input" data-field="nama_produk" value="<?= htmlspecialchars($nama_produk) ?>">
                </td>
                <td data-label="Kategori"><?= htmlspecialchars($nama_kategori) ?></td>
                <td data-label="Lokasi"><?= htmlspecialchars($nama_lokasi) ?></td>
                <td data-label="Harga Jual" class="price-text">
                  <span class="view-text"><?= formatRupiah($harga) ?></span>
                  <input type="number" class="edit-input" data-field="harga" value="<?= $harga ?>">
                </td>
                <td data-label="Brand/Penulis">
                  <span class="view-text">
                    <?= $brand ? htmlspecialchars($brand) : '-' ?> <br>
                    <small><?= $penulis ? htmlspecialchars($penulis) : '-' ?></small>
                  </span>
                  <div class="edit-input">
                    <input type="text" data-field="brand" placeholder="Brand" value="<?= htmlspecialchars($brand) ?>" style="margin-bottom: 5px;">
                    <input type="text" data-field="penulis" placeholder="Penulis" value="<?= htmlspecialchars($penulis) ?>">
                  </div>
                </td>
                <td data-label="Kelebihan" class="desc-text">
                  <span class="view-text"><?= $kelebihan ? htmlspecialchars($kelebihan) : '-' ?></span>
                  <textarea class="edit-input" data-field="kelebihan" rows="2"><?= htmlspecialchars($kelebihan) ?></textarea>
                </td>
                <td data-label="Kekurangan" class="desc-text">
                  <span class="view-text"><?= $kekurangan ? htmlspecialchars($kekurangan) : '-' ?></span>
                  <textarea class="edit-input" data-field="kekurangan" rows="2"><?= htmlspecialchars($kekurangan) ?></textarea>
                </td>
                <td data-label="Deskripsi" class="desc-text">
                  <span class="view-text"><?= htmlspecialchars(substr($deskripsi, 0, 40)) ?>...</span>
                  <textarea class="edit-input" data-field="deskripsi" rows="3"><?= htmlspecialchars($deskripsi) ?></textarea>
                </td>
                <td data-label="Status">
                  <span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span>
                </td>
                <td data-label="Aksi" class="action-buttons">
                  <?php if ($status === 'terjual'): ?>
                    <button class="btn-edit disabled" disabled>✏ Edit</button>
                  <?php else: ?>
                    <button type="button" class="btn-edit btn-inline-edit">✏ Edit</button>
                    <button type="button" class="btn-save btn-inline-save">💾 Simpan</button>
                    <button type="button" class="btn-cancel btn-inline-cancel">❌ Batal</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script src="../assets/js/kelola_produk.js"></script>
</body>

</html>