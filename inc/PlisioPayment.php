<?php
/*
 * Plisio Payment Class
 *
 * version 	: 1.1
 */

class PlisioPayment
{
    public $sandbox = false;
    public $api_key;

    public $price_amount;
    public $price_currency;
    public $pay_currency;
    public $callback_url;

    public $result;
    public $info;
    public $params;
    public $headers;
    public $error;

    public function __construct()
    {
        $this->api_key = awards_options('plisio_api_key');
        $this->callback_url = esc_url(site_url("?plisio-callback=true"));

        if (awards_options('plisio_test_mode') == 'enable') {
            $this->sandbox = true;
        }
        
        // Initialize API URL based on sandbox mode
        $this->initApiUrl();
    }

    public function callback()
    {
        if (isset($_GET['plisio-callback'])) {
            // Validate and sanitize post IDs
            if (!isset($_GET['post-id']) || empty($_GET['post-id'])) {
                wp_redirect(site_url('dashboard'));
                exit;
            }

            $postIDs = sanitize_text_field($_GET['post-id']);
            // Make sure we only process numeric IDs
            $posts = array_filter(explode(',', $postIDs), function($id) {
                return is_numeric($id) && intval($id) > 0;
            });

            if (empty($posts)) {
                wp_redirect(site_url('dashboard'));
                exit;
            }

            // Verify callback data
            if (!isset($_POST['verify_hash'])) {
                wp_redirect(site_url('dashboard'));
                exit;
            }

            // Get raw POST data first, then sanitize individual fields with specific methods
            $raw_post = $_POST;
            
            // Extract and sanitize critical payment fields individually
            $verify_hash = isset($raw_post['verify_hash']) ? sanitize_text_field($raw_post['verify_hash']) : '';
            $status = isset($raw_post['status']) ? sanitize_text_field($raw_post['status']) : '';
            $amount = isset($raw_post['amount']) ? floatval($raw_post['amount']) : 0;
            $txn_id = isset($raw_post['txn_id']) ? sanitize_text_field($raw_post['txn_id']) : '';
            $merchant_id = isset($raw_post['merchant_id']) ? sanitize_text_field($raw_post['merchant_id']) : '';
            
            // Create a copy of raw post for verification, with specific sanitization for each field
            $post = array();
            foreach ($raw_post as $key => $value) {
                if ($key === 'verify_hash') {
                    continue; // Skip the hash itself
                } elseif ($key === 'amount') {
                    $post[$key] = $amount;
                } elseif ($key === 'txn_id' || $key === 'merchant_id' || $key === 'status') {
                    $post[$key] = sanitize_text_field($value);
                } else {
                    // For other fields, apply general sanitization
                    $post[$key] = sanitize_text_field($value);
                }
            }

            // Verify the hash
            ksort($post);
            $postString = serialize($post);
            $checkKey = hash_hmac('sha1', $postString, $this->api_key);

            $verifyStatus = false;
            // Use hash_equals for timing-safe comparison to prevent timing attacks
            if ($status && (strtolower($status) == 'completed') && hash_equals($checkKey, $verify_hash)) {
                $verifyStatus = true;
                
                foreach ($posts as $postID) {
                    $postID = intval($postID);
                    
                    // Verify post exists and belongs to the right post type
                    $post_type = get_post_type($postID);
                    if ($post_type !== 'entry') {
                        continue;
                    }
                    
                    // Current time with proper format
                    $current_time = current_time('mysql');

                    // Store minimal payment information, encrypt sensitive data if possible
                    $payment_data = [
                        'gateway' => 'plisio',
                        'Amount' => $amount,
                        'RefID' => $txn_id,
                        'Authority' => substr($merchant_id, 0, 10) . '...', // Store partial ID for security
                        'time' => $current_time,
                    ];
                    
                    // If you have encryption capability, use it for sensitive data
                    if (function_exists('awards_encrypt_sensitive_data')) {
                        $payment_data['RefID'] = awards_encrypt_sensitive_data($txn_id);
                        $payment_data['Authority'] = awards_encrypt_sensitive_data($merchant_id);
                    }

                    // Update post meta with sanitized values
                    update_post_meta($postID, 'status', 'pending_review');
                    update_post_meta($postID, 'payment', $payment_data);
                }
            }

            // Use secure logging instead of email when possible
            $debug_mode = awards_options('debug_mode') === 'enable';
            
            if ($debug_mode) {
                // Prepare minimal log data with no sensitive information
                $log_data = [
                    'event' => 'plisio_callback',
                    'verify_status' => $verifyStatus ? "success" : "fail",
                    'post_count' => count($posts),
                    'timestamp' => current_time('mysql'),
                ];
                
                // Prefer secure logging over email
                if (function_exists('awards_secure_log')) {
                    awards_secure_log('plisio_payment', $log_data);
                } else {
                    // Fallback to error_log if available
                    error_log('Plisio Payment: ' . json_encode($log_data));
                    
                    // Only use email as last resort
                    $debug_email = awards_options('debug_email');
                    if ($debug_email) {
                        wp_mail(
                            sanitize_email($debug_email), 
                            'Plisio callback ' . time(), 
                            'Payment processed: ' . ($verifyStatus ? 'Success' : 'Failed')
                        );
                    }
                }
            }

            // Add success/error message based on verification status
            if ($verifyStatus) {
                wp_redirect(site_url('dashboard/?dash-page=payment-result&status=OK&id=' . implode('-', $posts)));
            } else {
                wp_redirect(site_url('dashboard/?dash-page=payment-result&status=NOK&id=' . implode('-', $posts)));
            }
            exit;
        }
    }

    public function createInvoice($amount, $currency, $sourceCurrency = 'USD')
    {
        // Validate inputs
        $amount = floatval($amount);
        $currency = sanitize_text_field($currency);
        $sourceCurrency = sanitize_text_field($sourceCurrency);
        
        if ($amount <= 0) {
            $this->error = (object)['status' => 'error', 'message' => 'Invalid amount'];
            return false;
        }
        
        // Validate supported currencies
        $supported_currencies = ['BTC', 'ETH', 'LTC', 'USDT', 'USDC', 'USD', 'EUR', 'GBP'];
        if (!in_array($currency, $supported_currencies)) {
            $this->error = (object)['status' => 'error', 'message' => 'Unsupported currency'];
            return false;
        }
        
        // Create a unique order ID that's tied to the user for security
        // Use cryptographically secure random bytes instead of wp_rand()
        $user_id = get_current_user_id();
        $random_bytes = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : bin2hex(openssl_random_pseudo_bytes(8));
        $order_id = 'order_' . $user_id . '_' . time() . '_' . $random_bytes;
        
        $request = $this->request('invoices/new', [
            'source_amount' => $amount,
            'currency' => $currency,
            'source_currency' => $sourceCurrency,
            'order_number' => $order_id,
            'order_name' => "Minimalist Photography Awards: " . $order_id,
            'callback_url' => $this->callbackUrl(),
        ]);

        if (!isset($request->status) || $request->status != 'success') {
            $this->error = $request;
            return false;
        }

        return $request->data;
    }

    public function setCallbackUrl($callback)
    {
        // Validate callback URL to ensure it's on the same domain and uses HTTPS
        $site_url = parse_url(site_url(), PHP_URL_HOST);
        $callback_host = parse_url($callback, PHP_URL_HOST);
        $callback_scheme = parse_url($callback, PHP_URL_SCHEME);
        
        // Require HTTPS for security unless we're in a local development environment
        $require_https = !in_array($site_url, ['localhost', '127.0.0.1', '::1']);
        
        if ($callback_host !== $site_url) {
            return false;
        }
        
        // Enforce HTTPS in production environments
        if ($require_https && $callback_scheme !== 'https') {
            return false;
        }
        
        $this->callback_url = esc_url($callback);
        return true;
    }

    public function callbackUrl()
    {
        return $this->callback_url;
    }

    /**
     * API base URL that can be configured for different environments
     */
    private $api_base_url = 'https://plisio.net/api/v1/';
    
    /**
     * Initialize the API base URL based on sandbox mode
     */
    private function initApiUrl() {
        // Potentially set a different URL for sandbox/testing mode
        if ($this->sandbox) {
            $this->api_base_url = 'https://plisio.net/api/v1/sandbox/';
        }
    }
    
    /**
     * @param $method
     * @param $params
     * @return string
     */
    public function apiURL($method, $params)
    {
        // Make sure API URL is initialized
        if (!isset($this->api_base_url)) {
            $this->initApiUrl();
        }
        
        // Sanitize method name to prevent URL manipulation
        $method = preg_replace('/[^a-zA-Z0-9\/\-\_]/', '', $method);
        
        // Build URL with proper encoding
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $this->api_base_url . $method . '?' . $query;
    }

    /**
     * @param $action
     * @param $params
     * @param $headers
     * @return mixed
     */
    private function request($action, $params = [], $headers = [])
    {
        // Sanitize action
        $action = preg_replace('/[^a-zA-Z0-9\/\-\_]/', '', $action);
        
        // Merge with API key
        $params = array_merge([
            'api_key' => $this->api_key,
        ], $params);

        $headers = array_merge([
            'Content-Type: application/json',
            'User-Agent: WordPress/Awards-Plugin', // Add user agent for identification
        ], $headers);

        $url = $this->apiURL($action, $params);
        
        // Initialize cURL with additional security measures
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increase timeout slightly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verify host
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set a connection timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Limit redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Prevent exposing authentication credentials if redirected to another domain
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        
        // Set a maximum file size to prevent memory exhaustion attacks
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 128000); // 128 KB buffer size
        curl_setopt($ch, CURLOPT_VERBOSE, false); // Disable verbose output for security

        // Set what we're requesting
        $this->params = $params;
        $this->headers = $headers;
        
        // Execute the request with error handling
        $this->result = curl_exec($ch);
        $this->error = curl_error($ch);
        $this->info = curl_getinfo($ch);
        
        // Get HTTP code for later validation
        $http_code = $this->info['http_code'];
        
        curl_close($ch);

        // Handle server errors first (5xx)
        if ($http_code >= 500) {
            return (object)[
                'status' => 'error',
                'message' => 'Server Error: ' . $http_code,
                'data' => null
            ];
        }
        
        // Handle client errors (4xx)
        if ($http_code >= 400 && $http_code < 500) {
            return (object)[
                'status' => 'error',
                'message' => 'Request Error: ' . $http_code,
                'data' => null
            ];
        }

        // Handle cURL errors
        if ($this->error) {
            return (object)[
                'status' => 'error',
                'message' => 'Connection Error: ' . $this->error,
                'data' => null
            ];
        }
        
        // Handle empty responses
        if (empty($this->result)) {
            return (object)[
                'status' => 'error',
                'message' => 'Empty response from server',
                'data' => null
            ];
        }

        // Safely decode JSON with comprehensive error handling
        $response = json_decode($this->result);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Store the error type for debugging
            $json_error = json_last_error_msg();
            
            return (object)[
                'status' => 'error',
                'message' => 'JSON parsing error: ' . $json_error,
                'data' => null
            ];
        }
        
        // Final check to ensure the response has the expected structure
        if (!is_object($response) || !isset($response->status)) {
            return (object)[
                'status' => 'error',
                'message' => 'Invalid response format',
                'data' => null
            ];
        }

        return $response;
    }

    public function error()
    {
        return $this->error;
    }

    public function errorMessage()
    {
        if (!is_object($this->error) || !isset($this->error->data)) {
            return 'Unknown error';
        }
        
        if (!is_object($this->error->data) || !isset($this->error->data->message)) {
            return 'Unknown error message';
        }
        
        $message = (array) json_decode($this->error->data->message);
        
        // Safe error handling
        if (empty($message)) {
            return esc_html((string)$this->error->data->message);
        }
        
        $firstMessageKey = array_keys($message);
        
        if (isset($firstMessageKey[0])) {
            // Only return the first error message safely escaped
            return esc_html((string)$message[$firstMessageKey[0]]);
        }
        
        return esc_html((string)$this->error->data->message);
    }
}

// Initialize the payment class
$PlisioPayment = new PlisioPayment();
add_action('init', [$PlisioPayment, 'callback']);