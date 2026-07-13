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
// HANDLE POST — PRG pattern (Post-Redirect-Get)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $aksi      = trim($_POST['aksi']      ?? '');
  $id_produk = (int) ($_POST['id_produk'] ?? 0);

  if ($id_produk <= 0) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'ID produk tidak valid.'];
    header('Location: produk.php');
    exit;
  }

  // ── Setujui produk ───────────────────────────────────────────────────────
  if ($aksi === 'setujui') {
    $stmt = $koneksi->prepare("
            UPDATE produk
            SET    status_produk = 'tersedia',
                   status_tayang = 'aktif'
            WHERE  id_produk      = ?
              AND  status_produk  = 'menunggu'
        ");
    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $_SESSION['notif'] = $affected > 0
      ? ['tipe' => 'success', 'pesan' => 'Produk disetujui dan sekarang aktif tayang.']
      : ['tipe' => 'warning', 'pesan' => 'Produk tidak ditemukan atau sudah diproses.'];

    // ── Tolak produk ─────────────────────────────────────────────────────────
  } elseif ($aksi === 'tolak') {
    $stmt = $koneksi->prepare("
            UPDATE produk
            SET    status_produk = 'ditolak',
                   status_tayang = 'nonaktif'
            WHERE  id_produk     = ?
              AND  status_produk = 'menunggu'
        ");
    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $_SESSION['notif'] = $affected > 0
      ? ['tipe' => 'warning', 'pesan' => 'Produk berhasil ditolak.']
      : ['tipe' => 'warning', 'pesan' => 'Produk tidak ditemukan atau sudah diproses.'];

    // ── Hapus produk ─────────────────────────────────────────────────────────
  } elseif ($aksi === 'hapus') {

    // Cek transaksi dulu
    $cek = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE id_produk = ?");
    $cek->bind_param('i', $id_produk);
    $cek->execute();
    $total_trx = (int) $cek->get_result()->fetch_assoc()['total'];
    $cek->close();

    if ($total_trx > 0) {
      $_SESSION['notif'] = [
        'tipe'  => 'error',
        'pesan' => 'Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi.'
      ];
    } else {
      $koneksi->begin_transaction();
      try {
        // Hapus gambar_produk dulu (foreign key constraint)
        $del_gbr = $koneksi->prepare("DELETE FROM gambar_produk WHERE id_produk = ?");
        $del_gbr->bind_param('i', $id_produk);
        $del_gbr->execute();
        $del_gbr->close();

        // Hapus produk
        $del = $koneksi->prepare("DELETE FROM produk WHERE id_produk = ?");
        $del->bind_param('i', $id_produk);
        $del->execute();
        $del->close();

        $koneksi->commit();
        $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Produk dan gambar terkait berhasil dihapus.'];
      } catch (Throwable $e) {
        $koneksi->rollback();
        error_log('[admin/produk hapus] ' . $e->getMessage());
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Terjadi kesalahan saat menghapus produk.'];
      }
    }
  }

  // Redirect kembali dengan filter yang sama
  $qs = http_build_query(array_filter([
    'status' => $_POST['current_status'] ?? '',
    'q'      => $_POST['current_q']      ?? '',
  ]));
  header('Location: produk.php' . ($qs ? '?' . $qs : ''));
  exit;
}

// ════════════════════════════════════════════════════════════════════════════
// GET — Tampilkan halaman
// ════════════════════════════════════════════════════════════════════════════

$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

// ── Filter ───────────────────────────────────────────────────────────────────
$filter_status = trim($_GET['status'] ?? 'semua');
$status_valid  = ['semua', 'menunggu', 'tersedia', 'dipesan', 'terjual', 'ditolak'];
if (!in_array($filter_status, $status_valid, true)) $filter_status = 'semua';

$search = trim($_GET['q'] ?? '');

// ── Counter per status (1 query) ─────────────────────────────────────────────
$counter = $koneksi->query("
    SELECT
        COUNT(*)                                                      AS semua,
        COUNT(CASE WHEN status_produk = 'menunggu'  THEN 1 END)      AS menunggu,
        COUNT(CASE WHEN status_produk = 'tersedia'  THEN 1 END)      AS tersedia,
        COUNT(CASE WHEN status_produk = 'dipesan'   THEN 1 END)      AS dipesan,
        COUNT(CASE WHEN status_produk = 'terjual'   THEN 1 END)      AS terjual,
        COUNT(CASE WHEN status_produk = 'ditolak'   THEN 1 END)      AS ditolak
    FROM produk
")->fetch_assoc();

// ── Query produk ─────────────────────────────────────────────────────────────
$where  = '1=1';
$params = [];
$types  = '';

if ($filter_status !== 'semua') {
  $where   .= ' AND p.status_produk = ?';
  $types   .= 's';
  $params[] = $filter_status;
}

if ($search !== '') {
  $like     = '%' . $search . '%';
  $where   .= ' AND (p.nama_produk LIKE ? OR u.nama LIKE ?)';
  $types   .= 'ss';
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
        p.status_tayang,
        p.created_at,
        p.penulis_penerbit,
        p.brand,
        k.nama_kategori,
        u.id_user       AS id_penjual,
        u.nama          AS nama_penjual,
        u.no_whatsapp   AS wa_penjual,
        (SELECT gp.image_path
         FROM gambar_produk gp
         WHERE gp.id_produk = p.id_produk
         LIMIT 1)                                    AS foto,
        (SELECT COUNT(*)
         FROM transaksi t
         WHERE t.id_produk = p.id_produk)            AS jumlah_transaksi
    FROM produk p
    JOIN users           u ON u.id_user     = p.id_user
    JOIN kategori_barang k ON k.id_kategori = p.id_kategori
    WHERE $where
    ORDER BY
        CASE p.status_produk WHEN 'menunggu' THEN 0 ELSE 1 END,
        p.created_at DESC
";

$stmt = $koneksi->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helper ───────────────────────────────────────────────────────────────────
function rupiah(float $n): string
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}

$badge_map = [
  'menunggu' => 'warning',
  'tersedia' => 'success',
  'dipesan'  => 'info',
  'terjual'  => 'teal',
  'ditolak'  => 'danger',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Produk | CampusTrade Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="assets/css/produk_admin.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>

  <div class="dash-page">

    <div class="dash-page-header">
      <h1>Kelola Produk</h1>
      <p>Verifikasi dan pantau produk yang diunggah pengguna</p>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($notif): ?>
      <div class="prd-alert prd-alert-<?= htmlspecialchars($notif['tipe']) ?>">
        <?= htmlspecialchars($notif['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- FILTER TAB -->
    <div class="prd-tabs">
      <?php
      $tabs = [
        'semua'    => 'Semua',
        'menunggu' => 'Menunggu',
        'tersedia' => 'Tersedia',
        'dipesan'  => 'Dipesan',
        'terjual'  => 'Terjual',
        'ditolak'  => 'Ditolak',
      ];
      foreach ($tabs as $val => $label):
        $act = $filter_status === $val ? 'active' : '';
        $cnt = (int) ($counter[$val] ?? 0);
        $qs  = http_build_query(array_filter(['status' => $val, 'q' => $search]));
      ?>
        <a href="produk.php?<?= $qs ?>" class="prd-tab <?= $act ?>">
          <?= $label ?>
          <?php if ($cnt > 0): ?>
            <span class="prd-tab-count <?= $val === 'menunggu' ? 'urgent' : '' ?>">
              <?= $cnt ?>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- SEARCH -->
    <form method="GET" action="produk.php" class="prd-search-form">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
      <div class="prd-search-box">
        <svg class="prd-search-icon" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
        </svg>
        <input type="text" name="q"
          value="<?= htmlspecialchars($search) ?>"
          placeholder="Cari nama produk atau nama penjual...">
        <button type="submit" class="prd-search-btn">Cari</button>
        <?php if ($search): ?>
          <a href="produk.php?status=<?= htmlspecialchars($filter_status) ?>"
            class="prd-search-reset" title="Hapus pencarian">✕</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- INFO HASIL -->
    <?php if ($search || $filter_status !== 'semua'): ?>
      <p class="prd-result-info">
        Menampilkan <strong><?= count($rows) ?></strong> produk
        <?= $filter_status !== 'semua' ? 'dengan status <strong>' . ucfirst($filter_status) . '</strong>' : '' ?>
        <?= $search ? ' · Pencarian: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
      </p>
    <?php endif; ?>

    <!-- TABEL PRODUK -->
    <div class="prd-table-wrap">
      <table class="prd-table">
        <thead>
          <tr>
            <th style="width:56px"></th>
            <th>Produk</th>
            <th>Penjual</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th>Kondisi</th>
            <th>Status</th>
            <th>Upload</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="9" class="prd-empty">
                Tidak ada produk
                <?= $filter_status !== 'semua' ? 'berstatus ' . ucfirst($filter_status) : '' ?>
                <?= $search ? 'dengan kata kunci "' . htmlspecialchars($search) . '"' : '' ?>.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $p):
              $sp   = $p['status_produk'];
              $bcls = $badge_map[$sp] ?? 'secondary';

              $foto_url = 'assets/images/no-image.png';
              if (!empty($p['foto'])) {
                $foto_url = 'uploads/produk/' . htmlspecialchars($p['foto']);
              }
            ?>
              <tr id="row-<?= (int) $p['id_produk'] ?>">

                <!-- Foto -->
                <td class="prd-td-foto">
                  <img src="<?= $foto_url ?>"
                    alt="foto"
                    class="prd-foto"
                    onclick="bukaModal('<?= $foto_url ?>', '<?= htmlspecialchars(addslashes($p['nama_produk'])) ?>')"
                    title="Klik untuk perbesar">
                </td>

                <!-- Nama produk -->
                <td>
                  <div class="prd-nama">
                    <?= htmlspecialchars($p['nama_produk']) ?>
                  </div>
                  <?php if (!empty($p['penulis_penerbit'])): ?>
                    <div class="prd-sub"><?= htmlspecialchars($p['penulis_penerbit']) ?></div>
                  <?php elseif (!empty($p['brand'])): ?>
                    <div class="prd-sub"><?= htmlspecialchars($p['brand']) ?></div>
                  <?php endif; ?>
                  <a href="detail_produk.php?id=<?= (int) $p['id_produk'] ?>"
                    class="prd-link-detail" target="_blank">Lihat detail →</a>
                </td>

                <!-- Penjual -->
                <td>
                  <div class="prd-penjual"><?= htmlspecialchars($p['nama_penjual']) ?></div>
                  <?php if ($p['wa_penjual']): ?>
                    <a href="https://wa.me/<?= wa_phone($p['wa_penjual']) ?>"
                      target="_blank" class="prd-wa">
                      Whatsapp
                    </a>
                  <?php endif; ?>
                </td>

                <!-- Kategori -->
                <td class="prd-td-kat">
                  <?= htmlspecialchars($p['nama_kategori']) ?>
                </td>

                <!-- Harga -->
                <td class="prd-harga">
                  <?= rupiah((float) $p['harga']) ?>
                </td>

                <!-- Kondisi -->
                <td>
                  <span class="prd-badge prd-badge-<?= $p['kondisi'] === 'Baru' ? 'baru' : 'bekas' ?>">
                    <?= $p['kondisi'] ?>
                  </span>
                </td>

                <!-- Status -->
                <td>
                  <span class="prd-badge prd-badge-<?= $bcls ?>">
                    <?= ucfirst($sp) ?>
                  </span>
                </td>

                <!-- Tanggal -->
                <td class="prd-td-tgl">
                  <?= date('d M Y', strtotime($p['created_at'])) ?>
                  <div class="prd-sub"><?= date('H:i', strtotime($p['created_at'])) ?></div>
                </td>

                <!-- Aksi -->
                <td>
                  <div class="prd-aksi">

                    <?php if ($sp === 'menunggu'): ?>
                      <!-- Setujui -->
                      <form method="POST" onsubmit="return confirm('Setujui produk ini? Produk akan langsung aktif tayang.')">
                        <input type="hidden" name="aksi" value="setujui">
                        <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                        <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter_status) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="prd-btn prd-btn-setujui">✓ Setujui</button>
                      </form>
                      <!-- Tolak -->
                      <form method="POST" onsubmit="return confirm('Tolak produk ini?')">
                        <input type="hidden" name="aksi" value="tolak">
                        <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                        <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter_status) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="prd-btn prd-btn-tolak">✕ Tolak</button>
                      </form>
                    <?php endif; ?>

                    <!-- Hapus -->
                    <?php if ((int) $p['jumlah_transaksi'] === 0): ?>
                      <form method="POST" onsubmit="return confirm('Hapus produk ini beserta semua gambarnya? Tindakan tidak dapat dibatalkan.')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                        <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter_status) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="prd-btn prd-btn-hapus">🗑 Hapus</button>
                      </form>
                    <?php else: ?>
                      <span class="prd-locked" title="Produk ini memiliki <?= (int) $p['jumlah_transaksi'] ?> transaksi">
                        🔒 Ada transaksi
                      </span>
                    <?php endif; ?>

                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /.dash-page -->

  <!-- Modal foto -->
  <div id="fotoModal" class="prd-modal-overlay" onclick="tutupModal()">
    <div class="prd-modal-box" onclick="event.stopPropagation()">
      <button class="prd-modal-close" onclick="tutupModal()">✕</button>
      <img id="fotoModalImg" src="" alt="" class="prd-modal-img">
      <p id="fotoModalCaption" class="prd-modal-caption"></p>
    </div>
  </div>

  <?php include 'layout/footer.php'; ?>

  <script src="assets/js/produk_admin.js"></script>

</body>

</html>