<?php

/**
 * Plugin Name: Regiones y Comunas de Chile
 * Plugin URI: https://github.com/Estudio-MOCA/em-regiones-comunas
 * Description: Comunas y regiones para WooCommerce
 * Version: 2.0.3
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

  public function scripts_css()
  {
    wp_enqueue_style('em-regiones-comunas', plugins_url('assets/css/em-regiones-comunas.css', __FILE__), [], "1.0");
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
    //if (!is_checkout()) return;
    wp_register_script(
      'em-fix-comuna-i18n',
      plugins_url('assets/js/fix-comuna-i18n.js', __FILE__),
      ['wp-i18n', 'wc-blocks-checkout'], // asegura cargar luego
      '1.0.0',
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
    // Script para el checkout clásico
    wp_enqueue_script('em-regiones-comunas', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas.js', ['jquery'], "1.0", true);

    // Script para el checkout de bloques
    wp_enqueue_script('em-regiones-comunas-blocks', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas-blocks.js', ['wc-blocks-checkout'], "1.0", true);
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
