<?php
if (!defined('ABSPATH')) {
    exit; // 直接アクセス禁止
}

// AIリファラルの識別パターン
$ai_referrers = array(
    'openai.com',
    'bing.com/chat',
    'perplexity.ai',
    'anthropic.com',
    'claude.ai',
    'bard.google.com',
    'chat.openai.com',
    'gemini.google.com'
);

// トラフィックトラッキングを追加
add_action('wp', 'lto_track_page_view');

function lto_track_page_view() {
    try {
        // シングル投稿/固定ページでのみトラッキング
        if (!is_singular()) {
            return;
        }

        global $post, $wpdb, $ai_referrers;
        $table_name = $wpdb->prefix . 'lto_analytics';

        // 現在のビュー情報を取得
        $post_id = $post->ID;
        $is_ai_referral = lto_is_ai_referral();

        // データベースに既存のレコードがあるか確認
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        if ($existing_record) {
            // 既存レコードの更新
            $wpdb->update(
                $table_name,
                array(
                    'views' => $existing_record->views + 1,
                    'ai_referrals' => $is_ai_referral ? $existing_record->ai_referrals + 1 : $existing_record->ai_referrals,
                    'last_updated' => current_time('mysql')
                ),
                array('id' => $existing_record->id),
                array('%d', '%d', '%s'),
                array('%d')
            );
        } else {
            // 新規レコードの作成
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
    } catch (Exception $e) {
        error_log('LTO tracking error: ' . $e->getMessage());
    }
}

// AIリファラルかどうかをチェック
function lto_is_ai_referral() {
    global $ai_referrers;

    if (empty($_SERVER['HTTP_REFERER'])) {
        return false;
    }

    $referer = strtolower($_SERVER['HTTP_REFERER']);

    foreach ($ai_referrers as $ai_referer) {
        if (strpos($referer, $ai_referer) !== false) {
            return true;
        }
    }

    // ユーザーエージェントもチェック
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

    $ai_user_agents = array(
        'openai',
        'chatgpt',
        'googlebot',
        'bingbot',
        'claude'
    );

    foreach ($ai_user_agents as $ai_agent) {
        if (strpos($user_agent, $ai_agent) !== false) {
            return true;
        }
    }

    return false;
}