
<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>WordPress Plugin Debug</h1>";

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
    if (file_exists($file)) {
        echo "<li>✅ {$file} - 存在します</li>";
        
        // PHP構文チェック
        $output = [];
        $return_var = 0;
        exec("php -l {$file}", $output, $return_var);
        
        if ($return_var === 0) {
            echo " - 構文は正常です";
        } else {
            echo " - <strong style='color:red'>構文エラーがあります:</strong> " . implode(", ", $output);
        }
    } else {
        echo "<li>❌ {$file} - <strong style='color:red'>存在しません</strong></li>";
    }
}
echo "</ul>";

echo "<h2>次のステップ</h2>";
echo "<p>上記のエラーを修正した後、WordPress管理画面でプラグインを有効化してみてください。</p>";
echo "<p>引き続きエラーが発生する場合は、WordPress error_log を確認するか、このスクリプトの結果をもとに追加の修正を行ってください。</p>";
?>
