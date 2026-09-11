<?php

/**
 * Plugin Name: Gravity Forms - Anthill Integration
 * Description: An add-on for Gravity Forms that sends form submissions to Anthill.
 * Version: 1.0.17
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

function init_anthill() {

	if (!class_exists('Anthill')) {
		require_once('anthill.class.php');
		require_once('anthill-settings.php');
	}

	// Loaded first: defines gf_anthill_form_setting(), used by both files below.
	require_once('gravity-forms-anthill-form-settings.php');

	require_once('gravity-forms-anthill-submit.php');
	require_once('gravity-forms-anthill-form.php');

	require_once('fields/class-gf-anthill-field-name.php');

}

add_action("gform_loaded", "init_anthill", 10, 0);