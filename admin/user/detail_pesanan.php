<?php
// detail_pesanan.php
// Halaman pembeli: detail lengkap satu pesanan.

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "../config/Koneksi.php";
/** @var mysqli $koneksi */


// HELPER

function rupiah(float $angka): string
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

function tgl(string $datetime): string
{
  return date('d M Y H:i', strtotime($datetime));
}


// 1. VALIDASI LOGIN

if (empty($_SESSION['id_user'])) {
  echo "<script>alert('Silakan login terlebih dahulu.');
          location='../auth/login.php';</script>";
  exit;
}

$id_pembeli = (int) $_SESSION['id_user'];


// 2. VALIDASI ID TRANSAKSI

$id_transaksi = isset($_GET['id_transaksi']) ? (int) $_GET['id_transaksi'] : 0;

if ($id_transaksi <= 0) {
  echo "<script>alert('Transaksi tidak valid.');
          location='pesanan_saya.php';</script>";
  exit;
}


// 3. AMBIL DATA TRANSAKSI UTAMA
/** @var mysqli $koneksi */
$stmt = $koneksi->prepare("
    SELECT
        t.id_transaksi,
        t.kode_invoice,
        t.id_penjual,
        t.id_produk,
        t.harga_produk,
        t.ongkir,
        t.total_bayar,
        t.jarak_km,
        t.status_transaksi,
        t.status_pembayaran,
        t.metode_pembayaran,
        t.created_at,
        p.nama_produk,
        p.kondisi,
        p.deskripsi,
        u.nama        AS nama_penjual,
        u.no_whatsapp AS wa_penjual,
        u.email       AS email_penjual
    FROM transaksi t
    JOIN produk p ON p.id_produk = t.id_produk
    JOIN users  u ON u.id_user   = t.id_penjual
    WHERE t.id_transaksi = ?
      AND t.id_pembeli   = ?
    LIMIT 1
");
$stmt->bind_param('ii', $id_transaksi, $id_pembeli);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
  echo "<script>alert('Pesanan tidak ditemukan.');
          location='pesanan_saya.php';</script>";
  exit;
}


// 4. AMBIL FOTO PRODUK (galeri mini)

$stmt = $koneksi->prepare("
    SELECT image_path
    FROM gambar_produk
    WHERE id_produk = ?
    ORDER BY id_gambar ASC
");
$stmt->bind_param('i', $transaksi['id_produk']);
$stmt->execute();
$result_foto  = $stmt->get_result();
$list_foto    = [];
while ($row = $result_foto->fetch_assoc()) {
  $list_foto[] = $row['image_path'];
}
$stmt->close();

$foto_utama = !empty($list_foto)
  ? '../uploads/produk/' . htmlspecialchars($list_foto[0])
  : '../assets/images/no-image.png';


// 6. AMBIL DATA PEMBAYARAN

$stmt = $koneksi->prepare("
    SELECT
        pb.id_pembayaran,
        pb.nominal,
        pb.bukti_pembayaran,
        pb.catatan_pembeli,
        pb.catatan_admin,
        pb.status_verifikasi,
        pb.tanggal_upload,
        pb.tanggal_verifikasi,
        mp.nama_metode,
        mp.jenis        AS jenis_metode,
        mp.nomor_tujuan,
        mp.nama_pemilik
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


// 7. DEFINISI LABEL & BADGE

$label_bayar = [
  'belum_bayar'         => ['text' => 'Belum Bayar',          'class' => 'warning'],
  'menunggu_konfirmasi' => ['text' => 'Menunggu Konfirmasi',  'class' => 'info'],
  'dibayar'             => ['text' => 'Dibayar',              'class' => 'success'],
  'ditolak'             => ['text' => 'Ditolak',              'class' => 'danger'],
];

$label_transaksi = [
  'menunggu_pembayaran' => ['text' => 'Menunggu Pembayaran',  'class' => 'warning'],
  'menunggu_verifikasi' => ['text' => 'Menunggu Verifikasi',  'class' => 'info'],
  'diproses'            => ['text' => 'Diproses',             'class' => 'primary'],
  'dikirim'             => ['text' => 'Dikirim',              'class' => 'info'],
  'selesai'             => ['text' => 'Selesai',              'class' => 'success'],
  'dibatalkan'          => ['text' => 'Dibatalkan',           'class' => 'danger'],
];


$sp  = $transaksi['status_pembayaran'];
$st  = $transaksi['status_transaksi'];
$bp  = $label_bayar[$sp]      ?? ['text' => $sp, 'class' => 'secondary'];
$bt  = $label_transaksi[$st]  ?? ['text' => $st, 'class' => 'secondary'];


// Tentukan aksi yang tersedia
$show_bayar        = in_array($sp, ['belum_bayar', 'ditolak'], true);
$show_bukti        = $pembayaran !== null;
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pesanan <?= htmlspecialchars($transaksi['kode_invoice']) ?> | CampusTrade</title>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/detail_pesanan.css">
</head>

<body>

  <div class="page-wrapper">

    <!-- HEADER -->
    <header class="top-header">
      <div class="header-left">
        <a href="pesanan_saya.php" class="btn-back">
          <i class="fas fa-arrow-left"></i> Pesanan Saya
        </a>
        <h1 class="page-title">Detail Pesanan</h1>
      </div>
      <div class="header-right">
        <span class="username">
          <?= htmlspecialchars($_SESSION['nama'] ?? '') ?>
        </span>
      </div>
    </header>

    <main class="main-content">

      <!-- ═══════════════════════════════════════
             SECTION 1: INFORMASI TRANSAKSI
        ════════════════════════════════════════ -->
      <div class="card">
        <div class="card-header">
          <i class="fas fa-file-invoice"></i>
          <span>Informasi Transaksi</span>
        </div>
        <div class="card-body">
          <table class="info-table">
            <tr>
              <td>No. Invoice</td>
              <td>: <strong><?= htmlspecialchars($transaksi['kode_invoice']) ?></strong></td>
            </tr>
            <tr>
              <td>Tanggal Order</td>
              <td>: <?= tgl($transaksi['created_at']) ?></td>
            </tr>
            <tr>
              <td>Status Pembayaran</td>
              <td>:
                <span class="badge badge-<?= $bp['class'] ?>">
                  <?= htmlspecialchars($bp['text']) ?>
                </span>
              </td>
            </tr>
            <tr>
              <td>Status Pesanan</td>
              <td>:
                <span class="badge badge-<?= $bt['class'] ?> badge-outline">
                  <?= htmlspecialchars($bt['text']) ?>
                </span>
              </td>
            </tr>
          </table>

          <!-- Tombol Bayar jika belum bayar atau ditolak -->
          <?php if ($show_bayar): ?>
            <div class="action-box">
              <?php if ($sp === 'ditolak' && !empty($pembayaran['catatan_admin'])): ?>
                <div class="alert alert-danger">
                  <i class="fas fa-times-circle"></i>
                  <strong>Alasan penolakan:</strong>
                  <?= htmlspecialchars($pembayaran['catatan_admin']) ?>
                </div>
              <?php endif; ?>
              <a href="tagihan.php?id_transaksi=<?= $id_transaksi ?>"
                class="btn-bayar">
                <i class="fas fa-credit-card"></i>
                <?= $sp === 'ditolak' ? 'Upload Ulang Bukti Pembayaran' : 'Bayar Sekarang' ?>
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ═══════════════════════════════════════
             SECTION 2: DETAIL PRODUK
        ════════════════════════════════════════ -->
      <div class="card">
        <div class="card-header">
          <i class="fas fa-box"></i>
          <span>Detail Produk</span>
        </div>
        <div class="card-body">
          <div class="product-detail-row">

            <!-- Galeri Foto -->
            <div class="product-gallery">
              <img src="<?= $foto_utama ?>"
                alt="<?= htmlspecialchars($transaksi['nama_produk']) ?>"
                class="foto-utama"
                id="fotoUtama">

              <?php if (count($list_foto) > 1): ?>
                <div class="thumbnail-row">
                  <?php foreach ($list_foto as $i => $foto): ?>
                    <img src="../uploads/produk/<?= htmlspecialchars($foto) ?>"
                      alt="Foto <?= $i + 1 ?>"
                      class="thumbnail <?= $i === 0 ? 'active' : '' ?>"
                      onclick="gantiGambar(this)">
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Info Produk -->
            <div class="product-info-col">
              <h2 class="product-name">
                <?= htmlspecialchars($transaksi['nama_produk']) ?>
              </h2>
              <p class="product-kondisi">
                Kondisi: <strong><?= htmlspecialchars($transaksi['kondisi']) ?></strong>
              </p>

              <table class="info-table">
                <tr>
                  <td>Penjual</td>
                  <td>: <strong><?= htmlspecialchars($transaksi['nama_penjual']) ?></strong></td>
                </tr>
                <tr>
                  <td>WhatsApp</td>
                  <td>:
                    <a href="https://wa.me/<?= wa_phone($transaksi['wa_penjual']) ?>"
                      target="_blank" class="link-wa">
                      <i class="fab fa-whatsapp"></i>
                      <?= htmlspecialchars($transaksi['wa_penjual']) ?>
                    </a>
                  </td>
                </tr>
                <tr>
                  <td>Email</td>
                  <td>: <?= htmlspecialchars($transaksi['email_penjual']) ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>


      <!-- ═══════════════════════════════════════
             SECTION 4: RINGKASAN PEMBAYARAN
        ════════════════════════════════════════ -->
      <div class="card">
        <div class="card-header">
          <i class="fas fa-receipt"></i>
          <span>Ringkasan Pembayaran</span>
        </div>
        <div class="card-body">
          <table class="summary-table">
            <tr>
              <td>Harga Produk</td>
              <td><?= rupiah((float) $transaksi['harga_produk']) ?></td>
            </tr>
            <tr class="total-row">
              <td><strong>Total Pembayaran</strong></td>
              <td><strong><?= rupiah((float) $transaksi['total_bayar']) ?></strong></td>
            </tr>
          </table>
        </div>
      </div>

      <!-- ═══════════════════════════════════════
             SECTION 5: RIWAYAT PEMBAYARAN
        ════════════════════════════════════════ -->
      <?php if ($show_bukti): ?>
        <div class="card">
          <div class="card-header">
            <i class="fas fa-money-check-alt"></i>
            <span>Riwayat Pembayaran</span>
          </div>
          <div class="card-body">
            <table class="info-table">
              <tr>
                <td>Metode</td>
                <td>:
                  <?= htmlspecialchars($pembayaran['nama_metode']) ?>
                  (<?= htmlspecialchars($pembayaran['jenis_metode']) ?>)
                </td>
              </tr>
              <tr>
                <td>Tujuan</td>
                <td>:
                  <?= htmlspecialchars($pembayaran['nomor_tujuan']) ?>
                  a/n <?= htmlspecialchars($pembayaran['nama_pemilik']) ?>
                </td>
              </tr>
              <tr>
                <td>Nominal</td>
                <td>: <?= rupiah((float) $pembayaran['nominal']) ?></td>
              </tr>
              <tr>
                <td>Tanggal Upload</td>
                <td>: <?= tgl($pembayaran['tanggal_upload']) ?></td>
              </tr>
              <tr>
                <td>Status</td>
                <td>:
                  <?php
                  $sv  = $pembayaran['status_verifikasi'];
                  $lv  = [
                    'belum_bayar'         => ['text' => 'Belum Bayar',         'class' => 'warning'],
                    'menunggu_verifikasi' => ['text' => 'Menunggu Verifikasi', 'class' => 'info'],
                    'diterima'            => ['text' => 'Diterima',             'class' => 'success'],
                    'ditolak'             => ['text' => 'Ditolak',              'class' => 'danger'],
                  ];
                  $bv  = $lv[$sv] ?? ['text' => $sv, 'class' => 'secondary'];
                  ?>
                  <span class="badge badge-<?= $bv['class'] ?>">
                    <?= htmlspecialchars($bv['text']) ?>
                  </span>
                </td>
              </tr>

              <?php if (!empty($pembayaran['tanggal_verifikasi'])): ?>
                <tr>
                  <td>Diverifikasi</td>
                  <td>: <?= tgl($pembayaran['tanggal_verifikasi']) ?></td>
                </tr>
              <?php endif; ?>

              <?php if (!empty($pembayaran['catatan_pembeli'])): ?>
                <tr>
                  <td>Catatan</td>
                  <td>: <?= htmlspecialchars($pembayaran['catatan_pembeli']) ?></td>
                </tr>
              <?php endif; ?>

              <?php if (!empty($pembayaran['catatan_admin'])): ?>
                <tr>
                  <td>Catatan Penjual</td>
                  <td>:
                    <span class="text-danger">
                      <?= htmlspecialchars($pembayaran['catatan_admin']) ?>
                    </span>
                  </td>
                </tr>
              <?php endif; ?>
            </table>

            <!-- Bukti Pembayaran -->
            <?php if (!empty($pembayaran['bukti_pembayaran'])): ?>
              <div class="bukti-section">
                <p class="section-label"><strong>Bukti Pembayaran</strong></p>
                <div class="bukti-preview">
                  <img src="../uploads/bukti_pembayaran/<?= htmlspecialchars($pembayaran['bukti_pembayaran']) ?>"
                    alt="Bukti Pembayaran"
                    class="bukti-img"
                    id="imgBukti"
                    onclick="zoomBukti()">
                  <button type="button" class="btn-zoom" onclick="zoomBukti()">
                    <i class="fas fa-search-plus"></i> Perbesar
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- TOMBOL NAVIGASI BAWAH -->
      <div class="bottom-actions">
        <?php if ($show_bayar): ?>
          <a href="tagihan.php?id_transaksi=<?= $id_transaksi ?>"
            class="btn-bayar">
            <i class="fas fa-credit-card"></i>
            <?= $sp === 'ditolak' ? 'Upload Ulang Bukti' : 'Bayar Sekarang' ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($transaksi['wa_penjual'])): ?>
          <a href="https://wa.me/<?= wa_phone($transaksi['wa_penjual']) ?>"
            target="_blank"
            class="btn-wa">
            <i class="fab fa-whatsapp"></i> Chat Penjual
          </a>
        <?php endif; ?>
      </div>

    </main>
  </div>

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
    // Ganti foto utama dari thumbnail
    function gantiGambar(el) {
      document.getElementById('fotoUtama').src = el.src;
      document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
      el.classList.add('active');
    }

    // Zoom bukti pembayaran
    function zoomBukti() {
      const src = document.getElementById('imgBukti')?.src;
      if (!src) return;
      document.getElementById('modalImg').src = src;
      document.getElementById('modalBukti').style.display = 'flex';
    }

    function tutupModal() {
      document.getElementById('modalBukti').style.display = 'none';
      document.getElementById('modalImg').src = '';
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') tutupModal();
    });
  </script>

</body>

</html>