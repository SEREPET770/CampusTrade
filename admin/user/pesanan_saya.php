<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "../config/Koneksi.php";
/** @var mysqli $koneksi */

function rupiah(float $angka): string
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

if (empty($_SESSION['id_user'])) {
  echo "<script>alert('Silakan login terlebih dahulu.');
          location='../auth/login.php';</script>";
  exit;
}

$id_pembeli = (int) $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'batalkan') {

  $id_transaksi  = (int) ($_POST['id_transaksi'] ?? 0);
  $balik_filter  = trim($_POST['current_filter'] ?? 'semua');

  if ($id_transaksi <= 0) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Pesanan tidak valid.'];
    header('Location: pesanan_saya.php');
    exit;
  }

  /** @var mysqli $koneksi */
  $cek = $koneksi->prepare("
        SELECT id_transaksi, id_produk, status_transaksi, status_pembayaran
        FROM transaksi
        WHERE id_transaksi = ? AND id_pembeli = ?
        LIMIT 1
    ");
  $cek->bind_param('ii', $id_transaksi, $id_pembeli);
  $cek->execute();
  $trx = $cek->get_result()->fetch_assoc();
  $cek->close();

  if (!$trx) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Pesanan tidak ditemukan.'];
    header('Location: pesanan_saya.php');
    exit;
  }

  $boleh_batal = $trx['status_transaksi'] === 'menunggu_pembayaran'
    && in_array($trx['status_pembayaran'], ['belum_bayar', 'ditolak'], true);

  if (!$boleh_batal) {
    $_SESSION['notif'] = ['tipe' => 'warning', 'pesan' => 'Pesanan tidak dapat dibatalkan karena sudah masuk proses pembayaran.'];
    header('Location: pesanan_saya.php' . ($balik_filter !== 'semua' ? '?bayar=' . urlencode($balik_filter) : ''));
    exit;
  }

  $koneksi->begin_transaction();
  try {
    $s1 = $koneksi->prepare("
            UPDATE transaksi
            SET status_transaksi = 'dibatalkan'
            WHERE id_transaksi = ? AND id_pembeli = ?
        ");
    $s1->bind_param('ii', $id_transaksi, $id_pembeli);
    $s1->execute();
    $s1->close();

    $s2 = $koneksi->prepare("
            UPDATE produk
            SET status_produk = 'tersedia'
            WHERE id_produk = ? AND status_produk = 'dipesan'
        ");
    $s2->bind_param('i', $trx['id_produk']);
    $s2->execute();
    $s2->close();

    $koneksi->commit();
    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Pesanan berhasil dibatalkan.'];
  } catch (Throwable $e) {
    $koneksi->rollback();
    error_log('[pesanan_saya] batal: ' . $e->getMessage());
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
  }

  header('Location: pesanan_saya.php' . ($balik_filter !== 'semua' ? '?bayar=' . urlencode($balik_filter) : ''));
  exit;
}

$filter_bayar = isset($_GET['bayar']) ? trim($_GET['bayar']) : 'semua';

$bayar_valid = [
  'semua',
  'belum_bayar',
  'menunggu_konfirmasi',
  'dibayar',
  'ditolak',
  'dibatalkan',
];

if (!in_array($filter_bayar, $bayar_valid, true)) {
  $filter_bayar = 'semua';
}

/**
 * @var mysqli $koneksi
 */
$stmt = $koneksi->prepare("
    SELECT
        COUNT(CASE WHEN status_transaksi != 'dibatalkan'                          THEN 1 END) AS semua,
        COUNT(CASE WHEN status_pembayaran = 'belum_bayar'         AND status_transaksi != 'dibatalkan' THEN 1 END) AS belum_bayar,
        COUNT(CASE WHEN status_pembayaran = 'menunggu_konfirmasi' AND status_transaksi != 'dibatalkan' THEN 1 END) AS menunggu_konfirmasi,
        COUNT(CASE WHEN status_pembayaran = 'dibayar'             AND status_transaksi != 'dibatalkan' THEN 1 END) AS dibayar,
        COUNT(CASE WHEN status_pembayaran = 'ditolak'              AND status_transaksi != 'dibatalkan' THEN 1 END) AS ditolak,
        COUNT(CASE WHEN status_transaksi = 'dibatalkan'                           THEN 1 END) AS dibatalkan
    FROM transaksi
    WHERE id_pembeli = ?
");
$stmt->bind_param('i', $id_pembeli);
$stmt->execute();
$counter = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($filter_bayar === 'dibatalkan') {
  $where_bayar = "AND t.status_transaksi = 'dibatalkan'";
} elseif ($filter_bayar === 'semua') {
  $where_bayar = "AND t.status_transaksi != 'dibatalkan'";
} else {
  $where_bayar = "AND t.status_transaksi != 'dibatalkan' AND t.status_pembayaran = ?";
}

$sql = "
    SELECT
        t.id_transaksi,
        t.kode_invoice,
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
        u.nama        AS nama_penjual,
        u.no_whatsapp AS wa_penjual,
        pb.status_verifikasi,
        pb.tanggal_upload,
        pg.metode_pengiriman
    FROM transaksi t
    JOIN produk  p  ON p.id_produk = t.id_produk
    JOIN users   u  ON u.id_user   = t.id_penjual
    LEFT JOIN pembayaran pb ON pb.id_transaksi = t.id_transaksi
    LEFT JOIN pengiriman pg ON pg.id_transaksi = t.id_transaksi
    WHERE t.id_pembeli = ?
    $where_bayar
    ORDER BY t.created_at DESC
";

if ($filter_bayar === 'semua' || $filter_bayar === 'dibatalkan') {
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param('i', $id_pembeli);
} else {
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param('is', $id_pembeli, $filter_bayar);
}

$stmt->execute();
$result       = $stmt->get_result();
$list_pesanan = [];
while ($row = $result->fetch_assoc()) {
  $list_pesanan[] = $row;
}
$stmt->close();

$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

$label_bayar = [
  'belum_bayar'          => ['text' => 'Belum Bayar',            'class' => 'warning'],
  'menunggu_konfirmasi'  => ['text' => 'Menunggu Konfirmasi',    'class' => 'info'],
  'dibayar'              => ['text' => 'Dibayar',                'class' => 'success'],
  'ditolak'              => ['text' => 'Ditolak',                'class' => 'danger'],
];

$label_transaksi = [
  'menunggu_pembayaran'  => ['text' => 'Menunggu Pembayaran',   'class' => 'warning'],
  'menunggu_verifikasi'  => ['text' => 'Menunggu Verifikasi',   'class' => 'info'],
  'diproses'             => ['text' => 'Diproses',               'class' => 'primary'],
  'dikirim'              => ['text' => 'Dikirim',                'class' => 'info'],
  'selesai'              => ['text' => 'Selesai',                'class' => 'success'],
  'dibatalkan'           => ['text' => 'Dibatalkan',             'class' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesanan Saya | CampusTrade</title>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/pesanan_saya.css">
</head>

<body>

  <div class="page-wrapper">

    <header class="top-header">
      <div class="header-left">
        <a href="user_dashboard.php" class="btn-back">
          <i class="fas fa-arrow-left"></i> Beranda
        </a>
        <h1 class="page-title">Pesanan Saya</h1>
      </div>
      <div class="header-right">
        <span class="username">
          <?= htmlspecialchars($_SESSION['nama'] ?? '') ?>
        </span>
      </div>
    </header>

    <main class="main-content">

      <?php if ($notif): ?>
        <div class="order-alert order-alert-<?= htmlspecialchars($notif['tipe']) ?>">
          <?= htmlspecialchars($notif['pesan']) ?>
        </div>
      <?php endif; ?>

      <div class="tab-filter">
        <a href="?bayar=semua"
          class="tab-item <?= $filter_bayar === 'semua' ? 'active' : '' ?>">
          Semua
          <span class="tab-count"><?= (int) $counter['semua'] ?></span>
        </a>
        <a href="?bayar=belum_bayar"
          class="tab-item <?= $filter_bayar === 'belum_bayar' ? 'active' : '' ?>">
          Belum Bayar
          <span class="tab-count"><?= (int) $counter['belum_bayar'] ?></span>
        </a>
        <a href="?bayar=menunggu_konfirmasi"
          class="tab-item <?= $filter_bayar === 'menunggu_konfirmasi' ? 'active' : '' ?>">
          Menunggu Konfirmasi
          <span class="tab-count"><?= (int) $counter['menunggu_konfirmasi'] ?></span>
        </a>
        <a href="?bayar=dibayar"
          class="tab-item <?= $filter_bayar === 'dibayar' ? 'active' : '' ?>">
          Dibayar
          <span class="tab-count"><?= (int) $counter['dibayar'] ?></span>
        </a>
        <a href="?bayar=ditolak"
          class="tab-item <?= $filter_bayar === 'ditolak' ? 'active' : '' ?>">
          Ditolak
          <span class="tab-count"><?= (int) $counter['ditolak'] ?></span>
        </a>
        <a href="?bayar=dibatalkan"
          class="tab-item <?= $filter_bayar === 'dibatalkan' ? 'active' : '' ?>">
          Dibatalkan
          <span class="tab-count"><?= (int) $counter['dibatalkan'] ?></span>
        </a>
      </div>

      <?php if (empty($list_pesanan)): ?>
        <div class="empty-state">
          <i class="fas fa-box-open"></i>
          <p>Belum ada pesanan di kategori ini.</p>
          <a href="user_produk.php" class="btn-belanja">
            Mulai Belanja
          </a>
        </div>

      <?php else: ?>
        <div class="order-list">
          <?php foreach ($list_pesanan as $item):

            $sp     = $item['status_pembayaran'];
            $bp     = $label_bayar[$sp]         ?? ['text' => $sp,  'class' => 'secondary'];

            $st     = $item['status_transaksi'];
            $bt     = $label_transaksi[$st]     ?? ['text' => $st,  'class' => 'secondary'];

            $foto = '../assets/images/no-image.png';
            if (!empty($item['foto_produk'])) {
              $path = __DIR__ . '/../uploads/produk/' . $item['foto_produk'];
              if (file_exists($path)) {
                $foto = '../uploads/produk/' . htmlspecialchars($item['foto_produk']);
              }
            }

            $boleh_batal = $st === 'menunggu_pembayaran'
              && in_array($sp, ['belum_bayar', 'ditolak'], true);
          ?>
            <div class="order-card">

              <div class="order-card-header">
                <div class="order-invoice">
                  <i class="fas fa-file-invoice"></i>
                  <span><?= htmlspecialchars($item['kode_invoice']) ?></span>
                </div>
                <div class="order-date">
                  <i class="fas fa-calendar-alt"></i>
                  <span><?= date('d M Y H:i', strtotime($item['created_at'])) ?></span>
                </div>
              </div>

              <div class="order-card-body">

                <div class="order-product-img">
                  <img src="<?= $foto ?>"
                    alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                </div>

                <div class="order-product-info">
                  <h3 class="order-product-name">
                    <?= htmlspecialchars($item['nama_produk']) ?>
                  </h3>
                  <p class="order-seller">
                    <i class="fas fa-store"></i>
                    <?= htmlspecialchars($item['nama_penjual']) ?>
                  </p>

                </div>

                <div class="order-status-col">
                  <p class="order-total">
                    <?= rupiah((float) $item['total_bayar']) ?>
                  </p>

                  <span class="badge badge-<?= $bp['class'] ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= htmlspecialchars($bp['text']) ?>
                  </span>

                  <span class="badge badge-<?= $bt['class'] ?> badge-outline">
                    <i class="fas fa-box"></i>
                    <?= htmlspecialchars($bt['text']) ?>
                  </span>
                </div>
              </div>

              <div class="order-card-footer">

                <?php if ($st !== 'dibatalkan' && in_array($sp, ['belum_bayar', 'ditolak'], true)): ?>
                  <a href="tagihan.php?id_transaksi=<?= (int) $item['id_transaksi'] ?>"
                    class="btn-bayar">
                    <i class="fas fa-credit-card"></i>
                    <?= $sp === 'ditolak' ? 'Upload Ulang Bukti' : 'Bayar Sekarang' ?>
                  </a>
                <?php endif; ?>

                <a href="detail_pesanan.php?id_transaksi=<?= (int) $item['id_transaksi'] ?>"
                  class="btn-detail">
                  <i class="fas fa-eye"></i> Lihat Detail
                </a>

                <?php if (!empty($item['wa_penjual'])): ?>
                  <a href="https://wa.me/<?= wa_phone($item['wa_penjual']) ?>"
                    target="_blank"
                    class="btn-wa">
                    <i class="fab fa-whatsapp"></i> Chat Penjual
                  </a>
                <?php endif; ?>

                <?php if ($boleh_batal): ?>
                  <form method="POST" action="pesanan_saya.php"
                    onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?')">
                    <input type="hidden" name="aksi" value="batalkan">
                    <input type="hidden" name="id_transaksi" value="<?= (int) $item['id_transaksi'] ?>">
                    <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter_bayar) ?>">
                    <button type="submit" class="btn-batal">
                      <i class="fas fa-times-circle"></i> Batalkan Pesanan
                    </button>
                  </form>
                <?php endif; ?>

              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </main>
  </div>

</body>

</html>