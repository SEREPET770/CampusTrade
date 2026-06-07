<?php
session_start();

if (!isset($_SESSION['id_user'])) {
  header("Location: ../auth/login.php");
  exit();
}

if ($_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

require_once 'config/Koneksi.php';

/**
 * @var mysqli $koneksi
 */

// Proses verifikasi user
if (isset($_GET['verifikasi'])) {
  $id = (int)$_GET['verifikasi'];
  $koneksi->query("UPDATE users SET status_verifikasi = 'terverifikasi' WHERE id_user = $id");
  header("Location: pengguna.php");
  exit();
}

// Proses tolak user
if (isset($_GET['tolak'])) {
  $id = (int)$_GET['tolak'];
  $koneksi->query("UPDATE users SET status_verifikasi = 'ditolak' WHERE id_user = $id");
  header("Location: pengguna.php");
  exit();
}

// Proses hapus user
if (isset($_GET['hapus'])) {
  $id = (int)$_GET['hapus'];
  $koneksi->query("DELETE FROM users WHERE id_user = $id AND role = 'user'");
  header("Location: pengguna.php");
  exit();
}

// Ambil semua user
$users = $koneksi->query("
  SELECT id_user, nama, email, nim, no_whatsapp, foto_ktm, status_verifikasi, created_at 
  FROM users 
  WHERE role = 'user' 
  ORDER BY created_at DESC
");
?>

<?php include 'layout/header.php'; ?>
<div class="admin-wrapper">
  <?php include 'layout/sidebar.php'; ?>
  <div class="content">
    <div class="content-header">
      <h1>Pengguna</h1>
    </div>
    <div class="content-body">

      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Email</th>
              <th>NIM</th>
              <th>No. WhatsApp</th>
              <th>Foto KTM</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            if ($users->num_rows > 0):
              while ($row = $users->fetch_assoc()): ?>
                <tr <?= isset($_GET['highlight']) && (string)$_GET['highlight'] === (string)$row['nim'] ? 'class="highlight-row"' : '' ?>>
                  <td><?= $no++ ?></td>
                  <td><?= htmlspecialchars($row['nama']) ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['nim']) ?></td>
                  <td><?= htmlspecialchars($row['no_whatsapp']) ?></td>
                  <td>
                    <?php
                    $foto = $row['foto_ktm'];
                    $ext = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png'];
                    if ($foto && in_array($ext, $allowed)) {
                      echo '<a href="uploads/ktm/' . $foto . '" target="_blank">
                        <img src="uploads/ktm/' . $foto . '" style="width:150px;  height:150px; object-fit:contain; border-radius:6px;">
                        </a>';
                    } else {
                      echo '<span style="color:var(--gray-500); font-size:12px;">Tidak ada</span>';
                    }
                    ?>
                  </td>
                  <td>
                    <?php
                    $status = $row['status_verifikasi'];
                    if ($status === 'terverifikasi'):
                    ?>
                      <span class="badge-status badge-verified">Terverifikasi</span>
                    <?php elseif ($status === 'ditolak'): ?>
                      <span class="badge-status badge-rejected">Ditolak</span>
                    <?php else: ?>
                      <span class="badge-status badge-pending">Menunggu</span>
                    <?php endif; ?>
                  </td>
                  <td class="aksi-cell">
                    <div class="aksi-group">
                      <?php if ($row['status_verifikasi'] === 'menunggu'): ?>
                        <a href="pengguna.php?verifikasi=<?= $row['id_user'] ?>"
                          class="btn-aksi btn-verify"
                          onclick="return confirm('Verifikasi user ini?')">
                          ✓ Verifikasi
                        </a>

                        <a href="pengguna.php?tolak=<?= $row['id_user'] ?>"
                          class="btn-aksi btn-reject"
                          onclick="return confirm('Tolak user ini?')">
                          ✕ Tolak
                        </a>
                      <?php endif; ?>

                      <a href="pengguna.php?hapus=<?= $row['id_user'] ?>"
                        class="btn-aksi btn-delete"
                        onclick="return confirm('Hapus user ini? Data tidak dapat dikembalikan.')">
                        🗑 Hapus
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endwhile;
            else: ?>
              <tr>
                <td colspan="8" style="text-align:center; color:var(--gray-500)">Belum ada pengguna</td>
              </tr>
            <?php endif; ?>
            <script>
              const row = document.querySelector('.highlight-row');
              if (row) {
                row.scrollIntoView({
                  behavior: 'smooth',
                  block: 'center'
                });
                setTimeout(() => {
                  row.style.background = '';
                }, 3000);
              }
            </script>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
<?php include 'layout/footer.php'; ?>