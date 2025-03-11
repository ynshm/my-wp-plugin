
<?php
// テスト環境フラグを設定
define('TESTENV', true);

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>プラグイン機能テスト</h1>";

// 必要なファイルをインクルード
echo "<h2>ファイルの読み込み</h2>";

$files_to_include = [
    'includes/openai-integration.php',
    'includes/summary-generator.php'
];

foreach ($files_to_include as $file) {
    echo "ファイル {$file} を読み込み中... ";
    if (file_exists($file)) {
        include_once($file);
        echo "<span style='color: green'>成功</span><br>";
    } else {
        echo "<span style='color: red'>失敗（ファイルが存在しません）</span><br>";
    }
}

// 関数の存在を確認
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
        echo "✅ 関数 {$function} は<span style='color: green'>定義されています</span><br>";
    } else {
        echo "❌ 関数 {$function} は<span style='color: red'>定義されていません</span><br>";
    }
}

// 簡単な関数テスト
echo "<h2>関数テスト</h2>";
echo "<h3>OpenAI API呼び出しテスト</h3>";

if (function_exists('lto_call_openai_api')) {
    $result = lto_call_openai_api("これはテストプロンプトです。");
    if (is_array($result) && isset($result['choices'][0]['message']['content'])) {
        echo "<p style='color: green'>テスト成功: " . htmlspecialchars($result['choices'][0]['message']['content']) . "</p>";
    } elseif (is_wp_error($result)) {
        echo "<p style='color: red'>テスト失敗: " . $result->get_error_message() . "</p>";
    } else {
        echo "<p style='color: orange'>予期しない結果: " . print_r($result, true) . "</p>";
    }
} else {
    echo "<p style='color: red'>関数が見つかりません</p>";
}

echo "<h3>記事要約生成テスト</h3>";
if (function_exists('lto_generate_post_summary')) {
    $result = lto_generate_post_summary(1); // テスト用ID
    if (is_string($result)) {
        echo "<p style='color: green'>テスト成功:<br>" . nl2br(htmlspecialchars($result)) . "</p>";
    } elseif (is_wp_error($result)) {
        echo "<p style='color: red'>テスト失敗: " . $result->get_error_message() . "</p>";
    } else {
        echo "<p style='color: orange'>予期しない結果: " . print_r($result, true) . "</p>";
    }
} else {
    echo "<p style='color: red'>関数が見つかりません</p>";
}

echo "<h3>人気記事要約生成テスト</h3>";
if (function_exists('lto_generate_popular_summary')) {
    $result = lto_generate_popular_summary();
    if (is_array($result) && isset($result['summary'])) {
        echo "<p style='color: green'>テスト成功:<br>" . nl2br(htmlspecialchars($result['summary'])) . "</p>";
        echo "<p>投稿ID: " . $result['post_id'] . "</p>";
        echo "<p>URL: " . htmlspecialchars($result['post_url']) . "</p>";
    } elseif (is_wp_error($result)) {
        echo "<p style='color: red'>テスト失敗: " . $result->get_error_message() . "</p>";
    } else {
        echo "<p style='color: orange'>予期しない結果: " . print_r($result, true) . "</p>";
    }
} else {
    echo "<p style='color: red'>関数が見つかりません</p>";
}

echo "<p>すべてのテストが完了しました。</p>";
?>
