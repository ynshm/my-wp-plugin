
<?php
// Function to make API requests to OpenAI
function lto_openai_api_request($prompt) {
    // Get API key
    $api_key = get_option('lto_openai_api_key');
    
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('OpenAI API key is required.', 'llm-traffic-optimizer'));
    }
    
    // Prepare the request
    $request_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a skilled content writer and SEO specialist creating high-quality, informative summaries and guides for a WordPress website.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2500,
            'temperature' => 0.7
        )),
        'timeout' => 60
    );
    
    // Make the request
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);
    
    // Check for errors
    if (is_wp_error($response)) {
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code !== 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error occurred.', 'llm-traffic-optimizer');
        
        return new WP_Error('api_error', sprintf(
            __('OpenAI API Error (Code %d): %s', 'llm-traffic-optimizer'),
            $response_code,
            $error_message
        ));
    }
    
    // Process the response
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($body['choices'][0]['message']['content'])) {
        return new WP_Error('empty_response', __('OpenAI returned an empty response.', 'llm-traffic-optimizer'));
    }
    
    return $body['choices'][0]['message']['content'];
}
