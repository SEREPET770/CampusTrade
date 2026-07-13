document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("checkoutForm");

  const ongkirInput = document.getElementById("ongkirInput");
  const totalInput = document.getElementById("totalInput");

  const ongkirText = document.getElementById("ongkirText");
  const totalText = document.getElementById("totalText");

  const radios = document.querySelectorAll("input[name='metode_pengiriman']");

  const subtotal = Number(
    document.querySelector("input[name='subtotal']").value,
  );

  function rupiah(angka) {
    return "Rp " + angka.toLocaleString("id-ID");
  }

  function hitungTotal() {
    const radio = document.querySelector(
      "input[name='metode_pengiriman']:checked",
    );

    if (!radio) return;

    const ongkir = Number(radio.dataset.ongkir);

    const total = subtotal + ongkir;

    ongkirInput.value = ongkir;
    totalInput.value = total;

    ongkirText.textContent = rupiah(ongkir);
    totalText.textContent = rupiah(total);
  }

  radios.forEach((radio) => {
    radio.addEventListener("change", hitungTotal);
  });

  hitungTotal();

  /* ================= MAP ================= */

  const latitude = document.getElementById("latitude");
  const longitude = document.getElementById("longitude");
  const search = document.getElementById("searchLocation");

  const surabayaBounds = [
    [-7.42, 112.58],
    [-7.14, 112.92],
  ];
  const map = L.map("map", {
    maxBounds: surabayaBounds,
    maxBoundsViscosity: 1.0,
  }).setView([-7.2575, 112.7521], 12);

  map.setMinZoom(11);
  map.setMaxZoom(18);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "© OpenStreetMap",
  }).addTo(map);

  const marker = L.marker([-7.3113, 112.7298], {
    draggable: true,
  }).addTo(map);

  latitude.value = -7.3113;
  longitude.value = 112.7298;

  function updateCoordinate(lat, lng) {
    latitude.value = lat.toFixed(8);
    longitude.value = lng.toFixed(8);

    marker.setLatLng([lat, lng]);

    map.panTo([lat, lng]);
  }

  marker.on("dragend", () => {
    const pos = marker.getLatLng();

    updateCoordinate(pos.lat, pos.lng);
  });

  map.on("click", (e) => {
    updateCoordinate(e.latlng.lat, e.latlng.lng);
  });

  /* ================= SEARCH LOCATION ================= */

  search.addEventListener("keypress", async (e) => {
    if (e.key !== "Enter") return;

    e.preventDefault();

    const keyword = search.value.trim();

    if (keyword === "") return;

    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(keyword)}`,
      );

      const result = await response.json();

      if (result.length === 0) {
        alert("Lokasi tidak ditemukan.");

        return;
      }

      const lat = parseFloat(result[0].lat);
      const lng = parseFloat(result[0].lon);

      updateCoordinate(lat, lng);

      map.setView([lat, lng], 16);
    } catch (error) {
      console.error(error);

      alert("Gagal mencari lokasi.");
    }
  });

  /* ================= LOKASI SAYA ================= */

  const btnLocation = document.getElementById("btnCurrentLocation");

  btnLocation.addEventListener("click", () => {
    if (!navigator.geolocation) {
      alert("Browser tidak mendukung Geolocation.");
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;

        updateCoordinate(lat, lng);

        map.setView([lat, lng], 16);
      },

      () => {
        alert("Gagal mendapatkan lokasi.");
      },
    );
  });

  /* ================= VALIDASI ================= */

  form.addEventListener("submit", (e) => {
    const nama = document
      .querySelector("input[name='nama_penerima']")
      .value.trim();
    const hp = document.querySelector("input[name='no_hp']").value.trim();
    const alamat = document
      .querySelector("textarea[name='alamat']")
      .value.trim();
    const pembayaran = document.querySelector(
      "input[name='metode_pembayaran']:checked",
    );

    if (nama === "") {
      alert("Nama penerima wajib diisi.");
      e.preventDefault();
      return;
    }

    if (hp === "") {
      alert("Nomor WhatsApp wajib diisi.");
      e.preventDefault();
      return;
    }

    if (alamat === "") {
      alert("Alamat pengiriman wajib diisi.");
      e.preventDefault();
      return;
    }

    if (latitude.value === "" || longitude.value === "") {
      alert("Silakan tentukan titik lokasi pada peta.");
      e.preventDefault();
      return;
    }

    if (!pembayaran) {
      alert("Pilih metode pembayaran.");
      e.preventDefault();
      return;
    }

    if (!confirm("Apakah Anda yakin ingin membuat pesanan ini?")) {
      e.preventDefault();
    }
  });
});
