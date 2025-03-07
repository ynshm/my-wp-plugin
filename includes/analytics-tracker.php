
<?php
// Track page views and AI referrals
add_action('wp_head', 'lto_track_visit');

function lto_track_visit() {
    // Only track if enabled
    if (get_option('lto_enable_analytics', 'yes') !== 'yes') {
        return;
    }
    
    // Only track on single posts/pages
    if (!is_singular()) {
        return;
    }
    
    global $post;
    $post_id = $post->ID;
    
    // Check if it's an AI referral
    $is_ai_referral = lto_is_ai_referral();
    
    // Record the visit
    lto_record_visit($post_id, $is_ai_referral);
    
    // Add tracking script for client-side detection
    ?>
    <script type="text/javascript">
    (function() {
        // Additional client-side AI detection
        function detectAI() {
            const aiSignals = [
                navigator.userAgent.includes('GPT'),
                navigator.userAgent.includes('ChatGPT'),
                navigator.userAgent.includes('Googlebot'),
                navigator.userAgent.includes('Bingbot'),
                document.referrer.includes('chat.openai.com'),
                document.referrer.includes('bard.google.com'),
                document.referrer.includes('bing.com/chat')
            ];
            
            return aiSignals.some(signal => signal === true);
        }
        
        if (detectAI()) {
            // Send AI detection to server
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=lto_record_ai_visit&post_id=<?php echo esc_js($post_id); ?>&nonce=<?php echo esc_js(wp_create_nonce('lto_record_visit')); ?>');
        }
    })();
    </script>
    <?php
}

// AJAX handler for client-side AI detection
add_action('wp_ajax_lto_record_ai_visit', 'lto_ajax_record_ai_visit');
add_action('wp_ajax_nopriv_lto_record_ai_visit', 'lto_ajax_record_ai_visit');

function lto_ajax_record_ai_visit() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lto_record_visit')) {
        wp_send_json_error('Invalid nonce');
        exit;
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if ($post_id > 0) {
        lto_record_visit($post_id, true);
    }
    
    wp_send_json_success();
    exit;
}

// Check if the current visit is from an AI source
function lto_is_ai_referral() {
    // Check user agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    $ai_user_agents = array(
        'GPT',
        'ChatGPT',
        'Googlebot',
        'Bingbot',
        'Anthropic',
        'Claude'
    );
    
    foreach ($ai_user_agents as $agent) {
        if (stripos($user_agent, $agent) !== false) {
            return true;
        }
    }
    
    // Check referrer
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    $ai_referrers = array(
        'chat.openai.com',
        'bard.google.com',
        'bing.com/chat',
        'claude.ai',
        'perplexity.ai'
    );
    
    foreach ($ai_referrers as $ref) {
        if (stripos($referrer, $ref) !== false) {
            return true;
        }
    }
    
    return false;
}

// Record the visit in the database
function lto_record_visit($post_id, $is_ai_referral = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false;
    }
    
    // Check if post already exists in the analytics table
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE post_id = %d",
        $post_id
    ));
    
    if ($exists) {
        // Update existing record
        if ($is_ai_referral) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET views = views + 1, ai_referrals = ai_referrals + 1, last_updated = %s WHERE post_id = %d",
                current_time('mysql'),
                $post_id
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET views = views + 1, last_updated = %s WHERE post_id = %d",
                current_time('mysql'),
                $post_id
            ));
        }
    } else {
        // Insert new record
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'views' => 1,
                'ai_referrals' => $is_ai_referral ? 1 : 0,
                'last_updated' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s')
        );
    }
    
    return true;
}
