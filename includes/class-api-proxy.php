<?php
/**
 * Secure API proxy for OpenAI integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle secure API proxy functionality
 */
class ExplainerPlugin_API_Proxy {
    
    /**
     * OpenAI API endpoint
     */
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * API timeout in seconds
     */
    private const API_TIMEOUT = 10;
    
    /**
     * Maximum tokens for explanation
     */
    private const MAX_TOKENS = 150;
    
    /**
     * Cache for decrypted API keys (per-request caching)
     */
    private $decrypted_key_cache = array();
    
    /**
     * Initialize the API proxy
     */
    public function __construct() {
        // Constructor will be called by main plugin
    }
    
    /**
     * Handle Ajax request for explanation with enhanced security
     */
    public function get_explanation() {
        $this->debug_log('=== EXPLANATION REQUEST STARTED ===', array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => explainer_get_client_ip(),
            'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown'))
        ));
        
        // Enhanced CSRF protection
        if (!$this->verify_request_security()) {
            $this->debug_log('SECURITY VALIDATION FAILED', array(
                'reason' => 'Enhanced CSRF protection failed',
                'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
                'referer' => sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'] ?? 'none'))
            ));
            wp_send_json_error(array('message' => __('Security validation failed', 'wp-ai-explainer')));
        }
        
        // Verify nonce for security
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_nonce' ) ) {
            $this->debug_log('NONCE VALIDATION FAILED', array(
                'nonce_provided' => isset($_POST['nonce']),
                'nonce_value' => isset($_POST['nonce']) ? substr(sanitize_text_field(wp_unslash($_POST['nonce'])), 0, 10) . '...' : 'none'
            ));
            wp_send_json_error(array('message' => __('Invalid nonce', 'wp-ai-explainer')));
        }
        
        $this->debug_log('Security validation passed', array('status' => 'success'));
        
        // Check if user has permission
        if (!$this->user_can_request_explanation()) {
            $this->debug_log('USER PERMISSION DENIED', array(
                'user_id' => get_current_user_id(),
                'is_logged_in' => is_user_logged_in(),
                'capabilities' => is_user_logged_in() ? array_keys(wp_get_current_user()->allcaps) : 'anonymous'
            ));
            wp_send_json_error(array('message' => __('Invalid request', 'wp-ai-explainer')));
        }
        
        // Get and validate input
        $this->debug_log('Starting input validation', array(
            'post_data_size' => count($_POST ?? array()),
            'text_provided' => isset($_POST['text'])
        ));
        
        $selected_text = $this->sanitize_and_validate_input($_POST ?? array());
        if (!$selected_text) {
            $this->debug_log('INPUT VALIDATION FAILED', array('reason' => 'sanitize_and_validate_input returned false'));
            return;
        }
        
        $this->debug_log('Input validation successful', array(
            'text_length' => strlen($selected_text),
            'word_count' => explainer_count_words($selected_text)
        ));
        
        // Enhanced rate limiting with DDoS protection
        if ($this->is_rate_limited()) {
            $user_identifier = explainer_get_user_identifier();
            $this->debug_log('RATE LIMIT EXCEEDED', array(
                'user_identifier' => $user_identifier,
                'is_logged_in' => is_user_logged_in(),
                'rate_limit_enabled' => get_option('explainer_rate_limit_enabled', true),
                'limit_logged' => get_option('explainer_rate_limit_logged', 20),
                'limit_anonymous' => get_option('explainer_rate_limit_anonymous', 10)
            ));
            $base_message = __('Rate limit exceeded. Please wait before making another request.', 'wp-ai-explainer');
            $polite_message = ExplainerPlugin_Localization::get_polite_error_message($base_message);
            wp_send_json_error(array('message' => $polite_message));
        }
        
        $this->debug_log('Rate limiting check passed', array('status' => 'success'));
        
        // Check if API key is configured
        $this->debug_log('Starting API key retrieval', array(
            'provider' => get_option('explainer_api_provider', 'openai')
        ));
        
        $api_key = $this->get_api_key();
        if (!$api_key) {
            $this->debug_log('API KEY CONFIGURATION FAILED', array(
                'reason' => 'No API key retrieved',  
                'provider' => get_option('explainer_api_provider', 'openai'),
                'key_option_exists' => !empty(get_option('explainer_api_key', '')) || !empty(get_option('explainer_claude_api_key', ''))
            ));
            wp_send_json_error(array('message' => __('API key not configured. Please check your settings.', 'wp-ai-explainer')));
        }
        
        $this->debug_log('API key retrieved successfully', array(
            'key_length' => strlen($api_key),
            'key_prefix' => substr($api_key, 0, 3) . '...'
        ));
        
        // TESTING: Simulate quota exceeded error (REMOVE AFTER TESTING)
        // if (isset($_POST['text']) && strpos($_POST['text'], 'test quota') !== false) {
        //     error_log('ExplainerPlugin: Simulating quota exceeded error for testing');
        //     $test_result = array(
        //         'success' => false,
        //         'error' => 'You exceeded your current quota. Please check your plan and billing details.',
        //         'disable_plugin' => true,
        //         'error_type' => 'quota_exceeded'
        //     );
        //     $this->handle_quota_exceeded_error($test_result);
        //     wp_send_json_error($test_result);
        // }
        
        // Check cache first
        $this->debug_log('Starting cache lookup', array(
            'cache_enabled' => get_option('explainer_cache_enabled', true),
            'text_hash' => hash('xxh64', $selected_text)
        ));
        
        $cached_explanation = $this->get_cached_explanation($selected_text);
        if ($cached_explanation) {
            $this->debug_log('CACHE HIT - Returning cached explanation', array(
                'explanation_length' => strlen($cached_explanation),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'response_source' => 'cache'
            ));
            wp_send_json_success(array(
                'explanation' => $cached_explanation,
                'cached' => true,
                'provider' => get_option('explainer_api_provider', 'openai')
            ));
        }
        
        $this->debug_log('CACHE MISS - Proceeding to API request', array(
            'cache_enabled' => get_option('explainer_cache_enabled', true),
            'reason' => 'No cached explanation found'
        ));
        
        // Prepare for API request
        $this->debug_log('Preparing API request', array(
            'text_length' => strlen($selected_text),
            'user_id' => get_current_user_id(),
            'provider' => get_option('explainer_api_provider', 'openai'),
            'model' => get_option('explainer_api_model', 'gpt-3.5-turbo')
        ));
        
        // Make API request
        $start_time = microtime(true);
        $result = $this->make_api_request($selected_text, $api_key);
        $response_time = microtime(true) - $start_time;
        
        if ($result['success']) {
            // Cache the successful response
            $this->debug_log('Caching successful explanation', array(
                'cache_enabled' => get_option('explainer_cache_enabled', true),
                'cache_duration_hours' => get_option('explainer_cache_duration', 24)
            ));
            
            $cache_result = $this->cache_explanation($selected_text, $result['explanation']);
            if (!$cache_result) {
                $this->debug_log('CACHE STORAGE FAILED', array(
                    'reason' => 'cache_explanation returned false',
                    'cache_enabled' => get_option('explainer_cache_enabled', true)
                ));
            } else {
                $this->debug_log('Explanation cached successfully', array('status' => 'success'));
            }
            
            // Success logging
            $this->debug_log('=== API REQUEST SUCCESSFUL ===', array(
                'explanation_length' => strlen($result['explanation']),
                'tokens_used' => $result['tokens_used'] ?? 'unknown',
                'cost' => $result['cost'] ?? 'unknown',
                'response_time_seconds' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'cached_for_future' => $cache_result
            ));
            
            $this->debug_log('=== EXPLANATION REQUEST COMPLETED SUCCESSFULLY ===', array(
                'total_response_time_seconds' => round($response_time, 3),
                'final_status' => 'success'
            ));
            
            wp_send_json_success(array(
                'explanation' => $result['explanation'],
                'cached' => false,
                'tokens_used' => $result['tokens_used'],
                'cost' => $result['cost'],
                'response_time' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai')
            ));
        } else {
            // Check if this is a quota exceeded error that should disable the plugin
            if (isset($result['disable_plugin']) && $result['disable_plugin'] === true) {
                $this->debug_log('QUOTA EXCEEDED - Auto-disabling plugin', array(
                    'error_type' => $result['error_type'] ?? 'quota_exceeded',
                    'provider' => get_option('explainer_api_provider', 'openai'),
                    'error_message' => $result['error']
                ));
                $this->handle_quota_exceeded_error($result);
            }
            
            // Failure logging
            $this->debug_log('=== API REQUEST FAILED ===', array(
                'error_message' => $result['error'],
                'error_type' => $result['error_type'] ?? 'unknown',
                'disable_plugin_triggered' => $result['disable_plugin'] ?? false,
                'response_time_seconds' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'user_impact' => 'Request failed, no explanation provided'
            ));
            
            $this->debug_log('=== EXPLANATION REQUEST COMPLETED WITH ERROR ===', array(
                'total_response_time_seconds' => round($response_time, 3),
                'final_status' => 'error',
                'error_message' => $result['error']
            ));
            
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Check if user can request explanations
     */
    private function user_can_request_explanation() {
        // Allow all users for now, but could be restricted based on settings
        return true;
    }
    
    /**
     * Sanitize and validate input
     */
    private function sanitize_and_validate_input($post_data) {
        // Get selected text
        $selected_text = isset($post_data['text']) ? sanitize_textarea_field( wp_unslash( $post_data['text'] ) ) : '';
        
        if (empty($selected_text)) {
            wp_send_json_error(array('message' => __('Text selection is required', 'wp-ai-explainer')));
            return false;
        }
        
        // Get admin settings for validation
        $min_length = get_option('explainer_min_selection_length', 3);
        $max_length = get_option('explainer_max_selection_length', 200);
        $min_words = get_option('explainer_min_words', 1);
        $max_words = get_option('explainer_max_words', 30);
        
        // Basic sanitization first
        $selected_text = wp_strip_all_tags($selected_text);
        $selected_text = html_entity_decode($selected_text, ENT_QUOTES, 'UTF-8');
        $selected_text = trim($selected_text);
        $selected_text = preg_replace('/\s+/', ' ', $selected_text);
        
        // Check minimum length first
        if (strlen($selected_text) < $min_length) {
            // translators: %d is the minimum number of characters required for text selection
            wp_send_json_error(array('message' => sprintf(__('Text selection is too short (minimum %d characters)', 'wp-ai-explainer'), $min_length)));
            return false;
        }
        
        // Check maximum length 
        if (strlen($selected_text) > $max_length) {
            // translators: %d is the maximum number of characters allowed for text selection
            wp_send_json_error(array('message' => sprintf(__('Text selection is too long (maximum %d characters)', 'wp-ai-explainer'), $max_length)));
            return false;
        }
        
        // Check word count
        $word_count = explainer_count_words($selected_text);
        if ($word_count < $min_words) {
            // translators: %d is the minimum number of words required for text selection
            wp_send_json_error(array('message' => sprintf(__('Text selection has too few words (minimum %d words)', 'wp-ai-explainer'), $min_words)));
            return false;
        }
        
        if ($word_count > $max_words) {
            // translators: %d is the maximum number of words allowed for text selection
            wp_send_json_error(array('message' => sprintf(__('Text selection has too many words (maximum %d words)', 'wp-ai-explainer'), $max_words)));
            return false;
        }
        
        // Use helper function for final security validation
        $validated_text = explainer_sanitize_text_selection($selected_text);
        if (!$validated_text) {
            // Check if this was due to a blocked word
            $blocked_word = apply_filters('explainer_blocked_word_found', false);
            if ($blocked_word !== false) {
                wp_send_json_error(array('message' => __('Your selection contains blocked content', 'wp-ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Text selection contains invalid content', 'wp-ai-explainer')));
            }
            return false;
        }
        
        return $validated_text;
    }
    
    /**
     * Check if user is rate limited with enhanced DDoS protection
     */
    private function is_rate_limited() {
        $user_identifier = explainer_get_user_identifier();
        return explainer_check_advanced_rate_limit($user_identifier);
    }
    
    /**
     * Get API key from options based on current provider
     */
    private function get_api_key() {
        $provider_key = get_option('explainer_api_provider', 'openai');
        
        $this->debug_log('Retrieving API key via factory', array(
            'provider' => $provider_key,
            'factory_method' => 'ExplainerPlugin_Provider_Factory::get_current_decrypted_api_key'
        ));
        
        // Use the factory method to get decrypted key directly
        $decrypted_key = ExplainerPlugin_Provider_Factory::get_current_decrypted_api_key();
        
        if (empty($decrypted_key)) {
            $this->debug_log('API KEY RETRIEVAL FAILED', array(
                'provider' => $provider_key,
                'reason' => 'Factory returned empty key',
                'encrypted_key_exists' => !empty(get_option('explainer_api_key', '')) || !empty(get_option('explainer_claude_api_key', ''))
            ));
            return false;
        }
        
        $this->debug_log('API key retrieved and decrypted successfully', array(
            'provider' => $provider_key,
            'key_length' => strlen($decrypted_key),
            'key_format_valid' => $this->is_valid_api_key_format($decrypted_key)
        ));
        
        return $decrypted_key;
    }
    
    /**
     * Get decrypted API key for admin display
     */
    public function get_decrypted_api_key() {
        return $this->get_api_key();
    }
    
    /**
     * Get decrypted API key for specific provider
     * 
     * @param string $provider Provider key (openai, claude)
     * @param bool $for_api_call Whether this is for an actual API call (affects debug logging)
     * @return string Decrypted API key or empty string
     */
    public function get_decrypted_api_key_for_provider($provider, $for_api_call = false) {
        $encrypted_key = '';
        
        switch ($provider) {
            case 'openai':
                $encrypted_key = get_option('explainer_api_key', '');
                break;
            case 'claude':
                $encrypted_key = get_option('explainer_claude_api_key', '');
                break;
            default:
                return '';
        }
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        // Decrypt the key
        return $this->decrypt_api_key($encrypted_key, $for_api_call);
    }
    
    /**
     * Encrypt API key for storage
     */
    public function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            $this->debug_log('Encrypt API Key: Empty key provided');
            return '';
        }
        
        // Check if the key is already encrypted to prevent double encryption
        if ($this->is_encrypted_key($api_key)) {
            $this->debug_log('Encrypt API Key: Key already encrypted, returning as-is');
            return $api_key; // Already encrypted, return as-is
        }
        
        // Validate that this looks like a real API key before encrypting
        if (!$this->is_valid_api_key_format($api_key)) {
            $this->debug_log('Encrypt API Key: Invalid key format', array('key_prefix' => substr($api_key, 0, 3) . '...'));
            // Return empty string for invalid keys
            return '';
        }
        
        // Use WordPress salts for encryption
        $salt = wp_salt('nonce');
        $encrypted = base64_encode($api_key . '|' . wp_hash($api_key . $salt));
        $this->debug_log('Encrypt API Key: Successfully encrypted', array('key_prefix' => substr($api_key, 0, 3) . '...'));
        
        return $encrypted;
    }
    
    /**
     * Check if a key is already encrypted
     * 
     * @param string $key Key to check
     * @return bool True if key appears to be encrypted
     */
    private function is_encrypted_key($key) {
        // Encrypted keys are base64 encoded and contain a pipe character when decoded
        $decoded = base64_decode($key, true);
        
        // Check if it's valid base64 and contains the pipe separator
        if ($decoded === false) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        return count($parts) === 2;
    }
    
    /**
     * Validate API key format for any provider
     * 
     * @param string $api_key Key to validate
     * @return bool True if valid format
     */
    private function is_valid_api_key_format($api_key) {
        if (!$api_key || !is_string($api_key)) {
            return false;
        }
        
        $api_key = trim($api_key);
        
        // Check for OpenAI format (sk-...)
        if (str_starts_with($api_key, 'sk-')) {
            return preg_match('/^sk-[a-zA-Z0-9_-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        // Check for Claude format (sk-ant-...)
        if (str_starts_with($api_key, 'sk-ant-')) {
            return preg_match('/^sk-ant-[a-zA-Z0-9_-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        return false;
    }
    
    /**
     * Decrypt API key from storage
     */
    private function decrypt_api_key($encrypted_key, $for_api_call = false) {
        if (empty($encrypted_key)) {
            $this->debug_log('Decrypt API Key: Empty key provided');
            return '';
        }
        
        // Check cache first to avoid repeated decryption
        $cache_key = md5($encrypted_key);
        if (isset($this->decrypted_key_cache[$cache_key])) {
            return $this->decrypted_key_cache[$cache_key];
        }
        
        // Check if the key is already in plain text (not encrypted)
        if ($this->is_valid_api_key_format($encrypted_key)) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Key already in plain text', array('key_prefix' => substr($encrypted_key, 0, 3) . '...'));
            }
            $this->decrypted_key_cache[$cache_key] = $encrypted_key;
            return $encrypted_key; // Already decrypted, return as-is
        }
        
        // Attempt to decrypt
        $decoded = base64_decode($encrypted_key, true);
        if ($decoded === false) {
            $this->debug_log('Decrypt API Key: Invalid base64 format');
            // Not valid base64, might be an unencrypted key that failed format validation
            return '';
        }
        
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 2) {
            $this->debug_log('Decrypt API Key: Not in encrypted format, checking if plain text');
            // Not in our encrypted format, check if it's a valid plain text key
            if ($this->is_valid_api_key_format($encrypted_key)) {
                $this->decrypted_key_cache[$cache_key] = $encrypted_key;
                return $encrypted_key;
            }
            $this->decrypted_key_cache[$cache_key] = '';
            return '';
        }
        
        $api_key = $parts[0];
        $hash = $parts[1];
        
        // Verify hash
        $salt = wp_salt('nonce');
        if (wp_hash($api_key . $salt) === $hash) {
            // Validate decrypted key format
            if ($this->is_valid_api_key_format($api_key)) {
                if ($for_api_call) {
                    $this->debug_log('Decrypt API Key: Successfully decrypted', array('key_prefix' => substr($api_key, 0, 3) . '...'));
                }
                $this->decrypted_key_cache[$cache_key] = $api_key;
                return $api_key;
            } else {
                if ($for_api_call) {
                    $this->debug_log('Decrypt API Key: Decrypted key has invalid format');
                }
            }
        } else {
            $this->debug_log('Decrypt API Key: Hash verification failed');
        }
        
        // Decryption failed or invalid key format
        $this->decrypted_key_cache[$cache_key] = '';
        return '';
    }
    
    /**
     * Get cached explanation with optimized query
     */
    private function get_cached_explanation($text) {
        if (!get_option('explainer_cache_enabled', true)) {
            return false;
        }
        
        // Use optimized cache key with consistent hashing
        $cache_key = 'explainer_' . hash('xxh64', $text);
        
        // Use object cache if available for better performance
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($cache_key, 'explainer_plugin');
        }
        
        return get_transient($cache_key);
    }
    
    /**
     * Cache explanation with optimized storage
     */
    private function cache_explanation($text, $explanation) {
        if (!get_option('explainer_cache_enabled', true)) {
            return false;
        }
        
        // Use optimized cache key with consistent hashing
        $cache_key = 'explainer_' . hash('xxh64', $text);
        $cache_duration = (int) get_option('explainer_cache_duration', 24) * HOUR_IN_SECONDS;
        
        // Use object cache if available for better performance
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($cache_key, $explanation, 'explainer_plugin', $cache_duration);
        }
        
        return set_transient($cache_key, $explanation, $cache_duration);
    }
    
    /**
     * Make API request using provider pattern
     */
    private function make_api_request($selected_text, $api_key) {
        // Get current provider
        $this->debug_log('Initializing AI provider', array(
            'provider_key' => get_option('explainer_api_provider', 'openai')
        ));
        
        $provider = ExplainerPlugin_Provider_Factory::get_current_provider();
        
        if (!$provider) {
            $this->debug_log('PROVIDER INITIALIZATION FAILED', array(
                'provider_key' => get_option('explainer_api_provider', 'openai'),
                'error' => 'Factory returned null provider',
                'available_providers' => array_keys(ExplainerPlugin_Provider_Factory::get_available_providers())
            ));
            return array(
                'success' => false,
                'error' => __('No AI provider configured.', 'wp-ai-explainer')
            );
        }
        
        $this->debug_log('Provider initialized successfully', array(
            'provider_name' => $provider->get_name(),
            'provider_class' => get_class($provider)
        ));
        
        // Get context if available (nonce already verified above)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified at start of get_explanation() method
        $context = isset($_POST['context']) ? sanitize_textarea_field( wp_unslash( $_POST['context'] ) ) : null;
        
        // Prepare the prompt
        $this->debug_log('Preparing prompt for API request', array(
            'custom_prompt_enabled' => !empty(get_option('explainer_custom_prompt', '')),
            'language' => get_option('explainer_language', 'en_GB'),
            'context_provided' => !empty($context)
        ));
        
        $prompt = $this->prepare_prompt($selected_text, $context);
        
        // Debug logging - log the prompt being sent (without API key)
        $this->debug_log('Prompt prepared for API', array(
            'provider' => $provider->get_name(),
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 100) . '...',
            'selected_text_length' => strlen($selected_text),
            'context_provided' => !empty($context)
        ));
        
        // Get AI model setting
        $model = get_option('explainer_api_model', 'gpt-3.5-turbo');
        
        $this->debug_log('Making API request to provider', array(
            'provider' => $provider->get_name(),
            'model' => $model,
            'api_key_configured' => !empty($api_key),
            'api_key_length' => strlen($api_key)
        ));
        
        // Make request using provider
        $api_start_time = microtime(true);
        $response = $provider->make_request($api_key, $prompt, $model);
        $api_response_time = microtime(true) - $api_start_time;
        
        $this->debug_log('Provider API call completed', array(
            'response_time_seconds' => round($api_response_time, 3),
            'response_received' => !empty($response),
            'response_size_bytes' => !empty($response) ? strlen(json_encode($response)) : 0
        ));
        
        // Parse response using provider
        $this->debug_log('Parsing API response', array(
            'parser' => get_class($provider) . '::parse_response',
            'model' => $model
        ));
        
        $parsed_result = $provider->parse_response($response, $model);
        
        $this->debug_log('API response parsed', array(
            'success' => $parsed_result['success'] ?? false,
            'has_explanation' => !empty($parsed_result['explanation']) ?? false,
            'explanation_length' => !empty($parsed_result['explanation']) ? strlen($parsed_result['explanation']) : 0,
            'has_error' => !empty($parsed_result['error']) ?? false,
            'error_type' => $parsed_result['error_type'] ?? 'unknown'
        ));
        
        return $parsed_result;
    }
    
    /**
     * Prepare prompt for API request
     */
    private function prepare_prompt($selected_text, $context = null) {
        // Get custom prompt template
        $custom_prompt = get_option('explainer_custom_prompt', 'Please provide a clear, concise explanation of the following text in 1-2 sentences: {{snippet}}');
        
        // Validate that template contains {{snippet}} placeholder
        if (!str_contains($custom_prompt, '{{snippet}}')) {
            // Fallback to default if invalid template
            $custom_prompt = 'Please provide a clear, concise explanation of the following text in 1-2 sentences: {{snippet}}';
        }
        
        // Add language instruction based on selected language
        $selected_language = get_option('explainer_language', 'en_GB');
        $language_instruction = $this->get_language_instruction($selected_language);
        
        if (!empty($language_instruction)) {
            $custom_prompt = $language_instruction . ' ' . $custom_prompt;
        }
        
        // Replace {{snippet}} with the selected text
        $prompt = str_replace('{{snippet}}', $selected_text, $custom_prompt);
        
        // Add context if available
        if ($context && is_array($context)) {
            $context_text = '';
            if (!empty($context['before'])) {
                $context_text .= "..." . $context['before'];
            }
            $context_text .= "[" . $selected_text . "]";
            if (!empty($context['after'])) {
                $context_text .= $context['after'] . "...";
            }
            
            $prompt .= "\n\nContext: " . $context_text;
        }
        
        return $prompt;
    }
    
    /**
     * Get language instruction for AI prompt based on selected language
     */
    private function get_language_instruction($language_code) {
        $language_instructions = array(
            'en_US' => 'Please respond in American English.',
            'en_GB' => 'Please respond in British English.',
            'es_ES' => 'Por favor responde en español.',
            'de_DE' => 'Bitte antworten Sie auf Deutsch.',
            'fr_FR' => 'Veuillez répondre en français.',
            'hi_IN' => 'कृपया हिंदी में उत्तर दें।',
            'zh_CN' => '请用中文回答。'
        );
        
        return isset($language_instructions[$language_code]) ? $language_instructions[$language_code] : '';
    }
    
    /**
     * Handle API response (now handled by providers)
     * 
     * @deprecated This method is now handled by individual providers
     */
    private function handle_api_response($response, $model) {
        // This method is deprecated and replaced by provider-specific parsing
        // Kept for backward compatibility
        $provider = ExplainerPlugin_Provider_Factory::get_current_provider();
        
        if (!$provider) {
            return array(
                'success' => false,
                'error' => __('No AI provider configured.', 'wp-ai-explainer')
            );
        }
        
        return $provider->parse_response($response, $model);
    }
    
    /**
     * Debug logging method
     */
    private function debug_log($message, $data = array()) {
        if (!get_option('explainer_debug_mode', false)) {
            return false;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => 'DEBUG',
            'message' => $message,
            'data' => $data
        );
        
        // Get existing logs
        $logs = get_option('explainer_debug_logs', array());
        
        // Add new log entry
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries to prevent memory issues
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        // Save logs
        update_option('explainer_debug_logs', $logs);
        
        // Also log to PHP error log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        return true;
    }
    
    /**
     * Test API key validity using provider pattern
     */
    public function test_api_key($api_key) {
        return ExplainerPlugin_Provider_Factory::test_current_api_key($api_key);
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_user_ip() {
        return explainer_get_client_ip();
    }
    
    /**
     * Verify request security with comprehensive checks
     *
     * @return bool True if request is secure
     */
    private function verify_request_security() {
        // Check if request is POST
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) !== 'POST' ) {
            return false;
        }
        
        // Check referer
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_nonce' ) ) {
            return false;
        }
        
        // Check user agent (basic bot detection)
        $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        if (empty($user_agent) || strlen($user_agent) < 10) {
            return false;
        }
        
        // Check for common bot patterns
        $bot_patterns = array(
            '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
            '/curl/i', '/wget/i', '/python/i', '/java/i'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return false;
            }
        }
        
        // Check request timing (prevent replay attacks)
        $request_time = isset( $_SERVER['REQUEST_TIME'] ) ? (int) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME'] ) ) : time();
        if (abs(time() - $request_time) > 300) { // 5 minute window
            return false;
        }
        
        // Check for suspicious headers
        $suspicious_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        );
        
        $proxy_count = 0;
        foreach ($suspicious_headers as $header) {
            if (isset($_SERVER[$header])) {
                $proxy_count++;
            }
        }
        
        // Too many proxy headers could indicate spoofing
        if ($proxy_count > 2) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Handle quota exceeded error and auto-disable plugin
     * 
     * @param array $result API result containing quota exceeded error
     */
    private function handle_quota_exceeded_error($result) {
        // Get current provider name for logging
        $provider = get_option('explainer_api_provider', 'openai');
        $provider_name = $provider === 'openai' ? 'OpenAI' : 'Claude';
        
        // Get the error message
        $error_message = $result['error'] ?? __('API usage limit exceeded.', 'wp-ai-explainer');
        
        // Auto-disable the plugin using helper function
        $disabled = explainer_auto_disable_plugin($error_message, $provider_name);
        
        if ($disabled) {
            // Additional logging for this critical event
            $this->debug_log('Plugin auto-disabled due to quota exceeded', array(
                'provider' => $provider_name,
                'error_message' => $error_message,
                'error_type' => $result['error_type'] ?? 'quota_exceeded',
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ));
            
            // Log to PHP error log as well for server-level tracking
            // Debug logging disabled for production
        }
    }
    
    /**
     * Clear explanation cache
     */
    public function clear_cache() {
        return explainer_clear_cache();
    }
}