<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oldtimers_XRPL_Vehicle_NFTs {
    private $admin;
    
    public function __construct() {
        $this->admin = new Oldtimers_XRPL_Admin();
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Additional initialization if needed
    }
}