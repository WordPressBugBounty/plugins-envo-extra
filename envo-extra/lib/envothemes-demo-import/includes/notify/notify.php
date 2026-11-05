<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outdated PRO plugins - the only notice besides the Envo Blocks one.
 */
function envo_extra_check_pro_versions() {
	if (
	(defined('ENVO_ROYAL_PRO_CURRENT_VERSION') && version_compare(ENVO_ROYAL_PRO_CURRENT_VERSION, '1.1.1', '<')  ) ||
	(defined('ENTR_PRO_CURRENT_VERSION') && version_compare(ENTR_PRO_CURRENT_VERSION, '1.3.7', '<') ) ||
	(defined('ENVO_STOREFRONT_PRO_CURRENT_VERSION') && version_compare(ENVO_STOREFRONT_PRO_CURRENT_VERSION, '1.8.4', '<') ) ||
	(defined('ENVO_SHOP_PRO_CURRENT_VERSION') && version_compare(ENVO_SHOP_PRO_CURRENT_VERSION, '1.9.10', '<') ) ||
	(defined('ENVO_ONLINE_STORE_PRO_CURRENT_VERSION') && version_compare(ENVO_ONLINE_STORE_PRO_CURRENT_VERSION, '1.8.4', '<') ) ||
	(defined('ENVO_SHOPPER_PRO_CURRENT_VERSION') && version_compare(ENVO_SHOPPER_PRO_CURRENT_VERSION, '1.9.4', '<') ) ||
	(defined('ENVO_MARKETPLACE_PRO_CURRENT_VERSION') && version_compare(ENVO_MARKETPLACE_PRO_CURRENT_VERSION, '1.9.4', '<') ) ||
	(defined('ENWOO_PRO_CURRENT_VERSION') && version_compare(ENWOO_PRO_CURRENT_VERSION, '1.6.3', '<') ) ||
	(defined('ENVO_ECOMMERCE_PRO_CURRENT_VERSION') && version_compare(ENVO_ECOMMERCE_PRO_CURRENT_VERSION, '2.2.4', '<')  ) ||
	(defined('ENVO_MAGAZINE_PRO_CURRENT_VERSION') && version_compare(ENVO_MAGAZINE_PRO_CURRENT_VERSION, '2.1.1', '<')  )
	) {
		add_action('admin_notices', 'envo_extra_admin_notice_update_pro');
	}
}

add_action('admin_init', 'envo_extra_check_pro_versions');

function envo_extra_admin_notice_update_pro(){
	if (!current_user_can('update_plugins')) {
		return;
	}

	$theme = wp_get_theme();
    $themetemplate = $theme->template;
	if ($themetemplate == 'entr' || $themetemplate == 'envo-one' ) {
		$themetemplate = 'envo';
	}
	if ($themetemplate == 'enwoo') {
		$changelogurl = 'https://enwoo-wp.com/' . $themetemplate . '-pro-changelog/';
		$updateurl = 'https://enwoo-wp.com/how-to-update-plugin/';
	}  else {
		$changelogurl = 'https://envothemes.com/' . $themetemplate . '-pro-changelog/';
		$updateurl = 'https://envothemes.com/how-to-update-plugin/';
	}

	/* translators: %1$s: PRO plugin name, %2$s and %3$s: strong tags, %4$s: theme name */
	$message = sprintf( __( '%1$s requires an %2$supdate%3$s. Please update the plugin to ensure full compatibility with the %4$s theme and WordPress.', 'envo-extra' ), '<strong>' . ucwords(str_replace('-', ' ',  $themetemplate)) . ' PRO</strong>','<strong>', '</strong>', '<strong>' . ucwords(str_replace('-', ' ',  $themetemplate)) . '</strong>' );
	$button_text = __( 'Update', 'envo-extra' );

	$button = '<p><a href="' . esc_url(admin_url( 'update-core.php?force-check=1')) . '" class="button-secondary">' . esc_html($button_text) . '</a><a href="' . esc_url($changelogurl) . '" target="_blank" class="envo-link-changelog" style="margin-left:10px;margin-top: 4px;display: inline-block;">' . esc_html__('Changelog', 'envo-extra') . '</a><a href="' . esc_url($updateurl) . '" target="_blank" class="envo-link" style="margin-left:10px;margin-top: 4px;display: inline-block;">' . esc_html__('How to update?', 'envo-extra') . '</a></p>';
	printf( '<div class="error"><p>%1$s</p>%2$s</div>', wp_kses_post( $message ), wp_kses_post( $button ) );
}

/**
 * Envo Blocks notice: state of the plugin (missing, inactive) or false when it should not be shown.
 *
 * @since 1.9.25
 */
function envo_extra_envo_blocks_state() {
	if ( !function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( 'envo-blocks/envo-blocks.php' ) || is_plugin_active( 'envo-blocks-pro/envo-blocks-pro.php' ) ) {
		return false;
	}
	// Envo Blocks needs WordPress 6.7 and PHP 7.4
	if ( version_compare( get_bloginfo( 'version' ), '6.7', '<' ) || version_compare( PHP_VERSION, '7.4', '<' ) ) {
		return false;
	}
	$installed = file_exists( WP_PLUGIN_DIR . '/envo-blocks/envo-blocks.php' );
	if ( !current_user_can( $installed ? 'activate_plugins' : 'install_plugins' ) ) {
		return false;
	}
	if ( get_user_meta( get_current_user_id(), 'envo_extra_envo_blocks_dismissed', true ) ) {
		return false;
	}
	return $installed ? 'inactive' : 'missing';
}

/**
 * Envo Blocks notice.
 *
 * @since 1.9.25
 */
function envo_extra_envo_blocks_notice() {
	$screen = get_current_screen();
	if ( $screen && ( $screen->is_block_editor() || in_array( $screen->id, array( 'update', 'plugin-install', 'appearance_page_envothemes-panel-install-demos' ), true ) ) ) {
		return;
	}
	$state = envo_extra_envo_blocks_state();
	if ( !$state ) {
		return;
	}
	$screens = ENVO_URL . 'img/envo-blocks-screens.webp';
	?>
	<div class="notice envo-eb-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'envo_extra_envo_blocks' ) ); ?>">
		<div class="envo-eb-content">
			<span class="envo-eb-badge"><?php esc_html_e( 'New · Free plugin', 'envo-extra' ); ?></span>
			<h2><?php esc_html_e( 'Build a beautiful WooCommerce store with blocks', 'envo-extra' ); ?></h2>
			<p><?php esc_html_e( 'Envo Blocks adds ready-made store pages, product, shop and cart layouts and WooCommerce blocks to the block editor – no page builder needed.', 'envo-extra' ); ?></p>
			<ul class="envo-eb-features">
				<li><span class="dashicons dashicons-layout" aria-hidden="true"></span><?php esc_html_e( '12 store templates, one-click import', 'envo-extra' ); ?></li>
				<li><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php esc_html_e( '33 product, shop, cart & checkout layouts', 'envo-extra' ); ?></li>
				<li><span class="dashicons dashicons-block-default" aria-hidden="true"></span><?php esc_html_e( '36 blocks: filters, wishlist, side cart, PDF invoices…', 'envo-extra' ); ?></li>
			</ul>
			<div class="envo-eb-actions">
				<button type="button" class="envo-eb-install" data-state="<?php echo esc_attr( $state ); ?>" data-busy="<?php echo esc_attr( 'missing' === $state ? __( 'Installing…', 'envo-extra' ) : __( 'Activating…', 'envo-extra' ) ); ?>" data-error="<?php esc_attr_e( 'Something went wrong. Install Envo Blocks under Plugins → Add New.', 'envo-extra' ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<span class="envo-eb-install-label"><?php echo 'missing' === $state ? esc_html__( 'Install Envo Blocks – free', 'envo-extra' ) : esc_html__( 'Activate Envo Blocks', 'envo-extra' ); ?></span>
				</button>
				<a class="envo-eb-more" href="https://wordpress.org/plugins/envo-blocks/" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'envo-extra' ); ?></a>
				<span class="envo-eb-status" aria-live="polite"></span>
			</div>
		</div>
		<div class="envo-eb-visual" aria-hidden="true">
			<img src="<?php echo esc_url( $screens ); ?>" alt="" width="854" height="500" loading="lazy" />
		</div>
		<button type="button" class="envo-eb-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'envo-extra' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
	</div>
	<script>
		( function ( $ ) {
			var $notice = $( '.envo-eb-notice' ),
				nonce = $notice.data( 'nonce' );

			$notice.on( 'click', '.envo-eb-dismiss', function () {
				$notice.fadeOut( 200 );
				$.post( ajaxurl, { action: 'envo_extra_envo_blocks_dismiss', security: nonce } );
			} );

			$notice.on( 'click', '.envo-eb-install', function () {
				var $button = $( this ),
					$label = $button.find( '.envo-eb-install-label' ),
					label = $label.text(),
					$status = $notice.find( '.envo-eb-status' ).removeClass( 'is-error' ).text( '' ),
					failed = function ( message ) {
						$status.addClass( 'is-error' ).text( message || $button.data( 'error' ) );
						$button.prop( 'disabled', false ).removeClass( 'is-busy' );
						$label.text( label );
					};

				$button.prop( 'disabled', true ).addClass( 'is-busy' );
				$label.text( $button.data( 'busy' ) );

				$.post( ajaxurl, { action: 'envo_extra_envo_blocks_install', security: nonce } ).done( function ( response ) {
					if ( response && response.success ) {
						window.location.href = response.data.redirect;
					} else {
						failed( response && response.data && response.data.message );
					}
				} ).fail( function () {
					failed();
				} );
			} );
		} )( jQuery );
	</script>
	<?php
}

add_action( 'admin_notices', 'envo_extra_envo_blocks_notice' );

/**
 * Dismiss the Envo Blocks notice for the current user.
 */
function envo_extra_envo_blocks_dismiss() {
	check_ajax_referer( 'envo_extra_envo_blocks', 'security' );
	update_user_meta( get_current_user_id(), 'envo_extra_envo_blocks_dismissed', time() );
	wp_send_json_success();
}

add_action( 'wp_ajax_envo_extra_envo_blocks_dismiss', 'envo_extra_envo_blocks_dismiss' );

/**
 * Install (from WordPress.org) and activate Envo Blocks.
 */
function envo_extra_envo_blocks_install() {
	check_ajax_referer( 'envo_extra_envo_blocks', 'security' );

	$plugin = 'envo-blocks/envo-blocks.php';

	if ( !file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		if ( !current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to install plugins on this site.', 'envo-extra' ) ), 403 );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = plugins_api( 'plugin_information', array( 'slug' => 'envo-blocks', 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			wp_send_json_error( array( 'message' => $api->get_error_message() ) );
		}

		$skin		 = new WP_Ajax_Upgrader_Skin();
		$upgrader	 = new Plugin_Upgrader( $skin );
		$result		 = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( is_wp_error( $skin->result ) ) {
			wp_send_json_error( array( 'message' => $skin->result->get_error_message() ) );
		}
		if ( $skin->get_errors()->has_errors() ) {
			wp_send_json_error( array( 'message' => $skin->get_error_messages() ) );
		}
		if ( !$result ) {
			// Usually FTP credentials are needed
			wp_send_json_error( array( 'message' => __( 'Envo Blocks could not be installed automatically. Install it under Plugins → Add New.', 'envo-extra' ) ) );
		}
	}

	if ( !current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to activate plugins on this site.', 'envo-extra' ) ), 403 );
	}

	$activated = activate_plugin( $plugin );
	if ( is_wp_error( $activated ) ) {
		wp_send_json_error( array( 'message' => $activated->get_error_message() ) );
	}

	wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=envo-blocks' ) ) );
}

add_action( 'wp_ajax_envo_extra_envo_blocks_install', 'envo_extra_envo_blocks_install' );
