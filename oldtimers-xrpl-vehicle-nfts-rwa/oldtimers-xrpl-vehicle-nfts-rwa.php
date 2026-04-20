<?php
/**
 * Plugin Name: Oldtimers XRPL Vehicle NFTs - RWA
 * Description: Admin plugin for minting and viewing Vehicle Passport NFTs - Real World Asset Tokenization (RWA)
 * Version: 1.0.3
 * Author: Oldtimers Offer
 * Author URI: https://oldtimersoffer.com
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OLDTIMERS_XRPL_VERSION', '1.0.3');
define('OLDTIMERS_XRPL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLDTIMERS_XRPL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once OLDTIMERS_XRPL_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once OLDTIMERS_XRPL_PLUGIN_DIR . 'includes/class-admin.php';
require_once OLDTIMERS_XRPL_PLUGIN_DIR . 'includes/class-main.php';

// Initialize the plugin
new Oldtimers_XRPL_Vehicle_NFTs();