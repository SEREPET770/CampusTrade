<?php
session_start();
require_once "../config/Koneksi.php";

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = $_POST['email'];
  $password = $_POST['password'];

  $sql = "SELECT * FROM users WHERE email = ?";
  /**
   * @var mysqli $koneksi
   */
  $stmt = $koneksi->prepare($sql);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if ($user && password_verify($password, $user['password'])) {

    //cek status verifikasi
    if ($user['status_verifikasi'] === 'menunggu') {
      $_SESSION['error'] = "Akun Anda sedang menunggu verifikasi";
    } else if ($user['status_verifikasi'] === 'ditolak') {
      $_SESSION['error'] = 'Akun anda telah ditolak. Silakan hubungi admin untuk informasi lebih lanjut.';
    } else {
      // status jika sudah diverifikasi
      $_SESSION['id_user'] = $user['id_user'];
      $_SESSION['nama'] = $user['nama'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['email'] = $user['email'];

      if ($user['role'] === 'admin') {
        header("Location: ../dashboard.php");
      } else {
        header("Location: ../../user/dashboard.php");
      }
      exit();
    }
  } else {
    $_SESSION['error'] = "Email atau password salah.";
  }
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Campus Trade</title>
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
        <a href="register.php">Buat Akun</a>
        <a href="Login.php" class="active">Login</a>
      </div>

      <form class="auth-form" action="" method="POST">

        <?php if ($error): ?>
          <p style="color:red;font-size:13px"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Email *" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="Password *" required>
            <button type="button" class="toggle-password" onclick="togglePassword()">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">Masuk</button>
        <p>Belum punya akun? <a href="register.php">Daftar</a></p>

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
            <line x1="1" y1="1" x2="23" y2="23"/>`;
      } else {
        input.type = 'password';
        icon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>`;
      }
    }
  </script>
</body>

</html>