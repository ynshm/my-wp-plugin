
<?php
// Generate basic LLMS.txt file
function lto_generate_llms_txt($return_content = false) {
    $site_name = get_bloginfo('name');
    $site_description = get_option('lto_site_description', get_bloginfo('description'));
    
    // Get top pages
    $top_pages = lto_get_top_pages(10);
    
    // Get categories
    $categories = get_categories(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 5,
        'hide_empty' => true
    ));
    
    // Build the content
    $content = "# {$site_name}\n\n";
    $content .= "> {$site_description}\n\n";
    
    // Main pages section
    $content .= "## Main Pages\n\n";
    
    // Add homepage
    $content .= "- [Home](" . home_url() . "): " . __('Main website homepage', 'llm-traffic-optimizer') . "\n";
    
    // Add about page if exists
    $about_page = get_page_by_path('about');
    if ($about_page) {
        $content .= "- [About](" . get_permalink($about_page->ID) . "): " . __('About our website', 'llm-traffic-optimizer') . "\n";
    }
    
    // Add contact page if exists
    $contact_page = get_page_by_path('contact');
    if ($contact_page) {
        $content .= "- [Contact](" . get_permalink($contact_page->ID) . "): " . __('Contact information', 'llm-traffic-optimizer') . "\n";
    }
    
    // Categories section
    if (!empty($categories)) {
        $content .= "\n## Categories\n\n";
        
        foreach ($categories as $category) {
            $content .= "- [" . $category->name . "](" . get_category_link($category->term_id) . "): " . 
                        wp_trim_words($category->description, 15, '...') . "\n";
        }
    }
    
    // Popular content section
    if (!empty($top_pages)) {
        $content .= "\n## Popular Content\n\n";
        
        foreach ($top_pages as $page) {
            $content .= "- [" . get_the_title($page->ID) . "](" . get_permalink($page->ID) . ")\n";
        }
    }
    
    // Get latest summary posts
    $summary_posts = lto_get_summary_posts(3);
    
    if (!empty($summary_posts)) {
        $content .= "\n## Curated Summaries\n\n";
        
        foreach ($summary_posts as $post) {
            $content .= "- [" . get_the_title($post->ID) . "](" . get_permalink($post->ID) . ")\n";
        }
    }
    
    if ($return_content) {
        return $content;
    } else {
        // Save file to root directory
        $file_path = ABSPATH . 'llms.txt';
        file_put_contents($file_path, $content);
        return true;
    }
}

// Generate full LLMS.txt file
function lto_generate_llms_full_txt($return_content = false) {
    $site_name = get_bloginfo('name');
    $site_description = get_option('lto_site_description', get_bloginfo('description'));
    
    // Get top pages
    $top_pages = lto_get_top_pages(20);
    
    // Get all categories
    $categories = get_categories(array(
        'orderby' => 'count',
        'order' => 'DESC',
        'hide_empty' => true
    ));
    
    // Build the content
    $content = "# {$site_name}\n\n";
    $content .= "> {$site_description}\n\n";
    
    // Main pages section
    $content .= "## Main Pages\n\n";
    
    // Add homepage
    $content .= "- [Home](" . home_url() . "): " . __('Main website homepage', 'llm-traffic-optimizer') . "\n";
    
    // Get all published pages
    $pages = get_pages(array(
        'sort_column' => 'menu_order',
        'post_status' => 'publish'
    ));
    
    foreach ($pages as $page) {
        $content .= "- [" . $page->post_title . "](" . get_permalink($page->ID) . "): " . 
                    wp_trim_words($page->post_excerpt ? $page->post_excerpt : $page->post_content, 15, '...') . "\n";
    }
    
    // Categories section with posts
    if (!empty($categories)) {
        $content .= "\n## Categories\n\n";
        
        foreach ($categories as $category) {
            $content .= "### " . $category->name . "\n\n";
            $content .= "> " . ($category->description ? $category->description : __('Articles in this category', 'llm-traffic-optimizer')) . "\n\n";
            $content .= "- [Category Index](" . get_category_link($category->term_id) . ")\n";
            
            // Get posts for this category
            $category_posts = get_posts(array(
                'category' => $category->term_id,
                'numberposts' => 10,
                'post_status' => 'publish'
            ));
            
            if (!empty($category_posts)) {
                foreach ($category_posts as $post) {
                    $content .= "- [" . $post->post_title . "](" . get_permalink($post->ID) . "): " . 
                                wp_trim_words($post->post_excerpt ? $post->post_excerpt : $post->post_content, 15, '...') . "\n";
                }
            }
            
            $content .= "\n";
        }
    }
    
    // Popular content section
    if (!empty($top_pages)) {
        $content .= "\n## Popular Content\n\n";
        
        foreach ($top_pages as $page) {
            $content .= "- [" . get_the_title($page->ID) . "](" . get_permalink($page->ID) . "): " . 
                        wp_trim_words(get_post_field('post_excerpt', $page->ID) ? get_post_field('post_excerpt', $page->ID) : get_post_field('post_content', $page->ID), 20, '...') . "\n";
        }
    }
    
    // Get all summary posts
    $summary_posts = lto_get_summary_posts(10);
    
    if (!empty($summary_posts)) {
        $content .= "\n## Curated Summaries\n\n";
        
        foreach ($summary_posts as $post) {
            $content .= "- [" . get_the_title($post->ID) . "](" . get_permalink($post->ID) . "): " . 
                        wp_trim_words($post->post_excerpt ? $post->post_excerpt : $post->post_content, 30, '...') . "\n";
        }
    }
    
    // Add recent posts
    $recent_posts = get_posts(array(
        'numberposts' => 10,
        'post_status' => 'publish'
    ));
    
    if (!empty($recent_posts)) {
        $content .= "\n## Recent Posts\n\n";
        
        foreach ($recent_posts as $post) {
            $content .= "- [" . $post->post_title . "](" . get_permalink($post->ID) . "): " . 
                        wp_trim_words($post->post_excerpt ? $post->post_excerpt : $post->post_content, 20, '...') . "\n";
        }
    }
    
    // Site structure and navigation
    $content .= "\n## Site Structure\n\n";
    $content .= "- [Archives](" . get_post_type_archive_link('post') . "): Browse all posts chronologically\n";
    
    if (function_exists('get_search_form')) {
        $content .= "- [Search](" . home_url('/?s=') . "): Search the site for specific content\n";
    }
    
    if ($return_content) {
        return $content;
    } else {
        // Save file to root directory
        $file_path = ABSPATH . 'llms-full.txt';
        file_put_contents($file_path, $content);
        return true;
    }
}

// Helper function to get top pages
function lto_get_top_pages($limit = 10) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    // If analytics table exists, use it
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id as ID, sum(views) as views
             FROM $table_name
             GROUP BY post_id
             ORDER BY views DESC
             LIMIT %d",
            $limit
        ));
        
        if (!empty($results)) {
            return $results;
        }
    }
    
    // Fallback to most commented posts if no analytics
    return get_posts(array(
        'numberposts' => $limit,
        'post_type' => 'post',
        'orderby' => 'comment_count',
        'order' => 'DESC',
        'post_status' => 'publish'
    ));
}

// Helper function to get summary posts
function lto_get_summary_posts($limit = 5) {
    // Get posts with a specific meta key indicating they are auto-generated summaries
    return get_posts(array(
        'numberposts' => $limit,
        'post_type' => 'post',
        'post_status' => 'publish',
        'meta_key' => '_lto_summary_type',
        'orderby' => 'date',
        'order' => 'DESC'
    ));
}
