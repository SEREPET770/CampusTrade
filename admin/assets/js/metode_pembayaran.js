function bukaFormTambah() {
  const form = document.getElementById("formTambah");
  if (form) {
    form.style.display = "block";
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}

function tutupFormTambah() {
  const form = document.getElementById("formTambah");
  if (form) {
    form.style.display = "none";
  }
  // Reset input preview when form ditutup
  const preview = document.getElementById("previewTambah");
  if (preview) {
    preview.src = "";
    preview.style.display = "none";
  }
  const fileInput = document.querySelector('#formTambah input[name="qr_code"]');
  if (fileInput) {
    fileInput.value = "";
  }
  const jenisSelect = document.getElementById("jenisTambah");
  const providerSelect = document.getElementById("providerTambah");
  if (jenisSelect) jenisSelect.value = "";
  if (providerSelect) {
    providerSelect.innerHTML = '<option value="">— Pilih Jenis dulu —</option>';
  }
}

function updateProvider(mode) {
  const jenisSelect = document.getElementById(
    mode === "edit" ? "jenisEdit" : "jenisTambah",
  );
  const providerSelect = document.getElementById(
    mode === "edit" ? "providerEdit" : "providerTambah",
  );
  if (!jenisSelect || !providerSelect) return;

  const jenis = jenisSelect.value;
  const options = [];

  if (jenis === "Bank") {
    options.push({ value: "BCA", label: "BCA" });
    options.push({ value: "BNI", label: "BNI" });
    options.push({ value: "BRI", label: "BRI" });
    options.push({ value: "Mandiri", label: "Mandiri" });
    options.push({ value: "Dana", label: "Dana" });
  } else if (jenis === "E-Wallet") {
    options.push({ value: "GoPay", label: "GoPay" });
    options.push({ value: "OVO", label: "OVO" });
    options.push({ value: "DANA", label: "DANA" });
    options.push({ value: "ShopeePay", label: "ShopeePay" });
    options.push({ value: "LinkAja", label: "LinkAja" });
  } else if (jenis === "QRIS") {
    options.push({ value: "QRIS", label: "QRIS" });
  }

  providerSelect.innerHTML = "";
  if (options.length === 0) {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "— Pilih Jenis dulu —";
    providerSelect.appendChild(opt);
    return;
  }

  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.textContent = "-- Pilih Provider --";
  providerSelect.appendChild(placeholder);

  options.forEach(({ value, label }) => {
    const opt = document.createElement("option");
    opt.value = value;
    opt.textContent = label;
    providerSelect.appendChild(opt);
  });

  providerSelect.addEventListener("change", () => {
    updateNamaMetode(mode);
  });
}

function updateNamaMetode(mode) {
  const providerSelect = document.getElementById(
    mode === "edit" ? "providerEdit" : "providerTambah",
  );
  const namaMetode = document.getElementById(
    mode === "edit" ? "namaMetodeEdit" : "namaMetodeTambah",
  );
  if (!providerSelect || !namaMetode) return;

  const providerValue = providerSelect.value;
  if (!providerValue) return;

  namaMetode.value = providerValue;
}

function previewQR(input, previewId) {
  const file = input.files && input.files[0];
  const preview = document.getElementById(previewId);
  if (!file || !preview) return;

  const reader = new FileReader();
  reader.onload = function (event) {
    preview.src = event.target.result;
    preview.style.display = "block";
  };
  reader.readAsDataURL(file);
}

// Jika halaman edit memuat form edit, isi provider jika sudah ada nilai provider
window.addEventListener("DOMContentLoaded", function () {
  const jenisEdit = document.getElementById("jenisEdit");
  const providerEdit = document.getElementById("providerEdit");
  if (jenisEdit && providerEdit && providerEdit.options.length <= 1) {
    updateProvider("edit");
    const currentProvider = providerEdit.dataset.current;
    if (currentProvider) {
      providerEdit.value = currentProvider;
    }
  }
});
