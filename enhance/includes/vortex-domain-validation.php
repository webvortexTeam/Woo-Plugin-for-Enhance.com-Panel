<?php

namespace Vortex;
if (!defined('ABSPATH')) {
    exit;
}
class EnhanceDomain_Validator {
    
     
    public function __construct() {
        add_filter('woocommerce_before_add_to_cart_button', array($this, 'add_EnhanceDomain_input_field'));
        add_action('woocommerce_add_to_cart_validation', array($this, 'validate_EnhanceDomain_before_add_to_cart'), 10, 3);
        add_action('woocommerce_add_cart_item_data', array($this, 'add_EnhanceDomain_to_cart_session'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_EnhanceDomain_to_order_meta'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'save_EnhanceDomain_to_order_meta_on_completion'), 10, 1);
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'display_EnhanceDomain_on_order_received_page'), 10, 2);
    }

    public function add_EnhanceDomain_input_field() {
        global $product;

        $enable_enhance = get_post_meta($product->get_id(), '_enable_enhance', true);
        if ($enable_enhance === 'yes') {
            echo '<p class="form-row validate-required">
                    <label for="EnhanceDomain">' . __('Domain', 'woocommerce') . ' <abbr class="required" title="required">*</abbr></label>
                    <input type="text" id="EnhanceDomain" name="EnhanceDomain" class="input-text" required>
                  </p>';
				          $enhance_plan = get_post_meta($product->get_id(), '_enhance_plan', true);
        if (!empty($enhance_plan)) {
            echo '<input type="hidden" id="_enhance_plan" name="_enhance_plan" value="' . esc_attr($enhance_plan) . '">';
        }
        }
    }


    public function add_EnhanceDomain_to_cart_session($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['EnhanceDomain']) && !empty($_POST['EnhanceDomain'])) {
            $EnhanceDomain = sanitize_text_field($_POST['EnhanceDomain']);
            $unique_key = md5(microtime() . rand());
            WC()->session->set('EnhanceDomain_' . $unique_key, $EnhanceDomain);
            $cart_item_data['EnhanceDomain'] = $EnhanceDomain;
        }
        if (isset($_POST['_enhance_plan']) && !empty($_POST['_enhance_plan'])) {
            $_enhance_plan = sanitize_text_field($_POST['_enhance_plan']);
            $unique_key = md5(microtime() . rand());
            WC()->session->set('_enhance_plan' . $unique_key, $_enhance_plan);
            $cart_item_data['_enhance_plan'] = $_enhance_plan;
        }
        return $cart_item_data;
    }

    public function save_EnhanceDomain_to_order_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['EnhanceDomain'])) {
            $order->update_meta_data('EnhanceDomain', $values['EnhanceDomain']);
        }
		if (!empty($values['_enhance_plan'])) {
            $order->update_meta_data('_enhance_plan', $values['_enhance_plan']);
        }
    }

    public function save_EnhanceDomain_to_order_meta_on_completion($order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        foreach ($items as $item) {
            $EnhanceDomain = $item->get_meta('EnhanceDomain', true);
			$_enhance_plan = $item->get_meta('_enhance_plan', true);

            if (!empty($EnhanceDomain)) {
                $order->update_meta_data('EnhanceDomain', $EnhanceDomain);
				$order->update_meta_data('_enhance_plan', $_enhance_plan);

                $order->save();
            }
        }
    }

    public function display_EnhanceDomain_on_order_received_page($text, $order) {
        $EnhanceDomain = $order->get_meta('EnhanceDomain', true);
        if (!empty($EnhanceDomain)) {
            $text .= '<p><strong>Domain:</strong> ' . esc_html($EnhanceDomain) . '</p>';
        }
        return $text;
    }


public function validate_EnhanceDomain_before_add_to_cart($passed, $product_id, $quantity) {
    if (isset($_POST['EnhanceDomain']) && !empty($_POST['EnhanceDomain'])) {
        $EnhanceDomain = sanitize_text_field($_POST['EnhanceDomain']);

        $host = get_option('host');
        $apikey = get_option('apikey');
        $orgId = get_option('orgId');
        if (!preg_match('/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/', $EnhanceDomain)) {
            wc_add_notice(__('Please enter a valid domain name in the format of domain.tld.', 'woocommerce'), 'error');
                $passed = false;
        }
        $url = $host . '/api/orgs/' . $orgId . '/websites?search=' . urlencode($EnhanceDomain);
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $apikey,
            ),
        ));

        if (!is_wp_error($response) && $response['response']['code'] == 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['total']) && $data['total'] > 0) {
                wc_add_notice(__('This EnhanceDomain already exists. Please choose a different one.', 'woocommerce'), 'error');
                $passed = false;
            }
        }
    }

    return $passed;
}

	    public function display_EnhanceDomain_in_cart($product_name, $cart_item, $cart_item_key) {
        $EnhanceDomain = WC()->session->get('EnhanceDomain' . $cart_item['key']);
        if ($EnhanceDomain) {
            $product_name .= '<br><small>Domain: ' . esc_html($EnhanceDomain) . '</small>';
        }
        return $product_name;
    }
}

new EnhanceDomain_Validator();
