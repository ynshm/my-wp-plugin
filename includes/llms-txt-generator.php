
<?php
/**
 * LLMS.txt Generator
 * 
 * LLM（大規模言語モデル）用のサイトマップファイルを生成する機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// LLMS.txtファイルを生成する関数
function lto_generate_llms_txt() {
    // サイト情報
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url();
    
    // コンテンツの生成
    $content = "# {$site_name} - LLMS.txt\n\n";
    $content .= "Site URL: {$site_url}\n";
    $content .= "Description: {$site_description}\n\n";
    
    // サイト構造情報
    $content .= "## Site Structure\n\n";
    
    // メインページ
    $content .= "### Main Pages\n\n";
    
    // ホームページ
    $content .= "- [Home]({$site_url})\n";
    
    // 固定ページの取得
    $pages = get_pages(array(
        'sort_column' => 'menu_order',
        'sort_order' => 'ASC',
        'hierarchical' => 0,
        'number' => 10,
        'parent' => 0
    ));
    
    foreach ($pages as $page) {
        $page_url = get_permalink($page->ID);
        $page_title = $page->post_title;
        $content .= "- [{$page_title}]({$page_url})\n";
    }
    
    $content .= "\n";
    
    // カテゴリー一覧
    $content .= "### Categories\n\n";
    $categories = get_categories(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 10
    ));
    
    foreach ($categories as $category) {
        $category_url = get_category_link($category->term_id);
        $category_name = $category->name;
        $content .= "- [{$category_name}]({$category_url})\n";
    }
    
    $content .= "\n";
    
    // 人気記事
    $content .= "### Popular Content\n\n";
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
    
    foreach ($popular_posts as $post) {
        $post_url = get_permalink($post->ID);
        $post_title = $post->post_title;
        $content .= "- [{$post_title}]({$post_url})\n";
    }
    
    // LLMS.txtファイルを書き込む
    $file_path = ABSPATH . 'llms.txt';
    return file_put_contents($file_path, $content);
}

// LLMS-FULL.txtファイルを生成する詳細関数
function lto_generate_llms_full_txt() {
    // サイト情報
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url();
    
    // コンテンツの生成
    $content = "# {$site_name} - LLMS-FULL.txt\n\n";
    $content .= "Site URL: {$site_url}\n";
    $content .= "Description: {$site_description}\n\n";
    
    // サイト全体情報
    $content .= "## Complete Site Structure\n\n";
    
    // 全固定ページ
    $content .= "### All Pages\n\n";
    
    $all_pages = get_pages();
    
    foreach ($all_pages as $page) {
        $page_url = get_permalink($page->ID);
        $page_title = $page->post_title;
        $content .= "- [{$page_title}]({$page_url})\n";
    }
    
    $content .= "\n";
    
    // 全カテゴリーとそこに含まれる記事
    $content .= "### All Categories and Posts\n\n";
    $categories = get_categories();
    
    foreach ($categories as $category) {
        $category_url = get_category_link($category->term_id);
        $category_name = $category->name;
        $content .= "#### {$category_name}\n";
        $content .= "[Category Link]({$category_url})\n\n";
        
        $cat_posts = get_posts(array(
            'numberposts' => 50,
            'category' => $category->term_id
        ));
        
        foreach ($cat_posts as $post) {
            $post_url = get_permalink($post->ID);
            $post_title = $post->post_title;
            $content .= "- [{$post_title}]({$post_url})\n";
        }
        $content .= "\n";
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
        wp_send_json_success(__('LLMS.txt files generated successfully!', 'llm-traffic-optimizer'));
    } else {
        wp_send_json_error(__('Failed to generate LLMS.txt files. Please check file permissions.', 'llm-traffic-optimizer'));
    }
}

// AJAX経由でLLMS.txtの内容を取得する
add_action('wp_ajax_lto_get_llms_content', 'lto_ajax_get_llms_content');

function lto_ajax_get_llms_content() {
    // セキュリティチェック
    check_ajax_referer('lto_ajax_nonce', 'nonce');

    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have sufficient permissions to perform this action.', 'llm-traffic-optimizer'));
        return;
    }

    $file_path = ABSPATH . 'llms.txt';
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        wp_send_json_success($content);
    } else {
        wp_send_json_error(__('LLMS.txt file does not exist. Please generate it first.', 'llm-traffic-optimizer'));
    }
}
