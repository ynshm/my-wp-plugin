<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// プラグインパスの設定
define('ABSPATH', dirname(__FILE__) . '/');
define('LTO_PLUGIN_DIR', dirname(__FILE__) . '/');
define('LTO_DEBUG', true);

// 主要なファイルを読み込み
require_once 'llm-traffic-optimizer.php';

echo "<h1>LLM Traffic Optimizer 関数チェック</h1>";

// 関数の存在チェック
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
        echo "<li>❌ 関数 {$function} は<strong style='color:red'>定義されていません</strong></li>";
    }
}
echo "</ul>";

// ファイルパスの確認
echo "<h2>ファイルパス</h2>";
echo "<p>Plugin dir: " . LTO_PLUGIN_DIR . "</p>";
echo "<p>OpenAI file: " . LTO_PLUGIN_DIR . "includes/openai-integration.php" . " - Exists: " . (file_exists(LTO_PLUGIN_DIR . "includes/openai-integration.php") ? "Yes" : "No") . "</p>";
echo "<p>Summary file: " . LTO_PLUGIN_DIR . "includes/summary-generator.php" . " - Exists: " . (file_exists(LTO_PLUGIN_DIR . "includes/summary-generator.php") ? "Yes" : "No") . "</p>";

// 次のステップの表示
echo "<h2>次のステップ</h2>";
echo "<p>すべての関数が正しく定義されていれば、プラグインは正常に動作するはずです。</p>";
echo "<p>もし関数が見つからない場合は、以下を確認してください：</p>";
echo "<ol>";
echo "<li>各ファイルが正しい場所にあるか</li>";
echo "<li>関数の定義に構文エラーがないか</li>";
echo "<li>ファイルの読み込み順序が正しいか</li>";
echo "</ol>";
?>
<?php
/**
 * Function test file for LLM Traffic Optimizer
 */

// WordPress環境のロード（直接アクセス用）
$wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    echo "WordPress環境が見つかりません。";
    exit;
}

// プラグインのパスを設定
define('LTO_PLUGIN_DIR', plugin_dir_path(__FILE__));

// 必要なファイルを直接インクルード
require_once 'includes/openai-integration.php';
require_once 'includes/summary-generator.php';

echo "<h1>LLM Traffic Optimizer 関数テスト</h1>";

// 関数のチェック
$functions = [
    'lto_call_openai_api',
    'lto_generate_openai_content',
    'lto_generate_post_summary',
    'lto_generate_popular_summary',
    'lto_create_summary_post'
];

foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "<p>✅ 関数 {$function} は定義されています</p>";
    } else {
        echo "<p>❌ 関数 {$function} は定義されていません</p>";
    }
}

// デバッグ情報
echo "<h2>デバッグ情報</h2>";
echo "<p>PHP バージョン: " . PHP_VERSION . "</p>";
echo "<p>インクルードされているファイル:</p>";
echo "<ul>";
$included_files = get_included_files();
foreach ($included_files as $file) {
    if (strpos($file, 'llm-traffic-optimizer') !== false) {
        echo "<li>" . $file . "</li>";
    }
}
echo "</ul>";
?>
