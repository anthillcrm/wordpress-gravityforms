<?php

/**
 * Plugin Name: Gravity Forms - Anthill Integration
 * Description: An add-on for Gravity Forms that sends form submissions to Anthill.
 * Version: 1.0.15
 * Author: Anthill
 * Author URI: http://www.anthill.co.uk/
 */

register_activation_hook(__FILE__,'gf_anthill_preactivation');
function gf_anthill_preactivation() {
	if (!extension_loaded('soap')) {
		echo 'This plugin needs the PHP SOAP extension to operate';
		@trigger_error('This plugin needs the PHP SOAP extension to operate', E_USER_ERROR);
	}
}

function gravity_forms_anthill_enqueue_admin() {
	$wp_scripts = wp_scripts();
	wp_enqueue_script( 'gf_anthill_admin_js', plugins_url('/js/gf-anthill.js',__FILE__), array(), '1.0.0', true );

}
function gravity_forms_anthill_enqueue_admin_gform_noconflict( $scripts ) {

	//registering my script with Gravity Forms so that it gets enqueued when running on no-conflict mode
	$scripts[] = 'gf_anthill_admin_js';
	return $scripts;
}

function init_anthill() {

	if (!class_exists('Anthill')) {
		require_once('anthill.class.php');
		require_once('anthill-settings.php');
	}

	require_once('gravity-forms-anthill-submit.php');
	require_once('gravity-forms-anthill-form.php');
	require_once('gravity-forms-anthill-form-settings.php');

	require_once('fields/class-gf-anthill-field-name.php');


	/* SCRIPTS */
	add_action( 'admin_enqueue_scripts', 'gravity_forms_anthill_enqueue_admin',1 );
	
	add_filter( 'gform_noconflict_scripts', 'gravity_forms_anthill_enqueue_admin_gform_noconflict' );	
}

add_action("gform_loaded", "init_anthill", 10, 0);