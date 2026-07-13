<?php
// tagihan.php
// Menampilkan tagihan transaksi dan menangani upload bukti pembayaran.

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/Koneksi.php';
/** @var mysqli $koneksi */

// ─────────────────────────────────────────
// HELPER
// ─────────────────────────────────────────
function rupiah(float $angka): string
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ─────────────────────────────────────────
// 1. VALIDASI LOGIN
// ─────────────────────────────────────────
if (empty($_SESSION['id_user'])) {
  echo "<script>alert('Silakan login terlebih dahulu.');location='../auth/login.php';</script>";
  exit;
}

$id_user = (int) $_SESSION['id_user'];

// ─────────────────────────────────────────
// 2. VALIDASI ID TRANSAKSI
// ─────────────────────────────────────────
$id_transaksi = isset($_GET['id_transaksi']) ? (int) $_GET['id_transaksi'] : 0;

if ($id_transaksi <= 0) {
  echo "<script>alert('Transaksi tidak valid.');location='pesanan_saya.php';</script>";
  exit;
}

// ─────────────────────────────────────────
// 3. HANDLE POST — UPLOAD BUKTI PEMBAYARAN
// ─────────────────────────────────────────
$pesan_sukses = '';
$pesan_error  = '';

// Ambil notifikasi dari session (redirect checkout)
if (!empty($_SESSION['sukses_checkout'])) {
  $pesan_sukses = $_SESSION['sukses_checkout'];
  unset($_SESSION['sukses_checkout']);
}

if (!empty($_SESSION['sukses_tagihan'])) {
  $pesan_sukses = $_SESSION['sukses_tagihan'];
  unset($_SESSION['sukses_tagihan']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['aksi']) && $_POST['aksi'] === 'batalkan') {
    $post_id_transaksi = isset($_POST['id_transaksi']) ? (int) $_POST['id_transaksi'] : 0;

    if ($post_id_transaksi !== $id_transaksi) {
      $pesan_error = 'Data transaksi tidak valid.';
      goto render;
    }

    $cek = $koneksi->prepare("SELECT id_transaksi, id_produk, status_transaksi, status_pembayaran FROM transaksi WHERE id_transaksi = ? AND id_pembeli = ? LIMIT 1");
    $cek->bind_param('ii', $post_id_transaksi, $id_user);
    $cek->execute();
    $trx = $cek->get_result()->fetch_assoc();
    $cek->close();

    if (!$trx) {
      $pesan_error = 'Transaksi tidak ditemukan.';
      goto render;
    }

    $boleh_batal = $trx['status_transaksi'] === 'menunggu_pembayaran'
      && in_array($trx['status_pembayaran'], ['belum_bayar', 'ditolak'], true);

    if (!$boleh_batal) {
      $pesan_error = 'Pesanan tidak dapat dibatalkan karena sudah masuk proses pembayaran.';
      goto render;
    }

    $koneksi->begin_transaction();

    try {
      $stmt = $koneksi->prepare("UPDATE transaksi SET status_transaksi = 'dibatalkan' WHERE id_transaksi = ? AND id_pembeli = ?");
      $stmt->bind_param('ii', $post_id_transaksi, $id_user);
      $stmt->execute();
      $stmt->close();

      $stmt = $koneksi->prepare("UPDATE produk SET status_produk = 'tersedia' WHERE id_produk = ? AND status_produk = 'dipesan'");
      $stmt->bind_param('i', $trx['id_produk']);
      $stmt->execute();
      $stmt->close();

      $koneksi->commit();
      $_SESSION['sukses_tagihan'] = 'Pesanan berhasil dibatalkan.';
      header('Location: tagihan.php?id_transaksi=' . $id_transaksi);
      exit;
    } catch (Throwable $e) {
      $koneksi->rollback();
      error_log('[tagihan] batal: ' . $e->getMessage());
      $pesan_error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
      goto render;
    }
  }

  // 3a. Ambil & validasi input POST
  $post_id_transaksi = isset($_POST['id_transaksi']) ? (int) $_POST['id_transaksi'] : 0;
  $id_metode         = isset($_POST['id_metode'])    ? (int) $_POST['id_metode']    : 0;
  $total_bayar_post  = isset($_POST['total_bayar'])  ? (float) $_POST['total_bayar'] : 0.0;
  $catatan_pembeli   = isset($_POST['catatan'])      ? trim($_POST['catatan'])       : '';

  // Validasi kepemilikan transaksi
  if ($post_id_transaksi !== $id_transaksi) {
    $pesan_error = 'Data transaksi tidak valid.';
    goto render;
  }

  // Validasi metode dipilih
  if ($id_metode <= 0) {
    $pesan_error = 'Pilih metode pembayaran terlebih dahulu.';
    goto render;
  }

  // 3b. Validasi file upload
  if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
    $pesan_error = 'Bukti pembayaran wajib diupload.';
    goto render;
  }

  $file      = $_FILES['bukti'];
  $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $ext_valid = ['jpg', 'jpeg', 'png'];
  $max_size  = 2 * 1024 * 1024; // 2 MB

  if (!in_array($ext, $ext_valid, true)) {
    $pesan_error = 'Format file harus JPG, JPEG, atau PNG.';
    goto render;
  }

  if ($file['size'] > $max_size) {
    $pesan_error = 'Ukuran file maksimal 2 MB.';
    goto render;
  }

  // Validasi MIME type
  $finfo     = finfo_open(FILEINFO_MIME_TYPE);
  $mime      = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);
  $mime_valid = ['image/jpeg', 'image/png'];

  if (!in_array($mime, $mime_valid, true)) {
    $pesan_error = 'File harus berupa gambar JPG atau PNG.';
    goto render;
  }

  // 3c. Simpan file
  $folder    = __DIR__ . '/../uploads/bukti_pembayaran/';
  $nama_file = 'BYR_' . $id_transaksi . '_' . time() . '.' . $ext;

  if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
  }

  if (!move_uploaded_file($file['tmp_name'], $folder . $nama_file)) {
    $pesan_error = 'Gagal menyimpan file. Silakan coba lagi.';
    goto render;
  }

  // 3d. Simpan ke database
  $koneksi->begin_transaction();

  try {
    // Cek apakah sudah ada record pembayaran sebelumnya (kasus upload ulang)
    $cek = $koneksi->prepare("
            SELECT id_pembayaran FROM pembayaran
            WHERE id_transaksi = ?
            LIMIT 1
        ");
    $cek->bind_param('i', $id_transaksi);
    $cek->execute();
    $cek->store_result();
    $sudah_ada = $cek->num_rows > 0;
    $cek->close();

    if ($sudah_ada) {
      // Upload ulang: UPDATE record lama
      $stmt = $koneksi->prepare("
                UPDATE pembayaran
                SET id_metode         = ?,
                    nominal           = ?,
                    bukti_pembayaran  = ?,
                    catatan_pembeli   = ?,
                    status_verifikasi = 'menunggu_verifikasi',
                    tanggal_upload    = NOW()
                WHERE id_transaksi = ?
            ");
      $stmt->bind_param('idssi', $id_metode, $total_bayar_post, $nama_file, $catatan_pembeli, $id_transaksi);
    } else {
      // Upload pertama: INSERT record baru
      $stmt = $koneksi->prepare("
                INSERT INTO pembayaran
                    (id_transaksi, id_metode, nominal, bukti_pembayaran,
                     catatan_pembeli, status_verifikasi, tanggal_upload)
                VALUES
                    (?, ?, ?, ?,
                     ?, 'menunggu_verifikasi', NOW())
            ");
      $stmt->bind_param('iidss', $id_transaksi, $id_metode, $total_bayar_post, $nama_file, $catatan_pembeli);
    }

    $stmt->execute();
    $stmt->close();

    // Update status_pembayaran di transaksi
    $stmt = $koneksi->prepare("
            UPDATE transaksi
            SET status_pembayaran = 'menunggu_konfirmasi'
            WHERE id_transaksi = ?
              AND id_pembeli    = ?
        ");
    $stmt->bind_param('ii', $id_transaksi, $id_user);
    $stmt->execute();
    $stmt->close();

    $koneksi->commit();
    $pesan_sukses = 'Bukti pembayaran berhasil dikirim. Menunggu konfirmasi penjual.';
  } catch (Exception $e) {
    $koneksi->rollback();
    // Hapus file yang sudah terupload jika DB gagal
    if (file_exists($folder . $nama_file)) {
      unlink($folder . $nama_file);
    }
    error_log('[tagihan] ' . $e->getMessage());
    $pesan_error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
  }
}

render:

// ─────────────────────────────────────────
// 4. AMBIL DATA TRANSAKSI
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT
        t.id_transaksi,
        t.kode_invoice,
        t.id_penjual,
        t.harga_produk,
        t.ongkir,
        t.total_bayar,
        t.status_transaksi,
        t.status_pembayaran,
        t.created_at,
        p.nama_produk,
        (SELECT gp.image_path
         FROM gambar_produk gp
         WHERE gp.id_produk = p.id_produk
         LIMIT 1) AS foto_produk,
        u.nama     AS nama_penjual,
        u.no_whatsapp AS wa_penjual
    FROM transaksi t
    JOIN produk p ON p.id_produk  = t.id_produk
    JOIN users  u ON u.id_user    = t.id_penjual
    WHERE t.id_transaksi = ?
      AND t.id_pembeli   = ?
    LIMIT 1
");
$stmt->bind_param('ii', $id_transaksi, $id_user);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
  echo "<script>alert('Transaksi tidak ditemukan.');location='pesanan_saya.php';</script>";
  exit;
}

// ─────────────────────────────────────────
// 5. AMBIL DATA PENGIRIMAN
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT nama_penerima, no_hp, alamat, metode_pengiriman
    FROM pengiriman
    WHERE id_transaksi = ?
    LIMIT 1
");
$stmt->bind_param('i', $id_transaksi);
$stmt->execute();
$pengiriman = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─────────────────────────────────────────
// 6. AMBIL METODE PEMBAYARAN PENJUAL
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT id_metode, nama_metode, jenis, provider,
           nomor_tujuan, nama_pemilik, catatan, qr_code
    FROM metode_pembayaran
    WHERE id_user = ?
      AND status  = 1
    ORDER BY jenis, nama_metode
");
$stmt->bind_param('i', $transaksi['id_penjual']);
$stmt->execute();
$result_metode  = $stmt->get_result();
$list_rekening  = [];
while ($row = $result_metode->fetch_assoc()) {
  $list_rekening[] = $row;
}
$stmt->close();

// ─────────────────────────────────────────
// 7. AMBIL DATA PEMBAYARAN (jika sudah upload)
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT pb.id_pembayaran, pb.bukti_pembayaran,
           pb.catatan_pembeli, pb.catatan_admin,
           pb.status_verifikasi, pb.tanggal_upload,
           mp.nama_metode
    FROM pembayaran pb
    JOIN metode_pembayaran mp ON mp.id_metode = pb.id_metode
    WHERE pb.id_transaksi = ?
    ORDER BY pb.created_at DESC
    LIMIT 1
");
$stmt->bind_param('i', $id_transaksi);
$stmt->execute();
$pembayaran = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─────────────────────────────────────────
// 8. TENTUKAN STATUS & TAMPILAN
// ─────────────────────────────────────────
// Acuan utama: transaksi.status_pembayaran
$status_pembayaran = $transaksi['status_pembayaran'];

$status_label = [
  'belum_bayar'          => 'Belum Melakukan Pembayaran',
  'menunggu_konfirmasi'  => 'Menunggu Konfirmasi Penjual',
  'dibayar'              => 'Pembayaran Diterima',
  'ditolak'              => 'Pembayaran Ditolak',
];

$status_class = [
  'belum_bayar'          => 'warning',
  'menunggu_konfirmasi'  => 'info',
  'dibayar'              => 'success',
  'ditolak'              => 'danger',
];

$status_order_label = [
  'menunggu_pembayaran' => 'Menunggu Pembayaran',
  'menunggu_verifikasi' => 'Menunggu Verifikasi',
  'diproses'            => 'Diproses',
  'dikirim'             => 'Dikirim',
  'selesai'             => 'Selesai',
  'dibatalkan'          => 'Dibatalkan',
];

$status_order_class = [
  'menunggu_pembayaran' => 'warning',
  'menunggu_verifikasi' => 'info',
  'diproses'            => 'primary',
  'dikirim'             => 'info',
  'selesai'             => 'success',
  'dibatalkan'          => 'danger',
];

$status_text  = $status_label[$status_pembayaran]  ?? 'Status Tidak Diketahui';
$badge_class  = $status_class[$status_pembayaran]  ?? 'secondary';

// Tampilkan form upload jika belum bayar atau ditolak dan pesanan belum dibatalkan
$show_upload  = in_array($status_pembayaran, ['belum_bayar', 'ditolak'], true)
  && $transaksi['status_transaksi'] !== 'dibatalkan';

// Foto produk
$foto_produk = '../assets/images/no-image.png';
if (!empty($transaksi['foto_produk'])) {
  $path = __DIR__ . '/../uploads/produk/' . $transaksi['foto_produk'];
  if (file_exists($path)) {
    $foto_produk = '../uploads/produk/' . $transaksi['foto_produk'];
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tagihan #<?= htmlspecialchars($transaksi['kode_invoice']) ?> | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/tagihan.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

  <div class="invoice-container">

    <a href="pesanan_saya.php" class="btn-back">
      <i class="fas fa-arrow-left"></i> Kembali ke Pesanan Saya
    </a>

    <div class="invoice-header">
      <h2>Tagihan Pembayaran</h2>
      <p>Selesaikan pembayaran Anda sesuai rincian berikut.</p>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan_sukses !== ''): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($pesan_sukses) ?>
      </div>
    <?php endif; ?>

    <?php if ($pesan_error !== ''): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($pesan_error) ?>
      </div>
    <?php endif; ?>

    <div class="invoice-grid">

      <div class="invoice-col-main">

        <!-- INFORMASI TRANSAKSI -->
        <div class="card">
          <div class="card-header">
            <i class="fas fa-file-invoice"></i>
            <span>Informasi Transaksi</span>
          </div>
          <div class="invoice-info">
            <div class="info-item">
              <label>No. Invoice</label>
              <span><?= htmlspecialchars($transaksi['kode_invoice']) ?></span>
            </div>
            <div class="info-item">
              <label>Tanggal Order</label>
              <span><?= date('d M Y H:i', strtotime($transaksi['created_at'])) ?></span>
            </div>
            <div class="info-item">
              <label>Status Pembayaran</label>
              <span class="badge <?= $badge_class ?>">
                <?= htmlspecialchars($status_text) ?>
              </span>
            </div>
            <div class="info-item">
              <label>Status Pesanan</label>
              <span class="badge <?= htmlspecialchars($status_order_class[$transaksi['status_transaksi']] ?? 'secondary') ?>">
                <?= htmlspecialchars($status_order_label[$transaksi['status_transaksi']] ?? ucwords(str_replace('_', ' ', $transaksi['status_transaksi']))) ?>
              </span>
            </div>
          </div>
        </div>

        <!-- PRODUK -->
        <div class="card">
          <div class="card-header">
            <i class="fas fa-box"></i>
            <span>Produk yang Dibeli</span>
          </div>
          <div class="product-box">
            <img src="<?= htmlspecialchars($foto_produk) ?>"
              alt="<?= htmlspecialchars($transaksi['nama_produk']) ?>">
            <div class="product-detail">
              <h3><?= htmlspecialchars($transaksi['nama_produk']) ?></h3>
              <table>
                <tr>
                  <td>Penjual</td>
                  <td>: <?= htmlspecialchars($transaksi['nama_penjual']) ?></td>
                </tr>
                <tr>
                  <td>WhatsApp</td>
                  <td>:
                    <a href="https://wa.me/<?= wa_phone($transaksi['wa_penjual']) ?>"
                      target="_blank">
                      <?= htmlspecialchars($transaksi['wa_penjual']) ?>
                    </a>
                  </td>
                </tr>
                <tr>
                  <td>Harga</td>
                  <td>: <?= rupiah((float) $transaksi['harga_produk']) ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <!-- ALAMAT PENGIRIMAN -->
        <div class="card">
          <div class="card-header">
            <i class="fas fa-location-dot"></i>
            <span>Informasi Pengiriman</span>
          </div>
          <?php if ($pengiriman): ?>
            <table class="info-table">
              <tr>
                <td>Penerima</td>
                <td>: <?= htmlspecialchars($pengiriman['nama_penerima'] ?? '-') ?></td>
              </tr>
              <tr>
                <td>No. HP</td>
                <td>: <?= htmlspecialchars($pengiriman['no_hp'] ?? '-') ?></td>
              </tr>
              <?php if (!empty($pengiriman['alamat'])): ?>
                <tr>
                  <td>Titik Temu COD</td>
                  <td>: <?= nl2br(htmlspecialchars($pengiriman['alamat'])) ?></td>
                </tr>
              <?php endif; ?>
            </table>
            <p class="cod-note">
              <i class="fas fa-circle-info"></i>
              Hubungi penjual via WhatsApp untuk menentukan waktu COD di lokasi tersebut.
            </p>
          <?php else: ?>
            <p>Data pengiriman tidak tersedia.</p>
          <?php endif; ?>
        </div>

        <!-- RINGKASAN PEMBAYARAN -->
        <div class="card">
          <div class="card-header">
            <i class="fas fa-receipt"></i>
            <span>Ringkasan Pembayaran</span>
          </div>
          <table class="summary-table">
            <tr>
              <td>Harga Produk</td>
              <td><?= rupiah((float) $transaksi['harga_produk']) ?></td>
            </tr>
            <tr>
              <td>Ongkir</td>
              <td><?= rupiah((float) $transaksi['ongkir']) ?></td>
            </tr>
            <tr class="total">
              <td><strong>Total Pembayaran</strong></td>
              <td><strong><?= rupiah((float) $transaksi['total_bayar']) ?></strong></td>
            </tr>
          </table>
        </div>

      </div><!-- /.invoice-col-main -->

      <div class="invoice-col-side">

        <!-- METODE PEMBAYARAN PENJUAL -->
        <?php if (!empty($list_rekening)): ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-wallet"></i>
              <span>Tujuan Pembayaran</span>
            </div>

            <?php foreach ($list_rekening as $i => $rek): ?>
              <div class="rekening-item">
                <div class="rekening-info">
                  <h4><?= htmlspecialchars($rek['nama_metode']) ?>
                    <span class="badge-jenis"><?= htmlspecialchars($rek['jenis']) ?></span>
                  </h4>
                  <p><strong><?= htmlspecialchars($rek['nama_pemilik']) ?></strong></p>

                  <?php if ($rek['jenis'] === 'Bank'): ?>
                    <p>No. Rekening: <strong><?= htmlspecialchars($rek['nomor_tujuan']) ?></strong></p>

                  <?php elseif ($rek['jenis'] === 'E-Wallet'): ?>
                    <p>No. HP: <strong><?= htmlspecialchars($rek['nomor_tujuan']) ?></strong></p>

                  <?php elseif ($rek['jenis'] === 'QRIS'): ?>
                    <p>Scan QRIS di bawah ini untuk membayar.</p>
                  <?php endif; ?>

                  <?php if (!empty($rek['catatan'])): ?>
                    <p class="rekening-catatan"><?= htmlspecialchars($rek['catatan']) ?></p>
                  <?php endif; ?>
                </div>

                <?php if (!empty($rek['qr_code'])): ?>
                  <div class="rekening-qr">
                    <img src="../uploads/qr/<?= htmlspecialchars($rek['qr_code']) ?>"
                      alt="QR Code <?= htmlspecialchars($rek['nama_metode']) ?>"
                      class="qr-image">
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($i < count($list_rekening) - 1): ?>
                <hr>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-wallet"></i>
              <span>Tujuan Pembayaran</span>
            </div>
            <p>Penjual belum menambahkan metode pembayaran. Hubungi penjual via WhatsApp.</p>
          </div>
        <?php endif; ?>

        <!-- FORM UPLOAD BUKTI PEMBAYARAN -->
        <?php if ($show_upload && !empty($list_rekening)): ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-cloud-upload-alt"></i>
              <span>Upload Bukti Pembayaran</span>
            </div>

            <?php if ($status_pembayaran === 'ditolak' && !empty($pembayaran['catatan_admin'])): ?>
              <div class="payment-reject-box">
                <div class="payment-reject-icon">
                  <i class="fas fa-circle-exclamation"></i>
                </div>
                <div class="payment-reject-content">
                  <h4>Pembayaran Ditolak</h4>
                  <p><?= htmlspecialchars($pembayaran['catatan_admin']) ?></p>
                  <small>Silakan unggah bukti pembayaran ulang dengan data yang benar.</small>
                </div>
              </div>
            <?php endif; ?>

            <form action="tagihan.php?id_transaksi=<?= $id_transaksi ?>"
              method="POST"
              enctype="multipart/form-data"
              id="formUploadBukti">

              <input type="hidden" name="id_transaksi" value="<?= $id_transaksi ?>">
              <input type="hidden" name="total_bayar" value="<?= (float) $transaksi['total_bayar'] ?>">

              <div class="form-group">
                <label>Metode Pembayaran yang Digunakan <span class="required">*</span></label>
                <select name="id_metode" required>
                  <option value="">-- Pilih Metode Pembayaran --</option>
                  <?php foreach ($list_rekening as $rek): ?>
                    <option value="<?= (int) $rek['id_metode'] ?>">
                      <?= htmlspecialchars($rek['nama_metode']) ?>
                      (<?= htmlspecialchars($rek['jenis']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Bukti Transfer <span class="required">*</span></label>
                <input type="file"
                  name="bukti"
                  id="inputBukti"
                  accept=".jpg,.jpeg,.png"
                  required>
                <small>Format: JPG, JPEG, PNG. Maks. 2 MB.</small>
                <div id="previewBukti"></div>
              </div>

              <div class="form-group">
                <label>Catatan (opsional)</label>
                <textarea name="catatan" rows="3"
                  placeholder="Contoh: Sudah transfer jam 10 pagi via BCA"></textarea>
              </div>

              <button type="submit" class="btn-primary" id="btnKirimBukti">
                <i class="fas fa-paper-plane"></i> Kirim Bukti Pembayaran
              </button>

            </form>
          </div>
        <?php endif; ?>

        <?php if ($transaksi['status_transaksi'] === 'dibatalkan'): ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-ban"></i>
              <span>Status Dibatalkan</span>
            </div>
            <p>Pesanan ini telah dibatalkan. Anda tidak dapat mengupload bukti pembayaran lagi.</p>
          </div>
        <?php endif; ?>

        <?php if ($show_upload): ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-ban"></i>
              <span>Batalkan Pesanan</span>
            </div>
            <p>Batalkan pesanan jika Anda tidak ingin melanjutkan pembayaran untuk transaksi ini.</p>
            <form action="tagihan.php?id_transaksi=<?= $id_transaksi ?>"
              method="POST"
              onsubmit="return confirm('Batalkan pesanan ini?');">

              <input type="hidden" name="id_transaksi" value="<?= $id_transaksi ?>">
              <input type="hidden" name="aksi" value="batalkan">

              <button type="submit" class="btn-danger">
                <i class="fas fa-times-circle"></i> Batalkan Pesanan
              </button>
            </form>
          </div>
        <?php endif; ?>

        <!-- STATUS PEMBAYARAN YANG SUDAH DIUPLOAD -->
        <?php if ($pembayaran): ?>
          <div class="card">
            <div class="card-header">
              <i class="fas fa-clock"></i>
              <span>Riwayat Pembayaran</span>
            </div>
            <table class="info-table">
              <tr>
                <td>Metode</td>
                <td>: <?= htmlspecialchars($pembayaran['nama_metode']) ?></td>
              </tr>
              <tr>
                <td>Nominal</td>
                <td>: <?= rupiah((float) $transaksi['total_bayar']) ?></td>
              </tr>
              <tr>
                <td>Tanggal Upload</td>
                <td>: <?= date('d M Y H:i', strtotime($pembayaran['tanggal_upload'])) ?></td>
              </tr>
              <tr>
                <td>Status</td>
                <td>:
                  <span class="badge <?= $badge_class ?>">
                    <?= htmlspecialchars($status_text) ?>
                  </span>
                </td>
              </tr>
              <?php if (!empty($pembayaran['catatan_pembeli'])): ?>
                <tr>
                  <td>Catatan</td>
                  <td>: <?= htmlspecialchars($pembayaran['catatan_pembeli']) ?></td>
                </tr>
              <?php endif; ?>
              <?php if (!empty($pembayaran['catatan_admin'])): ?>
                <tr>
                  <td>Catatan Penjual</td>
                  <td>: <?= htmlspecialchars($pembayaran['catatan_admin']) ?></td>
                </tr>
              <?php endif; ?>
            </table>

            <?php if (!empty($pembayaran['bukti_pembayaran'])): ?>
              <div class="bukti-box">
                <p><strong>Bukti Pembayaran:</strong></p>
                <img src="../uploads/bukti_pembayaran/<?= htmlspecialchars($pembayaran['bukti_pembayaran']) ?>"
                  alt="Bukti Pembayaran"
                  class="payment-proof"
                  id="imgBukti">
                <br>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div><!-- /.invoice-col-side -->

    </div><!-- /.invoice-grid -->

  </div><!-- /.invoice-container -->

  <!-- MODAL ZOOM BUKTI -->
  <div id="modalBukti" class="modal-overlay" style="display:none;" onclick="tutupModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
      <button class="modal-close" onclick="tutupModal()">
        <i class="fas fa-times"></i>
      </button>
      <img id="modalImg" src="" alt="Bukti Pembayaran">
    </div>
  </div>

  <script>
    // Preview gambar sebelum upload + tombol batalkan
    document.getElementById('inputBukti')?.addEventListener('change', function() {
      const inputFile = this;
      const preview = document.getElementById('previewBukti');
      preview.innerHTML = '';

      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const wrap = document.createElement('div');
          wrap.className = 'preview-wrap';

          const img = document.createElement('img');
          img.src = e.target.result;
          img.className = 'preview-image';

          const btnHapus = document.createElement('button');
          btnHapus.type = 'button';
          btnHapus.className = 'preview-remove';
          btnHapus.title = 'Batalkan file ini';
          btnHapus.innerHTML = '<i class="fas fa-times"></i>';
          btnHapus.addEventListener('click', function() {
            inputFile.value = '';
            preview.innerHTML = '';
          });

          wrap.appendChild(img);
          wrap.appendChild(btnHapus);
          preview.appendChild(wrap);
        };
        reader.readAsDataURL(this.files[0]);
      }
    });

    // Konfirmasi sebelum submit
    document.getElementById('formUploadBukti')?.addEventListener('submit', function(e) {
      const btn = document.getElementById('btnKirimBukti');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    });

    // Zoom bukti pembayaran
    function zoomBukti() {
      const src = document.getElementById('imgBukti')?.src;
      if (!src) return;
      document.getElementById('modalImg').src = src;
      document.getElementById('modalBukti').style.display = 'flex';
    }

    function tutupModal() {
      document.getElementById('modalBukti').style.display = 'none';
    }

    // Tutup modal dengan ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') tutupModal();
    });
  </script>

</body>

</html>