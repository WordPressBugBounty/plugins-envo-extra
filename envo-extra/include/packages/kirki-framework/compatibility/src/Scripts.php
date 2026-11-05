<?php
/**
 * Registers scripts for WordPress Compatibility with versions prior to WP5.0
 *
 * @package   Kirki
 * @category  Core
 * @author    Ari Stathopoulos (@aristath)
 * @copyright Copyright (c) 2020, David Vongries
 * @license   https://opensource.org/licenses/MIT
 * @since     0.1
 */

namespace Kirki\Compatibility;

use Kirki\URL;

/**
 * Adds scripts for backwards-compatibility
 *
 * @since 0.1
 */
class Scripts {

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 0.1
	 */
	public function __construct() {
		global $wp_version;

		/**
		 * Check if the WordPress version is lower than 5.0
		 * If lower then we need to enqueue the backported scripts.
		 */
		if ( version_compare( $GLOBALS['wp_version'], '5.0', '<' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
			add_action( 'admin_register_scripts', [ $this, 'register_scripts' ] );
			add_action( 'customize_controls_enqueue_scripts', [ $this, 'register_scripts' ] );
		}
	}

	/**
	 * Enqueue missing WP scripts.
	 *
	 * @access public
	 * @since 0.1
	 * @return void
	 */
	public function register_scripts() {
		// The scripts are in WordPress since 5.0 (Envo Extra requires 5.3), the backported copies were removed.
	}

}
