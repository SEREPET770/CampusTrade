document.addEventListener("DOMContentLoaded", function () {
  // 1. Toggle Navbar Mobile (Hamburger)
  const navToggle = document.getElementById("navToggle");
  const navLinks = document.getElementById("navLinks");

  if (navToggle && navLinks) {
    navToggle.addEventListener("click", function (event) {
      event.stopPropagation();
      navLinks.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
      if (
        !navLinks.contains(event.target) &&
        !navToggle.contains(event.target)
      ) {
        navLinks.classList.remove("show");
      }
    });
  }

  // 2. Dropdown Profil Navbar (hanya aktif jika pengunjung sudah login)
  const profilePillBtn = document.getElementById("profilePillBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profilePillBtn && profileDropdown) {
    profilePillBtn.addEventListener("click", function (event) {
      event.stopPropagation();
      profileDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
      if (
        !profileDropdown.contains(event.target) &&
        !profilePillBtn.contains(event.target)
      ) {
        profileDropdown.classList.remove("show");
      }
    });
  }

  // 3. Smooth Scroll untuk anchor link (Tentang Kami, Cara Kerja)
  const anchorLinks = document.querySelectorAll('a[href^="#"]');
  anchorLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      const targetId = this.getAttribute("href");
      const targetEl = document.querySelector(targetId);
      if (targetEl) {
        e.preventDefault();
        targetEl.scrollIntoView({ behavior: "smooth" });
        navLinks && navLinks.classList.remove("show");
      }
    });
  });

  // 4. Animasi ringan fade-in saat elemen section terlihat di layar
  const animatedItems = document.querySelectorAll(
    ".kategori-card, .keunggulan-card, .cara-kerja-step, .product-card",
  );

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 },
    );

    animatedItems.forEach((item) => {
      item.style.opacity = "0";
      item.style.transform = "translateY(15px)";
      item.style.transition = "opacity 0.4s ease, transform 0.4s ease";
      observer.observe(item);
    });
  }
});
