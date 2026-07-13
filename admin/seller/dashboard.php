<?php
// seller/dashboard.php
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
// QUERY STATISTIK PRODUK PENJUAL
// =========================
$stat_produk = $koneksi->prepare("
    SELECT
        COUNT(*)                                                          AS total,
        COUNT(CASE WHEN status_produk = 'menunggu'    THEN 1 END)         AS menunggu,
        COUNT(CASE WHEN status_produk = 'tersedia'    THEN 1 END)         AS tersedia,
        COUNT(CASE WHEN status_produk = 'dipesan'     THEN 1 END)         AS dipesan,
        COUNT(CASE WHEN status_produk = 'terjual'     THEN 1 END)         AS terjual,
        COUNT(CASE WHEN status_produk = 'dibatalkan'  THEN 1 END)         AS dibatalkan,
        COUNT(CASE WHEN status_produk = 'ditolak'     THEN 1 END)         AS ditolak
    FROM produk
    WHERE id_user = ?
");
$stat_produk->bind_param('i', $id_user);
$stat_produk->execute();
$sp = $stat_produk->get_result()->fetch_assoc();
$stat_produk->close();

// =========================
// QUERY STATISTIK PESANAN MASUK
// =========================
$stat_pesanan = $koneksi->prepare("
    SELECT
        COUNT(*)                                                                       AS total,
        COUNT(CASE WHEN t.status_pembayaran = 'menunggu_konfirmasi' THEN 1 END)       AS menunggu,
        COUNT(CASE WHEN t.status_pembayaran = 'dibayar'             THEN 1 END)       AS dibayar,
        COUNT(CASE WHEN t.status_transaksi = 'dibatalkan'            THEN 1 END)       AS dibatalkan,
        COALESCE(SUM(CASE WHEN t.status_pembayaran = 'dibayar' THEN t.total_bayar END), 0) AS pendapatan
    FROM transaksi t
    WHERE t.id_penjual = ?
");
$stat_pesanan->bind_param('i', $id_user);
$stat_pesanan->execute();
$so = $stat_pesanan->get_result()->fetch_assoc();
$stat_pesanan->close();

// =========================
// QUERY 5 PESANAN TERBARU
// =========================
$stmt_pesanan = $koneksi->prepare("
    SELECT
        t.id_transaksi,
        t.kode_invoice,
        t.total_bayar,
        t.status_pembayaran,
        t.status_transaksi,
        t.created_at,
        p.nama_produk,
        u.nama AS nama_pembeli
    FROM transaksi t
    JOIN produk p ON p.id_produk = t.id_produk
    JOIN users  u ON u.id_user   = t.id_pembeli
    WHERE t.id_penjual = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");
$stmt_pesanan->bind_param('i', $id_user);
$stmt_pesanan->execute();
$pesanan_terbaru = $stmt_pesanan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pesanan->close();

// =========================
// QUERY PRODUK MENUNGGU VERIFIKASI
// =========================
$stmt_review = $koneksi->prepare("
    SELECT id_produk, nama_produk, harga, created_at
    FROM produk
    WHERE id_user = ? AND status_produk = 'menunggu'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt_review->bind_param('i', $id_user);
$stmt_review->execute();
$produk_review = $stmt_review->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_review->close();

// =========================
// HELPER
// =========================
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Toko | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/seller.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>
  <div class="seller-wrapper">
    <div class="seller-content">

      <!-- ════════ HEADER ════════ -->
      <div class="seller-page-header">
        <div>
          <h1>Dashboard Toko</h1>
          <p class="seller-subtitle">Selamat datang, <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong> · <?= date('d F Y') ?></p>
        </div>
        <a href="../user/tambah_produk.php" class="seller-btn-primary">+ Tambah Produk</a>
      </div>

      <!-- ════════ HIGHLIGHT: TOTAL PENDAPATAN ════════ -->
      <div class="seller-highlight-card">
        <div>
          <div class="seller-highlight-label">Total Pendapatan Terkonfirmasi</div>
          <div class="seller-highlight-val"><?= rupiah((float) $so['pendapatan']) ?></div>
        </div>
        <div class="seller-highlight-icon">💰</div>
      </div>

      <!-- ════════ STATISTIK PRODUK ════════ -->
      <div class="seller-sec-head">
        <h2> Produk Saya</h2>
        <a href="produk.php">Kelola Produk →</a>
      </div>
      <div class="seller-stat-grid">
        <div class="seller-stat-card" onclick="location='produk.php'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['total'] ?></div>
            <div class="seller-stat-lbl">Total Produk</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=menunggu'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['menunggu'] ?></div>
            <div class="seller-stat-lbl">Menunggu Review</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=tersedia'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['tersedia'] ?></div>
            <div class="seller-stat-lbl">Aktif / Tersedia</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=dipesan'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['dipesan'] ?></div>
            <div class="seller-stat-lbl">Sedang Dipesan</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=terjual'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['terjual'] ?></div>
            <div class="seller-stat-lbl">Terjual</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=dibatalkan'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['dibatalkan'] ?></div>
            <div class="seller-stat-lbl">Dibatalkan</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='produk.php?status=ditolak'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$sp['ditolak'] ?></div>
            <div class="seller-stat-lbl">Ditolak Admin</div>
          </div>
        </div>
      </div>

      <!-- ════════ STATISTIK PESANAN ════════ -->
      <div class="seller-sec-head">
        <h2> Pesanan Masuk</h2>
        <a href="pesanan_masuk.php">Lihat Semua →</a>
      </div>
      <div class="seller-stat-grid seller-stat-grid--3">
        <div class="seller-stat-card" onclick="location='pesanan_masuk.php'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$so['total'] ?></div>
            <div class="seller-stat-lbl">Total Pesanan</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='pembayaran_masuk.php?status=menunggu_konfirmasi'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$so['menunggu'] ?></div>
            <div class="seller-stat-lbl">Menunggu Konfirmasi</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='pembayaran_masuk.php?status=dibayar'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$so['dibayar'] ?></div>
            <div class="seller-stat-lbl">Pembayaran Diterima</div>
          </div>
        </div>
        <div class="seller-stat-card" onclick="location='pesanan_masuk.php?status=dibatalkan'">
          <div class="seller-stat-info">
            <div class="seller-stat-val"><?= (int)$so['dibatalkan'] ?></div>
            <div class="seller-stat-lbl">Pesanan Dibatalkan</div>
          </div>
        </div>
      </div>

      <!-- ════════ TABEL: PESANAN TERBARU ════════ -->
      <div class="seller-sec-head" style="margin-top:32px;">
        <h2>Pesanan Terbaru</h2>
        <a href="pesanan_masuk.php">Lihat Semua →</a>
      </div>
      <div class="seller-table-wrap">
        <?php if (empty($pesanan_terbaru)): ?>
          <div class="seller-empty">
            <div class="seller-empty-icon">🛒</div>
            <p>Belum ada pesanan masuk.</p>
          </div>
        <?php else: ?>
          <table class="seller-table">
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Produk</th>
                <th>Pembeli</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pesanan_terbaru as $row):
                if ($row['status_transaksi'] === 'dibatalkan') {
                  $badge = ['label' => 'Dibatalkan', 'cls' => 'secondary'];
                } else {
                  $sp_map = [
                    'belum_bayar'         => ['label' => 'Belum Bayar',         'cls' => 'warning'],
                    'menunggu_konfirmasi' => ['label' => 'Menunggu Konfirmasi', 'cls' => 'info'],
                    'dibayar'             => ['label' => 'Dibayar',             'cls' => 'success'],
                    'ditolak'             => ['label' => 'Ditolak',             'cls' => 'danger'],
                  ];
                  $badge = $sp_map[$row['status_pembayaran']] ?? ['label' => $row['status_pembayaran'], 'cls' => 'secondary'];
                }
              ?>
                <tr>
                  <td><code><?= htmlspecialchars($row['kode_invoice']) ?></code></td>
                  <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                  <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                  <td><?= rupiah((float)$row['total_bayar']) ?></td>
                  <td><span class="seller-badge seller-badge-<?= $badge['cls'] ?>"><?= $badge['label'] ?></span></td>
                  <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                  <td>
                    <a href="pesanan_masuk.php?id=<?= (int)$row['id_transaksi'] ?>" class="seller-btn-sm">Detail</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- ════════ TABEL: PRODUK MENUNGGU REVIEW ════════ -->
      <?php if (!empty($produk_review)): ?>
        <div class="seller-sec-head" style="margin-top:32px;">
          <h2>⏳ Produk Menunggu Review Admin</h2>
          <a href="produk.php?status=menunggu">Lihat Semua →</a>
        </div>
        <div class="seller-table-wrap">
          <table class="seller-table">
            <thead>
              <tr>
                <th>Nama Produk</th>
                <th>Harga</th>
                <th>Tanggal Upload</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produk_review as $pr): ?>
                <tr>
                  <td><?= htmlspecialchars($pr['nama_produk']) ?></td>
                  <td><?= rupiah((float)$pr['harga']) ?></td>
                  <td><?= date('d M Y', strtotime($pr['created_at'])) ?></td>
                  <td>
                    <a href="produk.php" class="seller-btn-sm">Kelola</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

</body>

</html>