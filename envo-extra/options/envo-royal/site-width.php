<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if (!class_exists('Kirki')) {
    return;
}

Kirki::add_section('site_width_section', array(
    'title' => esc_attr__('Site Width Options', 'envo-extra'),
    'panel' => 'envo_theme_panel',
    'priority' => 20,
));
