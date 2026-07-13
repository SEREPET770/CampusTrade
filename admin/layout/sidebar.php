<?php $halaman_aktif = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar">

  <div class="sidebar-profile">
    <div class="sidebar-logo">
      <img src="assets/images/CAMPUS.png" alt="Logo CampusTrade">
    </div>
    <p class="profile-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'admin') ?></p>
    <p class="profile-email"><?= htmlspecialchars($_SESSION['email'] ?? '-') ?></p>
  </div>

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
    <li>
      <a href="pembayaran.php" class="<?= $halaman_aktif === 'pembayaran.php' ? 'active' : '' ?>">
        <span>Pembayaran</span>
      </a>
    </li>
    <li class="sidebar-logout">
      <a href="#" onclick="showLogoutModal()">
        <span>Logout</span>
      </a>
    </li>
  </ul>

</div>

<div id="logoutModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-icon"></div>
    <img src="../assets/images/CAMPUS.png">
    <h3 class="modal-title">Konfirmasi Logout</h3>
    <p class="modal-desc">Apakah Anda yakin ingin keluar dari sistem?</p>
    <div class="modal-actions">
      <button onclick="hideLogoutModal()" class="modal-btn-batal">Batal</button>
      <a href="auth/logout.php" class="modal-btn-logout">Ya, Logout</a>
    </div>
  </div>
</div>

<script>
  function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'flex';
  }

  function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'none';
  }
  // klik di luar modal untuk tutup
  document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) hideLogoutModal();
  });
</script>