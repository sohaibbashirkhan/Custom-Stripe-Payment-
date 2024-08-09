<?php
/*
Plugin Name: Custom Authorize.Net Payment Link
Description: Generate and send payment links to customers using Authorize.Net.
Version: 1.0
Author: Sohaib Ali Khan
*/

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

defined('ABSPATH') or die('No script kiddies please!');

add_shortcode('cap_payment_link_form', 'cap_payment_link_form_shortcode');

function cap_payment_link_form_shortcode() {
    ob_start();
    cap_admin_form();
    return ob_get_clean();
}

function cap_admin_form() {
    ?>
    <div class="wrap">
        <h1>Generate Authorize.Net Payment Link</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Order ID</th>
                    <td><input type="text" name="order_id" value="" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Amount (in dollars)</th>
                    <td><input type="number" step="0.01" name="amount" value="" required /></td>
                </tr>
            </table>
            <?php submit_button('Generate Payment Link'); ?>
        </form>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['amount'])) {
        $order_id = sanitize_text_field($_POST['order_id']);
        $amount = sanitize_text_field($_POST['amount']);

        $payment_link = cap_generate_payment_link($order_id, $amount);

        cap_send_payment_link($order_id, $payment_link);
        cap_notify_admin($order_id, $payment_link);
    }
}

function cap_generate_payment_link($order_id, $amount) {
    // Set up the Authorize.Net Merchant Authentication
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName('YOUR_API_LOGIN_ID');
    $merchantAuthentication->setTransactionKey('YOUR_TRANSACTION_KEY');

    // Create the payment transaction request
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction");
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setOrder(new AnetAPI\OrderType());
    $transactionRequestType->getOrder()->setInvoiceNumber($order_id);

    // Create the request
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequestType);

    // Create the controller
    $controller = new AnetController\CreateTransactionController($request);

    // Get the response
    $response = $controller->executeWithApiResponse(AnetAPI\ANetEnvironment::SANDBOX);

    // Check if the response is successful
    if ($response != null && $response->getMessages()->getResultCode() == "Ok") {
        $transactionResponse = $response->getTransactionResponse();
        if ($transactionResponse != null && $transactionResponse->getMessages() != null) {
            // Return the payment link
            return 'https://your-site.com/payment-success?transaction_id=' . $transactionResponse->getTransId();
        }
    }

    return 'Error generating payment link';
}

function cap_send_payment_link($order_id, $payment_link) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order_number = $order->get_order_number(); // Fetch the order number
        $customer_email = $order->get_billing_email();
        $subject = 'Your Payment Link';
        $message = 'Order Number: ' . $order_number . "\n" .
                   'Click the following link to complete your payment: ' . $payment_link;
        wp_mail($customer_email, $subject, $message);
    }
}

function cap_notify_admin($order_id, $payment_link) {
    $admin_email = 'rock@plexlogo.com';
    $order = wc_get_order($order_id);
    $order_number = $order ? $order->get_order_number() : $order_id;
    
    $subject = 'Payment Link Sent';
    $message = 'A payment link has been generated and sent to the customer for Order Number: ' . $order_number . '. Payment link: ' . $payment_link;
    wp_mail($admin_email, $subject, $message);
}