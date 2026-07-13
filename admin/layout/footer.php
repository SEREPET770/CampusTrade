<div class="popup-notif" id="popupContainer"></div>

<script>
  function showPopup(message, type = 'success') {
    const icons = {
      success: '✅',
      error: '❌',
      warning: '⚠️'
    };
    const container = document.getElementById('popupContainer');

    const item = document.createElement('div');
    item.className = `popup-item popup-${type}`;
    item.innerHTML = `
        <span class="popup-icon">${icons[type]}</span>
        <span>${message}</span>
        <button class="popup-close" onclick="closePopup(this)">✕</button>
    `;

    container.appendChild(item);

    setTimeout(() => closePopup(item.querySelector('.popup-close')), 3000);
  }

  function closePopup(btn) {
    const item = btn.closest('.popup-item');
    item.style.animation = 'slideOut 0.3s ease forwards';
    setTimeout(() => item.remove(), 300);
  }
</script>
<?php if (isset($_SESSION['notif'])): ?>
  <script>
    showPopup("<?= $_SESSION['notif']['pesan'] ?>", "<?= $_SESSION['notif']['tipe'] ?>");
  </script>
  <?php unset($_SESSION['notif']); ?>
<?php endif; ?>
</body>

</html>