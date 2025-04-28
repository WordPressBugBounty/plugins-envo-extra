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
	 * Start things up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ), 999 );
		//add_action('admin_init', array($this, 'domain_switcher_page'));
		add_action( 'wp_ajax_save_domain_switcher', array( $this, 'save_domain_switcher_ajax' ) ); // Handle AJAX request
	}

	/**
	 * Add sub menu page for the custom CSS input
	 *
	 * @since 1.0.0
	 */
	public function add_page() {


		$title = esc_html__( 'Install Demos', 'envothemes-demo-import' );

		add_theme_page(
		esc_html__( 'Install Demos', 'envothemes-demo-import' ), $title, 'manage_options', 'envothemes-panel-install-demos', array( $this, 'create_admin_page' )
		);
	}

	// Domain Switcher Page Content
	public function domain_switcher_page() {
		// Check if the user has permissions
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$theme = wp_get_theme();
		$source = '';
		if ( 'Enwoo' == $theme->name || 'enwoo' == $theme->template ) {
			$source = 'https://enwoo-wp.com';
		} elseif ( 'Envo Royal' == $theme->name || 'envo-royal' == $theme->template || 'Envo One' == $theme->name || 'envo-one' == $theme->template || 'Envo Shop' == $theme->name || 'envo-shop' == $theme->template ) {
			$source = 'https://envo-demos.com';
		} else {
			$source = 'https://envothemes.com';
		}
		// Get the current domain setting
		$current_domain = get_option( 'envothemes_demo_import_url');
		if ( false === $current_domain ) {
			add_option('envothemes_demo_import_url', esc_url($source));
		}
		$enwoo_source = '';
		if ( 'Enwoo' == $theme->name || 'enwoo' == $theme->template ) {
			$enwoo_source = 'https://enwoo-wp.com';
		} else {
			$enwoo_source = 'https://envothemes.com';
		}
		?>
		<script>
		    jQuery( document ).ready( function ( $ ) {
		        // Listen for toggle change
		        $( "#domain_switcher_toggle" ).on( "change", function () {
					var dataSource = this.getAttribute('data-source');
		            var domainValue = $( this ).prop( "checked" ) ? "https://envo-demos.com" : dataSource;

		            // Send AJAX request to save the domain setting
		            $.ajax( {
		                url: ajaxurl,
		                type: "POST",
		                data: {
		                    action: "save_domain_switcher",
		                    domain: domainValue,
							security: "<?php echo wp_create_nonce( 'save_domain_switcher_nonce' ); ?>" // Add nonce for security
		                },
		                success: function ( response ) {
		                    // Optionally show success message or log response
		                    console.log( "Domain switched successfully" );
		                },
		                error: function () {
		                    console.log( "Error saving domain setting" );
		                }
		            } );
		        } );
		    } );
		</script>
		<div class="server-switcher wrap">
			<form method="post" action="">
				<?php wp_nonce_field( 'save_domain_switcher_nonce', 'save_domain_switcher_nonce' ); ?>

				<label for="domain_switcher_toggle">If the <b>import failed</b>, try switching the demo source server.</label><br>

				<!-- Toggle Button -->
				<span><b>Server 1</b></span>
				<label class="toggle-switch">
					<input type="checkbox" name="domain_switcher_toggle" id="domain_switcher_toggle" data-source="<?php echo esc_url($enwoo_source) ?>" 
						   <?php echo ($current_domain == 'https://envo-demos.com') ? 'checked' : ''; ?>>
					<span class="slider"></span>
				</label>
				<span><b>Server 2</b></span>
				<br>
			</form>
		</div>
		<?php
	}

	// Save the selected domain option via AJAX
	public function save_domain_switcher_ajax() {
		
		if ( !current_user_can( 'manage_options' ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'security' ] ) ), 'save_domain_switcher_nonce' ) ) {
			die( 'This action was stopped for security purposes.' );
		}
		// Check if a valid request is made
		if ( isset( $_POST[ 'domain' ] ) ) {
			$new_domain = sanitize_text_field( $_POST[ 'domain' ] );

			// Update the option with the new domain value
			update_option( 'envothemes_demo_import_url', esc_url($new_domain) );

			// Return success response
			wp_send_json_success( 'Domain switched successfully' );
		}

		// Return error response if no domain was provided
		wp_send_json_error( 'No domain provided' );
	}

	/**
	 * Settings page output
	 *
	 * @since 1.0.0
	 */
	public function create_admin_page() {

		// Theme branding
		$brand		 = 'EnvoThemes'
		?>
		
		<div class="envo-demo-wrap wrap">

			<h2><?php echo esc_html( $brand ); ?> - <?php esc_html_e( 'Install Demos', 'envothemes-demo-import' ); ?></h2>
			<div class="theme-browser rendered">

				<?php
				// Vars
				$demos		 = EnvoThemes_Demos::get_demos_data();
				$categories	 = EnvoThemes_Demos::get_demo_all_categories( $demos );
				echo $this->domain_switcher_page();
				?>

				<?php if ( !empty( $categories ) ) : ?>
					<div class="envo-header-bar">
						<nav class="envo-navigation">
							<ul>
								<li class="active"><a href="#all" class="envo-navigation-link"><?php esc_html_e( 'All', 'envothemes-demo-import' ); ?></a></li>
								<?php foreach ( $categories as $key => $name ) : ?>
									<li><a href="#<?php echo esc_attr( $key ); ?>" class="envo-navigation-link"><?php echo esc_html( $name ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</nav>
						<div clas="envo-search">
							<input type="text" class="envo-search-input" name="envo-search" value="" placeholder="<?php esc_html_e( 'Search demos...', 'envothemes-demo-import' ); ?>">
						</div>
					</div>
				<?php endif; ?>

				<div class="themes wp-clearfix">

					<?php
					// Loop through all demos
					foreach ( $demos as $demo => $key ) {

						// Vars
						$item_categories = EnvoThemes_Demos::get_demo_item_categories( $key );
						$pro			 = isset( $key[ 'required_plugins' ] ) ? $key[ 'required_plugins' ] : '';
						$demo_link		 = isset( $key[ 'demo_link' ] ) ? $key[ 'demo_link' ] : '';
						?>

						<div class="theme-wrap" data-categories="<?php echo esc_attr( $item_categories ); ?>" data-name="<?php echo esc_attr( strtolower( $demo ) ); ?>">

							<div class="theme envo-open-popup" data-demo-id="<?php echo esc_attr( $demo ); ?>">

								<div class="theme-screenshot">
									<img src="<?php echo esc_url( $key[ 'screenshot' ] ); ?>" />

									<div class="demo-import-loader preview-all preview-all-<?php echo esc_attr( $demo ); ?>"></div>

									<div class="demo-import-loader preview-icon preview-<?php echo esc_attr( $demo ); ?>"><i class="custom-loader"></i></div>
									<?php if ( isset( $pro[ 'premium' ] ) && !empty( $pro[ 'premium' ] ) ) { ?>
										<div class="pro-badge">
											<?php esc_html_e( 'PRO', 'envo-extra' ); ?>
										</div>
									<?php } ?>
								</div>

								<div class="theme-id-container">

									<h2 class="theme-name" id="<?php echo esc_attr( $demo ); ?>"><span><?php echo esc_html( $key[ 'demo_name' ] ); ?></span></h2>

									<div class="theme-actions">
										<a class="button button-primary" href="<?php echo esc_url( $demo_link ); ?>" target="_blank"><?php esc_html_e( 'Live Preview', 'envothemes-demo-import' ); ?></a>
									</div>

								</div>

							</div>

						</div>

					<?php } ?>

				</div>

			</div>

		</div>

		<?php
	}

}

new envo_Install_Demos();
