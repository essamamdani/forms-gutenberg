<?php
/**
 * Plugin Name: WordPress Form Builder Plugin - Gutenberg Forms
 * Plugin URI: http://www.gutenbergforms.com
 * Description: The form builder plugin for WordPress Gutenberg editor. Build forms directly within Gutenberg editor live. Add & arrange form fields like blocks.
 * Author: essamamdani
 * Author URI: http://www.essamamdani.com
 * Version: 1.0.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'GBF_NAME' ) ) {
	define( 'GBF_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
}
if ( ! defined( 'GBF_DIR' ) ) {
	define( 'GBF_DIR', WP_PLUGIN_DIR . '/' . GBF_NAME );
}
if ( ! defined( 'GBF_BLOCK_DIR' ) ) {
	define( 'GBF_BLOCK_DIR', WP_PLUGIN_DIR . '/' . GBF_NAME . "/block/gutenberg-forms/" );
}
if ( ! defined( 'GBF_URL' ) ) {
	define( 'GBF_URL', WP_PLUGIN_URL . '/' . GBF_NAME );
}
if ( ! class_exists( "BlockFormBuilder" ) ) {
	require_once( GBF_BLOCK_DIR . "BlockFormBuilder.php" );
	$gutenbergForms = new BlockFormBuilder();
	$gutenbergForms->init();
}
