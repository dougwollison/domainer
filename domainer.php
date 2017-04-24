<?php
/*
Plugin Name: Domainer
Plugin URI: https://github.com/dougwollison/domainer
Description: Domain mapping management for WordPress Multisite.
Version: 1.0.0
Author: Doug Wollison
Author URI: http://dougw.me
Tags: domain mapping, domain management, multisite
License: GPL2
Text Domain: domainer
Domain Path: /languages
*/

// =========================
// ! Constants
// =========================

/**
 * Reference to the plugin file.
 *
 * @since 1.0.0
 *
 * @var string
 */
define( 'DOMAINER_PLUGIN_FILE', __FILE__ );

/**
 * Reference to the plugin directory.
 *
 * @since 1.0.0
 *
 * @var string
 */
define( 'DOMAINER_PLUGIN_DIR', dirname( DOMAINER_PLUGIN_FILE ) );

// =========================
// ! Includes
// =========================

require( DOMAINER_PLUGIN_DIR . '/includes/autoloader.php' );
require( DOMAINER_PLUGIN_DIR . '/includes/functions-domainer.php' );
require( DOMAINER_PLUGIN_DIR . '/includes/functions-template.php' );

// =========================
// ! Setup
// =========================

Domainer\System::setup();
