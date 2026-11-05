<?php

/**
 * Helpers class
 */
class Envo_Demos_Helpers {

    /**
     * Gets and returns url body using wp_remote_get
     *
     * @since 1.0.0
     */
    public static function get_remote($url) {

        // Try the selected demo server first, then the other ones (not every server has all demos)
        foreach (self::server_urls($url) as $server_url) {

            // Get data, demo XML files have several MB
            $response = wp_remote_get($server_url, array('timeout' => 60));

            // Check for errors
            if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
                continue;
            }

            // Get remote body val
            $body = wp_remote_retrieve_body($response);

            // Return data
            if (!empty($body)) {
                return $body;
            }
        }

        return false;
    }

    /**
     * The given URL followed by the same file on the other demo servers.
     *
     * @since 1.9.25
     */
    public static function server_urls($url) {
        $urls = array($url);
        $servers = class_exists('envo_Install_Demos') ? envo_Install_Demos::SERVERS : array('https://envo-demos.com', 'https://enwoo-wp.com', 'https://envothemes.com');
        foreach ($servers as $server) {
            if (0 === strpos($url, $server . '/')) {
                $path = substr($url, strlen($server));
                foreach ($servers as $other) {
                    if ($other !== $server) {
                        $urls[] = $other . $path;
                    }
                }
                break;
            }
        }
        return $urls;
    }

    /**
     * Perform a HTTP HEAD or GET request.
     *
     * If $file_path is a writable filename, this will do a GET request and write
     * the file to that path.
     *
     * This is a re-implementation of the deprecated wp_get_http() function from WP Core,
     * but this time using the recommended WP_Http() class and the WordPress filesystem.
     *
     * @param string      $url       URL to fetch.
     * @param string|bool $file_path Optional. File path to write request to. Default false.
     * @param array       $args      Optional. Arguments to be passed-on to the request.
     * @return bool|string False on failure and string of headers if HEAD request.
     */
    public static function wp_get_http($url, $file_path = false, $red = 1) {

        // No need to proceed if we don't have a $url or a $file_path.
        if (!$url || !$file_path) {
            return false;
        }
		$args	= array();
        $try_file_get_contents = false;

        // Make sure we normalize $file_path.
        $file_path = wp_normalize_path($file_path);

        // Include the WP_Http class if it doesn't already exist.
        if (!class_exists('WP_Http')) {
            include_once( wp_normalize_path(ABSPATH . WPINC . '/class-http.php') );
        }
        // Inlude the wp_remote_get function if it doesn't already exist.
        if (!function_exists('wp_remote_get')) {
            include_once( wp_normalize_path(ABSPATH . WPINC . '/http.php') );
        }

        $args = wp_parse_args($args, array(
            'timeout' => 30,
            'user-agent' => 'EnvoThemes Demo Import; ' . home_url(),
        ));
        $response = wp_safe_remote_get(esc_url_raw($url), $args);
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        // Initialize the WordPress filesystem.
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once( ABSPATH . '/wp-admin/includes/file.php' );
            WP_Filesystem();
        }

        // Attempt to write the file.
        if (!$wp_filesystem->put_contents($file_path, $body, FS_CHMOD_FILE)) {
            wp_delete_file($file_path);
            return false;
        }

        // If all went well, then return the headers of the request.
        if (isset($response['headers'])) {
            $response['headers']['response'] = $response['response']['code'];
            return $response['headers'];
        }

        // If all else fails, then return false.
        return false;
    }

}
