<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

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

// ════════════════════════════════════════════════════════════
// HANDLE POST
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $aksi = trim($_POST['aksi'] ?? '');

  // ── TAMBAH ──────────────────────────────────────────────
  if ($aksi === 'tambah') {

    $nama_metode  = trim($_POST['nama_metode']  ?? '');
    $jenis        = trim($_POST['jenis']         ?? '');
    $provider     = trim($_POST['provider']      ?? '');
    $nomor_tujuan = trim($_POST['nomor_tujuan']  ?? '');
    $nama_pemilik = trim($_POST['nama_pemilik']  ?? '');
    $catatan      = trim($_POST['catatan']        ?? '');

    $jenis_valid = ['Bank', 'E-Wallet', 'QRIS'];

    if ($nama_metode === '' || !in_array($jenis, $jenis_valid, true) || $nomor_tujuan === '' || $nama_pemilik === '') {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Semua kolom wajib diisi dengan benar.'];
      header('Location: metode_pembayaran.php');
      exit;
    }

    // Upload QR Code (opsional)
    $qr_code = null;
    if (!empty($_FILES['qr_code']['name'])) {
      $ext_allowed = ['jpg', 'jpeg', 'png', 'webp'];
      $ext         = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));

      if (!in_array($ext, $ext_allowed, true)) {
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Format QR Code harus JPG, PNG, atau WEBP.'];
        header('Location: metode_pembayaran.php');
        exit;
      }

      $upload_dir = '../uploads/qr_code/';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

      $nama_file = 'qr_' . $id_user . '_' . time() . '.' . $ext;
      if (!move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $nama_file)) {
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Gagal upload QR Code. Coba lagi.'];
        header('Location: metode_pembayaran.php');
        exit;
      }
      $qr_code = $nama_file;
    }

    $stmt = $koneksi->prepare("
            INSERT INTO metode_pembayaran
                (id_user, nama_metode, jenis, provider, nomor_tujuan, nama_pemilik, catatan, qr_code, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
    $stmt->bind_param(
      'isssssss',
      $id_user,
      $nama_metode,
      $jenis,
      $provider,
      $nomor_tujuan,
      $nama_pemilik,
      $catatan,
      $qr_code
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Metode pembayaran berhasil ditambahkan.'];

    // ── EDIT ────────────────────────────────────────────────
  } elseif ($aksi === 'edit') {

    $id_metode    = (int)   ($_POST['id_metode']    ?? 0);
    $nama_metode  = trim($_POST['nama_metode']  ?? '');
    $jenis        = trim($_POST['jenis']         ?? '');
    $provider     = trim($_POST['provider']      ?? '');
    $nomor_tujuan = trim($_POST['nomor_tujuan']  ?? '');
    $nama_pemilik = trim($_POST['nama_pemilik']  ?? '');
    $catatan      = trim($_POST['catatan']        ?? '');

    if ($id_metode <= 0 || $nama_metode === '' || $nomor_tujuan === '' || $nama_pemilik === '') {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Data tidak valid.'];
      header('Location: metode_pembayaran.php');
      exit;
    }

    // Pastikan metode ini milik user yang login (ownership check)
    $cek = $koneksi->prepare("SELECT qr_code FROM metode_pembayaran WHERE id_metode = ? AND id_user = ?");
    $cek->bind_param('ii', $id_metode, $id_user);
    $cek->execute();
    $existing = $cek->get_result()->fetch_assoc();
    $cek->close();

    if (!$existing) {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Metode pembayaran tidak ditemukan.'];
      header('Location: metode_pembayaran.php');
      exit;
    }

    // Upload QR Code baru (opsional — jika tidak upload, pakai yang lama)
    $qr_code = $existing['qr_code'];
    if (!empty($_FILES['qr_code']['name'])) {
      $ext_allowed = ['jpg', 'jpeg', 'png', 'webp'];
      $ext         = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));

      if (!in_array($ext, $ext_allowed, true)) {
        $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Format QR Code harus JPG, PNG, atau WEBP.'];
        header('Location: metode_pembayaran.php');
        exit;
      }

      $upload_dir = '../uploads/qr_code/';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

      // Hapus QR lama jika ada
      if ($qr_code && file_exists($upload_dir . $qr_code)) {
        unlink($upload_dir . $qr_code);
      }

      $nama_file = 'qr_' . $id_user . '_' . time() . '.' . $ext;
      if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $nama_file)) {
        $qr_code = $nama_file;
      }
    }

    $stmt = $koneksi->prepare("
            UPDATE metode_pembayaran
            SET nama_metode  = ?,
                jenis        = ?,
                provider     = ?,
                nomor_tujuan = ?,
                nama_pemilik = ?,
                catatan      = ?,
                qr_code      = ?
            WHERE id_metode = ?
              AND id_user   = ?
        ");
    $stmt->bind_param(
      'sssssssii',
      $nama_metode,
      $jenis,
      $provider,
      $nomor_tujuan,
      $nama_pemilik,
      $catatan,
      $qr_code,
      $id_metode,
      $id_user
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Metode pembayaran berhasil diperbarui.'];

    // ── TOGGLE AKTIF ────────────────────────────────────────
  } elseif ($aksi === 'toggle') {

    $id_metode = (int) ($_POST['id_metode'] ?? 0);

    $stmt = $koneksi->prepare("
            UPDATE metode_pembayaran
            SET status = IF(status = 1, 0, 1)
            WHERE id_metode = ?
              AND id_user   = ?
        ");
    $stmt->bind_param('ii', $id_metode, $id_user);
    $stmt->execute();
    $stmt->close();

    $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Status metode pembayaran diperbarui.'];

    // ── HAPUS ────────────────────────────────────────────────
  } elseif ($aksi === 'hapus') {

    $id_metode = (int) ($_POST['id_metode'] ?? 0);

    // Ownership check + ambil nama file QR untuk dihapus
    $cek = $koneksi->prepare("SELECT qr_code FROM metode_pembayaran WHERE id_metode = ? AND id_user = ?");
    $cek->bind_param('ii', $id_metode, $id_user);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();
    $cek->close();

    if ($row) {
      // Hapus file QR jika ada
      if ($row['qr_code']) {
        $path = '../uploads/qr_code/' . $row['qr_code'];
        if (file_exists($path)) unlink($path);
      }

      $stmt = $koneksi->prepare("DELETE FROM metode_pembayaran WHERE id_metode = ? AND id_user = ?");
      $stmt->bind_param('ii', $id_metode, $id_user);
      $stmt->execute();
      $stmt->close();

      $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => 'Metode pembayaran berhasil dihapus.'];
    } else {
      $_SESSION['notif'] = ['tipe' => 'error', 'pesan' => 'Metode tidak ditemukan.'];
    }
  }

  header('Location: metode_pembayaran.php');
  exit;
}

// ════════════════════════════════════════════════════════════
// GET — Ambil data
// ════════════════════════════════════════════════════════════
$notif = $_SESSION['notif'] ?? null;
unset($_SESSION['notif']);

$stmt = $koneksi->prepare("
    SELECT id_metode, nama_metode, jenis, provider,
           nomor_tujuan, nama_pemilik, catatan, qr_code, status
    FROM metode_pembayaran
    WHERE id_user = ?
    ORDER BY status DESC, jenis, nama_metode
");
$stmt->bind_param('i', $id_user);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Data untuk form edit (diambil via AJAX di JS, atau dari GET ?edit=id)
$edit_data = null;
if (isset($_GET['edit'])) {
  $edit_id = (int) $_GET['edit'];
  foreach ($list as $item) {
    if ((int) $item['id_metode'] === $edit_id) {
      $edit_data = $item;
      break;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Metode Pembayaran | CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/seller.css">
  <link rel="stylesheet" href="../assets/css/metode_pembayaran.css">
</head>

<body>

  <?php include 'layout/topnav.php'; ?>

  <div class="seller-wrapper">
    <div class="seller-content">

      <div class="seller-page-header">
        <div>
          <h1>Metode Pembayaran</h1>
          <p class="seller-subtitle">
            Tambahkan rekening, e-wallet, atau QRIS yang akan ditampilkan kepada pembeli saat tagihan.
          </p>
        </div>
        <button onclick="bukaFormTambah()" class="seller-btn-primary">
          Tambah Metode
        </button>
      </div>

      <!-- NOTIFIKASI -->
      <?php if ($notif): ?>
        <div class="seller-alert seller-alert-<?= $notif['tipe'] === 'success' ? 'success' : 'danger' ?>" id="notifBox">
          <?= htmlspecialchars($notif['pesan']) ?>
        </div>
      <?php endif; ?>

      <!-- ════ FORM TAMBAH ════ -->
      <div class="mp-form-card" id="formTambah" style="display:none">
        <h2>Tambah Metode Pembayaran</h2>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="aksi" value="tambah">
          <div class="mp-form-grid">

            <div class="mp-field">
              <label>Jenis *</label>
              <select name="jenis" id="jenisTambah" onchange="updateProvider('tambah')" required>
                <option value="">— Pilih Jenis —</option>
                <option value="Bank">🏦 Bank Transfer</option>
                <option value="E-Wallet">📱 E-Wallet</option>
                <option value="QRIS">⬛ QRIS</option>
              </select>
            </div>

            <div class="mp-field">
              <label>Provider *</label>
              <select name="provider" id="providerTambah" required>
                <option value="">— Pilih Jenis dulu —</option>
              </select>
            </div>

            <div class="mp-field">
              <label>Nama Metode (label tampilan) *</label>
              <input type="text" name="nama_metode" id="namaMetodeTambah"
                placeholder="cth: BCA, DANA, GoPay QRIS" required>
              <span class="hint">Nama ini yang dilihat pembeli.</span>
            </div>

            <div class="mp-field">
              <label>Nomor / ID Tujuan *</label>
              <input type="text" name="nomor_tujuan"
                placeholder="No. Rekening / No. HP / ID QRIS" required>
            </div>

            <div class="mp-field full">
              <label>Nama Pemilik *</label>
              <input type="text" name="nama_pemilik"
                placeholder="Nama sesuai rekening / akun" required>
            </div>

            <div class="mp-field full">
              <label>Catatan (opsional)</label>
              <textarea name="catatan" rows="2"
                placeholder="cth: Transfer pas nominal, kirim bukti via WA"></textarea>
            </div>

            <div class="mp-field full">
              <label>Upload QR Code (opsional)</label>
              <input type="file" name="qr_code" accept="image/*"
                onchange="previewQR(this, 'previewTambah')">
              <div class="qr-preview-wrap">
                <img id="previewTambah" class="qr-preview" src="" alt="Preview QR">
              </div>
              <span class="hint">Format JPG / PNG / WEBP. Maks 2MB.</span>
            </div>

          </div>
          <div class="mp-form-actions">
            <button type="submit" class="seller-btn-primary">💾 Simpan</button>
            <button type="button" onclick="tutupFormTambah()" class="seller-btn-secondary">Batal</button>
          </div>
        </form>
      </div>

      <!-- ════ FORM EDIT ════ -->
      <?php if ($edit_data): ?>
        <div class="mp-form-card" id="formEdit">
          <h2>✏️ Edit Metode Pembayaran</h2>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_metode" value="<?= (int) $edit_data['id_metode'] ?>">
            <div class="mp-form-grid">

              <div class="mp-field">
                <label>Jenis</label>
                <select name="jenis" id="jenisEdit" onchange="updateProvider('edit')">
                  <option value="Bank" <?= $edit_data['jenis'] === 'Bank'     ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                  <option value="E-Wallet" <?= $edit_data['jenis'] === 'E-Wallet' ? 'selected' : '' ?>>📱 E-Wallet</option>
                  <option value="QRIS" <?= $edit_data['jenis'] === 'QRIS'     ? 'selected' : '' ?>>⬛ QRIS</option>
                </select>
              </div>

              <div class="mp-field">
                <label>Provider</label>
                <select name="provider" id="providerEdit" data-current="<?= htmlspecialchars($edit_data['provider']) ?>">
                  <option value="<?= htmlspecialchars($edit_data['provider']) ?>" selected>
                    <?= htmlspecialchars($edit_data['provider']) ?>
                  </option>
                </select>
              </div>

              <div class="mp-field">
                <label>Nama Metode *</label>
                <input type="text" name="nama_metode" id="namaMetodeEdit"
                  value="<?= htmlspecialchars($edit_data['nama_metode']) ?>" required>
              </div>

              <div class="mp-field">
                <label>Nomor / ID Tujuan *</label>
                <input type="text" name="nomor_tujuan"
                  value="<?= htmlspecialchars($edit_data['nomor_tujuan']) ?>" required>
              </div>

              <div class="mp-field full">
                <label>Nama Pemilik *</label>
                <input type="text" name="nama_pemilik"
                  value="<?= htmlspecialchars($edit_data['nama_pemilik']) ?>" required>
              </div>

              <div class="mp-field full">
                <label>Catatan</label>
                <textarea name="catatan" rows="2"><?= htmlspecialchars($edit_data['catatan'] ?? '') ?></textarea>
              </div>

              <div class="mp-field full">
                <label>Ganti QR Code (opsional)</label>
                <?php if ($edit_data['qr_code']): ?>
                  <p style="font-size:12px;color:#6b7280;margin:0 0 6px">
                    QR saat ini:
                    <img src="../uploads/qr_code/<?= htmlspecialchars($edit_data['qr_code']) ?>"
                      style="height:40px;vertical-align:middle;border-radius:4px;margin-left:6px">
                  </p>
                <?php endif; ?>
                <input type="file" name="qr_code" accept="image/*"
                  onchange="previewQR(this, 'previewEdit')">
                <div class="qr-preview-wrap">
                  <img id="previewEdit" class="qr-preview" src="" alt="Preview QR Baru">
                </div>
                <span class="hint">Kosongkan jika tidak ingin mengganti QR.</span>
              </div>

            </div>
            <div class="mp-form-actions">
              <button type="submit" class="seller-btn-primary">💾 Perbarui</button>
              <a href="metode_pembayaran.php" class="seller-btn-secondary">Batal</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- ════ DAFTAR METODE ════ -->
      <?php if (empty($list)): ?>
        <div class="seller-empty">
          <div class="seller-empty-icon">💳</div>
          <p>Belum ada metode pembayaran. Tambahkan sekarang agar pembeli bisa melakukan transfer.</p>
        </div>
      <?php else: ?>
        <div class="mp-grid">
          <?php foreach ($list as $m):
            $jenis_cls = match ($m['jenis']) {
              'Bank'     => 'bank',
              'E-Wallet' => 'ewallet',
              'QRIS'     => 'qris',
              default    => 'bank',
            };
            $jenis_icon = match ($m['jenis']) {
              'Bank'     => '🏦',
              'E-Wallet' => '📱',
              'QRIS'     => '⬛',
              default    => '💳',
            };
            $aktif = (int) $m['status'] === 1;
          ?>
            <div class="mp-card <?= !$aktif ? 'nonaktif' : '' ?>">

              <div class="mp-card-head">
                <div class="mp-jenis-icon <?= $jenis_cls ?>"><?= $jenis_icon ?></div>
                <div class="mp-head-info">
                  <strong><?= htmlspecialchars($m['nama_metode']) ?></strong>
                  <small>
                    <span class="mp-jenis-badge <?= $jenis_cls ?>"><?= $m['jenis'] ?></span>
                    <?php if ($m['provider']): ?>
                      · <?= htmlspecialchars($m['provider']) ?>
                    <?php endif; ?>
                  </small>
                </div>
                <div class="mp-status-dot <?= $aktif ? 'aktif' : 'nonaktif' ?>"
                  title="<?= $aktif ? 'Aktif' : 'Nonaktif' ?>"></div>
              </div>

              <div class="mp-card-body">
                <div class="mp-row">
                  <span class="mp-row-label">Nomor</span>
                  <span class="mp-row-val mp-nomor"><?= htmlspecialchars($m['nomor_tujuan']) ?></span>
                </div>
                <div class="mp-row">
                  <span class="mp-row-label">Pemilik</span>
                  <span class="mp-row-val"><?= htmlspecialchars($m['nama_pemilik']) ?></span>
                </div>
                <?php if (!empty($m['catatan'])): ?>
                  <div class="mp-row">
                    <span class="mp-row-label">Catatan</span>
                    <span class="mp-row-val" style="color:#6b7280;font-style:italic">
                      <?= htmlspecialchars($m['catatan']) ?>
                    </span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($m['qr_code'])): ?>
                  <img src="../uploads/qr_code/<?= htmlspecialchars($m['qr_code']) ?>"
                    alt="QR Code <?= htmlspecialchars($m['nama_metode']) ?>"
                    class="mp-qr"
                    onclick="zoomQR(this.src)"
                    title="Klik untuk perbesar">
                <?php endif; ?>
              </div>

              <div class="mp-card-foot">
                <a href="metode_pembayaran.php?edit=<?= (int) $m['id_metode'] ?>"
                  class="seller-btn-secondary seller-btn-sm">✏️ Edit</a>

                <form method="POST" style="margin:0">
                  <input type="hidden" name="aksi" value="toggle">
                  <input type="hidden" name="id_metode" value="<?= (int) $m['id_metode'] ?>">
                  <button type="submit"
                    class="seller-btn-secondary seller-btn-sm"
                    onclick="return confirm('<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> metode ini?')">
                    <?= $aktif ? '⏸ Nonaktifkan' : '▶ Aktifkan' ?>
                  </button>
                </form>

                <form method="POST" style="margin:0">
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id_metode" value="<?= (int) $m['id_metode'] ?>">
                  <button type="submit"
                    class="seller-btn-danger seller-btn-sm"
                    onclick="return confirm('Hapus metode ini? Tindakan tidak dapat dibatalkan.')">
                    🗑 Hapus
                  </button>
                </form>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Modal zoom QR -->
  <div class="qr-modal" id="qrModal" onclick="this.classList.remove('open')">
    <img id="qrModalImg" src="" alt="QR Code">
  </div>

  <script src="../assets/js/metode_pembayaran.js"></script>

</body>

</html>