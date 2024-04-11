<?php

namespace Vortex;
if (!defined('ABSPATH')) {
    exit;
}
class Product_Enhancer {
    
    public function __construct() {
        add_action('woocommerce_product_write_panel_tabs', array($this, 'add_product_enhance_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'product_enhance_tab_content'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_enhance_settings'));
    }

    public function add_product_enhance_tab() {
        ?>
        <li class="enhance_tab">
            <a href="#enhance_tab_data"><?php _e('Enhance', 'woocommerce'); ?></a>
        </li>
        <?php
    }

    public function product_enhance_tab_content() {
        global $post;
        ?>
        <div id="enhance_tab_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox(array(
                    'id' => '_enable_enhance',
                    'label' => __('Enable Enhance for this product', 'woocommerce'),
                    'desc_tip' => 'true',
                    'description' => __('Check this box to enable Enhance for this product.', 'woocommerce'),
                ));

                $plans = $this->get_organization_plans();
                if (!empty($plans)) {
                    woocommerce_wp_select(array(
                        'id' => '_enhance_plan',
                        'label' => __('Plan', 'woocommerce'),
                        'options' => $plans,
                    ));
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function save_product_enhance_settings($post_id) {
        $enable_enhance = isset($_POST['_enable_enhance']) ? 'yes' : 'no';
        $enhance_plan = isset($_POST['_enhance_plan']) ? $_POST['_enhance_plan'] : '';

        update_post_meta($post_id, '_enable_enhance', $enable_enhance);
        update_post_meta($post_id, '_enhance_plan', $enhance_plan);
    }

	public function get_organization_plans() {
		$host = get_option('host');
		$apikey = get_option('apikey');
		$orgId = get_option('orgId');

		$url = $host . '/api/orgs/' . $orgId . '/plans';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $apikey,
			),
		));

		if (!is_wp_error($response) && $response['response']['code'] == 200) {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			$plans = array();

			if (isset($data['items']) && is_array($data['items'])) {
				foreach ($data['items'] as $plan) {
					$plans[$plan['id']] = $plan['name'];
				}
			}

			return $plans;
		}

		return array();
	}

}

new Product_Enhancer();
