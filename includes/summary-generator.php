<?php
/**
 * コンテンツ要約ジェネレーター
 * 
 * ブログ記事の要約を自動生成する機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// 人気記事の要約を生成
function lto_generate_popular_summary() {
    // OpenAI APIキーが設定されているか確認
    $api_key = get_option('lto_openai_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI API key is not set.', 'llm-traffic-optimizer'));
    }

    // 人気記事を取得（最大10件）
    $popular_posts = get_posts(array(
        'numberposts' => 10,
        'meta_key' => 'post_views_count',
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    ));

    // もし人気記事用のカスタムフィールドがない場合は最新の投稿を表示
    if (empty($popular_posts)) {
        $popular_posts = get_posts(array(
            'numberposts' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
    }

    // 投稿がなければエラーを返す
    if (empty($popular_posts)) {
        return new WP_Error('no_posts', __('No posts found to summarize.', 'llm-traffic-optimizer'));
    }

    // サイト情報
    $site_name = get_bloginfo('name');

    // 記事のタイトルと抜粋を収集
    $post_data = array();
    foreach ($popular_posts as $post) {
        $post_title = $post->post_title;
        $post_excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 55, '...');
        $post_url = get_permalink($post->ID);

        $post_data[] = array(
            'title' => $post_title,
            'excerpt' => $post_excerpt,
            'url' => $post_url
        );
    }

    // プロンプトを構築
    $prompt = "以下は{$site_name}の人気記事のリストです。これらの記事の要約と、それらが提供する主要な洞察や情報をまとめた総合的な要約を作成してください。\n\n";

    foreach ($post_data as $i => $post) {
        $num = $i + 1;
        $prompt .= "記事{$num}: {$post['title']}\n";
        $prompt .= "概要: {$post['excerpt']}\n";
        $prompt .= "URL: {$post['url']}\n\n";
    }

    $prompt .= "これらの記事に基づいて、{$site_name}の人気コンテンツに関する包括的なまとめを日本語で作成してください。読者にとって価値のある情報を強調し、なぜこれらの記事が重要なのかを説明してください。";

    // OpenAI APIを使用して要約を生成
    $summary = lto_generate_openai_content($prompt);

    if (is_wp_error($summary)) {
        return $summary;
    }

    // 現在の日付を取得
    $date = date_i18n(get_option('date_format'));

    // 要約ページのタイトル
    $summary_title = sprintf(__('%s の人気記事まとめ - %s', 'llm-traffic-optimizer'), $site_name, $date);

    // 要約ページのコンテンツを作成
    $summary_content = '';

    // 簡単な導入文
    $summary_content .= '<p>' . sprintf(__('これは%sの人気記事のAIによる要約です。%sに生成されました。', 'llm-traffic-optimizer'), $site_name, $date) . '</p>';

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

    // 既存の要約ページを検索
    $existing_summary = get_page_by_title($summary_title, OBJECT, 'page');

    // 新しいページデータを準備
    $page_data = array(
        'post_title'    => $summary_title,
        'post_content'  => $summary_content,
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_type'     => 'page',
        'comment_status' => 'closed'
    );

    // 既存ページの更新または新規作成
    if ($existing_summary) {
        $page_data['ID'] = $existing_summary->ID;
        $summary_id = wp_update_post($page_data);
    } else {
        $summary_id = wp_insert_post($page_data);
    }

    if (is_wp_error($summary_id)) {
        return $summary_id;
    }

    return array(
        'id' => $summary_id,
        'url' => get_permalink($summary_id),
        'title' => $summary_title
    );
}

// カテゴリー記事の要約を生成
function lto_generate_category_summary($category_id) {
    // カテゴリーの存在確認
    $category = get_term($category_id, 'category');
    if (is_wp_error($category) || !$category) {
        return new WP_Error('invalid_category', __('Invalid category ID.', 'llm-traffic-optimizer'));
    }

    // OpenAI APIキーが設定されているか確認
    $api_key = get_option('lto_openai_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI API key is not set.', 'llm-traffic-optimizer'));
    }

    // カテゴリーの記事を取得（最大10件）
    $category_posts = get_posts(array(
        'numberposts' => 10,
        'category' => $category_id,
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    // 投稿がなければエラーを返す
    if (empty($category_posts)) {
        return new WP_Error('no_posts', __('No posts found in this category.', 'llm-traffic-optimizer'));
    }

    // サイト情報とカテゴリー情報
    $site_name = get_bloginfo('name');
    $category_name = $category->name;
    $category_description = $category->description;

    // 記事のタイトルと抜粋を収集
    $post_data = array();
    foreach ($category_posts as $post) {
        $post_title = $post->post_title;
        $post_excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 55, '...');
        $post_url = get_permalink($post->ID);

        $post_data[] = array(
            'title' => $post_title,
            'excerpt' => $post_excerpt,
            'url' => $post_url
        );
    }

    // プロンプトを構築
    $prompt = "以下は{$site_name}のカテゴリー「{$category_name}」の記事リストです。";

    if (!empty($category_description)) {
        $prompt .= "カテゴリーの説明: {$category_description}\n\n";
    } else {
        $prompt .= "\n\n";
    }

    foreach ($post_data as $i => $post) {
        $num = $i + 1;
        $prompt .= "記事{$num}: {$post['title']}\n";
        $prompt .= "概要: {$post['excerpt']}\n";
        $prompt .= "URL: {$post['url']}\n\n";
    }

    $prompt .= "これらの記事に基づいて、カテゴリー「{$category_name}」に関する包括的なまとめを日本語で作成してください。このカテゴリーが扱うトピックの主要なテーマ、洞察、および重要なポイントを強調してください。";

    // OpenAI APIを使用して要約を生成
    $summary = lto_generate_openai_content($prompt);

    if (is_wp_error($summary)) {
        return $summary;
    }

    // 現在の日付を取得
    $date = date_i18n(get_option('date_format'));

    // 要約ページのタイトル
    $summary_title = sprintf(__('%s: %s カテゴリーのまとめ - %s', 'llm-traffic-optimizer'), $site_name, $category_name, $date);

    // 要約ページのコンテンツを作成
    $summary_content = '';

    // 簡単な導入文
    $summary_content .= '<p>' . sprintf(__('これは%sの「%s」カテゴリーの記事のAIによる要約です。%sに生成されました。', 'llm-traffic-optimizer'), $site_name, $category_name, $date) . '</p>';

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

    // 既存の要約ページを検索
    $existing_summary = get_page_by_title($summary_title, OBJECT, 'page');

    // 新しいページデータを準備
    $page_data = array(
        'post_title'    => $summary_title,
        'post_content'  => $summary_content,
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_type'     => 'page',
        'comment_status' => 'closed'
    );

    // 既存ページの更新または新規作成
    if ($existing_summary) {
        $page_data['ID'] = $existing_summary->ID;
        $summary_id = wp_update_post($page_data);
    } else {
        $summary_id = wp_insert_post($page_data);
    }

    if (is_wp_error($summary_id)) {
        return $summary_id;
    }

    return array(
        'id' => $summary_id,
        'url' => get_permalink($summary_id),
        'title' => $summary_title
    );
}

// AJAX経由で要約を生成する
add_action('wp_ajax_lto_generate_summary', 'lto_ajax_generate_summary');

function lto_ajax_generate_summary() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');

    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }

    // 要約タイプを取得
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

    if ($type === 'popular') {
        // 人気記事の要約を生成
        $result = lto_generate_popular_summary();
    } elseif ($type === 'category' && isset($_POST['category_id'])) {
        // カテゴリー記事の要約を生成
        $category_id = (int) $_POST['category_id'];
        $result = lto_generate_category_summary($category_id);
    } else {
        wp_send_json_error(__('Invalid summary type or missing category ID.', 'llm-traffic-optimizer'));
        return;
    }

    // エラーチェック
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
        return;
    }

    // 成功レスポンス
    wp_send_json_success(array(
        'message' => __('Summary generated successfully!', 'llm-traffic-optimizer'),
        'post_id' => $result['id'],
        'post_url' => $result['url'],
        'post_title' => $result['title']
    ));
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

// 単一投稿のサマリーを生成
function lto_generate_post_summary($post_id) {
    try {
        // 元の投稿を取得
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        // 十分なコンテンツがあるか確認
        if (str_word_count(strip_tags($post->post_content)) < 100) {
            return false; // 短すぎるコンテンツは処理しない
        }

        // OpenAIのプロンプトを作成
        $prompt = "Create a comprehensive SEO-friendly summary of the following content. The summary should be informative, well-structured, and aimed at helping AI systems provide accurate information about this topic: \n\n";
        $prompt .= "Title: " . $post->post_title . "\n\n";
        $prompt .= "Content: " . strip_tags($post->post_content) . "\n\n";
        $prompt .= "Please include a short introduction, key points, and a conclusion. Format the summary with proper headings, paragraphs, and bullet points where appropriate.";

        // OpenAIを呼び出し
        if (!function_exists('lto_openai_api_request')) {
            error_log('OpenAI integration function not available');
            return false;
        }

        $summary_content = lto_openai_api_request($prompt);

        if (is_wp_error($summary_content)) {
            error_log('OpenAI API error: ' . $summary_content->get_error_message());
            return false;
        }

        // サマリー投稿を作成
        $summary_post = array(
            'post_title' => 'AI Summary: ' . $post->post_title,
            'post_content' => $summary_content,
            'post_status' => 'publish',
            'post_author' => $post->post_author,
            'post_type' => 'lto_summary', // Assuming 'lto_summary' post type exists. Adjust as needed.
            'post_category' => wp_get_post_categories($post_id),
            'tags_input' => wp_get_post_tags($post_id, array('fields' => 'names'))
        );

        // 投稿を保存
        $summary_post_id = wp_insert_post($summary_post);

        if (!$summary_post_id || is_wp_error($summary_post_id)) {
            error_log('Error creating summary post: ' . ($summary_post_id->get_error_message() ?? 'Unknown error'));
            return false;
        }

        // 元の投稿へのリンクをメタデータとして保存
        update_post_meta($summary_post_id, 'lto_original_post_id', $post_id);
        update_post_meta($summary_post_id, 'lto_is_ai_summary', true);

        // カスタム投稿タイプの場合はカテゴリとタグをコピー
        if ($post->post_type !== 'post') {
            // カスタムタクソノミーの処理など、必要に応じて追加
        }

        return $summary_post_id;
    } catch (Exception $e) {
        error_log('Error generating post summary: ' . $e->getMessage());
        return false;
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
add_filter('the_content', 'lto_add_original_post_link');

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