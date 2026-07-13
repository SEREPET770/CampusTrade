/**
 * assets/js/pengguna.js
 * Interaksi halaman admin/pengguna.php
 * - Modal zoom KTM
 * - Auto-dismiss notifikasi
 * - Topnav dropdown & hamburger
 * - Modal logout
 */

(function () {
  "use strict";

  // ── Modal KTM ────────────────────────────────────────────────────────────
  const modal = document.getElementById("ktmModal");
  const ktmImg = document.getElementById("ktmImg");
  const ktmNama = document.getElementById("ktmNama");

  window.bukaKTM = function (src, nama) {
    if (!modal) return;
    ktmImg.src = src;
    ktmNama.textContent = nama;
    modal.classList.add("open");
    document.body.style.overflow = "hidden";
  };

  window.tutupKTM = function () {
    if (!modal) return;
    modal.classList.remove("open");
    document.body.style.overflow = "";
  };

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") tutupKTM();
  });

  // ── Auto-dismiss notifikasi setelah 4 detik ───────────────────────────────
  const alert = document.querySelector(".pgn-alert");
  if (alert) {
    setTimeout(function () {
      alert.style.transition = "opacity .4s";
      alert.style.opacity = "0";
      setTimeout(function () {
        alert.remove();
      }, 400);
    }, 4000);
  }

  // ── Topnav: dropdown profil ───────────────────────────────────────────────
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

  // ── Topnav: hamburger mobile ──────────────────────────────────────────────
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobileMenu");
  if (hamburger && mobileMenu) {
    hamburger.addEventListener("click", function () {
      mobileMenu.classList.toggle("open");
    });
  }

  // ── Modal logout ──────────────────────────────────────────────────────────
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
