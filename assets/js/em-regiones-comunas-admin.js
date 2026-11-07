/**
 * Perfil de usuario en wp-admin (direcciones de facturación y envío).
 *
 * Con el país en Chile, la Comuna (`*_state`) muestra solo las comunas de la
 * Región elegida (`*_city`). WooCommerce (`wc-users`) reemplaza el select de la
 * comuna cada vez que cambia el país; por eso se vuelve a filtrar después.
 */
jQuery(function ($) {
  var data = window.emRegionesComunasAdmin || { comunas: [], i18n: {} };
  var types = ["billing", "shipping"];

  function regionOf(comunaId) {
    for (var i = 0; i < data.comunas.length; i++) {
      if (data.comunas[i].id === String(comunaId)) {
        return data.comunas[i].region;
      }
    }
    return "";
  }

  function filter(type) {
    var $country = $("#" + type + "_country");
    var $region = $("#" + type + "_city");
    var $comuna = $("#" + type + "_state");

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

  $.each(types, function (i, type) {
    var $region = $("#" + type + "_city");
    if ($.fn.selectWoo) {
      $region.selectWoo();
    }
    $region.on("change", function () {
      filter(type);
    });
    filter(type);
  });

  // `wc-users` crea un select nuevo para la comuna al cambiar el país.
  $(document.body).on("country-change.woocommerce", function () {
    $.each(types, function (i, type) {
      filter(type);
    });
  });
});
