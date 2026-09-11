<?php

/**
 * Plugin Name: Gravity Forms - Anthill Integration
 * Description: An add-on for Gravity Forms that sends form submissions to Anthill.
 * Version: 2.0.0
 * Author: Anthill
 * Author URI: http://www.anthill.co.uk/
 * Text Domain: gravity-forms-anthill
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'GF_ANTHILL_VERSION', '2.0.0' );
define( 'GF_ANTHILL_MIN_GF_VERSION', '3.1.1.2' );
define( 'GF_ANTHILL_MIN_PHP_VERSION', '8.1' );
define( 'GF_ANTHILL_FILE', __FILE__ );
define( 'GF_ANTHILL_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Requirements that cannot be satisfied after the fact.
 *
 * Checked at activation, where failing them aborts the activation outright.
 * Gravity Forms is deliberately not among them: installing this add-on before
 * Gravity Forms is a reasonable order to work in, so a missing or outdated
 * Gravity Forms produces an admin notice rather than a blocked activation.
 *
 * @return array Human-readable failure messages; empty when the environment is fine.
 */
function gf_anthill_environment_failures() {
	$failures = array();

	if ( version_compare( PHP_VERSION, GF_ANTHILL_MIN_PHP_VERSION, '<' ) ) {
		$failures[] = sprintf(
			/* translators: 1: required PHP version, 2: PHP version in use. */
			__( 'PHP %1$s or later is required. This site is running PHP %2$s.', 'gravity-forms-anthill' ),
			GF_ANTHILL_MIN_PHP_VERSION,
			PHP_VERSION
		);
	}

	if ( ! extension_loaded( 'soap' ) ) {
		$failures[] = __( 'The PHP SOAP extension is required to communicate with Anthill, and is not installed.', 'gravity-forms-anthill' );
	}

	return $failures;
}

/**
 * The Gravity Forms version in use, or an empty string if it cannot be determined.
 *
 * @return string
 */
function gf_anthill_gravity_forms_version() {
	if ( class_exists( 'GFForms' ) && property_exists( 'GFForms', 'version' ) ) {
		return (string) GFForms::$version;
	}

	if ( class_exists( 'GFCommon' ) && property_exists( 'GFCommon', 'version' ) ) {
		return (string) GFCommon::$version;
	}

	return '';
}

/**
 * Everything that has to be true for the plugin to load its hooks.
 *
 * Memoised: this is consulted once while loading and again when rendering the
 * admin notice.
 *
 * @return array Human-readable failure messages; empty when all requirements are met.
 */
function gf_anthill_requirement_failures() {
	static $failures = null;

	if ( null !== $failures ) {
		return $failures;
	}

	$failures = gf_anthill_environment_failures();

	if ( ! class_exists( 'GFForms' ) ) {
		$failures[] = sprintf(
			/* translators: %s: required Gravity Forms version. */
			__( 'Gravity Forms %s or later is required, and is not active.', 'gravity-forms-anthill' ),
			GF_ANTHILL_MIN_GF_VERSION
		);

		return $failures;
	}

	$gf_version = gf_anthill_gravity_forms_version();

	// An unreadable version is treated as acceptable rather than blocking the
	// plugin on a detection change in some future Gravity Forms release.
	if ( '' !== $gf_version && version_compare( $gf_version, GF_ANTHILL_MIN_GF_VERSION, '<' ) ) {
		$failures[] = sprintf(
			/* translators: 1: required Gravity Forms version, 2: Gravity Forms version in use. */
			__( 'Gravity Forms %1$s or later is required. This site is running Gravity Forms %2$s.', 'gravity-forms-anthill' ),
			GF_ANTHILL_MIN_GF_VERSION,
			$gf_version
		);
	}

	return $failures;
}

register_activation_hook( __FILE__, 'gf_anthill_preactivation' );

/**
 * Aborts activation when the environment cannot support the plugin.
 *
 * Replaces an echo plus trigger_error( E_USER_ERROR ), which produced an
 * "unexpected output" warning and a bare fatal.
 *
 * @return void
 */
function gf_anthill_preactivation() {
	$failures = gf_anthill_environment_failures();

	if ( empty( $failures ) ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	$message = '<p>' . esc_html__( 'Gravity Forms - Anthill Integration could not be activated:', 'gravity-forms-anthill' ) . '</p><ul>';
	foreach ( $failures as $failure ) {
		$message .= '<li>' . esc_html( $failure ) . '</li>';
	}
	$message .= '</ul>';

	wp_die(
		wp_kses_post( $message ),
		esc_html__( 'Plugin activation failed', 'gravity-forms-anthill' ),
		array( 'back_link' => true )
	);
}

add_action( 'admin_notices', 'gf_anthill_requirements_notice' );

/**
 * Tells administrators why the plugin is sitting idle.
 *
 * @return void
 */
function gf_anthill_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$failures = gf_anthill_requirement_failures();

	if ( empty( $failures ) ) {
		return;
	}

	$items = '';
	foreach ( $failures as $failure ) {
		$items .= '<li>' . esc_html( $failure ) . '</li>';
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong></p><ul class="ul-disc">%s</ul><p>%s</p></div>',
		esc_html__( 'Gravity Forms - Anthill Integration is not running.', 'gravity-forms-anthill' ),
		wp_kses_post( $items ),
		esc_html__( 'Form submissions are not being sent to Anthill until this is resolved.', 'gravity-forms-anthill' )
	);
}

add_action( 'init', 'gf_anthill_load_textdomain' );

/**
 * @return void
 */
function gf_anthill_load_textdomain() {
	load_plugin_textdomain( 'gravity-forms-anthill', false, dirname( plugin_basename( GF_ANTHILL_FILE ) ) . '/languages' );
}

add_action( 'gform_loaded', 'init_anthill', 10, 0 );

/**
 * Loads the plugin, provided every requirement is met.
 *
 * Named for the hook it has always been registered with, so that any
 * remove_action() in a theme or companion plugin keeps working.
 *
 * @return void
 */
function init_anthill() {

	if ( gf_anthill_requirement_failures() ) {
		return;
	}

	if ( ! class_exists( 'Anthill' ) ) {
		require_once GF_ANTHILL_PATH . 'anthill.class.php';
		require_once GF_ANTHILL_PATH . 'anthill-settings.php';
	}

	// Loaded first: defines gf_anthill_form_setting(), used by both files below.
	require_once GF_ANTHILL_PATH . 'gravity-forms-anthill-form-settings.php';

	require_once GF_ANTHILL_PATH . 'gravity-forms-anthill-submit.php';
	require_once GF_ANTHILL_PATH . 'gravity-forms-anthill-form.php';

	require_once GF_ANTHILL_PATH . 'fields/class-gf-anthill-field-name.php';
}
