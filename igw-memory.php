<?php
/**
 * Plugin Name: IGW Memory Usage Info
 * Description: Displays memory limits, current memory usage, IP-Address, PHP-Version. Database Version and Size in the Tools and admin footer.
 * Version: 1.0.3
 * Author: iGlobalweb
 * Plugin URI: https://www.iglobalweb.com/igw-memory
 * Author URI: https://www.iglobalweb.com
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Disclaimer: The IGW Memory Usage Info plugin is provided as-is, without any warranty or guarantee of its functionality, accuracy, or suitability for any particular purpose. The developers of this plugin are not responsible for any damages, losses, or adverse effects resulting from the use of this plugin, including but not limited to data loss, site malfunction, or any other issues that may arise. It is recommended to thoroughly test the plugin in a development environment before deploying it to a production site. By installing and using this plugin, you agree to indemnify and hold harmless the developers from any liabilities, damages, or claims arising out of or in connection with the use of this plugin.
 */

defined('ABSPATH') or die('Hey, you can not access this file, you silly human');

class IGW_Memory_Plugin
{
    private $wp_memory_limit;
    private $php_memory_limit;
    private $total_memory_usage;
    private $total_memory_usaged;
    private $wp_memory_usage_percent;
    private $php_memory_usage_percent;

    public function __construct() {
        $this->calculate_memory_values();
        add_action('admin_menu', array($this, 'add_memory_usage_menu'));
    }

    private function calculate_memory_values() {
        $this->wp_memory_limit = size_format(wp_convert_hr_to_bytes(WP_MEMORY_LIMIT));
        $this->php_memory_limit = size_format(wp_convert_hr_to_bytes(ini_get('memory_limit')));
        $this->total_memory_usage = memory_get_peak_usage(true);
        $this->total_memory_usaged = size_format($this->total_memory_usage);
        $this->wp_memory_usage_percent = round(($this->total_memory_usage / wp_convert_hr_to_bytes(WP_MEMORY_LIMIT)) * 100);
        $this->php_memory_usage_percent = round(($this->total_memory_usage / wp_convert_hr_to_bytes(ini_get('memory_limit'))) * 100);
    }

    public function get_memory_values() {
        return array(
            'wp_memory_limit' => $this->wp_memory_limit,
            'php_memory_limit' => $this->php_memory_limit,
            'total_memory_usage' => $this->total_memory_usage,
            'total_memory_usaged' => $this->total_memory_usaged,
            'wp_memory_usage_percent' => $this->wp_memory_usage_percent,
            'php_memory_usage_percent' => $this->php_memory_usage_percent
        );
    }

    public function add_memory_usage_menu() {
        add_submenu_page('tools.php', 'Memory Usage Info', 'Memory Usage', 'manage_options', 'memory-usage-info', array($this, 'memory_usage_info'));
    }

    public function memory_usage_info() {
        echo '<div class="container">';
        echo '<h1>Memory Information</h1>';
        echo '<div class="row">';

        // Server Info
        echo '<div class="col-md-6">';
        echo '<div id="server-info" class="card mb-4">';
        echo '<h2 class="card-header">Server Information</h2>';
        echo '<div class="card-body">';
        echo '<p class="card-text"><strong>Server IP:</strong> ' . esc_html(filter_var(wp_unslash($_SERVER['SERVER_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'Invalid IP address') . '</p>';
        echo '<p class="card-text"><strong>PHP Version:</strong> ' . esc_html(phpversion()) . '</p>';
        echo '<p class="card-text"><strong>Memory Limit:</strong> ' . esc_html(size_format(wp_convert_hr_to_bytes(ini_get('memory_limit')))) . '</p>';
        echo '<p class="card-text"><strong>WP Memory Limit:</strong> ' . esc_html($this->total_memory_usaged) . ' of ' . esc_html($this->wp_memory_limit) . ' (';
        if ($this->wp_memory_usage_percent > 90) {
            echo '<span style="color: #FF0000;font-weight: 600;">';
        } elseif ($this->wp_memory_usage_percent > 70) {
            echo '<span style="color: #FF6600;font-weight: 600;">';
        }
        echo esc_html($this->wp_memory_usage_percent) . '%</span>)</p>';

        global $wp_version;
        echo '<p class="card-text"><strong>WordPress Version:</strong> ' . esc_html($wp_version) . '</p>';
        echo '</div></div>';
        echo '</div>'; // Close col-md-6

        // Active Plugins
        echo '<div class="col-md-6">';
        echo '<div class="card mb-4">';
        echo '<h2 class="card-header">Active Plugins</h2>';
        echo '<div class="card-body">';
        $active_plugins = get_option('active_plugins');
        echo '<table class="table">';
        echo '<thead><tr><th style="text-align: left;">Plugin</th><th style="text-align: left;">Memory Usage</th></tr></thead>';
        echo '<tbody>';
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugin_memory = memory_get_peak_usage(true);
            echo '<tr><td>' . esc_html($plugin_data['Name']) . '</td><td>' . esc_html(size_format($plugin_memory)) . '</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div></div>';
        echo '</div>'; // Close col-md-6

        echo '</div>'; // Close row

        // Active Theme and Database Info
        echo '<div class="row">';
        
        // Active Theme
        echo '<div class="col-md-6">';
        echo '<div class="card mb-4">';
        echo '<h2 class="card-header">Active Theme</h2>';
        echo '<div class="card-body">';
        $active_theme = wp_get_theme();
        echo '<p class="card-text"><strong>Theme:</strong> ' . esc_html($active_theme->get('Name')) . '</p>';
        echo '<p class="card-text"><strong>Memory Usage:</strong> ' . esc_html(size_format(memory_get_peak_usage(true))) . '</p>';
        echo '</div></div>';
        echo '</div>'; // Close col-md-6

        // Database Info
        global $wpdb;
        echo '<div class="col-md-6">';
        echo '<div class="card mb-4">';
        echo '<h2 class="card-header">Database Information</h2>';
        echo '<div class="card-body">';
        echo '<p class="card-text"><strong>Database Version:</strong> ' . esc_html($wpdb->db_version()) . '</p>';

        // Get total database size
        $tables = wp_cache_get('database_tables_status', 'database');
        if (false === $tables) {
			// This is a safe direct query because there's no user input involved.
            $tables = $wpdb->get_results("SHOW TABLE STATUS");//db call ok
            wp_cache_set('database_tables_status', $tables, 'database', 3600); // Cache for 1 hour
        }
        $total_size = 0;
        foreach ($tables as $table) {
            $total_size += $table->Data_length + $table->Index_length;
        }
        echo '<p class="card-text"><strong>Total Database Size:</strong> ' . esc_html(size_format($total_size)) . '</p>';
        echo '</div></div>';
        echo '</div>'; // Close col-md-6

        echo '</div>'; // Close row

        echo '</div>'; // Close wrap
    }
}

// Instantiate the IGW_Memory class
$igw_memory = new IGW_Memory_Plugin();

// Call the get_memory_values() method to retrieve the array data
$memory_values = $igw_memory->get_memory_values();

// Access each element of the array
$igw_wp_memory_limit = $memory_values['wp_memory_limit'];
$igw_php_memory_limit = $memory_values['php_memory_limit'];
//$total_memory_usage = $memory_values['total_memory_usage'];
$igw_total_memory_usaged = $memory_values['total_memory_usaged'];
$igw_wp_memory_usage_percent = $memory_values['wp_memory_usage_percent'];
$igw_php_memory_usage_percent = $memory_values['php_memory_usage_percent'];

// Add summary to admin footer
function igw_memory_usage_summary() {
    global $igw_total_memory_usaged, $igw_wp_memory_limit, $igw_wp_memory_usage_percent, $igw_php_memory_limit, $igw_php_memory_usage_percent;

    echo '<span>Server IP: <b>' . esc_html(filter_var(wp_unslash($_SERVER['SERVER_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'Invalid IP address') . '</b></span> | ';
    echo '<span>PHP: <b>' . esc_html(phpversion()) . '</b></span> | ';
    echo '<span>WP Memory Limit: ' . esc_html($igw_total_memory_usaged) . ' of ' . esc_html($igw_wp_memory_limit) . ' (';
    if ($igw_wp_memory_usage_percent > 90) {
        echo '<span style="color: #FF0000;font-weight: 600;">';
    } elseif ($igw_wp_memory_usage_percent > 70) {
        echo '<span style="color: #FF6600;font-weight: 600;">';
    }
    echo esc_html($igw_wp_memory_usage_percent) . '%</span>)</span> | ';
    echo '<span>PHP Memory Limit: ' . esc_html($igw_total_memory_usaged) . ' of ' . esc_html($igw_php_memory_limit) . ' (' . esc_html($igw_php_memory_usage_percent) . '%)</span>';
}
add_filter('admin_footer_text', 'igw_memory_usage_summary');

// Add settings link on plugin page
function igw_memory_setting_links($links) {
    $dashboard_link = '<a href="' . esc_url(admin_url('tools.php?page=memory-usage-info')) . '" style="color: #0073aa;font-weight: 600;">Dashboard</a>';
    $donate_link = '<a href="https://square.link/u/93D69qT5" target="_blank" style="color: #00a32a;font-weight: 600;">Donate</a>';
    // Add the dashboard link at the beginning
    array_unshift($links, $dashboard_link);
    // Add the donate link
    $links[] = $donate_link;
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'igw_memory_setting_links');
