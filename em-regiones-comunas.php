<?php

/**
 * Plugin Name: Regiones y Comunas de Chile
 * Plugin URI: https://estudiomoca.cl/
 * Description: Comunas y regiones para Woocommerce
 * Version: 25.09.04
 * Author: Estudio MOCA
 * Author URI: https://estudiomoca.cl/
 * Text Domain: em_regiones_comunas
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires WP: 6.3
 * WC requires at least: 8.0
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
    register_activation_hook(__FILE__, [$this->installer, 'install']);
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
            window.comunas = ' . $comunas_json . '; 
            window.regiones = ' . $regiones_json . '; 
            </script>';
    }
  }

  public function scripts()
  {
    // Script para el checkout clásico
    wp_enqueue_script('em_regiones_comunas', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas.js', ['jquery'], "1.0", true);

    // Script para el checkout de bloques
    wp_enqueue_script('em_regiones_comunas_blocks', plugin_dir_url(__FILE__) . 'assets/js/em-regiones-comunas-blocks.js', ['wc-blocks-checkout'], "1.0", true);
  }

  public function get_comunas($region_name = null)
  {
    global $wpdb;

    if (is_array($this->comunas)) {
      return $this->comunas;
    }

    $st_where = '';

    if ($region_name) {
      $st_where = " AND r.region='{$region_name}' ";
    }

    $sql = "SELECT co.id, co.comuna, p.provincia, p.region_id, r.region FROM {$this->installer->comunas_table} AS co
                    INNER JOIN {$this->installer->provincias_table} AS p ON co.provincia_id=p.id 
                    INNER JOIN {$this->installer->regiones_table} AS r ON p.region_id=r.id 
                    WHERE 1=1 {$st_where} ORDER BY co.comuna";

    $this->comunas = $results = $wpdb->get_results($sql);
    return $results;
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
    return $wpdb->get_results("SELECT * FROM {$this->installer->regiones_table} ORDER BY orden ASC");
  }
}

new Em_Regiones_Comunas();
