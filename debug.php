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

        // ファイルの最初の数行を表示
        $file_content = file_get_contents($plugin_dir . '/' . $file);
        $first_lines = explode("\n", $file_content, 5);
        echo "<br><small>ファイル冒頭: <pre>" . htmlspecialchars(implode("\n", $first_lines)) . "...</pre></small>";
    } else {
        echo "<li>❌ {$file} - <strong style='color:red'>存在しません</strong></li>";
    }
}
echo "</ul>";

// 関数の有無をチェック
echo "<h2>関数チェック</h2>";
$required_functions = [
    'lto_call_openai_api',
    'lto_generate_openai_content',
    'lto_generate_post_summary',
    'lto_generate_popular_summary',
    'lto_create_summary_post'
];

// 関数をインクルード
include_once($plugin_dir . '/includes/openai-integration.php');
include_once($plugin_dir . '/includes/summary-generator.php');

echo "<ul>";
foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "<li>✅ 関数 {$function} は定義されています</li>";
    } else {
        echo "<li>❌ 関数 {$function} は<strong style='color:red'>定義されていません</strong></li>";
    }
}
echo "</ul>";

// 次のステップ
echo "<h2>次のステップ</h2>";
echo "<p>上記のエラーを修正した後、WordPress管理画面でプラグインを有効化してみてください。</p>";
echo "<p>引き続きエラーが発生する場合は、WordPress環境を確認してください：</p>";
echo "<ol>";
echo "<li>各ファイルが正しい場所にあるか</li>";
echo "<li>関数の定義に構文エラーがないか</li>";
echo "<li>ファイルの読み込み順序が正しいか</li>";
echo "</ol>";
?>