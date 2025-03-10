
<?php
/**
 * OpenAI API 統合機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// グローバル関数定義であることを明示
global $lto_api_loaded;
$lto_api_loaded = true;

// OpenAI APIリクエスト関数
if (!function_exists('lto_call_openai_api')) {
    function lto_call_openai_api($prompt) {
        // APIキーの取得
        $api_key = get_option('lto_openai_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('OpenAI APIキーが設定されていません。', 'llm-traffic-optimizer'));
        }

        // APIモデルとパラメータの取得
        $model = get_option('lto_openai_model', 'gpt-3.5-turbo');
        $temperature = (float) get_option('lto_temperature', 0.7);

        // リクエストデータの準備
        $request_data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => 1500
        );

        // APIリクエストの実行
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 60
        ));

        // レスポンスの処理
        if (is_wp_error($response)) {
            error_log('OpenAI API error: ' . $response->get_error_message());
            return new WP_Error('api_error', __('OpenAI APIリクエストに失敗しました: ', 'llm-traffic-optimizer') . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : __('不明なエラーが発生しました。', 'llm-traffic-optimizer');
            error_log('OpenAI API error: ' . $error_message);
            return new WP_Error('api_error', __('OpenAI APIエラー: ', 'llm-traffic-optimizer') . $error_message);
        }

        // 成功時の処理
        if (isset($response_body['choices'][0]['message']['content'])) {
            return $response_body['choices'][0]['message']['content'];
        } else {
            return new WP_Error('no_content', __('APIからのレスポンスにコンテンツが含まれていませんでした。', 'llm-traffic-optimizer'));
        }
    }
}

// OpenAI APIリクエスト用の関数
if (!function_exists('lto_generate_openai_content')) {
    function lto_generate_openai_content($prompt) {
        if (!function_exists('lto_call_openai_api')) {
            error_log('LLM Traffic Optimizer: OpenAI APIの関数が読み込まれていません');
            return new WP_Error('missing_function', __('OpenAI API functions are not loaded.', 'llm-traffic-optimizer'));
        }
        return lto_call_openai_api($prompt);
    }
}

// OpenAIモデルリストの取得
function lto_get_available_models() {
    // API キーの取得
    $api_key = get_option('lto_openai_api_key', '');

    if (empty($api_key)) {
        return array();
    }

    // リクエストデータの準備
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'timeout' => 15
    );

    // APIリクエストの送信
    $response = wp_remote_get('https://api.openai.com/v1/models', $request_args);

    // エラーチェック
    if (is_wp_error($response)) {
        return array();
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code !== 200) {
        return array();
    }

    // レスポンスの処理
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['data'])) {
        return array();
    }

    // 使用可能なGPTモデルをフィルタリング
    $gpt_models = array();

    foreach ($body['data'] as $model) {
        $id = $model['id'];

        // GPTモデルのみをフィルタリング
        if (strpos($id, 'gpt-') === 0) {
            $gpt_models[$id] = $id;
        }
    }

    return $gpt_models;
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
    $available_models = lto_get_available_models();
    if (!in_array($model, array_keys($available_models))) {
        $model = 'gpt-3.5-turbo'; 
    }
    
    if ($temperature < 0 || $temperature > 1) {
        $temperature = 0.7; 
    }
    
    // 設定を保存
    update_option('lto_openai_model', $model);
    update_option('lto_temperature', $temperature);
    
    wp_send_json_success(__('Model settings saved successfully.', 'llm-traffic-optimizer'));
}
