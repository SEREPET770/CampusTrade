<?php
// admin/layout/topnav.php
// Pastikan session sudah dimulai sebelum file ini di-include.
$admin_name = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin';
$admin_role = 'Administrator';
$admin_initial = strtoupper(mb_substr($admin_name, 0, 1));

// Deteksi halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="topnav">
  <div class="topnav-inner">

    <!-- Brand -->
    <a href="dashboard.php" class="topnav-brand">
      <span class="topnav-title">CampusTrade</span>
      <span class="topnav-badge">ADMIN</span>
    </a>

    <!-- Nav Links (Desktop) -->
    <div class="topnav-links">
      <a href="dashboard.php" class="topnav-link <?= $current_page === 'dashboard.php'  ? 'active' : '' ?>">Dashboard</a>
      <a href="pengguna.php" class="topnav-link <?= $current_page === 'pengguna.php'   ? 'active' : '' ?>">Pengguna</a>
      <a href="produk.php" class="topnav-link <?= $current_page === 'produk.php'     ? 'active' : '' ?>">Produk</a>
      <a href="pembayaran.php" class="topnav-link <?= $current_page === 'pembayaran.php' ? 'active' : '' ?>">Pembayaran</a>
    </div>

    <!-- Profil + Dropdown -->
    <div class="topnav-profile" id="profileToggle">
      <div class="topnav-avatar"><?= htmlspecialchars($admin_initial) ?></div>
      <span class="topnav-name"><?= htmlspecialchars($admin_name) ?></span>
      <span class="topnav-caret">▼</span>

      <div class="topnav-dropdown">
        <div class="dropdown-info">
          <strong><?= htmlspecialchars($admin_name) ?></strong>
          <small><?= htmlspecialchars($admin_role) ?></small>
        </div>
        <hr class="dropdown-divider">
        <a href="auth/logout.php" class="dropdown-item dropdown-logout"
          onclick="return confirm('Yakin ingin keluar?')">
          🚪 Keluar
        </a>
      </div>
    </div>

    <!-- Hamburger (Mobile) -->
    <button class="topnav-hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span>
      <span></span>
      <span></span>
    </button>

  </div><!-- /.topnav-inner -->

  <!-- Mobile Menu -->
  <div class="topnav-mobile" id="mobileMenu">
    <a href="dashboard.php" class="mobile-link <?= $current_page === 'dashboard.php'  ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="pengguna.php" class="mobile-link <?= $current_page === 'pengguna.php'   ? 'active' : '' ?>">👥 Pengguna</a>
    <a href="produk.php" class="mobile-link <?= $current_page === 'produk.php'     ? 'active' : '' ?>">📦 Produk</a>
    <a href="pembayaran.php" class="mobile-link <?= $current_page === 'pembayaran.php' ? 'active' : '' ?>">🧾 Pembayaran</a>
    <hr class="dropdown-divider">
    <a href="auth/logout.php" class="mobile-link mobile-logout"
      onclick="return confirm('Yakin ingin keluar?')">
      🚪 Keluar
    </a>
  </div>

</nav>

<script>
  // ── Toggle Dropdown Profil ────────────────────────────────────────────────
  const profileToggle = document.getElementById('profileToggle');
  profileToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    this.classList.toggle('open');
  });
  document.addEventListener('click', function() {
    profileToggle.classList.remove('open');
  });

  // ── Toggle Mobile Menu ────────────────────────────────────────────────────
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburgerBtn.addEventListener('click', function() {
    mobileMenu.classList.toggle('open');
  });
</script>