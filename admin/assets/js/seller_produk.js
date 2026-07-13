(function () {
  "use strict";

  const toast = document.getElementById("sellerToast");

  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.style.background = isError ? "#fee2e2" : "#1a1a2e";
    toast.style.color = isError ? "#991b1b" : "#fff";
    toast.style.display = "block";
    setTimeout(() => {
      toast.style.display = "none";
    }, 3000);
  }

  function toggleEditMode(row, isEditing) {
    row.querySelectorAll(".view-text").forEach((el) => {
      el.style.display = isEditing ? "none" : "inline";
    });
    row.querySelectorAll(".edit-input").forEach((el) => {
      el.style.display = isEditing ? "block" : "none";
    });

    const btnEdit = row.querySelector(".btn-inline-edit");
    const btnSave = row.querySelector(".btn-inline-save");
    const btnCancel = row.querySelector(".btn-inline-cancel");

    if (btnEdit) btnEdit.style.display = isEditing ? "none" : "inline-block";
    if (btnSave) btnSave.style.display = isEditing ? "inline-block" : "none";
    if (btnCancel)
      btnCancel.style.display = isEditing ? "inline-block" : "none";
  }

  // Simpan nilai asli untuk fitur Batal
  function snapshotOriginal(row) {
    const data = {};
    row.querySelectorAll(".edit-input").forEach((input) => {
      data[input.dataset.field] = input.value;
    });
    row.dataset.original = JSON.stringify(data);
  }

  function restoreOriginal(row) {
    const data = JSON.parse(row.dataset.original || "{}");
    row.querySelectorAll(".edit-input").forEach((input) => {
      if (data[input.dataset.field] !== undefined) {
        input.value = data[input.dataset.field];
      }
    });
  }

  async function saveRow(row) {
    const idProduk = row.dataset.id;
    const formData = new FormData();
    formData.append("id_produk", idProduk);

    row.querySelectorAll(".edit-input").forEach((input) => {
      formData.append(input.dataset.field, input.value);
    });

    // Field tambahan yang tidak punya kolom inline tapi wajib dikirim
    // karena ajax_update_produk.php mengharuskan nama_produk & harga terisi
    if (!formData.has("nama_produk")) {
      const nama = row.querySelector('[data-field="nama_produk"]');
      if (nama) formData.set("nama_produk", nama.value);
    }

    const btnSave = row.querySelector(".btn-inline-save");
    if (btnSave) {
      btnSave.disabled = true;
      btnSave.textContent = "⏳ Menyimpan...";
    }

    try {
      const res = await fetch("ajax_update_produk.php", {
        method: "POST",
        body: formData,
      });
      const data = await res.json();

      if (data.status === "success") {
        // Update tampilan view-text dengan nilai baru
        row.querySelectorAll(".edit-input").forEach((input) => {
          const field = input.dataset.field;
          const viewEl =
            row.querySelector(`.view-text[data-view="${field}"]`) ||
            (field === "nama_produk"
              ? row.cells[1]?.querySelector(".view-text")
              : null) ||
            (field === "harga"
              ? row.cells[3]?.querySelector(".view-text")
              : null);

          if (field === "nama_produk" && viewEl) {
            viewEl.innerHTML =
              "<strong>" + escapeHtml(input.value) + "</strong>";
          } else if (field === "harga" && viewEl) {
            viewEl.textContent =
              "Rp " + Number(input.value).toLocaleString("id-ID");
          }
        });

        // Update brand/penulis gabungan jika ada
        const brandInput = row.querySelector(".edit-brand");
        const penulisInput = row.querySelector(".edit-penulis");
        const brandView = row.querySelector("td:nth-child(5) .view-text");
        if (brandView) {
          const tampil =
            brandInput && brandInput.value
              ? brandInput.value
              : penulisInput
                ? penulisInput.value
                : "-";
          brandView.textContent = tampil || "-";
        }

        snapshotOriginal(row);
        toggleEditMode(row, false);
        showToast("Produk berhasil diperbarui.", false);
      } else {
        showToast(data.message || "Gagal menyimpan perubahan.", true);
      }
    } catch (err) {
      console.error("[seller_produk.js] save error:", err);
      showToast("Terjadi kesalahan jaringan. Coba lagi.", true);
    } finally {
      if (btnSave) {
        btnSave.disabled = false;
        btnSave.textContent = "💾 Simpan";
      }
    }
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  // ── Event delegation — pasang sekali di tbody ────────────────────────────
  const table = document.getElementById("produkTable");
  if (!table) return;

  table.querySelectorAll("tbody tr").forEach((row) => {
    snapshotOriginal(row);
  });

  table.addEventListener("click", function (e) {
    const row = e.target.closest("tr");
    if (!row) return;

    if (e.target.classList.contains("btn-inline-edit")) {
      toggleEditMode(row, true);
    }

    if (e.target.classList.contains("btn-inline-cancel")) {
      restoreOriginal(row);
      toggleEditMode(row, false);
    }

    if (e.target.classList.contains("btn-inline-save")) {
      saveRow(row);
    }
  });
})();
