document.addEventListener("DOMContentLoaded", () => {
  // Memberikan efek fade in saat halaman dimuat
  const mainImage = document.getElementById("mainImage");
  if (mainImage) {
    mainImage.style.opacity = 0;
    setTimeout(() => {
      mainImage.style.opacity = 1;
    }, 100);
  }
});

/**
 * Fungsi untuk mengganti gambar utama ketika thumbnail diklik
 * @param {HTMLElement} element - Elemen gambar thumbnail yang diklik
 */
function changeImage(element) {
  const mainImage = document.getElementById("mainImage");
  const thumbnails = document.querySelectorAll(".thumbnail");

  // Efek transisi (fade out)
  mainImage.style.opacity = 0.5;

  setTimeout(() => {
    // Ganti source gambar utama dengan source dari thumbnail yang diklik
    mainImage.src = element.src;

    // Kembalikan opacity (fade in)
    mainImage.style.opacity = 1;
  }, 150);

  // Hapus class 'active' dari semua thumbnail
  thumbnails.forEach((thumb) => {
    thumb.classList.remove("active");
  });

  // Tambahkan class 'active' ke thumbnail yang sedang di-klik
  element.classList.add("active");
}
