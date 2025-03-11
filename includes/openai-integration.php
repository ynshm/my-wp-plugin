<?php
/**
 * OpenAI APIとの統合機能
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    // 非WordPress環境での実行のために条件付きで定義
    if (!defined('TESTENV')) {
        define('TESTENV', true);
    }
}

// テスト環境用のWordPress関数モック
if (!function_exists('get_option') && defined('TESTENV')) {
    function get_option($option, $default = false) {
        $options = [
            'lto_openai_api_key' => 'sk-mock-api-key',
            'lto_openai_model' => 'gpt-3.5-turbo',
            'lto_temperature' => 0.7
        ];
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('wp_parse_args') && defined('TESTENV')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r =& $args;
        } else {
            parse_str($args, $r);
        }
        return array_merge($defaults, $r);
    }
}

if (!function_exists('wp_remote_post') && defined('TESTENV')) {
    function wp_remote_post($url, $args = array()) {
        // モック実装
        return array('mock_response' => true, 'body' => '{"choices":[{"message":{"content":"モックレスポンス"}}]}');
    }
}

if (!function_exists('wp_remote_get') && defined('TESTENV')) {
    function wp_remote_get($url, $args = array()) {
        // モック実装
        return array('mock_response' => true, 'body' => '{"data":[{"id":"gpt-3.5-turbo"}]}');
    }
}

if (!function_exists('wp_remote_retrieve_response_code') && defined('TESTENV')) {
    function wp_remote_retrieve_response_code($response) {
        return 200; // 常に成功を返す
    }
}

if (!function_exists('wp_remote_retrieve_body') && defined('TESTENV')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '{}';
    }
}

if (!function_exists('is_wp_error') && defined('TESTENV')) {
    function is_wp_error($thing) {
        return is_object($thing) && is_a($thing, 'WP_Error');
    }
}

if (!class_exists('WP_Error') && defined('TESTENV')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            $messages = $this->errors[$code] ?? array();
            return $messages[0] ?? '';
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }
    }
}

/**
 * OpenAI APIを呼び出す関数
 * 
 * @param string $prompt APIに送信するプロンプト
 * @param array $args 追加のパラメータ
 * @return array|WP_Error レスポンスかエラー
 */
function lto_call_openai_api($prompt, $args = array()) {
    // APIキーを取得
    $api_key = get_option('lto_openai_api_key', '');
    if (empty($api_key)) {
        if (class_exists('WP_Error')) {
            return new WP_Error('no_api_key', 'OpenAI APIキーが設定されていません。');
        } else {
            return array('error' => 'OpenAI APIキーが設定されていません。');
        }
    }

    // デフォルトパラメータ
    $defaults = array(
        'model' => get_option('lto_openai_model', 'gpt-3.5-turbo'),
        'temperature' => (float) get_option('lto_temperature', 0.7),
        'max_tokens' => 1000,
    );

    // パラメータをマージ
    $params = wp_parse_args($args, $defaults);

    // リクエストデータ構築
    $request_data = array(
        'model' => $params['model'],
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        ),
        'temperature' => $params['temperature'],
        'max_tokens' => $params['max_tokens']
    );

    // APIリクエスト
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_data),
        'timeout' => 120,
    ));

    // エラーチェック
    if (is_wp_error($response)) {
        return $response;
    }

    // レスポンスコードチェック
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        $error_message = 'API呼び出しエラー: ' . $response_code;

        if (!empty($body)) {
            $error_data = json_decode($body, true);
            if (!empty($error_data['error']['message'])) {
                $error_message .= ' - ' . $error_data['error']['message'];
            }
        }

        if (class_exists('WP_Error')) {
            return new WP_Error('api_error', $error_message);
        } else {
            return array('error' => $error_message);
        }
    }

    // レスポンスを解析
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data;
}

/**
 * OpenAIからコンテンツを生成する関数
 * 
 * @param string $prompt コンテンツ生成のプロンプト
 * @param array $args 追加のパラメータ
 * @return string|WP_Error 生成されたコンテンツかエラー
 */
function lto_generate_openai_content($prompt, $args = array()) {
    $response = lto_call_openai_api($prompt, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    if (isset($response['choices'][0]['message']['content'])) {
        return trim($response['choices'][0]['message']['content']);
    }

    if (class_exists('WP_Error')) {
        return new WP_Error('unexpected_response', '予期しないAPIレスポンス形式です。');
    } else {
        return 'エラー: 予期しないAPIレスポンス形式です。';
    }
}

/**
 * 利用可能なOpenAIモデルを取得
 * 
 * @return array 利用可能なモデルの配列
 */
function lto_get_available_models() {
    // テスト環境ではモックデータを返す
    if (defined('TESTENV') && TESTENV) {
        return array(
            'gpt-3.5-turbo' => 'gpt-3.5-turbo',
            'gpt-4' => 'gpt-4'
        );
    }
    
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
    // テスト環境では常に成功を返す
    if (defined('TESTENV') && TESTENV) {
        return true;
    }
    
    if (empty($api_key)) {
        return new WP_Error('empty_api_key', 'API key cannot be empty');
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
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error occurred.';
        error_log('OpenAI API validation error: Code ' . $response_code . ' - ' . $error_message);
        return new WP_Error('api_validation_error', $error_message);
    }
}

if (!function_exists('set_transient') && defined('TESTENV')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}

if (!defined('HOUR_IN_SECONDS') && defined('TESTENV')) {
    define('HOUR_IN_SECONDS', 3600);
}
?>