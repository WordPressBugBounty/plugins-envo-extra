<?php
/*
 * Plugin Name: Envo Extra
 * Plugin URI: https://envothemes.com/
 * Description: Extra addon for EnvoThemes Themes
 * Version: 1.9.25
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * Author: EnvoThemes
 * Author URI: https://envothemes.com/
 * License: GPL-2.0+
 * Text Domain: envo-extra
 * Domain Path: /languages
 */
// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !function_exists( 'add_action' ) ) {
	die( 'Nothing to do...' );
}
//define( 'KIRKI_TEST', true );
$plugin_data	 = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
$plugin_version	 = $plugin_data[ 'Version' ];
// Define WC_PLUGIN_FILE.
if ( !defined( 'ENVO_EXTRA_CURRENT_VERSION' ) ) {
	define( 'ENVO_EXTRA_CURRENT_VERSION', $plugin_version );
}

//plugin constants
define( 'ENVO_EXTRA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ENVO_EXTRA_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'ENVO_EXTRA_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

function envo_extra_is_gutenberg() {

	if ( function_exists( 'has_blocks' ) && has_blocks( get_the_ID() ) ) {
		return true;
	} else {
		return false;
	}
}

function envo_extra_scripts() {
	if ( envo_extra_is_gutenberg() ) {
		wp_enqueue_style( 'envo-extra-gutenberg', plugin_dir_url( __FILE__ ) . 'css/gutenberg.css', array(), ENVO_EXTRA_CURRENT_VERSION );
		// Layout fallbacks for classic themes without theme.json (themes with it lay out the blocks themselves).
		if ( ! function_exists( 'wp_theme_has_theme_json' ) || ! wp_theme_has_theme_json() ) {
			wp_enqueue_style( 'envo-extra-gutenberg-classic', plugin_dir_url( __FILE__ ) . 'css/gutenberg-classic.css', array( 'envo-extra-gutenberg' ), ENVO_EXTRA_CURRENT_VERSION );
		}
	}
	wp_enqueue_style( 'envo-extra', plugin_dir_url( __FILE__ ) . 'css/style.css', array(), ENVO_EXTRA_CURRENT_VERSION );
	wp_enqueue_script( 'envo-extra-js', plugin_dir_url( __FILE__ ) . 'js/envo.js', array( 'jquery' ), ENVO_EXTRA_CURRENT_VERSION, true );
}

add_action( 'wp_enqueue_scripts', 'envo_extra_scripts' );

//Dequeue Styles
function envo_extra_dequeue_unnecessary_styles() {
	$value = get_theme_mod( 'main_typographydesktop', array() );
	if ( isset( $value[ 'font-family' ] ) && !empty( $value[ 'font-family' ] ) ) {
		wp_dequeue_style( 'enwoo-fonts' );
		wp_deregister_style( 'enwoo-fonts' );
	}
}

add_action( 'wp_print_styles', 'envo_extra_dequeue_unnecessary_styles' );

/**
 * Return theme slug
 */
function envo_extra_theme() {
	$theme	 = get_template();
	$slug	 = str_replace( '-', '_', $theme );
	return $slug;
}

/**
 * Footer copyright function
 */
if ( !function_exists( 'envo_extra_text' ) ) {

	function envo_extra_text( $rewritetexts ) {

		$currentyear = wp_date( 'Y' );
		$copy		 = '&copy;';

		return str_replace(
		array( '%current_year%', '%copy%' ), array( $currentyear, $copy ), $rewritetexts
		);
	}

}

add_filter( 'envo_extra_footer_text', 'envo_extra_text' );

/**
 * Back to top
 */
function envo_extra_back_to_top() {
	if ( get_theme_mod( 'back_to_top_on_off', 'block' ) == 'block' ) {
		?>
		<!-- Return to Top -->
		<a href="#" id="return-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'envo-extra' ); ?>"><i class="las la-chevron-up" aria-hidden="true"></i></a>
		<?php
	}
}

/**
 * Footer extra actions - footer text and back to top
 */
function envo_extra_action() {
	remove_action( envo_extra_theme() . '_generate_footer', envo_extra_theme() . '_generate_construct_footer', 20 );
	add_action( envo_extra_theme() . '_generate_footer', 'envo_extra_generate_construct_footer' );
	add_action( 'wp_footer', 'envo_extra_back_to_top' );
	remove_theme_support( 'widgets-block-editor' );
}

/**
 * Footer footer text
 */
function envo_extra_generate_construct_footer() {
	if ( get_theme_mod( 'enwoo_custom_footer_on_off', '' ) == 'elementor' && get_theme_mod( 'enwoo_custom_footer', '' ) != '' && envo_extra_check_for_elementor() ) {
		$elementor_section_ID = absint( get_theme_mod( 'enwoo_custom_footer', '' ) );
		?>
		<footer id="colophon" class="elementor-footer-credits">
			<?php echo do_shortcode( '[elementor-template id="' . $elementor_section_ID . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</footer>
	<?php } elseif ( get_theme_mod( 'footer-credits', '' ) != '' ) { ?>
		<footer id="colophon" class="footer-credits container-fluid">
			<div class="container">
				<div class="footer-credits-text text-center">
					<div class="envo-credits-text">
						<?php echo wp_kses_post( apply_filters( 'envo_extra_footer_text', get_theme_mod( 'footer-credits', '' ) ) ); ?>
					</div>
					<?php if ( envo_extra_footer_credits_enabled() ) { ?>
						<?php echo wp_kses_post( get_site_option( 'et_fc' ) ); ?>
					<?php } ?>
				</div>
			</div>	
		</footer>   
	<?php } else { ?>
		<?php if ( envo_extra_footer_credits_enabled() ) { ?>
			<footer id="colophon" class="footer-credits container-fluid">
				<div class="container">
					<div class="footer-credits-text text-center">
						<?php echo wp_kses_post( get_site_option( 'et_fc' ) ); ?>
					</div>
				</div>	
			</footer>
		<?php } ?>
		<?php
	}
}

/**
 * Theme credit link in the footer is opt-in in the free version.
 * PRO plugins keep their own toggle (enabled by default).
 */
function envo_extra_footer_credits_enabled() {
	$default = ( envo_extra_check_for_pro( 'enwoo' ) || envo_extra_check_for_pro( 'envo-royal' ) ) ? 1 : 0;
	return 1 == get_theme_mod( 'enwoo_footer_credits_on_off', $default );
}

function envo_extra_recommended_plugins() {
	add_theme_support( 'recommend-plugins', array(
		'woocommerce'	 => array(
			'name'				 => 'WooCommerce',
			'active_filename'	 => 'woocommerce/woocommerce.php',
			/* translators: %s plugin name string */
			'description'		 => sprintf( esc_html__( 'To enable shop features, please install and activate the %s plugin.', 'envo-extra' ), '<strong>WooCommerce</strong>' ),
		),
		'elementor'		 => array(
			'name'				 => 'Elementor',
			'active_filename'	 => 'elementor/elementor.php',
			'description'		 => esc_html__( 'The most advanced frontend drag & drop page builder.', 'envo-extra' ),
		),
	) );
}

if ( !class_exists( 'Kirki' ) ) {
	include_once( plugin_dir_path( __FILE__ ) . 'include/kirki.php' );
}

/**
 * Remove Kirki telemetry
 */
function envo_extra_remove_kirki_module( $modules ) {
	unset( $modules[ 'telemetry' ] );
	unset( $modules[ 'gutenberg' ] );
	return $modules;
}

add_filter( 'kirki_modules', 'envo_extra_remove_kirki_module' );

/**
 * Add Kirki CSS into a file
 */
add_filter( 'kirki_output_inline_styles', function() {
	return true;
} );

/* Register the config */
Kirki::add_config( 'envo_extra', array(
	'capability'	 => 'edit_theme_options',
	'option_type'	 => 'theme_mod',
) );


/* Make the CSS of kirki tabs available after switch */
//add_filter('kirki_envo_extra_webfonts_skip_hidden', '__return_false', 99);
//add_filter('kirki_envo_extra_css_skip_hidden', '__return_false', 99);
//add_filter( 'kirki_dynamic_css_method', function() {
//    return 'file';
//} );
// Check if needed functions exists - if not, require them
if ( !function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

function envo_extra_check_plugin_active( $plugin_slug ) {
	if ( is_plugin_active( $plugin_slug ) ) {
		return true;
	}

	return false;
}

/**
 * Add Kirki custom controls.
 *
 * @param WP_Customize_Manager $wp_customize Instance of WP_Customize_Manager.
 */
function envo_extra_custom_customizer_control( $wp_customize ) {

	// Custom controls.

	require ENVO_EXTRA_PATH . '/controls/responsive-devices/control-responsive-devices.php';
	require ENVO_EXTRA_PATH . '/controls/responsive-devices/control-responsive-devices-typography.php';
}

if ( !function_exists( 'envo_extra_dashboard' ) ) {

	function envo_extra_dashboard() {

		require_once( plugin_dir_path( __FILE__ ) . 'lib/admin/dashboard.php' );
	}

}

function envo_extra_functions() {
	require_once( plugin_dir_path( __FILE__ ) . 'options/extra.php' );
}
add_action( 'after_setup_theme', 'envo_extra_functions' );

/**
 * Return slug of the supported active theme (enwoo, envo-royal) or empty string.
 */
function envo_extra_supported_theme() {
	$theme = wp_get_theme();
	if ( 'Enwoo' == $theme->name || 'enwoo' == $theme->template ) {
		return 'enwoo';
	}
	if ( 'Envo Royal' == $theme->name || 'envo-royal' == $theme->template ) {
		return 'envo-royal';
	}
	return '';
}

if ( envo_extra_supported_theme() ) {

	add_action( 'customize_register', 'envo_extra_custom_customizer_control' );

	function envo_extra_check_for_woocommerce() {
		$slug	 = envo_extra_supported_theme();
		$path	 = plugin_dir_path( __FILE__ );

		require_once( $path . 'lib/admin/metabox.php' );
		if ( !envo_extra_check_for_pro( $slug ) ) {
			Kirki::add_section( 'pro', array(
				'title'			 => esc_html__( 'Unlock More Options', 'envo-extra' ),
				'type'			 => 'link',
				'button_text'	 => 'enwoo' === $slug ? esc_html__( 'Enwoo PRO', 'envo-extra' ) : esc_html__( 'Envo Royal PRO', 'envo-extra' ),
				'button_url'	 => 'enwoo' === $slug ? 'https://enwoo-wp.com/enwoo-pro/' : 'https://envothemes.com/product/envo-royal-pro/',
				'priority'		 => 1,
			) );
			require_once( $path . 'options/' . $slug . '/pro.php' );
			require_once( $path . 'lib/envothemes-demo-import/includes/panel/' . ( 'enwoo' === $slug ? 'pro-demos.php' : 'envo-royal-pro-demos.php' ) );
		}

		Kirki::add_panel( 'envo_theme_panel', array(
			'title'		 => esc_attr__( 'Theme Options', 'envo-extra' ),
			'priority'	 => 5,
		) );

		$options = array( 'top-bar', 'header', 'main-menu', 'footer-credits', 'footer-widgets', 'main-colors', 'posts-pages', 'sidebar', 'back-to-top', 'custom-codes' );
		array_unshift( $options, 'enwoo' === $slug ? 'site-width' : 'color-preset' );
		foreach ( $options as $option ) {
			require_once( $path . 'options/' . $slug . '/' . $option . '.php' );
		}
		if ( defined( 'WC_VERSION' ) ) {
			require_once( $path . 'options/' . $slug . '/woocommerce.php' );
			require_once( $path . 'lib/woocommerce.php' );
		}
	}

	add_action( 'after_setup_theme', 'envo_extra_check_for_woocommerce' );

	add_action( 'init', 'envo_extra_dashboard' );
	add_action( 'init', 'envo_extra_recommended_plugins' );

	add_filter( 'use_widgets_block_editor', '__return_false' );

	add_action( 'after_setup_theme', 'envo_extra_action', 0 );
}

// Deactivate 3rd party plugin
function envo_extra_deactivate_demo_import() {
	if ( is_plugin_active( 'envothemes-demo-import/envothemes-demo-import.php' ) && current_user_can( 'activate_plugins' ) ) {
		deactivate_plugins( 'envothemes-demo-import/envothemes-demo-import.php' );
	}
}

add_action( 'admin_init', 'envo_extra_deactivate_demo_import' );

add_action( 'plugins_loaded', 'envo_extra_plugin_load' );

function envo_extra_plugin_load() {
	if ( !in_array( 'envothemes-demo-import/envothemes-demo-import.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		require_once( plugin_dir_path( __FILE__ ) . 'lib/envothemes-demo-import/envothemes-demo-import.php' );
	}
}

add_action( 'customize_register', 'envo_extra_theme_customize_register', 99 );

function envo_extra_fonts() {
    if (envo_extra_check_for_pro('enwoo') || envo_extra_check_for_pro('envo-royal')) {
        $fonts = array();
    } else {
        $fonts = array(
                'google' => array(
                    'Roboto',
                    'Open Sans',
                    'Lato',
                    'Roboto Condensed',
                    'Slabo 27px',
                    'Montserrat',
                    'Oswald',
                    'Source Sans Pro',
                    'Raleway',
                    'Merriweather',
                ),
            );
    }
    return $fonts;
}
add_action( 'admin_init', 'envo_extra_fonts' );

function envo_extra_theme_customize_register( $wp_customize ) {

	$wp_customize->remove_control( 'header_textcolor' );

	// relocating default background color
	$wp_customize->get_control( 'background_color' )->section = 'background_image';
}

function envo_extra_get_meta( $name = '', $output = '' ) {
	if ( is_singular( array( 'post', 'page' ) ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
		global $post;
		if ( ( function_exists( 'is_shop' ) && is_shop() ) ) {
			$post_id = get_option( 'woocommerce_shop_page_id' );
			;
		} else {
			$post_id = $post->ID;
		}
		$meta = get_post_meta( $post_id, 'envo_extra_meta_' . $name, true );
		if ( isset( $meta ) && $meta != '' ) {
			if ( $output == 'echo' ) {
				echo esc_html( $meta );
			} else {
				return $meta;
			}
		} else {
			return;
		}
	}
}

if ( !function_exists( 'envo_extra_widget_date_comments' ) ) :

	/**
	 * Returns date for widgets.
	 */
	function envo_extra_widget_date_comments() {
		?>
		<span class="extra-posted-date">
		<?php echo esc_html( get_the_date() ); ?>
		</span>
		<span class="extra-comments-meta">
			<?php
			if ( !comments_open() ) {
				esc_html_e( 'Off', 'envo-extra' );
			} else {
				?>
				<?php /* translators: %s: post title */ ?>
				<a href="<?php echo esc_url( get_comments_link() ); ?>" rel="nofollow" title="<?php echo esc_attr( sprintf( __( 'Comment on %s', 'envo-extra' ), get_the_title() ) ); ?>">
				<?php echo absint( get_comments_number() ); ?>
				</a>
				<?php } ?>
			<i class="fa fa-comments-o"></i>
		</span>
		<?php
	}

endif;

/**
 * Check Elementor plugin
 */
function envo_extra_check_for_elementor() {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	return is_plugin_active( 'elementor/elementor.php' );
}

/**
 * Check Elementor PRO plugin
 */
function envo_extra_check_for_elementor_pro() {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	return is_plugin_active( 'elementor-pro/elementor-pro.php' );
}

/**
 * Register Elementor features
 */
if ( envo_extra_check_for_elementor() ) {
	if ( !envo_extra_check_for_elementor_pro() ) {
		include_once( plugin_dir_path( __FILE__ ) . 'lib/elementor/shortcode.php' );
	}
}

include_once( plugin_dir_path( __FILE__ ) . 'lib/elementor/widgets.php' );

function envo_extra_activate_fc() {
	$arr	 = array( '' );
	$slug	 = envo_extra_supported_theme();
	if ( 'enwoo' === $slug ) {
		$arr = array(
			'Created with <a href="https://enwoo-wp.com/free-woocommerce-theme/" title="Free WooCommerce WordPress Theme">Enwoo</a> WordPress theme',
			'Created with <a href="https://enwoo-wp.com/free-business-wp-theme/" title="Free Business WordPress Theme">Enwoo</a> WordPress theme',
			'Created with <a href="https://enwoo-wp.com/" title="Free Multipurpose WordPress Theme">Enwoo</a> WordPress theme',
		);
	} elseif ( 'envo-royal' === $slug ) {
		$arr = array(
			'Created with <a href="https://envothemes.com/envo-royal-free-wp-theme/" title="Free Multipurpose WordPress Theme">Envo Royal</a> WordPress theme',
		);
	}

	$key = array_rand( $arr );

	update_site_option( 'et_fc', $arr[ $key ] );
}

add_action( 'after_switch_theme', 'envo_extra_activate_fc' );
register_activation_hook( __FILE__, 'envo_extra_activate_fc' );



register_activation_hook( __FILE__, 'envo_extra_plugin_activate' );
add_action( 'admin_init', 'envo_extra_plugin_redirect' );
add_action( 'after_switch_theme', 'envo_extra_theme_redirect' );

function envo_extra_plugin_activate() {
	add_option( 'envo_plugin_do_activation_redirect', true );
}

/**
 * Check PRO plugin
 */
function envo_extra_check_for_pro( $plugin ) {
	return is_plugin_active( $plugin . '-pro/' . $plugin . '-pro.php' );
}

/**
 * Redirect after plugin activation
 */
function envo_extra_plugin_redirect() {
	if ( get_option( 'envo_plugin_do_activation_redirect', false ) ) {
		delete_option( 'envo_plugin_do_activation_redirect' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( !is_network_admin() && !isset( $_GET[ 'activate-multi' ] ) && !wp_doing_ajax() && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'themes.php?page=envothemes-panel-install-demos' ) );
			exit;
		}
	}
}

/**
 * Redirect after theme activation
 */
function envo_extra_theme_redirect() {
	// Only themes with demos to import
	if ( !class_exists( 'EnvoThemes_Demos' ) || !EnvoThemes_Demos::get_demos_data() ) {
		return;
	}
	// Not during AJAX (e.g. the demo import switches the theme and continues with further requests)
	if ( !is_network_admin() && !wp_doing_ajax() && !wp_doing_cron() && current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( admin_url( 'themes.php?page=envothemes-panel-install-demos' ) );
		exit;
	}
}

/**
 * Adjust customizer preview.
 */
function envo_extra_customizer_responsive_sizes() {

		$medium_breakpoint	 = 990;
		$mobile_breakpoint	 = 480;

		$tablet_margin_left	 = -$medium_breakpoint / 2 . 'px';
		$tablet_width		 = $medium_breakpoint . 'px';

		$mobile_margin_left	 = -$mobile_breakpoint / 2 . 'px';
		$mobile_width		 = $mobile_breakpoint . 'px';
		?>

	<style>
		.wp-customizer .preview-tablet .wp-full-overlay-main {
			margin-left: <?php echo esc_attr( $tablet_margin_left ); ?>;
			width: <?php echo esc_attr( $tablet_width ); ?>;
		}
		.wp-customizer .preview-mobile .wp-full-overlay-main {
			margin-left: <?php echo esc_attr( $mobile_margin_left ); ?>;
			width: <?php echo esc_attr( $mobile_width ); ?>;
			height: 680px;
		}
	</style>

	<?php
}

add_action( 'customize_controls_print_styles', 'envo_extra_customizer_responsive_sizes' );

/**
 * Enqueue customizer scripts & styles.
 */
function envo_extra_customizer_scripts_styles() {

	wp_enqueue_style( 'responsive-controls', ENVO_EXTRA_PLUGIN_URL . 'css/responsive-controls.css', '', ENVO_EXTRA_CURRENT_VERSION );
	wp_enqueue_script( 'responsive-controls', ENVO_EXTRA_PLUGIN_URL . 'js/responsive-controls.js', array( 'jquery' ), ENVO_EXTRA_CURRENT_VERSION, true );
}

add_action( 'customize_controls_print_styles', 'envo_extra_customizer_scripts_styles' );

add_filter( 'body_class', 'envo_extra_body_classes' );

/**
 * Current header layout (busnav/woonav).
 */
function envo_extra_header_layout() {
	if ( 'enwoo' === envo_extra_supported_theme() ) {
		return get_theme_mod( 'header_layout', envo_extra_check_plugin_active( 'woocommerce/woocommerce.php' ) ? 'woonav' : 'busnav' );
	}
	return get_theme_mod( 'header_layout', 'busnav' );
}

function envo_extra_body_classes( $classes ) {

	$header = envo_extra_header_layout();
	if ( $header != '' ) {
		$classes[] = 'header-' . sanitize_html_class( $header );
		if ( envo_extra_check_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$classes[] = 'woo-on';
		}
	}

	if ( is_archive() || is_home() )
		return $classes;

	if ( envo_extra_is_gutenberg() ) {
		$classes[] = 'gutenberg-on';
	}
	$title = get_post_meta( get_the_ID(), 'envo_extra_hide_title', true );
	if ( $title == 'on' ) {
		$classes[] = 'title-off';
	}
	$sidebar = get_post_meta( get_the_ID(), 'envo_extra_hide_sidebar', true );
	if ( $sidebar == 'on' ) {
		$classes[] = 'sidebar-off';
	}
	$transparent_header = get_post_meta( get_the_ID(), 'envo_extra_transparent_header', true );
	if ( $transparent_header == 'on' ) {
		$classes[] = 'transparent-header';
	}

	return $classes;
}

/**
 * Add custom CSS styles
 */
function envo_extra_enqueue_header_css() {

	if ( !is_singular() ) {
		return;
	}

	$css = '';

	$transparent_header			 = get_post_meta( get_the_ID(), 'envo_extra_transparent_header', true );
	$transparent_header_color	 = sanitize_hex_color( get_post_meta( get_the_ID(), 'envo_extra_header_text_color', true ) );
	$header						 = envo_extra_header_layout();
	if ( $transparent_header == 'on' && $transparent_header_color ) {
		if ( $header == 'busnav' ) {
			$css .= '.transparent-header .site-header.business-heading:not(.shrink), .transparent-header .site-header.business-heading:not(.shrink) .navbar-default .navbar-nav > li > a, .transparent-header .site-header.business-heading:not(.shrink) a.cart-contents i, .transparent-header .site-header.business-heading:not(.shrink) .header-my-account a, .transparent-header .site-header.business-heading:not(.shrink) .header-wishlist a, .transparent-header .site-header.business-heading:not(.shrink) .header-compare a, .transparent-header .site-header.business-heading:not(.shrink) .header-search a, .transparent-header .site-header.business-heading:not(.shrink) .site-branding-text h1.site-title a, .transparent-header .site-header.business-heading:not(.shrink) .site-branding-text .site-title a, .transparent-header .site-header.business-heading:not(.shrink) #site-navigation .navbar-nav > li > a, .transparent-header .site-header.business-heading:not(.shrink) p.site-description {color: ' . $transparent_header_color . ';}';
			$css .= '.transparent-header .site-header.business-heading:not(.shrink) .hc-nav-trigger span,.transparent-header .site-header.business-heading:not(.shrink) .hc-nav-trigger span:before,.transparent-header .site-header.business-heading:not(.shrink) .hc-nav-trigger span:after {background-color: ' . $transparent_header_color . ';}';
		} elseif ( $header == 'woonav' ) {
			$css .= '.transparent-header #second-site-navigation, .transparent-header #second-site-navigation .navbar-default .navbar-nav > li > a, .transparent-header #second-site-navigation a.cart-contents i, .transparent-header #second-site-navigation .header-my-account a, .transparent-header #second-site-navigation .header-wishlist a, .transparent-header #second-site-navigation .header-compare a, .transparent-header #second-site-navigation .header-search a, .transparent-header #second-site-navigation .site-branding-text h1.site-title a, .transparent-header #second-site-navigation .site-branding-text .site-title a, .transparent-header #second-site-navigation #site-navigation .navbar-nav > li > a, .transparent-header #second-site-navigation p.site-description {color: ' . $transparent_header_color . ';}';
			$css .= '.transparent-header #second-site-navigation .hc-nav-trigger span,.transparent-header #second-site-navigation .hc-nav-trigger span:before,.transparent-header #second-site-navigation .hc-nav-trigger span:after {color: ' . $transparent_header_color . ';}';
		}
	}

	if ( $css ) {
		wp_add_inline_style( 'envo-extra', $css );
	}
}

add_action( 'wp_enqueue_scripts', 'envo_extra_enqueue_header_css', 9999 );

function envo_extra_is_gutenberg_active() {
	$gutenberg		 = false;
	$block_editor	 = false;

	if ( has_filter( 'replace_editor', 'gutenberg_init' ) ) {
		// Gutenberg is installed and activated.
		$gutenberg = true;
	}

	if ( version_compare( $GLOBALS[ 'wp_version' ], '5.0-beta', '>' ) ) {
		// Block editor.
		$block_editor = true;
	}

	if ( !$gutenberg && !$block_editor ) {
		return false;
	}

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	if ( !is_plugin_active( 'classic-editor/classic-editor.php' ) ) {
		return true;
	}

	if ( is_plugin_active( 'classic-editor/classic-editor.php' ) && get_option( 'classic-editor-replace' ) === 'block' ) {
		return true;
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'envo_action_links' );

function envo_action_links( $links ) {
	$links[ 'install_demos' ]	 = sprintf( '<a href="%1$s" class="install-demos">%2$s</a>', esc_url( admin_url( 'themes.php?page=envothemes-panel-install-demos' ) ), esc_html__( 'Install Demos', 'envo-extra' ) );
	$slug					 = envo_extra_supported_theme();
	if ( 'enwoo' === $slug ) {
		$url = 'https://enwoo-wp.com/enwoo-pro/';
	} elseif ( 'envo-royal' === $slug ) {
		$url = 'https://envothemes.com/product/envo-royal-pro/';
	} else {
		$url = 'https://envothemes.com/';
	}
	if ( !$slug || !envo_extra_check_for_pro( $slug ) ) {
		$links[ 'go_pro' ] = sprintf( '<a href="%1$s" target="_blank" rel="noopener" class="elementor-plugins-gopro">%2$s</a>', esc_url( $url ), esc_html__( 'Go Pro', 'envo-extra' ) );
	}
	return $links;
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
