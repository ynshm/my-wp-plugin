
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
    if (file_exists($file)) {
        echo "<li>✅ {$file} - 存在します (サイズ: " . filesize($file) . " bytes, 更新日時: " . date("Y-m-d H:i:s", filemtime($file)) . ")</li>";
        
        // PHP構文チェック
        $output = [];
        $return_var = 0;
        exec("php -l {$file}", $output, $return_var);
        
        if ($return_var === 0) {
            echo " - <span style='color:green'>構文は正常です</span>";
        } else {
            echo " - <strong style='color:red'>構文エラーがあります:</strong> " . implode(", ", $output);
        }
        
        // ファイルの最初の数行を表示
        $file_content = file_get_contents($file);
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
echo "<p>引き続きエラーが発生する場合は、WordPress error_log を確認するか、このスクリプトの結果をもとに追加の修正を行ってください。</p>";

// トラブルシューティングアドバイス
echo "<h2>トラブルシューティングのヒント</h2>";
echo "<ol>";
echo "<li>プラグインファイルがすべて正しくアップロードされていることを確認してください</li>";
echo "<li>PHP構文エラーがあれば修正してください</li>";
echo "<li>WordPressのバージョン互換性を確認してください</li>";
echo "<li>必要なPHP拡張機能がインストールされていることを確認してください (json, curl等)</li>";
echo "<li>WordPress の設定で「WP_DEBUG」をtrueに設定すると詳細なエラー情報が得られます</li>";
echo "</ol>";

// CURL機能のテスト
echo "<h2>CURL機能テスト</h2>";
if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "<p>✅ CURL有効: バージョン " . $curl_info['version'] . "</p>";
    
    // 簡単なCURLリクエストテスト
    $ch = curl_init("https://www.example.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($result === false) {
        echo "<p>❌ CURLリクエストに失敗: " . $error . "</p>";
    } else {
        echo "<p>✅ CURLリクエスト成功 (HTTP Status: " . $info['http_code'] . ")</p>";
    }
} else {
    echo "<p>❌ <strong style='color:red'>CURL拡張機能が無効です</strong> - OpenAI APIの呼び出しに必要です</p>";
}
?>
