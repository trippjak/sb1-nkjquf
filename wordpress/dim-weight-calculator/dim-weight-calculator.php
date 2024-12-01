<?php
/**
 * Plugin Name: Dimensional Weight Calculator
 * Description: A comprehensive dimensional weight calculator for shipping and freight calculations
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register activation hook
register_activation_hook(__FILE__, 'dwc_activate_plugin');

function dwc_activate_plugin() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'dim_weight_settings';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        carrier_name varchar(100) NOT NULL,
        divisor decimal(10,2) NOT NULL,
        service_level varchar(100),
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add default settings if table is empty
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($count == 0) {
        $default_settings = [
            ['USPS', 166.00, 'Standard'],
            ['FedEx', 139.00, 'Standard'],
            ['UPS', 139.00, 'Standard']
        ];
        
        foreach ($default_settings as $setting) {
            $wpdb->insert($table_name, [
                'carrier_name' => $setting[0],
                'divisor' => $setting[1],
                'service_level' => $setting[2]
            ]);
        }
    }
}

// Add admin menu
add_action('admin_menu', 'dwc_add_admin_menu');

function dwc_add_admin_menu() {
    add_menu_page(
        'DIM Weight Calculator Settings',
        'DIM Calculator',
        'manage_options',
        'dim-weight-calculator',
        'dwc_settings_page',
        'dashicons-calculator'
    );
}

function dwc_settings_page() {
    include plugin_dir_path(__FILE__) . 'admin/settings.php';
}

// Enqueue necessary scripts and styles
function dwc_enqueue_scripts() {
    // Only enqueue on pages with our shortcode
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dim_weight_calculator')) {
        // Enqueue React and ReactDOM
        wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18.3.1', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', [], '18.3.1', true);
        
        // Enqueue our bundled calculator
        wp_enqueue_script(
            'dim-weight-calculator',
            plugins_url('dist/assets/index-AoLHerjI.js', __FILE__),
            ['react', 'react-dom'],
            '1.0.0',
            true
        );
    
        // Enqueue styles
        wp_enqueue_style(
            'dim-weight-calculator-styles',
            plugins_url('dist/assets/index-EewozFCa.css', __FILE__),
            [],
            '1.0.0'
        );

        // Add initialization script for Divi compatibility
        add_action('wp_footer', 'dwc_init_script');
    }
}
add_action('wp_enqueue_scripts', 'dwc_enqueue_scripts');

// Initialize calculator after Divi loads
function dwc_init_script() {
    ?>
    <script>
    function initDimWeightCalculator() {
        const container = document.getElementById('dim-weight-calculator');
        if (container && window.React && window.ReactDOM) {
            window.ReactDOM.createRoot(container).render(
                window.React.createElement(window.DimWeightCalculator.default)
            );
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initDimWeightCalculator);

    // Re-initialize when Divi updates the DOM
    if (window.jQuery) {
        jQuery(document).on('et_builder_api_ready', function() {
            initDimWeightCalculator();
        });
    }
    </script>
    <?php
}

// Register shortcode
function dwc_shortcode() {
    ob_start();
    ?>
    <div id="dim-weight-calculator" class="dim-weight-calculator-container"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('dim_weight_calculator', 'dwc_shortcode');