<?php
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// 管理メニューを追加
add_action('admin_menu', 'lto_add_admin_menu');

function lto_add_admin_menu() {
    add_menu_page(
        __('LLM Traffic Optimizer', 'llm-traffic-optimizer'),
        __('LLM Traffic', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer',
        'lto_render_main_page',
        'dashicons-chart-line',
        30
    );

    add_submenu_page(
        'llm-traffic-optimizer',
        __('Dashboard', 'llm-traffic-optimizer'),
        __('Dashboard', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer',
        'lto_render_main_page'
    );

    add_submenu_page(
        'llm-traffic-optimizer',
        __('Settings', 'llm-traffic-optimizer'),
        __('Settings', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-settings',
        'lto_render_settings_page'
    );

    add_submenu_page(
        'llm-traffic-optimizer',
        __('Analytics', 'llm-traffic-optimizer'),
        __('Analytics', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-analytics',
        'lto_render_analytics_page'
    );
}

// メインページレンダリング
function lto_render_main_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="lto-dashboard-container">
            <div class="lto-dashboard-header">
                <h2><?php _e('LLM Traffic Optimizer Dashboard', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Monitor and optimize your website for AI search traffic.', 'llm-traffic-optimizer'); ?></p>
            </div>

            <div class="lto-stats-container">
                <div class="lto-stat-box">
                    <h3><?php _e('Total AI Referrals', 'llm-traffic-optimizer'); ?></h3>
                    <div class="lto-stat-value"><?php echo esc_html(lto_get_total_ai_referrals()); ?></div>
                </div>

                <div class="lto-stat-box">
                    <h3><?php _e('Top AI-Referred Content', 'llm-traffic-optimizer'); ?></h3>
                    <ul class="lto-top-content">
                        <?php
                        $top_content = lto_get_top_ai_content(5);
                        if (empty($top_content)) {
                            echo '<li>' . __('No data available yet', 'llm-traffic-optimizer') . '</li>';
                        } else {
                            foreach ($top_content as $content) {
                                echo '<li><a href="' . esc_url(get_permalink($content->post_id)) . '">' . esc_html(get_the_title($content->post_id)) . '</a> (' . esc_html($content->ai_referrals) . ')</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>

                <div class="lto-stat-box">
                    <h3><?php _e('LLMS.txt Status', 'llm-traffic-optimizer'); ?></h3>
                    <div class="lto-status-indicator">
                        <?php if (file_exists(ABSPATH . 'llms.txt')) : ?>
                            <span class="lto-status-good"><?php _e('Active', 'llm-traffic-optimizer'); ?></span>
                        <?php else : ?>
                            <span class="lto-status-bad"><?php _e('Missing', 'llm-traffic-optimizer'); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php _e('View LLMS.txt', 'llm-traffic-optimizer'); ?></a>
                </div>
            </div>

            <div class="lto-action-buttons">
                <a href="<?php echo esc_url(admin_url('admin.php?page=llm-traffic-optimizer-settings')); ?>" class="button button-primary"><?php _e('Configure Settings', 'llm-traffic-optimizer'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=llm-traffic-optimizer-analytics')); ?>" class="button button-secondary"><?php _e('View Detailed Analytics', 'llm-traffic-optimizer'); ?></a>
            </div>
        </div>
    </div>
    <?php
}

// 設定ページレンダリング
function lto_render_settings_page() {
    // Save settings if form is submitted
    if (isset($_POST['lto_save_settings']) && check_admin_referer('lto_settings_nonce')) {
        $openai_api_key = isset($_POST['lto_openai_api_key']) ? sanitize_text_field($_POST['lto_openai_api_key']) : '';
        $openai_model = isset($_POST['lto_openai_model']) ? sanitize_text_field($_POST['lto_openai_model']) : 'gpt-3.5-turbo';
        $temperature = isset($_POST['lto_temperature']) ? floatval($_POST['lto_temperature']) : 0.7;
        $enable_auto_summaries = isset($_POST['lto_enable_auto_summaries']) ? 'yes' : 'no';

        update_option('lto_openai_api_key', $openai_api_key);
        update_option('lto_openai_model', $openai_model);
        update_option('lto_temperature', $temperature);
        update_option('lto_enable_auto_summaries', $enable_auto_summaries);

        // Regenerate LLMS.txt files
        if (function_exists('lto_generate_llms_txt')) {
            lto_generate_llms_txt();
        }

        if (function_exists('lto_generate_llms_full_txt')) {
            lto_generate_llms_full_txt();
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'llm-traffic-optimizer') . '</p></div>';
    }

    // Get current settings
    $openai_api_key = get_option('lto_openai_api_key', '');
    $openai_model = get_option('lto_openai_model', 'gpt-3.5-turbo');
    $temperature = get_option('lto_temperature', 0.7);
    $enable_auto_summaries = get_option('lto_enable_auto_summaries', 'yes');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('lto_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('OpenAI API Key', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <input type="password" name="lto_openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text" />
                        <p class="description"><?php _e('Your OpenAI API key is required for generating AI summaries.', 'llm-traffic-optimizer'); ?></p>
                        <button type="button" id="lto-validate-api-key" class="button button-secondary"><?php _e('Validate API Key', 'llm-traffic-optimizer'); ?></button>
                        <span id="lto-api-key-validation-result"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('OpenAI Model', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <select name="lto_openai_model">
                            <option value="gpt-3.5-turbo" <?php selected($openai_model, 'gpt-3.5-turbo'); ?>><?php _e('GPT-3.5 Turbo', 'llm-traffic-optimizer'); ?></option>
                            <option value="gpt-4" <?php selected($openai_model, 'gpt-4'); ?>><?php _e('GPT-4', 'llm-traffic-optimizer'); ?></option>
                        </select>
                        <p class="description"><?php _e('Select the AI model to use for generating summaries.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Temperature', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <input type="range" name="lto_temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr($temperature); ?>" />
                        <span id="lto-temperature-value"><?php echo esc_html($temperature); ?></span>
                        <p class="description"><?php _e('Controls randomness: 0 is more focused, 1 is more creative.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto-Generate Summaries', 'llm-traffic-optimizer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lto_enable_auto_summaries" <?php checked($enable_auto_summaries, 'yes'); ?> />
                            <?php _e('Automatically generate AI summaries for your content', 'llm-traffic-optimizer'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, the plugin will create AI-friendly summary posts for your content.', 'llm-traffic-optimizer'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="lto_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'llm-traffic-optimizer'); ?>" />
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Update temperature value display
        $('input[name="lto_temperature"]').on('input', function() {
            $('#lto-temperature-value').text($(this).val());
        });

        // API key validation
        $('#lto-validate-api-key').on('click', function() {
            const apiKey = $('input[name="lto_openai_api_key"]').val();
            const resultElement = $('#lto-api-key-validation-result');

            if (!apiKey) {
                resultElement.html('<span style="color: red;">APIキーを入力してください</span>');
                return;
            }

            resultElement.html('<span style="color: blue;">検証中...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_validate_api_key',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('lto_validate_api_key_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElement.html('<span style="color: green;">有効なAPIキーです</span>');
                    } else {
                        resultElement.html('<span style="color: red;">無効なAPIキー: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElement.html('<span style="color: red;">検証中にエラーが発生しました</span>');
                }
            });
        });
    });
    </script>
    <?php
}

// アナリティクスページのレンダリング
function lto_render_analytics_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="lto-analytics-container">
            <h2><?php _e('AI Traffic Analytics', 'llm-traffic-optimizer'); ?></h2>

            <div class="lto-analytics-table-container">
                <h3><?php _e('Top Posts by AI Referrals', 'llm-traffic-optimizer'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Post Title', 'llm-traffic-optimizer'); ?></th>
                            <th><?php _e('Total Views', 'llm-traffic-optimizer'); ?></th>
                            <th><?php _e('AI Referrals', 'llm-traffic-optimizer'); ?></th>
                            <th><?php _e('AI Referral %', 'llm-traffic-optimizer'); ?></th>
                            <th><?php _e('Last Updated', 'llm-traffic-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $analytics_data = lto_get_analytics_data();
                        if (empty($analytics_data)) {
                            echo '<tr><td colspan="5">' . __('No data available yet. Analytics will appear as your content receives traffic.', 'llm-traffic-optimizer') . '</td></tr>';
                        } else {
                            foreach ($analytics_data as $item) {
                                $percentage = $item->views > 0 ? round(($item->ai_referrals / $item->views) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" target="_blank">
                                            <?php echo esc_html(get_the_title($item->post_id)); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($item->views); ?></td>
                                    <td><?php echo esc_html($item->ai_referrals); ?></td>
                                    <td><?php echo esc_html($percentage); ?>%</td>
                                    <td><?php echo esc_html($item->last_updated); ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="lto-export-section">
                <h3><?php _e('Export Data', 'llm-traffic-optimizer'); ?></h3>
                <button id="lto-export-csv" class="button button-secondary"><?php _e('Export as CSV', 'llm-traffic-optimizer'); ?></button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Export to CSV functionality
        $('#lto-export-csv').on('click', function() {
            // Create CSV content
            var csv = 'Post ID,Post Title,Total Views,AI Referrals,AI Referral %,Last Updated\n';

            $('.lto-analytics-table-container table tbody tr').each(function() {
                var postTitle = $(this).find('td:nth-child(1) a').text().trim();
                var postUrl = $(this).find('td:nth-child(1) a').attr('href');
                var postId = postUrl ? postUrl.split('=').pop() : '';
                var views = $(this).find('td:nth-child(2)').text().trim();
                var aiReferrals = $(this).find('td:nth-child(3)').text().trim();
                var percentage = $(this).find('td:nth-child(4)').text().trim();
                var lastUpdated = $(this).find('td:nth-child(5)').text().trim();

                csv += '"' + postId + '","' + postTitle + '","' + views + '","' + aiReferrals + '","' + percentage + '","' + lastUpdated + '"\n';
            });

            // Create download link
            var downloadLink = document.createElement('a');
            downloadLink.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            downloadLink.download = 'llm-traffic-analytics.csv';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    });
    </script>
    <?php
}

// アナリティクスデータの取得
function lto_get_analytics_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $results = $wpdb->get_results("
        SELECT * FROM $table_name
        ORDER BY ai_referrals DESC
        LIMIT 100
    ");

    return $results ? $results : array();
}

// 総AIリファラル数の取得
function lto_get_total_ai_referrals() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $total = $wpdb->get_var("
        SELECT SUM(ai_referrals) 
        FROM $table_name
    ");

    return $total ? $total : 0;
}

// AIコンテンツトップの取得
function lto_get_top_ai_content($limit = 5) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_name
        ORDER BY ai_referrals DESC
        LIMIT %d
    ", $limit));

    return $results ? $results : array();
}

// Register settings (moved from original code)
add_action('admin_init', 'lto_register_settings');

function lto_register_settings() {
    register_setting('lto-settings-group', 'lto_openai_api_key');
    register_setting('lto-settings-group', 'lto_openai_model');
    register_setting('lto-settings-group', 'lto_temperature');
    register_setting('lto-settings-group', 'lto_enable_auto_summaries');
    register_setting('lto-settings-group', 'lto_summary_frequency');
    register_setting('lto-settings-group', 'lto_summary_category');
    register_setting('lto-settings-group', 'lto_site_description');
    register_setting('lto-settings-group', 'lto_top_posts_count');
    register_setting('lto-settings-group', 'lto_enable_analytics');
}


// Helper function to get AI traffic stats (modified to use new functions)
//function lto_get_ai_traffic_stats() { ... } //Removed - replaced by lto_get_total_ai_referrals

// Helper function to get top AI-driven posts (modified to use new functions)
//function lto_get_top_ai_posts($limit = 5) { ... } //Removed - replaced by lto_get_top_ai_content


// Generate summary page (largely removed - functionality might need to be added elsewhere)
//function lto_generate_page() { ... } // Removed -  Functionality should be integrated elsewhere, perhaps within a new admin page or by modifying the existing settings page.

?>
<script>
jQuery(document).ready(function($) {
    // Handle temperature slider updates (moved and updated for new ID)
    $('#lto_temperature').on('input', function() {
        $('#temperature_value').text($(this).val());
    });

    // API Key validation (moved and updated for new ID)
    $('#validate_api_key').on('click', function() {
        const apiKey = $('input[name="lto_openai_api_key"]').val();
        const statusElement = $('#api_key_status');

        if (!apiKey) {
            statusElement.text('Please enter an API key').css('color', 'red');
            return;
        }

        statusElement.text('Validating...').css('color', 'blue');

        // Ajax request to validate API key
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lto_validate_api_key',
                api_key: apiKey,
                nonce: '<?php echo wp_create_nonce('lto_validate_api_key_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    statusElement.text('✓ API Key is valid').css('color', 'green');
                } else {
                    statusElement.text('✗ ' + response.data).css('color', 'red');
                }
            },
            error: function() {
                statusElement.text('✗ Validation failed. Please try again.').css('color', 'red');
            }
        });
    });
    //Summary type change handler (moved from generate page)
    $('#summary_type').on('change', function() {
        if ($(this).val() === 'category') {
            $('#category_row').show();
        } else {
            $('#category_row').hide();
        }
    });
});
</script>