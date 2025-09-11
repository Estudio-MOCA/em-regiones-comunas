// assets/fix-comuna-i18n.js
(function (wp) {
  if (!wp || !wp.i18n || !wp.i18n.setLocaleData) return;
  const { setLocaleData } = wp.i18n;

  // Ajusta el msgid exacto si difiere en tu versión.
  setLocaleData(
    {
      "Select a state": ["Seleccionar una comuna"],
      "Select a city": ["Seleccionar una región"],
    },
    "woocommerce"
  );
})(window.wp);

// assets/fix-comuna-dom.js
(function () {
  const fix = () => {
    document.querySelectorAll("option, [placeholder]").forEach((el) => {
      if (el.textContent === "Seleccionar un comuna")
        el.textContent = "Seleccionar una comuna";
      if (el.placeholder === "Seleccionar un comuna")
        el.placeholder = "Seleccionar una comuna";
    });
  };

  // 1) Ejecuta una vez cuando el DOM inicial está listo
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fix);
  } else {
    fix();
  }

  // 2) Observa cambios futuros (React re-render, bloques dinámicos)
  new MutationObserver(fix).observe(document.body, {
    childList: true,
    subtree: true,
  });
})();
