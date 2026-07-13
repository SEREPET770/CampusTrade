/**
 * assets/js/produk.js
 * Interaksi halaman admin/produk.php
 * - Modal foto zoom
 * - Auto-dismiss notifikasi
 * - Topnav dropdown & hamburger (shared dengan dashboard.js)
 */

(function () {
  "use strict";

  // ── Modal foto ───────────────────────────────────────────────────────────
  const modal = document.getElementById("fotoModal");
  const modalImg = document.getElementById("fotoModalImg");
  const modalCap = document.getElementById("fotoModalCaption");

  window.bukaModal = function (src, caption) {
    if (!modal) return;
    modalImg.src = src;
    modalCap.textContent = caption || "";
    modal.classList.add("open");
    document.body.style.overflow = "hidden";
  };

  window.tutupModal = function () {
    if (!modal) return;
    modal.classList.remove("open");
    document.body.style.overflow = "";
  };

  // Tutup modal dengan ESC
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") tutupModal();
  });

  // ── Auto-dismiss notifikasi setelah 4 detik ───────────────────────────────
  const alert = document.querySelector(".prd-alert");
  if (alert) {
    setTimeout(function () {
      alert.style.transition = "opacity .4s";
      alert.style.opacity = "0";
      setTimeout(function () {
        alert.remove();
      }, 400);
    }, 4000);
  }

  // ── Topnav: dropdown profil ──────────────────────────────────────────────
  const profile = document.getElementById("profileTrigger");
  if (profile) {
    profile.addEventListener("click", function (e) {
      e.stopPropagation();
      this.classList.toggle("open");
    });
    document.addEventListener("click", function () {
      profile.classList.remove("open");
    });
  }

  // ── Topnav: hamburger mobile ─────────────────────────────────────────────
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobileMenu");
  if (hamburger && mobileMenu) {
    hamburger.addEventListener("click", function () {
      mobileMenu.classList.toggle("open");
    });
  }

  // ── Modal logout ─────────────────────────────────────────────────────────
  window.showLogoutModal = function () {
    document.getElementById("logoutModal").style.display = "flex";
  };
  window.hideLogoutModal = function () {
    document.getElementById("logoutModal").style.display = "none";
  };
  document
    .getElementById("logoutModal")
    ?.addEventListener("click", function (e) {
      if (e.target === this) hideLogoutModal();
    });
})();
