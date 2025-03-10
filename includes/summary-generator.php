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