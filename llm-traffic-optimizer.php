<?php
/**
 * Plugin Name: LLM Traffic Optimizer
 * Description: Maximize search traffic from AI by generating summary pages with ChatGPT and implementing LLMS.txt
 * Version: 1.0
 * Author: Replit Assistant
 * License: GPL v2 or later
 */

// エラーログを有効化
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// デバッグログファイル
define('LTO_DEBUG_LOG', true);
function lto_debug_log($message) {
    if (defined('LTO_DEBUG_LOG') && LTO_DEBUG_LOG) {
        error_log(print_r($message, true));
    }
}

// PHP 7 互換性チェック
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    // プラグインをすぐに停止
    if (!function_exists('lto_php_version_error')) {
        function lto_php_version_error() {
            echo '<div class="error"><p>LLM Traffic Optimizer requires PHP 7.0 or higher. Your current PHP version is ' . PHP_VERSION . '. Please upgrade PHP or contact your hosting provider.</p></div>';
        }
    }
    add_action('admin_notices', 'lto_php_version_error');

    // プラグインを自動的に無効化
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    deactivate_plugins(plugin_basename(__FILE__));

    // アクティベーションエラーをトリガー（リダイレクト）
    if (isset($_GET['activate'])) {
        unset($_GET['activate']);
        add_action('admin_notices', function() {
            echo '<div class="error"><p>LLM Traffic Optimizer was deactivated due to PHP version incompatibility.</p></div>';
        });
    }

    return;
}

// PHPのバージョン情報をログに記録
lto_debug_log('PHP Version: ' . PHP_VERSION);

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there! I\'m just a plugin, not much I can do when called directly.';
    exit;
}

// WordPressコア関数の依存確認
$required_wp_functions = ['add_action', 'add_filter', 'get_option', 'update_option', 'wp_enqueue_script', 'wp_enqueue_style'];
$missing_functions = [];

foreach ($required_wp_functions as $function) {
    if (!function_exists($function)) {
        $missing_functions[] = $function;
    }
}

if (!empty($missing_functions)) {
    lto_debug_log('Missing WordPress core functions: ' . implode(', ', $missing_functions));

    // 管理画面で警告を表示
    add_action('admin_notices', function() use ($missing_functions) {
        echo '<div class="error"><p>LLM Traffic Optimizer: Missing WordPress core functions: ' . implode(', ', $missing_functions) . '</p></div>';
    });
}

// Define constants
define('LTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LTO_VERSION', '1.0');

// Include required files with error handling
$required_files = [
    'includes/openai-integration.php',
    'includes/llms-txt-generator.php',
    'includes/summary-generator.php',
    'includes/analytics-tracker.php',
    'includes/admin-menu.php'
];

foreach ($required_files as $file) {
    $file_path = LTO_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        try {
            require_once($file_path);
            lto_debug_log("Loaded file: " . $file);
        } catch (Exception $e) {
            lto_debug_log("Error loading file " . $file . ": " . $e->getMessage());
            // エラーが発生しても処理を続行
        }
    } else {
        lto_debug_log("File does not exist: " . $file_path);
    }
}

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
    try {
        // Create required database tables
        global $wpdb;
        $table_name = $wpdb->prefix . 'lto_analytics';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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

        // テーブル作成の確認
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            throw new Exception("Failed to create database table: $table_name");
        }

        // Schedule daily cron jobs
        if (!wp_next_scheduled('lto_daily_update')) {
            wp_schedule_event(time(), 'daily', 'lto_daily_update');
        }

        // LLMSファイルの生成は初回実行時にエラーになる可能性があるため、遅延して実行
        wp_schedule_single_event(time() + 10, 'lto_generate_initial_files');

    } catch (Exception $e) {
        // エラーを記録
        error_log('LLM Traffic Optimizer activation error: ' . $e->getMessage());

        // ユーザーに表示するエラーメッセージを設定
        set_transient('lto_activation_error', $e->getMessage(), 5 * 60);
    }
}

// 遅延実行のためのフック
add_action('lto_generate_initial_files', 'lto_generate_initial_files_callback');

function lto_generate_initial_files_callback() {
    try {
        // 関数の存在確認
        if (!function_exists('lto_generate_llms_txt') || !function_exists('lto_generate_llms_full_txt')) {
            error_log('LLM Traffic Optimizer: Required functions not loaded');
            return;
        }

        // Create required files
        if (!file_exists(ABSPATH . 'llms.txt')) {
            lto_generate_llms_txt();
        }

        if (!file_exists(ABSPATH . 'llms-full.txt')) {
            lto_generate_llms_full_txt();
        }
    } catch (Exception $e) {
        error_log('Failed to generate LLMS files: ' . $e->getMessage());
    }
}

// アクティベーションエラーを表示
add_action('admin_notices', 'lto_display_activation_error');

function lto_display_activation_error() {
    $error = get_transient('lto_activation_error');
    if ($error) {
        echo '<div class="error"><p>LLM Traffic Optimizer activation error: ' . esc_html($error) . '</p></div>';
        delete_transient('lto_activation_error');
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

// プラグイン終了時にバッファを出力
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
        // 致命的なエラーが発生した場合
        $error_message = "Fatal error occurred: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        error_log($error_message);

        // 既存のバッファをクリア
        ob_end_clean();

        // 管理画面の場合はエラーメッセージを表示
        if (is_admin()) {
            echo '<div class="error"><p>LLM Traffic Optimizerでエラーが発生しました: ' . esc_html($error_message) . '</p></div>';
        }
    } else {
        // 正常終了の場合はバッファを出力
        ob_end_flush();
    }
});