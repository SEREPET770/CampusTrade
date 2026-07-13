document.addEventListener("DOMContentLoaded", function () {
  // ==========================================
  // 1. FITUR DROPDOWN PROFIL
  // ==========================================
  const profileBtn = document.getElementById("profileBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      profileDropdown.classList.toggle("show");
    });

    // Menutup dropdown jika user klik di luar kotak
    document.addEventListener("click", function (e) {
      if (!profileDropdown.contains(e.target) && e.target !== profileBtn) {
        profileDropdown.classList.remove("show");
      }
    });
  }

  // ==========================================
  // 2. FITUR SEARCH & FILTER REALTIME
  // ==========================================
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const productRows = document.querySelectorAll(".product-row");
  const emptyRow = document.getElementById("emptyRow");

  function filterTable() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const filterStatus = statusFilter.value.toLowerCase();
    let visibleCount = 0;

    productRows.forEach((row) => {
      const rowDataSearch = row.getAttribute("data-search");
      const rowStatus = row.getAttribute("data-status");

      // Cek kondisi pencarian text dan status dropdown
      const matchSearch = rowDataSearch.includes(searchTerm);
      const matchStatus =
        filterStatus === "semua" || rowStatus === filterStatus;

      if (matchSearch && matchStatus) {
        row.style.display = ""; // Tampilkan baris
        visibleCount++;
      } else {
        row.style.display = "none"; // Sembunyikan baris
      }
    });

    // Handling saat tidak ada data yang cocok dengan filter/search
    if (visibleCount === 0 && productRows.length > 0) {
      // Jika baris empty custom belum ada, buat baru
      let noResultRow = document.getElementById("noResultRow");
      if (!noResultRow) {
        const tbody = document.querySelector(".product-table tbody");
        noResultRow = document.createElement("tr");
        noResultRow.id = "noResultRow";
        noResultRow.innerHTML = `<td colspan="10" class="empty-state">Pencarian tidak ditemukan.</td>`;
        tbody.appendChild(noResultRow);
      } else {
        noResultRow.style.display = "";
      }
    } else {
      // Hapus atau sembunyikan baris "tidak ditemukan" jika ada data
      const noResultRow = document.getElementById("noResultRow");
      if (noResultRow) noResultRow.style.display = "none";
    }
  }

  // Listeners untuk menjalankan fungsi saat input berubah
  if (searchInput) searchInput.addEventListener("keyup", filterTable);
  if (statusFilter) statusFilter.addEventListener("change", filterTable);
});

const tableBody = document.querySelector(".product-table tbody");

if (tableBody) {
  tableBody.addEventListener("click", function (e) {
    const row = e.target.closest("tr");
    if (!row) return;

    // Jika Tombol Edit diklik
    if (e.target.classList.contains("btn-inline-edit")) {
      row.classList.add("is-editing");
    }

    // Jika Tombol Batal diklik
    else if (e.target.classList.contains("btn-inline-cancel")) {
      row.classList.remove("is-editing");
      // (Opsional) Mengembalikan nilai input ke semula bisa ditambahkan disini
    }

    // Jika Tombol Simpan diklik
    else if (e.target.classList.contains("btn-inline-save")) {
      const idProduk = row.getAttribute("data-id");
      const inputs = row.querySelectorAll("[data-field]");

      // Siapkan data untuk dikirim ke PHP
      let formData = new FormData();
      formData.append("id_produk", idProduk);

      inputs.forEach((input) => {
        formData.append(input.getAttribute("data-field"), input.value);
      });

      // Ubah teks tombol menjadi loading
      const originalBtnText = e.target.innerHTML;
      e.target.innerHTML = "⏳ Menyimpan...";
      e.target.disabled = true;

      // Kirim data menggunakan Fetch AJAX ke file proses PHP
      fetch("ajax_update_produk.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            // Jika berhasil di database, perbarui teks pada tampilan (view-text)
            row.querySelector(
              '[data-label="Nama Produk"] .view-text',
            ).innerHTML = `<strong>${formData.get("nama_produk")}</strong>`;
            row.querySelector(
              '[data-label="Harga Jual"] .view-text',
            ).innerText =
              "Rp " + parseInt(formData.get("harga")).toLocaleString("id-ID");

            const brandVal = formData.get("brand") || "-";
            const penulisVal = formData.get("penulis") || "-";
            row.querySelector(
              '[data-label="Brand/Penulis"] .view-text',
            ).innerHTML = `${brandVal} <br> <small>${penulisVal}</small>`;

            row.querySelector('[data-label="Kelebihan"] .view-text').innerText =
              formData.get("kelebihan") || "-";
            row.querySelector(
              '[data-label="Kekurangan"] .view-text',
            ).innerText = formData.get("kekurangan") || "-";
            row.querySelector('[data-label="Deskripsi"] .view-text').innerText =
              formData.get("deskripsi").substring(0, 40) + "...";

            // Tutup mode edit
            row.classList.remove("is-editing");
          } else {
            alert("Gagal menyimpan data: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Terjadi kesalahan pada server.");
        })
        .finally(() => {
          // Kembalikan tombol seperti semula
          e.target.innerHTML = originalBtnText;
          e.target.disabled = false;
        });
    }
  });
}
