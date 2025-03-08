<?php
/**
 * 要約生成機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// OpenAI統合が読み込まれていることを確認
if (!function_exists('lto_call_openai_api')) {
    require_once dirname(__FILE__) . '/openai-integration.php';
}

// OpenAI APIリクエスト用の関数
function lto_generate_openai_content($prompt) {
    if (!function_exists('lto_call_openai_api')) {
        error_log('LLM Traffic Optimizer: OpenAI APIの関数が読み込まれていません');
        require_once dirname(__FILE__) . '/openai-integration.php';
        
        if (!function_exists('lto_call_openai_api')) {
            return new WP_Error('missing_function', __('OpenAI API functions are not loaded.', 'llm-traffic-optimizer'));
        }
    }
    return lto_call_openai_api($prompt);
}

// 投稿の要約を生成する
function lto_generate_post_summary($post_id) {
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('invalid_post', __('Invalid post ID', 'llm-traffic-optimizer'));
    }

    // 要約のプロンプトを作成
    $prompt = "次の記事の300文字程度の要約を生成してください。SEO最適化された形式で記事の重要なポイントを含めてください：\n\n";
    $prompt .= "タイトル: " . $post->post_title . "\n\n";
    $prompt .= wp_strip_all_tags($post->post_content);

    // OpenAIを使用して要約を生成
    $summary = lto_generate_openai_content($prompt);

    if (is_wp_error($summary)) {
        error_log('Error generating summary: ' . $summary->get_error_message());
        return $summary;
    }

    // 要約をメタデータとして保存
    update_post_meta($post_id, '_lto_post_summary', $summary);
    update_post_meta($post_id, '_lto_summary_generated_date', current_time('mysql'));

    return $summary;
}

// 人気記事のサマリーページを生成する
function lto_generate_popular_summary() {
    try {
        // 頻度設定に基づいて生成するかを判断
        $frequency = get_option('lto_summary_frequency', 'weekly');

        if (!lto_should_generate_summary($frequency)) {
            return new WP_Error('too_soon', __('It\'s too soon to generate a new summary based on your frequency settings.', 'llm-traffic-optimizer'));
        }

        // 人気記事の取得
        $popular_posts = lto_get_popular_posts();

        if (empty($popular_posts)) {
            return new WP_Error('no_posts', __('No popular posts found to create a summary.', 'llm-traffic-optimizer'));
        }

        // 日付を含むタイトルの作成
        $date_format = get_option('date_format');
        $current_date = date_i18n($date_format);
        $title = sprintf(__('人気記事まとめ: %s', 'llm-traffic-optimizer'), $current_date);

        // 記事データの準備
        $post_data = array();
        foreach ($popular_posts as $post) {
            $post_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'excerpt' => has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words(wp_strip_all_tags($post->post_content), 55)
            );
        }

        // サマリーの作成
        return lto_create_summary_post('popular', $title, $post_data);
    } catch (Exception $e) {
        error_log('Error generating popular summary: ' . $e->getMessage());
        return new WP_Error('summary_error', $e->getMessage());
    }
}

// カテゴリー別のサマリーページを生成する
function lto_generate_category_summary($category_id) {
    try {
        $category = get_category($category_id);

        if (!$category) {
            return new WP_Error('invalid_category', __('Invalid category ID', 'llm-traffic-optimizer'));
        }

        // カテゴリー内の記事を取得
        $category_posts = get_posts(array(
            'category' => $category_id,
            'numberposts' => 10,
            'post_status' => 'publish'
        ));

        if (empty($category_posts)) {
            return new WP_Error('no_posts', __('No posts found in this category to create a summary.', 'llm-traffic-optimizer'));
        }

        // タイトルの作成
        $title = sprintf(__('%sカテゴリーの総まとめガイド', 'llm-traffic-optimizer'), $category->name);

        // 記事データの準備
        $post_data = array();
        foreach ($category_posts as $post) {
            $post_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'excerpt' => has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words(wp_strip_all_tags($post->post_content), 55)
            );
        }

        // サマリーの作成
        return lto_create_summary_post('category', $title, $post_data, $category_id);
    } catch (Exception $e) {
        error_log('Error generating category summary: ' . $e->getMessage());
        return new WP_Error('summary_error', $e->getMessage());
    }
}

// サマリー投稿を作成する
function lto_create_summary_post($type, $title, $post_data, $category_id = null) {
    // 記事情報をプロンプトに変換
    $prompt = "あなたは{$type}記事のキュレーターです。以下の記事情報を元に、「{$title}」というタイトルの包括的なまとめ記事を作成してください。\n\n";

    foreach ($post_data as $index => $post) {
        $prompt .= "記事 {$index+1}:\n";
        $prompt .= "タイトル: {$post['title']}\n";
        $prompt .= "URL: {$post['url']}\n";
        $prompt .= "概要: {$post['excerpt']}\n\n";
    }

    $prompt .= "各記事の主要なポイントを簡潔にまとめ、なぜその記事が価値があるのかを説明してください。導入部と結論部も含めてください。";

    // OpenAIを使用してコンテンツを生成
    $summary_content = lto_generate_openai_content($prompt);

    if (is_wp_error($summary_content)) {
        return $summary_content;
    }

    // 生成されたコンテンツに記事へのリンクを追加
    $summary_content .= "\n\n<h2>" . __('元の記事一覧', 'llm-traffic-optimizer') . "</h2>\n<ul>";
    foreach ($post_data as $post) {
        $summary_content .= "<li><a href='{$post['url']}'>{$post['title']}</a></li>";
    }
    $summary_content .= "</ul>";

    // フッターの追加
    $summary_content .= '<p><em>' . __('この要約はLLM Traffic Optimizerプラグインによって自動生成されました。', 'llm-traffic-optimizer') . '</em></p>';

    // 投稿データの作成
    $post_arr = array(
        'post_title'    => $title,
        'post_content'  => $summary_content,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_author'   => get_current_user_id(),
        'meta_input'    => array(
            'lto_summary_type' => $type,
            'lto_generation_date' => current_time('mysql')
        )
    );

    // 投稿の作成
    $post_id = wp_insert_post($post_arr);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // 記事の関連付け（メタデータとして保存）
    $related_ids = array();
    foreach ($post_data as $post) {
        $related_ids[] = $post['id'];
    }

    update_post_meta($post_id, 'lto_related_posts', $related_ids);

    return array(
        'post_id' => $post_id,
        'post_url' => get_permalink($post_id),
        'message' => __('サマリーが正常に生成されました。', 'llm-traffic-optimizer')
    );
}

// 人気記事を取得する関数
function lto_get_popular_posts($count = 10) {
    // ここでは簡易的に閲覧数の多い記事を仮定
    // 実際のサイトでは、ページビュー統計プラグインなどのデータを使用することが望ましい
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $popular_posts_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id FROM $table_name ORDER BY views DESC LIMIT %d",
            $count
        )
    );

    // 閲覧データがない場合は最新記事を使用
    if (empty($popular_posts_ids)) {
        return get_posts(array(
            'numberposts' => $count,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
    }

    // 人気記事IDから投稿オブジェクトを取得
    $popular_posts = array();
    foreach ($popular_posts_ids as $post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            $popular_posts[] = $post;
        }
    }

    return $popular_posts;
}

// OpenAI APIを使用してコンテンツを生成する関数
function lto_generate_openai_content($prompt) {
    if (!function_exists('lto_call_openai_api')) {
        if (LTO_DEBUG) {
            error_log('LLM Traffic Optimizer: OpenAI APIの関数が読み込まれていません');
        }
        return new WP_Error('missing_function', __('OpenAI API functions are not loaded.', 'llm-traffic-optimizer'));
    }

    return lto_call_openai_api($prompt);
}

// Function to generate summary posts (admin initiated)
function lto_generate_summary_posts() {
    try {
        // サマリーがないすべての公開投稿を取得
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_lto_has_summary',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        if (empty($posts)) {
            return;
        }

        foreach ($posts as $post) {
            lto_generate_post_summary($post->ID);

            // サマリーが生成されたことを示すメタを追加
            update_post_meta($post->ID, '_lto_has_summary', true);
        }
    } catch (Exception $e) {
        error_log('Error generating summary posts: ' . $e->getMessage());
    }
}

// Check if it's time to generate a new summary based on frequency
function lto_should_generate_summary($frequency) {
    $last_generated = get_option('lto_last_summary_generated', 0);
    $current_time = time();

    switch ($frequency) {
        case 'daily':
            // If more than 20 hours have passed
            return ($current_time - $last_generated) > (20 * 3600);

        case 'weekly':
            // If more than 6 days have passed
            return ($current_time - $last_generated) > (6 * 24 * 3600);

        case 'monthly':
            // If more than 28 days have passed
            return ($current_time - $last_generated) > (28 * 24 * 3600);

        default:
            return true;
    }
}

// 人気記事のサマリーを生成する関数
function lto_generate_popular_summary_original() {
    // OpenAI APIキーの確認
    $api_key = get_option('lto_openai_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI APIキーが設定されていません。', 'llm-traffic-optimizer'));
    }

    // 人気記事の取得（例：閲覧数の多い10件）
    $popular_posts = lto_get_popular_posts(10);

    if (empty($popular_posts)) {
        return new WP_Error('no_posts', __('対象となる記事が見つかりませんでした。', 'llm-traffic-optimizer'));
    }

    // 記事データの収集
    $post_data = array();
    $content_for_summary = "";

    foreach ($popular_posts as $post) {
        $post_title = $post->post_title;
        $post_url = get_permalink($post->ID);
        $post_excerpt = wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_content)), 100);

        $post_data[] = array(
            'id' => $post->ID,
            'title' => $post_title,
            'url' => $post_url,
            'excerpt' => $post_excerpt
        );

        $content_for_summary .= "タイトル: {$post_title}\n";
        $content_for_summary .= "概要: {$post_excerpt}\n\n";
    }

    // OpenAIでサマリー生成
    $prompt = "以下は、Webサイトの人気記事のリストです。これらの記事を要約し、共通するテーマや特徴を500〜800文字で説明してください。要約は日本語で作成してください。\n\n{$content_for_summary}";

    $summary = lto_openai_api_request($prompt);

    if (is_wp_error($summary)) {
        return $summary;
    }

    // サマリー記事を投稿として保存
    return lto_save_summary_as_post($summary, $post_data, 'popular');
}


// カテゴリー別のサマリーを生成
function lto_generate_category_summary_original($category_id) {
    // OpenAI APIキーの確認
    $api_key = get_option('lto_openai_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI APIキーが設定されていません。', 'llm-traffic-optimizer'));
    }

    // カテゴリーの確認
    $category = get_term($category_id, 'category');
    if (is_wp_error($category) || empty($category)) {
        return new WP_Error('invalid_category', __('指定されたカテゴリーが存在しません。', 'llm-traffic-optimizer'));
    }

    // カテゴリー内の記事を取得（最新10件）
    $args = array(
        'category' => $category_id,
        'posts_per_page' => 10,
        'post_status' => 'publish'
    );

    $posts = get_posts($args);

    if (empty($posts)) {
        return new WP_Error('no_posts', __('対象となる記事が見つかりませんでした。', 'llm-traffic-optimizer'));
    }

    // 記事データの収集
    $post_data = array();
    $content_for_summary = "";

    foreach ($posts as $post) {
        $post_title = $post->post_title;
        $post_url = get_permalink($post->ID);
        $post_excerpt = wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_content)), 100);

        $post_data[] = array(
            'id' => $post->ID,
            'title' => $post_title,
            'url' => $post_url,
            'excerpt' => $post_excerpt
        );

        $content_for_summary .= "タイトル: {$post_title}\n";
        $content_for_summary .= "概要: {$post_excerpt}\n\n";
    }

    // OpenAIでサマリー生成
    $prompt = "以下は、「{$category->name}」カテゴリーの記事のリストです。これらの記事を要約し、このカテゴリーの特徴や重要なポイントを500〜800文字で説明してください。要約は日本語で作成してください。\n\n{$content_for_summary}";

    $summary = lto_openai_api_request($prompt);

    if (is_wp_error($summary)) {
        return $summary;
    }

    // サマリー記事を投稿として保存
    return lto_save_summary_as_post($summary, $post_data, 'category', $category->name);
}

// サマリーを投稿として保存
function lto_save_summary_as_post($summary, $post_data, $type = 'popular', $category_name = '') {
    $site_name = get_bloginfo('name');
    $date = date_i18n(get_option('date_format'));

    // タイトルの設定
    if ($type === 'popular') {
        $title = sprintf(__('%sの人気記事まとめ - %s', 'llm-traffic-optimizer'), $site_name, $date);
    } else {
        $title = sprintf(__('%sの「%s」カテゴリー記事まとめ - %s', 'llm-traffic-optimizer'), $site_name, $category_name, $date);
    }

    // 投稿コンテンツの作成
    $summary_content = '';

    // ヘッダー
    $summary_content .= '<p class="summary-header">' . sprintf(__('これは%sの人気記事のAIによる要約です。%sに生成されました。', 'llm-traffic-optimizer'), $site_name, $date) . '</p>';

    // 区切り線
    $summary_content .= '<hr />';

    // 要約コンテンツ
    $summary_content .= $summary;

    // 記事リスト
    $summary_content .= '<h2>' . __('要約に含まれる記事', 'llm-traffic-optimizer') . '</h2>';
    $summary_content .= '<ul>';
    foreach ($post_data as $post) {
        $summary_content .= '<li><a href="' . esc_url($post['url']) . '">' . esc_html($post['title']) . '</a></li>';
    }
    $summary_content .= '</ul>';

    // フッター注記
    $summary_content .= '<hr />';
    $summary_content .= '<p><em>' . __('この要約はLLM Traffic Optimizerプラグインによって自動生成されました。', 'llm-traffic-optimizer') . '</em></p>';

    // 投稿データの作成
    $post_arr = array(
        'post_title'    => $title,
        'post_content'  => $summary_content,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_author'   => get_current_user_id(),
        'meta_input'    => array(
            'lto_summary_type' => $type,
            'lto_generation_date' => current_time('mysql')
        )
    );

    // 投稿の作成
    $post_id = wp_insert_post($post_arr);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // 記事の関連付け（メタデータとして保存）
    $related_ids = array();
    foreach ($post_data as $post) {
        $related_ids[] = $post['id'];
    }

    update_post_meta($post_id, 'lto_related_posts', $related_ids);

    return array(
        'post_id' => $post_id,
        'post_url' => get_permalink($post_id),
        'message' => __('サマリーが正常に生成されました。', 'llm-traffic-optimizer')
    );
}

// 人気記事を取得する関数
function lto_get_popular_posts_original($count = 10) {
    // ここでは簡易的に閲覧数の多い記事を仮定
    // 実際のサイトでは、ページビュー統計プラグインなどのデータを使用することが望ましい
    $args = array(
        'posts_per_page' => $count,
        'post_type' => 'post',
        'post_status' => 'publish',
        'orderby' => 'comment_count', // コメント数を人気の指標として使用
        'order' => 'DESC'
    );

    return get_posts($args);
}

// AJAX: サマリー生成リクエスト処理
add_action('wp_ajax_lto_generate_summary', 'lto_ajax_generate_summary');

function lto_ajax_generate_summary() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');

    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

    if ($type === 'popular') {
        $result = lto_generate_popular_summary();
    } elseif ($type === 'category' && isset($_POST['category_id'])) {
        $category_id = intval($_POST['category_id']);
        $result = lto_generate_category_summary($category_id);
    } else {
        wp_send_json_error(__('Invalid request parameters.', 'llm-traffic-optimizer'));
        return;
    }

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success($result);
    }
}

// OpenAI APIを使用してコンテンツを生成する関数
function lto_generate_openai_content_original($prompt) {
    if (!function_exists('lto_call_openai_api')) {
        if (LTO_DEBUG) {
            error_log('LLM Traffic Optimizer: OpenAI APIの関数が読み込まれていません');
        }
        return new WP_Error('missing_function', __('OpenAI API functions are not loaded.', 'llm-traffic-optimizer'));
    }

    return lto_call_openai_api($prompt);
}


// Function to generate summary posts (admin initiated)
function lto_generate_summary_posts_original() {
    try {
        // サマリーがないすべての公開投稿を取得
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_lto_has_summary',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        if (empty($posts)) {
            return;
        }

        foreach ($posts as $post) {
            lto_generate_post_summary($post->ID);

            // サマリーが生成されたことを示すメタを追加
            update_post_meta($post->ID, '_lto_has_summary', true);
        }
    } catch (Exception $e) {
        error_log('Error generating summary posts: ' . $e->getMessage());
    }
}

// Check if it's time to generate a new summary based on frequency
function lto_should_generate_summary_original($frequency) {
    $last_generated = get_option('lto_last_summary_generated', 0);
    $current_time = time();

    switch ($frequency) {
        case 'daily':
            // If more than 20 hours have passed
            return ($current_time - $last_generated) > (20 * 3600);

        case 'weekly':
            // If more than 6 days have passed
            return ($current_time - $last_generated) > (6 * 24 * 3600);

        case 'monthly':
            // If more than 28 days have passed
            return ($current_time - $last_generated) > (28 * 24 * 3600);

        default:
            return true;
    }
}

// Generate a specific summary
function lto_generate_summary($summary_type, $title, $category_id = null) {
    // Check if OpenAI API key is set
    $api_key = get_option('lto_openai_api_key');
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI API key is required for generating summaries.', 'llm-traffic-optimizer'));
    }

    // Get posts based on summary type
    $posts = array();

    switch ($summary_type) {
        case 'popular':
            $top_posts_count = get_option('lto_top_posts_count', 10);
            $posts = lto_get_top_pages($top_posts_count);
            break;

        case 'category':
            if (!$category_id) {
                return new WP_Error('missing_category', __('Category ID is required for category summaries.', 'llm-traffic-optimizer'));
            }

            $posts = get_posts(array(
                'numberposts' => 15,
                'category' => $category_id,
                'post_status' => 'publish'
            ));
            break;

        case 'latest':
            $posts = get_posts(array(
                'numberposts' => 10,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            break;

        default:
            return new WP_Error('invalid_type', __('Invalid summary type.', 'llm-traffic-optimizer'));
    }

    if (empty($posts)) {
        return new WP_Error('no_posts', __('No posts found to create a summary.', 'llm-traffic-optimizer'));
    }

    // Prepare data for OpenAI
    $post_data = array();
    foreach ($posts as $post) {
        $post_id = isset($post->ID) ? $post->ID : $post->post_id;
        $post_obj = get_post($post_id);

        if (!$post_obj) {
            continue;
        }

        $post_data[] = array(
            'title' => $post_obj->post_title,
            'excerpt' => wp_trim_words(
                $post_obj->post_excerpt ? $post_obj->post_excerpt : $post_obj->post_content,
                50,
                '...'
            ),
            'url' => get_permalink($post_id)
        );
    }

    // Generate content with OpenAI
    $content = lto_generate_content_with_ai($summary_type, $title, $post_data, $category_id);

    if (is_wp_error($content)) {
        return $content;
    }

    // Create the post
    $post_category = $summary_type === 'category' ? $category_id : get_option('lto_summary_category');

    $post_data = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
        'post_category' => $post_category ? array($post_category) : array(),
        'meta_input' => array(
            '_lto_summary_type' => $summary_type,
            '_lto_generated_date' => current_time('mysql')
        )
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // Update the last generated timestamp
    update_option('lto_last_summary_generated', time());

    return $post_id;
}

// Generate content with OpenAI
function lto_generate_content_with_ai($summary_type, $title, $posts, $category_id = null) {
    // Get API key
    $api_key = get_option('lto_openai_api_key');

    // Prepare the prompt based on summary type
    $prompt = '';
    $site_name = get_bloginfo('name');

    switch ($summary_type) {
        case 'popular':
            $prompt = "You are a content curator for {$site_name}. Create a comprehensive summary article titled \"{$title}\" that highlights and connects the main points from these popular articles. For each article, provide a brief summary and explanation of why it's valuable. Include an introduction and conclusion. Format the content in markdown with appropriate headings, bullet points, and links to the original articles.\n\n";
            break;

        case 'category':
            $category = get_category($category_id);
            $prompt = "You are a content specialist for {$site_name}. Create a comprehensive guide titled \"{$title}\" that covers the main topics within the {$category->name} category. Organize the content thematically, highlighting key insights from each article. Include an introduction explaining what readers will learn and a conclusion summarizing the main takeaways. Format the content in markdown with appropriate headings, bullet points, and links to the original articles.\n\n";
            break;

        case 'latest':
            $prompt = "You are a content curator for {$site_name}. Create a roundup article titled \"{$title}\" that summarizes recent content published on the site. For each article, provide a concise overview highlighting what readers will learn. Include an introduction explaining what's new and a conclusion that encourages readers to explore the full articles. Format the content in markdown with appropriate headings, bullet points, and links to the original articles.\n\n";
            break;
    }

    // Add the post data to the prompt
    $prompt .= "Articles to include:\n\n";

    foreach ($posts as $index => $post) {
        $prompt .= ($index + 1) . ". {$post['title']}\n";
        $prompt .= "   Excerpt: {$post['excerpt']}\n";
        $prompt .= "   URL: {$post['url']}\n\n";
    }

    $prompt .= "Important guidelines:\n";
    $prompt .= "1. Make sure to include links to all the original articles using their URLs.\n";
    $prompt .= "2. Keep the writing style engaging, informative, and SEO-friendly.\n";
    $prompt .= "3. Create a cohesive narrative that adds value beyond simply listing the articles.\n";
    $prompt .= "4. Include a call-to-action at the end encouraging readers to explore more content on the site.\n";

    // Make the API request to OpenAI
    $response = lto_openai_api_request($prompt);

    if (is_wp_error($response)) {
        return $response;
    }

    return $response;
}

//Function for getting top posts - needs implementation based on your logic.
function lto_get_top_pages($top_posts_count){
    // Replace this with your actual logic to fetch top posts
    return get_posts(array(
        'numberposts' => $top_posts_count,
        'orderby' => 'comment_count',
        'order' => 'DESC'
    ));

}


// 新しい投稿が公開されたときにサマリーを自動生成
add_action('transition_post_status', 'lto_auto_generate_summary', 10, 3);

function lto_auto_generate_summary($new_status, $old_status, $post) {
    try {
        // 設定がオンになっており、投稿が公開されたときのみ処理
        if ($new_status !== 'publish' || $old_status === 'publish' || get_option('lto_enable_auto_summaries', 'yes') !== 'yes') {
            return;
        }

        // 投稿タイプが投稿または固定ページのみ処理
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        // すでにサマリーが存在するか確認
        $existing_summary = get_posts(array(
            'post_type' => 'lto_summary',
            'meta_key' => 'lto_original_post_id',
            'meta_value' => $post->ID,
            'posts_per_page' => 1
        ));

        if (!empty($existing_summary)) {
            return; // サマリーがすでに存在する場合
        }

        // サマリーを生成
        lto_generate_post_summary($post->ID);
    } catch (Exception $e) {
        error_log('Error auto-generating summary: ' . $e->getMessage());
    }
}

// サマリーかどうかをフロントエンドに表示
add_action('wp_head', 'lto_add_summary_meta');

function lto_add_summary_meta() {
    if (is_singular() && get_post_meta(get_the_ID(), 'lto_is_ai_summary', true)) {
        echo '<meta name="robots" content="noindex, follow" />';
        echo '<meta name="llm:type" content="ai-summary" />';

        $original_post_id = get_post_meta(get_the_ID(), 'lto_original_post_id', true);
        if ($original_post_id) {
            echo '<link rel="canonical" href="' . esc_url(get_permalink($original_post_id)) . '" />';
        }
    }
}

// サマリー投稿に元記事へのリンクを追加
add_action('the_content', 'lto_add_original_post_link');

function lto_add_original_post_link($content) {
    if (is_singular() && get_post_meta(get_the_ID(), 'lto_is_ai_summary', true)) {
        $original_post_id = get_post_meta(get_the_ID(), 'lto_original_post_id', true);
        if ($original_post_id) {
            $link = '<div class="lto-original-post-link">';
            $link .= '<p>' . __('This is an AI-generated summary. View the original article: ', 'llm-traffic-optimizer');
            $link .= '<a href="' . esc_url(get_permalink($original_post_id)) . '">' . esc_html(get_the_title($original_post_id)) . '</a></p>';
            $link .= '</div>';

            $content = $link . $content;
        }
    }

    return $content;
}

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// OpenAI統合ファイルの読み込みを確認
if (!function_exists('lto_openai_api_request')) {
    require_once dirname(__FILE__) . '/openai-integration.php';
}

// アクセスを記録するアクション
add_action('wp_footer', 'lto_track_page_view');

// AIからの参照かどうかを判定する関数
function lto_is_ai_referral() {
    // ユーザーエージェントを取得
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

    // リファラーを取得
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    // AIボットのリスト
    $ai_bots = array(
        'GPTBot', 'ChatGPT', 'googlebot', 'bingbot', 'Baiduspider',
        'Anthropic', 'Claude', 'CCBot', 'facebookexternalhit'
    );

    // AIドメインのリスト
    $ai_domains = array(
        'openai.com', 'bing.com', 'google.com', 'facebook.com',
        'anthropic.com', 'claude.ai', 'gemini.google.com'
    );

    // ユーザーエージェントにAIボットの名前が含まれているか確認
    foreach ($ai_bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return true;
        }
    }

    // リファラーにAIドメインが含まれているか確認
    foreach ($ai_domains as $domain) {
        if (stripos($referer, $domain) !== false) {
            return true;
        }
    }

    return false;
}

function lto_track_page_view() {
    // 投稿ページのみトラッキング
    if (!is_singular('post')) {
        return;
    }

    global $post, $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    // ユーザーエージェントからAIアクセスを判定
    $is_ai_referral = lto_is_ai_referral();
}

?>