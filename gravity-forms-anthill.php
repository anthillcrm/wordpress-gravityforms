<?php

/**
 * Plugin Name: Gravity Forms - Anthill Integration
 * Description: An add-on for Gravity Forms that sends form submissions to Anthill.
 * Version: 1.0.2
 * Author: Cyan Commerce
 * Author URI: http://www.anthill.co.uk/
 */

if (!class_exists('Anthill')) {
	require_once('anthill.class.php');
	require_once('anthill-settings.php');
}

require_once('gravity-forms-anthill-submit.php');
require_once('gravity-forms-anthill-form.php');
require_once('gravity-forms-anthill-form-settings.php');

require_once('fields/class-gf-anthill-field-name.php');


/* SCRIPTS */
add_action( 'admin_enqueue_scripts', 'gravity_forms_anthill_enqueue_admin' );
function gravity_forms_anthill_enqueue_admin() {
	$wp_scripts = wp_scripts();
	wp_register_script( 'gf_anthill_admin_js', plugins_url('/js/gf-anthill.js',__FILE__), array(), '1.0.0' );
    wp_enqueue_script( 'gf_anthill_admin_js' );
	
}

