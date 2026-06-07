<?php
session_start();

if (!isset($_SESSION['id_user'])) {
  header("Location: ../auth/login.php");
  exit();
}
//
if ($_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}
require_once 'config/Koneksi.php';

/**
 * @var mysqli $koneksi
 */
// Hitung total user
$total_users = $koneksi->query("SELECT COUNT(*) as total from users where role ='user'")->fetch_assoc()['total'];

// Hitung total verifikasi pending
$total_verifikasi = $koneksi->query("SELECT COUNT(*) as total from users where status_verifikasi = 'menunggu'")->fetch_assoc()['total'];

// Hitung total produk
$total_produk = $koneksi->query("select count(*) as total from produk where status_aktif =1")->fetch_assoc()['total'];

$aktivitas = $koneksi->query("select u.nama, 'mendaftar di CampusTrade' as keterangan from users u union all select u.nama
, CONCAT('menambahkan produk \"', p.nama_produk, '\"') as keterangan from produk p join users u on p.id_user = u.id_user order by nama desc limit 5");

//ambil verifikasi pending
$verifikasi_menunggu = $koneksi->query("select nama, nim, no_whatsapp from users where status_verifikasi = 'menunggu' and role ='user' order by created_at desc limit 5");

//ambil produk pending
$produk_pending = $koneksi->query("select nama_produk, harga, kondisi from produk where status_aktif = 0 order by created_at desc limit 5");

?>
<?php include 'layout/header.php'; ?>
<div class="admin-wrapper">
  <?php include 'layout/sidebar.php'; ?>
  <div class="content">
    <div class="content-header">
      <h1>Dashboard Admin</h1>
    </div>
    <div class="content-body">

      <!-- Card Statistik -->
      <div class="stats-grid">
        <div class="stat-card stat-blue" onclick="window.location='pengguna.php'" style="cursor:pointer">
          <div class="stat-label">Pengguna</div>
          <div class="stat-value"><?= $total_users ?></div>
        </div>
        <div class="stat-card stat-outline" onclick="window.location='pengguna.php'" style="cursor:pointer">
          <div class="stat-label">Verifikasi</div>
          <div class="stat-value"><?= $total_verifikasi ?></div>
        </div>
        <div class="stat-card stat-orange" onclick="window.location='produk.php'" style="cursor:pointer">
          <div class="stat-label">Produk</div>
          <div class="stat-value"><?= $total_produk ?></div>
        </div>
      </div>

      <!-- Tabel -->
      <div class="table-grid">
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th style="text-align: center;">Aktivitas Terbaru</th>
              </tr>
            </thead>
            <tbody style="text-align: center;">
              <?php
              if ($aktivitas->num_rows > 0) :
                while ($row = $aktivitas->fetch_assoc()) : ?>
                  <tr>
                    <td><?php
                        $href = str_contains($row['keterangan'], 'produk') ? 'produk.php' : 'pengguna.php';
                        ?>
                      <a href="<?= $href ?>" style="text-decoration:none; color:inherit">
                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                        <?= htmlspecialchars($row['keterangan']) ?>
                      </a>
                    </td>
                  </tr>
                <?php endwhile;
              else: ?>
                <tr>
                  <td style="text-align:center;">Belum ada aktivitas</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th style="text-align: center;">Verifikasi Pending</th>
              </tr>
            </thead>
            <tbody style="text-align: center;">
              <?php
              if ($verifikasi_menunggu->num_rows > 0) :
                while ($row = $verifikasi_menunggu->fetch_assoc()) : ?>
                  <tr>
                    <td>
                      <a href="pengguna.php?highlight=<?= $row['nim'] ?>" style="text-decoration:none; color:inherit">
                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                        — <?= htmlspecialchars($row['nim']) ?>
                      </a>
                    </td>
                  </tr>
                <?php endwhile;
              else: ?>
                <tr>
                  <td style="text-align:center; color: var(--gray-500);">Tidak ada verifikasi pending</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th style="text-align: center;">Produk Pending</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php include 'layout/footer.php'; ?>