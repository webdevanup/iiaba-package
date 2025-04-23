<?php
/**
 * Plugin Name: WDG Wikit Facets
 * Plugin URI: https://github.com/wdgdc/wikit-facets
 * Version: 0.0.13
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Author: WDG
 * Author URI: https://wdg.co
 * License: MIT
 * Text Domain: wdg-facets
 *
 * This file is only used if installed directly as a plugin and not when used as a composer dependency
 *
 * @package WDG\Facets
 */

namespace WDG\Facets;

require_once 'vendor/autoload.php';

add_action( 'init', __NAMESPACE__ . '\\Facets::instance' );
