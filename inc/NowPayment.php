<?php
/*
 * ZarinPal Advanced Class
 *
 * version 	: 1.0
 * link 	: https://vrl.ir/zpc
 *
 * author 	: milad maldar
 * e-mail 	: miladworkshop@gmail.com
 * website 	: https://miladworkshop.ir
*/

class NowPayment
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
        $this->api_key = awards_options('nowpayment_api_key');

        if (awards_options('nowpayment_test') == 'enable') {
            $this->sandbox = true;
            $this->api_key = awards_options('nowpayment_api_key_sandbox');
        }
    }

    public function callback()
    {
        if(!isset($_GET['now-payment-ipn'])) return false;

        if (isset($_GET['mass'])) {
            $postID = (isset($_GET['order-id']) && $_GET['order-id']) ? explode('-', $_GET['order-id']) : false;
            $invoiceID = get_post_meta($postID[0], 'now_payment_payment_id', true);
        } else {
            $postID = (isset($_GET['order-id']) && is_numeric($_GET['order-id'])) ? (int) $_GET['order-id'] : false;
            $invoiceID = get_post_meta($postID, 'now_payment_payment_id', true);
        }

        if ($invoiceID) {
            $payment = $this->getInvoice($invoiceID);
            if(!$payment){
                echo __("invalid payment details:".$invoiceID);
                exit();
            }

            if ($payment->payment_status == 'finished') {
                if ($postID && is_numeric($postID)) {
                    update_post_meta($postID, 'status', 'pending_review');
                    update_post_meta($postID, 'payment', [
                        'gateway' => 'now-payment',
                        'payment_id' => $payment->payment_id,
                        'order_id' => $payment->order_id,
                        'price_amount' => $payment->price_amount,
                        'price_currency' => $payment->price_currency,
                        'pay_amount' => $payment->pay_amount,
                        'pay_currency' => $payment->pay_currency,
                        'outcome_amount' => $payment->outcome_amount,
                        'outcome_currency' => $payment->outcome_currency,
                        'purchase_id' => $payment->purchase_id,
                        'payment_status' => $payment->payment_status,
                        'time' => date('Y-m-d H:i:s'),
                    ]);
                }else if($postID && is_array($postID)){
                    foreach ($postID as $itemID){
                        if(!is_numeric($itemID)) return false;

                        update_post_meta($itemID, 'status', 'pending_review');
                        update_post_meta($itemID, 'payment', [
                            'gateway' => 'now-payment',
                            'payment_id' => $payment->payment_id,
                            'order_id' => $payment->order_id,
                            'price_amount' => $payment->price_amount,
                            'price_currency' => $payment->price_currency,
                            'pay_amount' => $payment->pay_amount,
                            'pay_currency' => $payment->pay_currency,
                            'outcome_amount' => $payment->outcome_amount,
                            'outcome_currency' => $payment->outcome_currency,
                            'purchase_id' => $payment->purchase_id,
                            'payment_status' => $payment->payment_status,
                            'time' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        }
        wp_redirect(get_the_permalink(awards_options('page_dashboard'))."/?dash-page=entry-list");
        exit();
    }

    public function setAmount($price)
    {
        $this->price_amount = $price;
    }

    public function setPriceCurrency($symbol)
    {
        $this->price_currency = $symbol;
    }

    public function setPayCurrency($symbol)
    {
        $this->pay_currency = $symbol;
    }

    public function setCallback($url)
    {
        $this->callback_url = $url;
    }

    /**
     * @param $payCurrency
     * @param $amount
     * @param $callback
     * @return false|mixed
     */
    public function payment($orderID, $payCurrency, $amount = '', $callback = '')
    {
        $this->setPayCurrency($payCurrency);

        if ($amount) $this->setAmount($amount);
        if ($callback) $this->setCallback($callback);

        $request = $this->request('payment', [
            'order_id' => $orderID,
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'pay_currency' => $this->pay_currency,
            'ipn_callback_url' => $this->callback_url,
        ], 'POST');
        if (!$request) return false;

        return $request;
    }

    public function invoice($orderID, $payCurrency, $amount = '', $callback = '')
    {
        $this->setPayCurrency($payCurrency);

        if ($amount) $this->setAmount($amount);
        if ($callback) $this->setCallback($callback);

        $request = $this->request('invoice', [
            'order_id' => $orderID,
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'pay_currency' => $this->pay_currency,
            'ipn_callback_url' => $this->callback_url,
            'success_url' => $this->callback_url,
            'cancel_url' => $this->callback_url,
        ], 'POST');
        if (!$request) return false;

        return $request;
    }

    /**
     * @return bool
     */
    public function getStatus()
    {
        $result = $this->request('status');
        if (!$result) return false;

        return ($result->message == 'OK');
    }

    /**
     * @return bool
     */
    public function getPayment($paymentID)
    {
        $result = $this->request("payment/{$paymentID}");
        if (!$result || !isset($result->payment_status) || !$result->payment_status) {
            $this->error = $result;
            return false;
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function getInvoices()
    {
        return $this->getPayments();
    }

    /**
     * @return bool
     */
    public function getInvoice($invoiceID)
    {
        $invoices = $this->getPayments();
        if(!$invoices || !isset($invoices->data)) return false;

        foreach ($invoices->data as $invoice){
            if($invoice->invoice_id == $invoiceID) return $invoice;
        }

        $this->error = $invoices->data;
        return false;
    }

    /**
     * @return bool
     */
    public function getPayments()
    {
        $result = $this->request("payment");
        if (!$result || !isset($result->data)) {
            $this->error = $result;
            return false;
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function getPaymentStatus($paymentID)
    {
        $result = $this->request("payment/{$paymentID}");
        if (!$result) return false;

        return (isset($result->payment_status) && $result->payment_status);
    }

    /**
     * @param $method
     * @return string
     */
    public function apiURL($method = '')
    {
        if ($this->sandbox) {
            return "https://api.sandbox.nowpayments.io/v1/{$method}";
        }

        return "https://api.nowpayments.io/v1/{$method}";
    }

    /**
     * @param $action
     * @param $params
     * @param $method
     * @param $headers
     * @return mixed
     */
    private function request($action, $params = [], $method = 'GET', $headers = [])
    {
        $headers = array_merge([
            "x-api-key: {$this->api_key}",
            'Content-Type: application/json',
        ], $headers);

        $ch = curl_init($this->apiURL($action));

        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                break;
        }

        $this->params = $params;
        $this->headers = $headers;
        $this->result = curl_exec($ch);
        $this->error = curl_error($ch);
        $this->info = curl_getinfo($ch);
        curl_close($ch);

        return json_decode($this->result);
    }

    public function error()
    {
        return $this->error;
    }

}

$nowPayment = new NowPayment();
add_action('init', [$nowPayment, 'callback']);
