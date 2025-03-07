
<?php
// Create admin menu
add_action('admin_menu', 'lto_add_admin_menu');

function lto_add_admin_menu() {
    add_menu_page(
        __('LLM Traffic Optimizer', 'llm-traffic-optimizer'),
        __('LLM Traffic', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer',
        'lto_admin_page',
        'dashicons-chart-line',
        30
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('Settings', 'llm-traffic-optimizer'),
        __('Settings', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-settings',
        'lto_settings_page'
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('Generate Summary', 'llm-traffic-optimizer'),
        __('Generate Summary', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-generate',
        'lto_generate_page'
    );
}

// Register settings
add_action('admin_init', 'lto_register_settings');

function lto_register_settings() {
    register_setting('lto-settings-group', 'lto_openai_api_key');
    register_setting('lto-settings-group', 'lto_enable_auto_summaries');
    register_setting('lto-settings-group', 'lto_summary_frequency');
    register_setting('lto-settings-group', 'lto_summary_category');
    register_setting('lto-settings-group', 'lto_site_description');
    register_setting('lto-settings-group', 'lto_top_posts_count');
    register_setting('lto-settings-group', 'lto_enable_analytics');
}

// Admin main page
function lto_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="lto-dashboard">
            <div class="lto-card">
                <h2><?php _e('LLM Traffic Overview', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Monitor traffic from AI sources and optimize your content.', 'llm-traffic-optimizer'); ?></p>
                
                <?php
                // Get analytics data
                $ai_traffic = lto_get_ai_traffic_stats();
                ?>
                
                <div class="lto-stats">
                    <div class="lto-stat-item">
                        <span class="lto-stat-number"><?php echo esc_html($ai_traffic['total']); ?></span>
                        <span class="lto-stat-label"><?php _e('Total AI Visits', 'llm-traffic-optimizer'); ?></span>
                    </div>
                    <div class="lto-stat-item">
                        <span class="lto-stat-number"><?php echo esc_html($ai_traffic['trend']); ?>%</span>
                        <span class="lto-stat-label"><?php _e('Growth Trend', 'llm-traffic-optimizer'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="lto-card">
                <h2><?php _e('Quick Actions', 'llm-traffic-optimizer'); ?></h2>
                <ul class="lto-actions">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=llm-traffic-optimizer-generate')); ?>" class="button button-primary">
                            <?php _e('Generate New Summary', 'llm-traffic-optimizer'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(site_url('llms.txt')); ?>" target="_blank" class="button">
                            <?php _e('View LLMS.txt', 'llm-traffic-optimizer'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(site_url('llms-full.txt')); ?>" target="_blank" class="button">
                            <?php _e('View LLMS-Full.txt', 'llm-traffic-optimizer'); ?>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="lto-card">
                <h2><?php _e('Top AI-Driven Content', 'llm-traffic-optimizer'); ?></h2>
                <?php
                $top_posts = lto_get_top_ai_posts(5);
                if (!empty($top_posts)) {
                    echo '<ul class="lto-post-list">';
                    foreach ($top_posts as $post) {
                        echo '<li>';
                        echo '<a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
                        echo '<span class="lto-visits">' . esc_html($post->ai_visits) . ' ' . __('AI visits', 'llm-traffic-optimizer') . '</span>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . __('No data available yet. Enable analytics to track AI visits.', 'llm-traffic-optimizer') . '</p>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <style>
    .lto-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .lto-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .lto-stats {
        display: flex;
        justify-content: space-around;
        margin-top: 20px;
    }
    
    .lto-stat-item {
        text-align: center;
    }
    
    .lto-stat-number {
        display: block;
        font-size: 24px;
        font-weight: bold;
        color: #0073aa;
    }
    
    .lto-stat-label {
        display: block;
        margin-top: 5px;
        color: #666;
    }
    
    .lto-actions {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .lto-actions li {
        margin-bottom: 10px;
    }
    
    .lto-post-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    
    .lto-post-list li {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .lto-visits {
        color: #666;
        font-size: 0.9em;
    }
    </style>
    <?php
}

// Settings page
function lto_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('lto-settings-group'); ?>
            <?php do_settings_sections('lto-settings-group'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('OpenAI API Key', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <input type="password" name="lto_openai_api_key" value="<?php echo esc_attr(get_option('lto_openai_api_key')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Required for generating summary content with ChatGPT.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Auto Summaries', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <select name="lto_enable_auto_summaries">
                            <option value="yes" <?php selected(get_option('lto_enable_auto_summaries', 'yes'), 'yes'); ?>><?php _e('Yes', 'llm-traffic-optimizer'); ?></option>
                            <option value="no" <?php selected(get_option('lto_enable_auto_summaries', 'yes'), 'no'); ?>><?php _e('No', 'llm-traffic-optimizer'); ?></option>
                        </select>
                        <p class="description"><?php _e('Automatically generate summary posts on a schedule.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Summary Generation Frequency', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <select name="lto_summary_frequency">
                            <option value="daily" <?php selected(get_option('lto_summary_frequency', 'weekly'), 'daily'); ?>><?php _e('Daily', 'llm-traffic-optimizer'); ?></option>
                            <option value="weekly" <?php selected(get_option('lto_summary_frequency', 'weekly'), 'weekly'); ?>><?php _e('Weekly', 'llm-traffic-optimizer'); ?></option>
                            <option value="monthly" <?php selected(get_option('lto_summary_frequency', 'weekly'), 'monthly'); ?>><?php _e('Monthly', 'llm-traffic-optimizer'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Summary Post Category', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <?php
                        $categories = get_categories(array('hide_empty' => false));
                        $selected_category = get_option('lto_summary_category', '');
                        ?>
                        <select name="lto_summary_category">
                            <option value=""><?php _e('Select Category', 'llm-traffic-optimizer'); ?></option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($selected_category, $category->term_id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Site Description', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <textarea name="lto_site_description" rows="3" class="large-text"><?php echo esc_textarea(get_option('lto_site_description', get_bloginfo('description'))); ?></textarea>
                        <p class="description"><?php _e('Used in LLMS.txt to describe your site to AI search engines.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Number of Top Posts to Include', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <input type="number" name="lto_top_posts_count" value="<?php echo esc_attr(get_option('lto_top_posts_count', '10')); ?>" min="5" max="50" />
                        <p class="description"><?php _e('Number of popular posts to include in summaries.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Analytics Tracking', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <select name="lto_enable_analytics">
                            <option value="yes" <?php selected(get_option('lto_enable_analytics', 'yes'), 'yes'); ?>><?php _e('Yes', 'llm-traffic-optimizer'); ?></option>
                            <option value="no" <?php selected(get_option('lto_enable_analytics', 'yes'), 'no'); ?>><?php _e('No', 'llm-traffic-optimizer'); ?></option>
                        </select>
                        <p class="description"><?php _e('Track visits from AI sources separately.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Generate summary page
function lto_generate_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
        // Handle form submission
        if (isset($_POST['lto_generate_summary'])) {
            check_admin_referer('lto_generate_summary_nonce');
            
            $summary_type = sanitize_text_field($_POST['summary_type']);
            $title = sanitize_text_field($_POST['summary_title']);
            
            if (empty($title)) {
                echo '<div class="notice notice-error"><p>' . __('Please enter a title for the summary.', 'llm-traffic-optimizer') . '</p></div>';
            } else {
                // Generate the summary
                $result = lto_generate_summary($summary_type, $title);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . 
                        sprintf(
                            __('Summary created successfully! <a href="%s">View Post</a>', 'llm-traffic-optimizer'),
                            get_permalink($result)
                        ) . '</p></div>';
                }
            }
        }
        ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('lto_generate_summary_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Summary Type', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <select name="summary_type" id="summary_type">
                            <option value="popular"><?php _e('Popular Posts Summary', 'llm-traffic-optimizer'); ?></option>
                            <option value="category"><?php _e('Category Summary', 'llm-traffic-optimizer'); ?></option>
                            <option value="latest"><?php _e('Latest Posts Summary', 'llm-traffic-optimizer'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr id="category_row" style="display:none;">
                    <th scope="row"><?php _e('Select Category', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <?php
                        $categories = get_categories(array('hide_empty' => false));
                        ?>
                        <select name="category_id">
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Summary Title', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <input type="text" name="summary_title" class="regular-text" required />
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Generate Summary', 'llm-traffic-optimizer'), 'primary', 'lto_generate_summary'); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#summary_type').on('change', function() {
                if ($(this).val() === 'category') {
                    $('#category_row').show();
                } else {
                    $('#category_row').hide();
                }
            });
        });
        </script>
    </div>
    <?php
}

// Helper function to get AI traffic stats
function lto_get_ai_traffic_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    // Default return if no data
    $default = array(
        'total' => 0,
        'trend' => 0
    );
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return $default;
    }
    
    // Get total AI visits
    $total = $wpdb->get_var("SELECT SUM(ai_referrals) FROM $table_name");
    
    // Get trend (comparing current month with previous month)
    $current_month = date('Y-m');
    $prev_month = date('Y-m', strtotime('-1 month'));
    
    $current_total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(ai_referrals) FROM $table_name WHERE DATE_FORMAT(last_updated, '%%Y-%%m') = %s",
        $current_month
    ));
    
    $prev_total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(ai_referrals) FROM $table_name WHERE DATE_FORMAT(last_updated, '%%Y-%%m') = %s",
        $prev_month
    ));
    
    $trend = 0;
    if ($prev_total > 0 && $current_total > 0) {
        $trend = round((($current_total - $prev_total) / $prev_total) * 100);
    }
    
    return array(
        'total' => $total ? $total : 0,
        'trend' => $trend
    );
}

// Helper function to get top AI-driven posts
function lto_get_top_ai_posts($limit = 5) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return array();
    }
    
    $query = $wpdb->prepare(
        "SELECT p.ID, p.post_title, a.ai_referrals as ai_visits 
         FROM {$wpdb->posts} p
         JOIN $table_name a ON p.ID = a.post_id
         WHERE p.post_status = 'publish'
         ORDER BY a.ai_referrals DESC
         LIMIT %d",
        $limit
    );
    
    $results = $wpdb->get_results($query);
    
    return $results ? $results : array();
}
