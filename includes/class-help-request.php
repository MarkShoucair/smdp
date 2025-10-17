<?php
/**
 * class-help-request.php – Final consolidated class with live Square calls
 *
 * ▸ Two shortcodes: [smdp_request_help] & [smdp_request_bill]
 * ▸ Ajax handlers create $0 orders via Square REST API
 * ▸ Single admin page under existing "Square Menu" parent (slug smdp_main)
 * ▸ Allows add / copy / delete tables with customer IDs
 * ▸ View Bill functionality to fetch and display customer bills
 * ▸ Dual-mode bill lookup: Customer ID or Table Item
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SMDP_Help_Request {
  /*  Option keys  */
  private string $opt_tables = 'smdp_help_tables';
  private string $opt_help   = 'smdp_help_item_id';
  private string $opt_bill   = 'smdp_bill_item_id';
  private string $opt_loc    = 'smdp_location_id';
  private string $opt_token  = 'square_menu_access_token';
  private string $opt_disabled_mods = 'smdp_disabled_modifiers';
  private string $opt_bill_lookup_method = 'smdp_bill_lookup_method';
  private string $opt_table_items = 'smdp_table_item_ids';
  private string $nonce_action = 'smdp_help_admin';

  /* ══════════════════════  Boot  ══════════════════════ */
  public function __construct() {
    // frontend
    add_action( 'init',               [ $this, 'register_shortcodes' ] );
    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );

    // ajax
    add_action( 'wp_ajax_smdp_request_help',        [ $this, 'ajax_help' ] );
    add_action( 'wp_ajax_nopriv_smdp_request_help', [ $this, 'ajax_help' ] );
    add_action( 'wp_ajax_smdp_request_bill',        [ $this, 'ajax_bill' ] );
    add_action( 'wp_ajax_nopriv_smdp_request_bill', [ $this, 'ajax_bill' ] );
    add_action( 'wp_ajax_smdp_get_bill',            [ $this, 'ajax_get_bill' ] );
    add_action( 'wp_ajax_nopriv_smdp_get_bill',     [ $this, 'ajax_get_bill' ] );

    // admin
    add_action( 'admin_menu',            [ $this, 'add_admin_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
    add_action( 'admin_init',            [ $this, 'handle_admin_form' ] );
    add_action( 'admin_menu',            [ $this, 'add_modifier_settings_page' ] );
    add_action( 'admin_init',            [ $this, 'handle_modifier_settings_form' ] );
  }

  /* ══════════════════  Shortcodes  ══════════════════ */
  public function register_shortcodes(): void {
    add_shortcode( 'smdp_request_help', [ $this, 'sc_help' ] );
    add_shortcode( 'smdp_request_bill', [ $this, 'sc_bill' ] );
  }

  private function ensure_scripts_loaded(): void {
    // Enqueue registered scripts when shortcode is used
    if ( ! wp_script_is( 'smdp-table-setup', 'enqueued' ) ) {
      wp_enqueue_script( 'smdp-table-setup' );
    }
    if ( ! wp_script_is( 'smdp-view-bill', 'enqueued' ) ) {
      wp_enqueue_script( 'smdp-view-bill' );
      wp_localize_script( 'smdp-view-bill', 'smdpViewBill', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('smdp_get_bill')
      ]);
    }
    if ( ! wp_script_is( 'smdp-help-frontend', 'enqueued' ) ) {
      wp_enqueue_script( 'smdp-help-frontend' );
      wp_localize_script( 'smdp-help-frontend', 'smdpHelp', [ 'ajax_url'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('smdp_request_help') ] );
      wp_localize_script( 'smdp-help-frontend', 'smdpBill', [ 'ajax_url'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('smdp_request_bill') ] );
    }
  }

  private function sc_button( string $type, string $table ): string {
    $cls  = $type === 'help' ? 'smdp-help-btn' : 'smdp-bill-btn';
    $text = $type === 'help' ? 'Request Help'  : 'Request Bill';
    return sprintf('<button class="%s" data-table="%s">%s (Table %s)</button>',
      esc_attr($cls), esc_attr($table), esc_html($text), esc_html($table));
  }
  public function sc_help( $atts ): string {
    $this->ensure_scripts_loaded();
    $a = shortcode_atts(['table'=>''],$atts);
    return $a['table']? $this->sc_button('help',$a['table']) : '<p style="color:red">No table</p>';
  }
  public function sc_bill( $atts ): string {
    $this->ensure_scripts_loaded();
    $a = shortcode_atts(['table'=>''],$atts);
    return $a['table']? $this->sc_button('bill',$a['table']) : '<p style="color:red">No table</p>';
  }

  /* ══════════════════  Assets  ══════════════════ */
  public function enqueue_frontend(): void {
    // Register scripts (don't enqueue yet - will be enqueued when shortcode is used)
    wp_register_script( 'smdp-table-setup', plugins_url( '../assets/js/table-setup.js', __FILE__ ), [], null, true );
    wp_register_script( 'smdp-view-bill', plugins_url( '../assets/js/view-bill.js', __FILE__ ), [ 'jquery' ], null, true );
    wp_register_script( 'smdp-help-frontend', plugins_url( '../assets/js/help-request.js', __FILE__ ), [ 'jquery', 'smdp-table-setup' ], null, true );
  }
  
  public function enqueue_admin( $hook ): void {
    if( empty($_GET['page']) || $_GET['page'] !== 'smdp-help-tables' ) return;
    wp_enqueue_script( 'jquery' ); // Ensure jQuery is loaded for inline scripts
    wp_enqueue_script( 'smdp-help-admin', plugins_url( '../assets/js/help-admin.js', __FILE__ ), ['jquery'], null, true );
  }

  /* ══════════════════  Square order helper  ══════════════════ */
  private function create_square_order( string $item, string $table ): bool {
    $token = smdp_get_access_token(); // Use encrypted token helper
    $loc   = get_option( $this->opt_loc );
    if( ! $token || ! $loc || ! $item ) return false;

    // 1) create order
    $order_body = [
      'idempotency_key' => wp_generate_uuid4(),
      'order' => [
        'location_id' => $loc,
        'line_items'  => [ [ 'catalog_object_id'=>$item, 'quantity'=>'1' ] ],
        'note'        => "Table {$table}",
        'fulfillments'=> [ [
          'type'=>'PICKUP', 'state'=>'PROPOSED',
          'pickup_details'=>[ 'recipient'=>[ 'display_name'=>"Table {$table}" ], 'schedule_type'=>'ASAP' ]
        ] ],
      ],
    ];
    $resp = wp_remote_post('https://connect.squareup.com/v2/orders',[
      'headers'=>[
        'Authorization'=>"Bearer {$token}",
        'Content-Type'=>'application/json',
        'Accept'=>'application/json',
        'Square-Version'=>'2025-04-22',
      ],
      'body'=>wp_json_encode($order_body), 'timeout'=>15,
    ]);
    if( is_wp_error($resp) ) return false;
    $order_id = json_decode(wp_remote_retrieve_body($resp),true)['order']['id']??null;
    if( ! $order_id ) return false;

    // 2) close with $0 payment
    $pay_body=[
      'idempotency_key'=>wp_generate_uuid4(), 'source_id'=>'CASH',
      'amount_money'=>[ 'amount'=>0, 'currency'=>'CAD' ], 'order_id'=>$order_id,
      'cash_details'=>[ 'buyer_supplied_money'=>[ 'amount'=>0,'currency'=>'CAD' ], 'change_back_money'=>[ 'amount'=>0,'currency'=>'CAD' ] ]
    ];
    $pay = wp_remote_post('https://connect.squareup.com/v2/payments',[
      'headers'=>[
        'Authorization'=>"Bearer {$token}",
        'Content-Type'=>'application/json',
        'Accept'=>'application/json',
        'Square-Version'=>'2025-04-22',
      ],
      'body'=>wp_json_encode($pay_body), 'timeout'=>15,
    ]);
    return ! is_wp_error($pay);
  }

  /* ══════════════════  AJAX  ══════════════════ */
  public function ajax_help(): void {
    check_ajax_referer('smdp_request_help','security');

    // SECURITY: Rate limit help requests (5 per minute per IP)
    if ( smdp_is_rate_limited( 'request_help', 5, 60 ) ) {
        wp_send_json_error( 'Too many requests. Please wait a moment.' );
    }

    $table = smdp_sanitize_text_field($_POST['table']??'', 10);

    // SECURITY: Validate table number format
    if ( ! preg_match( '/^[A-Za-z0-9]{1,10}$/', $table ) ) {
        error_log( '[SMDP Security] Invalid table number in help request: ' . $table );
        wp_send_json_error( 'Invalid table number' );
    }

    $ok = $this->create_square_order( get_option($this->opt_help), $table );
    $ok ? wp_send_json_success() : wp_send_json_error();
  }

  public function ajax_bill(): void {
    check_ajax_referer('smdp_request_bill','security');

    // SECURITY: Rate limit bill requests (5 per minute per IP)
    if ( smdp_is_rate_limited( 'request_bill', 5, 60 ) ) {
        wp_send_json_error( 'Too many requests. Please wait a moment.' );
    }

    $table = smdp_sanitize_text_field($_POST['table']??'', 10);

    // SECURITY: Validate table number format
    if ( ! preg_match( '/^[A-Za-z0-9]{1,10}$/', $table ) ) {
        error_log( '[SMDP Security] Invalid table number in bill request: ' . $table );
        wp_send_json_error( 'Invalid table number' );
    }

    $ok = $this->create_square_order( get_option($this->opt_bill), $table );
    $ok ? wp_send_json_success() : wp_send_json_error();
  }

  public function ajax_get_bill(): void {
    check_ajax_referer('smdp_get_bill','security');

    // SECURITY: Rate limit bill lookups to prevent abuse
    if ( smdp_is_rate_limited( 'get_bill', 10, 60 ) ) {
        wp_send_json_error( 'Too many requests. Please wait a moment.', 429 );
    }

    $table = smdp_sanitize_text_field($_POST['table'] ?? '', 10); // Table numbers
    if (empty($table)) {
        wp_send_json_error('No table specified');
    }

    // SECURITY: Validate table number format (should be numeric/alphanumeric)
    if ( ! preg_match( '/^[A-Za-z0-9]{1,10}$/', $table ) ) {
        error_log( '[SMDP Security] Invalid table number format attempted: ' . $table );
        wp_send_json_error( 'Invalid table number format' );
    }

    $token = smdp_get_access_token(); // Use encrypted token helper
    $loc   = get_option($this->opt_loc);
    
    if (empty($token) || empty($loc)) {
        wp_send_json_error('Square not configured');
    }
    
    // Get lookup method
    $lookup_method = get_option($this->opt_bill_lookup_method, 'customer');
    
    error_log("[SMDP Bill] Using lookup method: {$lookup_method} for table {$table}");
    
    if ($lookup_method === 'item') {
        // Lookup by table item on the order
        $this->get_bill_by_item($table, $token, $loc);
    } else {
        // Original: Lookup by customer ID
        $this->get_bill_by_customer($table, $token, $loc);
    }
  }
  
  /* ══════════════════  Bill Lookup Methods  ══════════════════ */
  
  // Get bill by searching for table item in line items
  private function get_bill_by_item($table, $token, $loc) {
    $table_items = (array)get_option($this->opt_table_items, []);
    
    if (!isset($table_items[$table])) {
        wp_send_json_error("Table {$table} not configured with a table item ID. Please add one in the admin settings.");
    }
    
    $table_item_id = $table_items[$table];
    
    // Search for open orders at this location
    $search_body = [
        'location_ids' => [$loc],
        'query' => [
            'filter' => [
                'source_filter' => [
                    'source_names' => ['Point of Sale']
                ],
                'state_filter' => [
                    'states' => ['OPEN']
                ]
            ]
        ],
        'limit' => 100 // Get more orders to search through
    ];
    
    $response = wp_remote_post('https://connect.squareup.com/v2/orders/search', [
        'headers' => [
            'Authorization'  => "Bearer {$token}",
            'Content-Type'   => 'application/json',
            'Square-Version' => '2025-04-22',
        ],
        'body' => wp_json_encode($search_body),
        'timeout' => 15
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Connection error: ' . $response->get_error_message());
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    error_log('[SMDP Bill] Search response: ' . print_r($data, true));
    
    $orders = $data['orders'] ?? [];
    
    if (empty($orders)) {
        wp_send_json_error("No open orders found. Make sure a 'Table {$table}' item is added to the order in Square POS.");
    }
    
    // Find order containing the table item
    $matching_order = null;
    foreach ($orders as $order) {
        if (empty($order['line_items'])) continue;
        
        foreach ($order['line_items'] as $line_item) {
            // Check if this line item matches our table item
            $catalog_object_id = $line_item['catalog_object_id'] ?? '';
            
            error_log("[SMDP Bill] Checking line item {$catalog_object_id} against table item {$table_item_id}");
            
            if ($catalog_object_id === $table_item_id) {
                $matching_order = $order;
                error_log("[SMDP Bill] Found matching order: {$order['id']}");
                break 2;
            }
        }
    }
    
    if (!$matching_order) {
        wp_send_json_error("No order found with 'Table {$table}' item. Make sure to add the Table {$table} item to the order in Square POS.");
    }
    
    // Remove the table item from line_items before returning (it's just a marker)
    $filtered_line_items = array_filter($matching_order['line_items'], function($item) use ($table_item_id) {
        return ($item['catalog_object_id'] ?? '') !== $table_item_id;
    });
    
    $matching_order['line_items'] = array_values($filtered_line_items);
    
    wp_send_json_success($matching_order);
  }
  
  // Original customer lookup method
  private function get_bill_by_customer($table, $token, $loc) {
    $tables = (array)get_option($this->opt_tables, []);
    
    if (!isset($tables[$table])) {
        wp_send_json_error("Table {$table} not configured with a customer ID");
    }
    
    $customer_id = $tables[$table];
    
    // Search orders by customer ID and location
    $search_body = [
        'location_ids' => [$loc],
        'query' => [
            'filter' => [
                'customer_filter' => [
                    'customer_ids' => [$customer_id]
                ],
                'source_filter' => [
                    'source_names' => ['Point of Sale']
                ],
                'state_filter' => [
                    'states' => ['OPEN']
                ]
            ]
        ],
        'limit' => 10
    ];
    
    $response = wp_remote_post('https://connect.squareup.com/v2/orders/search', [
        'headers' => [
            'Authorization'  => "Bearer {$token}",
            'Content-Type'   => 'application/json',
            'Square-Version' => '2025-04-22',
        ],
        'body' => wp_json_encode($search_body),
        'timeout' => 15
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Connection error: ' . $response->get_error_message());
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log for debugging
    error_log('[SMDP Bill] Search response: ' . print_r($data, true));
    
    $orders = $data['orders'] ?? [];
    
    if (empty($orders)) {
        wp_send_json_error("No open orders found for Table {$table}. Make sure the customer 'Table {$table}' is attached to the order in Square POS.");
    }
    
    // Return the most recent order
    $order = $orders[0];
    error_log('[SMDP Bill] Returning order: ' . $order['id']);
    
    wp_send_json_success($order);
  }

  /* ══════════════════  Admin page  ══════════════════ */
  public function add_admin_page(): void {
    $parent='smdp_main'; $slug='smdp-help-tables'; global $submenu;
    if(isset($submenu[$parent])){foreach($submenu[$parent] as $it){if($it[2]===$slug) return;}}
    add_submenu_page($parent,'Help & Bill','Help & Bill','manage_options',$slug,[ $this,'render_admin' ]);
  }

  public function handle_admin_form(): void {
    if(empty($_POST['smdp_help_admin_nonce'])||!wp_verify_nonce($_POST['smdp_help_admin_nonce'],$this->nonce_action)) return;
    
    if(isset($_POST['smdp_save'])){
      $help_item = smdp_sanitize_text_field($_POST['smdp_help_item_id'] ?? '', 100); // Square variation IDs
      $bill_item = smdp_sanitize_text_field($_POST['smdp_bill_item_id'] ?? '', 100); // Square variation IDs
      $location = smdp_sanitize_text_field($_POST['smdp_location_id'] ?? '', 100); // Square location IDs
      $lookup_method = smdp_sanitize_text_field($_POST['smdp_bill_lookup_method'] ?? 'customer', 20); // customer/item

      // SECURITY: Validate Square IDs before saving
      $validation_error = false;

      if ( ! empty( $help_item ) && ! smdp_validate_catalog_id( $help_item ) ) {
          echo '<div class="notice notice-error is-dismissible"><p>Invalid Help Item ID format.</p></div>';
          $validation_error = true;
      }

      if ( ! empty( $bill_item ) && ! smdp_validate_catalog_id( $bill_item ) ) {
          echo '<div class="notice notice-error is-dismissible"><p>Invalid Bill Item ID format.</p></div>';
          $validation_error = true;
      }

      if ( ! empty( $location ) && ! smdp_validate_location_id( $location ) ) {
          echo '<div class="notice notice-error is-dismissible"><p>Invalid Location ID format.</p></div>';
          $validation_error = true;
      }

      // Validate lookup method is one of the allowed values
      if ( ! in_array( $lookup_method, ['customer', 'item'], true ) ) {
          $lookup_method = 'customer'; // Default to safe value
      }

      if ( ! $validation_error ) {
          // Save all values
          update_option($this->opt_help, $help_item);
          update_option($this->opt_bill, $bill_item);
          update_option($this->opt_loc, $location);
          update_option($this->opt_bill_lookup_method, $lookup_method);

          error_log('[SMDP] Help & Bill settings saved successfully');

          // Redirect to prevent form resubmission and reload fresh data
          wp_redirect(add_query_arg(['page' => 'smdp-help-tables', 'updated' => 'true'], admin_url('admin.php')));
          exit;
      }
    }
    
    // Handle adding table items
    if(isset($_POST['add_table_item'])){
        $table_items = (array)get_option($this->opt_table_items, []);
        $table_num = smdp_sanitize_text_field($_POST['table_item_number'] ?? '', 10); // Table numbers
        $item_id = smdp_sanitize_text_field($_POST['table_item_id'] ?? '', 100); // Square item IDs
        
        error_log("[SMDP] Add table item - Table: $table_num, Item ID: $item_id");
        
        if(!empty($table_num) && !empty($item_id)){
            $table_items[$table_num] = $item_id;
            $result = update_option($this->opt_table_items, $table_items);
            
            error_log("[SMDP] Table items after save: " . print_r($table_items, true));
            error_log("[SMDP] Update result: " . ($result ? 'success' : 'failed'));
            
            echo '<div class="notice notice-success is-dismissible"><p>Table ' . esc_html($table_num) . ' item added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Please fill in both table number and select an item.</p></div>';
        }
    }
    
    // Handle deleting table items
    if(!empty($_POST['delete_table_item'])&&is_array($_POST['delete_table_item'])){
        $table_items = (array)get_option($this->opt_table_items, []);
        foreach(array_keys($_POST['delete_table_item']) as $table_num){
            $clean_num = sanitize_text_field($table_num);
            unset($table_items[$clean_num]);
        }
        update_option($this->opt_table_items, $table_items);
        echo '<div class="notice notice-success is-dismissible"><p>Table item deleted successfully!</p></div>';
    }
    
    // Handle adding new table with customer ID
    if(!empty($_POST['new_table']) && !empty($_POST['new_table_customer'])){
        $tables = (array)get_option($this->opt_tables, []);
        $table_num = smdp_sanitize_text_field($_POST['new_table'], 10); // Table numbers
        $customer_id = smdp_sanitize_text_field($_POST['new_table_customer'], 100); // Square customer IDs
        
        // Store as associative array: table_number => customer_id
        $tables[$table_num] = $customer_id;
        update_option($this->opt_tables, $tables);
        echo '<div class="notice notice-success is-dismissible"><p>Table ' . esc_html($table_num) . ' added successfully!</p></div>';
    }
    
    // Handle deleting table
    if(!empty($_POST['delete_table'])&&is_array($_POST['delete_table'])){
        $tables = (array)get_option($this->opt_tables, []);
        foreach(array_keys($_POST['delete_table']) as $table_num){
            unset($tables[sanitize_text_field($table_num)]);
        }
        update_option($this->opt_tables, $tables);
        echo '<div class="notice notice-success is-dismissible"><p>Table deleted successfully!</p></div>';
    }
  }

  public function render_admin(): void {
    $help=esc_attr(get_option($this->opt_help,'')); 
    $bill=esc_attr(get_option($this->opt_bill,''));
    $loc=esc_attr(get_option($this->opt_loc,''));
    $tables=(array)get_option($this->opt_tables,[]);
    $lookup_method = get_option($this->opt_bill_lookup_method, 'customer');
    $table_items = (array)get_option($this->opt_table_items, []);
    
    // Get all items from cache for the item picker
    $all_items = get_option(SMDP_ITEMS_OPTION, []);
    $items_list = [];
    foreach ($all_items as $obj) {
        if ($obj['type'] === 'ITEM') {
            $item_data = $obj['item_data'];
            $item_id = $obj['id'];
            $item_name = $item_data['name'] ?? 'Unnamed Item';
            
            // Get first variation ID if exists
            $variation_id = '';
            if (!empty($item_data['variations'][0]['id'])) {
                $variation_id = $item_data['variations'][0]['id'];
            }
            
            $items_list[] = [
                'id' => $item_id,
                'variation_id' => $variation_id,
                'name' => $item_name
            ];
        }
    }
    
    // Sort alphabetically
    usort($items_list, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    echo '<div class="wrap"><h1>Help &amp; Bill</h1>';

    // Show success message if redirected after save
    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }

    // Add some inline styles for better UI
    echo '<style>
        .smdp-item-picker { 
            position: relative; 
            margin-bottom: 10px;
        }
        .smdp-search-box {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .smdp-item-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            z-index: 1000;
            display: none;
        }
        .smdp-item-dropdown.active {
            display: block;
        }
        .smdp-item-option {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .smdp-item-option:hover {
            background: #f5f5f5;
        }
        .smdp-selected-item {
            padding: 10px;
            background: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 4px;
            margin-bottom: 10px;
            display: none;
        }
        .smdp-selected-item.active {
            display: block;
        }
        .smdp-selected-item strong {
            color: #2e7d32;
        }
        .smdp-clear-selection {
            margin-left: 10px;
            color: #d32f2f;
            text-decoration: none;
            font-size: 12px;
        }
        .smdp-lookup-section {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .smdp-radio-option {
            margin: 10px 0;
            padding: 15px;
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            display: block;
        }
        .smdp-radio-option:hover {
            border-color: #0073aa;
        }
        .smdp-radio-option.active {
            border-color: #0073aa;
            background: #f0f7ff;
        }
        .smdp-radio-option input[type="radio"] {
            margin-right: 8px;
            vertical-align: middle;
        }
        .smdp-radio-option strong {
            display: inline;
            font-size: 14px;
        }
        .smdp-method-description {
            margin: 8px 0 0 28px;
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }
    </style>';
    
    echo '<form method="post">';
    wp_nonce_field( $this->nonce_action, 'smdp_help_admin_nonce' );

    echo '<table class="form-table">';
    
    // Help Item Picker
    echo '<tr><th>Help Item</th><td>';
    echo '<div class="smdp-item-picker">';
    echo '<div class="smdp-selected-item' . ($help ? ' active' : '') . '" id="help-selected">';
    $help_name = '';
    foreach ($items_list as $item) {
        if ($item['variation_id'] === $help) {
            $help_name = $item['name'];
            break;
        }
    }
    echo '<strong>Selected:</strong> ' . esc_html($help_name ?: 'None') . ' ';
    echo '<a href="#" class="smdp-clear-selection" data-target="help">Clear</a>';
    echo '</div>';
    echo '<input type="text" class="smdp-search-box" id="help-search" placeholder="Search for help item..." autocomplete="off">';
    echo '<input type="hidden" name="smdp_help_item_id" id="help-item-id" value="' . $help . '">';
    echo '<div class="smdp-item-dropdown" id="help-dropdown">';
    foreach ($items_list as $item) {
        if ($item['variation_id']) {
            echo '<div class="smdp-item-option" data-id="' . esc_attr($item['variation_id']) . '" data-name="' . esc_attr($item['name']) . '" data-target="help">';
            echo esc_html($item['name']);
            echo '<br><small style="color:#666;">ID: ' . esc_html($item['variation_id']) . '</small>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>';
    echo '</td></tr>';
    
    // Bill Item Picker
    echo '<tr><th>Bill Item</th><td>';
    echo '<div class="smdp-item-picker">';
    echo '<div class="smdp-selected-item' . ($bill ? ' active' : '') . '" id="bill-selected">';
    $bill_name = '';
    foreach ($items_list as $item) {
        if ($item['variation_id'] === $bill) {
            $bill_name = $item['name'];
            break;
        }
    }
    echo '<strong>Selected:</strong> ' . esc_html($bill_name ?: 'None') . ' ';
    echo '<a href="#" class="smdp-clear-selection" data-target="bill">Clear</a>';
    echo '</div>';
    echo '<input type="text" class="smdp-search-box" id="bill-search" placeholder="Search for bill item..." autocomplete="off">';
    echo '<input type="hidden" name="smdp_bill_item_id" id="bill-item-id" value="' . $bill . '">';
    echo '<div class="smdp-item-dropdown" id="bill-dropdown">';
    foreach ($items_list as $item) {
        if ($item['variation_id']) {
            echo '<div class="smdp-item-option" data-id="' . esc_attr($item['variation_id']) . '" data-name="' . esc_attr($item['name']) . '" data-target="bill">';
            echo esc_html($item['name']);
            echo '<br><small style="color:#666;">ID: ' . esc_html($item['variation_id']) . '</small>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>';
    echo '</td></tr>';
    
    echo '<tr><th>Location ID</th><td><input name="smdp_location_id" value="'.$loc.'" class="regular-text"></td></tr>';
    echo '</table>';
    
    // Bill Lookup Method Section
    echo '<div class="smdp-lookup-section">';
    echo '<h3>View Bill Lookup Method</h3>';
    echo '<p>Choose how the "View Bill" button should find orders:</p>';
    
    echo '<label class="smdp-radio-option ' . ($lookup_method === 'customer' ? 'active' : '') . '">';
    echo '<input type="radio" name="smdp_bill_lookup_method" value="customer" ' . checked($lookup_method, 'customer', false) . '>';
    echo '<strong>Customer ID Method</strong>';
    echo '<div class="smdp-method-description">Orders are found by matching the customer ID assigned to each table. Configure customer IDs in the Tables section below.</div>';
    echo '</label>';
    
    echo '<label class="smdp-radio-option ' . ($lookup_method === 'item' ? 'active' : '') . '">';
    echo '<input type="radio" name="smdp_bill_lookup_method" value="item" ' . checked($lookup_method, 'item', false) . '>';
    echo '<strong>Table Item Method</strong>';
    echo '<div class="smdp-method-description">Orders are found by looking for a specific "Table X" item on the order. Add these table items to orders in Square POS. Configure table items in the section below.</div>';
    echo '</label>';
    
    echo '</div>';

    submit_button('Save Help & Bill Settings', 'primary', 'smdp_save');
    echo '</form><hr />';

    // Table Items Configuration (for item method)
    echo '<h2>Table Items (for Item Lookup Method)</h2>';
    echo '<p class="description">These are the catalog items that will be added to orders in Square POS to identify which table the order belongs to.</p>';
    echo '<form method="post">';
    wp_nonce_field( $this->nonce_action, 'smdp_help_admin_nonce' );
    
    echo '<table class="form-table"><tr>';
    echo '<th style="width:150px;">Table Number</th>';
    echo '<td>';
    echo '<input name="table_item_number" placeholder="Table #" style="width:100px;">';
    echo '</td>';
    echo '</tr><tr>';
    echo '<th>Table Item</th>';
    echo '<td>';
    
    // Item picker for table items
    echo '<div class="smdp-item-picker">';
    echo '<div class="smdp-selected-item" id="table-item-selected">';
    echo '<strong>Selected:</strong> <span class="selected-name">None</span> ';
    echo '<a href="#" class="smdp-clear-selection" data-target="table-item">Clear</a>';
    echo '</div>';
    echo '<input type="text" class="smdp-search-box" id="table-item-search" placeholder="Search for table item..." autocomplete="off">';
    echo '<input type="hidden" name="table_item_id" id="table-item-id" value="">';
    echo '<div class="smdp-item-dropdown" id="table-item-dropdown">';
    foreach ($items_list as $item) {
        if ($item['variation_id']) {
            echo '<div class="smdp-item-option" data-id="' . esc_attr($item['variation_id']) . '" data-name="' . esc_attr($item['name']) . '" data-target="table-item">';
            echo esc_html($item['name']);
            echo '<br><small style="color:#666;">ID: ' . esc_html($item['variation_id']) . '</small>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>';
    
    echo '</td>';
    echo '</tr></table>';

    submit_button('Add Table Item', 'secondary', 'add_table_item', false);
    echo '<p class="description">Create items in Square called "Table 1", "Table 2", etc., search for them above, then add them.</p>';
    echo '</form>';
    
    if(!empty($table_items)){
        echo '<table class="widefat fixed striped"><thead><tr><th style="width:150px;">Table</th><th>Item Name</th><th>Item Variation ID</th><th style="width:100px;">Actions</th></tr></thead><tbody>';
        foreach($table_items as $table_num => $item_id){
            $t_esc = esc_html($table_num);
            $id_esc = esc_html($item_id);
            
            // Find item name
            $item_name = 'Unknown Item';
            foreach($items_list as $item) {
                if($item['variation_id'] === $item_id) {
                    $item_name = $item['name'];
                    break;
                }
            }
            
            echo '<tr>';
            echo '<td><strong>Table '.$t_esc.'</strong></td>';
            echo '<td>'.esc_html($item_name).'</td>';
            echo '<td><code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;">'.$id_esc.'</code></td>';
            echo '<td>';
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field( $this->nonce_action, 'smdp_help_admin_nonce' );
            echo '<button type="submit" name="delete_table_item['.$t_esc.']" class="button button-small" onclick="return confirm(\'Delete table item '.$t_esc.'?\');">Delete</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    
    echo '<hr />';

    // Original Tables section (for customer method)
    echo '<h2>Tables (for Customer ID Lookup Method)</h2>';
    echo '<form method="post">';
    wp_nonce_field( $this->nonce_action, 'smdp_help_admin_nonce' );
    
    echo '<table class="form-table"><tr>';
    echo '<th style="width:150px;">Table Number</th>';
    echo '<td><input name="new_table" placeholder="Table #" style="width:100px;"></td>';
    echo '</tr><tr>';
    echo '<th>Customer ID</th>';
    echo '<td><input name="new_table_customer" placeholder="Customer ID (from Square)" class="regular-text"></td>';
    echo '</tr></table>';

    submit_button('Add Table with Customer ID', 'secondary', 'add_table_button', false);
    echo '<p class="description">Create customers in Square POS (e.g., "Table 1", "Table 2"), then paste their Customer IDs here.</p>';
    echo '</form>';
    
    if(!empty($tables)){
        echo '<table class="widefat fixed striped"><thead><tr><th style="width:100px;">Table</th><th style="width:200px;">Customer ID</th><th>Help SC</th><th>Bill SC</th><th style="width:280px;">Actions</th></tr></thead><tbody>';
        foreach ($tables as $table_num => $customer_id) {
            $t_esc = esc_html($table_num);
            $cust_esc = esc_html($customer_id);
            $help_sc = '[smdp_request_help table="'.$t_esc.'"]';
            $bill_sc = '[smdp_request_bill table="'.$t_esc.'"]';
            
            echo '<tr>';
            echo '<td><strong>'.$t_esc.'</strong></td>';
            echo '<td><code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:11px;word-break:break-all;">'.$cust_esc.'</code></td>';
            echo '<td><input type="text" readonly value="'.esc_attr($help_sc).'" style="width:100%;font-size:11px;"></td>';
            echo '<td><input type="text" readonly value="'.esc_attr($bill_sc).'" style="width:100%;font-size:11px;"></td>';
            echo '<td style="white-space:nowrap;">';
            echo '<button type="button" class="button button-small smdp-copy-btn" data-text="'.esc_attr($help_sc).'" style="margin:2px;">Copy Help</button> ';
            echo '<button type="button" class="button button-small smdp-copy-btn" data-text="'.esc_attr($bill_sc).'" style="margin:2px;">Copy Bill</button> ';
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field( $this->nonce_action, 'smdp_help_admin_nonce' );
            echo '<button type="submit" name="delete_table['.$t_esc.']" class="button button-small" style="margin:2px;" onclick="return confirm(\'Delete table '.$t_esc.'?\');">Delete</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p><em>No tables configured yet.</em></p>';
    }

    echo '</div>';
  }
  
  /* --------------------------------------------------
   *  Admin: Modifier Settings
   * -------------------------------------------------- */
  public function add_modifier_settings_page(): void {
    // avoid duplicates
    global $submenu;
    $parent = 'smdp_main'; $slug = 'smdp-modifier-settings';
    if ( isset($submenu[$parent]) ) {
      foreach($submenu[$parent] as $item) {
        if($item[2]===$slug) return;
      }
    }
    add_submenu_page($parent,'Modifier Settings','Modifiers','manage_options',$slug,[ $this,'render_modifier_settings_page']);
  }
  
  public function render_modifier_settings_page(): void {
    settings_errors('smdp_modifier_admin');
    $all = get_option(SMDP_ITEMS_OPTION,[]);
    $lists = [];
    foreach($all as $o){ if($o['type']==='MODIFIER_LIST') $lists[$o['id']]=$o['modifier_list_data']['name']; }
    $disabled = (array)get_option($this->opt_disabled_mods,[]);
    ?>
    <div class="wrap"><h1>Modifier Settings</h1><form method="post">
      <?php wp_nonce_field('smdp_modifier_admin','smdp_modifier_admin_nonce'); ?>
      <table class="widefat fixed striped"><thead><tr><th>Show?</th><th>Modifier List</th></tr></thead><tbody>
      <?php foreach($lists as $id=>$name): 
          $is_visible = !in_array($id, $disabled, true);
      ?>
        <tr>
          <td><input type="checkbox" name="smdp_visible_mods[<?php echo esc_attr($id);?>]" value="1" <?php checked($is_visible, true);?>></td>
          <td><?php echo esc_html($name);?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <p><button name="smdp_save_modifiers" class="button button-primary">Save Modifier Visibility</button></p>
    </form></div>
    <?php
  }

  public function handle_modifier_settings_form(): void {
    if(
      empty($_POST['smdp_modifier_admin_nonce']) ||
      ! wp_verify_nonce($_POST['smdp_modifier_admin_nonce'],'smdp_modifier_admin')
    ) return;
    
    if(isset($_POST['smdp_save_modifiers'])){
      // Get all modifier lists
      $all = get_option(SMDP_ITEMS_OPTION,[]);
      $all_list_ids = [];
      foreach($all as $o) {
          if($o['type']==='MODIFIER_LIST') {
              $all_list_ids[] = $o['id'];
          }
      }
      
      // Get the ones marked as visible (checked boxes)
      $visible = isset($_POST['smdp_visible_mods']) ? array_keys($_POST['smdp_visible_mods']) : [];
      
      // Disabled = all lists MINUS visible ones
      $disabled = array_diff($all_list_ids, $visible);
      
      update_option($this->opt_disabled_mods, array_values($disabled));
      add_settings_error('smdp_modifier_admin','mods_saved','Modifier settings saved.','updated');
      settings_errors('smdp_modifier_admin');
    }
  }

} // end class

new SMDP_Help_Request();