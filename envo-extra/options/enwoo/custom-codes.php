<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if (!class_exists('Kirki')) {
    return;
}

/**
 * Raw code is allowed only for users with unfiltered_html capability
 * (not site admins on multisite). Others get the value filtered by wp_kses_post.
 */
if (!function_exists('envo_extra_do_not_filter_anything')) {

    function envo_extra_do_not_filter_anything($value) {
        if (current_user_can('unfiltered_html')) {
            return $value;
        }
        return wp_kses_post($value);
    }

}

Kirki::add_section('custom_code_section', array(
    'title' => esc_attr__('Custom Codes', 'envo-extra'),
    'panel' => 'envo_theme_panel',
    'priority' => 100,
));

Kirki::add_field('envo_extra', array(
    'type' => 'textarea',
    'settings' => 'header-code',
    'label' => __('Code to be added to the HEAD', 'envo-extra'),
    'description' => __('Suitable for Google Analytics code', 'envo-extra'),
    'section' => 'custom_code_section',
    'transport' => 'postMessage',
    'sanitize_callback' => 'envo_extra_do_not_filter_anything',
    'default' => '',
    'priority' => 10,
));

add_action('wp_head', 'envo_extra_add_googleanalytics', 10);

function envo_extra_add_googleanalytics() {
    $header_code = get_theme_mod('header-code', '');
    if ($header_code) {
        echo $header_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw code saved by a user with unfiltered_html.
    }
}

Kirki::add_field('envo_extra', array(
    'type' => 'textarea',
    'settings' => 'footer-code',
    'label' => __('Code to be added to the footer', 'envo-extra'),
    'section' => 'custom_code_section',
    'transport' => 'postMessage',
    'sanitize_callback' => 'envo_extra_do_not_filter_anything',
    'default' => '',
    'priority' => 10,
));

add_action('wp_footer', 'envo_extra_add_footer_code');

function envo_extra_add_footer_code() {
    $footer_code = get_theme_mod('footer-code', '');
    if ($footer_code) {
        echo $footer_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw code saved by a user with unfiltered_html.
    }
}
