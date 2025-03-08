
<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// システム情報
echo "<h1>PHP情報</h1>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>WordPress Version: " . (defined('ABSPATH') ? get_bloginfo('version') : 'WordPress not loaded') . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// エラーログの場所を表示
echo "<h2>エラーログ情報</h2>";
echo "<p>PHP error_log path: " . ini_get('error_log') . "</p>";

// プラグインのデバッグ
echo "<h2>プラグインのデバッグ</h2>";
echo "<p>プラグインディレクトリが存在するか: ";
$plugin_dir = dirname(__FILE__);
echo file_exists($plugin_dir) ? "はい" : "いいえ";
echo "</p>";

echo "<p>プラグインファイルリスト:</p><ul>";
if ($handle = opendir($plugin_dir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            echo "<li>$entry</li>";
        }
    }
    closedir($handle);
}
echo "</ul>";

// PHPの詳細情報
echo "<h2>PHP詳細情報</h2>";
phpinfo();

// includes ディレクトリのファイル一覧
$includes_dir = $plugin_dir . '/includes';
echo "<p>includes ディレクトリが存在するか: ";
echo file_exists($includes_dir) ? "はい" : "いいえ";
echo "</p>";

if (file_exists($includes_dir)) {
    echo "<p>includes ディレクトリのファイル:</p><ul>";
    if ($handle = opendir($includes_dir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                echo "<li>$entry</li>";
            }
        }
        closedir($handle);
    }
    echo "</ul>";
}

// PHPモジュール一覧
echo "<h2>読み込まれているPHPモジュール</h2>";
echo "<ul>";
$modules = get_loaded_extensions();
foreach ($modules as $module) {
    echo "<li>$module</li>";
}
echo "</ul>";

// データベース接続テスト（WordPressが読み込まれている場合）
if (defined('ABSPATH')) {
    echo "<h2>WordPress データベース接続テスト</h2>";
    global $wpdb;
    if (isset($wpdb)) {
        $test_query = $wpdb->get_var("SELECT 1");
        echo "<p>Database connection: " . ($test_query === "1" ? "成功" : "失敗") . "</p>";
    } else {
        echo "<p>$wpdb オブジェクトが利用できません</p>";
    }
}
?>
