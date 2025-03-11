<?php
/**
 * 記事要約生成機能
 */

// 必要なファイルをインクルード
if (file_exists(dirname(__FILE__) . '/openai-integration.php')) {
    require_once dirname(__FILE__) . '/openai-integration.php';
}

// テスト環境用のWordPress関数モック
if (!function_exists('get_post') && defined('TESTENV')) {
    function get_post($post_id = null, $output = OBJECT, $filter = 'raw') {
        return (object) [
            'ID' => $post_id ?? 1,
            'post_title' => 'テスト投稿タイトル',
            'post_content' => 'これはテスト投稿の内容です。LLMベースのAIについての解説が含まれています。',
            'post_excerpt' => '',
            'post_date' => '2023-01-01 12:00:00',
            'post_status' => 'publish',
            'post_type' => 'post',
        ];
    }
}

if (!function_exists('get_permalink') && defined('TESTENV')) {
    function get_permalink($post = 0) {
        return 'https://example.com/?p=' . $post;
    }
}

if (!function_exists('wp_insert_post') && defined('TESTENV')) {
    function wp_insert_post($postarr, $wp_error = false) {
        return 999; // モックID
    }
}

if (!function_exists('wp_update_post') && defined('TESTENV')) {
    function wp_update_post($postarr, $wp_error = false) {
        return $postarr['ID'] ?? 999;
    }
}

/**
 * 投稿の要約を生成する関数
 * 
 * @param int $post_id 要約する投稿のID
 * @return string|WP_Error 生成された要約またはエラー
 */
function lto_generate_post_summary($post_id) {
    // 投稿データを取得
    $post = get_post($post_id);

    if (!$post) {
        if (class_exists('WP_Error')) {
            return new WP_Error('no_post', '指定された投稿が見つかりません。');
        } else {
            return 'エラー: 指定された投稿が見つかりません。';
        }
    }

    // コンテンツを準備
    $content = $post->post_content;
    $title = $post->post_title;

    // コンテンツが空の場合
    if (empty($content)) {
        if (class_exists('WP_Error')) {
            return new WP_Error('empty_content', '投稿内容が空です。');
        } else {
            return 'エラー: 投稿内容が空です。';
        }
    }

    // プロンプトを作成
    $prompt = "以下の記事タイトルと内容を300文字程度にまとめて要約してください。要約は、AIアシスタントへの回答として適した形式にしてください。\n\nタイトル: $title\n\n内容:\n$content";

    // OpenAIを使用して要約を生成
    $summary = lto_generate_openai_content($prompt);

    return $summary;
}

/**
 * 人気投稿の要約を生成する関数
 * 
 * @return array|WP_Error 要約情報の配列またはエラー
 */
function lto_generate_popular_summary() {
    // テスト環境ではモックデータを返す
    if (defined('TESTENV')) {
        $post_id = 1;
        $post = get_post($post_id);
        $summary = "これはテスト投稿の要約です。LLMベースのAIに関する基本概念と応用例について解説しています。";

        return array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post_id),
            'summary' => $summary
        );
    }

    // WordPress環境でのロジックを追加
    // (ここでは、通常はアクセス数の多い投稿を取得するクエリを実行します)

    // サンプル実装として最新の投稿を取得
    $recent_posts = get_posts(array(
        'numberposts' => 1,
        'post_status' => 'publish'
    ));

    if (empty($recent_posts)) {
        if (class_exists('WP_Error')) {
            return new WP_Error('no_posts', '投稿が見つかりません。');
        } else {
            return array('error' => '投稿が見つかりません。');
        }
    }

    $post = $recent_posts[0];
    $post_id = $post->ID;

    // 要約を生成
    $summary = lto_generate_post_summary($post_id);

    if (is_wp_error($summary)) {
        return $summary;
    }

    return array(
        'post_id' => $post_id,
        'post_title' => $post->post_title,
        'post_url' => get_permalink($post_id),
        'summary' => $summary
    );
}

/**
 * 要約投稿を作成する関数
 * 
 * @param string $summary 要約内容
 * @param int $original_post_id 元の投稿ID
 * @return int|WP_Error 作成された投稿IDまたはエラー
 */
function lto_create_summary_post($summary, $original_post_id) {
    $original_post = get_post($original_post_id);

    if (!$original_post) {
        if (class_exists('WP_Error')) {
            return new WP_Error('no_post', '元の投稿が見つかりません。');
        } else {
            return 0;
        }
    }

    // 要約投稿のデータを準備
    $post_data = array(
        'post_title' => '[要約] ' . $original_post->post_title,
        'post_content' => $summary . "\n\n<p>元の記事: <a href=\"" . get_permalink($original_post_id) . "\">" . $original_post->post_title . "</a></p>",
        'post_status' => 'publish',
        'post_author' => $original_post->post_author,
        'post_type' => 'summary', // カスタム投稿タイプを想定
        'meta_input' => array(
            'lto_original_post_id' => $original_post_id
        )
    );

    // 投稿を作成
    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    return $post_id;
}

// WordPress関数のモック（非WordPress環境用）
if (!function_exists('wp_strip_all_tags') && defined('TESTENV')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}

/**
 * 記事の要約を生成する関数
 * 
 * @param int $post_id 要約を生成する記事のID
 * @return string|WP_Error 生成された要約またはエラー
 */
function lto_generate_post_summary_original($post_id) {
    // 記事データの取得
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('invalid_post', 'Invalid post ID');
    }

    // 記事のコンテンツとタイトルを取得
    $content = wp_strip_all_tags($post->post_content);
    $title = $post->post_title;

    // コンテンツが空の場合はエラー
    if (empty($content)) {
        return new WP_Error('empty_content', 'The post content is empty');
    }

    // 要約用のプロンプトを作成
    $prompt = sprintf(
        "Please create a concise summary of the following article titled '%s'. The summary should be 2-3 paragraphs long, capture the key points, and be suitable for LLM understanding. Here's the content:\n\n%s",
        $title,
        $content
    );

    // テスト環境では固定の要約を返す
    if (defined('TESTENV') && TESTENV) {
        return "これは「{$title}」の要約です。テスト環境ではモックデータを返しています。実際の環境ではOpenAI APIを使用して要約が生成されます。";
    }

    // 要約を生成
    $summary = lto_generate_openai_content($prompt, array(
        'max_tokens' => 500,
        'temperature' => 0.5 // やや低い温度で一貫性を高める
    ));

    if (is_wp_error($summary)) {
        return $summary;
    }

    return $summary;
}


/**
 * 人気記事の要約を生成する関数
 * 
 * @param int $num_posts 含める記事数（デフォルト5）
 * @return string|WP_Error 生成された要約またはエラーオブジェクト
 */
function lto_generate_popular_summary_original($num_posts = 5) {
    // テスト環境ではモックデータを返す
    if (defined('TESTENV') && TESTENV) {
        $post_id = 123;
        return array(
            'summary' => 'これは人気記事の要約のモックデータです。',
            'post_id' => $post_id,
            'post_url' => "https://example.com/?p={$post_id}"
        );
    }

    // 最近の人気記事を取得（PVベース、またはコメント数など）
    // 注: この部分は使用しているPV計測プラグインなどによって実装が異なります
    // この例ではシンプルにコメント数をベースにします
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $num_posts,
        'orderby' => 'comment_count',
        'order' => 'DESC'
    );

    $popular_posts = get_posts($args);

    if (empty($popular_posts)) {
        return new WP_Error('no_posts', 'No posts found');
    }

    // 各記事の情報を収集
    $post_data = array();
    foreach ($popular_posts as $post) {
        $excerpt = has_excerpt($post->ID) ? 
                  wp_strip_all_tags(get_the_excerpt($post->ID)) : 
                  wp_trim_words(wp_strip_all_tags($post->post_content), 50);

        $post_data[] = array(
            'title' => $post->post_title,
            'excerpt' => $excerpt,
            'url' => get_permalink($post->ID)
        );
    }

    // 要約用のプロンプトを作成
    $prompt = "Please create a comprehensive summary of the following popular articles from our website. For each article, provide a brief overview of the key points. Then, conclude with a general summary that connects these articles thematically where possible.\n\n";

    foreach ($post_data as $index => $data) {
        $prompt .= sprintf(
            "Article %d: %s\nExcerpt: %s\nURL: %s\n\n",
            $index + 1,
            $data['title'],
            $data['excerpt'],
            $data['url']
        );
    }

    // 要約を生成
    $summary = lto_generate_openai_content($prompt, array(
        'max_tokens' => 800,
        'temperature' => 0.7
    ));

    if (is_wp_error($summary)) {
        return $summary;
    }

    // サマリー記事の作成
    $summary_post_id = lto_create_summary_post_original($summary, $popular_posts, 'popular');

    if (is_wp_error($summary_post_id)) {
        return $summary_post_id;
    }

    return array(
        'summary' => $summary,
        'post_id' => $summary_post_id,
        'post_url' => get_permalink($summary_post_id)
    );
}

/**
 * 要約投稿を作成する関数
 * 
 * @param string $summary 生成された要約テキスト
 * @param array $source_posts 要約元の投稿の配列
 * @param string $type 要約のタイプ（'popular'、'category'など）
 * @return int|WP_Error 作成された投稿のIDまたはエラー
 */
function lto_create_summary_post_original($summary, $source_posts, $type = 'popular') {
    // テスト環境ではモックデータを返す
    if (defined('TESTENV') && TESTENV) {
        return 123; // 仮のポストID
    }

    // タイトルと内容を設定
    $current_date = date_i18n(get_option('date_format'));

    switch ($type) {
        case 'popular':
            $title = sprintf('Popular Content Summary - %s', $current_date);
            break;
        case 'category':
            $category = get_the_category($source_posts[0]->ID);
            $category_name = $category[0]->name;
            $title = sprintf('%s Category Summary - %s', $category_name, $current_date);
            break;
        default:
            $title = sprintf('Content Summary - %s', $current_date);
    }

    // HTML形式の内容を作成
    $content = '<div class="lto-summary">';
    $content .= wpautop($summary);
    $content .= '</div>';

    // 元記事へのリンクリストを追加
    $content .= '<div class="lto-source-posts">';
    $content .= '<h3>Source Articles</h3>';
    $content .= '<ul>';

    foreach ($source_posts as $post) {
        $content .= sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url(get_permalink($post->ID)),
            esc_html($post->post_title)
        );
    }

    $content .= '</ul></div>';

    // 既存の同タイプのサマリー記事を検索
    $existing_args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_lto_summary_type',
                'value' => $type
            )
        ),
        'posts_per_page' => 1
    );

    $existing_posts = get_posts($existing_args);
    $post_id = !empty($existing_posts) ? $existing_posts[0]->ID : 0;

    // 投稿データの準備
    $post_data = array(
        'ID' => $post_id,
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => 1, // デフォルト管理者
        'meta_input' => array(
            '_lto_is_summary' => 'yes',
            '_lto_summary_type' => $type,
            '_lto_summary_date' => current_time('mysql')
        )
    );

    // 投稿の保存または更新
    if ($post_id) {
        $result = wp_update_post($post_data, true);
    } else {
        $result = wp_insert_post($post_data, true);
    }

    // エラーチェック
    if (is_wp_error($result)) {
        return $result;
    }

    return $result; // 投稿ID
}

// 新しい投稿が公開されたときに自動的に要約を生成
add_action('publish_post', 'lto_auto_generate_summary', 10, 2);

function lto_auto_generate_summary($post_id, $post) {
    // 自動生成が有効か確認
    if (get_option('lto_enable_auto_summaries') !== 'yes') {
        return;
    }

    // 自動生成済みか確認
    $already_generated = get_post_meta($post_id, '_lto_summary_generated', true);
    if ($already_generated) {
        return;
    }

    // リビジョンや自動保存は無視
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // サマリー自体は処理しない
    if (get_post_meta($post_id, '_lto_is_summary', true) === 'yes') {
        return;
    }

    // 要約を生成して保存
    $summary = lto_generate_post_summary($post_id);

    if (!is_wp_error($summary)) {
        update_post_meta($post_id, '_lto_post_summary', $summary);
        update_post_meta($post_id, '_lto_summary_generated', 'yes');
        update_post_meta($post_id, '_lto_summary_date', current_time('mysql'));
    }
}

// AJAX処理：要約の生成
add_action('wp_ajax_lto_generate_summary', 'lto_ajax_generate_summary');

function lto_ajax_generate_summary() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied', 'llm-traffic-optimizer'));
        return;
    }

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

    if ($type === 'popular') {
        $result = lto_generate_popular_summary();
    } elseif ($type === 'category' && isset($_POST['category_id'])) {
        $category_id = intval($_POST['category_id']);

        // カテゴリーに属する記事を取得
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'cat' => $category_id
        );

        $posts = get_posts($args);

        if (empty($posts)) {
            wp_send_json_error(__('No posts found in this category', 'llm-traffic-optimizer'));
            return;
        }

        // 各記事の情報を収集
        $post_data = array();
        foreach ($posts as $post) {
            $excerpt = has_excerpt($post->ID) ? 
                      wp_strip_all_tags(get_the_excerpt($post->ID)) : 
                      wp_trim_words(wp_strip_all_tags($post->post_content), 50);

            $post_data[] = array(
                'title' => $post->post_title,
                'excerpt' => $excerpt,
                'url' => get_permalink($post->ID)
            );
        }

        // 要約用のプロンプトを作成
        $cat_name = get_cat_name($category_id);
        $prompt = sprintf(
            __("Please create a comprehensive summary of the following articles from the '%s' category on our website. Highlight the common themes and key insights across these articles. Include a brief overview of each article but focus on creating a cohesive summary of the entire category's content.\n\n", 'llm-traffic-optimizer'),
            $cat_name
        );

        foreach ($post_data as $index => $data) {
            $prompt .= sprintf(
                "Article %d: %s\nExcerpt: %s\nURL: %s\n\n",
                $index + 1,
                $data['title'],
                $data['excerpt'],
                $data['url']
            );
        }

        // 要約を生成
        $summary = lto_generate_openai_content($prompt, array(
            'max_tokens' => 800,
            'temperature' => 0.7
        ));

        if (is_wp_error($summary)) {
            wp_send_json_error($summary->get_error_message());
            return;
        }

        // サマリー記事の作成
        $summary_post_id = lto_create_summary_post($summary, $posts[0]->ID, 'category');

        if (is_wp_error($summary_post_id)) {
            wp_send_json_error($summary_post_id->get_error_message());
            return;
        }

        $result = array(
            'summary' => $summary,
            'post_id' => $summary_post_id,
            'post_url' => get_permalink($summary_post_id)
        );
    } else {
        wp_send_json_error(__('Invalid request', 'llm-traffic-optimizer'));
        return;
    }

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'message' => __('Summary generated and saved successfully.', 'llm-traffic-optimizer'),
            'post_url' => $result['post_url']
        ));
    }
}

?>