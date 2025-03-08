
<?php
/**
 * 管理画面の設定処理
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// サマリー設定保存のAJAXハンドラー
add_action('wp_ajax_lto_save_summary_settings', 'lto_ajax_save_summary_settings');

function lto_ajax_save_summary_settings() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');
    
    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }
    
    // 自動サマリー生成の設定を取得
    $enable_auto_summaries = isset($_POST['enable_auto_summaries']) ? sanitize_text_field($_POST['enable_auto_summaries']) : 'no';
    
    // 設定を保存
    update_option('lto_enable_auto_summaries', $enable_auto_summaries);
    
    wp_send_json_success(__('Summary settings saved successfully.', 'llm-traffic-optimizer'));
}

// OpenAI APIキーの表示文字列をセキュアに生成
function lto_get_masked_api_key() {
    $api_key = get_option('lto_openai_api_key', '');
    
    if (empty($api_key)) {
        return '';
    }
    
    // APIキーの最初と最後の4文字だけを表示し、残りを*で隠す
    $length = strlen($api_key);
    if ($length <= 8) {
        return str_repeat('•', $length); // キーが短すぎる場合は全て隠す
    }
    
    $visible_prefix = substr($api_key, 0, 4);
    $visible_suffix = substr($api_key, -4);
    $masked_part = str_repeat('•', $length - 8);
    
    return $visible_prefix . $masked_part . $visible_suffix;
}
