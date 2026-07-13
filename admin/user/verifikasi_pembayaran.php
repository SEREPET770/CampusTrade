<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "../config/Koneksi.php";
/** @var mysqli $koneksi */
// HELPER //
function rupiah(float $angka): string
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ─────────────────────────────────────────
// 1. VALIDASI LOGIN
// ─────────────────────────────────────────
if (empty($_SESSION['id_user'])) {
  echo "<script>alert('Silakan login terlebih dahulu.');location='../../auth/login.php';</script>";
  exit;
}

$id_penjual = (int) $_SESSION['id_user'];

// ─────────────────────────────────────────
// 2. HANDLE POST — TERIMA / TOLAK PEMBAYARAN
// ─────────────────────────────────────────
$pesan_sukses = '';
$pesan_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi          = isset($_POST['aksi'])          ? trim($_POST['aksi'])          : '';
  $id_pembayaran = isset($_POST['id_pembayaran']) ? (int) $_POST['id_pembayaran'] : 0;
  $id_transaksi  = isset($_POST['id_transaksi'])  ? (int) $_POST['id_transaksi']  : 0;
  $catatan_admin = isset($_POST['catatan_admin']) ? trim($_POST['catatan_admin']) : '';

  // Validasi aksi
  if (!in_array($aksi, ['terima', 'tolak'], true)) {
    $pesan_error = 'Aksi tidak valid.';
    goto render;
  }

  if ($id_pembayaran <= 0 || $id_transaksi <= 0) {
    $pesan_error = 'Data pembayaran tidak valid.';
    goto render;
  }

  // Validasi catatan wajib saat menolak
  if ($aksi === 'tolak' && $catatan_admin === '') {
    $pesan_error = 'Alasan penolakan wajib diisi.';
    goto render;
  }

  /** @var mysqli $koneksi */
  // Validasi kepemilikan: pastikan transaksi ini memang milik penjual yang login
  $cek = $koneksi->prepare("
        SELECT t.id_transaksi
        FROM transaksi t
        JOIN pembayaran pb ON pb.id_transaksi = t.id_transaksi
        WHERE pb.id_pembayaran = ?
          AND t.id_transaksi   = ?
          AND t.id_penjual     = ?
          AND pb.status_verifikasi = 'menunggu_verifikasi'
        LIMIT 1
    ");
  $cek->bind_param('iii', $id_pembayaran, $id_transaksi, $id_penjual);
  $cek->execute();
  $cek->store_result();

  if ($cek->num_rows === 0) {
    $cek->close();
    $pesan_error = 'Data tidak ditemukan atau sudah diverifikasi sebelumnya.';
    goto render;
  }
  $cek->close();

  // Tentukan nilai update berdasarkan aksi
  if ($aksi === 'terima') {
    $status_verifikasi  = 'diterima';
    $status_pembayaran  = 'dibayar';
  } else {
    $status_verifikasi  = 'ditolak';
    $status_pembayaran  = 'belum_bayar';
  }

  $koneksi->begin_transaction();

  try {
    // UPDATE tabel pembayaran
    $stmt = $koneksi->prepare("
            UPDATE pembayaran
            SET status_verifikasi  = ?,
                catatan_admin      = ?,
                tanggal_verifikasi = NOW()
            WHERE id_pembayaran    = ?
        ");
    $stmt->bind_param('ssi', $status_verifikasi, $catatan_admin, $id_pembayaran);
    $stmt->execute();
    $stmt->close();

    // UPDATE tabel transaksi — hanya status_pembayaran, BUKAN status_transaksi
    $stmt = $koneksi->prepare("
    UPDATE transaksi
    SET status_transaksi = 'diproses'
    WHERE id_transaksi = ?
      AND id_penjual   = ?
");
    $stmt->bind_param('ii', $id_transaksi, $id_penjual);
    $stmt->execute();
    $stmt->close();

    if ($aksi === 'terima') {
      $stmt = $koneksi->prepare("
        UPDATE produk p
        JOIN transaksi t ON t.id_produk = p.id_produk
        SET p.status_produk = 'terjual'
        WHERE t.id_transaksi = ?
          AND p.status_produk IN ('tersedia', 'dipesan')
      ");
      $stmt->bind_param('i', $id_transaksi);
      $stmt->execute();
      $stmt->close();
    }

    $koneksi->commit();

    $pesan_sukses = $aksi === 'terima'
      ? 'Pembayaran berhasil diterima.'
      : 'Pembayaran ditolak. Pembeli dapat mengupload ulang bukti.';
  } catch (Exception $e) {
    $koneksi->rollback();
    error_log('[verifikasi_pembayaran] ' . $e->getMessage());
    $pesan_error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
  }
}

render:

// ─────────────────────────────────────────
// 3. AMBIL DATA FILTER
// ─────────────────────────────────────────
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'menunggu_verifikasi';

$status_valid = ['menunggu_verifikasi', 'diterima', 'ditolak', 'semua'];
if (!in_array($filter_status, $status_valid, true)) {
  $filter_status = 'menunggu_verifikasi';
}

// ─────────────────────────────────────────
// 4. AMBIL DAFTAR PEMBAYARAN MASUK
// ─────────────────────────────────────────
if ($filter_status === 'semua') {
  $where_status = "1=1";
  $bind_types   = 'i';
  $bind_values  = [$id_penjual];
} else {
  $where_status = "pb.status_verifikasi = ?";
  $bind_types   = 'si';
  $bind_values  = [$filter_status, $id_penjual];
}

$sql = "
    SELECT
        pb.id_pembayaran,
        pb.id_transaksi,
        pb.nominal,
        pb.bukti_pembayaran,
        pb.catatan_pembeli,
        pb.catatan_admin,
        pb.status_verifikasi,
        pb.tanggal_upload,
        pb.tanggal_verifikasi,
        t.kode_invoice,
        t.total_bayar,
        t.status_transaksi,
        t.status_pembayaran,
        p.nama_produk,
        (SELECT gp.image_path
         FROM gambar_produk gp
         WHERE gp.id_produk = p.id_produk
         LIMIT 1) AS foto_produk,
        mp.nama_metode,
        mp.jenis         AS jenis_metode,
        u.nama           AS nama_pembeli,
        u.no_whatsapp    AS wa_pembeli,
        u.email          AS email_pembeli
    FROM pembayaran pb
    JOIN transaksi        t  ON t.id_transaksi  = pb.id_transaksi
    JOIN produk           p  ON p.id_produk     = t.id_produk
    JOIN metode_pembayaran mp ON mp.id_metode   = pb.id_metode
    JOIN users            u  ON u.id_user       = t.id_pembeli
    WHERE $where_status
      AND t.id_penjual = ?
    ORDER BY pb.tanggal_upload DESC
";

/** @var mysqli $koneksi */
if ($filter_status === 'semua') {
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param('i', $id_penjual);
} else {
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param('si', $filter_status, $id_penjual);
}

$stmt->execute();
$result       = $stmt->get_result();
$list_bayar   = [];
while ($row = $result->fetch_assoc()) {
  $list_bayar[] = $row;
}
$stmt->close();

// ─────────────────────────────────────────
// 5. HITUNG RINGKASAN
// ─────────────────────────────────────────
$stmt = $koneksi->prepare("
    SELECT
        COUNT(CASE WHEN pb.status_verifikasi = 'menunggu_verifikasi' THEN 1 END) AS menunggu,
        COUNT(CASE WHEN pb.status_verifikasi = 'diterima'            THEN 1 END) AS diterima,
        COUNT(CASE WHEN pb.status_verifikasi = 'ditolak'             THEN 1 END) AS ditolak,
        COUNT(*) AS semua
    FROM pembayaran pb
    JOIN transaksi t ON t.id_transaksi = pb.id_transaksi
    WHERE t.id_penjual = ?
      AND t.status_transaksi != 'dibatalkan'
");
$stmt->bind_param('i', $id_penjual);
$stmt->execute();
$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Label & badge untuk status verifikasi
$label_status = [
  'menunggu_verifikasi' => ['text' => 'Menunggu Verifikasi', 'class' => 'warning'],
  'diterima'            => ['text' => 'Diterima',            'class' => 'success'],
  'ditolak'             => ['text' => 'Ditolak',             'class' => 'danger'],
  'belum_bayar'         => ['text' => 'Belum Bayar',         'class' => 'secondary'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verifikasi Pembayaran | CampusTrade</title>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/verifikasi_pembayaran.css">
</head>

<body>

  <div class="page-wrapper">

    <!-- HEADER -->
    <header class="top-header">
      <div class="header-left">
        <a href="../user/user_dashboard.php" class="btn-back">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <h1 class="page-title">Verifikasi Pembayaran</h1>
      </div>
      <div class="header-right">
        <span class="username"><?= htmlspecialchars($_SESSION['nama'] ?? '') ?></span>
      </div>
    </header>

    <main class="main-content">

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

      <!-- RINGKASAN -->
      <div class="summary-cards">
        <a href="?status=menunggu_verifikasi"
          class="summary-card <?= $filter_status === 'menunggu_verifikasi' ? 'active' : '' ?>">
          <i class="fas fa-clock"></i>
          <div>
            <span class="summary-count"><?= (int) $ringkasan['menunggu'] ?></span>
            <span class="summary-label">Menunggu</span>
          </div>
        </a>
        <a href="?status=diterima"
          class="summary-card <?= $filter_status === 'diterima' ? 'active' : '' ?>">
          <i class="fas fa-check-circle"></i>
          <div>
            <span class="summary-count"><?= (int) $ringkasan['diterima'] ?></span>
            <span class="summary-label">Diterima</span>
          </div>
        </a>
        <a href="?status=ditolak"
          class="summary-card <?= $filter_status === 'ditolak' ? 'active' : '' ?>">
          <i class="fas fa-times-circle"></i>
          <div>
            <span class="summary-count"><?= (int) $ringkasan['ditolak'] ?></span>
            <span class="summary-label">Ditolak</span>
          </div>
        </a>
        <a href="?status=semua"
          class="summary-card <?= $filter_status === 'semua' ? 'active' : '' ?>">
          <i class="fas fa-list"></i>
          <div>
            <span class="summary-count"><?= (int) $ringkasan['semua'] ?></span>
            <span class="summary-label">Semua</span>
          </div>
        </a>
      </div>

      <!-- DAFTAR PEMBAYARAN -->
      <?php if (empty($list_bayar)): ?>
        <div class="empty-state">
          <p>Tidak ada pembayaran untuk ditampilkan.</p>
        </div>
      <?php else: ?>
        <div class="payment-list">
          <?php foreach ($list_bayar as $item):
            $sv     = $item['status_verifikasi'];
            $badge  = $label_status[$sv] ?? ['text' => $sv, 'class' => 'secondary'];

            $foto_produk = '../../assets/images/no-image.png';
            if (!empty($item['foto_produk'])) {
              $path = __DIR__ . '/../uploads/produk/' . $item['foto_produk'];
              if (file_exists($path)) {
                $foto_produk = '../uploads/produk/' . htmlspecialchars($item['foto_produk']);
              }
            }

            $bukti_url = !empty($item['bukti_pembayaran'])
              ? '../uploads/bukti_pembayaran/' . htmlspecialchars($item['bukti_pembayaran'])
              : '';
          ?>
            <div class="payment-card" id="card-<?= (int) $item['id_pembayaran'] ?>">

              <!-- HEADER CARD -->
              <div class="payment-card-header">
                <div class="invoice-info">
                  <span class="invoice-number">
                    <i class="fas fa-file-invoice"></i>
                    <?= htmlspecialchars($item['kode_invoice']) ?>
                  </span>
                  <span class="upload-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d M Y H:i', strtotime($item['tanggal_upload'])) ?>
                  </span>
                </div>
                <span class="badge badge-<?= $badge['class'] ?>">
                  <?= htmlspecialchars($badge['text']) ?>
                </span>
              </div>

              <!-- BODY CARD -->
              <div class="payment-card-body">

                <!-- KOLOM KIRI: Produk & Pembeli -->
                <div class="payment-col-left">

                  <!-- Produk -->
                  <div class="section-label">
                    <i class="fas fa-box"></i> Produk
                  </div>
                  <div class="product-row">
                    <img src="<?= $foto_produk ?>"
                      alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                      class="product-thumb">
                    <div>
                      <p class="product-name">
                        <?= htmlspecialchars($item['nama_produk']) ?>
                      </p>
                      <p class="product-nominal">
                        <?= rupiah((float) $item['nominal']) ?>
                      </p>
                      <p class="metode-bayar">
                        via <?= htmlspecialchars($item['nama_metode']) ?>
                        (<?= htmlspecialchars($item['jenis_metode']) ?>)
                      </p>
                    </div>
                  </div>

                  <!-- Data Pembeli -->
                  <div class="section-label" style="margin-top:16px;">
                    <i class="fas fa-user"></i> Data Pembeli
                  </div>
                  <table class="info-table">
                    <tr>
                      <td>Nama</td>
                      <td>: <strong><?= htmlspecialchars($item['nama_pembeli']) ?></strong></td>
                    </tr>
                    <tr>
                      <td>WhatsApp</td>
                      /** @var string|null $item['wa_penjual'] */
                      <td>:
                        <a href="https://wa.me/<?= wa_phone($item['wa_pembeli']) ?>"
                          target="_blank" class="link-wa">
                          <i class="fab fa-whatsapp"></i>
                          <?= htmlspecialchars($item['wa_pembeli']) ?>
                        </a>
                      </td>
                    </tr>
                    <tr>
                      <td>Email</td>
                      <td>: <?= htmlspecialchars($item['email_pembeli']) ?></td>
                    </tr>
                  </table>

                  <?php if (!empty($item['catatan_pembeli'])): ?>
                    <div class="catatan-box catatan-pembeli">
                      <i class="fas fa-comment-dots"></i>
                      <strong>Catatan Pembeli:</strong>
                      <p><?= htmlspecialchars($item['catatan_pembeli']) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($item['catatan_admin']) && $sv === 'ditolak'): ?>
                    <div class="catatan-box catatan-tolak">
                      <i class="fas fa-times-circle"></i>
                      <strong>Alasan Penolakan:</strong>
                      <p><?= htmlspecialchars($item['catatan_admin']) ?></p>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($item['tanggal_verifikasi'])): ?>
                    <p class="tanggal-verifikasi">
                      <i class="fas fa-check"></i>
                      Diverifikasi: <?= date('d M Y H:i', strtotime($item['tanggal_verifikasi'])) ?>
                    </p>
                  <?php endif; ?>
                </div>

                <!-- KOLOM KANAN: Bukti Pembayaran -->
                <div class="payment-col-right">
                  <div class="section-label">
                    <i class="fas fa-image"></i> Bukti Pembayaran
                  </div>

                  <?php if ($bukti_url !== ''): ?>
                    <div class="bukti-wrapper">
                      <img src="<?= $bukti_url ?>"
                        alt="Bukti Pembayaran"
                        class="bukti-img"
                        id="bukti-<?= (int) $item['id_pembayaran'] ?>"
                        onclick="zoomBukti('<?= $bukti_url ?>')">
                      <button type="button"
                        class="btn-zoom"
                        onclick="zoomBukti('<?= $bukti_url ?>')">
                        <i class="fas fa-search-plus"></i> Perbesar
                      </button>
                    </div>
                  <?php else: ?>
                    <p class="no-bukti">Bukti belum diupload.</p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- FOOTER CARD: Tombol Aksi (hanya jika menunggu verifikasi) -->
              <?php if ($sv === 'menunggu_verifikasi'): ?>
                <div class="payment-card-footer">

                  <!-- Form Terima -->
                  <form method="POST"
                    action="verifikasi_pembayaran.php?status=<?= urlencode($filter_status) ?>"
                    class="form-aksi"
                    onsubmit="return konfirmasi('terima')">
                    <input type="hidden" name="aksi" value="terima">
                    <input type="hidden" name="id_pembayaran" value="<?= (int) $item['id_pembayaran'] ?>">
                    <input type="hidden" name="id_transaksi" value="<?= (int) $item['id_transaksi'] ?>">
                    <button type="submit" class="btn-terima">
                      <i class="fas fa-check"></i> Terima Pembayaran
                    </button>
                  </form>

                  <!-- Form Tolak (toggle panel) -->
                  <div class="tolak-section">
                    <button type="button"
                      class="btn-tolak"
                      onclick="toggleTolak(<?= (int) $item['id_pembayaran'] ?>)">
                      <i class="fas fa-times"></i> Tolak
                    </button>

                    <div class="tolak-panel" id="tolak-<?= (int) $item['id_pembayaran'] ?>"
                      style="display:none;">
                      <form method="POST"
                        action="verifikasi_pembayaran.php?status=<?= urlencode($filter_status) ?>"
                        class="form-aksi"
                        onsubmit="return konfirmasi('tolak')">
                        <input type="hidden" name="aksi" value="tolak">
                        <input type="hidden" name="id_pembayaran" value="<?= (int) $item['id_pembayaran'] ?>">
                        <input type="hidden" name="id_transaksi" value="<?= (int) $item['id_transaksi'] ?>">

                        <div class="form-group">
                          <label>Alasan Penolakan <span class="required">*</span></label>
                          <textarea name="catatan_admin"
                            rows="3"
                            placeholder="Contoh: Nominal tidak sesuai, bukti tidak terbaca..."
                            required></textarea>
                        </div>

                        <div class="tolak-actions">
                          <button type="submit" class="btn-tolak-submit">
                            <i class="fas fa-paper-plane"></i> Kirim Penolakan
                          </button>
                          <button type="button"
                            class="btn-batal-tolak"
                            onclick="toggleTolak(<?= (int) $item['id_pembayaran'] ?>)">
                            Batal
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>

                </div>
              <?php endif; ?>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

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
    // Zoom bukti pembayaran
    function zoomBukti(src) {
      document.getElementById('modalImg').src = src;
      document.getElementById('modalBukti').style.display = 'flex';
    }

    function tutupModal() {
      document.getElementById('modalBukti').style.display = 'none';
      document.getElementById('modalImg').src = '';
    }

    // Tutup modal dengan ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') tutupModal();
    });

    // Toggle panel form penolakan
    function toggleTolak(id) {
      const panel = document.getElementById('tolak-' + id);
      if (!panel) return;
      panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }

    // Konfirmasi sebelum submit
    function konfirmasi(aksi) {
      const pesan = aksi === 'terima' ?
        'Terima pembayaran ini? Status transaksi akan diperbarui.' :
        'Tolak pembayaran ini? Pembeli akan diminta upload ulang.';
      return confirm(pesan);
    }
  </script>

</body>

</html>