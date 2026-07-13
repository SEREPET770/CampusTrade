document.addEventListener("DOMContentLoaded", () => {
  const formFilter = document.getElementById("filter-form");

  // 1. Event listener untuk membuat seluruh card dapat diklik menuju detail
  const cards = document.querySelectorAll(".product-card");
  cards.forEach((card) => {
    card.addEventListener("click", function () {
      const idProduk = this.getAttribute("data-id");
      window.location.href = `detail_produk.php?id_produk=${idProduk}`;
    });
  });

  // 2. Validasi Form Filter Harga saat submit dipicu
  if (formFilter) {
    formFilter.addEventListener("submit", function (e) {
      const minPriceInput = document.querySelector('input[name="harga_min"]');
      const maxPriceInput = document.querySelector('input[name="harga_max"]');

      let min = parseInt(minPriceInput.value) || 0;
      let max = parseInt(maxPriceInput.value) || 0;

      if (min > 0 && max > 0 && min > max) {
        e.preventDefault(); // Hentikan proses submit form jika salah
        alert("Harga minimum tidak boleh lebih besar dari harga maksimum.");
        minPriceInput.focus();
      }
    });
  }

  // 3. FITUR AUTO-SUBMIT: Otomatis cari tanpa klik tombol ketika dropdown berubah
  // Menggunakan requestSubmit() agar fungsi pengecekan harga di atas tetap berjalan
  const selectKategori = document.getElementById("kategori-select");
  if (selectKategori && formFilter) {
    selectKategori.addEventListener("change", () => {
      formFilter.requestSubmit();
    });
  }

  const lokasiSelect = document.getElementById("lokasi-select");
  if (lokasiSelect && formFilter) {
    lokasiSelect.addEventListener("change", () => {
      formFilter.requestSubmit();
    });
  }

  const urutkanSelect = document.getElementById("urutkan-select");
  if (urutkanSelect && formFilter) {
    urutkanSelect.addEventListener("change", () => {
      formFilter.requestSubmit();
    });
  }

  // 4. Pengaturan Dropdown Menu Profil Navbar
  const profilePillBtn = document.getElementById("profilePillBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profilePillBtn && profileDropdown) {
    profilePillBtn.addEventListener("click", function (event) {
      event.stopPropagation();
      profileDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
      if (
        !profileDropdown.contains(event.target) &&
        !profilePillBtn.contains(event.target)
      ) {
        profileDropdown.classList.remove("show");
      }
    });
  }
});
