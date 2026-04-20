<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oldtimers_XRPL_API_Handler {
    private $api_base_url;
    private $api_key;
    
    public function __construct() {
        $this->api_base_url = defined('OLDTIMERS_XRPL_API_URL') ? rtrim(OLDTIMERS_XRPL_API_URL, '/') : '';
        $this->api_key      = defined('OLDTIMERS_XRPL_API_KEY') ? OLDTIMERS_XRPL_API_KEY : '';
    }
    
    public function mint_vehicle_nft(int $vehicle_id, string $table_name, string $vehicle_table): array {
        if (empty($this->api_base_url) || empty($this->api_key)) {
            return ['success' => false, 'message' => 'XRPL API URL or API key is missing in wp-config.php.'];
        }
        
        global $wpdb;
        
        $vehicle_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT vehicle_id FROM {$vehicle_table} WHERE vehicle_id = %d LIMIT 1",
                $vehicle_id
            )
        );
        
        if (!$vehicle_exists) {
            return ['success' => false, 'message' => 'Vehicle not found in database.'];
        }
        
        $already_minted = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT nft_id FROM {$table_name} WHERE vehicle_id = %d LIMIT 1",
                $vehicle_id
            )
        );
        
        if ($already_minted) {
            return ['success' => false, 'message' => 'This vehicle already has an NFT.'];
        }
        
        $endpoint = $this->api_base_url . '/nft/vehicle/' . $vehicle_id . '/mint';
        
        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept'        => 'application/json',
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Request failed: ' . $response->get_error_message()];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $json        = json_decode($body, true);
        
        if ($status_code < 200 || $status_code >= 300) {
            $message = is_array($json) && !empty($json['error'])
                ? $json['error']
                : 'XRPL service returned an error.';
            return ['success' => false, 'message' => $message];
        }
        
        if (!is_array($json) || empty($json['success'])) {
            $message = is_array($json) && !empty($json['error'])
                ? $json['error']
                : 'Unexpected response from XRPL service.';
            return ['success' => false, 'message' => $message];
        }
        
        $notice = sprintf(
            'Vehicle #%d NFT minted successfully. NFT ID: %s',
            $vehicle_id,
            $json['nft_id'] ?? 'N/A'
        );
        
        return ['success' => true, 'message' => $notice];
    }
}