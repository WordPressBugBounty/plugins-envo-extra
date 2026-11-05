<?php
/**
 * Install demos page
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
class envo_Install_Demos {

	/**
	 * Servers with the demo files. envo-demos.com hosts all of them.
	 */
	const SERVERS = array( 'https://envo-demos.com', 'https://enwoo-wp.com', 'https://envothemes.com' );

	/**
	 * Start things up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ), 999 );
		add_action( 'wp_ajax_save_domain_switcher', array( $this, 'save_domain_switcher_ajax' ) ); // Handle AJAX request
		add_action( 'in_admin_header', array( $this, 'hide_promo_notices' ) );
	}

	/**
	 * Add sub menu page for the custom CSS input
	 *
	 * @since 1.0.0
	 */
	public function add_page() {
		add_theme_page(
		esc_html__( 'Install Demos', 'envo-extra' ), esc_html__( 'Install Demos', 'envo-extra' ), 'manage_options', 'envothemes-panel-install-demos', array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Our own promo notices push the demos below the fold - not on this page.
	 */
	public function hide_promo_notices() {
		$screen = get_current_screen();
		if ( !$screen || 'appearance_page_envothemes-panel-install-demos' !== $screen->id ) {
			return;
		}
		foreach ( array( 'envothemes_review_notice_message', 'envothemes_pro_notice_message', 'envo_extra_envo_one_notice_message', 'envo_extra_admin_notice_promo', 'envo_extra_admin_notice' ) as $callback ) {
			remove_action( 'admin_notices', $callback );
		}
	}

	/**
	 * Servers offered in the switcher: envo-demos.com and the home server of the active theme.
	 */
	public static function get_servers() {
		$home = ( 'enwoo' === get_template() ) ? 'https://enwoo-wp.com' : 'https://envothemes.com';
		return array(
			'https://envo-demos.com' => __( 'Server 1', 'envo-extra' ),
			$home					 => __( 'Server 2', 'envo-extra' ),
		);
	}

	/**
	 * Currently selected server.
	 */
	public static function get_current_server() {
		$current = untrailingslashit( (string) get_option( 'envothemes_demo_import_url' ) );
		return in_array( $current, self::SERVERS, true ) ? $current : 'https://envo-demos.com';
	}

	// Domain Switcher
	public function domain_switcher_page() {
		// Check if the user has permissions
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$current = self::get_current_server();
		$servers = self::get_servers();
		if ( !isset( $servers[ $current ] ) ) {
			$servers[ $current ] = __( 'Server 3', 'envo-extra' );
		}
		?>
		<div class="envo-di-server">
			<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
			<label for="envo-di-server-select"><?php esc_html_e( 'Demo server', 'envo-extra' ); ?></label>
			<select id="envo-di-server-select" data-nonce="<?php echo esc_attr( wp_create_nonce( 'save_domain_switcher_nonce' ) ); ?>">
				<?php foreach ( $servers as $url => $label ) : ?>
					<option value="<?php echo esc_attr( $url ); ?>" <?php selected( $current, $url ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="envo-di-server-status" aria-live="polite"></span>
			<p class="envo-di-server-help"><?php esc_html_e( 'If an import fails, switch the server. The importer also tries the other servers automatically when a file is missing.', 'envo-extra' ); ?></p>
		</div>
		<?php
	}

	// Save the selected domain option via AJAX
	public function save_domain_switcher_ajax() {
		$nonce = isset( $_POST[ 'security' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'security' ] ) ) : '';
		if ( !current_user_can( 'manage_options' ) || !wp_verify_nonce( $nonce, 'save_domain_switcher_nonce' ) ) {
			wp_send_json_error( __( 'This action was stopped for security purposes.', 'envo-extra' ), 403 );
		}
		// Check if a valid request is made
		if ( isset( $_POST[ 'domain' ] ) ) {
			$new_domain = untrailingslashit( esc_url_raw( wp_unslash( $_POST[ 'domain' ] ) ) );
			if ( !in_array( $new_domain, self::SERVERS, true ) ) {
				wp_send_json_error( __( 'Unknown demo server.', 'envo-extra' ), 400 );
			}

			// Update the option with the new domain value
			update_option( 'envothemes_demo_import_url', $new_domain );

			// Return success response
			wp_send_json_success( __( 'Saved', 'envo-extra' ) );
		}

		// Return error response if no domain was provided
		wp_send_json_error( __( 'No server selected.', 'envo-extra' ), 400 );
	}

	/**
	 * Settings page output
	 *
	 * @since 1.0.0
	 */
	public function create_admin_page() {

		$demos	 = EnvoThemes_Demos::get_demos_data();
		$theme	 = wp_get_theme();

		// Categories (Free/PRO have their own filter) with counts
		$categories	 = array();
		$counts		 = array( 'all' => count( $demos ), 'free' => 0, 'pro' => 0 );
		foreach ( $demos as $item ) {
			$counts[ self::is_pro( $item ) ? 'pro' : 'free' ] ++;
			if ( !empty( $item[ 'categories' ] ) && is_array( $item[ 'categories' ] ) ) {
				foreach ( $item[ 'categories' ] as $category ) {
					$key = sanitize_key( $category );
					if ( in_array( $key, array( 'free', 'pro' ), true ) ) {
						continue;
					}
					if ( !isset( $categories[ $key ] ) ) {
						$categories[ $key ] = array( 'name' => $category, 'count' => 0 );
					}
					$categories[ $key ][ 'count' ] ++;
				}
			}
		}

		?>

		<div class="envo-demo-wrap wrap">

			<?php // Admin notices go here, not into the heading. ?>
			<hr class="wp-header-end">

			<div class="envo-di-hero">
				<div class="envo-di-hero-text">
					<h1>
						<?php esc_html_e( 'Install Demos', 'envo-extra' ); ?>
						<?php /* translators: %s: theme name */ ?>
						<span class="envo-di-hero-theme"><?php echo esc_html( sprintf( __( 'for %s', 'envo-extra' ), $theme->get( 'Name' ) ) ); ?></span>
					</h1>
					<p><?php esc_html_e( 'Start with a ready-made website and replace the content with your own. Pick a demo, install the plugins it needs and import it with one click.', 'envo-extra' ); ?></p>
					<ol class="envo-di-steps">
						<li><span>1</span><?php esc_html_e( 'Choose a demo', 'envo-extra' ); ?></li>
						<li><span>2</span><?php esc_html_e( 'Install plugins', 'envo-extra' ); ?></li>
						<li><span>3</span><?php esc_html_e( 'Import content', 'envo-extra' ); ?></li>
					</ol>
				</div>
				<div class="envo-di-hero-note">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<p>
						<?php
						esc_html_e( 'Import a demo on a fresh site for the best result. Your existing posts and pages are kept, but the front page, menus and theme settings are replaced. Want only the demo content? Tick "Delete existing content first" in the import step. Always back up your site first.', 'envo-extra' );
						?>
					</p>
				</div>
			</div>

			<?php $this->imported_demos(); ?>

			<div class="envo-di-toolbar">
				<div class="envo-di-type" role="group" aria-label="<?php esc_attr_e( 'Demo type', 'envo-extra' ); ?>">
					<button type="button" class="envo-di-type-btn is-active" data-type="all"><?php esc_html_e( 'All', 'envo-extra' ); ?> <span><?php echo absint( $counts[ 'all' ] ); ?></span></button>
					<button type="button" class="envo-di-type-btn" data-type="free"><?php esc_html_e( 'Free', 'envo-extra' ); ?> <span><?php echo absint( $counts[ 'free' ] ); ?></span></button>
					<?php if ( $counts[ 'pro' ] ) : ?>
						<button type="button" class="envo-di-type-btn" data-type="pro"><?php esc_html_e( 'PRO', 'envo-extra' ); ?> <span><?php echo absint( $counts[ 'pro' ] ); ?></span></button>
					<?php endif; ?>
				</div>
				<?php if ( !empty( $categories ) ) : ?>
					<nav class="envo-navigation" aria-label="<?php esc_attr_e( 'Demo categories', 'envo-extra' ); ?>">
						<ul>
							<li class="active"><a href="#all" class="envo-navigation-link"><?php esc_html_e( 'All categories', 'envo-extra' ); ?></a></li>
							<?php foreach ( $categories as $key => $category ) : ?>
								<li><a href="#<?php echo esc_attr( $key ); ?>" class="envo-navigation-link"><?php echo esc_html( $category[ 'name' ] ); ?> <span><?php echo absint( $category[ 'count' ] ); ?></span></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>
				<div class="envo-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<label class="screen-reader-text" for="envo-search-input"><?php esc_html_e( 'Search demos', 'envo-extra' ); ?></label>
					<input type="search" id="envo-search-input" class="envo-search-input" name="envo-search" value="" placeholder="<?php esc_attr_e( 'Search demos...', 'envo-extra' ); ?>">
				</div>
			</div>

			<div class="themes envo-di-grid">

				<?php
				// Loop through all demos
				foreach ( $demos as $demo => $key ) {

					$item_categories = EnvoThemes_Demos::get_demo_item_categories( $key );
					$is_pro			 = self::is_pro( $key );
					$demo_link		 = isset( $key[ 'demo_link' ] ) ? $key[ 'demo_link' ] : '';
					$required_theme	 = isset( $key[ 'required_theme' ] ) ? $key[ 'required_theme' ] : '';
					$other_theme	 = $required_theme && !EnvoThemes_Demos::is_active_theme( $required_theme );
					$labels			 = array();
					if ( !empty( $key[ 'categories' ] ) && is_array( $key[ 'categories' ] ) ) {
						foreach ( $key[ 'categories' ] as $category ) {
							if ( !in_array( sanitize_key( $category ), array( 'free', 'pro' ), true ) ) {
								$labels[] = $category;
							}
						}
					}
					?>

					<div class="theme-wrap" data-categories="<?php echo esc_attr( $item_categories ); ?>" data-name="<?php echo esc_attr( strtolower( $demo . ' ' . $key[ 'demo_name' ] . ' ' . implode( ' ', $labels ) ) ); ?>" data-type="<?php echo $is_pro ? 'pro' : 'free'; ?>">

						<div class="theme envo-open-popup envo-di-card" data-demo-id="<?php echo esc_attr( $demo ); ?>" tabindex="0" role="button" aria-label="<?php /* translators: %s: demo name */ echo esc_attr( sprintf( __( 'Import %s', 'envo-extra' ), $key[ 'demo_name' ] ) ); ?>">

							<div class="theme-screenshot">
								<img src="<?php echo esc_url( $key[ 'screenshot' ] ); ?>" alt="" loading="lazy" />

								<div class="envo-di-badges">
									<?php if ( $is_pro ) : ?>
										<span class="envo-di-badge envo-di-badge-pro"><?php esc_html_e( 'PRO', 'envo-extra' ); ?></span>
									<?php else : ?>
										<span class="envo-di-badge envo-di-badge-free"><?php esc_html_e( 'Free', 'envo-extra' ); ?></span>
									<?php endif; ?>
									<?php if ( $other_theme ) : ?>
										<?php /* translators: %s: theme name */ ?>
										<span class="envo-di-badge envo-di-badge-theme" title="<?php echo esc_attr( sprintf( __( 'Activates the %s theme', 'envo-extra' ), $required_theme ) ); ?>"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><?php echo esc_html( $required_theme ); ?></span>
									<?php endif; ?>
								</div>

								<div class="envo-di-card-overlay">
									<span class="button button-primary button-hero envo-di-import-btn"><?php esc_html_e( 'Import', 'envo-extra' ); ?></span>
								</div>

								<div class="demo-import-loader preview-all preview-all-<?php echo esc_attr( $demo ); ?>"></div>
								<div class="demo-import-loader preview-icon preview-<?php echo esc_attr( $demo ); ?>"><i class="custom-loader"></i></div>
							</div>

							<div class="theme-id-container">
								<div class="envo-di-card-text">
									<h2 class="theme-name" id="<?php echo esc_attr( $demo ); ?>"><span><?php echo esc_html( $key[ 'demo_name' ] ); ?></span></h2>
									<?php if ( $labels ) : ?>
										<p class="envo-di-card-cats"><?php echo esc_html( implode( ' · ', $labels ) ); ?></p>
									<?php endif; ?>
								</div>
								<?php if ( $demo_link ) : ?>
									<div class="theme-actions">
										<a class="button envo-di-preview" href="<?php echo esc_url( $demo_link ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'Preview', 'envo-extra' ); ?></a>
									</div>
								<?php endif; ?>
							</div>

						</div>

					</div>

				<?php } ?>

			</div>

			<div class="envo-di-empty" hidden>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No demos match your filter.', 'envo-extra' ); ?></p>
				<button type="button" class="button envo-di-reset"><?php esc_html_e( 'Show all demos', 'envo-extra' ); ?></button>
			</div>

			<?php $this->domain_switcher_page(); ?>

		</div>

		<?php
	}

	/**
	 * Previously imported demos with the option to remove their content.
	 */
	private function imported_demos() {
		$history = EnvoThemes_Demos::get_history();
		if ( empty( $history ) ) {
			return;
		}
		uasort( $history, function( $a, $b ) {
			return $b[ 'time' ] - $a[ 'time' ];
		} );
		?>
		<div class="envo-di-history" data-nonce="<?php echo esc_attr( wp_create_nonce( 'envo_remove_demo' ) ); ?>">
			<div class="envo-di-history-head">
				<h2><span class="dashicons dashicons-backup" aria-hidden="true"></span><?php esc_html_e( 'Imported demos', 'envo-extra' ); ?></h2>
				<p><?php esc_html_e( 'Remove the content a demo added (pages, posts, products, images, menus) and restore the settings you had before the import. Content you created yourself is kept.', 'envo-extra' ); ?></p>
			</div>
			<ul>
				<?php foreach ( $history as $demo => $entry ) : ?>
					<li class="envo-di-history-item" data-demo="<?php echo esc_attr( $demo ); ?>">
						<div class="envo-di-history-text">
							<strong><?php echo esc_html( $entry[ 'name' ] ); ?></strong>
							<span>
								<?php
								/* translators: 1: date and time of the import, 2: number of items */
								echo esc_html( sprintf( _n( 'Imported %1$s · %2$s item', 'Imported %1$s · %2$s items', count( $entry[ 'posts' ] ), 'envo-extra' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry[ 'time' ] ), number_format_i18n( count( $entry[ 'posts' ] ) ) ) );
								?>
							</span>
						</div>
						<span class="envo-di-history-status" aria-live="polite"></span>
						<button type="button" class="button envo-di-remove-demo"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Remove imported content', 'envo-extra' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * PRO demo = needs a premium plugin.
	 */
	public static function is_pro( $item ) {
		return !empty( $item[ 'required_plugins' ][ 'premium' ] );
	}

}

new envo_Install_Demos();
