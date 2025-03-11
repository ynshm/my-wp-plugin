
<?php
/**
 * OpenAI APIとの統合機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

/**
 * OpenAI APIを呼び出す関数
 * 
 * @param string $prompt APIに送信するプロンプト
 * @param array $options 追加オプション（モデル、温度など）
 * @return array|WP_Error 成功した場合はレスポンス、失敗した場合はエラー
 */
function lto_call_openai_api($prompt, $options = array()) {
    // APIキーを取得
    $api_key = get_option('lto_openai_api_key', '');
    
    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('OpenAI API key is not set.', 'llm-traffic-optimizer'));
    }
    
    // デフォルトオプション
    $defaults = array(
        'model' => get_option('lto_openai_model', 'gpt-3.5-turbo'),
        'temperature' => (float) get_option('lto_temperature', 0.7),
        'max_tokens' => 800,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    );
    
    // ユーザーオプションをデフォルトとマージ
    $options = wp_parse_args($options, $defaults);
    
    // APIリクエストの組み立て
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => $options['model'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty']
        )),
        'timeout' => 30  // タイムアウトを30秒に設定
    );
    
    // APIリクエストの送信
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);
    
    // エラーチェック
    if (is_wp_error($response)) {
        error_log('OpenAI API Error: ' . $response->get_error_message());
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error occurred.', 'llm-traffic-optimizer');
        error_log('OpenAI API Error: Code ' . $response_code . ' - ' . $error_message);
        return new WP_Error('api_error', $error_message);
    }
    
    // レスポンスのデコード
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($body['choices'][0]['message']['content'])) {
        return new WP_Error('empty_response', __('The API response was empty.', 'llm-traffic-optimizer'));
    }
    
    return $body;
}

/**
 * OpenAIを使用してコンテンツを生成する関数
 * 
 * @param string $prompt 生成に使用するプロンプト
 * @param array $options 追加オプション
 * @return string|WP_Error 生成されたテキストまたはエラー
 */
function lto_generate_openai_content($prompt, $options = array()) {
    $response = lto_call_openai_api($prompt, $options);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    // 生成されたテキストを取得
    $content = $response['choices'][0]['message']['content'];
    
    return trim($content);
}

/**
 * 利用可能なOpenAIモデルを取得
 * 
 * @return array 利用可能なモデルの配列
 */
function lto_get_available_models() {
    // APIキーを取得
    $api_key = get_option('lto_openai_api_key', '');
    
    if (empty($api_key)) {
        return array();
    }
    
    // キャッシュされたモデルリストを確認
    $cached_models = get_transient('lto_openai_models');
    if ($cached_models !== false) {
        return $cached_models;
    }
    
    // APIリクエストを作成
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15
    );
    
    // APIリクエストを送信
    $response = wp_remote_get('https://api.openai.com/v1/models', $request_args);
    
    // エラーチェック
    if (is_wp_error($response)) {
        error_log('OpenAI Models API Error: ' . $response->get_error_message());
        return array();
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code !== 200) {
        error_log('OpenAI Models API Error: Code ' . $response_code);
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
    
    // モデルリストをキャッシュ（24時間）
    set_transient('lto_openai_models', $gpt_models, 24 * HOUR_IN_SECONDS);

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
