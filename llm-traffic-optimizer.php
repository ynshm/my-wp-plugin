<?php
/**
 * Plugin Name: LLM Traffic Optimizer
 * Description: Maximize search traffic from AI by generating summary pages with ChatGPT and implementing LLMS.txt
 * Version: 1.0
 * Author: Replit Assistant
 * License: GPL v2 or later
 */

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there! I\'m just a plugin, not much I can do when called directly.';
    exit;
}

// Define constants
define('LTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LTO_VERSION', '1.0');

// Include required files
require_once(LTO_PLUGIN_DIR . 'includes/admin-menu.php');
require_once(LTO_PLUGIN_DIR . 'includes/openai-integration.php');
require_once(LTO_PLUGIN_DIR . 'includes/summary-generator.php');
require_once(LTO_PLUGIN_DIR . 'includes/llms-txt-generator.php');
require_once(LTO_PLUGIN_DIR . 'includes/analytics-tracker.php');

// Display admin notice if OpenAI API key is missing
function lto_admin_notice_missing_api_key() {
    // Only show to admins
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if API key is set
    $api_key = get_option('lto_openai_api_key');
    if (empty($api_key)) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php 
                printf(
                    __('<strong>LLM Traffic Optimizer:</strong> OpenAI API Key is required for generating summaries. <a href="%s">Click here to configure it</a>.', 'llm-traffic-optimizer'),
                    admin_url('admin.php?page=llm-traffic-optimizer-settings')
                ); 
                ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'lto_admin_notice_missing_api_key');

// Activation hook
register_activation_hook(__FILE__, 'lto_activate_plugin');

function lto_activate_plugin() {
    // Create required database tables
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id mediumint(9) NOT NULL,
        views int NOT NULL DEFAULT 0,
        ai_referrals int NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create required directories
    if (!file_exists(ABSPATH . 'llms.txt')) {
        lto_generate_llms_txt();
    }

    if (!file_exists(ABSPATH . 'llms-full.txt')) {
        lto_generate_llms_full_txt();
    }

    // Schedule daily cron jobs
    if (!wp_next_scheduled('lto_daily_update')) {
        wp_schedule_event(time(), 'daily', 'lto_daily_update');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'lto_deactivate_plugin');

function lto_deactivate_plugin() {
    // Clear scheduled tasks
    wp_clear_scheduled_hook('lto_daily_update');
}

// Add rewrite rules for LLMS.txt files
add_action('init', 'lto_add_rewrite_rules');

function lto_add_rewrite_rules() {
    add_rewrite_rule('^llms\.txt$', 'index.php?lto_llms=basic', 'top');
    add_rewrite_rule('^llms-full\.txt$', 'index.php?lto_llms=full', 'top');
    flush_rewrite_rules();
}

// Add query vars
add_filter('query_vars', 'lto_add_query_vars');

function lto_add_query_vars($vars) {
    $vars[] = 'lto_llms';
    return $vars;
}

// Handle LLMS.txt file requests
add_action('template_redirect', 'lto_handle_llms_txt');

function lto_handle_llms_txt() {
    global $wp_query;

    if (isset($wp_query->query_vars['lto_llms'])) {
        header('Content-Type: text/plain');

        if ($wp_query->query_vars['lto_llms'] === 'basic') {
            echo lto_generate_llms_txt(true);
        } elseif ($wp_query->query_vars['lto_llms'] === 'full') {
            echo lto_generate_llms_full_txt(true);
        }

        exit;
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'lto_init_plugin');

function lto_init_plugin() {
    // Load text domain for translations
    load_plugin_textdomain('llm-traffic-optimizer', false, basename(dirname(__FILE__)) . '/languages');

    // Daily update hook
    add_action('lto_daily_update', 'lto_daily_update_function');
}

function lto_daily_update_function() {
    // Update LLMS.txt files
    lto_generate_llms_txt();
    lto_generate_llms_full_txt();

    // Generate summary posts if enabled
    if (get_option('lto_enable_auto_summaries', 'yes') === 'yes') {
        lto_generate_summary_posts();
    }
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'lto_settings_link');

function lto_settings_link($links) {
    $settings_link = '<a href="admin.php?page=llm-traffic-optimizer">' . __('Settings', 'llm-traffic-optimizer') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}