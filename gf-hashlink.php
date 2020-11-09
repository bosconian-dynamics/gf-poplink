<?php
/**
 * Plugin Name:       GF Hash Link
 * Plugin URI:        https://github.com/bosconian-dynamics/gf-hash-link
 * Description:       Lorem UPSON!
 * Version:           0.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Adam Bosco <wordpress@adambos.co>
 * License:           MIT
 *
 * @package BosconianDynamics\GFHashLink
 */

namespace BosconianDynamics\GFHashLink;

\add_action( 'admin_notices', __NAMESPACE__ . '\check_gf_dependency' );

/**
 * Print an error to admin notices if Gravity Forms or the GF Addon Framework appear to be absent.
 *
 * @return void
 */
function check_gf_dependency() {
  if( method_exists( 'GFForms', 'include_addon_framework' ) )
    return;

  echo '<div class="notice notice-error"><p>' . __( 'The GF HashLink plugin depends on Gravity Forms.', 'gf-hashlink' ) . '</p></div>';
}

\add_action( 'gform_loaded', __NAMESPACE__ . '\load', 5 );

/**
 * Load the HashLink addon
 *
 * @return void
 */
function load() {
  if( ! method_exists( 'GFForms', 'include_addon_framework' ) )
    return;

  require_once __DIR__ . '/inc/GFHashLinkAddOn.php';

  \GFAddOn::register( __NAMESPACE__ . '\GFHashLinkAddOn' );
}

/**
 * Public addon instance accessor.
 *
 * @return GFHashLinkAddOn
 */
function addon() {
  return GFHashLinkAddOn::get_instance();
}
