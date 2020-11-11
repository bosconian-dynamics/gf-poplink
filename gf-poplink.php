<?php
/**
 * Plugin Name:       GF Population Link
 * Plugin URI:        https://github.com/bosconian-dynamics/gf-poplink
 * Description:       Lorem UPSON!
 * Version:           0.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Adam Bosco <wordpress@adambos.co>
 * License:           MIT
 *
 * @package BosconianDynamics\GFPopLink
 */

namespace BosconianDynamics\GFPopLink;

/**
 * Print an error to admin notices if Gravity Forms or the GF Addon Framework appear to be absent.
 *
 * @return void
 */
function check_gf_dependency() {
  if( method_exists( 'GFForms', 'include_addon_framework' ) )
    return;

  echo '<div class="notice notice-error"><p>' . __( 'The GF Population Link plugin depends on Gravity Forms.', 'gf-poplink' ) . '</p></div>';
}

\add_action( 'admin_notices', __NAMESPACE__ . '\check_gf_dependency' );

/**
 * Load the poplink addon
 *
 * @return void
 */
function load() {
  if( ! method_exists( 'GFForms', 'include_addon_framework' ) )
    return;

  require_once __DIR__ . '/inc/GFPopLinkAddOn.php';

  \GFAddOn::register( __NAMESPACE__ . '\GFPopLinkAddOn' );
}

\add_action( 'gform_loaded', __NAMESPACE__ . '\load', 5 );

/**
 * Public addon instance accessor.
 *
 * @return GFpoplinkAddOn
 */
function addon() {
  return GFPopLinkAddOn::get_instance();
}
