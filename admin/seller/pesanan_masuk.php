<?php
// seller/pesanan_masuk.php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// =========================
// VALIDASI LOGIN
// =========================
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_user'])) {
  header('Location: ../auth/login.php');
  exit;
}
if ($_SESSION['role'] !== 'user') {
  header('Location: ../auth/login.php');
  exit;
}

require_once '../config/Koneksi.php';
/** @var mysqli $koneksi */

$id_user = (int) $_SESSION['id_user'];

// =========================
// FILTER STATUS
// =========================
$filter = trim($_GET['status'] ?? 'semua');
$valid  = ['semua', 'belum_bayar', 'menunggu_konfirmasi', 'dibayar', 'ditolak', 'dibatalkan'];
if (!in_array($filter, $valid, true)) $filter = 'semua';

$search = trim($_GET['q'] ?? '');

// =========================
// COUNTER PER STATUS
// =========================
$stmt_cnt = $koneksi->prepare("
    SELECT
        COUNT(CASE WHEN status_transaksi != 'dibatalkan'                              THEN 1 END) AS semua,
        COUNT(CASE WHEN status_pembayaran = 'belum_bayar'         AND status_transaksi != 'dibatalkan' THEN 1 END) AS belum_bayar,
        COUNT(CASE WHEN status_pembayaran = 'menunggu_konfirmasi' AND status_transaksi != 'dibatalkan' THEN 1 END) AS menunggu_konfirmasi,
        COUNT(CASE WHEN status_pembayaran = 'dibayar'             AND status_transaksi != 'dibatalkan' THEN 1 END) AS dibayar,
        COUNT(CASE WHEN status_pembayaran = 'ditolak'              AND status_transaksi != 'dibatalkan' THEN 1 END) AS ditolak,
        COUNT(CASE WHEN status_transaksi = 'dibatalkan'                               THEN 1 END) AS dibatalkan
    FROM transaksi
    WHERE id_penjual = ?
");
$stmt_cnt->bind_param('i', $id_user);
$stmt_cnt->execute();
$counter = $stmt_cnt->get_result()->fetch_assoc();
$stmt_cnt->close();

// =========================
// QUERY PESANAN
// =========================
$where  = 't.id_penjual = ?';
$types  = 'i';
$params = [$id_user];

if ($filter === 'dibatalkan') {
  $where .= " AND t.status_transaksi = 'dibatalkan'";
} elseif ($filter !== 'semua') {
  $where   .= " AND t.status_pembayaran = ? AND t.status_transaksi != 'dibatalkan'";
  $types   .= 's';
  $params[] = $filter;
} else {
  $where .= " AND t.status_transaksi != 'dibatalkan'";
}

if ($search !== '') {
  $like     = '%' . $search . '%';
  $where   .= ' AND (p.nama_produk LIKE ? OR u.nama LIKE ? OR t.kode_invoice LIKE ?)';
  $types   .= 'sss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
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
         LIMIT 1)                     AS foto_produk,
        u.nama        AS nama_pembeli,
        u.no_whatsapp AS wa_pembeli,
        u.email       AS email_pembeli,
        pb.status_verifikasi,
        pb.tanggal_upload,
        pg.metode_pengiriman,
        pg.nama_penerima,
        pg.alamat
    FROM transaksi t
    JOIN produk  p  ON p.id_produk  = t.id_produk
    JOIN users   u  ON u.id_user    = t.id_pembeli
    LEFT JOIN pembayaran pb ON pb.id_transaksi = t.id_transaksi
    LEFT JOIN pengiriman  pg ON pg.id_transaksi = t.id_transaksi
    WHERE $where
    ORDER BY
        CASE t.status_pembayaran WHEN 'menunggu_konfirmasi' THEN 0 ELSE 1 END,
        t.created_at DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pesanan_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// =========================
// HELPER
// =========================
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}

$badge_map = [
  'belum_bayar'         => ['label' => 'Belum Bayar',         'cls' => 'warning'],
  'menunggu_konfirmasi' => ['label' => 'Menunggu Konfirmasi', 'cls' => 'info'],
  'dibayar'             => ['label' => 'Dibayar',             'cls' => 'success'],
  'ditolak'             => ['label' => 'Ditolak',             'cls' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesanan Masuk | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/seller.css">
</head>

<body>
  <?php include 'layout/topnav.php'; ?>
  <div class="seller-wrapper">
    <div class="seller-content">

      <!-- ════════ HEADER ════════ -->
      <div class="seller-page-header">
        <div>
          <h1>Pesanan Masuk</h1>
          <p class="seller-subtitle">Daftar semua pesanan dari pembeli untuk produk Anda.</p>
        </div>
        <a href="pembayaran_masuk.php" class="seller-btn-primary">💳 Verifikasi Pembayaran</a>
      </div>

      <!-- ════════ TAB FILTER ════════ -->
      <div class="seller-tab-filter">
        <?php
        $tabs = [
          'semua'               => 'Semua',
          'belum_bayar'         => 'Belum Bayar',
          'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
          'dibayar'             => 'Dibayar',
          'ditolak'             => 'Ditolak',
          'dibatalkan'          => 'Dibatalkan',
        ];
        foreach ($tabs as $key => $label):
          $q = http_build_query(array_filter(['status' => $key === 'semua' ? '' : $key, 'q' => $search]));
        ?>
          <a href="pesanan_masuk.php<?= $q ? '?' . $q : '' ?>"
            class="seller-tab <?= $filter === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="seller-tab-count"><?= (int)$counter[$key] ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ════════ SEARCH ════════ -->
      <div class="seller-toolbar">
        <form method="GET" action="pesanan_masuk.php" class="seller-search-form">
          <?php if ($filter !== 'semua'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
          <?php endif; ?>
          <div class="seller-search-box">
            <svg class="seller-search-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
            </svg>
            <input type="text" name="q"
              value="<?= htmlspecialchars($search) ?>"
              placeholder="Cari invoice, nama produk, atau pembeli...">
            <button type="submit" class="seller-search-btn">Cari</button>
            <?php if ($search): ?>
              <a href="pesanan_masuk.php<?= $filter !== 'semua' ? '?status=' . urlencode($filter) : '' ?>"
                class="seller-search-reset">✕</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ════════ DAFTAR PESANAN ════════ -->
      <?php if (empty($pesanan_list)): ?>
        <div class="seller-empty">
          <div class="seller-empty-icon">🛒</div>
          <p>Belum ada pesanan<?= $filter !== 'semua' ? ' dengan status ' . htmlspecialchars($tabs[$filter]) : '' ?>.</p>
        </div>
      <?php else: ?>
        <div class="seller-pesanan-list">
          <?php foreach ($pesanan_list as $row):
            $badge = $row['status_transaksi'] === 'dibatalkan'
              ? ['label' => 'Dibatalkan', 'cls' => 'secondary']
              : ($badge_map[$row['status_pembayaran']] ?? ['label' => $row['status_pembayaran'], 'cls' => 'secondary']);
            $foto_src = !empty($row['foto_produk'])
              ? '../uploads/produk/' . htmlspecialchars($row['foto_produk'])
              : '../assets/img/no-image.png';
            $wa_link = !empty($row['wa_pembeli'])
              ? 'https://wa.me/' . wa_phone($row['wa_pembeli'])
              : '#';
          ?>
            <div class="seller-pesanan-card">

              <!-- Header card: invoice + status -->
              <div class="seller-pesanan-card-head">
                <div>
                  <span class="seller-invoice"><?= htmlspecialchars($row['kode_invoice']) ?></span>
                  <span class="seller-pesanan-date"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></span>
                </div>
                <span class="seller-badge seller-badge-<?= $badge['cls'] ?>"><?= $badge['label'] ?></span>
              </div>

              <!-- Body card: foto + detail -->
              <div class="seller-pesanan-card-body">

                <img src="<?= $foto_src ?>"
                  alt="<?= htmlspecialchars($row['nama_produk']) ?>"
                  class="seller-pesanan-img"
                  onerror="this.src='../assets/img/no-image.png'">

                <div class="seller-pesanan-detail">
                  <h3><?= htmlspecialchars($row['nama_produk']) ?></h3>

                  <div class="seller-pesanan-meta">
                    <div class="seller-meta-item">
                      <span class="seller-meta-label">Pembeli</span>
                      <span><?= htmlspecialchars($row['nama_pembeli']) ?></span>
                    </div>
                    <?php if (!empty($row['wa_pembeli'])): ?>
                      <div class="seller-meta-item">
                        <span class="seller-meta-label">WhatsApp</span>
                        <a href="<?= $wa_link ?>" target="_blank" class="seller-wa-link">
                          📱 <?= htmlspecialchars($row['wa_pembeli']) ?>
                        </a>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($row['metode_pengiriman'])): ?>
                      <div class="seller-meta-item">
                        <span class="seller-meta-label">Pengiriman</span>
                        <span><?= htmlspecialchars($row['metode_pengiriman']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($row['nama_penerima'])): ?>
                      <div class="seller-meta-item">
                        <span class="seller-meta-label">Penerima</span>
                        <span><?= htmlspecialchars($row['nama_penerima']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($row['alamat'])): ?>
                      <div class="seller-meta-item">
                        <span class="seller-meta-label">Alamat</span>
                        <span><?= nl2br(htmlspecialchars($row['alamat'])) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Total + aksi -->
                <div class="seller-pesanan-right">
                  <div class="seller-pesanan-total">
                    <span class="seller-meta-label">Total</span>
                    <strong><?= rupiah((float)$row['total_bayar']) ?></strong>
                  </div>
                  <?php if ($row['status_pembayaran'] === 'menunggu_konfirmasi'): ?>
                    <a href="pembayaran_masuk.php"
                      class="seller-btn-primary seller-btn-sm">
                      ✅ Perlu Verifikasi
                    </a>
                  <?php endif; ?>
                  <a href="<?= $wa_link ?>" target="_blank" class="seller-btn-secondary seller-btn-sm">
                    💬 Chat
                  </a>
                </div>

              </div><!-- /.seller-pesanan-card-body -->
            </div><!-- /.seller-pesanan-card -->
          <?php endforeach; ?>
        </div><!-- /.seller-pesanan-list -->
      <?php endif; ?>

    </div><!-- /.seller-content -->
  </div><!-- /.seller-wrapper -->

</body>

</html>