jQuery(function ($) {
  function search_comuna(region) {
    let comunas = [
      {
        id: 0,
        comuna: "Seleccionar",
      },
    ];
    for (let i in window.comunas) {
      let comuna = window.comunas[i];

      if (region === comuna.region) {
        comunas.push(comuna);
      }
    }

    return comunas;
  }

  let $billingCity = $("#billing_city");
  let $comunas = $("#billing_state");
  let $selectedComuna = $comunas.find("option:selected");

  $billingCity.change(function (e) {
    let region = this.value;
    let comunas = search_comuna(region);
    $comunas.html("");

    for (let i in comunas) {
      let comuna = comunas[i];
      $comunas.append(
        '<option data-region-id="' +
          comuna.region_id +
          '" data-region="' +
          comuna.region +
          '" value="' +
          comuna.id +
          '">' +
          comuna.comuna +
          "</option>"
      );
    }
  });

  window.onload = function () {
    $billingCity.trigger("change");

    if ($selectedComuna.length) {
      let selectedComuna = $selectedComuna.text();
      $("#billing_state option").prop("selected", false);
      $('#billing_state option[value="' + selectedComuna + '"]').prop(
        "selected",
        true
      );
      $comunas.trigger("change");
    }
  };
});
