<?php
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

// New functions from edited snippet
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
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
?>