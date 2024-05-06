<?php

namespace Vortex;
if (!defined('ABSPATH')) {
    exit;
}
class Customer_Exist_Checker {
    
    public function __construct() {
        add_action('woocommerce_register_post', array($this, 'check_customer_existence'), 10, 3);
    }

    public function check_customer_existence($username, $email, $validation_errors) {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if ($current_user->user_email === $email) {
                return;
            } else {
                wc_add_notice(__('You are already logged in with a different email. Please logout or continue with the current session.', 'woocommerce'), 'error');
                return false;
            }
        }

        $orgId = get_option('orgId');

        $host = get_option('host');
        $url = $host . '/api/orgs/' . $orgId . '/customers?email=' . urlencode($email);

        $response = wp_remote_get($url);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data) && isset($data['ownerEmail']) && $data['ownerEmail'] === $email) {
                if (!is_user_logged_in()) {
                    wc_add_notice(__('User already exists. Please login instead.', 'vortexenhance'), 'error');
                    exit;
                } else {
                    wc_add_notice(__('You are already logged in with a different email. Please logout or continue with the current session.', 'vortexenhance'), 'error');
                    return false; 
                }
            }
        }
    }
}

new Customer_Exist_Checker();
