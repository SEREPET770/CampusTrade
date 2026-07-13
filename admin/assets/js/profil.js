function bukaKTM(src) {
  const modal = document.getElementById("ktmModal");
  const img = document.getElementById("ktmModalImg");
  if (!modal || !img) return;

  img.src = src;
  modal.classList.add("show");
}

function tutupKTM() {
  const modal = document.getElementById("ktmModal");
  const img = document.getElementById("ktmModalImg");
  if (!modal) return;

  modal.classList.remove("show");
  if (img) img.src = "";
}

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") tutupKTM();
});
