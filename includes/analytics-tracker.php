
<?php
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// アクセスを記録するアクション
add_action('wp_footer', 'lto_track_page_view');

function lto_track_page_view() {
    // 投稿ページのみトラッキング
    if (!is_singular('post')) {
        return;
    }
    
    global $post, $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    // ユーザーエージェントからAIアクセスを判定
    $is_ai_referral = lto_is_ai_referral();
    
    // テーブルにレコードがあるか確認
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE post_id = %d",
        $post->ID
    ));
    
    if ($record) {
        // レコードを更新
        $wpdb->update(
            $table_name,
            array(
                'views' => $record->views + 1,
                'ai_referrals' => $is_ai_referral ? $record->ai_referrals + 1 : $record->ai_referrals,
                'last_updated' => current_time('mysql')
            ),
            array('post_id' => $post->ID)
        );
    } else {
        // 新しいレコードを挿入
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post->ID,
                'views' => 1,
                'ai_referrals' => $is_ai_referral ? 1 : 0,
                'last_updated' => current_time('mysql')
            )
        );
    }
}

// AIリファラルかどうかの判定
function lto_is_ai_referral() {
    // リファラの確認
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return false;
    }
    
    $referer = sanitize_text_field($_SERVER['HTTP_REFERER']);
    
    // 一般的なAIエージェントのリファラパターン
    $ai_patterns = array(
        'bing.com/search',
        'bingai',
        'chat.openai.com',
        'claude.ai',
        'anthropic.com',
        'perplexity.ai',
        'bard.google.com',
        'chatgpt'
    );
    
    foreach ($ai_patterns as $pattern) {
        if (stripos($referer, $pattern) !== false) {
            return true;
        }
    }
    
    // ユーザーエージェントの確認
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }
    
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
    
    // 一般的なAIクローラーのユーザーエージェントパターン
    $ai_agents = array(
        'ChatGPT-User',
        'ClaudeBot',
        'BingBot',
        'GoogleBard',
        'AnthropicBot'
    );
    
    foreach ($ai_agents as $agent) {
        if (stripos($user_agent, $agent) !== false) {
            return true;
        }
    }
    
    return false;
}

// AIトラフィックの統計情報を取得
function lto_get_traffic_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lto_analytics';
    
    $total_views = $wpdb->get_var("SELECT SUM(views) FROM $table_name");
    $total_ai_referrals = $wpdb->get_var("SELECT SUM(ai_referrals) FROM $table_name");
    
    $percent = $total_views > 0 ? round(($total_ai_referrals / $total_views) * 100, 2) : 0;
    
    return array(
        'total_views' => $total_views ?: 0,
        'total_ai_referrals' => $total_ai_referrals ?: 0,
        'percent' => $percent
    );
}
