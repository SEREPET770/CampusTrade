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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $aksi    = trim($_POST['aksi']    ?? '');
  $id_user = (int) ($_POST['id_user'] ?? 0);

  if ($id_user <= 0) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'ID pengguna tidak valid.'];
    header('Location: pengguna.php');
    exit;
  }

  $cek = $koneksi->prepare("SELECT role, status_aktif FROM users WHERE id_user = ? LIMIT 1");
  $cek->bind_param('i', $id_user);
  $cek->execute();
  $target = $cek->get_result()->fetch_assoc();
  $cek->close();

  if (!$target) {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Pengguna tidak ditemukan.'];
    header('Location: pengguna.php');
    exit;
  }

  if ($target['role'] === 'admin') {
    $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Akun admin tidak dapat diubah melalui halaman ini.'];
    header('Location: pengguna.php');
    exit;
  }

  if ($aksi === 'verifikasi') {
    $stmt = $koneksi->prepare("UPDATE users SET status_verifikasi = 'terverifikasi' WHERE id_user = ?");
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $stmt->close();
    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Pengguna berhasil diverifikasi.'];
  } elseif ($aksi === 'tolak') {
    $stmt = $koneksi->prepare("UPDATE users SET status_verifikasi = 'ditolak' WHERE id_user = ?");
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $stmt->close();
    $_SESSION['notif'] = ['tipe' => 'warning', 'pesan' => 'Pengguna berhasil ditolak.'];
  } elseif ($aksi === 'toggle_aktif') {
    $status_baru = (int) $target['status_aktif'] === 1 ? 0 : 1;
    $stmt = $koneksi->prepare("UPDATE users SET status_aktif = ? WHERE id_user = ?");
    $stmt->bind_param('ii', $status_baru, $id_user);
    $stmt->execute();
    $stmt->close();
    $label = $status_baru === 1 ? 'diaktifkan' : 'dinonaktifkan';
    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => "Akun berhasil $label."];
  } elseif ($aksi === 'hapus') {
    $cek_trx = $koneksi->prepare("
            SELECT COUNT(*) AS total FROM transaksi
            WHERE id_pembeli = ? OR id_penjual = ?
        ");
    $cek_trx->bind_param('ii', $id_user, $id_user);
    $cek_trx->execute();
    $total_trx = (int) $cek_trx->get_result()->fetch_assoc()['total'];
    $cek_trx->close();

    $cek_produk = $koneksi->prepare("SELECT COUNT(*) AS total FROM produk WHERE id_user = ?");
    $cek_produk->bind_param('i', $id_user);
    $cek_produk->execute();
    $total_produk = (int) $cek_produk->get_result()->fetch_assoc()['total'];
    $cek_produk->close();

    if ($total_trx > 0 || $total_produk > 0) {
      $alasan = $total_produk > 0 && $total_trx > 0
        ? "memiliki {$total_produk} produk dan {$total_trx} riwayat transaksi"
        : ($total_produk > 0
          ? "memiliki {$total_produk} produk"
          : "memiliki {$total_trx} riwayat transaksi");

      $_SESSION['notif'] = [
        'tipe'  => 'error',
        'pesan' => "Pengguna tidak dapat dihapus karena {$alasan}."
      ];
    } else {
      $stmt = $koneksi->prepare("DELETE FROM users WHERE id_user = ? AND role = 'user'");
      $stmt->bind_param('i', $id_user);
      $stmt->execute();
      $stmt->close();
      $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Pengguna berhasil dihapus.'];
    }
  }

  $qs = http_build_query(array_filter([
    'filter' => $_POST['current_filter'] ?? '',
    'q'      => $_POST['current_q']      ?? '',
  ]));
  header('Location: pengguna.php' . ($qs ? '?' . $qs : ''));
  exit;
}

$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

$filter = trim($_GET['filter'] ?? 'semua');
$filter_valid = ['semua', 'menunggu', 'terverifikasi', 'ditolak'];
if (!in_array($filter, $filter_valid, true)) $filter = 'semua';

$search = trim($_GET['q'] ?? '');

$counter = $koneksi->query("
    SELECT
        COUNT(*)                                                              AS semua,
        COUNT(CASE WHEN status_verifikasi = 'menunggu'      THEN 1 END)      AS menunggu,
        COUNT(CASE WHEN status_verifikasi = 'terverifikasi' THEN 1 END)      AS terverifikasi,
        COUNT(CASE WHEN status_verifikasi = 'ditolak'       THEN 1 END)      AS ditolak
    FROM users WHERE role = 'user'
")->fetch_assoc();

$where  = "u.role = 'user'";
$params = [];
$types  = '';

if ($filter !== 'semua') {
  $where   .= ' AND u.status_verifikasi = ?';
  $types   .= 's';
  $params[] = $filter;
}

if ($search !== '') {
  $like     = '%' . $search . '%';
  $where   .= ' AND (u.nama LIKE ? OR u.email LIKE ? OR u.nim LIKE ?)';
  $types   .= 'sss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql = "
    SELECT
        u.id_user,
        u.nama,
        u.email,
        u.nim,
        u.no_whatsapp,
        u.foto_ktm,
        u.status_verifikasi,
        u.status_aktif,
        u.created_at,
        COUNT(DISTINCT t.id_transaksi) AS jumlah_transaksi,
        COUNT(DISTINCT p.id_produk)    AS jumlah_produk
    FROM users u
    LEFT JOIN transaksi t ON (t.id_pembeli = u.id_user OR t.id_penjual = u.id_user)
    LEFT JOIN produk    p ON p.id_user = u.id_user
    WHERE $where
    GROUP BY u.id_user
    ORDER BY
        CASE u.status_verifikasi WHEN 'menunggu' THEN 0 ELSE 1 END,
        u.created_at DESC
";

$stmt = $koneksi->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengguna | CampusTrade Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="assets/css/pengguna.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>

  <div class="dash-page">

    <div class="dash-page-header">
      <h1>Pengguna</h1>
      <p>Verifikasi dan kelola akun pengguna CampusTrade</p>
    </div>

    <?php if ($notif): ?>
      <div class="pgn-alert pgn-alert-<?= htmlspecialchars($notif['tipe']) ?>">
        <?= htmlspecialchars($notif['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="pgn-toolbar">
      <div class="pgn-tabs">
        <?php
        $tabs = [
          'semua'         => 'Semua',
          'menunggu'      => 'Menunggu Verifikasi',
          'terverifikasi' => 'Terverifikasi',
          'ditolak'       => 'Ditolak',
        ];
        foreach ($tabs as $val => $label):
          $act = $filter === $val ? 'active' : '';
          $cnt = (int) ($counter[$val] ?? 0);
          $qs  = http_build_query(array_filter(['filter' => $val, 'q' => $search]));
        ?>
          <a href="pengguna.php?<?= $qs ?>" class="pgn-tab <?= $act ?>">
            <?= $label ?>
            <?php if ($cnt > 0): ?>
              <span class="pgn-tab-count <?= $val === 'menunggu' ? 'urgent' : '' ?>">
                <?= $cnt ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <form method="GET" action="pengguna.php" class="pgn-search-form">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="pgn-search-box">
          <svg class="pgn-search-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" />
          </svg>
          <input type="text" name="q"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Cari nama, email, atau NIM...">
          <button type="submit" class="pgn-search-btn">Cari</button>
          <?php if ($search): ?>
            <a href="pengguna.php?filter=<?= htmlspecialchars($filter) ?>"
              class="pgn-search-reset">✕</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <?php if ($search || $filter !== 'semua'): ?>
      <p class="pgn-result-info">
        Menampilkan <strong><?= count($users) ?></strong> pengguna
        <?= $filter !== 'semua' ? '· Filter: <strong>' . htmlspecialchars($tabs[$filter] ?? $filter) . '</strong>' : '' ?>
        <?= $search ? '· Pencarian: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
      </p>
    <?php endif; ?>

    <div class="pgn-table-wrap">
      <table class="pgn-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Pengguna</th>
            <th>NIM</th>
            <th>WhatsApp</th>
            <th>KTM</th>
            <th class="text-center">Produk</th>
            <th class="text-center">Transaksi</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="9" class="pgn-empty">
                Tidak ada pengguna
                <?= $filter !== 'semua' ? 'dengan status ' . htmlspecialchars($tabs[$filter] ?? $filter) : '' ?>.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $no => $u):
              $sv   = $u['status_verifikasi'];
              $aktif = (int) $u['status_aktif'];
              $jumlah_produk = (int) $u['jumlah_produk'];
              $jumlah_transaksi = (int) $u['jumlah_transaksi'];
              $boleh_hapus = $jumlah_produk === 0 && $jumlah_transaksi === 0;

              $badge_cls = match ($sv) {
                'terverifikasi' => 'success',
                'ditolak'       => 'danger',
                default         => 'warning',
              };
              $badge_txt = match ($sv) {
                'terverifikasi' => 'Terverifikasi',
                'ditolak'       => 'Ditolak',
                default         => 'Menunggu',
              };

              $foto = $u['foto_ktm'] ?? '';
              $ext  = strtolower(pathinfo((string) $foto, PATHINFO_EXTENSION));
              $ada_foto = $foto && in_array($ext, ['jpg', 'jpeg', 'png'], true);
            ?>
              <tr class="<?= $aktif === 0 ? 'pgn-row-nonaktif' : '' ?>
                            <?= $sv === 'menunggu' ? 'pgn-row-pending' : '' ?>">

                <td class="pgn-td-no"><?= $no + 1 ?></td>

                <td>
                  <div class="pgn-user-info">
                    <div class="pgn-avatar">
                      <?= mb_strtoupper(mb_substr($u['nama'], 0, 1)) ?>
                    </div>
                    <div>
                      <div class="pgn-nama">
                        <?= htmlspecialchars($u['nama']) ?>
                        <?php if ($aktif === 0): ?>
                          <span class="pgn-badge pgn-badge-secondary">Nonaktif</span>
                        <?php endif; ?>
                      </div>
                      <div class="pgn-email"><?= htmlspecialchars($u['email']) ?></div>
                      <div class="pgn-join">
                        Bergabung <?= date('d M Y', strtotime($u['created_at'])) ?>
                      </div>
                    </div>
                  </div>
                </td>

                <td class="pgn-nim"><?= htmlspecialchars($u['nim']) ?></td>

                <td>
                  <?php if ($u['no_whatsapp']): ?>
                    <a href="https://wa.me/<?= wa_phone($u['no_whatsapp']) ?>"
                      target="_blank" class="pgn-wa-link"><?= htmlspecialchars($u['no_whatsapp']) ?>
                    </a>
                  <?php else: ?>
                    <span class="pgn-empty-val">—</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if ($ada_foto): ?>
                    <img src="uploads/ktm/<?= htmlspecialchars($foto) ?>"
                      alt="KTM <?= htmlspecialchars($u['nama']) ?>"
                      class="pgn-ktm-thumb"
                      onclick="bukaKTM('uploads/ktm/<?= htmlspecialchars($foto) ?>', '<?= htmlspecialchars(addslashes($u['nama'])) ?>')"
                      title="Klik untuk perbesar">
                  <?php else: ?>
                    <span class="pgn-empty-val">Tidak ada</span>
                  <?php endif; ?>
                </td>

                <td class="text-center pgn-count">
                  <?= (int) $u['jumlah_produk'] ?>
                </td>

                <td class="text-center pgn-count">
                  <?= (int) $u['jumlah_transaksi'] ?>
                </td>

                <td>
                  <span class="pgn-badge pgn-badge-<?= $badge_cls ?>">
                    <?= $badge_txt ?>
                  </span>
                </td>

                <td>
                  <div class="pgn-aksi">

                    <?php if ($sv === 'menunggu'): ?>
                      <form method="POST"
                        onsubmit="return confirm('Verifikasi pengguna ini?')">
                        <input type="hidden" name="aksi" value="verifikasi">
                        <input type="hidden" name="id_user" value="<?= (int) $u['id_user'] ?>">
                        <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="pgn-btn pgn-btn-verify">✓ Verifikasi</button>
                      </form>
                      <form method="POST"
                        onsubmit="return confirm('Tolak pengguna ini?')">
                        <input type="hidden" name="aksi" value="tolak">
                        <input type="hidden" name="id_user" value="<?= (int) $u['id_user'] ?>">
                        <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="pgn-btn pgn-btn-tolak">✕ Tolak</button>
                      </form>
                    <?php endif; ?>

                    <form method="POST"
                      onsubmit="return confirm('<?= $aktif === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> akun ini?')">
                      <input type="hidden" name="aksi" value="toggle_aktif">
                      <input type="hidden" name="id_user" value="<?= (int) $u['id_user'] ?>">
                      <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                      <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                      <button type="submit"
                        class="pgn-btn <?= $aktif === 1 ? 'pgn-btn-suspend' : 'pgn-btn-aktif' ?>">
                        <?= $aktif === 1 ? '⏸ Nonaktifkan' : '▶ Aktifkan' ?>
                      </button>
                    </form>

                    <form method="POST"
                      onsubmit="return confirm('<?= $boleh_hapus ? 'Hapus pengguna ini? Data tidak dapat dikembalikan.' : 'Akun ini tidak dapat dihapus karena masih memiliki data terkait.' ?>')">
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id_user" value="<?= (int) $u['id_user'] ?>">
                      <input type="hidden" name="current_filter" value="<?= htmlspecialchars($filter) ?>">
                      <input type="hidden" name="current_q" value="<?= htmlspecialchars($search) ?>">
                      <button type="submit"
                        class="pgn-btn pgn-btn-hapus <?= $boleh_hapus ? '' : 'pgn-btn-hapus-disabled' ?>"
                        <?= $boleh_hapus ? '' : 'disabled' ?>
                        title="<?= $boleh_hapus ? 'Hapus akun pengguna ini.' : ($jumlah_produk > 0 ? 'Akun ini memiliki produk, tidak dapat dihapus.' : 'Akun ini memiliki riwayat transaksi, tidak dapat dihapus.') ?>">
                        <?= $boleh_hapus ? '🗑 Hapus' : ($jumlah_produk > 0 ? '⚠ Ada Produk' : '⚠ Ada Transaksi') ?>
                      </button>
                    </form>

                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <div id="ktmModal" class="pgn-modal-overlay" onclick="tutupKTM()">
    <div class="pgn-modal-box" onclick="event.stopPropagation()">
      <button class="pgn-modal-close" onclick="tutupKTM()">✕</button>
      <p class="pgn-modal-title" id="ktmNama"></p>
      <img id="ktmImg" src="" alt="" class="pgn-modal-img">
      <p class="pgn-modal-caption">Kartu Tanda Mahasiswa</p>
    </div>
  </div>

  <?php include 'layout/footer.php'; ?>

  <script src="assets/js/pengguna.js"></script>

</body>

</html>