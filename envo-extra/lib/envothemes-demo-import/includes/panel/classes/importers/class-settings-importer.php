<?php
/**
 * Class for the settings importer.
 */

class envo_Settings_Importer {

	/**
	 * Process import file - this parses the settings data and returns it.
	 *
	 * @param string $file path to json file.
	 */
	public function process_import_file( $file ) {

		// Get file contents.
		$data = Envo_Demos_Helpers::get_remote( $file );

		// Return from this function if there was an error.
		if ( false === $data ) {
			return new WP_Error( 'settings_import_error', __( 'Can not retrieve the customizer settings file.', 'envo-extra' ) );
		}

		// Decode file contents, objects are not allowed.
		$data = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		if ( !is_array( $data ) || !isset( $data['mods'] ) || !is_array( $data['mods'] ) ) {
			return new WP_Error( 'settings_import_error', __( 'The customizer settings file is not valid.', 'envo-extra' ) );
		}

		// Delete import file
		//unlink( $file );

		// If wp_css is set then import it.
		if ( function_exists( 'wp_update_custom_css_post' ) && isset( $data['wp_css'] ) && '' !== $data['wp_css'] ) {
			wp_update_custom_css_post( $data['wp_css'] );
		}

		// Import the data
		$mods = $this->import_data( $data['mods'] );

		// Logo of the demo (by URL - attachment IDs change on import). An empty URL means the demo has no logo: the logo
		// of the site is removed so the site title shows like in the demo (restored when the demo is removed).
		if ( array_key_exists( 'logo_url', $data ) && '' === $data['logo_url'] ) {
			remove_theme_mod( 'custom_logo' );
			delete_option( 'site_logo' ); // WordPress takes the logo from this option before the theme mod
		} elseif ( !empty( $data['logo_url'] ) && is_string( $data['logo_url'] ) ) {
			$logo = $this->import_logo( esc_url_raw( $data['logo_url'] ) );
			if ( $logo ) {
				set_theme_mod( 'custom_logo', $logo );
			}
		}

		return $mods;

	}

	/**
	 * Attachment ID of the logo: the file imported with the demo content (same file name), otherwise it is downloaded.
	 *
	 * @since 1.9.25
	 */
	private function import_logo( $url ) {
		$name = wp_basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( !$name ) {
			return 0;
		}
		$found = get_posts( array(
			'post_type'		 => 'attachment',
			'post_status'	 => 'inherit',
			'posts_per_page' => 1,
			'fields'		 => 'ids',
			'orderby'		 => 'ID',
			'order'			 => 'DESC',
			'meta_query'	 => array( array( 'key' => '_wp_attached_file', 'value' => '/' . $name, 'compare' => 'LIKE' ) ),
		) );
		foreach ( $found as $id ) {
			if ( wp_basename( get_attached_file( $id ) ) === $name ) {
				return (int) $id;
			}
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $url, 0, null, 'id' );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Sanitization callback
	 *
	 * @since 1.0.5
	 */
	private function import_data( $file ) {

		// Import the file
		if ( ! empty( $file ) ) {

			// Loop through mods and add them
			foreach ( $file as $mod => $value ) {
				set_theme_mod( $mod, $value );
			}

		}

		// Return file
		return $file;

	}
}
