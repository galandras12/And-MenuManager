<?php
/**
 * Eltávolítás: táblák és beállítások törlése.
 *
 * @package And_MenuManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-amm-installer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-amm-capabilities.php';

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		AMM_Installer::uninstall();
		restore_current_blog();
	}
} else {
	AMM_Installer::uninstall();
}
