<?php
// seller/produk.php
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

$id_user   = (int) $_SESSION['id_user'];
$nama_user = $_SESSION['nama'] ?? 'Penjual';

// =========================
// HANDLE POST — HAPUS PRODUK
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi      = trim($_POST['aksi'] ?? '');
  $id_produk = (int) ($_POST['id_produk'] ?? 0);

  if ($id_produk <= 0) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'ID produk tidak valid.'];
  } elseif ($aksi === 'hapus') {
    $cek_produk = $koneksi->prepare("SELECT status_produk FROM produk WHERE id_produk = ? AND id_user = ?");
    $cek_produk->bind_param('ii', $id_produk, $id_user);
    $cek_produk->execute();
    $produk = $cek_produk->get_result()->fetch_assoc();
    $cek_produk->close();

    if (!$produk) {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Produk tidak ditemukan atau bukan milik Anda.'];
    } elseif (($produk['status_produk'] ?? '') === 'terjual') {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Produk yang sudah terjual tidak dapat dihapus.'];
    } else {
      $cek_trx = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE id_produk = ? AND status_transaksi != 'dibatalkan'");
      $cek_trx->bind_param('i', $id_produk);
      $cek_trx->execute();
      $total_trx = (int) $cek_trx->get_result()->fetch_assoc()['total'];
      $cek_trx->close();

      if ($total_trx > 0) {
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Produk tidak dapat dihapus karena sudah memiliki transaksi aktif.'];
      } else {
        $koneksi->begin_transaction();
        try {
          $del_gbr = $koneksi->prepare("DELETE FROM gambar_produk WHERE id_produk = ?");
          $del_gbr->bind_param('i', $id_produk);
          $del_gbr->execute();
          $del_gbr->close();

          $del = $koneksi->prepare("DELETE FROM produk WHERE id_produk = ? AND id_user = ?");
          $del->bind_param('ii', $id_produk, $id_user);
          $del->execute();
          $del->close();

          $koneksi->commit();
          $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Produk berhasil dihapus.'];
        } catch (Throwable $e) {
          $koneksi->rollback();
          error_log('[seller/produk hapus] ' . $e->getMessage());
          $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Terjadi kesalahan saat menghapus produk.'];
        }
      }
    }
  } else {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Aksi tidak valid.'];
  }

  $qs = http_build_query(array_filter([
    'status' => $_POST['current_status'] ?? '',
    'q'      => $_POST['current_q']      ?? '',
  ]));
  header('Location: produk.php' . ($qs ? '?' . $qs : ''));
  exit;
}

$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

// =========================
// FILTER & SEARCH
// =========================
$filter_status = trim($_GET['status'] ?? 'semua');
$status_valid  = ['semua', 'menunggu', 'tersedia', 'dipesan', 'terjual', 'ditolak'];
if (!in_array($filter_status, $status_valid, true)) $filter_status = 'semua';

$search = trim($_GET['q'] ?? '');

// =========================
// COUNTER PER STATUS (1 query)
// =========================
$stmt_cnt = $koneksi->prepare("
    SELECT
        COUNT(*)                                                      AS semua,
        COUNT(CASE WHEN status_produk = 'menunggu'  THEN 1 END)     AS menunggu,
        COUNT(CASE WHEN status_produk = 'tersedia'  THEN 1 END)     AS tersedia,
        COUNT(CASE WHEN status_produk = 'dipesan'   THEN 1 END)     AS dipesan,
        COUNT(CASE WHEN status_produk = 'terjual'   THEN 1 END)     AS terjual,
        COUNT(CASE WHEN status_produk = 'ditolak'   THEN 1 END)     AS ditolak
    FROM produk
    WHERE id_user = ?
");
$stmt_cnt->bind_param('i', $id_user);
$stmt_cnt->execute();
$counter = $stmt_cnt->get_result()->fetch_assoc();
$stmt_cnt->close();

// =========================
// QUERY PRODUK
// =========================
$where  = 'p.id_user = ?';
$types  = 'i';
$params = [$id_user];

if ($filter_status !== 'semua') {
  $where   .= ' AND p.status_produk = ?';
  $types   .= 's';
  $params[] = $filter_status;
}

if ($search !== '') {
  $like     = '%' . $search . '%';
  $where   .= ' AND (p.nama_produk LIKE ? OR p.brand LIKE ? OR p.penulis_penerbit LIKE ?)';
  $types   .= 'sss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql = "
    SELECT
        p.id_produk,
        p.nama_produk,
        p.harga,
        p.kondisi,
        p.status_produk,
        p.created_at,
        p.brand,
        p.penulis_penerbit,
        p.kelebihan,
        p.kekurangan,
        p.deskripsi,
        p.id_lokasi,
        k.nama_kategori,
        l.nama_lokasi,
        (SELECT gp.image_path
         FROM gambar_produk gp
         WHERE gp.id_produk = p.id_produk
         LIMIT 1) AS foto,
        (SELECT COUNT(*)
         FROM transaksi t
         WHERE t.id_produk = p.id_produk
           AND t.status_transaksi != 'dibatalkan') AS total_transaksi
    FROM produk p
    LEFT JOIN kategori_barang k ON k.id_kategori = p.id_kategori
    LEFT JOIN lokasi           l ON l.id_lokasi   = p.id_lokasi
    WHERE $where
    ORDER BY
        CASE p.status_produk WHEN 'menunggu' THEN 0 ELSE 1 END,
        p.created_at DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$produk_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// =========================
// HELPER
// =========================
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}
$daftar_lokasi = $koneksi->query("SELECT id_lokasi, nama_lokasi FROM lokasi ORDER BY nama_lokasi ASC")
  ->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Produk Saya | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/seller.css">
</head>

<body>
  <?php include 'layout/topnav.php'; ?>
  <div class="seller-wrapper">
    <div class="seller-content">

      <!-- ════════ HEADER ════════ -->
      <div class="seller-page-header">
        <div>
          <h1>Produk Saya</h1>
          <p class="seller-subtitle">Kelola semua produk yang Anda jual di CampusTrade.</p>
        </div>
        <a href="../user/tambah_produk.php" class="seller-btn-primary">+ Tambah Produk</a>
      </div>

      <!-- ════════ COUNTER BADGE ════════ -->
      <div class="seller-tab-filter">
        <?php
        $tabs = [
          'semua'    => 'Semua',
          'menunggu' => 'Menunggu',
          'tersedia' => 'Tersedia',
          'dipesan'  => 'Dipesan',
          'terjual'  => 'Terjual',
          'ditolak'  => 'Ditolak',
        ];
        foreach ($tabs as $key => $label):
          $q = http_build_query(array_filter(['status' => $key === 'semua' ? '' : $key, 'q' => $search]));
        ?>
          <a href="produk.php<?= $q ? '?' . $q : '' ?>"
            class="seller-tab <?= $filter_status === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="seller-tab-count"><?= (int)$counter[$key] ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ════════ SEARCH ════════ -->
      <div class="seller-toolbar">
        <form method="GET" action="produk.php" class="seller-search-form">
          <?php if ($filter_status !== 'semua'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
          <?php endif; ?>
          <div class="seller-search-box">
            <svg class="seller-search-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
            </svg>
            <input type="text" name="q"
              value="<?= htmlspecialchars($search) ?>"
              placeholder="Cari nama produk, brand, atau penulis...">
            <button type="submit" class="seller-search-btn">Cari</button>
            <?php if ($search): ?>
              <a href="produk.php<?= $filter_status !== 'semua' ? '?status=' . urlencode($filter_status) : '' ?>"
                class="seller-search-reset">✕</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ════════ TABEL PRODUK ════════ -->
      <?php if ($notif): ?>
        <div class="seller-alert seller-alert-<?= htmlspecialchars($notif['tipe']) ?>">
          <?= htmlspecialchars($notif['pesan']) ?>
        </div>
      <?php endif; ?>

      <div class="seller-table-wrap">
        <?php if (empty($produk_list)): ?>
          <div class="seller-empty">
            <div class="seller-empty-icon">📦</div>
            <p>Belum ada produk<?= $filter_status !== 'semua' ? ' dengan status ' . htmlspecialchars($tabs[$filter_status]) : '' ?>.</p>
            <a href="../user/tambah_produk.php" class="seller-btn-primary" style="margin-top:12px;">+ Tambah Produk</a>
          </div>
        <?php else: ?>
          <table class="seller-table" id="produkTable">
            <thead>
              <tr>
                <th>Foto</th>
                <th>Nama Produk</th>
                <th>Lokasi Kampus</th>
                <th>Harga</th>
                <th>Brand / Penulis</th>
                <th>Status</th>
                <th>Upload</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produk_list as $p):
                $status = $p['status_produk'];
                $badge_map = [
                  'menunggu' => 'warning',
                  'tersedia' => 'success',
                  'dipesan'  => 'info',
                  'terjual'  => 'teal',
                  'ditolak'  => 'danger',
                ];
                $badge_cls = $badge_map[$status] ?? 'secondary';

                // FIX: gunakan penulis_penerbit, bukan penulis
                $brand_penulis = !empty($p['brand'])
                  ? $p['brand']
                  : ($p['penulis_penerbit'] ?? '-');

                $foto_src = !empty($p['foto'])
                  ? '../uploads/produk/' . htmlspecialchars($p['foto'])
                  : '../assets/img/no-image.png';
              ?>
                <tr data-id="<?= (int)$p['id_produk'] ?>"
                  data-search="<?= htmlspecialchars(strtolower($p['nama_produk'] . ' ' . $p['brand'] . ' ' . $p['penulis_penerbit'])) ?>">
                  <td>
                    <img src="<?= $foto_src ?>"
                      alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                      class="seller-prod-thumb"
                      onerror="this.src='../assets/img/no-image.png'">
                  </td>
                  <td>
                    <!-- View mode -->
                    <span class="view-text"><strong><?= htmlspecialchars($p['nama_produk']) ?></strong></span>
                    <!-- Edit mode -->
                    <input type="text" class="edit-input seller-edit-input" data-field="nama_produk"
                      value="<?= htmlspecialchars($p['nama_produk']) ?>">
                  </td>
                  <td>
                    <span class="view-text"><?= htmlspecialchars($p['nama_lokasi'] ?? '-') ?></span>
                    <select class="edit-input seller-edit-input" data-field="id_lokasi">
                      <option value="">— Pilih Lokasi —</option>
                      <?php foreach ($daftar_lokasi as $lok): ?>
                        <option value="<?= (int) $lok['id_lokasi'] ?>"
                          <?= (int) $p['id_lokasi'] === (int) $lok['id_lokasi'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($lok['nama_lokasi']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <span class="view-text"><?= rupiah((float)$p['harga']) ?></span>
                    <input type="text" class="edit-input seller-edit-input" data-field="harga"
                      value="<?= (int)$p['harga'] ?>">
                  </td>
                  <td>
                    <span class="view-text"><?= htmlspecialchars($brand_penulis) ?></span>
                    <!-- Edit brand -->
                    <input type="text" class="edit-input seller-edit-input edit-brand" data-field="brand"
                      value="<?= htmlspecialchars($p['brand'] ?? '') ?>"
                      placeholder="Brand">
                    <!-- Edit penulis_penerbit -->
                    <input type="text" class="edit-input seller-edit-input edit-penulis" data-field="penulis"
                      value="<?= htmlspecialchars($p['penulis_penerbit'] ?? '') ?>"
                      placeholder="Penulis/Penerbit">
                  </td>
                  <td>
                    <span class="seller-badge seller-badge-<?= $badge_cls ?>">
                      <?= ucfirst($status) ?>
                    </span>
                  </td>
                  <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                  <td class="seller-action-cell">
                    <?php if ($status === 'terjual'): ?>
                      <button type="button" class="seller-btn-sm seller-btn-secondary" disabled>Terjual</button>
                    <?php else: ?>
                      <button type="button" class="seller-btn-sm btn-inline-edit">✏ Edit</button>
                      <button type="button" class="seller-btn-sm seller-btn-save btn-inline-save" style="display:none;">💾 Simpan</button>
                      <button type="button" class="seller-btn-sm seller-btn-cancel btn-inline-cancel" style="display:none;">✕ Batal</button>
                    <?php endif; ?>

                    <form method="POST" action="produk.php" class="seller-inline-form" onsubmit="return confirm('Hapus produk ini?');">
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id_produk" value="<?= (int)$p['id_produk'] ?>">
                      <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter_status) ?>">
                      <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                      <button type="submit"
                        class="seller-btn-sm seller-btn-danger"
                        <?= $status === 'terjual' || (int)$p['total_transaksi'] > 0 ? 'disabled' : '' ?>
                        title="<?= $status === 'terjual' ? 'Produk sudah terjual, tidak bisa dihapus.' : ((int)$p['total_transaksi'] > 0 ? 'Produk memiliki transaksi, tidak bisa dihapus.' : 'Hapus produk') ?>">
                        <?= $status === 'terjual' ? 'Terjual' : ((int)$p['total_transaksi'] > 0 ? 'Ada Transaksi' : '🗑 Hapus') ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div><!-- /.seller-table-wrap -->

    </div><!-- /.seller-content -->
  </div><!-- /.seller-wrapper -->

  <!-- ════════ TOAST NOTIFIKASI ════════ -->
  <div id="sellerToast" class="seller-toast" style="display:none;"></div>

  <script src="../assets/js/seller_produk.js"></script>

</body>

</html>