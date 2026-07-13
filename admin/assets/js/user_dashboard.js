document.addEventListener("DOMContentLoaded", function () {
  const profilePillBtn = document.getElementById("profilePillBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profilePillBtn && profileDropdown) {
    // 1. Toggle Tampilan Menu Dropdown Profil saat tombol diklik
    profilePillBtn.addEventListener("click", function (event) {
      event.stopPropagation();
      console.log("Tombol Profil Berhasil Diklik!");
      profileDropdown.classList.toggle("show");
    });

    // 2. Menutup Dropdown secara otomatis ketika mengklik area di luar dropdown
    document.addEventListener("click", function (event) {
      if (
        !profileDropdown.contains(event.target) &&
        !profilePillBtn.contains(event.target)
      ) {
        profileDropdown.classList.remove("show");
      }
    });
  }

  // 3. Efek Sederhana Input Search Bar
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("keypress", function (event) {
      if (event.key === "Enter") {
        alert("Mencari produk: " + searchInput.value);
      }
    });
  }
});
