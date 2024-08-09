<?php
/*
Plugin Name: Custom Stripe Payment Link
Description: Generate and send payment links to customers using Stripe.
Version: 1.0
Author: Sohaib Ali Khan
*/

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

defined('ABSPATH') or die('No script kiddies please!');

add_shortcode('csp_payment_link_form', 'csp_payment_link_form_shortcode');

function csp_payment_link_form_shortcode() {
    ob_start();
    csp_admin_form();
    return ob_get_clean();
}

function csp_admin_form() {
    ?>
    <div class="wrap">
        <h1>Generate Stripe Payment Link</h1>
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
        $amount = sanitize_text_field($_POST['amount']) * 100; // Convert dollars to cents

        $payment_link = csp_generate_payment_link($order_id, $amount);

        csp_send_payment_link($order_id, $payment_link);
        csp_notify_admin($order_id, $payment_link);
    }
}

function csp_generate_payment_link($order_id, $amount) {
    // Replace 'YOUR_SECRET_KEY' with your actual Stripe secret key
    Stripe::setApiKey('API KEY WORKING ');

    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Order ' . $order_id,
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => site_url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => site_url('/payment-cancel'),
    ]);

    return $session->url;
}

function csp_send_payment_link($order_id, $payment_link) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order_number = $order->get_order_number(); // Fetch the order number
        $customer_email = $order->get_billing_email();
        $subject = 'Pay Now  #' . $order_number;
        $message = "Dear Customer,\n\nThank you for your order. Please complete your payment by clicking the link below:\n\n";
        $message .= "Payment Link: " . $payment_link . "\n\n";
        $message .= "If you have any questions, feel free to contact us.\n\nBest regards,\nYour Website Name";

        wp_mail($customer_email, $subject, $message);
    }
}

function csp_notify_admin($order_id, $payment_link) {
    $admin_email = 'your@gmail.com';
    $order = wc_get_order($order_id);
    $order_number = $order ? $order->get_order_number() : $order_id;

    $subject = 'Payment Link Sent for Order #' . $order_number;
    $message = "A payment link has been generated and sent to the customer for Order Number: " . $order_number . ".\n";
    $message .= "Payment Link: " . $payment_link;

    wp_mail($admin_email, $subject, $message);
}
