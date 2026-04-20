<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oldtimers_XRPL_Admin {
    private $table_name;
    private $vehicle_table;
    private $api_handler;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name    = $wpdb->prefix . 'vehicle_nfts';
        $this->vehicle_table = $wpdb->prefix . 'vehicle_details';
        $this->api_handler   = new Oldtimers_XRPL_API_Handler();
        
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_oldtimers_xrpl_mint_vehicle', [$this, 'handle_mint_vehicle']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function register_admin_menu(): void {
        add_menu_page(
            'Vehicle NFTs - RWA',
            'Vehicle NFTs - RWA',
            'manage_options',
            'oldtimers-xrpl-vehicle-nfts',
            [$this, 'render_admin_page'],
            'dashicons-shield-alt',
            58
        );
    }
    
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_oldtimers-xrpl-vehicle-nfts') {
            return;
        }
        
        wp_enqueue_style(
            'oldtimers-xrpl-admin',
            OLDTIMERS_XRPL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            filemtime(OLDTIMERS_XRPL_PLUGIN_DIR . 'assets/css/admin.css')
        );
        
        $this->add_inline_styles();
    }
    
    private function add_inline_styles(): void {
        wp_add_inline_style('oldtimers-xrpl-admin', '
            @media (max-width: 782px) {
                .oldtimers-xrpl-wrap { padding: 8px !important; }
                .oldtimers-xrpl-header { flex-direction: column; gap: 10px; }
                .oldtimers-xrpl-grid { grid-template-columns: 1fr !important; }
                .oldtimers-xrpl-table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                .oldtimers-xrpl-table thead { display: none; }
                .oldtimers-xrpl-table tr { display: block; border: 1px solid #e0e0e0; margin-bottom: 12px; border-radius: 6px; padding: 8px; }
                .oldtimers-xrpl-table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 4px; border: none; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
                .oldtimers-xrpl-table td:last-child { border-bottom: none; }
                .oldtimers-xrpl-table td::before { content: attr(data-label); font-weight: 600; color: #555; min-width: 100px; flex-shrink: 0; }
                .oldtimers-xrpl-limit-bar { flex-direction: column; align-items: flex-start !important; gap: 6px; }
                .oldtimers-xrpl-pagination { flex-wrap: wrap; }
            }
        ');
    }
    
    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        // Prepare data for template
        $data = $this->prepare_admin_data();
        
        // Include template
        require_once OLDTIMERS_XRPL_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    private function prepare_admin_data(): array {
        global $wpdb;
        
        $allowed_limits = [25, 50, 100, 200];
        
        $nft_limit = isset($_GET['nft_limit']) ? absint($_GET['nft_limit']) : 50;
        if (!in_array($nft_limit, $allowed_limits, true)) $nft_limit = 50;
        
        $vehicle_limit = isset($_GET['vehicle_limit']) ? absint($_GET['vehicle_limit']) : 50;
        if (!in_array($vehicle_limit, $allowed_limits, true)) $vehicle_limit = 50;
        
        $nft_page     = isset($_GET['nft_page'])     ? max(1, absint($_GET['nft_page']))     : 1;
        $vehicle_page = isset($_GET['vehicle_page']) ? max(1, absint($_GET['vehicle_page'])) : 1;
        
        $nft_offset     = ($nft_page - 1)     * $nft_limit;
        $vehicle_offset = ($vehicle_page - 1) * $vehicle_limit;
        
        $nft_total     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $vehicle_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->vehicle_table}");
        
        $nft_pages     = max(1, (int) ceil($nft_total     / $nft_limit));
        $vehicle_pages = max(1, (int) ceil($vehicle_total / $vehicle_limit));
        
        // Clamp pages
        $nft_page     = min($nft_page,     $nft_pages);
        $vehicle_page = min($vehicle_page, $vehicle_pages);
        
        $nfts = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT n.id, n.vehicle_id, n.nft_id, n.tx_hash, n.metadata_url, n.network, n.status, n.minted_at,
                       v.make_display, v.model_name, v.model_year, v.vehicle_type, v.country_location
                FROM {$this->table_name} n
                LEFT JOIN {$this->vehicle_table} v ON v.vehicle_id = n.vehicle_id
                ORDER BY n.id DESC
                LIMIT %d OFFSET %d
                ",
                $nft_limit,
                $nft_offset
            )
        );
        
        $recent_vehicles = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT vehicle_id, model_year, make_display, model_name, vehicle_type, country_location, post_status
                FROM {$this->vehicle_table}
                ORDER BY vehicle_id DESC
                LIMIT %d OFFSET %d
                ",
                $vehicle_limit,
                $vehicle_offset
            )
        );
        
        return [
            'notice' => isset($_GET['oldtimers_notice']) ? sanitize_text_field(wp_unslash($_GET['oldtimers_notice'])) : '',
            'error' => isset($_GET['oldtimers_error']) ? sanitize_text_field(wp_unslash($_GET['oldtimers_error'])) : '',
            'allowed_limits' => $allowed_limits,
            'nft_limit' => $nft_limit,
            'vehicle_limit' => $vehicle_limit,
            'nft_page' => $nft_page,
            'vehicle_page' => $vehicle_page,
            'nft_pages' => $nft_pages,
            'vehicle_pages' => $vehicle_pages,
            'nfts' => $nfts,
            'recent_vehicles' => $recent_vehicles,
            'nft_total' => $nft_total,
            'vehicle_total' => $vehicle_total,
            'api_base_url' => defined('OLDTIMERS_XRPL_API_URL') ? rtrim(OLDTIMERS_XRPL_API_URL, '/') : '',
            'api_key' => defined('OLDTIMERS_XRPL_API_KEY') ? OLDTIMERS_XRPL_API_KEY : '',
            'table_name' => $this->table_name,
            'vehicle_table' => $this->vehicle_table
        ];
    }
    
    public function handle_mint_vehicle(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }
        
        check_admin_referer('oldtimers_xrpl_mint_vehicle_action', 'oldtimers_xrpl_nonce');
        
        $vehicle_id = isset($_POST['vehicle_id']) ? absint($_POST['vehicle_id']) : 0;
        
        if ($vehicle_id <= 0) {
            $this->redirect_with_error('Invalid vehicle ID.');
        }
        
        $result = $this->api_handler->mint_vehicle_nft($vehicle_id, $this->table_name, $this->vehicle_table);
        
        if ($result['success']) {
            $this->redirect_with_notice($result['message']);
        } else {
            $this->redirect_with_error($result['message']);
        }
    }
    
    private function redirect_with_notice(string $message): void {
        $url = add_query_arg(
            ['page' => 'oldtimers-xrpl-vehicle-nfts', 'oldtimers_notice' => rawurlencode($message)],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }
    
    private function redirect_with_error(string $message): void {
        $url = add_query_arg(
            ['page' => 'oldtimers-xrpl-vehicle-nfts', 'oldtimers_error' => rawurlencode($message)],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }
    
    private function render_pagination(int $current, int $total, string $page_param, array $extra_params): void {
        if ($total <= 1) return;
        
        $base_url = admin_url('admin.php');
        $params   = array_merge(['page' => 'oldtimers-xrpl-vehicle-nfts'], $extra_params);
        ?>
        <div class="oldtimers-xrpl-pagination" style="display:flex; align-items:center; gap:6px; margin-top:14px; flex-wrap:wrap;">
            <?php if ($current > 1): ?>
                <a href="<?php echo esc_url(add_query_arg(array_merge($params, [$page_param => 1]), $base_url)); ?>"
                   class="button">«</a>
                <a href="<?php echo esc_url(add_query_arg(array_merge($params, [$page_param => $current - 1]), $base_url)); ?>"
                   class="button">← Previous</a>
            <?php endif; ?>
            
            <span style="font-size:13px; color:#555; padding: 0 6px;">
                Page <strong><?php echo $current; ?></strong> of <strong><?php echo $total; ?></strong>
            </span>
            
            <?php if ($current < $total): ?>
                <a href="<?php echo esc_url(add_query_arg(array_merge($params, [$page_param => $current + 1]), $base_url)); ?>"
                   class="button">Next →</a>
                <a href="<?php echo esc_url(add_query_arg(array_merge($params, [$page_param => $total]), $base_url)); ?>"
                   class="button">»</a>
            <?php endif; ?>
        </div>
        <?php
    }
}