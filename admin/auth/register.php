<?php
require_once '../config/Koneksi.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama        = $_POST['nama'];
  $email       = $_POST['email'];
  $password    = $_POST['password'];
  $nim         = $_POST['nim'];
  $no_whatsapp = $_POST['no_whatsapp'];

  // upload foto ktm
  $ext = pathinfo($_FILES['foto_ktm']['name'], PATHINFO_EXTENSION);
  $foto_ktm = uniqid('ktm_') . '.' . $ext;
  move_uploaded_file($_FILES['foto_ktm']['tmp_name'], '../uploads/ktm/' . $foto_ktm);

  // masukkan data ke database
  $sql = "INSERT INTO users (nama, email, password, nim, no_whatsapp, foto_ktm) 
          VALUES (?, ?, ?, ?, ?, ?)";

  /**
   * @var mysqli $koneksi
   */
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param("ssssss", $nama, $email, $password, $nim, $no_whatsapp, $foto_ktm);
  $stmt->execute();

  header("Location: Login.php");
  exit();
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
          <p><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
          <p><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <div class="field">
          <label for="nama">Nama Lengkap</label>
          <input type="text" id="nama" name="nama" placeholder="Nama *" required>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Email *" required>
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
          <input type="text" id="nim" name="nim" placeholder="NIM *" required>
        </div>
        <div class="field-group">
          <div class="field">
            <label for="no_whatsapp">No. WhatsApp</label>
            <input type="tel" id="no_whatsapp" name="no_whatsapp" placeholder="No. WhatsApp *" required>
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