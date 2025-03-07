
<?php
/**
 * 管理メニューと設定ページの機能
 */

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
        'lto_display_dashboard_page',
        'dashicons-chart-line',
        85
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('Dashboard', 'llm-traffic-optimizer'),
        __('Dashboard', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer',
        'lto_display_dashboard_page'
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('Settings', 'llm-traffic-optimizer'),
        __('Settings', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-settings',
        'lto_display_settings_page'
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('LLMS.txt Generator', 'llm-traffic-optimizer'),
        __('LLMS.txt Generator', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-llms',
        'lto_display_llms_page'
    );
    
    add_submenu_page(
        'llm-traffic-optimizer',
        __('Summaries', 'llm-traffic-optimizer'),
        __('Summaries', 'llm-traffic-optimizer'),
        'manage_options',
        'llm-traffic-optimizer-summaries',
        'lto_display_summaries_page'
    );
}

// ダッシュボードページの表示
function lto_display_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Welcome to LLM Traffic Optimizer! To get started, set your OpenAI API key in the Settings page.', 'llm-traffic-optimizer'); ?></p>
        </div>
        
        <div class="lto-dashboard-container">
            <div class="lto-dashboard-card">
                <h2><?php _e('AI Traffic Statistics', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Coming soon: Statistics on AI traffic to your site', 'llm-traffic-optimizer'); ?></p>
            </div>
            
            <div class="lto-dashboard-card">
                <h2><?php _e('Popular Content', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Coming soon: Your most popular content for AI visitors', 'llm-traffic-optimizer'); ?></p>
            </div>
        </div>
    </div>
    <?php
}

// 設定ページの表示
function lto_display_settings_page() {
    // 保存済みの設定を取得
    $api_key = get_option('lto_openai_api_key', '');
    $current_model = get_option('lto_openai_model', 'gpt-3.5-turbo');
    $temperature = get_option('lto_temperature', 0.7);
    $enable_auto_summaries = get_option('lto_enable_auto_summaries', 'yes');
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form id="lto-settings-form" method="post" action="options.php">
            <div class="lto-settings-container">
                <div class="lto-settings-card">
                    <h2><?php _e('OpenAI API Settings', 'llm-traffic-optimizer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lto-openai-api-key"><?php _e('API Key', 'llm-traffic-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="lto-openai-api-key" 
                                       name="lto_openai_api_key" 
                                       class="regular-text" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       autocomplete="off" />
                                <button type="button" 
                                        id="lto-validate-api-key" 
                                        class="button button-secondary">
                                    <?php _e('Validate & Save', 'llm-traffic-optimizer'); ?>
                                </button>
                                <span id="lto-api-validation-result"></span>
                                <p class="description">
                                    <?php _e('Enter your OpenAI API key. This is required for AI-generated summaries and content.', 'llm-traffic-optimizer'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Get an API key', 'llm-traffic-optimizer'); ?></a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="lto-openai-model"><?php _e('Model', 'llm-traffic-optimizer'); ?></label>
                            </th>
                            <td>
                                <select id="lto-openai-model" name="lto_openai_model">
                                    <option value="gpt-4o" <?php selected($current_model, 'gpt-4o'); ?>>GPT-4o</option>
                                    <option value="gpt-4-turbo" <?php selected($current_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                    <option value="gpt-4" <?php selected($current_model, 'gpt-4'); ?>>GPT-4</option>
                                    <option value="gpt-3.5-turbo" <?php selected($current_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                    <option value="gpt-3.5-turbo-16k" <?php selected($current_model, 'gpt-3.5-turbo-16k'); ?>>GPT-3.5 Turbo 16K</option>
                                </select>
                                <p class="description">
                                    <?php _e('Select the OpenAI model to use for content generation.', 'llm-traffic-optimizer'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="lto-temperature"><?php _e('Temperature', 'llm-traffic-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="range" 
                                       id="lto-temperature" 
                                       name="lto_temperature" 
                                       min="0" 
                                       max="1" 
                                       step="0.1" 
                                       value="<?php echo esc_attr($temperature); ?>" />
                                <span id="lto-temperature-value"><?php echo esc_html($temperature); ?></span>
                                <p class="description">
                                    <?php _e('Control the randomness of the AI output. Lower values make the output more focused and deterministic, higher values make it more creative.', 'llm-traffic-optimizer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" 
                            id="lto-save-model-settings" 
                            class="button button-primary">
                        <?php _e('Save Model Settings', 'llm-traffic-optimizer'); ?>
                    </button>
                    <span id="lto-model-settings-result"></span>
                </div>
                
                <div class="lto-settings-card">
                    <h2><?php _e('Summary Generation Settings', 'llm-traffic-optimizer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('Automatic Summaries', 'llm-traffic-optimizer'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <span><?php _e('Automatic Summaries', 'llm-traffic-optimizer'); ?></span>
                                    </legend>
                                    <label for="lto-enable-auto-summaries">
                                        <input type="checkbox" 
                                               id="lto-enable-auto-summaries" 
                                               name="lto_enable_auto_summaries" 
                                               value="yes" 
                                               <?php checked($enable_auto_summaries, 'yes'); ?> />
                                        <?php _e('Enable automatic summary generation', 'llm-traffic-optimizer'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('When enabled, summaries will be automatically generated for new posts and pages.', 'llm-traffic-optimizer'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" 
                            id="lto-save-summary-settings" 
                            class="button button-primary">
                        <?php _e('Save Summary Settings', 'llm-traffic-optimizer'); ?>
                    </button>
                    <span id="lto-summary-settings-result"></span>
                </div>
            </div>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // 温度値の表示を更新
        $('#lto-temperature').on('input', function() {
            $('#lto-temperature-value').text($(this).val());
        });
        
        // APIキーの検証
        $('#lto-validate-api-key').on('click', function() {
            const apiKey = $('#lto-openai-api-key').val();
            const resultElem = $('#lto-api-validation-result');
            
            if (!apiKey) {
                resultElem.html('<span style="color: red;">APIキーを入力してください</span>');
                return;
            }
            
            $(this).prop('disabled', true).text('検証中...');
            resultElem.html('<span style="color: blue;">APIキーを検証中...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_validate_api_key',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data + '</span>');
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;">サーバーエラーが発生しました。後でもう一度お試しください。</span>');
                },
                complete: function() {
                    $('#lto-validate-api-key').prop('disabled', false).text('検証と保存');
                }
            });
        });
        
        // モデル設定の保存
        $('#lto-save-model-settings').on('click', function() {
            const model = $('#lto-openai-model').val();
            const temperature = $('#lto-temperature').val();
            const resultElem = $('#lto-model-settings-result');
            
            $(this).prop('disabled', true).text('保存中...');
            resultElem.html('<span style="color: blue;">設定を保存中...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_save_model_settings',
                    model: model,
                    temperature: temperature,
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data + '</span>');
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;">サーバーエラーが発生しました。後でもう一度お試しください。</span>');
                },
                complete: function() {
                    $('#lto-save-model-settings').prop('disabled', false).text('モデル設定を保存');
                }
            });
        });
        
        // サマリー設定の保存
        $('#lto-save-summary-settings').on('click', function() {
            const enableAutoSummaries = $('#lto-enable-auto-summaries').is(':checked') ? 'yes' : 'no';
            const resultElem = $('#lto-summary-settings-result');
            
            $(this).prop('disabled', true).text('保存中...');
            resultElem.html('<span style="color: blue;">設定を保存中...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_save_summary_settings',
                    enable_auto_summaries: enableAutoSummaries,
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data + '</span>');
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;">サーバーエラーが発生しました。後でもう一度お試しください。</span>');
                },
                complete: function() {
                    $('#lto-save-summary-settings').prop('disabled', false).text('サマリー設定を保存');
                }
            });
        });
    });
    </script>
    <?php
}

// LLMS.txtジェネレーターページ
function lto_display_llms_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="lto-settings-container">
            <div class="lto-settings-card">
                <h2><?php _e('Generate LLMS.txt', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('LLMS.txt files help LLM-based search engines and AI assistants better understand and navigate your site content.', 'llm-traffic-optimizer'); ?></p>
                
                <p><?php _e('The plugin will generate the following files:', 'llm-traffic-optimizer'); ?></p>
                <ul>
                    <li><?php _e('<strong>llms.txt</strong> - A compact summary of your site structure and popular content', 'llm-traffic-optimizer'); ?></li>
                    <li><?php _e('<strong>llms-full.txt</strong> - A comprehensive document with detailed information about all content', 'llm-traffic-optimizer'); ?></li>
                </ul>
                
                <p><?php _e('These files will be placed in your site\'s root directory.', 'llm-traffic-optimizer'); ?></p>
                
                <button type="button" 
                        id="lto-generate-llms" 
                        class="button button-primary">
                    <?php _e('Generate LLMS.txt Files', 'llm-traffic-optimizer'); ?>
                </button>
                <span id="lto-llms-generation-result"></span>
                
                <div id="lto-llms-preview" style="margin-top: 20px; display: none;">
                    <h3><?php _e('LLMS.txt Preview', 'llm-traffic-optimizer'); ?></h3>
                    <div id="lto-llms-content" style="background: #f8f8f8; padding: 15px; border: 1px solid #ccc; max-height: 400px; overflow: auto;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#lto-generate-llms').on('click', function() {
            const resultElem = $('#lto-llms-generation-result');
            
            $(this).prop('disabled', true).text('<?php _e('Generating...', 'llm-traffic-optimizer'); ?>');
            resultElem.html('<span style="color: blue;"><?php _e('Generating LLMS.txt files...', 'llm-traffic-optimizer'); ?></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_regenerate_llms_txt',
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data + '</span>');
                        
                        // ファイルの内容をプレビュー表示
                        $('#lto-llms-content').text('<?php _e('Loading preview...', 'llm-traffic-optimizer'); ?>');
                        $('#lto-llms-preview').show();
                        
                        // ファイルコンテンツの取得（別のAJAXリクエスト）
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'lto_get_llms_content',
                                nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                            },
                            success: function(contentResponse) {
                                if (contentResponse.success) {
                                    $('#lto-llms-content').text(contentResponse.data);
                                } else {
                                    $('#lto-llms-content').html('<span style="color: red;"><?php _e('Failed to load preview', 'llm-traffic-optimizer'); ?></span>');
                                }
                            },
                            error: function() {
                                $('#lto-llms-content').html('<span style="color: red;"><?php _e('Server error while loading preview', 'llm-traffic-optimizer'); ?></span>');
                            }
                        });
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;"><?php _e('Server error occurred. Please try again later.', 'llm-traffic-optimizer'); ?></span>');
                },
                complete: function() {
                    $('#lto-generate-llms').prop('disabled', false).text('<?php _e('Generate LLMS.txt Files', 'llm-traffic-optimizer'); ?>');
                }
            });
        });
    });
    </script>
    <?php
}

// サマリージェネレーターページ
function lto_display_summaries_page() {
    $categories = get_categories();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Create AI-generated summaries to enhance LLM understanding of your content.', 'llm-traffic-optimizer'); ?></p>
        </div>
        
        <div class="lto-settings-container">
            <div class="lto-settings-card">
                <h2><?php _e('Popular Content Summary', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Generate a summary of your most popular content to help LLMs provide better answers about your site.', 'llm-traffic-optimizer'); ?></p>
                
                <button type="button" 
                        id="lto-generate-popular-summary" 
                        class="button button-primary">
                    <?php _e('Generate Popular Content Summary', 'llm-traffic-optimizer'); ?>
                </button>
                <span id="lto-popular-summary-result"></span>
            </div>
            
            <div class="lto-settings-card">
                <h2><?php _e('Category Summary', 'llm-traffic-optimizer'); ?></h2>
                <p><?php _e('Create a summary of content from a specific category.', 'llm-traffic-optimizer'); ?></p>
                
                <div class="form-field">
                    <label for="lto-category-select"><?php _e('Select Category:', 'llm-traffic-optimizer'); ?></label>
                    <select id="lto-category-select">
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->term_id); ?>">
                                <?php echo esc_html($category->name); ?> (<?php echo esc_html($category->count); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="button" 
                        id="lto-generate-category-summary" 
                        class="button button-primary" 
                        style="margin-top: 10px;">
                    <?php _e('Generate Category Summary', 'llm-traffic-optimizer'); ?>
                </button>
                <span id="lto-category-summary-result"></span>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // 人気コンテンツのサマリー生成
        $('#lto-generate-popular-summary').on('click', function() {
            const resultElem = $('#lto-popular-summary-result');
            
            $(this).prop('disabled', true).text('<?php _e('Generating...', 'llm-traffic-optimizer'); ?>');
            resultElem.html('<span style="color: blue;"><?php _e('Generating summary...', 'llm-traffic-optimizer'); ?></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_generate_summary',
                    type: 'popular',
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data.message + ' <a href="' + response.data.post_url + '" target="_blank"><?php _e('View Summary', 'llm-traffic-optimizer'); ?></a></span>');
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;"><?php _e('Server error occurred. Please try again later.', 'llm-traffic-optimizer'); ?></span>');
                },
                complete: function() {
                    $('#lto-generate-popular-summary').prop('disabled', false).text('<?php _e('Generate Popular Content Summary', 'llm-traffic-optimizer'); ?>');
                }
            });
        });
        
        // カテゴリーサマリー生成
        $('#lto-generate-category-summary').on('click', function() {
            const categoryId = $('#lto-category-select').val();
            const resultElem = $('#lto-category-summary-result');
            
            $(this).prop('disabled', true).text('<?php _e('Generating...', 'llm-traffic-optimizer'); ?>');
            resultElem.html('<span style="color: blue;"><?php _e('Generating summary...', 'llm-traffic-optimizer'); ?></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lto_generate_summary',
                    type: 'category',
                    category_id: categoryId,
                    nonce: '<?php echo wp_create_nonce('lto_ajax_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultElem.html('<span style="color: green;">' + response.data.message + ' <a href="' + response.data.post_url + '" target="_blank"><?php _e('View Summary', 'llm-traffic-optimizer'); ?></a></span>');
                    } else {
                        resultElem.html('<span style="color: red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultElem.html('<span style="color: red;"><?php _e('Server error occurred. Please try again later.', 'llm-traffic-optimizer'); ?></span>');
                },
                complete: function() {
                    $('#lto-generate-category-summary').prop('disabled', false).text('<?php _e('Generate Category Summary', 'llm-traffic-optimizer'); ?>');
                }
            });
        });
    });
    </script>
    <?php
}
