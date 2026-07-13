document.addEventListener("DOMContentLoaded", function () {
  // Element Selector
  const inputSearch = document.getElementById("inputSearch");
  const filterStatus = document.getElementById("filterStatus");
  const paymentRows = document.querySelectorAll(".pembayaran-row");

  const previewModal = document.getElementById("previewModal");
  const closeModalBtn = document.getElementById("closeModalBtn");
  const thumbnails = document.querySelectorAll(".img-thumbnail-proof");

  // Modal Data Binder
  const mdProduk = document.getElementById("md-produk");
  const mdPenjual = document.getElementById("md-penjual");
  const mdNominal = document.getElementById("md-nominal");
  const mdTanggal = document.getElementById("md-tanggal");
  const mdStatusBadge = document.getElementById("md-status-badge");
  const mdLargeImg = document.getElementById("md-large-img");

  let currentStatusFilter = "all";

  // --- 1. Mekanisme Realtime Filter & Live Search ---
  function filterTable() {
    const searchQuery = inputSearch.value.toLowerCase().trim();

    paymentRows.forEach((row) => {
      const rowStatus = row.getAttribute("data-status");

      // Mengambil text bertanda searchable dalam row table
      const searchableElements = row.querySelectorAll(".text-searchable");
      let combinedText = "";
      searchableElements.forEach((el) => {
        combinedText += el.textContent.toLowerCase() + " ";
      });

      const textMatch =
        combinedText.includes(searchQuery) || searchQuery === "";
      // Pencocokan status filter (diselaraskan dengan nilai ENUM real db Anda)
      const statusMatch =
        currentStatusFilter === "all" || rowStatus === currentStatusFilter;

      if (textMatch && statusMatch) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  }

  if (inputSearch) {
    inputSearch.addEventListener("input", filterTable);
  }

  if (filterStatus) {
    filterStatus.addEventListener("change", function () {
      currentStatusFilter = this.value;
      filterTable();
    });
  }

  // --- 2. Modal Handler Preview Detail Bukti ---
  thumbnails.forEach((thumb) => {
    thumb.addEventListener("click", function () {
      const produk = this.getAttribute("data-produk");
      const penjual = this.getAttribute("data-penjual");
      const nominal = this.getAttribute("data-nominal");
      const tanggal = this.getAttribute("data-tanggal");
      const status = this.getAttribute("data-status");
      const imgSrc = this.getAttribute("data-imgsrc");

      mdProduk.textContent = produk;
      mdPenjual.textContent = penjual;
      mdNominal.textContent = nominal;
      mdTanggal.textContent = tanggal;
      mdLargeImg.src = imgSrc;

      mdStatusBadge.innerHTML = "";
      const badgeSpan = document.createElement("span");
      badgeSpan.className = "badge";

      // Penyesuaian nama visual label modal sesuai enum db
      if (status === "belum_bayar") {
        badgeSpan.classList.add("badge-warning");
        badgeSpan.textContent = "Menunggu Verifikasi";
      } else if (status === "berhasil") {
        badgeSpan.classList.add("badge-success");
        badgeSpan.textContent = "Lunas";
      } else {
        badgeSpan.classList.add("badge-danger");
        badgeSpan.textContent = "Ditolak";
      }
      mdStatusBadge.appendChild(badgeSpan);

      previewModal.classList.add("open");
    });
  });

  if (closeModalBtn) {
    closeModalBtn.addEventListener("click", function () {
      previewModal.classList.remove("open");
    });
  }

  window.addEventListener("click", function (e) {
    if (e.target === previewModal) {
      previewModal.classList.remove("open");
    }
  });

  // --- 3. Dialog Dialog Konfirmasi Aksi ---
  const actionForms = document.querySelectorAll(".form-action-verify");
  actionForms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const actionType = this.getAttribute("data-type");
      let confirmationMessage =
        "Apakah Anda yakin ingin menerima verifikasi pembayaran ini?\nProduk pengguna akan otomatis berstatus AKTIF.";

      if (actionType === "tolak") {
        confirmationMessage = "Apakah Anda yakin ingin MENOLAK pembayaran ini?";
      }

      if (!confirm(confirmationMessage)) {
        e.preventDefault();
      }
    });
  });
});
