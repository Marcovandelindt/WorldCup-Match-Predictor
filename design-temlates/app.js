/* GOALCAST — kleine UI-interacties (Blade: resources/js/app.js) */
(function () {
  "use strict";

  /* "Genereer voorspelling" — korte laad-state vóór navigatie */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-generate]");
    if (!btn) return;
    e.preventDefault();
    if (btn.getAttribute("aria-busy") === "true") return;
    var href = btn.getAttribute("href") || btn.dataset.generate;
    var original = btn.innerHTML;
    btn.setAttribute("aria-busy", "true");
    btn.innerHTML = '<span class="ico">\u26A1</span> Genereren\u2026';
    setTimeout(function () {
      window.location.href = href;
    }, 480);
    // hard fallback restore (mocht navigatie geblokkeerd zijn)
    setTimeout(function () {
      btn.removeAttribute("aria-busy");
      btn.innerHTML = original;
    }, 4000);
  });

  /* Klikbare tabelrijen (data-href) */
  document.addEventListener("click", function (e) {
    if (e.target.closest("a,button")) return;
    var row = e.target.closest("[data-href]");
    if (row) window.location.href = row.getAttribute("data-href");
  });
})();
