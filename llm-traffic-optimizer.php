
<?php
/**
 * Plugin Name: LLM Traffic Optimizer
 * Description: 生成AIからの検索流入を最大化するためのWordPressプラグイン。LLMs.txtの生成や人気記事のまとめページを自動作成します。
 * Version: 1.0.0
 * Author: LLM Traffic Optimizer Team
 * Text Domain: llm-traffic-optimizer
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

// プラグインのパスとURLを定義
define('LTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LTO_VERSION', '1.0.0');

// ファイルの読み込み
require_once LTO_PLUGIN_DIR . 'includes/admin-menu.php';
require_once LTO_PLUGIN_DIR . 'includes/openai-integration.php';
require_once LTO_PLUGIN_DIR . 'includes/llms-txt-generator.php';
require_once LTO_PLUGIN_DIR . 'includes/summary-generator.php';
require_once LTO_PLUGIN_DIR . 'includes/analytics-tracker.php';

// プラグイン有効化時の処理
register_activation_hook(__FILE__, 'lto_activate_plugin');
function lto_activate_plugin() {
    // アナリティクステーブルの作成
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        views int(11) NOT NULL DEFAULT 0,
        ai_referrals int(11) NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // デフォルト設定
    if (!get_option('lto_openai_model')) {
        update_option('lto_openai_model', 'gpt-3.5-turbo');
    }
    
    if (!get_option('lto_temperature')) {
        update_option('lto_temperature', 0.7);
    }
    
    if (!get_option('lto_enable_auto_summaries')) {
        update_option('lto_enable_auto_summaries', 'yes');
    }
    
    // LLMS.txtファイルの初期生成
    if (function_exists('lto_generate_llms_txt')) {
        lto_generate_llms_txt();
    }
}

// プラグイン無効化時の処理
register_deactivation_hook(__FILE__, 'lto_deactivate_plugin');
function lto_deactivate_plugin() {
    // スケジュールされたイベントをクリア
    wp_clear_scheduled_hook('lto_daily_summary_generation');
}

// プラグイン削除時の処理
register_uninstall_hook(__FILE__, 'lto_uninstall_plugin');
function lto_uninstall_plugin() {
    // オプションの削除
    delete_option('lto_openai_api_key');
    delete_option('lto_openai_model');
    delete_option('lto_temperature');
    delete_option('lto_enable_auto_summaries');
    
    // アナリティクステーブルの削除
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// スタイルとスクリプトの読み込み
add_action('admin_enqueue_scripts', 'lto_enqueue_admin_assets');
function lto_enqueue_admin_assets($hook) {
    if (strpos($hook, 'llm-traffic-optimizer') !== false) {
        wp_enqueue_style('lto-admin-style', LTO_PLUGIN_URL . 'assets/css/admin-style.css', array(), LTO_VERSION);
        wp_enqueue_script('lto-admin-script', LTO_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), LTO_VERSION, true);
        
        // Ajax用のnonce
        wp_localize_script('lto-admin-script', 'ltoAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lto_ajax_nonce')
        ));
    }
}

// 毎日の自動要約生成スケジュール設定
add_action('wp', 'lto_setup_daily_schedule');
function lto_setup_daily_schedule() {
    if (!wp_next_scheduled('lto_daily_summary_generation') && get_option('lto_enable_auto_summaries') === 'yes') {
        wp_schedule_event(time(), 'daily', 'lto_daily_summary_generation');
    }
}

// 自動要約生成の実行
add_action('lto_daily_summary_generation', 'lto_generate_daily_summaries');
function lto_generate_daily_summaries() {
    if (function_exists('lto_generate_popular_summary')) {
        lto_generate_popular_summary();
    }
}

// アクティブなプラグインのリストに現在のプラグインが含まれるかチェック
function lto_is_plugin_active() {
    return in_array(plugin_basename(__FILE__), apply_filters('active_plugins', get_option('active_plugins')));
}
