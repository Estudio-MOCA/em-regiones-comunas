/**
 * Script para manejar regiones y comunas en el checkout de bloques de WooCommerce
 *
 * Este script se encarga de:
 * 1. Convertir el campo "city" (regiones) en un select en el checkout de bloques
 * 2. Mantener la funcionalidad actual de las comunas (state)
 */
(function () {
  // Esperar a que el DOM esté completamente cargado
  document.addEventListener("DOMContentLoaded", function () {
    initRegionesBlocks();
    initComunasBlocks();
  });

  // También escuchar cambios en el DOM para detectar cuando se carga el checkout de bloques
  const observer = new MutationObserver(function (mutations) {
    for (let mutation of mutations) {
      if (mutation.addedNodes.length) {
        initRegionesBlocks();
        initComunasBlocks();
      }
    }
  });

  // Iniciar observación del DOM
  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });

  /**
   * Inicializa la funcionalidad de regiones y comunas para el checkout de bloques
   */
  function initRegionesBlocks() {
    // Verificar si estamos en el checkout y si existe el objeto window.comunas
    if (!window.comunas || !isCheckoutPage()) {
      return;
    }

    // Buscar el campo de ciudad (regiones) en el checkout de bloques
    const cityInputs = document.querySelectorAll(
      [
        '.wc-block-components-address-form input[name="billing_city"], .wc-block-components-address-form input[name="shipping_city"]',
        '.wc-block-checkout__billing-fields input[name="billing_city"]',
        '.wc-block-checkout__shipping-fields input[name="shipping_city"]',
        ".wc-block-components-address-form__city input[type='text']",
      ].join(", ")
    );

    // Si no encontramos los campos, salimos
    if (!cityInputs.length) {
      return;
    }

    // Para cada campo de ciudad encontrado
    cityInputs.forEach(function (cityInput) {
      // Si ya fue procesado, salimos
      if (cityInput.dataset.processed === "true") {
        return;
      }

      // Marcar como procesado
      cityInput.dataset.processed = "true";

      // Crear el select de regiones
      const regionSelect = createRegionSelect(cityInput);

      regionSelect.addEventListener("change", function (e) {
        const elem = e.target;
        const selectedOption = elem.options[elem.selectedIndex];
        const selectedRegionId = selectedOption?.dataset.regionId || "";

        // Sincronizar con el input original oculto para que Woo Blocks/React valide correctamente
        const isBilling = elem.getAttribute("id")?.startsWith("billing");
        const hiddenCityInput = document.getElementById(
          isBilling ? "billing-city" : "shipping-city"
        );
        if (hiddenCityInput) {
          setNativeValue(hiddenCityInput, elem.value || "");
          hiddenCityInput.setAttribute("value", elem.value || "");
          dispatchReactInputEvents(hiddenCityInput);
        }

        // Actualizar y filtrar las comunas según la región seleccionada
        const comunaElem = document.getElementById(
          isBilling ? "billing-state" : "shipping-state"
        );
        if (!comunaElem) {
          return;
        }

        if (e.isTrusted) {
          comunaElem.value = "";
          dispatchReactInputEvents(comunaElem);
        }

        // Aplicar filtro de comunas según región
        filterComunasByRegion(comunaElem, selectedRegionId);
      });

      // Filtro inicial según selección actual (no resetea comuna)
      const initSelectedOption =
        regionSelect.options[regionSelect.selectedIndex];
      const initRegionId = initSelectedOption?.dataset.regionId || "";
      const initIsBilling = regionSelect
        .getAttribute("id")
        ?.startsWith("billing");
      const initComunaElem = document.getElementById(
        initIsBilling ? "billing-state" : "shipping-state"
      );
      if (initComunaElem) {
        filterComunasByRegion(initComunaElem, initRegionId);
      }
    });
  }

  function initComunasBlocks() {
    if (!window.regiones || !isCheckoutPage()) {
      return;
    }
    // Buscar los campos de comunas (state) en el checkout de bloques
    const stateInputs = document.querySelectorAll(
      [
        '.wc-block-components-address-form select[name="billing_state"], .wc-block-components-address-form select[name="shipping_state"]',
        '.wc-block-checkout__billing-fields select[name="billing_state"]',
        '.wc-block-checkout__shipping-fields select[name="shipping_state"]',
        ".wc-block-components-address-form__state select",
      ].join(", ")
    );

    // Si no encontramos los campos, salimos
    if (!stateInputs.length) {
      return;
    }

    // Para cada campo de comuna encontrado
    stateInputs.forEach(function (stateInput) {
      // Si ya fue procesado, salimos
      if (stateInput.dataset.processed === "true") {
        return;
      }

      // Marcar como procesado
      stateInput.dataset.processed = "true";

      // Orden de las comunas según window.comunas (WooCommerce las entrega por id)
      sortComunaOptions(stateInput);

      // Aseguramos data attributes (region) en las opciones antes de disparar filtros
      updateCityInputData(stateInput);

      // Aplicar filtro inmediatamente según la región ya seleccionada (si existe)
      try {
        const isBilling = stateInput.getAttribute("id").startsWith("billing");
        const regionSelect = document.querySelector(
          isBilling ? "#billing-city-select" : "#shipping-city-select"
        );
        if (regionSelect) {
          const opt = regionSelect.options[regionSelect.selectedIndex];
          const regionId = opt?.dataset?.regionId || "";
          filterComunasByRegion(stateInput, regionId);
        }
      } catch (err) {
        // no-op
      }

      // Buscar el select de regiones correspondiente
      //const fieldName = stateInput.name.replace("_state", "_city");

      // Si encontramos el select de regiones, añadimos el evento change
      if (stateInput) {
        const comunaId = stateInput.value;

        if (comunaId) {
          const isBilling = stateInput.getAttribute("id").startsWith("billing");
          //select region
          const regionSelect =
            document.querySelector(
              isBilling ? "#billing-city-select" : "#shipping-city-select"
            ) ||
            document.querySelector(
              isBilling ? "#billing-city" : "#shipping-city"
            );

          const regionId = getComuna(comunaId)?.region_id;
          if (regionSelect && regionId) {
            setRegionSelectByRegionId(regionSelect, regionId);
            // Sincroniza el input oculto de ciudad con el nombre de la región seleccionada
            const hiddenCityInput = document.getElementById(
              isBilling ? "billing-city" : "shipping-city"
            );
            if (hiddenCityInput) {
              setNativeValue(hiddenCityInput, regionSelect.value || "");
              dispatchReactInputEvents(hiddenCityInput);
            }
          }
        }

        stateInput.addEventListener("change", function (e) {
          //only if change with human
          if (e.isTrusted) {
            const val = stateInput.value;

            const isBilling = stateInput
              .getAttribute("id")
              .startsWith("billing");
            const regionSelect =
              document.querySelector(
                isBilling ? "#billing-city-select" : "#shipping-city-select"
              ) ||
              document.querySelector(
                isBilling ? "#billing-city" : "#shipping-city"
              );
            const cityVal = regionSelect?.value;

            if (cityVal) {
              return;
            }

            if (val && regionSelect) {
              const regionId = getComuna(val)?.region_id;
              if (regionId) {
                setRegionSelectByRegionId(regionSelect, regionId);
                // Sincroniza el input oculto de ciudad con el nombre de la región seleccionada
                const hiddenCityInput = document.getElementById(
                  isBilling ? "billing-city" : "shipping-city"
                );
                if (hiddenCityInput) {
                  setNativeValue(hiddenCityInput, regionSelect.value || "");
                  dispatchReactInputEvents(hiddenCityInput);
                }
              }
            }
          }
        });

        // data attributes ya actualizados
      }
    });
  }

  function getComuna(id) {
    const comunas = getComunas();
    return comunas.find((c) => Number(c.id) == Number(id));
  }

  // Reordena las opciones del select de comunas siguiendo el orden de window.comunas
  function sortComunaOptions(stateInput) {
    const order = new Map(getComunas().map((c, i) => [String(c.id), i]));
    const rank = (option) =>
      option.value === ""
        ? -1
        : order.has(option.value)
        ? order.get(option.value)
        : Infinity;
    const options = Array.from(stateInput.options);
    const sorted = options.slice().sort((a, b) => rank(a) - rank(b));
    if (sorted.every((option, i) => option === options[i])) {
      return;
    }
    const value = stateInput.value;
    sorted.forEach((option) => stateInput.appendChild(option));
    stateInput.value = value;
  }

  function updateCityInputData(stateInput) {
    //foreach option and add data-region attribute with the option text
    stateInput.querySelectorAll("option").forEach(function (option) {
      const comunaId = option.value;
      const comuna = getComuna(comunaId);
      // Si no hay comuna o no hay region_id válido, limpiamos atributos
      if (
        !comuna ||
        comuna.region_id === undefined ||
        comuna.region_id === null ||
        comuna.region_id === ""
      ) {
        option.removeAttribute("data-region-id");
        option.removeAttribute("data-region");
        return;
      }

      option.setAttribute("data-region-id", comuna.region_id);
      option.setAttribute("data-region", comuna.region);
    });
  }

  function getComunas() {
    return window.comunas;
  }

  // Dispara eventos compatibles con React/Woo Blocks para que capte cambios programáticos
  function dispatchReactInputEvents(el) {
    try {
      el.dispatchEvent(new Event("input", { bubbles: true }));
      el.dispatchEvent(new Event("change", { bubbles: true }));
    } catch (err) {
      // No-op
    }
  }

  // Asigna el valor usando el setter nativo para que React/Woo Blocks detecte el cambio del input controlado
  function setNativeValue(el, value) {
    try {
      const proto =
        el instanceof HTMLInputElement
          ? HTMLInputElement.prototype
          : HTMLElement.prototype;
      const valueSetter = Object.getOwnPropertyDescriptor(proto, "value")?.set;
      if (valueSetter) {
        valueSetter.call(el, value);
      } else {
        el.value = value;
      }
    } catch (e) {
      el.value = value;
    }
  }

  // Selecciona la opción del select de regiones a partir de un region_id de comuna
  function setRegionSelectByRegionId(regionSelect, regionId) {
    if (!regionSelect) return;
    const opts = Array.from(regionSelect.options || []);
    const match = opts.find(
      (o) => String(o.dataset.regionId) === String(regionId)
    );
    if (match) {
      regionSelect.value = match.value;
      dispatchReactInputEvents(regionSelect);
    }
  }

  // Muestra/Oculta opciones de comuna según la región seleccionada
  function filterComunasByRegion(comunaElem, selectedRegionId) {
    if (!comunaElem) return;
    const options = comunaElem.querySelectorAll("option");
    if (!selectedRegionId) {
      options.forEach((option) => {
        option.style.display = "block";
      });
      return;
    }
    options.forEach((option) => {
      const optionRegionId = option.dataset.regionId;
      // Mantener visible la opción por defecto (sin data-region-id)
      if (!optionRegionId) {
        option.style.display = "block";
        return;
      }
      if (String(optionRegionId) === String(selectedRegionId)) {
        option.style.display = "block";
      } else {
        option.style.display = "none";
      }
    });
  }
  /**
   * Crea un select de regiones a partir de un input de texto
   * @param {HTMLInputElement} cityInput - El input de texto a reemplazar
   */
  function createRegionSelect(cityInput) {
    // Obtener las regiones disponibles
    const regiones = getRegiones();

    /*
      Creamos un select custom sin reemplazar el input original. El input se oculta
      y el select se inserta a continuación, sincronizando su valor con el input
      oculto para que React/Woo Blocks lo valide correctamente.
    */
    // Crear el wrap
    const wrap = document.createElement("div");
    wrap.className = "wc-blocks-components-select";

    // Crear el container
    const container = document.createElement("div");
    container.className = "wc-blocks-components-select__container";
    wrap.appendChild(container);

    // ID del select a crear
    const selectId = `${cityInput.id}-select`;

    // Ocultar el label nativo de Woo Blocks si existe para evitar duplicidad
    const fieldContainer =
      cityInput.closest(".wc-block-components-address-form__city") ||
      cityInput.parentElement;
    const existingLabel = fieldContainer
      ? fieldContainer.querySelector("label")
      : null;
    if (existingLabel) {
      existingLabel.style.display = "none";
      existingLabel.setAttribute("aria-hidden", "true");
      existingLabel.setAttribute("data-erc-hidden", "true");
    }

    // Crear SIEMPRE el label del wrapper (mantener label de JS)
    const label = document.createElement("label");
    label.htmlFor = selectId;
    label.className = "wc-blocks-components-select__label";
    label.textContent = "Región";
    container.appendChild(label);

    // Crear el select
    const select = document.createElement("select");
    select.name = `${cityInput.name}_select`;
    select.id = selectId;
    select.className =
      cityInput.className +
      " region-select wc-blocks-components-select__select";
    select.required = cityInput.required;

    // Añadir la opción por defecto
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "Seleccionar región";
    select.appendChild(defaultOption);

    // Añadir las opciones de regiones
    regiones.forEach(function (region) {
      const option = document.createElement("option");
      option.value = region.region;
      option.dataset.regionId = region.id;
      option.textContent = region.region;
      select.appendChild(option);
    });

    // Si el input tiene un valor, seleccionarlo en el select
    if (cityInput.value) {
      select.value = cityInput.value;
    }

    // Añadir el select al container
    container.appendChild(select);

    // Añadir el svg
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("width", "24");
    svg.setAttribute("height", "24");
    svg.classList.add("wc-blocks-components-select__expand");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("focusable", "false");
    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute(
      "d",
      "M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"
    );
    svg.appendChild(path);
    container.appendChild(svg);

    // Insertar el select después del input y ocultar el input original (sin eliminarlo)
    cityInput.insertAdjacentElement("afterend", wrap);
    cityInput.style.display = "none";
    return select;
  }

  /**
   * Obtiene la lista de regiones disponibles
   * @returns {Array} - Lista de nombres de regiones
   */
  function getRegiones() {
    return window.regiones;
  }

  /**
   * Verifica si estamos en la página de checkout
   * @returns {boolean} - true si estamos en el checkout
   */
  function isCheckoutPage() {
    // Verificar si estamos en la página de checkout
    return (
      document.body.classList.contains("woocommerce-checkout") ||
      document.querySelector(".wc-block-checkout") !== null ||
      document.querySelector(".is-checkout") !== null
    );
  }
})();
