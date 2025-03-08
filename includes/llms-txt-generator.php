<?php
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// LLMS.txtファイルを生成する関数
function lto_generate_llms_txt() {
    // サイトの基本情報を取得
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url();

    // 人気の投稿を取得
    $popular_posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'orderby' => 'comment_count',
        'order' => 'DESC'
    ));

    // カテゴリーの取得
    $categories = get_categories(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 5
    ));

    // LLMS.txtの内容を生成
    $content = "# {$site_name}\n\n";
    $content .= "> {$site_description}\n\n";

    $content .= "## 主要コンテンツ\n\n";

    foreach ($popular_posts as $post) {
        $post_url = get_permalink($post->ID);
        $post_title = $post->post_title;
        $content .= "- [{$post_title}]({$post_url}): " . mb_substr(strip_tags($post->post_content), 0, 100) . "...\n";
    }

    $content .= "\n## カテゴリー\n\n";

    foreach ($categories as $category) {
        $category_url = get_category_link($category->term_id);
        $category_name = $category->name;
        $content .= "- [{$category_name}]({$category_url}): {$category->category_description}\n";
    }

    // LLMsの活用方法
    $content .= "\n## LLMsの活用方法\n\n";
    $content .= "このサイトでは以下の方法でLLMを活用できます:\n";
    $content .= "- 記事内容に関する質問\n";
    $content .= "- トピックの詳細情報のリクエスト\n";
    $content .= "- 関連するコンテンツの検索\n";

    // LLMS.txtファイルを書き込む
    $file_path = ABSPATH . 'llms.txt';
    return file_put_contents($file_path, $content);
}

// LLMS-FULL.txtファイルを生成する関数
function lto_generate_llms_full_txt() {
    // サイトの基本情報を取得
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url();

    // すべての投稿を取得
    $all_posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    // すべてのカテゴリーの取得
    $all_categories = get_categories();

    // LLMS-FULL.txtの内容を生成
    $content = "# {$site_name} - 詳細情報\n\n";
    $content .= "> {$site_description}\n\n";
    $content .= "サイトURL: {$site_url}\n\n";

    $content .= "## すべての記事\n\n";

    foreach ($all_posts as $post) {
        $post_url = get_permalink($post->ID);
        $post_title = $post->post_title;
        $post_date = get_the_date('Y-m-d', $post->ID);
        $post_excerpt = get_the_excerpt($post->ID);

        $content .= "### {$post_title}\n";
        $content .= "- 公開日: {$post_date}\n";
        $content .= "- URL: {$post_url}\n";
        $content .= "- 概要: {$post_excerpt}\n\n";
    }

    $content .= "\n## すべてのカテゴリー\n\n";

    foreach ($all_categories as $category) {
        $category_url = get_category_link($category->term_id);
        $category_name = $category->name;
        $category_posts = get_posts(array(
            'category' => $category->term_id,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));

        $content .= "### {$category_name}\n";
        $content .= "- URL: {$category_url}\n";
        $content .= "- 説明: {$category->category_description}\n";
        $content .= "- 記事数: " . count($category_posts) . "\n\n";

        if (!empty($category_posts)) {
            $content .= "#### このカテゴリーの記事:\n";
            foreach ($category_posts as $post) {
                $post_url = get_permalink($post->ID);
                $post_title = $post->post_title;
                $content .= "- [{$post_title}]({$post_url})\n";
            }
            $content .= "\n";
        }
    }

    // LLMS-FULL.txtファイルを書き込む
    $file_path = ABSPATH . 'llms-full.txt';
    return file_put_contents($file_path, $content);
}

// AJAX経由でLLMS.txtファイルを再生成する
add_action('wp_ajax_lto_regenerate_llms_txt', 'lto_ajax_regenerate_llms_txt');

function lto_ajax_regenerate_llms_txt() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');

    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }

    // LLMS.txtファイルを生成
    $llms_result = lto_generate_llms_txt();

    // LLMS-FULL.txtファイルを生成
    $llms_full_result = lto_generate_llms_full_txt();

    if ($llms_result !== false && $llms_full_result !== false) {
        // プレビューのためにLLMS.txtの内容を取得
        $llms_content = file_get_contents(ABSPATH . 'llms.txt');

        wp_send_json_success(array(
            'message' => __('LLMS.txt files generated successfully!', 'llm-traffic-optimizer'),
            'content' => $llms_content
        ));
    } else {
        wp_send_json_error(__('Failed to generate LLMS.txt files. Please check file permissions.', 'llm-traffic-optimizer'));
    }
}

// 管理者向けにLLMS.txtの内容を手動で更新するためのアクション
// 注：すでに上で登録済みなので重複を避けるためコメントアウト
// add_action('wp_ajax_lto_regenerate_llms_txt', 'lto_ajax_regenerate_llms_txt');

// 投稿が公開されたとき、LLMS.txtを更新
add_action('publish_post', 'lto_update_llms_on_publish');

function lto_update_llms_on_publish($post_id) {
    // 自動下書きは無視
    if (wp_is_post_autosave($post_id)) {
        return;
    }

    // リビジョンは無視
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // 投稿タイプが「post」の場合のみ処理
    if (get_post_type($post_id) === 'post') {
        lto_generate_llms_txt();
    }
}