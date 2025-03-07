
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
    $content .= "このサイトでは以下の方法でLLMを活用できます：\n";
    $content .= "- コンテンツの要約と把握\n";
    $content .= "- 特定のトピックに関する情報検索\n";
    $content .= "- カテゴリー別の記事一覧の閲覧\n\n";
    
    // ファイルに書き込み
    $file_path = ABSPATH . 'llms.txt';
    file_put_contents($file_path, $content);
    
    // 詳細バージョンも生成
    lto_generate_llms_full_txt();
    
    return true;
}

// 詳細なLLMS-full.txtファイルを生成する関数
function lto_generate_llms_full_txt() {
    // サイトの基本情報を取得
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url();
    
    // すべての投稿を取得
    $all_posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // すべてのカテゴリー
    $all_categories = get_categories();
    
    // LLMS-full.txtの内容を生成
    $content = "# {$site_name} - 詳細情報\n\n";
    $content .= "> {$site_description}\n\n";
    $content .= "サイトURL: {$site_url}\n\n";
    
    $content .= "## すべての記事\n\n";
    
    foreach ($all_posts as $post) {
        $post_url = get_permalink($post->ID);
        $post_title = $post->post_title;
        $post_date = get_the_date('Y-m-d', $post->ID);
        $post_categories = get_the_category($post->ID);
        $category_names = array();
        
        foreach ($post_categories as $category) {
            $category_names[] = $category->name;
        }
        
        $category_list = implode(', ', $category_names);
        $excerpt = mb_substr(strip_tags($post->post_content), 0, 200) . "...";
        
        $content .= "### [{$post_title}]({$post_url})\n";
        $content .= "- 公開日: {$post_date}\n";
        $content .= "- カテゴリー: {$category_list}\n";
        $content .= "- 概要: {$excerpt}\n\n";
    }
    
    $content .= "## カテゴリー別コンテンツ\n\n";
    
    foreach ($all_categories as $category) {
        $category_url = get_category_link($category->term_id);
        $category_name = $category->name;
        $content .= "### {$category_name}\n";
        $content .= "カテゴリーURL: {$category_url}\n\n";
        
        $category_posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'category' => $category->term_id
        ));
        
        foreach ($category_posts as $post) {
            $post_url = get_permalink($post->ID);
            $post_title = $post->post_title;
            $content .= "- [{$post_title}]({$post_url})\n";
        }
        
        $content .= "\n";
    }
    
    // サイトの構造情報
    $content .= "## サイト構造\n\n";
    $content .= "- トップページ: {$site_url}\n";
    
    $pages = get_pages(array('sort_column' => 'menu_order'));
    if ($pages) {
        $content .= "- 固定ページ:\n";
        foreach ($pages as $page) {
            $page_url = get_permalink($page->ID);
            $page_title = $page->post_title;
            $content .= "  - [{$page_title}]({$page_url})\n";
        }
    }
    
    // ファイルに書き込み
    $file_path = ABSPATH . 'llms-full.txt';
    file_put_contents($file_path, $content);
    
    return true;
}

// 管理者向けにLLMS.txtの内容を手動で更新するためのアクション
add_action('wp_ajax_lto_regenerate_llms_txt', 'lto_ajax_regenerate_llms_txt');

function lto_ajax_regenerate_llms_txt() {
    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error('権限がありません');
    }
    
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');
    
    // LLMS.txtを再生成
    $result = lto_generate_llms_txt();
    
    if ($result) {
        wp_send_json_success('LLMS.txtが正常に更新されました');
    } else {
        wp_send_json_error('LLMS.txtの更新中にエラーが発生しました');
    }
}

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
