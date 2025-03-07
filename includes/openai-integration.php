<?php
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
function lto_validate_api_key() {
    // セキュリティチェック
    if (!check_ajax_referer('lto_validate_api_key_nonce', 'nonce', false)) {
        wp_send_json_error('セキュリティチェックに失敗しました');
        return;
    }

    // POSTからAPIキーを取得
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

    if (empty($api_key)) {
        wp_send_json_error('APIキーが空です');
        return;
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

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code === 200) {
        wp_send_json_success('APIキーが有効です');
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : '無効なAPIキーまたはAPIエラー';
        wp_send_json_error($error_message);
    }
}

// AJAXアクションを登録
add_action('wp_ajax_lto_validate_api_key', 'lto_validate_api_key');

// エラーハンドリング
try {
    // WP_Errorクラスが利用可能か確認
    if (!class_exists('WP_Error')) {
        throw new Exception('WP_Error class is not available. WordPress core may not be loaded correctly.');
    }
} catch (Exception $e) {
    // エラーをログに記録
    error_log('LLM Traffic Optimizer OpenAI Integration Error: ' . $e->getMessage());

    // 管理画面でエラーを表示
    if (is_admin()) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>LLM Traffic Optimizer OpenAI Integration Error: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}
?>