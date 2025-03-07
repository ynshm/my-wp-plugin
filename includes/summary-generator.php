
<?php
// Function to generate summary posts
function lto_generate_summary_posts() {
    $frequency = get_option('lto_summary_frequency', 'weekly');
    
    // Check if it's time to generate a new summary
    if (!lto_should_generate_summary($frequency)) {
        return false;
    }
    
    // Generate popular posts summary
    $title = sprintf(
        __('Top %s for %s', 'llm-traffic-optimizer'),
        __('Articles', 'llm-traffic-optimizer'),
        date_i18n('F Y')
    );
    
    lto_generate_summary('popular', $title);
    
    // Generate category summaries (for top 3 categories)
    $categories = get_categories(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 3,
        'hide_empty' => true
    ));
    
    foreach ($categories as $category) {
        $title = sprintf(
            __('%s: A Complete Guide', 'llm-traffic-optimizer'),
            $category->name
        );
        
        lto_generate_summary('category', $title, $category->term_id);
    }
    
    return true;
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
