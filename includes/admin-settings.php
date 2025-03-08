
<?php
/**
 * 管理画面の設定処理
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// APIキー検証と保存のAJAXハンドラ
add_action('wp_ajax_lto_validate_api_key', 'lto_ajax_validate_api_key');

function lto_ajax_validate_api_key() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');
    
    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }
    
    // APIキーを取得
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    
    if (empty($api_key)) {
        wp_send_json_error(__('API key cannot be empty', 'llm-traffic-optimizer'));
        return;
    }
    
    // APIキーの検証
    $validation_result = lto_validate_api_key($api_key);
    
    if (is_wp_error($validation_result)) {
        wp_send_json_error($validation_result->get_error_message());
    } else {
        // 有効なAPIキーを保存
        update_option('lto_openai_api_key', $api_key);
        wp_send_json_success(__('API key validated and saved successfully.', 'llm-traffic-optimizer'));
    }
}

// モデル設定保存のAJAXハンドラー
add_action('wp_ajax_lto_save_model_settings', 'lto_ajax_save_model_settings');

function lto_ajax_save_model_settings() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');
    
    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }
    
    // 設定を取得して保存
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-3.5-turbo';
    $temperature = isset($_POST['temperature']) ? (float) $_POST['temperature'] : 0.7;
    
    // 値の検証
    $available_models = array_keys(lto_get_openai_models());
    if (!in_array($model, $available_models)) {
        $model = 'gpt-3.5-turbo'; // デフォルトに戻す
    }
    
    if ($temperature < 0 || $temperature > 1) {
        $temperature = 0.7; // デフォルトに戻す
    }
    
    // 設定を保存
    update_option('lto_openai_model', $model);
    update_option('lto_temperature', $temperature);
    
    wp_send_json_success(__('Model settings saved successfully.', 'llm-traffic-optimizer'));
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

// APIキーの検証関数
function lto_validate_api_key($api_key) {
    if (empty($api_key)) {
        return new WP_Error('empty_api_key', __('API key cannot be empty', 'llm-traffic-optimizer'));
    }

    // 簡単なリクエストでAPIキーの有効性をチェック
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello, this is a test message to validate the API key.'
                )
            ),
            'max_tokens' => 5
        )),
        'timeout' => 15
    );

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);

    // レスポンスチェック
    if (is_wp_error($response)) {
        error_log('OpenAI API validation error: ' . $response->get_error_message());
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code === 200) {
        return true;
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error occurred.', 'llm-traffic-optimizer');
        error_log('OpenAI API validation error: Code ' . $response_code . ' - ' . $error_message);
        return new WP_Error('api_validation_error', $error_message);
    }
}

// OpenAIモデルリストの取得
function lto_get_openai_models() {
    return array(
        'gpt-4o' => 'GPT-4o',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4' => 'GPT-4',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K'
    );
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
