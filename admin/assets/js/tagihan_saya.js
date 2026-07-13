document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const billTableBody = document.querySelector("#billTable tbody");
  const originalEmptyRow = document.getElementById("emptyRow");

  // Ambil semua baris menggunakan class bawaan kelola_produk (product-row)
  let rows = Array.from(document.querySelectorAll(".product-row"));

  /* =========================================
       1. LOGIKA DROPDOWN PROFIL
       ========================================= */
  const profileBtn = document.getElementById("profileBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileDropdown.classList.toggle("show");
    });

    document.addEventListener("click", (e) => {
      if (
        !profileBtn.contains(e.target) &&
        !profileDropdown.contains(e.target)
      ) {
        profileDropdown.classList.remove("show");
      }
    });
  }

  /* =========================================
       2. FITUR SEARCH & FILTER REAL-TIME
       ========================================= */
  function filterTable() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const filterValue = statusFilter.value;
    let visibleCount = 0;

    rows.forEach((row) => {
      const searchData = row.getAttribute("data-search");
      const statusData = row.getAttribute("data-status");

      const matchesSearch = searchData.includes(searchTerm);
      const matchesFilter =
        filterValue === "semua" || statusData === filterValue;

      if (matchesSearch && matchesFilter) {
        row.style.display = "";
        visibleCount++;
      } else {
        row.style.display = "none";
      }
    });

    // Tangani baris "Data tidak ditemukan"
    const existingEmptyFilter = document.getElementById("emptyFilterRow");

    if (visibleCount === 0 && rows.length > 0) {
      if (!existingEmptyFilter) {
        const tr = document.createElement("tr");
        tr.id = "emptyFilterRow";
        tr.innerHTML = `
                    <td colspan="5" class="empty-state" style="text-align: center; padding: 30px;">
                        Tidak ada tagihan yang sesuai dengan pencarian Anda.
                    </td>
                `;
        billTableBody.appendChild(tr);
      }
      if (originalEmptyRow) originalEmptyRow.style.display = "none";
    } else {
      if (existingEmptyFilter) existingEmptyFilter.remove();

      if (visibleCount === 0 && rows.length === 0 && originalEmptyRow) {
        originalEmptyRow.style.display = "";
      } else if (originalEmptyRow) {
        originalEmptyRow.style.display = "none";
      }
    }
  }

  // Pasang Event Listener
  if (searchInput) searchInput.addEventListener("input", filterTable);
  if (statusFilter) statusFilter.addEventListener("change", filterTable);
});
