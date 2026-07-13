<?php
// seller/pembayaran_masuk.php
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
// HANDLE POST — TERIMA / TOLAK PEMBAYARAN
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $aksi         = trim($_POST['aksi']         ?? '');
  $id_transaksi = (int) ($_POST['id_transaksi'] ?? 0);
  $catatan      = trim($_POST['catatan_admin']  ?? '');

  if ($id_transaksi > 0 && in_array($aksi, ['terima', 'tolak'], true)) {

    // Pastikan transaksi memang milik penjual ini
    $cek = $koneksi->prepare("SELECT id_transaksi FROM transaksi WHERE id_transaksi = ? AND id_penjual = ?");
    $cek->bind_param('ii', $id_transaksi, $id_user);
    $cek->execute();
    $valid = $cek->get_result()->num_rows > 0;
    $cek->close();

    if ($valid) {
      $koneksi->begin_transaction();
      try {
        if ($aksi === 'terima') {
          // Update status pembayaran di tabel pembayaran
          $s1 = $koneksi->prepare("
                        UPDATE pembayaran
                        SET status_verifikasi  = 'diterima',
                            catatan_admin      = ?,
                            tanggal_verifikasi = NOW()
                        WHERE id_transaksi = ?
                    ");
          $s1->bind_param('si', $catatan, $id_transaksi);
          $s1->execute();
          $s1->close();

          // Update status_pembayaran di transaksi
          $s2 = $koneksi->prepare("
    UPDATE transaksi
    SET status_pembayaran = 'dibayar',
        status_transaksi  = 'selesai'
    WHERE id_transaksi = ?
");
          $s2->bind_param('i', $id_transaksi);
          $s2->execute();
          $s2->close();

          // Update status produk menjadi terjual
          $s3 = $koneksi->prepare("
    UPDATE produk p
    JOIN transaksi t ON t.id_produk = p.id_produk
    SET p.status_produk = 'terjual'
    WHERE t.id_transaksi = ?
      AND p.status_produk IN ('tersedia', 'dipesan')
");
          $s3->bind_param('i', $id_transaksi);
          $s3->execute();
          $s3->close();

          $koneksi->commit();
          $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Pembayaran diterima. Status produk menjadi Terjual.'];
        } elseif ($aksi === 'tolak') {
          // Update status pembayaran di tabel pembayaran
          $s1 = $koneksi->prepare("
                        UPDATE pembayaran
                        SET status_verifikasi  = 'ditolak',
                            catatan_admin      = ?,
                            tanggal_verifikasi = NOW()
                        WHERE id_transaksi = ?
                    ");
          $s1->bind_param('si', $catatan, $id_transaksi);
          $s1->execute();
          $s1->close();

          // Update status_pembayaran di transaksi ke ditolak
          $s2 = $koneksi->prepare("
                        UPDATE transaksi
                        SET status_pembayaran = 'ditolak'
                        WHERE id_transaksi = ?
                    ");
          $s2->bind_param('i', $id_transaksi);
          $s2->execute();
          $s2->close();

          $koneksi->commit();
          $_SESSION['notif'] = ['tipe' => 'warning', 'pesan' => 'Pembayaran ditolak. Pembeli akan diminta upload ulang.'];
        }
      } catch (Throwable $e) {
        $koneksi->rollback();
        error_log('[seller/pembayaran_masuk] ' . $e->getMessage());
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Terjadi kesalahan sistem.'];
      }
    } else {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Akses tidak diizinkan.'];
    }
  }

  $qs = http_build_query(array_filter(['status' => $_POST['current_status'] ?? '', 'q' => $_POST['current_q'] ?? '']));
  header('Location: pembayaran_masuk.php' . ($qs ? '?' . $qs : ''));
  exit;
}

// =========================
// FILTER STATUS
// =========================
$filter = trim($_GET['status'] ?? 'semua');
$valid  = ['semua', 'menunggu_verifikasi', 'diterima', 'ditolak'];
if (!in_array($filter, $valid, true)) $filter = 'semua';

$search = trim($_GET['q'] ?? '');

// =========================
// COUNTER
// =========================
$stmt_cnt = $koneksi->prepare("
    SELECT
        COUNT(*)                                                                    AS semua,
        COUNT(CASE WHEN pb.status_verifikasi = 'menunggu_verifikasi' THEN 1 END)   AS menunggu_verifikasi,
        COUNT(CASE WHEN pb.status_verifikasi = 'diterima'            THEN 1 END)   AS diterima,
        COUNT(CASE WHEN pb.status_verifikasi = 'ditolak'             THEN 1 END)   AS ditolak
    FROM pembayaran pb
    JOIN transaksi t ON t.id_transaksi = pb.id_transaksi
    WHERE t.id_penjual = ?
      AND t.status_transaksi != 'dibatalkan'
");
$stmt_cnt->bind_param('i', $id_user);
$stmt_cnt->execute();
$counter = $stmt_cnt->get_result()->fetch_assoc();
$stmt_cnt->close();

// =========================
// QUERY PEMBAYARAN MASUK
// =========================
$where  = "t.id_penjual = ? AND t.status_transaksi != 'dibatalkan'";
$types  = 'i';
$params = [$id_user];

if ($filter !== 'semua') {
  $where   .= ' AND pb.status_verifikasi = ?';
  $types   .= 's';
  $params[] = $filter;
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
        p.nama_produk,
        mp.nama_metode,
        mp.jenis AS jenis_metode,
        u.nama        AS nama_pembeli,
        u.no_whatsapp AS wa_pembeli,
        u.email       AS email_pembeli
    FROM pembayaran pb
    JOIN transaksi t ON t.id_transaksi = pb.id_transaksi
    JOIN produk    p ON p.id_produk    = t.id_produk
    JOIN users     u ON u.id_user      = t.id_pembeli
    LEFT JOIN metode_pembayaran mp ON mp.id_metode = pb.id_metode
    WHERE $where
    ORDER BY
        CASE pb.status_verifikasi WHEN 'menunggu_verifikasi' THEN 0 ELSE 1 END,
        pb.tanggal_upload DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$list_bayar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// =========================
// NOTIF DARI SESSION
// =========================
$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

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
  <title>Pembayaran Masuk | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/seller.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>
  <div class="seller-wrapper">
    <div class="seller-content">

      <!-- ════════ HEADER ════════ -->
      <div class="seller-page-header">
        <div>
          <h1>Pembayaran Masuk</h1>
          <p class="seller-subtitle">Periksa dan verifikasi bukti pembayaran dari pembeli.</p>
        </div>
      </div>

      <!-- ════════ NOTIFIKASI ════════ -->
      <?php if ($notif): ?>
        <div class="seller-alert seller-alert-<?= htmlspecialchars($notif['tipe']) ?>">
          <?= htmlspecialchars($notif['pesan']) ?>
        </div>
      <?php endif; ?>

      <!-- ════════ TAB FILTER ════════ -->
      <div class="seller-tab-filter">
        <?php
        $tabs = [
          'semua'               => 'Semua',
          'menunggu_verifikasi' => 'Menunggu',
          'diterima'            => 'Diterima',
          'ditolak'             => 'Ditolak',
        ];
        foreach ($tabs as $key => $label):
          $q = http_build_query(array_filter(['status' => $key === 'semua' ? '' : $key, 'q' => $search]));
        ?>
          <a href="pembayaran_masuk.php<?= $q ? '?' . $q : '' ?>"
            class="seller-tab <?= $filter === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="seller-tab-count"><?= (int)$counter[$key] ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ════════ SEARCH ════════ -->
      <div class="seller-toolbar">
        <form method="GET" action="pembayaran_masuk.php" class="seller-search-form">
          <?php if ($filter !== 'semua'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
          <?php endif; ?>
          <div class="seller-search-box">
            <svg class="seller-search-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
            </svg>
            <input type="text" name="q"
              value="<?= htmlspecialchars($search) ?>"
              placeholder="Cari invoice, produk, atau pembeli...">
            <button type="submit" class="seller-search-btn">Cari</button>
            <?php if ($search): ?>
              <a href="pembayaran_masuk.php<?= $filter !== 'semua' ? '?status=' . urlencode($filter) : '' ?>"
                class="seller-search-reset">✕</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ════════ KARTU PEMBAYARAN ════════ -->
      <?php if (empty($list_bayar)): ?>
        <div class="seller-empty">
          <div class="seller-empty-icon">💳</div>
          <p>Belum ada pembayaran<?= $filter !== 'semua' ? ' dengan status ' . htmlspecialchars($tabs[$filter]) : '' ?>.</p>
        </div>
      <?php else: ?>
        <div class="seller-bayar-list">
          <?php foreach ($list_bayar as $item):
            $sv = $item['status_verifikasi'];
            $badge_map = [
              'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'cls' => 'info'],
              'diterima'            => ['label' => 'Diterima',             'cls' => 'success'],
              'ditolak'             => ['label' => 'Ditolak',              'cls' => 'danger'],
            ];
            $badge = $badge_map[$sv] ?? ['label' => $sv, 'cls' => 'secondary'];

            $bukti_url = !empty($item['bukti_pembayaran'])
              ? '../uploads/bukti_pembayaran/' . htmlspecialchars($item['bukti_pembayaran'])
              : '';
            $wa_link = !empty($item['wa_pembeli'])
              ? 'https://wa.me/' . wa_phone($item['wa_pembeli'])
              : '#';
          ?>
            <div class="seller-bayar-card">

              <!-- Header -->
              <div class="seller-bayar-card-head">
                <div>
                  <span class="seller-invoice"><?= htmlspecialchars($item['kode_invoice']) ?></span>
                  <span class="seller-pesanan-date">
                    Upload: <?= date('d M Y H:i', strtotime($item['tanggal_upload'])) ?>
                  </span>
                </div>
                <span class="seller-badge seller-badge-<?= $badge['cls'] ?>"><?= $badge['label'] ?></span>
              </div>

              <!-- Body -->
              <div class="seller-bayar-card-body">

                <!-- Kolom kiri: info -->
                <div class="seller-bayar-info">
                  <h3><?= htmlspecialchars($item['nama_produk']) ?></h3>

                  <div class="seller-pesanan-meta">
                    <div class="seller-meta-item">
                      <span class="seller-meta-label">Pembeli</span>
                      <span><?= htmlspecialchars($item['nama_pembeli']) ?></span>
                    </div>
                    <div class="seller-meta-item">
                      <span class="seller-meta-label">WhatsApp</span>
                      <a href="<?= $wa_link ?>" target="_blank" class="seller-wa-link">
                        📱 <?= htmlspecialchars($item['wa_pembeli'] ?? '-') ?>
                      </a>
                    </div>
                    <div class="seller-meta-item">
                      <span class="seller-meta-label">Metode</span>
                      <span><?= htmlspecialchars($item['nama_metode'] ?? '-') ?>
                        <?= !empty($item['jenis_metode']) ? '(' . htmlspecialchars($item['jenis_metode']) . ')' : '' ?>
                      </span>
                    </div>
                    <div class="seller-meta-item">
                      <span class="seller-meta-label">Nominal</span>
                      <strong><?= rupiah((float)$item['nominal']) ?></strong>
                    </div>
                    <?php if (!empty($item['catatan_pembeli'])): ?>
                      <div class="seller-meta-item">
                        <span class="seller-meta-label">Catatan</span>
                        <span><?= htmlspecialchars($item['catatan_pembeli']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($item['catatan_admin']) && $sv === 'ditolak'): ?>
                      <div class="seller-catatan-tolak">
                        <strong>Alasan penolakan:</strong>
                        <?= htmlspecialchars($item['catatan_admin']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Kolom kanan: bukti -->
                <div class="seller-bayar-bukti">
                  <p class="seller-meta-label">Bukti Pembayaran</p>
                  <?php if ($bukti_url): ?>
                    <img src="<?= $bukti_url ?>"
                      alt="Bukti Pembayaran"
                      class="seller-bukti-img"
                      onclick="zoomBukti('<?= $bukti_url ?>')">
                    <button type="button"
                      class="seller-btn-secondary seller-btn-sm"
                      onclick="zoomBukti('<?= $bukti_url ?>')"
                      style="margin-top:8px;">
                      🔍 Perbesar
                    </button>
                  <?php else: ?>
                    <p class="seller-no-bukti">Bukti belum diupload.</p>
                  <?php endif; ?>
                </div>

              </div><!-- /.seller-bayar-card-body -->

              <!-- Footer: Tombol aksi -->
              <?php if ($sv === 'menunggu_verifikasi'): ?>
                <div class="seller-bayar-card-footer">

                  <!-- Terima -->
                  <form method="POST" action="pembayaran_masuk.php"
                    onsubmit="return confirm('Terima pembayaran ini? Status transaksi akan diperbarui.')">
                    <input type="hidden" name="aksi" value="terima">
                    <input type="hidden" name="id_transaksi" value="<?= (int)$item['id_transaksi'] ?>">
                    <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter) ?>">
                    <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="seller-btn-primary">✅ Terima Pembayaran</button>
                  </form>

                  <!-- Tolak -->
                  <div class="seller-tolak-section">
                    <button type="button"
                      class="seller-btn-danger"
                      onclick="toggleTolak(<?= (int)$item['id_transaksi'] ?>)">
                      ✕ Tolak
                    </button>

                    <div class="seller-tolak-panel" id="tolak-<?= (int)$item['id_transaksi'] ?>" style="display:none;">
                      <form method="POST" action="pembayaran_masuk.php"
                        onsubmit="return confirm('Tolak pembayaran ini?')">
                        <input type="hidden" name="aksi" value="tolak">
                        <input type="hidden" name="id_transaksi" value="<?= (int)$item['id_transaksi'] ?>">
                        <input type="hidden" name="current_status" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">

                        <div class="seller-field">
                          <label>Alasan Penolakan <span class="req">*</span></label>
                          <textarea name="catatan_admin" rows="3" required
                            placeholder="Contoh: Nominal tidak sesuai, bukti tidak terbaca..."></textarea>
                        </div>
                        <div class="seller-tolak-actions">
                          <button type="submit" class="seller-btn-danger">Kirim Penolakan</button>
                          <button type="button"
                            class="seller-btn-secondary"
                            onclick="toggleTolak(<?= (int)$item['id_transaksi'] ?>)">
                            Batal
                          </button>
                        </div>
                      </form>
                    </div><!-- /.seller-tolak-panel -->
                  </div><!-- /.seller-tolak-section -->

                </div><!-- /.seller-bayar-card-footer -->
              <?php endif; ?>

            </div><!-- /.seller-bayar-card -->
          <?php endforeach; ?>
        </div><!-- /.seller-bayar-list -->
      <?php endif; ?>

    </div><!-- /.seller-content -->
  </div><!-- /.seller-wrapper -->

  <!-- MODAL ZOOM BUKTI -->
  <div id="modalBukti" class="seller-modal-overlay" style="display:none;" onclick="tutupModal()">
    <div class="seller-modal-box" onclick="event.stopPropagation()">
      <button class="seller-modal-close" onclick="tutupModal()">✕</button>
      <img id="modalImg" src="" alt="Bukti Pembayaran">
    </div>
  </div>

  <script>
    let buktiZoomTimeout;

    function zoomBukti(src) {
      clearTimeout(buktiZoomTimeout);
      document.getElementById('modalImg').src = src;
      document.getElementById('modalBukti').style.display = 'flex';
      buktiZoomTimeout = setTimeout(tutupModal, 8000);
    }

    function tutupModal() {
      clearTimeout(buktiZoomTimeout);
      document.getElementById('modalBukti').style.display = 'none';
      document.getElementById('modalImg').src = '';
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') tutupModal();
    });

    function toggleTolak(id) {
      const panel = document.getElementById('tolak-' + id);
      if (!panel) return;

      document.querySelectorAll('.seller-tolak-panel').forEach((item) => {
        if (item !== panel) item.style.display = 'none';
      });

      panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', (event) => {
      if (!event.target.closest('.seller-tolak-section')) {
        document.querySelectorAll('.seller-tolak-panel').forEach((panel) => {
          panel.style.display = 'none';
        });
      }
    });
  </script>

</body>

</html>