<?php
require_once '../config/Koneksi.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama        = trim($_POST['nama']);
  $email       = trim($_POST['email']);
  $password    = $_POST['password'];
  $nim         = trim($_POST['nim']);
  $no_whatsapp = trim($_POST['no_whatsapp']);

  // =========================
  // VALIDASI: CEK DUPLIKAT NIM / EMAIL
  // =========================
  /**
   * @var mysqli $koneksi
   */
  $cek = $koneksi->prepare("SELECT nim, email FROM users WHERE nim = ? OR email = ?");
  $cek->bind_param("ss", $nim, $email);
  $cek->execute();
  $hasil_cek = $cek->get_result();

  if ($hasil_cek->num_rows > 0) {
    $row = $hasil_cek->fetch_assoc();
    if ($row['nim'] === $nim) {
      $error = 'NIM ini sudah terdaftar. Silakan login atau gunakan NIM lain.';
    } else {
      $error = 'Email ini sudah terdaftar. Silakan login atau gunakan email lain.';
    }
  }
  $cek->close();

  // =========================
  // LANJUT HANYA JIKA TIDAK ADA ERROR
  // =========================
  if (empty($error)) {

    // Validasi file upload
    if (!isset($_FILES['foto_ktm']) || $_FILES['foto_ktm']['error'] !== UPLOAD_ERR_OK) {
      $error = 'Foto KTM wajib diunggah.';
    } else {
      $ext_asli = strtolower(pathinfo($_FILES['foto_ktm']['name'], PATHINFO_EXTENSION));
      $ext_valid = ['jpg', 'jpeg', 'png'];

      if (!in_array($ext_asli, $ext_valid, true)) {
        $error = 'Format foto KTM harus JPG, JPEG, atau PNG.';
      } elseif ($_FILES['foto_ktm']['size'] > 2 * 1024 * 1024) {
        $error = 'Ukuran foto KTM maksimal 2 MB.';
      }
    }
  }

  // =========================
  // PROSES UPLOAD & INSERT
  // =========================
  if (empty($error)) {
    $foto_ktm = uniqid('ktm_') . '.' . $ext_asli;
    $upload_dir = '../uploads/ktm/';

    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($_FILES['foto_ktm']['tmp_name'], $upload_dir . $foto_ktm)) {
      $error = 'Gagal mengunggah foto KTM. Silakan coba lagi.';
    } else {

      try {
        $sql = "INSERT INTO users (nama, email, password, nim, no_whatsapp, foto_ktm) 
                VALUES (?, ?, ?, ?, ?, ?)";

        /**
         * @var mysqli $koneksi
         */
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ssssss", $nama, $email, $password, $nim, $no_whatsapp, $foto_ktm);
        $stmt->execute();
        $stmt->close();

        header("Location: Login.php?registrasi=sukses");
        exit();
      } catch (mysqli_sql_exception $e) {
        // Hapus foto yang sudah terupload karena insert gagal
        if (file_exists($upload_dir . $foto_ktm)) {
          unlink($upload_dir . $foto_ktm);
        }

        // Tangani duplicate entry secara spesifik
        if ($e->getCode() === 1062) {
          if (str_contains($e->getMessage(), 'nim')) {
            $error = 'NIM ini sudah terdaftar. Silakan login atau gunakan NIM lain.';
          } elseif (str_contains($e->getMessage(), 'email')) {
            $error = 'Email ini sudah terdaftar. Silakan login atau gunakan email lain.';
          } else {
            $error = 'Data ini sudah terdaftar sebelumnya.';
          }
        } else {
          error_log('[register.php] ' . $e->getMessage());
          $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Campus Trade</title>
  <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body>
  <div class="auth-wrapper">
    <div class="auth-left">
      <div class="logo-circle">
        <img src="../assets/images/CAMPUS.png" alt="Logo Campus Trade">
      </div>
      <div class="auth-brand">
        <h1>Campus<br>Trade</h1>
        <p>platform eksklusif khusus <br>mahasiswa terverifikasi</p>
      </div>
    </div>
    <div class="auth-right">
      <div class="auth-tabs">
        <a href="register.php" class="active">Buat Akun</a>
        <a href="Login.php">Login</a>
      </div>

      <form class="auth-form" action="" method="POST" enctype="multipart/form-data">

        <?php if ($error): ?>
          <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
          <p class="form-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <div class="field">
          <label for="nama">Nama Lengkap</label>
          <input type="text" id="nama" name="nama" placeholder="Nama *" required
            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Email *" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="Password *" required minlength="8">
            <button type="button" class="toggle-password" onclick="togglePassword()">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="nim">NIM</label>
          <input type="text" id="nim" name="nim" placeholder="NIM *" required
            value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>">
        </div>
        <div class="field-group">
          <div class="field">
            <label for="no_whatsapp">No. WhatsApp</label>
            <input type="tel" id="no_whatsapp" name="no_whatsapp" placeholder="No. WhatsApp *" required
              value="<?= htmlspecialchars($_POST['no_whatsapp'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="foto_ktm">Foto KTM</label>
            <input type="file" id="foto_ktm" name="foto_ktm" accept="image/jpeg,image/png,image/jpg" required>
          </div>
        </div>
        <button type="submit" class="btn-submit">Daftar</button>
        <p>Sudah punya akun? <a href="Login.php">Masuk</a></p>
      </form>
    </div>
  </div>
  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      const icon = document.getElementById('eye-icon');

      if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
        <line x1="1" y1="1" x2="23" y2="23"/>
      `;
      } else {
        input.type = 'password';
        icon.innerHTML = `
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      `;
      }
    }
  </script>
</body>

</html>