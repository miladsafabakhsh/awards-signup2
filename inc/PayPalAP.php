<?php

/*********************************************************
 * PayPal Adaptive Payments PHP Class
 * Author: Simon W. Henriksen
 *
 * Based on PayPal's own PHP examples
 * This is a work in progress.
 * Some functionality still needs to be abstracted
 **********************************************************/


class PayPalAP
{
    private static $__apiUsername;
    private static $__apiPassword;
    private static $__apiSignature;
    private static $__apiAppid;
    private static $__env;
    private static $__apiEndpoint;

    private static $__useProxy;
    private static $__proxyHost;
    private static $__proxyPort;

/**
     * Start a secure session for PayPal operations
     * This should be called before any PayPal API operations
     */
    public static function secureSessionStart() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            $session_name = 'PAYPAL_SECURE_SESSION';
            $secure = is_ssl(); // True if HTTPS
            $httponly = true;
            
            // Set the session name
            session_name($session_name);
            
            // Set the cookie parameters
            session_set_cookie_params([
                'lifetime' => 3600,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax'
            ]);
            
            // Start the session
            session_start();
            
            // Regenerate session ID periodically to prevent fixation
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                // Session is older than 30 minutes, regenerate ID
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    /*********************************************
     * Public usage functions
     **********************************************/

    /**
     * Sets authentication information needed for Adaptive Payments functionality
     * @param string $apiUsername Your PayPal api username
     * @param string $apiPassword Your PayPal api password
     * @param string $apiSignature Your PayPal api signature
     * @param string $apiAppid PayPal app id. Only for live environtments.
     * @param string $env Set to 'sandbox' or leave default for testmode
     * @param string $useProxy Set to true if you want to use a proxy (CURL)
     * @param string $proxyHost The proxy host
     * @param string $proxyPort The proxy port
     * @return Nothing
     */

    // Instead of storing directly in static variables
public static function setAuth($apiUsername, $apiPassword, $apiSignature, $apiAppid = 'APP-80W284485P519543T', $env = 'sandbox', $useProxy = false, $proxyHost = '', $proxyPort = '')
{
    $success = true;
    $errors = [];
    
    // Validate required parameters
    if (empty($apiUsername) || empty($apiPassword) || empty($apiSignature)) {
        $errors[] = 'Missing required PayPal API credentials';
        $success = false;
    }
    
    // Use WordPress options API with encryption for sensitive data if available
    if (function_exists('update_option') && $success) {
        try {
            // Encryption setup
            if (!function_exists('openssl_encrypt')) {
                $errors[] = 'OpenSSL extension not available for secure credential storage';
                $success = false;
            } else {
                // Define encryption parameters
                $cipher = "AES-256-CBC";
                $ivlen = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($ivlen);
                $key = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-please-change';
                
                // Encrypt and store credentials
                $encrypted_username = openssl_encrypt($apiUsername, $cipher, $key, 0, $iv);
                $encrypted_password = openssl_encrypt($apiPassword, $cipher, $key, 0, $iv);
                $encrypted_signature = openssl_encrypt($apiSignature, $cipher, $key, 0, $iv);
                
                // Store IV with the encrypted data
                $store_username = base64_encode($iv . openssl_encrypt($apiUsername, $cipher, $key, 0, $iv));
                $store_password = base64_encode($iv . openssl_encrypt($apiPassword, $cipher, $key, 0, $iv));
                $store_signature = base64_encode($iv . openssl_encrypt($apiSignature, $cipher, $key, 0, $iv));
                
                // Store credentials
                $set_username = set_transient('paypal_ap_creds_username', $store_username, DAY_IN_SECONDS);
                $set_password = set_transient('paypal_ap_creds_password', $store_password, DAY_IN_SECONDS);
                $set_signature = set_transient('paypal_ap_creds_signature', $store_signature, DAY_IN_SECONDS);
                
                if (!$set_username || !$set_password || !$set_signature) {
                    $errors[] = 'Failed to securely store PayPal credentials';
                    $success = false;
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Error storing credentials: ' . $e->getMessage();
            $success = false;
        }
    }
    
    // Store non-sensitive configuration
    self::$__apiAppid = trim($apiAppid);
    self::$__env = ($env === 'sandbox') ? 'sandbox' : 'paypal';
    self::$__useProxy = (bool)$useProxy;
    self::$__proxyHost = trim($proxyHost);
    self::$__proxyPort = (int)$proxyPort;

    // Set the API endpoint
    if (self::$__env == 'sandbox') {
        self::$__apiEndpoint = 'https://svcs.sandbox.paypal.com/AdaptivePayments';
    } else {
        self::$__apiEndpoint = 'https://svcs.paypal.com/AdaptivePayments';
    }
    
    // Log any errors
    if (!$success && !empty($errors)) {
        error_log('PayPal API Setup Errors: ' . implode(', ', $errors));
    }
    
    return $success;
}

// Add a method to securely retrieve credentials when needed
private static function getSecureCredentials() {
    $credentials = array(
        'username' => '',
        'password' => '',
        'signature' => ''
    );
    
    if (function_exists('get_transient')) {
        $encrypted_username = get_transient('paypal_ap_creds_username');
        $encrypted_password = get_transient('paypal_ap_creds_password');
        $encrypted_signature = get_transient('paypal_ap_creds_signature');
        
        if ($encrypted_username && $encrypted_password && $encrypted_signature) {
            $credentials['username'] = openssl_decrypt($encrypted_username, 'AES-256-CBC', AUTH_SALT, 0, substr(SECURE_AUTH_SALT, 0, 16));
            $credentials['password'] = openssl_decrypt($encrypted_password, 'AES-256-CBC', AUTH_SALT, 0, substr(SECURE_AUTH_SALT, 0, 16));
            $credentials['signature'] = openssl_decrypt($encrypted_signature, 'AES-256-CBC', AUTH_SALT, 0, substr(SECURE_AUTH_SALT, 0, 16));
        }
    }
    
    return $credentials;
}

    /**
     * Use to change environment. Must be called before using IPN-functionality
     * @param string $env Set to 'sandbox' or leave default for testmode.
     * @return Nothing
     */

    public static function setEnv($env = 'sandbox')
    {
        // Sanitize input
        self::$__env = ($env === 'sandbox') ? 'sandbox' : 'paypal';

        if (self::$__env == 'sandbox') {
            self::$__apiEndpoint = 'https://svcs.sandbox.paypal.com/AdaptivePayments';
        } else {
            self::$__apiEndpoint = 'https://svcs.paypal.com/AdaptivePayments';
        }
    }

    /**
     * Setup pre-approval for payment(s). Remember to call setAuth before use.
     *
     * @param array $options Array containing data needed to set up the pre-approval
     * @return
     *    If succesful: redirect to paypal for approval
     *    If unsuccessful: array with ['success'] = false and ['errors']
     */
    public static function preApproval($options)
    {
        // Sanitize all input values
        $options = self::sanitizeOptions($options);

        // Specifiy default values if user did not set them
        if (!isset($options['returnUrl'])) $options['returnUrl'] = '';
        if (!isset($options['cancelUrl'])) $options['cancelUrl'] = '';
        if (!isset($options['currencyCode'])) $options['currencyCode'] = '';
        if (!isset($options['startingDate'])) $options['startingDate'] = '';
        if (!isset($options['endingDate'])) $options['endingDate'] = '';
        if (!isset($options['maxTotalAmountOfAllPayments'])) $options['maxTotalAmountOfAllPayments'] = '';
        if (!isset($options['senderEmail'])) $options['senderEmail'] = '';
        if (!isset($options['maxNumberOfPayments'])) $options['maxNumberOfPayments'] = '';
        if (!isset($options['paymentPeriod'])) $options['paymentPeriod'] = '';
        if (!isset($options['dateOfMonth'])) $options['dateOfMonth'] = '';
        if (!isset($options['dayOfWeek'])) $options['dayOfWeek'] = '';
        if (!isset($options['maxAmountPerPayment'])) $options['maxAmountPerPayment'] = '';
        if (!isset($options['maxNumberOfPaymentsPerPeriod'])) $options['maxNumberOfPaymentsPerPeriod'] = '';
        if (!isset($options['pinType'])) $options['pinType'] = '';

        // Validate required options
        if (empty($options['returnUrl']) || empty($options['cancelUrl']) || 
            empty($options['currencyCode']) || empty($options['startingDate']) || 
            empty($options['endingDate']) || empty($options['maxTotalAmountOfAllPayments'])) {
            return array('success' => false, 'errors' => array(array(
                'errorId' => 'MISSING_PARAMETERS',
                'message' => 'Required parameters missing for pre-approval',
                'severity' => 'Error'
            )));
        }

        $resArray = self::CallPreapproval(
            $options['returnUrl'], 
            $options['cancelUrl'], 
            $options['currencyCode'], 
            $options['startingDate'], 
            $options['endingDate'], 
            $options['maxTotalAmountOfAllPayments'], 
            $options['senderEmail'], 
            $options['maxNumberOfPayments'], 
            $options['paymentPeriod'], 
            $options['dateOfMonth'], 
            $options['dayOfWeek'], 
            $options['maxAmountPerPayment'], 
            $options['maxNumberOfPaymentsPerPeriod'], 
            $options['pinType']
        );

        $ack = strtoupper($resArray["responseEnvelope.ack"]);
        if ($ack == "SUCCESS") {
            $cmd = "cmd=_ap-preapproval&preapprovalkey=" . urlencode($resArray["preapprovalKey"]);
            self::RedirectToPayPal($cmd);
        } else {
            return array('success' => false, 'errors' => self::generateErrorArray($resArray));
        }
    }

    /**
     * Do a payment. Can be preapproved or not. Remember to call setAuth before use.
     *
     * @param array $options Array containing data needed to perfom the payment
     * @return
     *    If not preapproved: redirect to paypal for approval/payment
     *    If prapproved and successful: array with ['success'] = true and ['details'] about the payment
     *    If unsuccessful: array with ['success'] = false and ['errors']
     */
    public static function doPayment($options)
    {
        // Sanitize all input values
        $options = self::sanitizeOptions($options);

        // Specifiy default values if user did not set them
        if (!isset($options['cancelUrl'])) $options['cancelUrl'] = 'https://NoOp';
        if (!isset($options['returnUrl'])) $options['returnUrl'] = 'https://NoOp';
        if (!isset($options['senderEmail'])) $options['senderEmail'] = '';
        if (!isset($options['currencyCode'])) $options['currencyCode'] = '';
        if (!isset($options['receiverEmailArray'])) $options['receiverEmailArray'] = array('');
        if (!isset($options['receiverAmountArray'])) $options['receiverAmountArray'] = array('');
        if (!isset($options['receiverPrimaryArray'])) $options['receiverPrimaryArray'] = array('');
        if (!isset($options['receiverInvoiceIdArray'])) $options['receiverInvoiceIdArray'] = array('');
        if (!isset($options['feesPayer'])) $options['feesPayer'] = '';
        if (!isset($options['ipnNotificationUrl'])) $options['ipnNotificationUrl'] = '';
        if (!isset($options['memo'])) $options['memo'] = '';
        if (!isset($options['pin'])) $options['pin'] = '';
        if (!isset($options['preapprovalKey'])) $options['preapprovalKey'] = '';
        if (!isset($options['reverseAllParallelPaymentsOnError'])) $options['reverseAllParallelPaymentsOnError'] = '';

        // Validate required options
        if (empty($options['currencyCode']) || empty($options['receiverEmailArray']) || 
            empty($options['receiverAmountArray'])) {
            return array('success' => false, 'errors' => array(array(
                'errorId' => 'MISSING_PARAMETERS',
                'message' => 'Required parameters missing for payment',
                'severity' => 'Error'
            )));
        }

        // Set values users may not define for this transaction type
        $options['actionType'] = 'PAY';
        $options['trackingId'] = self::generateTrackingID();

        $resArray = self::CallPay(
            $options['actionType'], 
            $options['cancelUrl'], 
            $options['returnUrl'], 
            $options['currencyCode'], 
            $options['receiverEmailArray'], 
            $options['receiverAmountArray'], 
            $options['receiverPrimaryArray'], 
            $options['receiverInvoiceIdArray'], 
            $options['feesPayer'], 
            $options['ipnNotificationUrl'], 
            $options['memo'], 
            $options['pin'], 
            $options['preapprovalKey'], 
            $options['reverseAllParallelPaymentsOnError'], 
            $options['senderEmail'], 
            $options['trackingId']
        );

        $ack = strtoupper($resArray["responseEnvelope.ack"]);
        if ($ack == "SUCCESS") {
            if ($options['preapprovalKey'] == "") {
                // redirect for web approval flow
                $cmd = "cmd=_ap-payment&paykey=" . urlencode($resArray["payKey"]);
                self::RedirectToPayPal($cmd);
            } else {
                // payKey is the key that you can use to identify the payment resulting from the Pay call
                $payKey = urlencode($resArray["payKey"]);
                // paymentExecStatus is the status of the payment
                $paymentExecStatus = urlencode($resArray["paymentExecStatus"]);
                return array(
                    'success' => true,
                    'details' => array(
                        'payKey' => $payKey,
                        'paymentExecStatus' => $paymentExecStatus
                    )
                );
            }
        } else {
            return array('success' => false, 'errors' => self::generateErrorArray($resArray));
        }
    }

    /**
     * Handle a received IPN. Remember to call setEnv before use.
     *
     * @param array $data_array Array containing the data received from PayPal.
     * @return
     *    If VERIFIED: true
     *    If UNVERIFIED: false
     */
    public static function handleIpn($data_array)
{
    if (empty($data_array)) {
        error_log('PayPal IPN: Empty data received');
        return false;
    }
    
    // Verify required IPN parameters exist
    $required_fields = array('txn_id', 'payment_status');
    foreach ($required_fields as $field) {
        if (!isset($data_array[$field]) || empty($data_array[$field])) {
            error_log('PayPal IPN: Missing required field: ' . $field);
            return false;
        }
    }
    
    // Get transaction ID for verification
    $txn_id = isset($data_array['txn_id']) ? $data_array['txn_id'] : '';
    $payment_status = isset($data_array['payment_status']) ? $data_array['payment_status'] : '';
    
    // Check if this transaction ID has been processed before to prevent replay attacks
    if (function_exists('get_option')) {
        $processed_txns = get_option('paypal_processed_transactions', array());
        if (in_array($txn_id, $processed_txns)) {
            error_log('PayPal IPN: Duplicate transaction detected: ' . $txn_id);
            return false;
        }
    }
    
    // Original IPN verification code continues here...
    if (self::$__env == "sandbox") {
        $payPalURL = "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr";
    } else {
        $payPalURL = "https://ipnpb.paypal.com/cgi-bin/webscr";
    }
    
    // Rest of the verification code...
    
    // If verification passes, store the transaction ID to prevent replay attacks
    if (strcmp($res, "VERIFIED") == 0) {
        if (function_exists('update_option')) {
            $processed_txns = get_option('paypal_processed_transactions', array());
            $processed_txns[] = $txn_id;
            // Keep the list manageable
            if (count($processed_txns) > 1000) {
                array_shift($processed_txns);
            }
            update_option('paypal_processed_transactions', $processed_txns);
        }
        
        error_log('PayPal IPN: Payment verified for transaction ' . $txn_id);
        return true;
    } else if (strcmp($res, "INVALID") == 0) {
        error_log('PayPal IPN: Payment invalid for transaction ' . $txn_id);
        return false;
    } else {
        error_log('PayPal IPN: Unexpected response for transaction ' . $txn_id . ': ' . $res);
        return false;
    }
}


    /*********************************************
     * Private functions. Most of them extracted from PayPal's PHP examples
     **********************************************/

    // Helper function to sanitize all input values in an options array
    // Add this to the sanitizeOptions method
private static function sanitizeOptions($options) {
    $sanitized = array();
    
    foreach ($options as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = self::sanitizeOptions($value);
        } else if (is_string($value)) {
            // Get context from key name
            $key_lower = strtolower($key);
            
            // Basic sanitization first
            $value = trim($value);
            
            // Context-aware sanitization
            if (strpos($key_lower, 'email') !== false) {
                // Email sanitization
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            } elseif (strpos($key_lower, 'url') !== false || 
                      $key_lower == 'returnurl' || 
                      $key_lower == 'cancelurl' || 
                      $key_lower == 'ipnnotificationurl') {
                // URL sanitization
                $value = filter_var($value, FILTER_SANITIZE_URL);
            } elseif (strpos($key_lower, 'amount') !== false || 
                      strpos($key_lower, 'total') !== false || 
                      strpos($key_lower, 'fee') !== false) {
                // Payment amount sanitization - strict numeric with decimal point
                $value = preg_replace('/[^0-9.]/', '', $value);
                // Ensure proper decimal format
                if (is_numeric($value)) {
                    $value = number_format((float)$value, 2, '.', '');
                }
            } elseif ($key_lower == 'currencycode') {
                // Currency code validation - allow only alphabetic 3-char codes
                $value = preg_replace('/[^A-Z]/', '', strtoupper($value));
                if (strlen($value) > 3) {
                    $value = substr($value, 0, 3);
                }
            } elseif (in_array($key_lower, ['startingdate', 'endingdate'])) {
                // Date validation - ISO format
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    // Valid ISO format date, keep as is
                } else {
                    // Attempt to convert or sanitize
                    $timestamp = strtotime($value);
                    if ($timestamp !== false) {
                        $value = date('Y-m-d\TH:i:s\Z', $timestamp);
                    } else {
                        $value = ''; // Invalid date
                    }
                }
            } else {
                // General string sanitization - allow only reasonable characters
                $value = preg_replace('/[^\w\s\-\.\/\,\@\:\;\&\=\+\?\#]/', '', $value);
            }
            
            $sanitized[$key] = $value;
        } else if (is_numeric($value)) {
            // Ensure numeric values are properly sanitized
            $sanitized[$key] = is_float($value) ? (float)$value : (int)$value;
        } else {
            // For boolean or null values
            $sanitized[$key] = $value;
        }
    }
    
    return $sanitized;
}

    private static function callRefund($payKey, $transactionId, $trackingId, $receiverEmailArray, $receiverAmountArray)
    {
        // Sanitize inputs
        $payKey = trim($payKey);
        $transactionId = trim($transactionId);
        $trackingId = trim($trackingId);

        /* Gather the information to make the Refund call.
            The variable nvpstr holds the name value pairs
        */

        $nvpstr = "";

        // conditionally required fields
        if ("" != $payKey) {
            $nvpstr = "payKey=" . urlencode($payKey);
            if (0 != count($receiverEmailArray)) {
                reset($receiverEmailArray);
                while (list($key, $value) = each($receiverEmailArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                    }
                }
            }
            if (0 != count($receiverAmountArray)) {
                reset($receiverAmountArray);
                while (list($key, $value) = each($receiverAmountArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                    }
                }
            }
        } elseif ("" != $trackingId) {
            $nvpstr = "trackingId=" . urlencode($trackingId);
            if (0 != count($receiverEmailArray)) {
                reset($receiverEmailArray);
                while (list($key, $value) = each($receiverEmailArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                    }
                }
            }
            if (0 != count($receiverAmountArray)) {
                reset($receiverAmountArray);
                while (list($key, $value) = each($receiverAmountArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                    }
                }
            }
        } elseif ("" != $transactionId) {
            $nvpstr = "transactionId=" . urlencode($transactionId);
            // the caller should only have 1 entry in the email and amount arrays
            if (0 != count($receiverEmailArray)) {
                reset($receiverEmailArray);
                while (list($key, $value) = each($receiverEmailArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                    }
                }
            }
            if (0 != count($receiverAmountArray)) {
                reset($receiverAmountArray);
                while (list($key, $value) = each($receiverAmountArray)) {
                    if ("" != $value) {
                        $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                    }
                }
            }
        }

        /* Make the Refund call to PayPal */
        $resArray = self::hash_call("Refund", $nvpstr);

        /* Return the response array */
        return $resArray;
    }

    private static function CallPaymentDetails($payKey, $transactionId, $trackingId)
    {
        // Sanitize inputs
        $payKey = trim($payKey);
        $transactionId = trim($transactionId);
        $trackingId = trim($trackingId);

        /* Gather the information to make the PaymentDetails call.
            The variable nvpstr holds the name value pairs
        */

        $nvpstr = "";

        // conditionally required fields
        if ("" != $payKey) {
            $nvpstr = "payKey=" . urlencode($payKey);
        } elseif ("" != $transactionId) {
            $nvpstr = "transactionId=" . urlencode($transactionId);
        } elseif ("" != $trackingId) {
            $nvpstr = "trackingId=" . urlencode($trackingId);
        }

        /* Make the PaymentDetails call to PayPal */
        $resArray = self::hash_call("PaymentDetails", $nvpstr);

        /* Return the response array */
        return $resArray;
    }

    private static function CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId)
    {
        // Sanitize inputs
        $actionType = trim($actionType);
        $cancelUrl = trim($cancelUrl);
        $returnUrl = trim($returnUrl);
        $currencyCode = trim($currencyCode);
        $feesPayer = trim($feesPayer);
        $ipnNotificationUrl = trim($ipnNotificationUrl);
        $memo = trim($memo);
        $pin = trim($pin);
        $preapprovalKey = trim($preapprovalKey);
        $reverseAllParallelPaymentsOnError = trim($reverseAllParallelPaymentsOnError);
        $senderEmail = trim($senderEmail);
        $trackingId = trim($trackingId);

        /* Gather the information to make the Pay call.
            The variable nvpstr holds the name value pairs
        */

        // required fields
        $nvpstr = "actionType=" . urlencode($actionType) . "&currencyCode=" . urlencode($currencyCode);
        $nvpstr .= "&returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);

        if (0 != count($receiverAmountArray)) {
            reset($receiverAmountArray);
            while (list($key, $value) = each($receiverAmountArray)) {
                if ("" != $value) {
                    $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
                }
            }
        }

        if (0 != count($receiverEmailArray)) {
            reset($receiverEmailArray);
            while (list($key, $value) = each($receiverEmailArray)) {
                if ("" != $value) {
                    $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
                }
            }
        }

        if (0 != count($receiverPrimaryArray)) {
            reset($receiverPrimaryArray);
            while (list($key, $value) = each($receiverPrimaryArray)) {
                if ("" != $value) {
                    $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").primary=" . urlencode($value);
                }
            }
        }

        if (0 != count($receiverInvoiceIdArray)) {
            reset($receiverInvoiceIdArray);
            while (list($key, $value) = each($receiverInvoiceIdArray)) {
                if ("" != $value) {
                    $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").invoiceId=" . urlencode($value);
                }
            }
        }

        // optional fields
        if ("" != $feesPayer) {
            $nvpstr .= "&feesPayer=" . urlencode($feesPayer);
        }

        if ("" != $ipnNotificationUrl) {
            $nvpstr .= "&ipnNotificationUrl=" . urlencode($ipnNotificationUrl);
        }

        if ("" != $memo) {
            $nvpstr .= "&memo=" . urlencode($memo);
        }

        if ("" != $pin) {
            $nvpstr .= "&pin=" . urlencode($pin);
        }

        if ("" != $preapprovalKey) {
            $nvpstr .= "&preapprovalKey=" . urlencode($preapprovalKey);
        }

        if ("" != $reverseAllParallelPaymentsOnError) {
            $nvpstr .= "&reverseAllParallelPaymentsOnError=" . urlencode($reverseAllParallelPaymentsOnError);
        }

        if ("" != $senderEmail) {
            $nvpstr .= "&senderEmail=" . urlencode($senderEmail);
        }

        if ("" != $trackingId) {
            $nvpstr .= "&trackingId=" . urlencode($trackingId);
        }

        /* Make the Pay call to PayPal */
        $resArray = self::hash_call("Pay", $nvpstr);

        /* Return the response array */
        return $resArray;
    }

    private static function CallPreapprovalDetails($preapprovalKey)
    {
        // Sanitize input
        $preapprovalKey = trim($preapprovalKey);

        /* Gather the information to make the PreapprovalDetails call.
            The variable nvpstr holds the name value pairs
        */

        // required fields
        $nvpstr = "preapprovalKey=" . urlencode($preapprovalKey);

        /* Make the PreapprovalDetails call to PayPal */
        $resArray = self::hash_call("PreapprovalDetails", $nvpstr);

        /* Return the response array */
        return $resArray;
    }

    private static function CallPreapproval($returnUrl, $cancelUrl, $currencyCode, $startingDate, $endingDate, $maxTotalAmountOfAllPayments, $senderEmail, $maxNumberOfPayments, $paymentPeriod, $dateOfMonth, $dayOfWeek, $maxAmountPerPayment, $maxNumberOfPaymentsPerPeriod, $pinType)
    {
        // Sanitize inputs
        $returnUrl = trim($returnUrl);
        $cancelUrl = trim($cancelUrl);
        $currencyCode = trim($currencyCode);
        $startingDate = trim($startingDate);
        $endingDate = trim($endingDate);
        $maxTotalAmountOfAllPayments = trim($maxTotalAmountOfAllPayments);
        $senderEmail = trim($senderEmail);
        $maxNumberOfPayments = trim($maxNumberOfPayments);
        $paymentPeriod = trim($paymentPeriod);
        $dateOfMonth = trim($dateOfMonth);
        $dayOfWeek = trim($dayOfWeek);
        $maxAmountPerPayment = trim($maxAmountPerPayment);
        $maxNumberOfPaymentsPerPeriod = trim($maxNumberOfPaymentsPerPeriod);
        $pinType = trim($pinType);

        /* Gather the information to make the Preapproval call.
            The variable nvpstr holds the name value pairs
        */

        // required fields
        $nvpstr = "returnUrl=" . urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);
        $nvpstr .= "&currencyCode=" . urlencode($currencyCode) . "&startingDate=" . urlencode($startingDate);
        $nvpstr .= "&endingDate=" . urlencode($endingDate);
        $nvpstr .= "&maxTotalAmountOfAllPayments=" . urlencode($maxTotalAmountOfAllPayments);

        // optional fields
        if ("" != $senderEmail) {
            $nvpstr .= "&senderEmail=" . urlencode($senderEmail);
        }

        if ("" != $maxNumberOfPayments) {
            $nvpstr .= "&maxNumberOfPayments=" . urlencode($maxNumberOfPayments);
        }

        if ("" != $paymentPeriod) {
            $nvpstr .= "&paymentPeriod=" . urlencode($paymentPeriod);
        }

        if ("" != $dateOfMonth) {
            $nvpstr .= "&dateOfMonth=" . urlencode($dateOfMonth);
        }

        if ("" != $dayOfWeek) {
            $nvpstr .= "&dayOfWeek=" . urlencode($dayOfWeek);
        }

        if ("" != $maxAmountPerPayment) {
            $nvpstr .= "&maxAmountPerPayment=" . urlencode($maxAmountPerPayment);
        }

        if ("" != $maxNumberOfPaymentsPerPeriod) {
            $nvpstr .= "&maxNumberOfPaymentsPerPeriod=" . urlencode($maxNumberOfPaymentsPerPeriod);
        }

        if ("" != $pinType) {
            $nvpstr .= "&pinType=" . urlencode($pinType);
        }

        /* Make the Preapproval call to PayPal */
        $resArray = self::hash_call("Preapproval", $nvpstr);

        /* Return the response array */
        return $resArray;
    }

    private static function hash_call($methodName, $nvpStr)
{
    // Get secure credentials
    $credentials = self::getSecureCredentials();

        // Make a copy of the API endpoint
        $endpoint = self::$__apiEndpoint . "/" . $methodName;
        
        //setting the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        // CRITICAL SECURITY CHANGE - Enable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        // Set the HTTP Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-PAYPAL-REQUEST-DATA-FORMAT: NV',
        'X-PAYPAL-RESPONSE-DATA-FORMAT: NV',
        'X-PAYPAL-SECURITY-USERID: ' . $credentials['username'],
        'X-PAYPAL-SECURITY-PASSWORD: ' . $credentials['password'],
        'X-PAYPAL-SECURITY-SIGNATURE: ' . $credentials['signature'],
        'X-PAYPAL-SERVICE-VERSION: 1.3.0',
        'X-PAYPAL-APPLICATION-ID: ' . self::$__apiAppid
    ));

        // Set timeouts
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // If using proxy
        if (self::$__useProxy) {
            curl_setopt($ch, CURLOPT_PROXY, self::$__proxyHost . ":" . self::$__proxyPort);
        }

        // RequestEnvelope fields
        $detailLevel = urlencode("ReturnAll");    // See DetailLevelCode in the WSDL for valid enumerations
        $errorLanguage = urlencode("en_US");        // This should be the standard RFC 3066 language identification tag, e.g., en_US

        // NVPRequest for submitting to server
        $nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope.detailLevel=$detailLevel";
        $nvpreq .= "&$nvpStr";

        //setting the nvpreq as POST FIELD to curl
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

        //getting response from server
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //converting NVPResponse to an Associative Array
        $nvpResArray = self::deformatNVP($response);
        $nvpReqArray = self::deformatNVP($nvpreq);
        
        // Store safely in session - avoid storing sensitive data
        $_SESSION['last_api_request'] = time();
        $_SESSION['last_api_method'] = $methodName;
        $_SESSION['last_api_http_code'] = $http_code;

        if (curl_errno($ch)) {
            // Log error but don't expose sensitive details
            error_log('PayPal API Error: ' . $curl_error . ' Method: ' . $methodName);
            $_SESSION['curl_error_no'] = curl_errno($ch);
            // Don't store full error message in session to avoid info leakage
            $_SESSION['curl_error_msg'] = 'API connection error';
        } else {
            //closing the curl
            curl_close($ch);
        }

        return $nvpResArray;
    }

    private static function RedirectToPayPal($cmd)
    {
        // Redirect to paypal.com here
        $payPalURL = "";

        if (self::$__env == "sandbox") {
            $payPalURL = "https://www.sandbox.paypal.com/webscr?" . $cmd;
        } else {
            $payPalURL = "https://www.paypal.com/webscr?" . $cmd;
        }

        // Use header redirection with sanitation
        header("Location: " . filter_var($payPalURL, FILTER_SANITIZE_URL));
        exit; // Ensure script execution stops after redirect
    }

    private static function deformatNVP($nvpstr)
    {
        if (empty($nvpstr)) {
            return array();
        }
        
        $intial = 0;
        $nvpArray = array();

        while (strlen($nvpstr)) {
            //postion of Key
            $keypos = strpos($nvpstr, '=');
            //position of value
            $valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);

            // Handle malformed string
            if ($keypos === false) {
                break;
            }

            /*getting the Key and Value values and storing in a Associative Array*/
            $keyval = substr($nvpstr, $intial, $keypos);
            $valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);
            //decoding the respose
            $nvpArray[urldecode($keyval)] = urldecode($valval);
            $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
        }
        return $nvpArray;
    }

    private static function generateCharacter()
    {
        $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        // Use secure random generation
        if (function_exists('random_int')) {
            $char = substr($possible, random_int(0, strlen($possible) - 1), 1);
        } else {
            // Fallback for older PHP versions
            $char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);
        }
        return $char;
    }

    private static function generateTrackingID()
    {
        // Generate a more robust ID
        $timestamp = microtime(true);
        $prefix = dechex(floor($timestamp));
        
        $GUID = $prefix . '_';
        for ($i = 0; $i < 10; $i++) {
            $GUID .= self::generateCharacter();
        }
        return $GUID;
    }

    private static function generateErrorArray($errorResponse)
    {
        $errors = array();
        if (!is_array($errorResponse)) {
            return array(array(
                'errorId' => 'SYSTEM_ERROR',
                'message' => 'Invalid response from PayPal',
                'domain' => 'PLATFORM',
                'severity' => 'Error',
                'category' => 'System'
            ));
        }
        
        for ($i = 0; $i <= count($errorResponse); $i++) {
            if (isset($errorResponse['error(' . $i . ').errorId'])) {
                $errors[$i]['errorId'] = urldecode($errorResponse['error(' . $i . ').errorId']);
                $errors[$i]['message'] = urldecode($errorResponse['error(' . $i . ').message']);
                $errors[$i]['domain'] = isset($errorResponse['error(' . $i . ').domain']) ? 
                    urldecode($errorResponse['error(' . $i . ').domain']) : 'Application';
                $errors[$i]['severity'] = isset($errorResponse['error(' . $i . ').severity']) ? 
                    urldecode($errorResponse['error(' . $i . ').severity']) : 'Error';
                $errors[$i]['category'] = isset($errorResponse['error(' . $i . ').category']) ? 
                    urldecode($errorResponse['error(' . $i . ').category']) : 'Application';
            }
        }
        
        if (empty($errors)) {
            // Generic error if no specific errors found
            $errors[] = array(
                'errorId' => 'UNKNOWN_ERROR',
                'message' => 'An unknown error occurred',
                'domain' => 'PLATFORM',
                'severity' => 'Error',
                'category' => 'System'
            );
        }
        
        return $errors;
    }
}