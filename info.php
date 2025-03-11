
<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP情報</h1>";

// PHPバージョン情報
echo "<h2>PHP環境</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";

// ファイル情報
echo "<h2>ファイル情報</h2>";
$plugin_dir = __DIR__;
echo "<p>プラグインディレクトリ: " . $plugin_dir . "</p>";

$required_files = [
    'llm-traffic-optimizer.php',
    'includes/openai-integration.php',
    'includes/summary-generator.php',
    'includes/admin-menu.php',
    'includes/admin-settings.php',
    'includes/analytics-tracker.php',
    'includes/llms-txt-generator.php'
];

echo "<ul>";
foreach ($required_files as $file) {
    $file_path = $plugin_dir . '/' . $file;
    if (file_exists($file_path)) {
        echo "<li>✅ {$file} - 存在します (サイズ: " . filesize($file_path) . " bytes)</li>";
    } else {
        echo "<li>❌ {$file} - 存在しません</li>";
    }
}
echo "</ul>";

// 関数チェック
echo "<h2>読み込み状態と関数確認</h2>";

// インクルードを試みる
include_once('includes/openai-integration.php');
include_once('includes/summary-generator.php');

// 関数確認
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

// WordPressがロードされているかチェック
echo "<h2>WordPress環境チェック</h2>";
if (defined('ABSPATH')) {
    echo "<p>✅ WordPressが正しくロードされています</p>";
} else {
    echo "<p>❌ WordPressがロードされていません。このツールはWordPress環境内でのみ完全に機能します。</p>";
    
    echo "<h3>WordPressモック関数</h3>";
    echo "<p>テスト用にWordPress関数をモックしています...</p>";
    
    // WordPressモック関数の確認
    $wp_functions = [
        'get_option',
        'wp_parse_args',
        'is_wp_error',
        'get_post',
        'get_permalink',
        'wp_insert_post'
    ];
    
    foreach ($wp_functions as $function) {
        if (function_exists($function)) {
            echo "<p>✅ {$function} 関数はモックされています</p>";
        } else {
            echo "<p>❌ {$function} 関数はモックされていません</p>";
        }
    }
}
?>
