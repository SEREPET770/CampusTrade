<?php
session_start();

//if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
// header("Location: ../auth/login.php");
// exit();
//}

require_once "../config/Koneksi.php";

$error = '';
$success = '';

// Menangkap notifikasi sukses setelah form berhasil direset (Post-Redirect-Get)
if (isset($_SESSION['sukses_tambah'])) {
  $success = $_SESSION['sukses_tambah'];
  unset($_SESSION['sukses_tambah']); // Hapus session agar pesan tidak muncul terus menerus
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_user     = $_SESSION['id_user'];
  $id_kategori = $_POST['id_kategori'];
  $id_lokasi   = $_POST['id_lokasi'];
  $nama_produk = $_POST['nama_produk'];
  $deskripsi   = $_POST['deskripsi'];

  // Membersihkan format Rp dan titik agar menjadi angka murni
  $harga      = preg_replace('/[^0-9]/', '', $_POST['harga']);

  $kondisi     = ucfirst($_POST['kondisi']);
  $alasan_jual = !empty($_POST['alasan_jual']) ? $_POST['alasan_jual'] : null;
  $kelebihan   = !empty($_POST['kelebihan']) ? $_POST['kelebihan'] : null;
  $kekurangan   = !empty($_POST['kekurangan']) ? $_POST['kekurangan'] : null;
  $is_buku = false;
  $brand = !empty($_POST['brand']) ? $_POST['brand'] : null;
  $penulis_penerbit = !empty($_POST['penulis_penerbit']) ? $_POST['penulis_penerbit'] : null;

  if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === 0) {
    $nama_baru = uniqid('prod_') . '.' . strtolower(pathinfo($_FILES['foto_produk']['name'], PATHINFO_EXTENSION));
    $target_dir = "../uploads/produk/";

    // Pastikan folder uploads/produk/ sudah ada
    if (!is_dir($target_dir)) {
      mkdir($target_dir, 0755, true);
    }

    if (move_uploaded_file($_FILES['foto_produk']['tmp_name'], $target_dir . $nama_baru)) {

      /** @var mysqli $koneksi */
      $sql_produk = "INSERT INTO produk (id_user, id_kategori, id_lokasi, nama_produk, deskripsi, harga, kondisi, alasan_jual, kelebihan, kekurangan, brand, penulis_penerbit) 
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $stmt = $koneksi->prepare($sql_produk);

      if ($stmt) {
        $stmt->bind_param("iiisssssssss", $id_user, $id_kategori, $id_lokasi, $nama_produk, $deskripsi, $harga, $kondisi, $alasan_jual, $kelebihan, $kekurangan, $brand, $buku);

        if ($stmt->execute()) {
          $id_p = $koneksi->insert_id;
          $koneksi->query("INSERT INTO gambar_produk (id_produk, image_path) VALUES ($id_p, '$nama_baru')");

          // SET NOTIFIKASI DAN REDIRECT UNTUK MERESET FORM
          $_SESSION['sukses_tambah'] = "Produk berhasil diajukan! Menunggu verifikasi admin.";
          header("Location: tambah_produk.php");
          exit();
        } else {
          // Jika gagal, tampilkan error asli dari Database agar mudah dilacak
          $error = "Gagal menyimpan ke database SQL: " . $stmt->error;
        }
      } else {
        $error = "Error Query SQL: " . $koneksi->error;
      }
    } else {
      $error = "Gagal memindahkan file gambar produk ke folder server.";
    }
  } else {
    $error = "Harap unggah foto produk Anda!";
  }
}

/** @var mysqli $koneksi */
$query_kat = "SELECT * FROM kategori_barang ORDER BY nama_kategori ASC";
$result_kat = $koneksi->query($query_kat);
$lokasi_query = "select * from lokasi order by nama_lokasi ASC";
$lokasi_result = $koneksi->query($lokasi_query);
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Produk - CampusTrade</title>
  <link rel="stylesheet" href="../assets/css/tambah_produk.css">
  <style>
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      font-weight: 500;
    }

    .alert-success {
      background-color: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #047857;
    }

    .alert-error {
      background-color: #fef2f2;
      border: 1px solid #fca5a5;
      color: #b91c1c;
    }
  </style>
</head>

<body>

  <div class="form-wrapper">
    <?php
    $url_kembali = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'user_dashboard.php';
    ?>
    <a href="<?= htmlspecialchars($url_kembali) ?>" class="btn-kembali" onclick="if(history.length > 1){ history.back(); return false; }">
      &laquo; Kembali
    </a>
    <div class="form-header">
      <h2>Informasi Produk</h2>
      <p>Lengkapi detail produk Anda untuk diajukan proses verifikasi oleh Admin.</p>
    </div>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form action="tambah_produk.php" method="POST" enctype="multipart/form-data" class="product-form">

      <div class="form-left">
        <div class="image-upload-container">
          <label for="foto_produk" class="image-upload-box">
            <div class="upload-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
              </svg>
            </div>
            <span class="upload-title">Unggah Foto Produk</span>
            <p class="upload-subtitle">Format: JPG, JPEG, PNG (Maks 2MB)</p>
            <input type="file" id="foto_produk" name="foto_produk" accept="image/*" required onchange="previewImage(event)">
            <img id="img-preview" src="#" alt="Pratinjau Gambar" style="display:none;">
          </label>
        </div>
      </div>

      <div class="form-right">
        <div class="form-group">
          <label for="nama_produk">Nama Produk *</label>
          <input type="text" id="nama_produk" name="nama_produk" placeholder="Macbook Pro M1 2020" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="id_kategori">Kategori Barang *</label>
            <select id="id_kategori" name="id_kategori" required class="select-placeholder">
              <option value="" disabled selected>Pilih Kategori</option>
              <?php while ($row = $result_kat->fetch_assoc()): ?>
                <option value="<?= $row['id_kategori'] ?>"><?= htmlspecialchars($row['nama_kategori']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="kondisi">Kondisi Barang *</label>
            <select id="kondisi" name="kondisi" required class="select-placeholder">
              <option value="bekas" selected>Bekas (Second)</option>
              <option value="baru">Baru</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="id_lokasi">Lokasi Kampus / COD *</label>
            <select id="id_lokasi" name="id_lokasi" required>
              <option value="" disabled selected>-- Pilih Lokasi Kampus --</option>
              <?php
              // Mengambil data dari tabel lokasi secara dinamis
              $result_lokasi = $koneksi->query("SELECT * FROM lokasi ORDER BY nama_lokasi ASC");
              while ($lok = $result_lokasi->fetch_assoc()):
              ?>
                <option value="<?= $lok['id_lokasi'] ?>"><?= htmlspecialchars($lok['nama_lokasi']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="harga">Harga Jual *</label>
            <input type="text" id="harga" name="harga" placeholder="Rp 12.500.000" required>
          </div>
        </div>

        <div class="form-group" id="field-brand">
          <label for="brand">Brand / Merek Produk</label>
          <input type="text" id="brand" name="brand" placeholder="Contoh: Asus, Canon, Apple, Erigo">
        </div>

        <div class="form-group" id="field-buku" style="display: none;">
          <label for="penulis_penerbit">Penulis / Penerbit Buku</label>
          <input type="text" id="penulis_penerbit" name="penulis_penerbit" placeholder="Contoh: Andrea Hirata / Bentang Pustaka">
        </div>

        <div class="form-group">
          <label for="alasan_jual">Alasan Dijual</label>
          <input type="text" id="alasan_jual" name="alasan_jual" placeholder="Sudah lulus kuliah / jarang digunakan">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="kelebihan">Kelebihan Produk (Optional)</label>
            <textarea id="kelebihan" name="kelebihan" rows="3" placeholder="Contoh: Baterai masih awet, mulus, free pouch"></textarea>
          </div>
          <div class="form-group">
            <label for="kekurangan">Kekurangan / Minus (Optional)</label>
            <textarea id="kekurangan" name="kekurangan" rows="3" placeholder="Contoh: Ada lecet sedikit di pojok kiri bawah, kelengkapan tanpa box"></textarea>
          </div>
        </div>

        <div class="form-group">
          <label for="deskripsi">Deskripsi Lengkap Produk *</label>
          <textarea id="deskripsi" name="deskripsi" rows="6" required placeholder="Macbook Pro M1 2020 Mulus 99%&#10;Kelengkapan:&#10;- Unit&#10;- Charger&#10;- Box&#10;Minus: Tidak ada"></textarea>
        </div>

        <div class="form-actions">
          <?php
          $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'user_dashboard.php'; ?>
          <a href="<?= htmlspecialchars($back_url) ?>" class="btn-batal" onclick="if(history.length > 1) {history.back(); return false;}">Batal</a>
          <button type=" submit" class="btn-simpan">Ajukan Verifikasi Produk</button>
        </div>
      </div>

    </form>
  </div>

  <script>
    function previewImage(event) {
      const reader = new FileReader();
      reader.onload = function() {
        const output = document.getElementById('img-preview');
        const icon = document.querySelector('.upload-icon');
        const title = document.querySelector('.upload-title');
        const subtitle = document.querySelector('.upload-subtitle');

        output.src = reader.result;
        output.style.display = 'block';

        if (icon) icon.style.display = 'none';
        if (title) title.style.display = 'none';
        if (subtitle) subtitle.style.display = 'none';
      }
      reader.readAsDataURL(event.target.files[0]);
    }
    document.getElementById('id_kategori').addEventListener('change', function() {
      let kategori = this.options[this.selectedIndex].text.toLowerCase();
      let containerBuku = document.getElementById('field-buku'); // misal ID div-nya
      let containerUmum = document.getElementById('field-umum');

      if (kategori === 'buku') {
        containerBuku.style.display = 'block';
        containerUmum.style.display = 'none';
      } else {
        containerBuku.style.display = 'none';
        containerUmum.style.display = 'block';
      }
    });
  </script>
  <script>
    document.getElementById('id_kategori').addEventListener('change', function() {
      // Mendapatkan teks nama kategori yang sedang dipilih
      var namaKategori = this.options[this.selectedIndex].text.toLowerCase();

      var fieldBrand = document.getElementById('field-brand');
      var fieldBuku = document.getElementById('field-buku');
      var inputBrand = document.getElementById('brand');
      var inputBuku = document.getElementById('penulis_penerbit');

      // Jika user memilih kategori yang ada kata "buku"-nya
      if (namaKategori.includes('buku')) {
        fieldBuku.style.display = 'block';
        fieldBrand.style.display = 'none';
        inputBrand.value = ''; // Mengosongkan value brand
      } else {
        fieldBrand.style.display = 'block';
        fieldBuku.style.display = 'none';
        inputBuku.value = ''; // Mengosongkan value penulis/penerbit
      }
    });
  </script>
</body>

</html>