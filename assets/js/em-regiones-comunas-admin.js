/**
 * Direcciones de facturación y envío en wp-admin: perfil de usuario y editor
 * de pedidos (crear o editar).
 *
 * Con el país en Chile, la Comuna (`*_state`) muestra solo las comunas de la
 * Región elegida (`*_city`). WooCommerce (`wc-users` en el perfil,
 * `wc-admin-order-meta-boxes` en el pedido) reemplaza el select de la comuna
 * cada vez que cambia el país; por eso se vuelve a filtrar después.
 */
jQuery(function ($) {
  var data = window.emRegionesComunasAdmin || { comunas: [], addresses: [], i18n: {} };

  function regionOf(comunaId) {
    for (var i = 0; i < data.comunas.length; i++) {
      if (data.comunas[i].id === String(comunaId)) {
        return data.comunas[i].region;
      }
    }
    return "";
  }

  function filter(address) {
    var $country = $(address.country);
    var $region = $(address.region);
    var $comuna = $(address.comuna);

    if ($country.val() !== "CL" || !$comuna.is("select")) {
      return;
    }

    var current = $comuna.val() || "";

    // Datos antiguos con comuna y sin región: se completa la región.
    if (!$region.val() && current) {
      var inferred = regionOf(current);
      if (inferred) {
        $region.val(inferred).trigger("change.select2");
      }
    }

    var region = $region.val();
    var placeholder = region ? data.i18n.selectComuna : data.i18n.selectRegionFirst;

    $comuna.empty().append($("<option>", { value: "" }).text(placeholder));
    $.each(data.comunas, function (i, comuna) {
      if (comuna.region === region) {
        $comuna.append($("<option>", { value: comuna.id }).text(comuna.comuna));
      }
    });

    var stillValid = $comuna.find("option").filter(function () {
      return this.value === current;
    }).length;
    $comuna.val(stillValid ? current : "");
    $comuna.prop("disabled", !region).trigger("change.select2");
  }

  function filterAll() {
    $.each(data.addresses, function (i, address) {
      filter(address);
    });
  }

  $.each(data.addresses, function (i, address) {
    var $region = $(address.region);
    if ($.fn.selectWoo) {
      $region.selectWoo();
    }
    $region.on("change", function () {
      filter(address);
    });
  });
  filterAll();

  // WooCommerce crea un select nuevo para la comuna al cambiar el país.
  $(document.body).on("country-change.woocommerce", filterAll);
});
