<?php
/**
 * Plugin Name:       And-MenuManager
 * Plugin URI:        https://github.com/galandras12/And-MenuManager
 * Description:       Nagy méretű (több száz / több ezer oldalas) oldalstruktúrák navigációs menüinek gyors, stabil kezelése. Szabályalapú menük, automatikus aloldal-hozzáadás, drag &amp; drop rendezés, szerepkör alapú hozzáférés és modern, minimalista kezelőfelület.
 * Version:           0.5.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            galandras12 + AI
 * Author URI:        https://github.com/galandras12
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       and-menumanager
 * Domain Path:       /languages
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

define( 'AMM_VERSION', '0.5.1' );
define( 'AMM_DB_VERSION', '1' );
define( 'AMM_FILE', __FILE__ );
define( 'AMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMM_URL', plugin_dir_url( __FILE__ ) );
define( 'AMM_REST_NAMESPACE', 'and-menumanager/v1' );

/**
 * Egyszerű, fájlnév-konvención alapuló autoloader.
 *
 * AMM_Menu_Repository  ->  includes/class-amm-menu-repository.php
 *
 * @param string $class Osztálynév.
 * @return void
 */
function amm_autoload( $class ) {
	if ( 0 !== strpos( $class, 'AMM_' ) ) {
		return;
	}

	$file = AMM_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'amm_autoload' );

require_once AMM_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'AMM_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AMM_Installer', 'deactivate' ) );

/**
 * A plugin egyetlen belépési pontja.
 *
 * @return AMM_Plugin
 */
function amm() {
	return AMM_Plugin::instance();
}

amm();
