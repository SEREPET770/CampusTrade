document.addEventListener("DOMContentLoaded", function () {
  // --- 1. Sistem Tab Switching Antar Panel ---
  const tabButtons = document.querySelectorAll(".tab-nav-btn");
  const tabPanels = document.querySelectorAll(".tab-content-panel");

  tabButtons.forEach((button) => {
    button.addEventListener("click", function () {
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      tabPanels.forEach((panel) => panel.classList.remove("active"));

      this.classList.add("active");
      const targetPanelId = this.getAttribute("data-target");
      document.getElementById(targetPanelId).classList.add("active");
    });
  });

  // --- 2. Real-time Search Sistem Multi-Tabel ---
  const searchInputs = document.querySelectorAll(".inputSearchRealtime");

  searchInputs.forEach((input) => {
    input.addEventListener("input", function () {
      const targetTableId = this.getAttribute("data-table");
      const query = this.value.toLowerCase().trim();
      const table = document.getElementById(targetTableId);
      const rows = table.querySelectorAll(
        "tbody tr.searchable-row, tbody tr.pembayaran-row",
      );

      rows.forEach((row) => {
        let match = false;
        const searchables = row.querySelectorAll(".text-searchable");
        let combinedText = "";

        searchables.forEach(
          (el) => (combinedText += el.textContent.toLowerCase() + " "),
        );

        if (combinedText.includes(query) || query === "") {
          match = true;
        }

        // Khusus tabel pembayaran, sinkronisasi dengan filter status yang aktif
        if (targetTableId === "tablePembayaran") {
          const activeStatusBtn = document.querySelector(".filter-btn.active");
          const currentStatusFilter = activeStatusBtn
            ? activeStatusBtn.getAttribute("data-status")
            : "all";
          const rowStatus = row.getAttribute("data-status");
          const statusMatch =
            currentStatusFilter === "all" || rowStatus === currentStatusFilter;

          row.style.display = match && statusMatch ? "" : "none";
        } else {
          row.style.display = match ? "" : "none";
        }
      });
    });
  });

  // --- 3. Filter Status Khusus Tab Pembayaran ---
  const filterButtons = document.querySelectorAll(".filter-btn");
  const paymentRows = document.querySelectorAll(".pembayaran-row");

  filterButtons.forEach((button) => {
    button.addEventListener("click", function () {
      filterButtons.forEach((btn) => btn.classList.remove("active"));
      this.classList.add("active");

      const selectedStatus = this.getAttribute("data-status");
      const searchInput = document.querySelector(
        '.inputSearchRealtime[data-table="tablePembayaran"]',
      );
      const query = searchInput ? searchInput.value.toLowerCase().trim() : "";

      paymentRows.forEach((row) => {
        const rowStatus = row.getAttribute("data-status");

        let textMatch = false;
        const searchables = row.querySelectorAll(".text-searchable");
        let combinedText = "";
        searchables.forEach(
          (el) => (combinedText += el.textContent.toLowerCase() + " "),
        );
        if (combinedText.includes(query) || query === "") textMatch = true;

        const statusMatch =
          selectedStatus === "all" || rowStatus === selectedStatus;

        row.style.display = textMatch && statusMatch ? "" : "none";
      });
    });
  });

  // --- 4. Modal View Detail & Bukti Pembayaran ---
  const previewModal = document.getElementById("previewModal");
  const closeModalBtn = document.getElementById("closeModalBtn");
  const thumbnails = document.querySelectorAll(".img-thumbnail-proof");

  thumbnails.forEach((thumb) => {
    thumb.addEventListener("click", function () {
      document.getElementById("md-produk").textContent =
        this.getAttribute("data-produk");
      document.getElementById("md-penjual").textContent =
        this.getAttribute("data-penjual");
      document.getElementById("md-nominal").textContent =
        this.getAttribute("data-nominal");
      document.getElementById("md-tanggal").textContent =
        this.getAttribute("data-tanggal");
      document.getElementById("md-large-img").src =
        this.getAttribute("data-imgsrc");

      const status = this.getAttribute("data-status");
      const badgeContainer = document.getElementById("md-status-badge");
      badgeContainer.innerHTML = "";

      const badge = document.createElement("span");
      badge.className = `badge badge-${status === "menunggu" ? "warning" : status === "berhasil" ? "success" : "danger"}`;
      badge.textContent =
        status === "menunggu"
          ? "Menunggu Verifikasi"
          : status === "berhasil"
            ? "Lunas"
            : "Ditolak";
      badgeContainer.appendChild(badge);

      previewModal.classList.add("open");
    });
  });

  if (closeModalBtn) {
    closeModalBtn.addEventListener("click", () =>
      previewModal.classList.remove("open"),
    );
  }

  // --- 5. Dialog Konfirmasi Aksi Form ---
  const confirmForms = document.querySelectorAll(".form-confirm-action");
  confirmForms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const msg = this.getAttribute("data-message");
      if (!confirm(msg)) e.preventDefault();
    });
  });
});

function chatPenjual(button) {
  let phone = button.dataset.phone;

  if (!phone || phone.trim() === "") {
    alert("Nomor WhatsApp penjual tidak tersedia.");
    return;
  }

  // Ubah format 08xxx menjadi 628xxx
  phone = phone.replace(/\D/g, ""); // hapus selain angka

  if (phone.startsWith("0")) {
    phone = "62" + phone.substring(1);
  }

  const pesan = encodeURIComponent(
    "Halo, saya tertarik dengan produk yang Anda jual.",
  );

  window.open(`https://wa.me/${phone}?text=${pesan}`, "_blank");
}
