<?php
session_start();
require_once 'config/Koneksi.php';

if (!isset($_SESSION['id_user'])) {
  header("Location: ../auth/login.php");
  exit();
}

if ($_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}
// proses ubah status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'])) {
  $id_produk     = $_POST['id_produk'];
  $status_produk = $_POST['status_produk'];

  /** @var mysqli $koneksi */
  $stmt = $koneksi->prepare("UPDATE produk SET status_produk = ? WHERE id_produk = ?");
  $stmt->bind_param("si", $status_produk, $id_produk);
  $stmt->execute();

  header("Location: produk.php");
  exit();
}
/** @var mysqli $koneksi  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
  $id = $_POST['hapus_id'];
  $stmt = $koneksi->prepare("DELETE FROM produk WHERE id_produk = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  header("Location: produk.php");
  exit();
}
/** @var mysqli $koneksi */
$sql = "SELECT p.*, u.nama AS nama_penjual, 
               (SELECT image_path FROM gambar_produk 
                WHERE id_produk = p.id_produk 
                LIMIT 1) AS gambar
        FROM produk p
        JOIN users u ON p.id_user = u.id_user
        ORDER BY p.created_at DESC";

$result = $koneksi->query($sql);
?>
<?php include 'layout/header.php'; ?>

<div class="admin-wrapper">
  <?php include 'layout/sidebar.php'; ?>

  <div class="content">
    <h1 class="page-title">Kelola Produk</h1>

    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produk</th>
            <th>Penjual</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                <td><?= htmlspecialchars($row['nama_penjual']) ?></td>
                <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                <td>
                  <form action="" method="POST">
                    <input type="hidden" name="id_produk" value="<?= $row['id_produk'] ?>">
                    <select name="status_produk" onchange="this.form.submit()">
                      <option value="tersedia" <?= $row['status_produk'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                      <option value="terjual" <?= $row['status_produk'] === 'terjual'  ? 'selected' : '' ?>>Terjual</option>
                      <option value="ditolak" <?= $row['status_produk'] === 'ditolak'  ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                  </form>
                </td>
                <td>
                  <button type="button" class="btn-detail">Detail</button>
                  <form action="" method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                    <input type="hidden" name="hapus_id" value="<?= $row['id_produk'] ?>">
                    <button type="submit" class="btn-hapus">Hapus</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align:center">Belum ada produk.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>