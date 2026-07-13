<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$nama_login = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Mahasiswa';
require_once "../config/Koneksi.php";

// --- FUNGSI HELPER ---
function formatRupiah($angka)
{
  return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatTanggal($tanggal)
{
  return date('d M Y', strtotime($tanggal));
}

// 2. TANGKAP & SANITASI INPUT GET
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori  = isset($_GET['kategori']) ? intval($_GET['kategori']) : 0;
$harga_min = isset($_GET['harga_min']) ? intval($_GET['harga_min']) : 0;
$harga_max = isset($_GET['harga_max']) ? intval($_GET['harga_max']) : 0;
$halaman   = isset($_GET['halaman']) ? max(1, intval($_GET['halaman'])) : 1;
$id_lokasi = isset($_GET['id_lokasi']) ? intval($_GET['id_lokasi']) : 0;
$urutkan = isset($_GET['urutkan']) ? trim($_GET['urutkan']) : '';

$per_halaman = 32;
$offset      = ($halaman - 1) * $per_halaman;

$where  = ["p.status_produk = 'tersedia'"];
$types  = "";
$values = [];

if (!empty($search)) {
  $where[] = "p.nama_produk LIKE ?";
  $types .= "s";
  $values[] = "%" . $search . "%";
}
if ($kategori > 0) {
  $where[] = "p.id_kategori = ?";
  $types .= "i";
  $values[] = $kategori;
}
if ($harga_min > 0) {
  $where[] = "p.harga >= ?";
  $types .= "i";
  $values[] = $harga_min;
}
if ($harga_max > 0 && $harga_max >= $harga_min) {
  $where[] = "p.harga <= ?";
  $types .= "i";
  $values[] = $harga_max;
}

if ($id_lokasi > 0) {
  $where[] = "p.id_lokasi = ?";
  $types .= "i";
  $values[] = $id_lokasi;
}
$where_sql = implode(" AND ", $where);

// Helper Fungsi untuk Bind Parameter secara dinamis ke mysqli_stmt
function bindDynamic(mysqli_stmt $stmt, string $types, array $values)
{
  if (!empty($types)) {
    $bind_names[] = $types;
    for ($i = 0; $i < count($values); $i++) {
      $bind_name = 'bind' . $i;
      $$bind_name = $values[$i];
      $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
  }
}
/** @var mysqli $koneksi */
// 4. HITUNG TOTAL PRODUK (Untuk Pagination & Info Total)
$sql_count = "
SELECT COUNT(*) as total
FROM produk p
WHERE ($where_sql)";
$stmt_count = $koneksi->prepare($sql_count);
bindDynamic($stmt_count, $types, $values);
$stmt_count->execute();
$total_produk = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_halaman = max(1, ceil($total_produk / $per_halaman));

// 5. AMBIL DATA PRODUK HALAMAN AKTIF
$order_by = "p.created_at DESC";
if ($urutkan === 'harga_asc') {
  $order_by = "p.harga ASC";
} elseif ($urutkan === 'harga_desc') {
  $order_by = "p.harga DESC";
}

$sql_data = "
    SELECT p.id_produk, p.nama_produk, p.harga, p.created_at, l.nama_lokasi,
           (SELECT gp.image_path FROM gambar_produk gp WHERE gp.id_produk = p.id_produk LIMIT 1) as foto
    FROM produk p
    LEFT JOIN lokasi l ON p.id_lokasi = l.id_lokasi
    WHERE ($where_sql)
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";
$stmt_data = $koneksi->prepare($sql_data);
$types_data = $types . "ii";
$values_data = array_merge($values, [$per_halaman, $offset]);
bindDynamic($stmt_data, $types_data, $values_data);
$stmt_data->execute();
$produk_list = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

// 6. AMBIL DATA KATEGORI UNTUK FILTER
$kategori_list = [];
$res_kat = $koneksi->query("SELECT id_kategori, nama_kategori FROM kategori_barang ORDER BY nama_kategori ASC");
if ($res_kat) {
  $kategori_list = $res_kat->fetch_all(MYSQLI_ASSOC);
}

$lokasi_list = [];
$res_lok = $koneksi->query("SELECT id_lokasi, nama_lokasi FROM lokasi ORDER BY nama_lokasi ASC");
if ($res_lok) {
  $lokasi_list = $res_lok->fetch_all(MYSQLI_ASSOC);
}
// Build URL Pagination
$params = $_GET;
function getPageUrl($page, $params)
{
  $params['halaman'] = $page;
  return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Katalog Produk - CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/produk.css">
</head>

<body>

  <header class="navbar">
    <div class="nav-left">
      <a href="user_dashboard.php" class="logo">CampusTrade</a>
      <nav class="nav-links">
        <a href="user_dashboard.php">Beranda</a>
        <a href="user_produk.php" class="active">Produk</a>
        <a href="tambah_produk.php">Tambah Produk</a>
      </nav>
    </div>

    <form action="user_produk.php" method="GET" class="nav-search-form">
      <?php if ($kategori > 0): ?> <input type="hidden" name="kategori" value="<?= $kategori ?>"> <?php endif; ?>
      <?php if ($harga_min > 0): ?> <input type="hidden" name="harga_min" value="<?= $harga_min ?>"> <?php endif; ?>
      <?php if ($harga_max > 0): ?> <input type="hidden" name="harga_max" value="<?= $harga_max ?>"> <?php endif; ?>

      <input type="text" name="search" placeholder="Cari produk di CampusTrade..." value="<?= isset($search) ? htmlspecialchars($search) : '' ?>">
      <button type="submit" class="nav-search-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>

    <div class="profile-pill-wrapper">
      <div class="profile-pill" id="profilePillBtn">
        <span><?= htmlspecialchars($nama_login) ?></span>
      </div>

      <div class="profile-dropdown-card" id="profileDropdown">
        <div class="dropdown-header">
          <div class="avatar-large"></div>
          <div class="user-info-text">
            <h3><?= htmlspecialchars($nama_login) ?></h3>
            <p>Mahasiswa</p>
          </div>
        </div>

        <a href="#" class="btn-lihat-profil">Lihat Profil</a>

        <div class="dropdown-section-label">Pembeli</div>
        <ul class="dropdown-menu-list">
          <li><a href="pesanan_saya.php">Pesanan Saya</a></li>
        </ul>

        <div class="dropdown-section-label">Penjual</div>
        <ul class="dropdown-menu-list">
          <li><a href="../seller/dashboard.php">Dashboard Toko</a></li>
          <li><a href="../seller/produk.php">Produk Saya</a></li>
          <li><a href="tambah_produk.php">Tambah Produk</a></li>
          <li><a href="../seller/pesanan_masuk.php">Pesanan Masuk</a></li>
          <li><a href="../seller/pembayaran_masuk.php">Verifikasi Pembayaran</a></li>
        </ul>

        <hr class="dropdown-divider">
        <a href="../auth/logout.php" class="btn-exit">Logout</a>
      </div>
    </div>
  </header>

  <main class="container">
    <nav class="breadcrumb">
      <a href="user_dashboard.php">Beranda</a>
      <span class="separator">&gt;</span>

      <?php if (!empty($search)): ?>
        <a href="user_produk.php">Produk</a>
        <span class="separator">&gt;</span>
        <span class="current">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
      <?php elseif (!empty($_GET['kategori'])): ?>
        <a href="user_produk.php">Produk</a>
        <span class="separator">&gt;</span>
        <span class="current">Filter Kategori</span>
      <?php else: ?>
        <span class="current">Produk</span>
      <?php endif; ?>
    </nav>


    <div class="top-section">
      <div class="top-row">
        <div class="page-info">
          <h2>Ditemukan</h2>
          <p>Total: <strong><?= $total_produk ?></strong> Produk</p>
        </div>

        <div class="top-filter-bar">
          <div class="top-filter-item">
            <label class="top-filter-label">Lokasi:</label>
            <select name="id_lokasi" id="lokasi-select" class="top-filter-select" form="filter-form" onchange="this.form.submit()">
              <option value="0">Semua Lokasi</option>
              <?php foreach ($lokasi_list as $lok): ?>
                <option value="<?= $lok['id_lokasi'] ?>" <?= $id_lokasi == $lok['id_lokasi'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($lok['nama_lokasi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="top-filter-item">
            <label class="top-filter-label">Urutkan:</label>
            <select name="urutkan" id="urutkan-select" class="top-filter-select" form="filter-form" onchange="this.form.submit()">
              <option value="" <?= $urutkan == '' ? 'selected' : '' ?>>Terbaru</option>
              <option value="harga_asc" <?= $urutkan == 'harga_asc' ? 'selected' : '' ?>>Harga: Rendah ke Tinggi</option>
              <option value="harga_desc" <?= $urutkan == 'harga_desc' ? 'selected' : '' ?>>Harga: Tinggi ke Rendah</option>
            </select>
          </div>
        </div>
      </div>

      <form id="filter-form" method="GET" action="user_produk.php" class="filter-controls">
        <?php if (!empty($search)): ?>
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <?php endif; ?>

        <div class="filter-box">
          <label>Kategori:</label>
          <select name="kategori" id="kategori-select">
            <option value="0">Semua Kategori</option>
            <?php foreach ($kategori_list as $kat): ?>
              <option value="<?= $kat['id_kategori'] ?>" <?= $kategori == $kat['id_kategori'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($kat['nama_kategori']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-box">
          <label>Filter Harga:</label>
          <div class="price-inputs">
            <input type="number" name="harga_min" placeholder="Rp Min" value="<?= $harga_min ?: '' ?>" min="0">
            <span>-</span>
            <input type="number" name="harga_max" placeholder="Rp Max" value="<?= $harga_max ?: '' ?>" min="0">
          </div>
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn-apply">Terapkan</button>
          <a href="user_produk.php" class="btn-reset">Reset</a>
        </div>
      </form>
    </div>


    <?php if (empty($produk_list)): ?>
      <div class="empty-state">
        <p>Maaf, tidak ada produk yang sesuai dengan pencarian Anda.</p>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($produk_list as $p):
          $foto = !empty($p['foto']) ? "../uploads/produk/" . htmlspecialchars($p['foto']) : "../assets/img/no-image.png";
        ?>
          <article class="product-card" data-id="<?= $p['id_produk'] ?>">
            <div class="card-image">
              <img src="<?= $foto ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>">
            </div>
            <div class="card-content">
              <h3 class="product-name"><?= htmlspecialchars($p['nama_produk']) ?></h3>
              <p class="product-price"><?= formatRupiah($p['harga']) ?></p>
              <div class="product-meta">
                <span class="location">📍 <?= !empty($p['nama_lokasi']) ? htmlspecialchars($p['nama_lokasi']) : 'Tidak Diketahui' ?></span>
                <span class="date">Ditambahkan <?= formatTanggal($p['created_at']) ?></span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($total_halaman > 1): ?>
      <div class="pagination">
        <?php if ($halaman > 1): ?>
          <a href="<?= getPageUrl($halaman - 1, $params) ?>" class="page-btn">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_halaman; $i++): ?>
          <a href="<?= getPageUrl($i, $params) ?>" class="page-btn <?= $i == $halaman ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($halaman < $total_halaman): ?>
          <a href="<?= getPageUrl($halaman + 1, $params) ?>" class="page-btn">Next &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </main>

  <script src="../assets/js/produk.js"></script>
</body>

</html>