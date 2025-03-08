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

// デバッグモード（開発時のみtrueに）
define('LTO_DEBUG', false);

// 基本的な安全なローディング機能
function lto_load_file($file) {
    $file_path = LTO_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    if (LTO_DEBUG) {
        error_log('LLM Traffic Optimizer: ファイルが見つかりません: ' . $file_path);
    }
    return false;
}

// 基本機能のみロード - エラーが発生してもプラグインが完全に機能停止しないように
lto_load_file('includes/admin-menu.php');
lto_load_file('includes/admin-settings.php');
lto_load_file('includes/openai-integration.php');

// 初期化段階でのローディング
function lto_init() {
    // 必要最小限のファイルのみロード
    lto_load_file('includes/analytics-tracker.php');
}
add_action('init', 'lto_init');

// 条件付きローディング - 管理画面でのみ特定の機能を有効化
function lto_admin_init() {
    if (is_admin()) {
        // スタイルとスクリプトのエンキュー
        add_action('admin_enqueue_scripts', 'lto_enqueue_admin_assets');
    }
}
add_action('admin_init', 'lto_admin_init');

// プラグイン有効化時の処理
register_activation_hook(__FILE__, 'lto_activate_plugin');
function lto_activate_plugin() {
    // 基本的なデータベーステーブルの作成
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
    add_option('lto_openai_model', 'gpt-3.5-turbo');
    add_option('lto_temperature', 0.7);
    add_option('lto_enable_auto_summaries', 'yes');
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

// 管理者向けのメッセージ表示機能
function lto_admin_notice() {
    $screen = get_current_screen();
    if ($screen->id === 'plugins' || strpos($screen->id, 'llm-traffic-optimizer') !== false) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>LLM Traffic Optimizer:</strong> APIキーを設定して機能を有効化してください。</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'lto_admin_notice');


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
?>