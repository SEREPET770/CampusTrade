<?php
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "../config/Koneksi.php";

/* ==========================================================
| VALIDASI LOGIN
========================================================== */

if (!isset($_SESSION['id_user'])) {
  echo "
        <script>
            alert('Silakan login terlebih dahulu.');
            window.location.href = '../auth/login.php';
        </script>
    ";
  exit();
}

$id_user = $_SESSION['id_user'];
$nama_login = $_SESSION['nama'];

/* ==========================================================
| HELPER
========================================================== */

function formatRupiah($angka)
{
  return "Rp " . number_format($angka, 0, ',', '.');
}

/* ==========================================================
| VALIDASI ID PRODUK
========================================================== */

$id_produk = isset($_GET['id_produk']) ? (int) $_GET['id_produk'] : 0;

if ($id_produk <= 0) {
  echo "
        <script>
            alert('Produk tidak ditemukan.');
            window.location.href = 'user_produk.php';
        </script>
    ";
  exit();
}

/* ==========================================================
| AMBIL DATA PRODUK
========================================================== */

$sql = "
    SELECT
        p.*,
        k.nama_kategori,
        u.id_user,
        u.nama,
        u.no_whatsapp,
        u.email
    FROM produk p
    JOIN kategori_barang k
        ON p.id_kategori = k.id_kategori
    JOIN users u
        ON p.id_user = u.id_user
    WHERE p.id_produk = ?
    LIMIT 1
";

/**
 * @var mysqli $koneksi
 */
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $id_produk);
$stmt->execute();

$produk = $stmt->get_result()->fetch_assoc();

$stmt->close();

/* ==========================================================
| VALIDASI PRODUK
========================================================== */

if (!$produk) {
  echo "
        <script>
            alert('Produk tidak ditemukan.');
            window.location.href = 'user_produk.php';
        </script>
    ";
  exit();
}

if ($produk['id_user'] == $id_user) {
  echo "
        <script>
            alert('Anda tidak dapat membeli produk milik sendiri.');
            window.location.href = 'detail_produk.php?id_produk={$id_produk}';
        </script>
    ";
  exit();
}

if ($produk['status_produk'] !== 'tersedia') {
  echo "
        <script>
            alert('Produk sudah tidak tersedia.');
            window.location.href = 'detail_produk.php?id_produk={$id_produk}';
        </script>
    ";
  exit();
}

/* ==========================================================
| AMBIL FOTO PRODUK
========================================================== */

$sqlGambar = "
    SELECT image_path
    FROM gambar_produk
    WHERE id_produk = ?
    LIMIT 1
";

$stmt = $koneksi->prepare($sqlGambar);
$stmt->bind_param("i", $id_produk);
$stmt->execute();

$gambar = $stmt->get_result()->fetch_assoc();

$stmt->close();

$fotoProduk = "../assets/img/no-image.png";

if ($gambar) {
  $fotoProduk = "../uploads/produk/" . $gambar['image_path'];
}

/* ==========================================================
| AMBIL DATA USER LOGIN
========================================================== */

$sqlUser = "
    SELECT
        nama,
        email,
        no_whatsapp,
        alamat
    FROM users
    WHERE id_user = ?
    LIMIT 1
";

$stmt = $koneksi->prepare($sqlUser);
$stmt->bind_param("i", $id_user);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();
$sqlLokasi = "SELECT id_lokasi, nama_lokasi FROM lokasi ORDER BY nama_lokasi ASC";
$daftar_lokasi = $koneksi->query($sqlLokasi)->fetch_all(MYSQLI_ASSOC);

/*HITUNG ONGKIR */

$ongkir = 0;

/*HITUNG TOTAL PEMBAYARAN */
$subtotal = $produk['harga'];
$total = $subtotal + $ongkir;

?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout | CampusTrade</title>

  <link rel="stylesheet" href="../assets/css/checkout.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

  <div class="checkout-container">

    <a href="detail_produk.php?id_produk=<?= $produk['id_produk']; ?>" class="btn-back">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>

    <div class="page-title">
      <h2>Checkout</h2>
      <p>Pastikan informasi pesanan Anda sudah benar.</p>
    </div>

    <form action="proses_checkout.php" method="POST" id="checkoutForm">

      <input type="hidden" name="id_produk" value="<?= $produk['id_produk']; ?>">
      <input type="hidden" name="subtotal" value="<?= $subtotal; ?>">
      <input type="hidden" name="total_bayar" value="<?= $total; ?>">

      <div class="checkout-left">

        <div class="card">
          <div class="card-header">
            <i class="fas fa-box"></i>
            <span>Informasi Produk</span>
          </div>
          <div class="product-info">
            <img src="<?= $fotoProduk; ?>" alt="<?= htmlspecialchars($produk['nama_produk']); ?>">
            <div class="product-detail">
              <h3><?= htmlspecialchars($produk['nama_produk']); ?></h3>
              <table>
                <tr>
                  <td>Kategori</td>
                  <td>: <?= htmlspecialchars($produk['nama_kategori']); ?></td>
                </tr>
                <tr>
                  <td>Kondisi</td>
                  <td>: <?= htmlspecialchars($produk['kondisi']); ?></td>
                </tr>
                <tr>
                  <td>Penjual</td>
                  <td>: <?= htmlspecialchars($produk['nama']); ?></td>
                </tr>
                <tr>
                  <td>Harga</td>
                  <td class="price">
                    <?= formatRupiah($produk['harga']); ?>
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <i class="fas fa-handshake"></i>
            <span>Titik Temu COD</span>
          </div>
          <div class="form-group">
            <label>Nama Penerima</label>
            <input type="text" name="nama_penerima" value="<?= htmlspecialchars($user['nama']); ?>" required>
          </div>
          <div class="form-group">
            <label>No. WhatsApp</label>
            <input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_whatsapp']); ?>" required>
          </div>
          <div class="form-group">
            <label>Kampus Terdekat Anda</label>
            <select name="id_lokasi" required>
              <option value="">— Pilih Kampus —</option>
              <?php foreach ($daftar_lokasi as $lok): ?>
                <option value="<?= (int) $lok['id_lokasi'] ?>">
                  <?= htmlspecialchars($lok['nama_lokasi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="form-hint">
              Lokasi ini menjadi patokan untuk janjian COD dengan penjual via WhatsApp.
            </small>
          </div>
        </div>
      </div>
      <div class="checkout-right">

        <div class="card">
          <div class="card-header">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Ringkasan Pembayaran</span>
          </div>
          <table class="summary-table">
            <tr>
              <td>Harga Produk</td>
              <td class="text-right"><?= formatRupiah($subtotal); ?></td>
            </tr>
            <tr class="total-row">
              <td>Total Pembayaran</td>
              <td class="text-right total-price"><?= formatRupiah($total); ?></td>
            </tr>
          </table>
        </div>

        <div class="checkout-action">
          <button type="submit" class="btn-checkout">
            <i class="fas fa-credit-card"></i> Buat Pesanan
          </button>
        </div>

      </div>
    </form>

  </div>
  <script src="../assets/js/checkout.js"></script>

</body>

</html>