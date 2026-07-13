<?php
session_start();
require_once "config/Koneksi.php";

// Fungsi Helper
function formatRupiah($angka)
{
  return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function formatTanggal($tanggal)
{
  return date('d M Y H:i', strtotime($tanggal));
}

$id_produk = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_produk <= 0) {
  die("<div class='error-msg'>ID Produk tidak valid.</div>");
}

/** @var mysqli $koneksi */
// Query utama untuk mengambil data produk beserta nama kategorinya
$sql_produk = "
    SELECT p.*, k.nama_kategori 
    FROM produk p
    LEFT JOIN kategori_barang k ON p.id_kategori = k.id_kategori
    WHERE p.id_produk = ?
";
$stmt = $koneksi->prepare($sql_produk);
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$result = $stmt->get_result();
$produk = $result->fetch_assoc();
$stmt->close();

if (!$produk) {
  die("<div class='error-msg'>Produk tidak ditemukan di database.</div>");
}

// Query untuk mengambil gambar-gambar produk
$sql_gambar = "SELECT image_path FROM gambar_produk WHERE id_produk = ?";
$stmt_gambar = $koneksi->prepare($sql_gambar);
$stmt_gambar->bind_param("i", $id_produk);
$stmt_gambar->execute();
$result_gambar = $stmt_gambar->get_result();
$gambar_list = $result_gambar->fetch_all(MYSQLI_ASSOC);
$stmt_gambar->close();

$ada_gambar = !empty($gambar_list);

// Jika tidak ada gambar, gunakan gambar default
$gambar_utama = !empty($gambar_list) ? "../uploads/produk/" . $gambar_list[0]['image_path'] : "../assets/img/no-image.png";

$kategori_nama = $produk['nama_kategori'] ?? 'Tidak diketahui';
$is_buku = (strtolower($kategori_nama) === 'buku');

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Produk Admin - CampusTrade</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../admin/assets/css/detail_produk_admin.css">
</head>

<body>

  <div class="admin-container">
    <div class="page-header">
      <h1>Detail Produk Admin</h1>
      <a href="../admin/produk.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="top-section">
      <div class="gallery-card">
        <div class="main-image-wrapper">
          <?php
          // Jika ada gambar, ambil dari folder uploads. Jika tidak, ambil default no-image dari assets
          if ($ada_gambar) {
            $gambar_utama = "../admin/uploads/produk/" . htmlspecialchars($gambar_list[0]['image_path']);
          } else {
            $gambar_utama = "../assets/img/no-image.png";
          }
          ?>
          <img src="<?= $gambar_utama ?>" alt="Gambar Utama" id="mainImage" class="main-image">
        </div>
        <?php if (count($gambar_list) > 1): ?>
          <div class="thumbnail-container">
            <?php foreach ($gambar_list as $index => $gbr): ?>
              <img src="../uploads/produk/<?= htmlspecialchars($gbr['image_path']) ?>"
                alt="Thumbnail"
                class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                onclick="changeImage(this)">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="info-card">
        <div class="info-header">
          <span class="badge-category"><?= htmlspecialchars($kategori_nama) ?></span>
          <h2 class="product-title"><?= htmlspecialchars($produk['nama_produk'] ?? '-') ?></h2>
          <div class="product-price"><?= formatRupiah($produk['harga'] ?? 0) ?></div>
        </div>

        <div class="info-details-table">
          <div class="info-row">
            <span class="info-label">Kondisi</span>
            <span class="badge-condition <?= strtolower($produk['kondisi'] ?? '') == 'baru' ? 'baru' : 'bekas' ?>">
              <?= htmlspecialchars($produk['kondisi'] ?? '-') ?>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Status</span>
            <span class="badge-status">
              <?= htmlspecialchars(ucfirst($produk['status_produk'] ?? '-')) ?>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Tanggal Upload</span>
            <span class="info-value"><?= formatTanggal($produk['created_at'] ?? date('Y-m-d H:i:s')) ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="details-section">

      <div class="detail-card">
        <h3>Spesifikasi Pembuat</h3>
        <div class="detail-content">
          <?php if ($is_buku): ?>
            <p><strong>Penulis :</strong> <?= htmlspecialchars($produk['penulis'] ?? '-') ?></p>
            <p><strong>Penerbit :</strong> <?= htmlspecialchars($produk['penerbit'] ?? '-') ?></p>
          <?php else: ?>
            <p><strong>Brand :</strong> <?= htmlspecialchars($produk['brand'] ?? '-') ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="detail-card">
        <h3>Kelebihan</h3>
        <div class="detail-content">
          <p><?= nl2br(htmlspecialchars($produk['kelebihan'] ?? '-')) ?></p>
        </div>
      </div>

      <div class="detail-card">
        <h3>Kekurangan</h3>
        <div class="detail-content">
          <p><?= nl2br(htmlspecialchars($produk['kekurangan'] ?? '-')) ?></p>
        </div>
      </div>

      <div class="detail-card">
        <h3>Alasan Dijual</h3>
        <div class="detail-content">
          <p><?= nl2br(htmlspecialchars($produk['alasan_jual'] ?? '-')) ?></p>
        </div>
      </div>

      <div class="detail-card description-card">
        <h3>Deskripsi Produk</h3>
        <div class="detail-content">
          <p><?= nl2br(htmlspecialchars($produk['deskripsi'] ?? '-')) ?></p>
        </div>
      </div>

    </div>
  </div>

  <script src="../admin/assets/js/detail_produk_admin.js"></script>
</body>

</html>