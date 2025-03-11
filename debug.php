
<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>WordPress Plugin Debug</h1>";

// PHPバージョン確認
echo "<h2>PHP環境</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";

// ディレクトリとファイルの権限確認
echo "<h2>ディレクトリ権限</h2>";
$plugin_dir = __DIR__;
$includes_dir = $plugin_dir . '/includes';

echo "<p>Plugin directory: " . $plugin_dir . " - Permissions: " . substr(sprintf('%o', fileperms($plugin_dir)), -4) . "</p>";
echo "<p>Includes directory: " . $includes_dir . " - Permissions: " . (file_exists($includes_dir) ? substr(sprintf('%o', fileperms($includes_dir)), -4) : "Not found") . "</p>";

// ファイルの検証
$plugin_files = [
    'llm-traffic-optimizer.php',
    'includes/admin-menu.php',
    'includes/admin-settings.php',
    'includes/analytics-tracker.php',
    'includes/llms-txt-generator.php',
    'includes/openai-integration.php',
    'includes/summary-generator.php'
];

echo "<h2>ファイル検証</h2>";
echo "<ul>";
foreach ($plugin_files as $file) {
    if (file_exists($plugin_dir . '/' . $file)) {
        echo "<li>✅ {$file} - 存在します (サイズ: " . filesize($plugin_dir . '/' . $file) . " bytes, 更新日時: " . date("Y-m-d H:i:s", filemtime($plugin_dir . '/' . $file)) . ")</li>";

        // PHP構文チェック
        $output = [];
        $return_var = 0;
        exec("php -l " . $plugin_dir . '/' . $file, $output, $return_var);

        if ($return_var === 0) {
            echo " - <span style='color:green'>構文は正常です</span>";
        } else {
            echo " - <strong style='color:red'>構文エラーがあります:</strong> " . implode(", ", $output);
        }
    } else {
        echo "<li>❌ {$file} - <strong style='color:red'>存在しません</strong></li>";
    }
}
echo "</ul>";

// 関数チェック
echo "<h2>関数チェック</h2>";

// 関数をインクルード
include_once($plugin_dir . '/includes/openai-integration.php');
include_once($plugin_dir . '/includes/summary-generator.php');

$required_functions = [
    'lto_call_openai_api',
    'lto_generate_openai_content',
    'lto_generate_post_summary',
    'lto_generate_popular_summary',
    'lto_create_summary_post'
];

echo "<ul>";
foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "<li>✅ 関数 {$function} は定義されています</li>";
    } else {
        echo "<li>❌ 関数 {$function} は定義されていません</li>";
    }
}
echo "</ul>";

// WordPressモック関数
echo "<h2>WordPress互換性テスト</h2>";
echo "<p>注: この環境はWordPress環境ではないため、WordPress関数のモック処理を実行しています。</p>";

// WordPress関数のモックを定義
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

// 実際のテスト実行
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
