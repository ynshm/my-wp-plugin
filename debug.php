<?php
// テスト環境フラグを設定
define('TESTENV', true);

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>WordPress Plugin Debug</h1>";

// PHP環境情報
echo "<h2>PHP環境</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";

// ディレクトリ権限チェック
echo "<h2>ディレクトリ権限</h2>";
$plugin_dir = __DIR__;
$includes_dir = $plugin_dir . '/includes';

echo "<p>Plugin directory: " . $plugin_dir . " - Permissions: " . substr(sprintf('%o', fileperms($plugin_dir)), -4) . "</p>";
echo "<p>Includes directory: " . $includes_dir . " - Permissions: " . substr(sprintf('%o', fileperms($includes_dir)), -4) . "</p>";

// 必要なファイルの存在確認
echo "<h2>ファイル検証</h2>";
echo "<ul>";

$required_files = [
    'llm-traffic-optimizer.php',
    'includes/admin-menu.php',
    'includes/admin-settings.php',
    'includes/analytics-tracker.php',
    'includes/llms-txt-generator.php',
    'includes/openai-integration.php',
    'includes/summary-generator.php'
];

foreach ($required_files as $file) {
    $file_path = $plugin_dir . '/' . $file;
    if (file_exists($file_path)) {
        $size = filesize($file_path);
        $mtime = date('Y-m-d H:i:s', filemtime($file_path));
        echo "<li>✅ {$file} - 存在します (サイズ: {$size} bytes, 更新日時: {$mtime})</li>";

        // 構文チェック
        $output = [];
        $return_var = 0;
        exec("php -l {$file_path}", $output, $return_var);

        if ($return_var === 0) {
            echo " - <span style='color:green'>構文は正常です</span>";
        } else {
            echo " - <span style='color:red'>構文エラー: " . implode("\n", $output) . "</span>";
        }
    } else {
        echo "<li>❌ {$file} - 存在しません</li>";
    }
}

echo "</ul>";

// WordPress関数のモック
function __($text, $domain = 'default') {
    return $text;
}

// 必要なファイルをインクルードする前にモック関数を追加
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // テスト環境ではアクションをモック
        return true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        echo json_encode(['success' => false, 'data' => $data]);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}


// 必要なファイルを読み込み
try {
    if (file_exists($plugin_dir . '/includes/openai-integration.php')) {
        include_once($plugin_dir . '/includes/openai-integration.php');
    }

    if (file_exists($plugin_dir . '/includes/summary-generator.php')) {
        include_once($plugin_dir . '/includes/summary-generator.php');
    }

    // 関数チェック
    echo "<h2>関数チェック</h2>";
    $required_functions = [
        'lto_call_openai_api',
        'lto_generate_openai_content',
        'lto_generate_post_summary',
        'lto_generate_popular_summary',
        'lto_create_summary_post'
    ];

    foreach ($required_functions as $function) {
        if (function_exists($function)) {
            echo "✅ 関数 {$function} は定義されています<br>";
        } else {
            echo "❌ 関数 {$function} は定義されていません<br>";
        }
    }

    // 簡単なユニットテスト
    if (function_exists('lto_generate_post_summary')) {
        echo "<h3>記事要約生成テスト</h3>";
        $result = lto_generate_post_summary(1);
        if (is_string($result)) {
            echo "<p>テスト成功: " . htmlspecialchars($result) . "</p>";
        } else {
            echo "<p>テスト失敗: " . (is_wp_error($result) ? $result->get_error_message() : print_r($result, true)) . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<h2>エラー発生</h2>";
    echo "<p>エラーメッセージ: " . $e->getMessage() . "</p>";
    echo "<p>ファイル: " . $e->getFile() . " (行: " . $e->getLine() . ")</p>";
}

// WordPressモック関数 (オリジナルのコードを保持)
echo "<h2>WordPress互換性テスト</h2>";
echo "<p>注: この環境はWordPress環境ではないため、WordPress関数のモック処理を実行しています。</p>";

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return is_object($thing) && is_a($thing, 'WP_Error');
    }
    echo "<p>✅ is_wp_error関数をモックしました</p>";
}

if (!class_exists('WP_Error')) {
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
    echo "<p>✅ WP_Errorクラスをモックしました</p>";
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // オプションのモック
        $options = [
            'lto_openai_api_key' => 'sk-mock-api-key',
            'lto_openai_model' => 'gpt-3.5-turbo',
            'lto_temperature' => 0.7,
            'lto_enable_auto_summaries' => 'yes'
        ];

        return $options[$option] ?? $default;
    }
    echo "<p>✅ get_option関数をモックしました</p>";
}

if (!function_exists('wp_parse_args')) {
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
    echo "<p>✅ wp_parse_args関数をモックしました</p>";
}

// 実際のテスト実行 (一部修正)
echo "<h2>関数テスト</h2>";
try {
    echo "<p>OpenAI関数のテスト（モック環境）...</p>";
    // 実際はAPIを呼び出さないモック
    if (function_exists('lto_call_openai_api')) {
        echo "<p>✅ OpenAI統合機能が正しく読み込まれています</p>";
    } else {
        echo "<p>❌ OpenAI統合機能が読み込まれていません</p>";
    }

    echo "<p>サマリー生成機能のテスト（モック環境）...</p>";
    if (function_exists('lto_generate_post_summary')) {
        echo "<p>✅ サマリー生成機能が正しく読み込まれています</p>";
    } else {
        echo "<p>❌ サマリー生成機能が読み込まれていません</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ テスト中にエラーが発生しました: " . $e->getMessage() . "</p>";
}
?>