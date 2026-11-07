<?php

/**
 * Plugin Name: Regiones y Comunas de Chile
 * Plugin URI: https://github.com/Estudio-MOCA/em-regiones-comunas
 * Description: Comunas y regiones para WooCommerce
 * Version: 2.2.0
 * Author: Estudio MOCA
 * Author URI: https://estudiomoca.cl/
 * Text Domain: em-regiones-comunas
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Requires PHP: 8.1
 * Requires WP: 6.3
 * WC requires at least: 9.0
 * WC tested up to: 8.2
 * @package Em_Regiones_Comunas
 */

/**
 * Class Em_Regiones_Comunas clase principal del plugin
 * @property Em_Regiones_Comunas_Install $installer
 * @property array $comunas
 */

add_action('before_woocommerce_init', function () {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});
class Em_Regiones_Comunas
{
  // Región que se lista primero y comunas que abren su lista; el resto va en orden alfabético
  private const REGION_PRIORITARIA = 'Metropolitana de Santiago';
  private const COMUNAS_PRIORITARIAS = ['Santiago', 'Las Condes', 'Providencia'];

  private $comunas;
  private $installer;

  public function __construct()
  {
    include "inc/em-install.php";

    $this->installer = new Em_Regiones_Comunas_Install;
    add_filter('woocommerce_get_country_locale', [$this, 'filter_woocommerce_get_country_locale'], 30);
    add_filter('woocommerce_states', [$this, 'custom_woocommerce_states']);
    add_filter('woocommerce_checkout_fields', [$this, 'woocommerce_checkout_fields']);
    add_filter('woocommerce_default_address_fields', [$this, 'custom_override_default_address_fields']);
    add_action('wp_enqueue_scripts', [$this, 'scripts']);
    add_action('wp_enqueue_scripts', [$this, 'scripts_i18n']);

    add_action('wp_enqueue_scripts', [$this, 'scripts_css']);
    add_action('wp_head', [$this, 'wp_head']);
    add_filter('woocommerce_customer_meta_fields', [$this, 'customer_meta_fields'], 20);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
    add_action('init', [$this, 'action_init'], 30);
    register_activation_hook(__FILE__, [$this, 'on_activation']);
  }

  public function on_activation()
  {
    // Instalar tablas
    $this->installer->install();

    // Limpiar caché al activar
    $this->clear_cache();
  }

  public function clear_cache()
  {
    // Limpiar caché de regiones y comunas
    wp_cache_delete('em_regiones_all', 'em_regiones_comunas');
    wp_cache_delete('em_comunas_all', 'em_regiones_comunas');
  }

  public function action_init()
  {
    add_filter('gettext', [$this, 'gettext'], 10, 3);
  }

  // Versión del asset según su fecha de modificación, para que el navegador no use una copia vieja
  private function asset_version($path)
  {
    $file = plugin_dir_path(__FILE__) . $path;
    return file_exists($file) ? (string) filemtime($file) : '2.2.0';
  }

  // Páginas con formularios de dirección. En las demás (home, tienda, producto) no se carga nada:
  // los scripts del checkout de bloques arrastran wp-api-fetch y wc-blocks-middleware, que imprimen
  // nonces en el HTML y rompen la caché de página completa.
  private function is_address_page()
  {
    return is_cart() || is_checkout() || is_account_page();
  }

  public function scripts_css()
  {
    if (!$this->is_address_page()) {
      return;
    }
    wp_enqueue_style('em-regiones-comunas', plugins_url('assets/css/em-regiones-comunas.css', __FILE__), [], $this->asset_version('assets/css/em-regiones-comunas.css'));
  }

  public function gettext($translation, $text, $domain)
  {
    if ($translation === 'Select a state') {
      return 'Seleccionar una comuna';
    }
    if ($domain === 'woocommerce') {
      switch ($text) {
        case 'Country/Region':
        case 'Country / Region':
          return 'País';
        case 'Select a country/region':
          return 'Seleccionar un país';
      }
    }
    return $translation;
  }

  public function scripts_i18n()
  {
    // Solo el carro y el checkout de bloques tienen el campo de comuna de WooCommerce Blocks.
    if (!is_cart() && !is_checkout()) {
      return;
    }
    wp_register_script(
      'em-fix-comuna-i18n',
      plugins_url('assets/js/fix-comuna-i18n.js', __FILE__),
      ['wp-i18n', 'wc-blocks-checkout'], // asegura cargar luego
      $this->asset_version('assets/js/fix-comuna-i18n.js'),
      true
    );
    wp_enqueue_script('em-fix-comuna-i18n');
  }


  public function custom_override_default_address_fields($fields)
  {
    $options = [];
    $data = $this->get_regiones();

    foreach ($data as $d) {
      $options[$d->region] = $d->region;
    }

    $city_args = wp_parse_args(array(
      'type' => 'select',
      'options' => $options,
    ), $fields['city']);

    $fields['city'] = $city_args;

    return $fields;
  }
  public function wp_head()
  {
    if (is_cart() || is_checkout() || is_account_page()) {
      $comunas = $this->get_comunas();
      array_unshift($comunas, [
        "id" => 0,
        "comuna" => "Seleccionar",
        "disabled" => true
      ]);
      $regiones_json = json_encode($this->get_regiones());
      $comunas_json = json_encode($comunas);
      echo '<script>
            window.comunas = ' . wp_json_encode(wp_unslash(json_decode($comunas_json))) . '; 
            window.regiones = ' . wp_json_encode(wp_unslash(json_decode($regiones_json))) . '; 
            </script>';
    }
  }

  public function scripts()
  {
    if (!$this->is_address_page()) {
      return;
    }

    // Script para los formularios clásicos (direcciones de Mi cuenta)
    wp_enqueue_script('em-regiones-comunas', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas.js', ['jquery'], $this->asset_version('assets/js/em-regiones-comunas.js'), true);

    // Script para el carro y el checkout de bloques
    if (!is_cart() && !is_checkout()) {
      return;
    }
    wp_enqueue_script('em-regiones-comunas-blocks', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas-blocks.js', ['wc-blocks-checkout'], $this->asset_version('assets/js/em-regiones-comunas-blocks.js'), true);
  }

  /**
   * Perfil de usuario en wp-admin: la Región (`*_city`) es un selector y la
   * Comuna (`*_state`) va justo después, junto al País. `admin_scripts` deja en
   * la Comuna solo las comunas de la región elegida.
   */
  public function customer_meta_fields($fields)
  {
    $regiones = ['' => __('Seleccionar región', 'em-regiones-comunas')];
    foreach ($this->get_regiones() ?: [] as $region) {
      $regiones[$region->region] = $region->region;
    }

    foreach (['billing', 'shipping'] as $type) {
      if (!isset($fields[$type]['fields']["{$type}_city"], $fields[$type]['fields']["{$type}_state"])) {
        continue;
      }
      $address = $fields[$type]['fields'];

      $address["{$type}_city"] = array_merge($address["{$type}_city"], [
        'label' => __('Región', 'em-regiones-comunas'),
        'type' => 'select',
        'class' => 'em-rc-region',
        'options' => $regiones,
      ]);
      $address["{$type}_state"]['label'] = __('Comuna', 'em-regiones-comunas');
      $address["{$type}_state"]['description'] = '';

      // País, Región y Comuna seguidos, en ese orden, donde estaba la ciudad.
      $moved = ["{$type}_country", "{$type}_city", "{$type}_state"];
      $ordered = [];
      foreach ($address as $key => $field) {
        if ($key === "{$type}_city") {
          foreach ($moved as $movedKey) {
            if (isset($address[$movedKey])) {
              $ordered[$movedKey] = $address[$movedKey];
            }
          }
        } elseif (!in_array($key, $moved, true)) {
          $ordered[$key] = $field;
        }
      }
      $fields[$type]['fields'] = $ordered;
    }

    return $fields;
  }

  public function admin_scripts($hook)
  {
    if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) {
      return;
    }

    $comunas = array_map(
      fn ($comuna) => ['id' => (string) $comuna->id, 'comuna' => $comuna->comuna, 'region' => $comuna->region],
      $this->get_comunas() ?: []
    );

    wp_enqueue_script(
      'em-regiones-comunas-admin',
      plugins_url('assets/js/em-regiones-comunas-admin.js', __FILE__),
      ['jquery', 'wc-users'],
      $this->asset_version('assets/js/em-regiones-comunas-admin.js'),
      true
    );
    wp_add_inline_script('em-regiones-comunas-admin', 'window.emRegionesComunasAdmin = ' . wp_json_encode([
      'comunas' => $comunas,
      'i18n' => [
        'selectComuna' => __('Seleccionar comuna', 'em-regiones-comunas'),
        'selectRegionFirst' => __('Primero elige una región', 'em-regiones-comunas'),
      ],
    ]) . ';', 'before');
  }

  public function get_comunas($region_name = null)
  {
    global $wpdb;

    if (is_array($this->comunas)) {
      return $this->comunas;
    }

    // Generar clave de caché única
    $cache_key = $region_name ? 'em_comunas_' . md5($region_name) : 'em_comunas_all';
    $cache_group = 'em_regiones_comunas';

    // Intentar obtener del caché
    $results = wp_cache_get($cache_key, $cache_group);

    if (false === $results) {
      // No está en caché, consultar la base de datos
      // Escapar nombres de tablas para seguridad
      $comunas_table = esc_sql($this->installer->comunas_table);
      $provincias_table = esc_sql($this->installer->provincias_table);
      $regiones_table = esc_sql($this->installer->regiones_table);

      if ($region_name) {
        $results = $wpdb->get_results($wpdb->prepare(
          "SELECT co.id, co.comuna, p.provincia, p.region_id, r.region FROM %i AS co
          INNER JOIN %i AS p ON co.provincia_id=p.id
          INNER JOIN %i AS r ON p.region_id=r.id
          WHERE 1=1 AND r.region=%s ORDER BY co.comuna",
          $comunas_table,
          $provincias_table,
          $regiones_table,
          $region_name
        ));
      } else {
        $results = $wpdb->get_results($wpdb->prepare(
          "SELECT co.id, co.comuna, p.provincia, p.region_id, r.region FROM %i AS co
          INNER JOIN %i AS p ON co.provincia_id=p.id
          INNER JOIN %i AS r ON p.region_id=r.id
          WHERE 1=1 ORDER BY co.comuna",
          $comunas_table,
          $provincias_table,
          $regiones_table
        ));
      }

      // Guardar en caché por 1 hora (3600 segundos)
      wp_cache_set($cache_key, $results, $cache_group, 3600);
    }

    $this->comunas = $this->sort_comunas($results);
    return $this->comunas;
  }

  /**
   * Agrupa las comunas por región (en el orden de get_regiones) y deja primero
   * las prioritarias de la región prioritaria. El resto conserva el orden
   * alfabético de la consulta (usort es estable desde PHP 8).
   */
  private function sort_comunas($comunas)
  {
    if (!is_array($comunas)) {
      return $comunas;
    }

    $posiciones = [];
    foreach ($this->get_regiones() as $i => $region) {
      $posiciones[$region->region] = $i;
    }

    $rango = function ($comuna) {
      if ($comuna->region !== self::REGION_PRIORITARIA) {
        return 0;
      }
      $i = array_search($comuna->comuna, self::COMUNAS_PRIORITARIAS, true);
      return $i === false ? count(self::COMUNAS_PRIORITARIAS) : $i;
    };

    usort($comunas, function ($a, $b) use ($posiciones, $rango) {
      return [$posiciones[$a->region] ?? PHP_INT_MAX, $rango($a)]
        <=> [$posiciones[$b->region] ?? PHP_INT_MAX, $rango($b)];
    });

    return $comunas;
  }

  public function woocommerce_checkout_fields($fields)
  {
    $options = [];
    $data = $this->get_regiones();
    $options[0] = 'Seleccionar';
    foreach ($data as $d) {
      $options[$d->region] = $d->region;
    }

    $city_args = wp_parse_args(array(
      'type' => 'select',
      'options' => $options,
    ), $fields['shipping']['shipping_city']);

    $fields['shipping']['shipping_city'] = $city_args;
    $fields['billing']['billing_city'] = $city_args;
    return $fields;
  }

  public function filter_woocommerce_get_country_locale($array)
  {
    $array['CL']['state']['label'] = __('Comuna', 'em-regiones-comunas');
    $array['CL']['city']['label'] = __('Región', 'em-regiones-comunas');
    $array['CL']['state']['placeholder'] = __('Seleccionar comuna', 'em-regiones-comunas');
    $array['CL']['city']['placeholder'] = __('Seleccionar región', 'em-regiones-comunas');
    $array['CL']['state']['disabled'] = true;

    $array['CL']['state']['required'] = true;
    $array['CL']['city']['required'] = true;

    $array['CL']['state']['options'] = [];
    return $array;
  }

  public function custom_woocommerce_states($states)
  {
    $options = [];
    $data = $this->get_comunas();
    //$options[0] = 'Seleccionar';

    foreach ($data as $d) {
      $options[$d->id] = $d->comuna;
    }
    $states['CL'] = $options;
    return $states;
  }

  public function get_regiones()
  {
    global $wpdb;

    // Intentar obtener del caché
    $cache_key = 'em_regiones_all';
    $cache_group = 'em_regiones_comunas';
    $results = wp_cache_get($cache_key, $cache_group);

    if (false === $results) {
      // No está en caché, consultar la base de datos
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $results = $wpdb->get_results("SELECT * FROM {$this->installer->regiones_table} ORDER BY orden ASC");

      // Guardar en caché por 1 hora (3600 segundos)
      wp_cache_set($cache_key, $results, $cache_group, 3600);
    }

    if (is_array($results)) {
      // Región prioritaria primero; el resto sigue el orden de la tabla
      usort($results, function ($a, $b) {
        return ($b->region === self::REGION_PRIORITARIA) <=> ($a->region === self::REGION_PRIORITARIA);
      });
    }

    return $results;
  }
}

new Em_Regiones_Comunas();
