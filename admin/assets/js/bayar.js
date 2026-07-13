document.addEventListener("DOMContentLoaded", () => {
  const fileInput = document.getElementById("bukti_pembayaran");
  const formPembayaran = document.getElementById("formPembayaran");
  const previewContainer = document.getElementById("preview-container");
  const previewImage = document.getElementById("preview-image");
  const previewPdf = document.getElementById("preview-pdf");
  const pdfName = document.getElementById("pdf-name");

  const MAX_SIZE_MB = 2;
  const ALLOWED_EXT = ["jpg", "jpeg", "png", "pdf"];

  // Menangani perubahan input file
  fileInput.addEventListener("change", function () {
    const file = this.files[0];
    previewContainer.classList.add("hidden");
    previewImage.classList.add("hidden");
    previewPdf.classList.add("hidden");

    if (file) {
      // 1. Validasi Ekstensi
      const fileExt = file.name.split(".").pop().toLowerCase();
      if (!ALLOWED_EXT.includes(fileExt)) {
        alert(
          `Format file tidak didukung! Harap unggah file dengan format: ${ALLOWED_EXT.join(", ")}`,
        );
        this.value = ""; // Reset input
        return;
      }

      // 2. Validasi Ukuran File (Maksimal 2 MB)
      const fileSizeMB = file.size / (1024 * 1024);
      if (fileSizeMB > MAX_SIZE_MB) {
        alert(
          `Ukuran file terlalu besar! Maksimal ukuran file adalah ${MAX_SIZE_MB} MB.`,
        );
        this.value = ""; // Reset input
        return;
      }

      // 3. Menampilkan Preview
      previewContainer.classList.remove("hidden");

      if (["jpg", "jpeg", "png"].includes(fileExt)) {
        // Tampilkan Image Preview
        previewImage.src = URL.createObjectURL(file);
        previewImage.classList.remove("hidden");
      } else if (fileExt === "pdf") {
        // Tampilkan Icon PDF
        pdfName.textContent = file.name;
        previewPdf.classList.remove("hidden");
      }
    }
  });

  // Menangani konfirmasi sebelum submit form
  formPembayaran.addEventListener("submit", function (e) {
    if (!fileInput.value) {
      e.preventDefault();
      alert("Bukti pembayaran wajib diunggah.");
      return;
    }

    const konfirmasi = confirm(
      "Apakah Anda yakin ingin mengirim bukti pembayaran? Data yang sudah dikirim akan masuk ke proses verifikasi admin.",
    );

    if (!konfirmasi) {
      e.preventDefault(); // Batalkan pengiriman jika user menekan 'Cancel'
    }
  });
});
