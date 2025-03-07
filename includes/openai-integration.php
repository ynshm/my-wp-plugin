
<?php
/**
 * OpenAI API統合機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// OpenAIのAPIリクエスト関数
function lto_openai_api_request($prompt) {
    // APIキーを取得
    $api_key = get_option('lto_openai_api_key');

    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI API key is required.', 'llm-traffic-optimizer'));
    }

    // リクエストの準備
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => get_option('lto_openai_model', 'gpt-3.5-turbo'),
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a skilled content writer and SEO specialist creating high-quality, informative summaries and guides for a WordPress website.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2500,
            'temperature' => (float) get_option('lto_temperature', 0.7)
        )),
        'timeout' => 60
    );

    // APIリクエスト実行
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);

    // エラーチェック
    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error occurred.', 'llm-traffic-optimizer');

        return new WP_Error('api_error', sprintf(
            __('OpenAI API Error (Code %d): %s', 'llm-traffic-optimizer'),
            $response_code,
            $error_message
        ));
    }

    // レスポンスの処理
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['choices'][0]['message']['content'])) {
        return new WP_Error('empty_response', __('OpenAI returned an empty response.', 'llm-traffic-optimizer'));
    }

    return $body['choices'][0]['message']['content'];
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
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code === 200) {
        return true;
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error occurred.', 'llm-traffic-optimizer');
        
        return new WP_Error('api_validation_error', $error_message);
    }
}

// AJAX経由でAPIキーを検証するためのエンドポイント
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

// モデル設定の保存
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
