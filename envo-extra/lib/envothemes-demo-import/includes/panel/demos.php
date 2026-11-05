<?php
/**
 * Demos
 *
 * @package EnvoThemes_Demo_Import
 * @category Core
 * @author EnvoThemes
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

// Start Class
if ( !class_exists( 'EnvoThemes_Demos' ) ) {

	class EnvoThemes_Demos {

		/**
		 * Start things up
		 */
		public function __construct() {

			// Return if not in admin
			if ( !is_admin() || is_customize_preview() ) {
				return;
			}

			// Import demos page
			if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
				require_once( ENVO_PATH . '/includes/panel/classes/importers/class-helpers.php' );
				require_once( ENVO_PATH . '/includes/panel/classes/class-install-demos.php' );
			}

			// Disable Woo Wizard
			add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
			add_filter( 'woocommerce_show_admin_notice', '__return_false' );
			add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_false' );

			// Start things
			add_action( 'admin_init', array( $this, 'init' ) );

			// Demos scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

			// Demos popup
			add_action( 'admin_footer', array( $this, 'popup' ) );
		}

		/**
		 * Register the AJAX methods
		 *
		 * @since 1.0.0
		 */
		public function init() {

			// Demos popup ajax
			add_action( 'wp_ajax_envo_ajax_get_demo_data', array( $this, 'ajax_demo_data' ) );
			add_action( 'wp_ajax_envo_ajax_required_plugins_activate', array( $this, 'ajax_required_plugins_activate' ) );

			// Get data to import
			add_action( 'wp_ajax_envo_ajax_get_import_data', array( $this, 'ajax_get_import_data' ) );

			// Import XML file
			add_action( 'wp_ajax_envo_ajax_import_xml', array( $this, 'ajax_import_xml' ) );

			// Delete existing content before the import (optional)
			add_action( 'wp_ajax_envo_ajax_reset_content', array( $this, 'ajax_reset_content' ) );

			// Remove the content of an imported demo
			add_action( 'wp_ajax_envo_ajax_remove_demo', array( $this, 'ajax_remove_demo' ) );

			// Import customizer settings
			add_action( 'wp_ajax_envo_ajax_import_theme_settings', array( $this, 'ajax_import_theme_settings' ) );

			// Import widgets
			add_action( 'wp_ajax_envo_ajax_import_widgets', array( $this, 'ajax_import_widgets' ) );

			// Import widgets
			add_action( 'wp_ajax_envo_ajax_envo_activate_enwoo', array( $this, 'ajax_activate_enwoo' ) );

			// After import
			add_action( 'wp_ajax_envo_after_import', array( $this, 'ajax_after_import' ) );
		}

		/**
		 * Load scripts
		 *
		 * @since 1.4.5
		 */
		public static function scripts( $hook_suffix ) {

			if ( 'appearance_page_envothemes-panel-install-demos' == $hook_suffix ) {

				// CSS
				wp_enqueue_style( 'envo-demos-style', plugins_url( '/assets/css/demos.css', __FILE__ ), array(), ENVO_EXTRA_CURRENT_VERSION );

				// JS
				wp_enqueue_script( 'envo-demos-js', plugins_url( '/assets/js/demos.js', __FILE__ ), array( 'jquery', 'wp-util', 'updates' ), ENVO_EXTRA_CURRENT_VERSION, true );

				wp_localize_script( 'envo-demos-js', 'envoDemos', array(
					'ajaxurl'					 => admin_url( 'admin-ajax.php' ),
					'demo_data_nonce'			 => wp_create_nonce( 'get-demo-data' ),
					'envo_import_data_nonce'	 => wp_create_nonce( 'envo_import_data_nonce' ),
					'content_importing_error'	 => esc_html__( 'There was a problem during the importing process resulting in the following error from your server:', 'envo-extra' ),
					'button_activating'			 => esc_html__( 'Activating', 'envo-extra' ) . '&hellip;',
					'button_active'				 => esc_html__( 'Active', 'envo-extra' ),
					'button_activate'			 => esc_html__( 'Activate', 'envo-extra' ),
					'button_install'			 => esc_html__( 'Install', 'envo-extra' ),
					'install_failed'			 => esc_html__( 'Installation failed', 'envo-extra' ),
					'reset_confirm'				 => esc_html__( 'All existing posts, pages, products and media files will be permanently deleted. This cannot be undone. Continue?', 'envo-extra' ),
					'remove_confirm'			 => esc_html__( 'Remove all content imported with this demo and restore your previous settings? Images and pages of the demo will be permanently deleted.', 'envo-extra' ),
					'removing'					 => esc_html__( 'Removing…', 'envo-extra' ),
					'removed'					 => esc_html__( 'Removed. Reload the page to see the current state.', 'envo-extra' ),
					'remove_error'				 => esc_html__( 'Removal failed. Try it again.', 'envo-extra' ),
					'server_saving'				 => esc_html__( 'Saving…', 'envo-extra' ),
					'server_saved'				 => esc_html__( 'Saved', 'envo-extra' ),
					'server_error'				 => esc_html__( 'Could not save the server.', 'envo-extra' ),
					'import_failed'				 => esc_html__( 'The import stopped. Choose another demo server below the demo list and try again.', 'envo-extra' ),
					'popup_error'				 => esc_html__( 'The demo could not be loaded. Reload the page and try again.', 'envo-extra' ),
				) );
			}

			//wp_enqueue_style('envo-notices', plugins_url('/assets/css/notify.css', __FILE__));
		}

		/**
		 * Verify capability and nonce of an import request. Stops the request on failure.
		 *
		 * @since 1.9.25
		 */
		private static function verify_import_request( $capability = 'manage_options' ) {
			$nonce = isset( $_POST[ 'envo_import_demo_data_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'envo_import_demo_data_nonce' ] ) ) : '';
			if ( !current_user_can( $capability ) || !wp_verify_nonce( $nonce, 'envo_import_demo_data_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'This action was stopped for security purposes.', 'envo-extra' ) ), 403 );
			}
		}

		/**
		 * Return data of the demo selected in the request. Stops the request if the demo does not exist.
		 *
		 * @since 1.9.25
		 */
		private static function get_requested_demo( $key = 'envo_import_demo', $method = 'post' ) {
			$request	 = 'get' === $method ? $_GET : $_POST; // phpcs:ignore WordPress.Security.NonceVerification -- verified by the caller.
			$demo_type	 = isset( $request[ $key ] ) ? sanitize_text_field( wp_unslash( $request[ $key ] ) ) : '';
			$demos		 = self::get_demos_data();
			if ( '' === $demo_type || !isset( $demos[ $demo_type ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown demo.', 'envo-extra' ) ), 400 );
			}
			// PRO demo: only with the PRO plugin and its active license - the same check as the popup, here for every
			// import step (the popup can be bypassed)
			$premium = isset( $demos[ $demo_type ][ 'required_plugins' ][ 'premium' ] ) ? $demos[ $demo_type ][ 'required_plugins' ][ 'premium' ] : array();
			if ( $premium ) {
				$pro = self::pro_action( $premium, array() );
				if ( $pro ) {
					wp_send_json_error( array( 'message' => $pro[ 'note' ] ), 403 );
				}
			}
			return array( $demo_type, $demos[ $demo_type ] );
		}

		/**
		 * Send the result of an import step.
		 *
		 * @since 1.9.25
		 */
		private static function send_import_result( $result ) {
			if ( is_wp_error( $result ) ) {
				wp_send_json( $result->errors );
			}
			wp_die( 'successful import' );
		}

		/**
		 * IDs created while the XML import runs.
		 *
		 * @since 1.9.25
		 */
		private static $tracked = array( 'posts' => array(), 'terms' => array(), 'menus' => array() );

		/**
		 * Demo being imported (for the filters running during the XML import).
		 */
		private static $import_demo = '';

		/**
		 * A menu of the site with the same slug as a menu of the demo would get the demo items added to it (the importer
		 * reuses existing terms). Rename it first, so the demo brings its own menu; it is renamed back when the demo is
		 * removed.
		 *
		 * @since 1.9.25
		 */
		public static function rename_existing_menus( $terms ) {
			$history = self::get_history();
			$own	 = array();
			if ( isset( $history[ self::$import_demo ][ 'terms' ] ) ) {
				foreach ( $history[ self::$import_demo ][ 'terms' ] as $term ) {
					$own[] = (int) $term[ 0 ];
				}
			}
			foreach ( (array) $terms as $term ) {
				if ( empty( $term[ 'term_taxonomy' ] ) || 'nav_menu' !== $term[ 'term_taxonomy' ] || empty( $term[ 'slug' ] ) ) {
					continue;
				}
				$existing = get_term_by( 'slug', $term[ 'slug' ], 'nav_menu' );
				// Keep a menu this demo created in an earlier import
				if ( !$existing || in_array( (int) $existing->term_id, $own, true ) ) {
					continue;
				}
				/* translators: %s: menu name */
				$name	 = sprintf( __( '%s (before demo)', 'envo-extra' ), $existing->name );
				$slug	 = wp_unique_term_slug( $existing->slug . '-before-demo', (object) array( 'taxonomy' => 'nav_menu', 'parent' => 0 ) );
				$updated = wp_update_term( $existing->term_id, 'nav_menu', array( 'name' => $name, 'slug' => $slug ) );
				if ( !is_wp_error( $updated ) ) {
					self::$tracked[ 'menus' ][] = array( (int) $existing->term_id, $existing->name, $existing->slug );
				}
			}
			return $terms;
		}

		public static function track_post( $post_id, $post, $update ) {
			if ( !$update ) {
				self::$tracked[ 'posts' ][] = (int) $post_id;
			}
		}

		public static function track_attachment( $post_id ) {
			self::$tracked[ 'posts' ][] = (int) $post_id;
		}

		public static function track_term( $term_id, $tt_id, $taxonomy ) {
			self::$tracked[ 'terms' ][] = array( (int) $term_id, $taxonomy );
		}

		/**
		 * Imported demos with what they added and the site state before the import.
		 *
		 * @since 1.9.25
		 */
		public static function get_history() {
			$history = get_option( 'envo_demo_import_history', array() );
			return is_array( $history ) ? $history : array();
		}

		private static function save_history( $history ) {
			update_option( 'envo_demo_import_history', $history, false );
		}

		/**
		 * Replace the URL of the demo site (demo data "demo_url") with the URL of this site in the posts and menu items
		 * the import added.
		 *
		 * @since 1.9.25
		 */
		private static function replace_demo_urls( $demo_type, $demo ) {
			$from = apply_filters( 'envo_extra_demo_urls', empty( $demo[ 'demo_url' ] ) ? array() : array( $demo[ 'demo_url' ] ), $demo_type, $demo );
			if ( !$from ) {
				return;
			}
			$history = self::get_history();
			$to		 = untrailingslashit( home_url() );
			$search	 = array();
			foreach ( $from as $url ) {
				$url		 = untrailingslashit( $url );
				$search[]	 = $url;
				$search[]	 = str_replace( '/', '\\/', $url ); // JSON in block attributes
			}
			$replace = array();
			foreach ( $search as $url ) {
				$replace[] = false !== strpos( $url, '\\/' ) ? str_replace( '/', '\\/', $to ) : $to;
			}
			foreach ( isset( $history[ $demo_type ][ 'posts' ] ) ? $history[ $demo_type ][ 'posts' ] : array() as $post_id ) {
				$post = get_post( $post_id );
				if ( !$post ) {
					continue;
				}
				if ( 'nav_menu_item' === $post->post_type ) {
					$link = get_post_meta( $post_id, '_menu_item_url', true );
					if ( $link ) {
						update_post_meta( $post_id, '_menu_item_url', str_replace( $search, $replace, $link ) );
					}
					continue;
				}
				$content = str_replace( $search, $replace, $post->post_content );
				if ( $content !== $post->post_content ) {
					wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $content ) ) );
				}
			}
		}

		/**
		 * Remember the state of the site before the first import of the demo (kept for later imports of the same demo).
		 *
		 * @since 1.9.25
		 */
		private static function remember_state( $demo_type, $demo ) {
			$history	 = self::get_history();
			$stylesheet	 = get_stylesheet();
			if ( !isset( $history[ $demo_type ] ) ) {
				$history[ $demo_type ] = array(
					'name'		 => isset( $demo[ 'demo_name' ] ) ? $demo[ 'demo_name' ] : $demo_type,
					'time'		 => time(),
					'theme'		 => $stylesheet,
					'posts'		 => array(),
					'terms'		 => array(),
					'mods'		 => array(),
					'options'	 => array(
						'show_on_front'		 => get_option( 'show_on_front' ),
						'page_on_front'		 => get_option( 'page_on_front' ),
						'page_for_posts'	 => get_option( 'page_for_posts' ),
						'posts_per_page'	 => get_option( 'posts_per_page' ),
						'sidebars_widgets'	 => get_option( 'sidebars_widgets' ),
						'site_logo'			 => get_option( 'site_logo' ),
					),
				);
			}
			if ( !array_key_exists( $stylesheet, $history[ $demo_type ][ 'mods' ] ) ) {
				$history[ $demo_type ][ 'mods' ][ $stylesheet ] = get_option( 'theme_mods_' . $stylesheet, null );
			}
			$history[ $demo_type ][ 'time' ] = time();
			self::save_history( $history );
		}

		/**
		 * Remove the content added by an imported demo and restore the previous settings. Runs in batches.
		 *
		 * @since 1.9.25
		 */
		public function ajax_remove_demo() {
			check_ajax_referer( 'envo_remove_demo', 'security' );
			if ( !current_user_can( 'manage_options' ) || !current_user_can( 'delete_others_posts' ) ) {
				wp_send_json_error( array( 'message' => __( 'This action was stopped for security purposes.', 'envo-extra' ) ), 403 );
			}

			$demo_type	 = isset( $_POST[ 'demo' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'demo' ] ) ) : '';
			$history	 = self::get_history();
			if ( !isset( $history[ $demo_type ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown demo.', 'envo-extra' ) ), 400 );
			}
			$entry = $history[ $demo_type ];

			// Never delete the Elementor kit, the WooCommerce pages and the privacy page
			$keep = array( (int) get_option( 'elementor_active_kit' ), (int) get_option( 'wp_page_for_privacy_policy' ) );
			foreach ( array( 'shop', 'cart', 'checkout', 'myaccount', 'terms' ) as $wc_page ) {
				$keep[] = (int) get_option( 'woocommerce_' . $wc_page . '_page_id' );
			}

			// Posts in batches
			$started = time();
			while ( $entry[ 'posts' ] && time() - $started < 20 ) {
				$id = (int) array_pop( $entry[ 'posts' ] );
				if ( !$id || in_array( $id, $keep, true ) || 'kit' === get_post_meta( $id, '_elementor_template_type', true ) ) {
					continue;
				}
				$type = get_post_type( $id );
				if ( 'attachment' === $type ) {
					wp_delete_attachment( $id, true );
				} elseif ( $type ) {
					wp_delete_post( $id, true );
				}
			}
			if ( $entry[ 'posts' ] ) {
				$history[ $demo_type ] = $entry;
				self::save_history( $history );
				wp_send_json_success( array( 'done' => false, 'left' => count( $entry[ 'posts' ] ) ) );
			}

			// Terms (categories, tags, product categories, menus)
			foreach ( $entry[ 'terms' ] as $term ) {
				if ( 'nav_menu' === $term[ 1 ] ) {
					wp_delete_nav_menu( $term[ 0 ] );
				} elseif ( term_exists( (int) $term[ 0 ], $term[ 1 ] ) && (int) get_option( 'default_' . $term[ 1 ] ) !== (int) $term[ 0 ] ) {
					wp_delete_term( $term[ 0 ], $term[ 1 ] );
				}
			}
			// Menus of the site renamed before the import get their names back
			foreach ( isset( $entry[ 'menus' ] ) ? $entry[ 'menus' ] : array() as $menu ) {
				if ( term_exists( (int) $menu[ 0 ], 'nav_menu' ) ) {
					wp_update_term( (int) $menu[ 0 ], 'nav_menu', array( 'name' => $menu[ 1 ], 'slug' => $menu[ 2 ] ) );
				}
			}

			// Settings before the import
			foreach ( $entry[ 'mods' ] as $stylesheet => $mods ) {
				if ( null === $mods || false === $mods ) {
					delete_option( 'theme_mods_' . $stylesheet );
				} else {
					update_option( 'theme_mods_' . $stylesheet, $mods );
				}
			}
			foreach ( $entry[ 'options' ] as $option => $value ) {
				if ( false === $value ) {
					delete_option( $option ); // did not exist before the import
				} else {
					update_option( $option, $value );
				}
			}
			if ( !empty( $entry[ 'theme' ] ) && get_stylesheet() !== $entry[ 'theme' ] && wp_get_theme( $entry[ 'theme' ] )->exists() ) {
				switch_theme( $entry[ 'theme' ] );
			}

			unset( $history[ $demo_type ] );
			self::save_history( $history );

			wp_send_json_success( array( 'done' => true ) );
		}

		/**
		 * Get demos data to add them in the Demo Import and Pro Demos plugins
		 *
		 * @since 1.4.5
		 */
		public static function get_demos_data() {
			$theme		 = wp_get_theme();
			$enwoo_data	 = array();
			$data_royal	 = array();
			$data_one	 = array();
			$data		 = array();
			// Demos url
			$img_url	 = ENVO_URL . 'img/demos/' . $theme->template . '/';
			$source = get_option( 'envothemes_demo_import_url');
			$url		 = $source . '/wp-content/uploads/demo-import/' . $theme->template . '/';

			if ( 'Envo Shop' == $theme->name || 'envo-shop' == $theme->template ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce' ),
						'xml_file'			 => $url . 'default/default-content.xml',
						'theme_settings'	 => $url . 'default/default-customizer.dat',
						'widgets_file'		 => $url . 'default/default-widgets.wie',
						'screenshot'		 => $img_url . 'default/screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/envo-shop/',
						'home_title'		 => 'Home',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1400',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '1',
						'woo_crop_height'	 => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
								),
							),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Envo Shopper' == $theme->name || 'envo-shopper' == $theme->template || 'Envo Online Store' == $theme->name || 'envo-online-store' == $theme->template || 'Envo Marketplace' == $theme->name || 'envo-marketplace' == $theme->template ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce' ),
						'xml_file'			 => $url . 'default/default-content.xml',
						'theme_settings'	 => $url . 'default/default-customizer.dat',
						'widgets_file'		 => $url . 'default/default-widgets.wie',
						'screenshot'		 => $img_url . 'default/screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/' . $theme->template,
						'home_title'		 => 'Home',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
								),
							),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Envo eCommerce' == $theme->name || 'envo-ecommerce' == $theme->template || 'Envo Storefront' == $theme->name || 'envo-storefront' == $theme->template ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce' ),
						'xml_file'			 => $url . 'default/default-content.xml',
						'theme_settings'	 => $url . 'default/default-customizer.dat',
						'widgets_file'		 => $url . 'default/default-widgets.wie',
						'screenshot'		 => $img_url . 'default/screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/' . $theme->template,
						'home_title'		 => 'Home',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '1',
						'woo_crop_height'	 => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Entr' == $theme->name ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free', 'Business', 'Landing Page' ),
						'xml_file'			 => $url . 'content.xml',
						'theme_settings'	 => $url . 'customizer.dat',
						'widgets_file'		 => $url . 'widgets.wie',
						'screenshot'		 => $img_url . 'screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/entr/',
						'home_title'		 => 'Entr Homepage',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '1',
						'woo_crop_height'	 => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '(Only for shop purposes.)',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '(Only for shop purposes.)',
								),
							),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Altr' == $theme->name ) {
				$img_url = ENVO_URL . 'img/demos/altr/';

				$url	 = $source . '/wp-content/uploads/demo-import/altr/';
				$data	 = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'			 => $url . 'content.xml',
						'theme_settings'	 => $url . 'customizer.dat',
						'widgets_file'		 => $url . 'widgets.wie',
						'screenshot'		 => $img_url . 'screenshot.webp',
						'home_title'		 => 'Altr Homepage',
						'demo_link'			 => 'https://envothemes.com/altr/',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Spacr' == $theme->name ) {
				$img_url = ENVO_URL . 'img/demos/spacr/';
				$url	 = $source . '/wp-content/uploads/demo-import/spacr/';
				$data	 = array(
					$theme->template => array(
						'demo_name'						 => $theme->name,
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot.webp',
						'home_title'					 => 'Spacr Homepage',
						'demo_link'						 => 'https://envothemes.com/spacr/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1140',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '2',
						'woo_crop_height'				 => '3',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
					'homepage-2'	 => array(
						'demo_name'						 => 'Spacr #2',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-2.webp',
						'home_title'					 => 'Homepage #2',
						'demo_link'						 => 'https://envothemes.com/spacr/homepage-2/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1140',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '2',
						'woo_crop_height'				 => '3',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
					'homepage-3'	 => array(
						'demo_name'						 => 'Spacr #3',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-3.webp',
						'home_title'					 => 'Homepage #3',
						'demo_link'						 => 'https://envothemes.com/spacr/homepage-3/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1140',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '2',
						'woo_crop_height'				 => '3',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
					'homepage-4'	 => array(
						'demo_name'						 => 'Spacr #4',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-4.webp',
						'home_title'					 => 'Homepage #4',
						'demo_link'						 => 'https://envothemes.com/spacr/homepage-4/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1140',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '2',
						'woo_crop_height'				 => '3',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
					'homepage-5'	 => array(
						'demo_name'						 => 'Spacr #5',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-5.webp',
						'home_title'					 => 'Homepage #5',
						'demo_link'						 => 'https://envothemes.com/spacr/homepage-5/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1140',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '2',
						'woo_crop_height'				 => '3',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
					),
				);				
			} elseif ( 'Lupr' == $theme->name || 'lupr' == $theme->template ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce', 'Gutenberg', 'Free', 'Business', 'Landing Page', 'FSE' ),
						'xml_file'			 => $url . 'content.xml',
						'theme_settings'	 => $url . 'customizer.dat',
						'widgets_file'		 => $url . 'widgets.wie',
						'screenshot'		 => $img_url . 'screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/lupr/',
						//'home_title' => 'Lupr Homepage',
						//'blog_title' => 'Blog',
						//'posts_to_show' => '6',
						//'elementor_width' => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '1',
						'woo_crop_height'	 => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '(Only for shop purposes.)',
								),
							),
							'premium'		 => array(),
						),
					),
				);
			} elseif ( 'Elastra' == $theme->name || 'elastra' == $theme->template ) {
				$data = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'WooCommerce', 'Gutenberg', 'Free', 'Business', 'Landing Page', 'FSE' ),
						'xml_file'			 => $url . 'content.xml',
						'theme_settings'	 => $url . 'customizer.dat',
						'widgets_file'		 => $url . 'widgets.wie',
						'screenshot'		 => $img_url . 'screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/elastra/',
						//'home_title' => 'Lupr Homepage',
						//'blog_title' => 'Blog',
						//'posts_to_show' => '6',
						//'elementor_width' => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '1',
						'woo_crop_height'	 => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '(Only for shop purposes.)',
								),
							),
							'premium'		 => array(),
						),
					),
				);	
			} elseif ( 'Lancr' == $theme->name || 'lancr' == $theme->template ) {
				$img_url	 = ENVO_URL . 'img/demos/' . $theme->template . '/';
				$enwoo_url		 = $source . '/wp-content/uploads/demo-import/enwoo/';
				$data			 = array(
					$theme->template => array(
						'demo_name'			 => $theme->name,
						'categories'		 => array( 'Gutenberg', 'Free', 'Blog', 'Newspaper', 'FSE' ),
						'xml_file'			 => $url . 'content.xml',
						'theme_settings'	 => $url . 'customizer.dat',
						'widgets_file'		 => $url . 'widgets.wie',
						'screenshot'		 => $img_url . 'screenshot.webp',
						'demo_link'			 => 'https://envothemes.com/fse-demo/',
						//'home_title' => 'Lancr Homepage',
						//'blog_title' => 'Blog',
						//'posts_to_show' => '6',
						//'elementor_width' => '1140',
						'is_shop'			 => false,
//                        'woo_image_size' => '600',
//                        'woo_thumb_size' => '300',
//                        'woo_crop_width' => '1',
//                        'woo_crop_height' => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
					),
					'free-demo-9'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #9',
						'categories'		 => array( 'Gutenberg', 'Free', 'Blog', 'Newspaper' ),
						'xml_file'			 => $enwoo_url . 'free-9/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-9/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-9/widgets.wie',
						'screenshot'		 => $img_url . 'free-9/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-9/',
						//'home_title' => 'Lancr Homepage',
						//'blog_title' => 'Blog',
						//'posts_to_show' => '6',
						//'elementor_width' => '1140',
						'is_shop'			 => false,
//                        'woo_image_size' => '600',
//                        'woo_thumb_size' => '300',
//                        'woo_crop_width' => '1',
//                        'woo_crop_height' => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-10'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #10',
						'categories'		 => array( 'Gutenberg', 'Free', 'Blog', 'Newspaper' ),
						'xml_file'			 => $enwoo_url . 'free-10/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-10/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-10/widgets.wie',
						'screenshot'		 => $img_url . 'free-10/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-10/',
						//'home_title' => 'Lancr Homepage',
						//'blog_title' => 'Blog',
						//'posts_to_show' => '6',
						//'elementor_width' => '1140',
						'is_shop'			 => false,
//                        'woo_image_size' => '600',
//                        'woo_thumb_size' => '300',
//                        'woo_crop_width' => '1',
//                        'woo_crop_height' => '1',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
				);
			} elseif ( 'Enwoo' == $theme->name ) {
				// Enwoo Demos url
				$img_url	 = ENVO_URL . 'img/demos/enwoo/';
				$enwoo_url		 = $source . '/wp-content/uploads/demo-import/enwoo/';

				$data	 = array(
					'free-demo-8'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #8',
						'categories'		 => array( 'Elementor', 'Free', 'Business', 'WooCommerce' ),
						'xml_file'			 => $enwoo_url . 'free-8/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-8/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-8/widgets.wie',
						'screenshot'		 => $img_url . 'free-8/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-8/',
						'home_title'		 => 'Home Free #8',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '(Only for shop purposes.)',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-1'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #1',
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'			 => $enwoo_url . 'free-1/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-1/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-1/widgets.wie',
						'screenshot'		 => $img_url . 'free-1/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-1/',
						'home_title'		 => 'Home Free #1',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-2'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #2',
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'			 => $enwoo_url . 'free-2/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-2/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-2/widgets.wie',
						'screenshot'		 => $img_url . 'free-2/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-2/',
						'home_title'		 => 'Home Free #2',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-3'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #3',
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'			 => $enwoo_url . 'free-3/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-3/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-3/widgets.wie',
						'screenshot'		 => $img_url . 'free-3/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-3/',
						'home_title'		 => 'Home Free #3',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-4'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #4',
						'categories'		 => array( 'WooCommerce', 'Elementor', 'Free', 'Business' ),
						'xml_file'			 => $enwoo_url . 'free-4/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-4/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-4/widgets.wie',
						'screenshot'		 => $img_url . 'free-4/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-4/',
						'home_title'		 => 'Home Free #4',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-5'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #5',
						'categories'		 => array( 'WooCommerce', 'Gutenberg', 'Free', 'Business' ),
						'xml_file'			 => $enwoo_url . 'free-5/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-5/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-5/widgets.wie',
						'screenshot'		 => $img_url . 'free-5/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-5/',
						'home_title'		 => 'Home Free #5',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-6'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #6',
						'categories'		 => array( 'Gutenberg', 'Free', 'Business' ),
						'xml_file'			 => $enwoo_url . 'free-6/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-6/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-6/widgets.wie',
						'screenshot'		 => $img_url . 'free-6/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-6/',
						'home_title'		 => 'Home Free #6',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
					'free-demo-7'	 => array(
						'demo_name'			 => 'Enwoo Free Demo #7',
						'categories'		 => array( 'Elementor', 'Free', 'Business', 'WooCommerce' ),
						'xml_file'			 => $enwoo_url . 'free-7/content.xml',
						'theme_settings'	 => $enwoo_url . 'free-7/customizer.dat',
						'widgets_file'		 => $enwoo_url . 'free-7/widgets.wie',
						'screenshot'		 => $img_url . 'free-7/screenshot.webp',
						'demo_link'			 => 'https://enwoo-demos.com/free-demo-7/',
						'home_title'		 => 'Home Free #7',
						'blog_title'		 => 'Blog',
						'posts_to_show'		 => '6',
						'elementor_width'	 => '1140',
						'is_shop'			 => true,
						'woo_image_size'	 => '600',
						'woo_thumb_size'	 => '300',
						'woo_crop_width'	 => '2',
						'woo_crop_height'	 => '3',
						'required_plugins'	 => array(
							'free'			 => array(
								array(
									'slug'	 => 'envo-extra',
									'init'	 => 'envo-extra/envo-extra.php',
									'name'	 => 'Envo Extra',
								),
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
								),
							),
							'recommended'	 => array(),
							'premium'		 => array(),
						),
						'required_theme'	 => 'Enwoo',
						'required_theme_url'	 => 'https://enwoo-wp.com/',
					),
				);
			}

			$img_url = ENVO_URL . 'img/demos/envo-royal/';
			$url		= $source . '/wp-content/uploads/demo-import/envo-royal/envo-royal/';
			$url_woo	= $source . '/wp-content/uploads/demo-import/envo-royal/envo-royal-woocommerce/';
			$data_royal	 = array(
				'home-free-1' => array(
					'demo_name'						 => 'Envo Royal Home #1',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url . 'content.xml',
					'theme_settings'				 => $url . 'customizer.dat',
					'widgets_file'					 => $url . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-1-new.webp',
					'home_title'					 => 'Home Free #1',
					'demo_link'						 => 'https://envothemes.com/envo-royal/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
								'notice' => 'For Shop purposes only',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-2'	 => array(
					'demo_name'						 => 'Envo Royal Home #2',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url . 'content.xml',
					'theme_settings'				 => $url . 'customizer.dat',
					'widgets_file'					 => $url . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-2.webp',
					'home_title'					 => 'Home Free #2',
					'demo_link'						 => 'https://envothemes.com/envo-royal/home-free-2/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
								'notice' => '',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
								'notice' => 'For Shop purposes only',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-3'	 => array(
					'demo_name'						 => 'Envo Royal Home #3',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url . 'content.xml',
					'theme_settings'				 => $url . 'customizer.dat',
					'widgets_file'					 => $url . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-3.webp',
					'home_title'					 => 'Home Free #3',
					'demo_link'						 => 'https://envothemes.com/envo-royal/home-free-3/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
								'notice' => '',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
								'notice' => 'For Shop purposes only',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-4'	 => array(
					'demo_name'						 => 'Envo Royal Home #4',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url . 'content.xml',
					'theme_settings'				 => $url . 'customizer.dat',
					'widgets_file'					 => $url . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-4.webp',
					'home_title'					 => 'Home Free #4',
					'demo_link'						 => 'https://envothemes.com/envo-royal/home-free-4/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
								'notice' => '',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
								'notice' => 'For Shop purposes only',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-woo-1'	 => array(
					'demo_name'						 => 'Envo Royal Woo Home #1',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url_woo . 'content.xml',
					'theme_settings'				 => $url_woo . 'customizer.dat',
					'widgets_file'					 => $url_woo . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-woo-1.webp',
					'home_title'					 => 'Home Free WooCommerce #1',
					'demo_link'						 => 'https://envothemes.com/envo-royal-woocommerce/home-free-woocommerce-1/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
							),
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'yith-woocommerce-wishlist',
								'init'	 => 'yith-woocommerce-wishlist/init.php',
								'name'	 => 'YITH WooCommerce Wishlist',
							),
							array(
								'slug'	 => 'yith-woocommerce-compare',
								'init'	 => 'yith-woocommerce-compare/init.php',
								'name'	 => 'YITH WooCommerce Compare',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-woo-2'	 => array(
					'demo_name'						 => 'Envo Royal Woo Home #2',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url_woo . 'content.xml',
					'theme_settings'				 => $url_woo . 'customizer.dat',
					'widgets_file'					 => $url_woo . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-woo-2.webp',
					'home_title'					 => 'Home Free WooCommerce #2',
					'demo_link'						 => 'https://envothemes.com/envo-royal-woocommerce/home-free-woocommerce-2/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
							),
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'yith-woocommerce-wishlist',
								'init'	 => 'yith-woocommerce-wishlist/init.php',
								'name'	 => 'YITH WooCommerce Wishlist',
							),
							array(
								'slug'	 => 'yith-woocommerce-compare',
								'init'	 => 'yith-woocommerce-compare/init.php',
								'name'	 => 'YITH WooCommerce Compare',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
				'home-free-woo-3'	 => array(
					'demo_name'						 => 'Envo Royal Woo Home #3',
					'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
					'xml_file'						 => $url_woo . 'content.xml',
					'theme_settings'				 => $url_woo . 'customizer.dat',
					'widgets_file'					 => $url_woo . 'widgets.wie',
					'screenshot'					 => $img_url . 'screenshot-woo-3.webp',
					'home_title'					 => 'Home Free WooCommerce #3',
					'demo_link'						 => 'https://envothemes.com/envo-royal-woocommerce/home-free-woocommerce-3/',
					'blog_title'					 => 'Blog',
					'posts_to_show'					 => '6',
					'elementor_width'				 => '1140',
					'is_shop'						 => true,
					'elementor_experiment-container' => 'active',
					'woo_image_size'				 => '600',
					'woo_thumb_size'				 => '300',
					'woo_crop_width'				 => '2',
					'woo_crop_height'				 => '3',
					'required_plugins'				 => array(
						'free'			 => array(
							array(
								'slug'	 => 'elementor',
								'init'	 => 'elementor/elementor.php',
								'name'	 => 'Elementor',
							),
							array(
								'slug'	 => 'envo-elementor-for-woocommerce',
								'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
								'name'	 => 'Elementor Templates and Widgets',
							),
							array(
								'slug'	 => 'woocommerce',
								'init'	 => 'woocommerce/woocommerce.php',
								'name'	 => 'WooCommerce',
							),
						),
						'recommended'	 => array(
							array(
								'slug'	 => 'yith-woocommerce-wishlist',
								'init'	 => 'yith-woocommerce-wishlist/init.php',
								'name'	 => 'YITH WooCommerce Wishlist',
							),
							array(
								'slug'	 => 'yith-woocommerce-compare',
								'init'	 => 'yith-woocommerce-compare/init.php',
								'name'	 => 'YITH WooCommerce Compare',
							),
						),
						'premium'		 => array(),
					),
					'required_theme'		=> 'Envo Royal',
					'required_theme_url'	=> 'https://envothemes.com/envo-royal-free-wp-theme/',
				),
			);
			if ( 'Envo Royal' !== $theme->name ) {
				$img_url = ENVO_URL . 'img/demos/envo-one/';
				$url	 = $source . '/wp-content/uploads/demo-import/envo-one/';
				$data_one	 = array(
					'envo-one-homepage-1' => array(
						'demo_name'						 => 'Envo One Home #1',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot.webp',
						'home_title'					 => 'Envo One Homepage #1',
						'demo_link'						 => 'https://envothemes.com/envo-one/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1270',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '1',
						'woo_crop_height'				 => '1',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'		=> 'Envo One',
						'required_theme_url'	=> 'https://envothemes.com/envo-one-woocommerce/',
					),
					'envo-one-homepage-2'	 => array(
						'demo_name'						 => 'Envo One Home #2',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-2.webp',
						'home_title'					 => 'Envo One Homepage #2',
						'demo_link'						 => 'https://envothemes.com/envo-one/envo-one-homepage-2/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1270',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '1',
						'woo_crop_height'				 => '1',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'		=> 'Envo One',
						'required_theme_url'	=> 'https://envothemes.com/envo-one-woocommerce/',
					),
					'envo-one-homepage-3'	 => array(
						'demo_name'						 => 'Envo One Home #3',
						'categories'					 => array( 'WooCommerce', 'Elementor', 'Free' ),
						'xml_file'						 => $url . 'content.xml',
						'theme_settings'				 => $url . 'customizer.dat',
						'widgets_file'					 => $url . 'widgets.wie',
						'screenshot'					 => $img_url . 'screenshot-3.webp',
						'home_title'					 => 'Envo One Homepage #3',
						'demo_link'						 => 'https://envothemes.com/envo-one/envo-one-homepage-3/',
						'blog_title'					 => 'Blog',
						'posts_to_show'					 => '6',
						'elementor_width'				 => '1270',
						'is_shop'						 => true,
						'elementor_experiment-container' => 'active',
						'woo_image_size'				 => '600',
						'woo_thumb_size'				 => '300',
						'woo_crop_width'				 => '1',
						'woo_crop_height'				 => '1',
						'required_plugins'				 => array(
							'free'			 => array(
								array(
									'slug'	 => 'elementor',
									'init'	 => 'elementor/elementor.php',
									'name'	 => 'Elementor',
								),
								array(
									'slug'	 => 'woocommerce',
									'init'	 => 'woocommerce/woocommerce.php',
									'name'	 => 'WooCommerce',
									'notice' => '',
								),
								array(
									'slug'	 => 'envo-elementor-for-woocommerce',
									'init'	 => 'envo-elementor-for-woocommerce/elementor-templates-widgets-woocommerce.php',
									'name'	 => 'Elementor Templates and Widgets for WooCommerce',
									'notice' => '',
								),
							),
							'recommended'	 => array(
								array(
									'slug'	 => 'yith-woocommerce-wishlist',
									'init'	 => 'yith-woocommerce-wishlist/init.php',
									'name'	 => 'YITH WooCommerce Wishlist',
									'notice' => 'WooCommerce Whishlist Function',
								),
								array(
									'slug'	 => 'yith-woocommerce-compare',
									'init'	 => 'yith-woocommerce-compare/init.php',
									'name'	 => 'YITH WooCommerce Compare',
									'notice' => 'WooCommerce Compare Function',
								),
							),
							'premium'		 => array(),
						),
						'required_theme'		=> 'Envo One',
						'required_theme_url'	=> 'https://envothemes.com/envo-one-woocommerce/',
					),
				);				
			}
			if (defined('ENVO_PRO_CURRENT_VERSION') || defined('ENWOO_PRO_CURRENT_VERSION') || defined('ENVO_SHOPPER_PRO_CURRENT_VERSION') || defined('ENVO_ECOMMERCE_PRO_CURRENT_VERSION') || defined('ENVO_STOREFRONT_PRO_CURRENT_VERSION') || defined('ENVO_SHOP_PRO_CURRENT_VERSION') || defined('ENVO_ONLINE_STORE_PRO_CURRENT_VERSION') || defined('ENVO_MARKETPLACE_PRO_CURRENT_VERSION') || defined('ENVO_SHOPPER_PRO_CURRENT_VERSION')) {
				$data_royal = array();
				$data_one = array();
			}
			// combine the two arrays
			$data		 = array_merge( $data, $data_one );
			$data		 = array_merge( $data, $data_royal );

			// Block demos (Envo Blocks) for the classic themes - one shared demo site, the theme is picked by ?theme=
			$data = array_merge( self::get_block_demos( $source ), $data );

			// Return
			return apply_filters( 'envo_demos_data', $data );
		}

		/**
		 * Get the category list of all categories used in the predefined demo imports array.
		 *
		 * @since 1.4.5
		 */
		public static function get_demo_all_categories( $demo_imports ) {
			$categories = array();

			foreach ( $demo_imports as $item ) {
				if ( !empty( $item[ 'categories' ] ) && is_array( $item[ 'categories' ] ) ) {
					foreach ( $item[ 'categories' ] as $category ) {
						$categories[ sanitize_key( $category ) ] = $category;
					}
				}
			}

			if ( empty( $categories ) ) {
				return false;
			}

			return $categories;
		}

		/**
		 * Return the concatenated string of demo import item categories.
		 * These should be separated by comma and sanitized properly.
		 *
		 * @since 1.4.5
		 */
		public static function get_demo_item_categories( $item ) {
			$sanitized_categories = array();

			if ( isset( $item[ 'categories' ] ) ) {
				foreach ( $item[ 'categories' ] as $category ) {
					$sanitized_categories[] = sanitize_key( $category );
				}
			}

			if ( !empty( $sanitized_categories ) ) {
				return implode( ',', $sanitized_categories );
			}

			return false;
		}

		/**
		 * Block demos for Enwoo, Envo Royal and Envo One. Content is shared, the customizer settings and widgets are
		 * exported for every theme (demo-import/blocks/<demo>/<theme>/).
		 *
		 * @since 1.9.25
		 */
		public static function get_block_demos( $source ) {
			$template = get_template();
			if ( !in_array( $template, array( 'enwoo', 'envo-royal', 'envo-one' ), true ) ) {
				return array();
			}
			$url		 = $source . '/wp-content/uploads/demo-import/blocks/tech-fashion/';
			$demo_url	 = 'https://envothemes.com/block-demos';
			$common		 = array(
				'categories'		 => array( 'Gutenberg', 'WooCommerce', 'Free', 'Blocks' ),
				'xml_file'			 => $url . 'content.xml',
				'theme_settings'	 => $url . $template . '/customizer.dat',
				'widgets_file'		 => $url . $template . '/widgets.wie',
				'demo_url'			 => $demo_url,
				'blog_title'		 => 'Blog',
				'posts_to_show'		 => '6',
				'is_shop'			 => true,
				'woo_image_size'	 => '600',
				'woo_thumb_size'	 => '300',
				'woo_crop_width'	 => '1',
				'woo_crop_height'	 => '1',
				'required_plugins'	 => array(
					'free'			 => array(
						array(
							'slug'	 => 'woocommerce',
							'init'	 => 'woocommerce/woocommerce.php',
							'name'	 => 'WooCommerce',
						),
						array(
							'slug'	 => 'envo-blocks',
							'init'	 => 'envo-blocks/envo-blocks.php',
							'name'	 => 'Envo Blocks',
						),
					),
					'recommended'	 => array(),
					'premium'		 => array(),
				),
			);
			return array(
				'blocks-store-1' => array_merge( $common, array(
					'demo_name'	 => 'Block Shop: Audio',
					'screenshot' => ENVO_URL . 'img/demos/blocks/store-1.webp',
					'demo_link'	 => $demo_url . '/?theme=' . $template,
					'home_title' => 'Home',
				) ),
				'blocks-store-2' => array_merge( $common, array(
					'demo_name'	 => 'Block Shop: Electronics',
					'screenshot' => ENVO_URL . 'img/demos/blocks/store-2.webp',
					'demo_link'	 => $demo_url . '/home-2/?theme=' . $template,
					'home_title' => 'Home 2',
				) ),
				'blocks-store-3' => array_merge( $common, array(
					'demo_name'	 => 'Block Shop: Lifestyle',
					'screenshot' => ENVO_URL . 'img/demos/blocks/store-3.webp',
					'demo_link'	 => $demo_url . '/home-3/?theme=' . $template,
					'home_title' => 'Home 3',
				) ),
				'blocks-store-4' => array_merge( $common, array(
					'demo_name'	 => 'Block Shop: Flash Sale',
					'screenshot' => ENVO_URL . 'img/demos/blocks/store-4.webp',
					'demo_link'	 => $demo_url . '/home-4/?theme=' . $template,
					'home_title' => 'Home 4',
				) ),
				'blocks-store-5' => array_merge( $common, array(
					'demo_name'	 => 'Block Shop: Gadgets',
					'screenshot' => ENVO_URL . 'img/demos/blocks/store-5.webp',
					'demo_link'	 => $demo_url . '/home-5/?theme=' . $template,
					'home_title' => 'Home 5',
				) ),
			);
		}

		/**
		 * Demos popup
		 *
		 * @since 1.4.5
		 */
		public static function popup() {
			global $pagenow;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks which admin page is shown
			if ( isset( $_GET[ 'page' ] ) ) {
				// Display on the demos pages
				if ( ( 'themes.php' == $pagenow && 'envothemes-panel-install-demos' == $_GET[ 'page' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>

					<div id="envo-demo-popup-wrap">
						<div class="envo-demo-popup-container">
							<div class="envo-demo-popup-content-wrap">
								<div class="envo-demo-popup-content-inner">
									<a href="#" class="envo-demo-popup-close" aria-label="<?php esc_attr_e( 'Close', 'envo-extra' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></a>
									<div id="envo-demo-popup-content" role="dialog" aria-modal="true"></div>
								</div>
							</div>
						</div>
						<div class="envo-demo-popup-overlay"></div>
					</div>

					<?php
				}
			}
		}

		/**
		 * Demos popup ajax.
		 *
		 * @since 1.4.5
		 */
		public static function ajax_demo_data() {

			$nonce = isset( $_GET[ 'demo_data_nonce' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'demo_data_nonce' ] ) ) : '';
			if ( !current_user_can( 'manage_options' ) || !wp_verify_nonce( $nonce, 'get-demo-data' ) ) {
				wp_die( esc_html__( 'This action was stopped for security purposes.', 'envo-extra' ), 403 );
			}

			// Get all demos
			$demos = self::get_demos_data();

			// Get selected demo
			$demo = isset( $_GET[ 'demo_name' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'demo_name' ] ) ) : '';
			if ( !isset( $demos[ $demo ] ) ) {
				wp_die( esc_html__( 'Unknown demo.', 'envo-extra' ), 400 );
			}

			// Get required plugins
			$plugins = isset( $demos[ $demo ][ 'required_plugins' ] ) ? $demos[ $demo ][ 'required_plugins' ] : '';

			// Get free plugins
			$free = isset( $plugins[ 'free' ] ) ? $plugins[ 'free' ] : '';

			// Get premium plugins
			$premium = isset( $plugins[ 'premium' ] ) ? $plugins[ 'premium' ] : '';

			// Get recommended plugins
			$recommended = isset( $plugins[ 'recommended' ] ) ? $plugins[ 'recommended' ] : '';

			// Get required plugins
			$gutenberg = isset( $demos[ $demo ][ 'categories' ] ) ? $demos[ $demo ][ 'categories' ] : '';

			// Get required theme
			$required_theme	 = isset( $demos[ $demo ][ 'required_theme' ] ) ? $demos[ $demo ][ 'required_theme' ] : '';
			$required_theme_url	 = isset( $demos[ $demo ][ 'required_theme_url' ] ) ? $demos[ $demo ][ 'required_theme_url' ] : '';
			$theme			 = wp_get_theme();
			$item			 = $demos[ $demo ];
			$is_pro			 = !empty( $premium );
			$other_theme	 = $required_theme && !self::is_active_theme( $required_theme );
			$pro_action		 = self::pro_action( $premium, $gutenberg );
			?>

			<div class="envo-di-modal-head">
				<?php if ( !empty( $item[ 'screenshot' ] ) ) : ?>
					<img class="envo-di-modal-thumb" src="<?php echo esc_url( $item[ 'screenshot' ] ); ?>" alt="" />
				<?php endif; ?>
				<div class="envo-di-modal-heading">
					<span class="envo-di-kicker"><?php esc_html_e( 'Import demo', 'envo-extra' ); ?></span>
					<h2 class="title"><?php echo esc_html( $item[ 'demo_name' ] ); ?></h2>
					<div class="envo-di-badges">
						<?php if ( $is_pro ) : ?>
							<span class="envo-di-badge envo-di-badge-pro"><?php esc_html_e( 'PRO', 'envo-extra' ); ?></span>
						<?php else : ?>
							<span class="envo-di-badge envo-di-badge-free"><?php esc_html_e( 'Free', 'envo-extra' ); ?></span>
						<?php endif; ?>
						<?php if ( !empty( $item[ 'demo_link' ] ) ) : ?>
							<a class="envo-di-modal-preview" href="<?php echo esc_url( $item[ 'demo_link' ] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Live preview', 'envo-extra' ); ?><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
						<?php endif; ?>
					</div>
				</div>
				<ol class="envo-di-stepper">
					<li class="is-active" data-step="plugins"><span>1</span><?php esc_html_e( 'Plugins', 'envo-extra' ); ?></li>
					<li data-step="content"><span>2</span><?php esc_html_e( 'Content', 'envo-extra' ); ?></li>
					<li data-step="import"><span>3</span><?php esc_html_e( 'Import', 'envo-extra' ); ?></li>
				</ol>
			</div>

			<div id="envo-demo-plugins">

				<div class="envo-popup-text">

					<?php if ( !empty( $free ) || !empty( $premium ) ) : ?>
						<div class="envo-required-plugins-wrap">
							<h3><?php esc_html_e( 'Required plugins', 'envo-extra' ); ?></h3>
							<p><?php esc_html_e( 'The demo needs these plugins to look like the preview.', 'envo-extra' ); ?></p>
							<div class="envo-required-plugins oe-plugin-installer" data-group="required">
								<?php
								self::required_plugins( $free, 'free' );
								self::required_plugins( $premium, 'premium' );
								?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( !empty( $recommended ) ) : ?>
						<div class="envo-required-plugins-wrap">
							<h3><?php esc_html_e( 'Recommended plugins', 'envo-extra' ); ?></h3>
							<p><?php esc_html_e( 'Optional. Without them, some demo features (for example the shop) will not be included.', 'envo-extra' ); ?></p>
							<div class="envo-required-plugins oe-plugin-installer" data-group="recommended">
								<?php self::required_plugins( $recommended, 'recommended' ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $other_theme ) : ?>
						<div class="envo-di-callout">
							<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
							<p>
								<?php
								/* translators: %s: theme name with link */
								echo wp_kses( sprintf( __( 'This demo is made for the %s theme. The theme will be installed and activated after the import.', 'envo-extra' ), '<a href="' . esc_url( $required_theme_url ) . '" target="_blank" rel="noopener">' . esc_html( $required_theme ) . '</a>' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) );
								?>
							</p>
						</div>
					<?php endif; ?>

				</div>

				<div class="envo-di-modal-foot">
					<?php if ( $pro_action ) : ?>
						<p class="envo-di-foot-note"><?php echo esc_html( $pro_action[ 'note' ] ); ?></p>
						<?php if ( $pro_action[ 'url' ] ) : ?>
							<div class="envo-button envo-plugins-pro">
								<a href="<?php echo esc_url( $pro_action[ 'url' ] ); ?>" <?php echo $pro_action[ 'external' ] ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $pro_action[ 'label' ] ); ?></a>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<p class="envo-di-foot-note envo-di-missing" hidden>
							<?php esc_html_e( 'Install and activate the required plugins first.', 'envo-extra' ); ?>
							<a href="#" class="envo-di-skip"><?php esc_html_e( 'Continue anyway', 'envo-extra' ); ?></a>
						</p>
						<div class="envo-button envo-plugins-next">
							<a href="#"><?php esc_html_e( 'Next: choose content', 'envo-extra' ); ?><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a>
						</div>
					<?php endif; ?>
				</div>

			</div>

			<form method="post" id="envo-demo-import-form">

				<input id="envo_import_demo" type="hidden" name="envo_import_demo" value="<?php echo esc_attr( $demo ); ?>" />

				<div class="envo-demo-import-form-types envo-popup-text">

					<h3><?php esc_html_e( 'What do you want to import?', 'envo-extra' ); ?></h3>
					<p class="envo-di-backup-note"><span class="dashicons dashicons-backup" aria-hidden="true"></span><?php esc_html_e( 'Back up your site (database and files) before the import, so you can always go back.', 'envo-extra' ); ?></p>

					<ul class="envo-di-options">
						<li>
							<label for="envo_import_xml">
								<input id="envo_import_xml" type="checkbox" name="envo_import_xml" checked="checked" />
								<span class="envo-di-option-text">
									<strong><?php esc_html_e( 'Content', 'envo-extra' ); ?></strong>
									<small><?php esc_html_e( 'Pages, posts, products, images and menus', 'envo-extra' ); ?></small>
								</span>
							</label>
						</li>
						<li>
							<label for="envo_theme_settings">
								<input id="envo_theme_settings" type="checkbox" name="envo_theme_settings" checked="checked" />
								<span class="envo-di-option-text">
									<strong><?php esc_html_e( 'Customizer settings', 'envo-extra' ); ?></strong>
									<small><?php esc_html_e( 'Colors, fonts, header and layout options', 'envo-extra' ); ?></small>
								</span>
							</label>
						</li>
						<li>
							<label for="envo_import_widgets">
								<input id="envo_import_widgets" type="checkbox" name="envo_import_widgets" checked="checked"/>
								<span class="envo-di-option-text">
									<strong><?php esc_html_e( 'Widgets', 'envo-extra' ); ?></strong>
									<small><?php esc_html_e( 'Sidebar and footer widgets', 'envo-extra' ); ?></small>
								</span>
							</label>
						</li>
						<li class="envo-di-option-danger">
							<label for="envo_reset_content">
								<input id="envo_reset_content" type="checkbox" name="envo_reset_content" />
								<span class="envo-di-option-text">
									<strong><?php esc_html_e( 'Delete existing content first (optional)', 'envo-extra' ); ?></strong>
									<small><?php esc_html_e( 'Removes all posts, pages, products, media files, Elementor templates, menus and widgets, so only the demo content stays. Users, plugins, settings, orders and data of other plugins are kept.', 'envo-extra' ); ?></small>
								</span>
							</label>
						</li>
						<?php if ( $other_theme ) : ?>
							<li class="is-locked">
								<label for="envo_activate_enwoo">
									<input id="envo_activate_enwoo" type="checkbox" name="envo_activate_enwoo" checked="checked" disabled/>
									<span class="envo-di-option-text">
										<?php /* translators: %s: theme name */ ?>
										<strong><?php echo esc_html( sprintf( __( 'Activate the %s theme', 'envo-extra' ), $required_theme ) ); ?></strong>
										<small><?php esc_html_e( 'Required for this demo', 'envo-extra' ); ?></small>
									</span>
								</label>
							</li>
						<?php endif; ?>
					</ul>

					<div class="envo-di-warning" hidden>
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<p>
							<strong><?php esc_html_e( 'This cannot be undone.', 'envo-extra' ); ?></strong>
							<?php esc_html_e( 'All existing posts, pages, products and media files will be permanently deleted before the import. Make a backup of your site first.', 'envo-extra' ); ?>
						</p>
					</div>

				</div>

				<?php wp_nonce_field( 'envo_import_demo_data_nonce', 'envo_import_demo_data_nonce' ); ?>
				<div class="envo-di-modal-foot">
					<a href="#" class="envo-di-back"><span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span><?php esc_html_e( 'Back', 'envo-extra' ); ?></a>
					<input type="submit" name="submit" class="envo-button envo-import" value="<?php esc_attr_e( 'Import demo', 'envo-extra' ); ?>" data-label="<?php esc_attr_e( 'Import demo', 'envo-extra' ); ?>" data-label-reset="<?php esc_attr_e( 'Delete content and import', 'envo-extra' ); ?>" />
				</div>

			</form>

			<div class="envo-loader envo-popup-text">
				<h3><?php esc_html_e( 'Importing the demo…', 'envo-extra' ); ?></h3>
				<p class="envo-di-loader-note"><?php esc_html_e( 'This can take a few minutes. Please keep this window open.', 'envo-extra' ); ?></p>
				<div class="envo-di-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
				<div class="envo-import-status"></div>
			</div>

			<div class="envo-last">
				<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"></circle><path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"></path></svg>
				<h3><?php esc_html_e( 'Demo imported!', 'envo-extra' ); ?></h3>
				<p><?php esc_html_e( 'Your site now looks like the demo. Replace the texts and images with your own.', 'envo-extra' ); ?></p>
				<p class="envo-di-last-undo"><?php esc_html_e( 'Changed your mind? You can remove the imported content under "Imported demos" on this page.', 'envo-extra' ); ?></p>
				<div class="envo-di-last-actions">
					<a class="button button-primary button-hero" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your site', 'envo-extra' ); ?></a>
					<a class="button button-hero" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'Customize', 'envo-extra' ); ?></a>
				</div>
			</div>

			<?php
			die();
		}

		/**
		 * Is the given theme (name or slug) the active theme or its parent?
		 *
		 * @since 1.9.25
		 */
		public static function is_active_theme( $theme_name ) {
			$theme = wp_get_theme();
			$slug	 = sanitize_key( str_replace( ' ', '-', strtolower( $theme_name ) ) );
			return $theme->get( 'Name' ) === $theme_name || $theme->get_template() === $slug || $theme->get_stylesheet() === $slug;
		}

		/**
		 * Button shown instead of "Next" when a PRO plugin, its license or the block editor is missing.
		 *
		 * @since 1.9.25
		 */
		private static function pro_action( $premium, $categories ) {
			$slug = !empty( $premium[ 0 ][ 'slug' ] ) ? $premium[ 0 ][ 'slug' ] : '';
			if ( 'enwoo-pro' === $slug || 'envo-royal-pro' === $slug ) {
				$enwoo		 = 'enwoo-pro' === $slug;
				$name		 = $enwoo ? 'Enwoo PRO' : 'Envo Royal PRO';
				$constant	 = $enwoo ? 'ENWOO_PRO' : 'ENVO_ROYAL_PRO';
				if ( !defined( $constant . '_CURRENT_VERSION' ) && !empty( $premium[ 0 ][ 'init' ] ) && file_exists( WP_PLUGIN_DIR . '/' . $premium[ 0 ][ 'init' ] ) ) {
					return array(
						/* translators: %s: PRO plugin name */
						'note'		 => sprintf( __( 'Activate %s above to continue.', 'envo-extra' ), $name ),
						'label'		 => '',
						'url'		 => '',
						'external'	 => false,
					);
				}
				if ( !defined( $constant . '_CURRENT_VERSION' ) ) {
					return array(
						/* translators: %s: PRO plugin name */
						'note'		 => sprintf( __( 'This demo is part of %s.', 'envo-extra' ), $name ),
						/* translators: %s: PRO plugin name */
						'label'		 => sprintf( __( 'Get %s', 'envo-extra' ), $name ),
						'url'		 => $enwoo ? 'https://enwoo-wp.com/enwoo-pro/' : 'https://envothemes.com/product/envo-royal-pro/',
						'external'	 => true,
					);
				}
				if ( !defined( $constant . '_STATUS' ) ) {
					return array(
						/* translators: %s: PRO plugin name */
						'note'		 => sprintf( __( 'Activate your %s license to import this demo.', 'envo-extra' ), $name ),
						'label'		 => __( 'Activate license', 'envo-extra' ),
						'url'		 => network_admin_url( 'options-general.php?page=' . ( $enwoo ? 'enwoo' : 'envo-royal' ) . '-license-options' ),
						'external'	 => false,
					);
				}
				if ( 'expired' === constant( $constant . '_STATUS' ) ) {
					return array(
						/* translators: %s: PRO plugin name */
						'note'		 => sprintf( __( 'Your %s license has expired.', 'envo-extra' ), $name ),
						'label'		 => __( 'Renew license', 'envo-extra' ),
						'url'		 => $enwoo ? 'https://enwoo-wp.com/my-account/orders/' : 'https://envothemes.com/my-account/orders/',
						'external'	 => true,
					);
				}
			}
			if ( !envo_extra_is_gutenberg_active() && is_array( $categories ) && in_array( 'Gutenberg', $categories, true ) ) {
				return array(
					'note'		 => __( 'This demo is built with blocks, but the block editor is disabled on your site.', 'envo-extra' ),
					'label'		 => __( 'Enable the block editor', 'envo-extra' ),
					'url'		 => network_admin_url( 'options-writing.php#classic-editor-options' ),
					'external'	 => false,
				);
			}
			return false;
		}

		/**
		 * Required plugins.
		 *
		 * @since 1.4.5
		 */
		public static function required_plugins( $plugins, $return ) {

			if ( empty( $plugins ) || !is_array( $plugins ) ) {
				return;
			}

			foreach ( $plugins as $plugin ) {

				$api = array(
					'slug'	 => isset( $plugin[ 'slug' ] ) ? $plugin[ 'slug' ] : '',
					'init'	 => isset( $plugin[ 'init' ] ) ? $plugin[ 'init' ] : '',
					'name'	 => isset( $plugin[ 'name' ] ) ? $plugin[ 'name' ] : '',
					'notice' => isset( $plugin[ 'notice' ] ) ? $plugin[ 'notice' ] : '',
				);

				$installed = $api[ 'init' ] && file_exists( WP_PLUGIN_DIR . '/' . $api[ 'init' ] );

				if ( $installed && is_plugin_inactive( $api[ 'init' ] ) ) {
					// Installed but Inactive.
					$state			 = 'inactive';
					$button_classes	 = 'button activate-now button-primary';
					$button_text	 = esc_html__( 'Activate', 'envo-extra' );
				} elseif ( !$installed ) {
					// Not Installed.
					$state			 = 'missing';
					$button_classes	 = 'button install-now';
					$button_text	 = esc_html__( 'Install', 'envo-extra' );
				} else {
					// Active.
					$state			 = 'active';
					$button_classes	 = 'button disabled';
					$button_text	 = esc_html__( 'Active', 'envo-extra' );
				}
				?>

				<div class="envo-plugin envo-clr envo-plugin-<?php echo esc_attr( $api[ 'slug' ] ); ?> is-<?php echo esc_attr( $state ); ?>" data-slug="<?php echo esc_attr( $api[ 'slug' ] ); ?>" data-init="<?php echo esc_attr( $api[ 'init' ] ); ?>"<?php echo 'premium' === $return ? ' data-premium="1"' : ''; ?>>
					<span class="envo-plugin-icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<div class="envo-plugin-text">
						<h4><?php echo esc_html( $api[ 'name' ] ); ?><?php if ( 'premium' === $return ) : ?> <span class="envo-di-badge envo-di-badge-pro"><?php esc_html_e( 'PRO', 'envo-extra' ); ?></span><?php endif; ?></h4>
						<?php if ( $api[ 'notice' ] != '' ) { ?>
							<div class="envo-plugin-notice"><?php echo esc_html( $api[ 'notice' ] ); ?></div>
						<?php } ?>
					</div>

					<?php
					// If premium plugins and not installed
					if ( 'premium' == $return && !$installed ) {
						$url = 'enwoo-pro' === $api[ 'slug' ] ? 'https://enwoo-wp.com/enwoo-pro/' : 'https://envothemes.com/product/' . $api[ 'slug' ] . '/';
						?>
						<a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get this addon', 'envo-extra' ); ?></a>
					<?php } else { ?>
						<button type="button" class="<?php echo esc_attr( $button_classes ); ?>" data-init="<?php echo esc_attr( $api[ 'init' ] ); ?>" data-slug="<?php echo esc_attr( $api[ 'slug' ] ); ?>" data-name="<?php echo esc_attr( $api[ 'name' ] ); ?>" <?php disabled( 'active', $state ); ?>><?php echo esc_html( $button_text ); ?></button>
					<?php } ?>
				</div>

				<?php
			}
		}

			/**
			 * Required plugins activate
			 *
			 * @since 1.4.5
			 */
			public function ajax_required_plugins_activate() {

				check_ajax_referer( 'envo_import_data_nonce', 'security' );

				if ( !current_user_can( 'activate_plugins' ) || !isset( $_POST[ 'init' ] ) || !sanitize_text_field( wp_unslash( $_POST[ 'init' ] ) ) ) {
					wp_send_json_error(
					array(
						'success'	 => false,
						'message'	 => __( 'No plugin specified', 'envo-extra' ),
					)
					);
				}

				$plugin_init = sanitize_text_field( wp_unslash( $_POST[ 'init' ] ) );

				// Allow only plugins listed in the demos data
				$allowed = array();
				foreach ( self::get_demos_data() as $demo_data ) {
					if ( empty( $demo_data[ 'required_plugins' ] ) || !is_array( $demo_data[ 'required_plugins' ] ) ) {
						continue;
					}
					foreach ( $demo_data[ 'required_plugins' ] as $group ) {
						foreach ( (array) $group as $plugin ) {
							if ( !empty( $plugin[ 'init' ] ) ) {
								$allowed[] = $plugin[ 'init' ];
							}
						}
					}
				}
				if ( !in_array( $plugin_init, $allowed, true ) ) {
					wp_send_json_error(
					array(
						'success'	 => false,
						'message'	 => __( 'This plugin is not required by any demo.', 'envo-extra' ),
					)
					);
				}

				$activate	 = activate_plugin( $plugin_init, '', false, true );

				if ( is_wp_error( $activate ) ) {
					wp_send_json_error(
					array(
						'success'	 => false,
						'message'	 => $activate->get_error_message(),
					)
					);
				}

				wp_send_json_success(
				array(
					'success'	 => true,
					'message'	 => __( 'Plugin Successfully Activated', 'envo-extra' ),
				)
				);
			}

			/**
			 * Returns an array containing all the importable content
			 *
			 * @since 1.4.5
			 */
			/**
			 * Delete the existing content (posts, pages, products, media, menus, widgets) before the import.
			 * Users, plugins, settings and WooCommerce orders are kept. Runs in batches.
			 *
			 * @since 1.9.25
			 */
			public function ajax_reset_content() {
				self::verify_import_request();
				if ( !current_user_can( 'delete_others_posts' ) ) {
					wp_send_json_error( array( 'message' => __( 'This action was stopped for security purposes.', 'envo-extra' ) ), 403 );
				}
				// The demo must be importable (license of a PRO demo) before anything is deleted
				self::get_requested_demo();

				// Only the content a demo brings - never data of other plugins (orders, tickets, forms...)
				$post_types = array_values( array_filter( array( 'post', 'page', 'attachment', 'product', 'product_variation', 'nav_menu_item', 'elementor_library', 'wp_block' ), 'post_type_exists' ) );

				// Keep the WooCommerce pages (shop, cart, checkout, account) and the privacy policy page
				// and the Elementor kit (global colors and fonts - settings, not content)
				$keep = array( (int) get_option( 'wp_page_for_privacy_policy' ), (int) get_option( 'elementor_active_kit' ) );
				foreach ( array( 'shop', 'cart', 'checkout', 'myaccount', 'terms' ) as $wc_page ) {
					$keep[] = (int) get_option( 'woocommerce_' . $wc_page . '_page_id' );
				}
				$keep = array_values( array_filter( $keep ) );

				$started = time();
				do {
					$ids = get_posts( array(
						'post__not_in'		 => $keep,
						'meta_query'		 => array(
							'relation' => 'OR',
							array( 'key' => '_elementor_template_type', 'compare' => 'NOT EXISTS' ),
							array( 'key' => '_elementor_template_type', 'value' => 'kit', 'compare' => '!=' ),
						),
						'post_type'			 => $post_types,
						'post_status'		 => 'any',
						'posts_per_page'	 => 100,
						'fields'			 => 'ids',
						'orderby'			 => 'ID',
						'no_found_rows'		 => true,
					) );
					foreach ( $ids as $id ) {
						if ( 'attachment' === get_post_type( $id ) ) {
							wp_delete_attachment( $id, true );
						} else {
							wp_delete_post( $id, true );
						}
					}
				} while ( $ids && time() - $started < 20 );

				// Not finished - the browser calls again
				if ( $ids ) {
					wp_die( 'envo-reset-continue' );
				}

				// Menus and widgets
				foreach ( wp_get_nav_menus() as $menu ) {
					wp_delete_nav_menu( $menu->term_id );
				}
				set_theme_mod( 'nav_menu_locations', array() );

				$sidebars = get_option( 'sidebars_widgets', array() );
				if ( is_array( $sidebars ) ) {
					foreach ( $sidebars as $sidebar => $widgets ) {
						if ( is_array( $widgets ) && 'wp_inactive_widgets' !== $sidebar ) {
							$sidebars[ $sidebar ] = array();
						}
					}
					update_option( 'sidebars_widgets', $sidebars );
				}

				update_option( 'show_on_front', 'posts' );
				update_option( 'page_on_front', 0 );
				update_option( 'page_for_posts', 0 );

				// The content of the previous imports is gone
				self::save_history( array() );

				self::send_import_result( true );
			}

			public function ajax_get_import_data() {
				if ( !current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'This action was stopped for security purposes.', 'envo-extra' ) ), 403 );
				}
				check_ajax_referer( 'envo_import_data_nonce', 'security' );

				wp_send_json(
					array(
						array(
							'input_name' => 'envo_reset_content',
							'action'	 => 'envo_ajax_reset_content',
							'method'	 => 'ajax_reset_content',
							'loader'	 => esc_html__( 'Deleting existing content', 'envo-extra' )
						),
						array(
							'input_name' => 'envo_import_xml',
							'action'	 => 'envo_ajax_import_xml',
							'method'	 => 'ajax_import_xml',
							'loader'	 => esc_html__( 'Importing XML Data', 'envo-extra' )
						),
						array(
							'input_name' => 'envo_theme_settings',
							'action'	 => 'envo_ajax_import_theme_settings',
							'method'	 => 'ajax_import_theme_settings',
							'loader'	 => esc_html__( 'Importing Customizer Settings', 'envo-extra' )
						),
						array(
							'input_name' => 'envo_import_widgets',
							'action'	 => 'envo_ajax_import_widgets',
							'method'	 => 'ajax_import_widgets',
							'loader'	 => esc_html__( 'Importing Widgets', 'envo-extra' )
						),
						array(
							'input_name' => 'envo_activate_enwoo',
							'action'	 => 'envo_ajax_envo_activate_enwoo',
							'method'	 => 'ajax_activate_enwoo',
							'loader'	 => esc_html__( 'Activating Required Theme', 'envo-extra' )
						),
					)
				);
			}

			public function ajax_activate_enwoo() {
				self::verify_import_request( 'install_themes' );
				if ( !current_user_can( 'switch_themes' ) ) {
					wp_send_json_error( array( 'message' => __( 'This action was stopped for security purposes.', 'envo-extra' ) ), 403 );
				}

				// Get the selected demo
				list( $demo_type, $demo ) = self::get_requested_demo();
				self::remember_state( $demo_type, $demo );

				// Settings file
				$theme_settings = isset( $demo[ 'theme_settings' ] ) ? $demo[ 'theme_settings' ] : '';
				$required_theme	 = isset( $demo[ 'required_theme' ] ) ? $demo[ 'required_theme' ] : '';
				$required_theme = sanitize_key( str_replace( ' ', '-', strtolower( $required_theme ) ) );
				if ( '' === $required_theme ) {
					wp_send_json_error( array( 'message' => __( 'This demo does not require another theme.', 'envo-extra' ) ), 400 );
				}

				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				include_once ABSPATH . 'wp-admin/includes/theme.php';

				$api = themes_api(
					'theme_information', array(
						'slug'	 => $required_theme,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					$status[ 'errorMessage' ] = $api->get_error_message();
					wp_send_json_error( $status );
				}

				if ( !wp_get_theme( $required_theme )->exists() ) {
					$skin		 = new WP_Ajax_Upgrader_Skin();
					$upgrader	 = new Theme_Upgrader( $skin );
					$result		 = $upgrader->install( $api->download_link );
					if ( is_wp_error( $result ) || !$result ) {
						wp_send_json_error( array( 'errorMessage' => is_wp_error( $result ) ? $result->get_error_message() : __( 'The theme could not be installed.', 'envo-extra' ) ) );
					}
				}

				// Include settings importer
				include_once ENVO_PATH . 'includes/panel/classes/importers/class-settings-importer.php';

				switch_theme( $required_theme );
				self::remember_state( $demo_type, $demo );

				// Import settings.
				$settings_importer	 = new envo_Settings_Importer();
				self::send_import_result( $settings_importer->process_import_file( $theme_settings ) );
			}

			/**
			 * Import XML file
			 *
			 * @since 1.4.5
			 */
			public function ajax_import_xml() {
				self::verify_import_request();

				// Get the selected demo
				list( $demo_type, $demo ) = self::get_requested_demo();
				self::remember_state( $demo_type, $demo );

				// Content file
				$xml_file = isset( $demo[ 'xml_file' ] ) ? $demo[ 'xml_file' ] : '';

				// Move the default post and page to trash (only when they were not edited)
				$sample_page		 = get_page_by_path( 'sample-page', OBJECT, 'page' );
				$hello_world_post	 = get_page_by_path( 'hello-world', OBJECT, 'post' );

				foreach ( array( $sample_page, $hello_world_post ) as $default_post ) {
					if ( $default_post instanceof WP_Post && $default_post->post_date === $default_post->post_modified ) {
						wp_trash_post( $default_post->ID );
					}
				}

				// Import Posts, Pages, Images, Menus - and remember what was created
				add_action( 'wp_insert_post', array( __CLASS__, 'track_post' ), 10, 3 );
				add_action( 'add_attachment', array( __CLASS__, 'track_attachment' ) ); // attachments do not fire wp_insert_post
				add_action( 'created_term', array( __CLASS__, 'track_term' ), 10, 3 );
				self::$import_demo = $demo_type;
				add_filter( 'wp_import_terms', array( __CLASS__, 'rename_existing_menus' ) );
				$result = $this->process_xml( $xml_file );
				remove_filter( 'wp_import_terms', array( __CLASS__, 'rename_existing_menus' ) );
				remove_action( 'wp_insert_post', array( __CLASS__, 'track_post' ), 10 );
				remove_action( 'add_attachment', array( __CLASS__, 'track_attachment' ) );
				remove_action( 'created_term', array( __CLASS__, 'track_term' ), 10 );

				$history = self::get_history();
				if ( isset( $history[ $demo_type ] ) ) {
					$history[ $demo_type ][ 'posts' ]	 = array_values( array_unique( array_merge( $history[ $demo_type ][ 'posts' ], self::$tracked[ 'posts' ] ) ) );
					$history[ $demo_type ][ 'terms' ]	 = array_merge( $history[ $demo_type ][ 'terms' ], self::$tracked[ 'terms' ] );
					$history[ $demo_type ][ 'menus' ]	 = array_merge( isset( $history[ $demo_type ][ 'menus' ] ) ? $history[ $demo_type ][ 'menus' ] : array(), self::$tracked[ 'menus' ] );
					self::save_history( $history );
				}

				self::send_import_result( $result );
			}

			/**
			 * Import customizer settings
			 *
			 * @since 1.4.5
			 */
			public function ajax_import_theme_settings() {
				self::verify_import_request();

				// Include settings importer
				include_once ENVO_PATH . 'includes/panel/classes/importers/class-settings-importer.php';

				// Get the selected demo
				list( $demo_type, $demo ) = self::get_requested_demo();
				self::remember_state( $demo_type, $demo );

				// Settings file
				$theme_settings = isset( $demo[ 'theme_settings' ] ) ? $demo[ 'theme_settings' ] : '';

				// Import settings.
				$settings_importer	 = new envo_Settings_Importer();
				self::send_import_result( $settings_importer->process_import_file( $theme_settings ) );
			}

			/**
			 * Import widgets
			 *
			 * @since 1.4.5
			 */
			public function ajax_import_widgets() {
				self::verify_import_request();

				// Include widget importer
				include_once ENVO_PATH . 'includes/panel/classes/importers/class-widget-importer.php';

				// Get the selected demo
				list( $demo_type, $demo ) = self::get_requested_demo();
				self::remember_state( $demo_type, $demo );

				// Widgets file
				$widgets_file = isset( $demo[ 'widgets_file' ] ) ? $demo[ 'widgets_file' ] : '';

				// Import settings.
				$widgets_importer	 = new Envo_Widget_Importer();
				self::send_import_result( $widgets_importer->process_import_file( $widgets_file ) );
			}

			public function new_get_page_by_title( $page_title, $output = OBJECT, $post_type = 'page' ) {
				$args  = array(
					'title'                  => $page_title,
					'post_type'              => $post_type,
					'post_status'            => get_post_stati(),
					'posts_per_page'         => 1,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
					'no_found_rows'          => true,
					'orderby'                => 'post_date ID',
					'order'                  => 'ASC',
				);
				$query = new WP_Query( $args );
				$pages = $query->posts;

				if ( empty( $pages ) ) {
					return null;
				}

				return get_post( $pages[0], $output );
			}

			/**
			 * After import
			 *
			 * @since 1.4.5
			 */
			public function ajax_after_import() {
				self::verify_import_request();

				// Get the selected demo
				list( $demo_type, $demo ) = self::get_requested_demo();
				self::remember_state( $demo_type, $demo );

				// If XML file is imported
				if ( isset( $_POST[ 'envo_import_is_xml' ] ) && 'true' === $_POST[ 'envo_import_is_xml' ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verify_import_request()

					// Links to the demo site in the imported content and menus point to this site
					self::replace_demo_urls( $demo_type, $demo );

					// Elementor width setting
					$elementor_width = isset( $demo[ 'elementor_width' ] ) ? $demo[ 'elementor_width' ] : '';

					// Elementor experiment container
					$elementor_container = isset( $demo[ 'elementor_experiment-container' ] ) ? $demo[ 'elementor_experiment-container' ] : '';

					// Reading settings
					$homepage_title	 = isset( $demo[ 'home_title' ] ) ? $demo[ 'home_title' ] : 'Home';
					$blog_title		 = isset( $demo[ 'blog_title' ] ) ? $demo[ 'blog_title' ] : '';

					// Posts to show on the blog page
					$posts_to_show = isset( $demo[ 'posts_to_show' ] ) ? $demo[ 'posts_to_show' ] : '';

					// If shop demo
					$shop_demo = isset( $demo[ 'is_shop' ] ) ? $demo[ 'is_shop' ] : false;

					// Product image size
					$image_size		 = isset( $demo[ 'woo_image_size' ] ) ? $demo[ 'woo_image_size' ] : '';
					$thumbnail_size	 = isset( $demo[ 'woo_thumb_size' ] ) ? $demo[ 'woo_thumb_size' ] : '';
					$crop_width		 = isset( $demo[ 'woo_crop_width' ] ) ? $demo[ 'woo_crop_width' ] : '';
					$crop_height	 = isset( $demo[ 'woo_crop_height' ] ) ? $demo[ 'woo_crop_height' ] : '';

					// Assign WooCommerce pages if WooCommerce Exists
					if ( class_exists( 'WooCommerce' ) && true == $shop_demo ) {

						$woopages = array(
							'woocommerce_shop_page_id'				 => 'Shop',
							'woocommerce_cart_page_id'				 => 'Cart',
							'woocommerce_checkout_page_id'			 => 'Checkout',
							'woocommerce_pay_page_id'				 => 'Checkout &#8594; Pay',
							'woocommerce_thanks_page_id'			 => 'Order Received',
							'woocommerce_myaccount_page_id'			 => 'My Account',
							'woocommerce_edit_address_page_id'		 => 'Edit My Address',
							'woocommerce_view_order_page_id'		 => 'View Order',
							'woocommerce_change_password_page_id'	 => 'Change Password',
							'woocommerce_logout_page_id'			 => 'Logout',
							'woocommerce_lost_password_page_id'		 => 'Lost Password'
						);

						foreach ( $woopages as $woo_page_name => $woo_page_title ) {

							$woopage = $this->new_get_page_by_title( $woo_page_title );
							if ( isset( $woopage ) && $woopage->ID ) {
								update_option( $woo_page_name, $woopage->ID );
							}
						}

						// We no longer need to install pages
						delete_option( '_wc_needs_pages' );
						delete_transient( '_wc_activation_redirect' );

						// Get products image size
						update_option( 'woocommerce_single_image_width', absint( $image_size ) );
						update_option( 'woocommerce_thumbnail_image_width', absint( $thumbnail_size ) );
						update_option( 'woocommerce_thumbnail_cropping', 'custom' );
						update_option( 'woocommerce_thumbnail_cropping_custom_width', absint( $crop_width ) );
						update_option( 'woocommerce_thumbnail_cropping_custom_height', absint( $crop_height ) );
					}

					// Set imported menus to registered theme locations
					$locations	 = get_theme_mod( 'nav_menu_locations' );
					$menus		 = wp_get_nav_menus();

					if ( $menus ) {

						foreach ( $menus as $menu ) {

							if ( $menu->name == 'Main Menu' ) {
								$locations[ 'main_menu' ] = $menu->term_id;
							}
							if ( $menu->name == 'Main Menu Right' ) {
								$locations[ 'main_menu_right' ] = $menu->term_id;
							}
							if ( $menu->name == 'Categories Menu' ) {
								$locations[ 'main_menu_cats' ] = $menu->term_id;
							}
							if ( $menu->name == 'Homepage Main Menu' ) {
								$locations[ 'main_menu_home' ] = $menu->term_id;
							}
						}
					}

					// Set menus to locations
					set_theme_mod( 'nav_menu_locations', $locations );

					// Disable Elementor default settings
					//update_option( 'elementor_disable_color_schemes', 'yes' );
					//update_option( 'elementor_disable_typography_schemes', 'yes' );
					if ( !empty( $elementor_width ) ) {
						update_option( 'elementor_container_width', $elementor_width );
					}
					if ( !empty( $elementor_container ) ) {
						update_option( 'elementor_experiment-container', $elementor_container );
					}

					// Assign front page and posts page (blog page).
					$home_page	 = $this->new_get_page_by_title( $homepage_title );
					$blog_page	 = $this->new_get_page_by_title( $blog_title );

					update_option( 'show_on_front', 'page' );

					if ( is_object( $home_page ) ) {
						update_option( 'page_on_front', $home_page->ID );
					}

					if ( is_object( $blog_page ) ) {
						update_option( 'page_for_posts', $blog_page->ID );
					}

					// Posts to show on the blog page
					if ( !empty( $posts_to_show ) ) {
						update_option( 'posts_per_page', absint( $posts_to_show ) );
					}
				}

				die();
			}

			/**
			 * Import XML data
			 *
			 * @since 1.0.0
			 */
			public function process_xml( $file ) {

				$response = envo_Demos_Helpers::get_remote( $file );
				$theme		 = wp_get_theme();
				$url		 = 'https://envothemes.com/docs/docs/' . $theme->template . '/one-click-demo-import/import-failed-manual-import/';

				// No sample data found
				if ( $response === false ) {
					/* translators: %1$s: URL of the manual import guide */
					return new WP_Error( 'xml_import_error', sprintf(__( 'Can not retrieve sample data xml file. The server may be down at the moment please try again later or try manual import. How to: %1$s', 'envo-extra' ), $url ) );
				}

				// Write sample data content to temp xml file
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$temp_xml = wp_tempnam( 'envo-demo-content.xml' );
				file_put_contents( $temp_xml, $response ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				// If file exists lets import it
				if ( $temp_xml && file_exists( $temp_xml ) ) {
					$result = $this->import_xml( $temp_xml );
					wp_delete_file( $temp_xml );
					return $result;
				} else {
					// Import file can't be imported - we should die here since this is core for most people.
					/* translators: %1$s: URL of the manual import guide */
					return new WP_Error( 'xml_import_error', sprintf(__( 'The xml import file could not be accessed. Please try again or try manual import. How to: %1$s', 'envo-extra' ), $url ) );
				}
			}

			/**
			 * Import XML file
			 *
			 * @since 1.0.0
			 */
			private function import_xml( $file ) {

				// Make sure importers constant is defined
				if ( !defined( 'WP_LOAD_IMPORTERS' ) ) {
					define( 'WP_LOAD_IMPORTERS', true );
				}

				// Import file location
				$import_file = ABSPATH . 'wp-admin/includes/import.php';

				// Include import file
				if ( !file_exists( $import_file ) ) {
					return;
				}

				// Include import file
				require_once( $import_file );

				// Define error var
				$importer_error = false;

				if ( !class_exists( 'WP_Importer' ) ) {
					$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';

					if ( file_exists( $class_wp_importer ) ) {
						require_once $class_wp_importer;
					} else {
						$importer_error = __( 'Can not retrieve class-wp-importer.php', 'envo-extra' );
					}
				}

				if ( !class_exists( 'WP_Import' ) ) {
					$class_wp_import = ENVO_PATH . 'includes/panel/classes/importers/class-wordpress-importer.php';

					if ( file_exists( $class_wp_import ) ) {
						require_once $class_wp_import;
					} else {
						$importer_error = __( 'Can not retrieve wordpress-importer.php', 'envo-extra' );
					}
				}

				// Display error
				if ( $importer_error ) {
					return new WP_Error( 'xml_import_error', $importer_error );
				} else {

					// No error, lets import things...
					if ( !is_file( $file ) ) {
						$importer_error = __( 'Sample data file appears corrupt or can not be accessed.', 'envo-extra' );
						return new WP_Error( 'xml_import_error', $importer_error );
					} else {
						$importer					 = new WP_Import();
						$importer->fetch_attachments = true;
						$importer->import( $file );
					}
				}
			}

		}

	}
	new EnvoThemes_Demos();
