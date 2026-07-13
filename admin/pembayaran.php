<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_user'])) {
  header('Location: auth/login.php');
  exit;
}
if ($_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

require_once 'config/Koneksi.php';
/** @var mysqli $koneksi */

// ════════════════════════════════════════════════════════════════════════════
// FILTER & SEARCH
// ════════════════════════════════════════════════════════════════════════════
$filter_status = trim($_GET['status'] ?? 'semua');
$valid_status  = ['semua', 'menunggu_verifikasi', 'diterima', 'ditolak'];
if (!in_array($filter_status, $valid_status, true)) $filter_status = 'semua';

$search = trim($_GET['q'] ?? '');

// ════════════════════════════════════════════════════════════════════════════
// COUNTER UNTUK TAB
// ════════════════════════════════════════════════════════════════════════════
$counter = $koneksi->query("
    SELECT
        COUNT(*)                                                              AS semua,
        COUNT(CASE WHEN status_verifikasi = 'menunggu_verifikasi' THEN 1 END) AS menunggu_verifikasi,
        COUNT(CASE WHEN status_verifikasi = 'diterima'            THEN 1 END) AS diterima,
        COUNT(CASE WHEN status_verifikasi = 'ditolak'              THEN 1 END) AS ditolak
    FROM pembayaran
")->fetch_assoc();

// ════════════════════════════════════════════════════════════════════════════
// QUERY UTAMA — pembayaran + transaksi + produk + penjual + pembeli
// ════════════════════════════════════════════════════════════════════════════
$where  = '1=1';
$types  = '';
$params = [];

if ($filter_status !== 'semua') {
  $where   .= ' AND pb.status_verifikasi = ?';
  $types   .= 's';
  $params[] = $filter_status;
}

if ($search !== '') {
  $like     = '%' . $search . '%';
  $where   .= ' AND (p.nama_produk LIKE ? OR upj.nama LIKE ? OR upb.nama LIKE ? OR t.kode_invoice LIKE ?)';
  $types   .= 'ssss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql = "
    SELECT
        pb.id_pembayaran,
        pb.nominal,
        pb.bukti_pembayaran,
        pb.catatan_pembeli,
        pb.catatan_admin,
        pb.status_verifikasi,
        pb.tanggal_upload,
        pb.tanggal_verifikasi,
        t.id_transaksi,
        t.kode_invoice,
        t.total_bayar,
        t.status_pembayaran,
        t.status_transaksi,
        p.nama_produk,
        mp.nama_metode,
        mp.jenis        AS jenis_metode,
        upj.nama         AS nama_penjual,
        upb.nama         AS nama_pembeli
    FROM pembayaran pb
    JOIN transaksi t  ON t.id_transaksi = pb.id_transaksi
    JOIN produk    p  ON p.id_produk    = t.id_produk
    JOIN users     upj ON upj.id_user   = t.id_penjual
    JOIN users     upb ON upb.id_user   = t.id_pembeli
    LEFT JOIN metode_pembayaran mp ON mp.id_metode = pb.id_metode
    WHERE $where
    ORDER BY
        CASE pb.status_verifikasi WHEN 'menunggu_verifikasi' THEN 0 ELSE 1 END,
        pb.tanggal_upload DESC
";

$stmt = $koneksi->prepare($sql);
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ════════════════════════════════════════════════════════════════════════════
// NOTIF DARI SESSION
// ════════════════════════════════════════════════════════════════════════════
$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

// ════════════════════════════════════════════════════════════════════════════
// HELPER
// ════════════════════════════════════════════════════════════════════════════
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}

$badge_map = [
  'menunggu_verifikasi' => 'warning',
  'diterima'             => 'success',
  'ditolak'              => 'danger',
];

$label_status = [
  'menunggu_verifikasi' => 'Menunggu Verifikasi',
  'diterima'             => 'Diterima',
  'ditolak'              => 'Ditolak',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran | CampusTrade Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="assets/css/pembayaran_admin.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>

  <div class="dash-page">

    <div class="dash-page-header">
      <h1>Riwayat Pembayaran</h1>
      <p>Pantau seluruh pembayaran yang masuk dari pembeli ke penjual. Verifikasi dilakukan oleh penjual masing-masing.</p>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($notif): ?>
      <div class="bay-alert bay-alert-<?= htmlspecialchars($notif['tipe']) ?>">
        <?= htmlspecialchars($notif['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- FILTER TAB -->
    <div class="bay-tabs">
      <?php
      $tabs_ordered = ['semua' => 'Semua'] + $label_status;
      foreach ($tabs_ordered as $val => $label):
        $act = $filter_status === $val ? 'active' : '';
        $cnt = (int) ($counter[$val] ?? 0);
        $qs  = http_build_query(array_filter(['status' => $val, 'q' => $search]));
      ?>
        <a href="pembayaran.php?<?= $qs ?>" class="bay-tab <?= $act ?>">
          <?= $label ?>
          <?php if ($cnt > 0): ?>
            <span class="bay-tab-count <?= $val === 'menunggu_verifikasi' ? 'urgent' : '' ?>">
              <?= $cnt ?>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- SEARCH -->
    <form method="GET" action="pembayaran.php" class="bay-search-form">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
      <div class="bay-search-box">
        <svg class="bay-search-icon" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
        </svg>
        <input type="text" name="q"
          value="<?= htmlspecialchars($search) ?>"
          placeholder="Cari produk, penjual, pembeli, atau no. invoice...">
        <button type="submit" class="bay-search-btn">Cari</button>
        <?php if ($search): ?>
          <a href="pembayaran.php?status=<?= htmlspecialchars($filter_status) ?>"
            class="bay-search-reset" title="Hapus pencarian">✕</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- INFO HASIL -->
    <?php if ($search || $filter_status !== 'semua'): ?>
      <p class="bay-result-info">
        Menampilkan <strong><?= count($rows) ?></strong> pembayaran
        <?= $filter_status !== 'semua' ? '· Status: <strong>' . htmlspecialchars($label_status[$filter_status]) . '</strong>' : '' ?>
        <?= $search ? '· Pencarian: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
      </p>
    <?php endif; ?>

    <!-- TABEL -->
    <div class="bay-table-wrap">
      <table class="bay-table">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Produk</th>
            <th>Penjual</th>
            <th>Pembeli</th>
            <th>Nominal</th>
            <th>Metode</th>
            <th>Bukti</th>
            <th>Diupload</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="9" class="bay-empty">
                Tidak ada riwayat pembayaran
                <?= $filter_status !== 'semua' ? 'berstatus ' . htmlspecialchars($label_status[$filter_status]) : '' ?>
                <?= $search ? 'dengan kata kunci "' . htmlspecialchars($search) . '"' : '' ?>.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r):
              $bcls = $badge_map[$r['status_verifikasi']] ?? 'secondary';
            ?>
              <tr>
                <td class="bay-td-invoice"><?= htmlspecialchars($r['kode_invoice']) ?></td>

                <td class="bay-nama"><?= htmlspecialchars($r['nama_produk']) ?></td>

                <td><?= htmlspecialchars($r['nama_penjual']) ?></td>

                <td><?= htmlspecialchars($r['nama_pembeli']) ?></td>

                <td class="bay-harga"><?= rupiah((float) $r['nominal']) ?></td>

                <td class="bay-td-metode">
                  <?= htmlspecialchars($r['nama_metode'] ?? '-') ?>
                </td>

                <td>
                  <?php if (!empty($r['bukti_pembayaran'])):
                    $ext = strtolower(pathinfo($r['bukti_pembayaran'], PATHINFO_EXTENSION));
                    $src = 'uploads/bukti_pembayaran/' . htmlspecialchars($r['bukti_pembayaran']);
                  ?>
                    <?php if ($ext === 'pdf'): ?>
                      <a href="<?= $src ?>" target="_blank" class="bay-link-detail">Lihat PDF</a>
                    <?php else: ?>
                      <img src="<?= $src ?>"
                        alt="Bukti bayar"
                        class="bay-foto"
                        onclick="bukaModal('<?= $src ?>', '<?= htmlspecialchars(addslashes($r['nama_produk'])) ?>')"
                        title="Klik untuk perbesar">
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="bay-locked">Belum upload</span>
                  <?php endif; ?>
                </td>

                <td class="bay-td-tgl">
                  <?= date('d M Y', strtotime($r['tanggal_upload'])) ?>
                  <div class="bay-sub"><?= date('H:i', strtotime($r['tanggal_upload'])) ?></div>
                </td>

                <td>
                  <span class="bay-badge bay-badge-<?= $bcls ?>">
                    <?= $label_status[$r['status_verifikasi']] ?? ucfirst($r['status_verifikasi']) ?>
                  </span>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /.dash-page -->

  <!-- Modal foto -->
  <div id="buktiModal" class="bay-modal-overlay" onclick="tutupModal()">
    <div class="bay-modal-box" onclick="event.stopPropagation()">
      <button class="bay-modal-close" onclick="tutupModal()">✕</button>
      <img id="buktiModalImg" src="" alt="" class="bay-modal-img">
      <p id="buktiModalCaption" class="bay-modal-caption"></p>
    </div>
  </div>

  <?php include 'layout/footer.php'; ?>

  <script>
    function bukaModal(src, caption) {
      document.getElementById('buktiModalImg').src = src;
      document.getElementById('buktiModalCaption').textContent = caption;
      document.getElementById('buktiModal').classList.add('open');
    }

    function tutupModal() {
      document.getElementById('buktiModal').classList.remove('open');
    }

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') tutupModal();
    });
  </script>

</body>

</html>