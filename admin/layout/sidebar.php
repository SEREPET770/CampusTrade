<?php $halaman_aktif = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar">

  <div class="sidebar-profile">
    <div class="sidebar-logo">
      <img src="assets/images/CAMPUS.png" alt="Logo CampusTrade">
    </div>
    <p class="profile-name"><?= htmlspecialchars($_SESSION['nama']) ?? 'admin' ?></p>
    <p class="profile-email"><?= htmlspecialchars($_SESSION['email'] ?? '-') ?></p>
  </div>

  <!-- Menu navigasi -->
  <ul class="sidebar-menu">
    <li>
      <a href="dashboard.php" class="<?= $halaman_aktif === 'dashboard.php' ? 'active' : '' ?>">
        <span>Dashboard</span>
      </a>
    </li>
    <li>
      <a href="pengguna.php" class="<?= $halaman_aktif === 'pengguna.php' ? 'active' : '' ?>">
        <span>Pengguna</span>
      </a>
    </li>
    <li>
      <a href="produk.php" class="<?= $halaman_aktif === 'produk.php' ? 'active' : '' ?>">
        <span>Produk</span>
      </a>
    </li>
    <li class="sidebar-logout">
      <a href="auth/logout.php" onclick="return konfirmasiLogout()">
        <span>Logout</span>
      </a>
    </li>
  </ul>

</div>
<script>
  function konfirmasiLogout() {
    return confirm("Apakah Anda yakin ingin keluar?");
  }
</script>