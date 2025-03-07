<?php
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// 基本的なLLMS.txtファイルの生成
function lto_generate_llms_txt($return_content = false) {
    try {
        // サイト情報の取得
        $site_url = home_url();
        $site_name = get_bloginfo('name');

        // 最新の投稿を取得
        $recent_posts = get_posts(array(
            'numberposts' => 10,
            'post_status' => 'publish'
        ));

        // LLMS.txtコンテンツの作成
        $content = "# LLMS.txt for {$site_name}\n";
        $content .= "# Version 1.0\n";
        $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "# Allow all LLMs to access this site\n";
        $content .= "User-agent: *\n";
        $content .= "Allow: /\n\n";

        $content .= "# Latest content\n";
        foreach ($recent_posts as $post) {
            $permalink = get_permalink($post->ID);
            $content .= "Allow: {$permalink}\n";
        }

        if ($return_content) {
            return $content;
        }

        // ファイルに保存
        $result = file_put_contents(ABSPATH . 'llms.txt', $content);

        if ($result === false) {
            // ファイル保存に失敗した場合
            error_log('Failed to save llms.txt file');
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log('Error generating LLMS.txt: ' . $e->getMessage());
        return false;
    }
}

// 詳細なLLMS-FULL.txtファイルの生成
function lto_generate_llms_full_txt($return_content = false) {
    try {
        // サイト情報の取得
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');

        // カテゴリーの取得
        $categories = get_categories(array(
            'orderby' => 'name',
            'order'   => 'ASC'
        ));

        // LLMS-FULL.txtコンテンツの作成
        $content = "# LLMS-FULL.txt for {$site_name}\n";
        $content .= "# Version 1.0\n";
        $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "# Site Information\n";
        $content .= "Name: {$site_name}\n";
        $content .= "Description: {$site_description}\n";
        $content .= "URL: {$site_url}\n\n";

        $content .= "# Content Categories\n";
        foreach ($categories as $category) {
            $content .= "Category: {$category->name}\n";
            $content .= "URL: " . get_category_link($category->term_id) . "\n";
            $content .= "\n";
        }

        $content .= "# Content Guidance\n";
        $content .= "Preferred-Content-Format: HTML\n";
        $content .= "Preferred-Citation-Format: Title, URL\n";
        $content .= "Content-Update-Frequency: Daily\n\n";

        $content .= "# Allow all LLMs to access and index this site\n";
        $content .= "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /wp-admin/\n";
        $content .= "Disallow: /wp-includes/\n";

        if ($return_content) {
            return $content;
        }

        // ファイルに保存
        $result = file_put_contents(ABSPATH . 'llms-full.txt', $content);

        if ($result === false) {
            // ファイル保存に失敗した場合
            error_log('Failed to save llms-full.txt file');
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log('Error generating LLMS-FULL.txt: ' . $e->getMessage());
        return false;
    }
}

?>