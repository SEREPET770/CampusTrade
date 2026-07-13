<?php
$seller_name = $_SESSION['nama'] ?? 'Penjual';
$seller_initial = mb_strtoupper(mb_substr($seller_name, 0, 1));
$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
  'dashboard.php' => ['label' => 'Dashboard', 'icon' => ''],
  'produk.php' => ['label' => 'Produk Saya', 'icon' => ''],
  'pesanan_masuk.php' => ['label' => 'Pesanan Masuk', 'icon' => ''],
  'pembayaran_masuk.php' => ['label' => 'Pembayaran Masuk', 'icon' => ''],
  'metode_pembayaran.php' => ['label' => 'Metode Pembayaran', 'icon' => ''],
];
?>
<nav class="seller-topnav">
  <div class="seller-topnav-inner">

    <a href="dashboard.php" class="seller-brand">
      <span class="seller-brand-title">CampusTrade</span>
      <span class="seller-brand-badge">Toko</span>
    </a>

    <div class="seller-nav-links">
      <?php foreach ($nav_items as $file => $item):
        $active = $current_page === $file ? 'active' : '';
      ?>
        <a href="<?= $file ?>" class="seller-nav-link <?= $active ?>">
          <?= $item['icon'] ?> <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <a href="../user/user_dashboard.php" class="seller-btn-marketplace">← Marketplace</a>

    <div class="seller-profile" id="profileToggle">
      <div class="seller-avatar"><?= htmlspecialchars($seller_initial) ?></div>
      <span class="seller-name"><?= htmlspecialchars($seller_name) ?></span>
      <span class="seller-caret">▼</span>
      <div class="seller-dropdown">
        <div class="seller-dropdown-info">
          <strong><?= htmlspecialchars($seller_name) ?></strong>
          <small>Penjual</small>
        </div>
        <hr class="seller-dropdown-divider">
        <a href="../user/user_dashboard.php" class="seller-dropdown-item">Kembali ke Marketplace</a>
        <a href="../auth/logout.php" class="seller-dropdown-item seller-dropdown-logout"
          onclick="return confirm('Yakin ingin keluar?')">Logout</a>
      </div>
    </div>

    <button class="seller-hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

  </div>

  <div class="seller-mobile-menu" id="mobileMenu">
    <?php foreach ($nav_items as $file => $item):
      $active = $current_page === $file ? 'active' : '';
    ?>
      <a href="<?= $file ?>" class="seller-mobile-link <?= $active ?>">
        <?= $item['icon'] ?> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
    <hr class="seller-dropdown-divider">
    <a href="../user/user_dashboard.php" class="seller-mobile-link">Kembali ke Marketplace</a>
    <a href="../../auth/logout.php" class="seller-mobile-link seller-mobile-logout"
      onclick="return confirm('Yakin ingin keluar?')">Logout</a>
  </div>
</nav>

<style>
  .topnav-badge-seller {
    background: #10b981 !important;
    color: #fff !important;
  }

  .topnav-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 500;
    color: #6b7280;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    white-space: nowrap;
    margin-left: auto;
    margin-right: 12px;
    transition: background .15s, color .15s;
  }

  .topnav-back-btn:hover {
    background: #f3f4f6;
    color: #1a1a2e;
  }

  @media (max-width: 768px) {
    .topnav-back-btn {
      display: none;
    }
  }
</style>

<script>
  (function() {
    const profileToggle = document.getElementById('profileToggle');
    if (profileToggle) {
      profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('open');
      });
      document.addEventListener('click', () => profileToggle.classList.remove('open'));
    }
    const btn = document.getElementById('hamburgerBtn');
    const menu = document.getElementById('mobileMenu');
    if (btn && menu) btn.addEventListener('click', () => menu.classList.toggle('open'));
  }());
</script>