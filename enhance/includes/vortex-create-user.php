<?php

namespace Vortex;
if (!defined('ABSPATH')) {
    exit;
}
class Subscription_Order_Handler
{
    private $orgUUID;
    private $apikey;
	//private $lastPlanId;
    private $host;

    public function __construct()
    {
        $this->orgUUID = get_option("orgId");
        $this->apikey = get_option("apikey");
        $this->host = get_option("host");

        add_action(
            "woocommerce_subscription_payment_complete",
            [$this, "handleOrderCompletion"],
            10,
            1
        );
		       add_action(
            "woocommerce_subscription_status_on-hold",
            [$this, "handleOrderCompletionHolde"],
            10,
            1
        );
        add_action(
            "woocommerce_subscription_status_active",
            [$this, "handleOrderCompletionUnHolde"],
            10,
            1
        );
        add_action(
            "woocommerce_subscription_status_cancelled",
            [$this, "handleOrderCompletionCancelled"],
            10,
            1
        );
        add_shortcode('fetch_sso_link', [$this, 'shortcodeApiCallHandler']);

    }

    public function handleOrderCompletion($subscription)
    {
        $last_order_id = $subscription->get_last_order();
		$order = wc_get_order($last_order_id);
        $orgUUID = get_option("orgId");
        $user_id = $order->get_customer_id();
        $EnhanceDomain = $order->get_meta('EnhanceDomain');
		$_enhance_plan = (int)$order->get_meta('_enhance_plan', true);

        if ($user_id) {
            $user = get_user_by("id", $user_id);

            if ($user) {
                $email = $user->user_email;
                $name = $user->display_name;
            }
        } else {
            $this->log($order, "User is not registered.");
        }

        $customerExists = $this->checkLogin($order, $name, $email, $orgUUID);
        if (!$customerExists) {
            $password = $this->generatePassword();

            $customerOrg = $this->createCustomer($order, $name);
            if (!$customerOrg) {
                return;
            }

            $loginUUID = $this->createLogin(
                $order,
                $name,
                $email,
                $password,
                $customerOrg
            );

            if ($loginUUID) {
                $this->assignRoles($order, $loginUUID, $customerOrg);
            }
                $user_id = $subscription->get_user_id(); // This gets the user ID from the subscription

				$subscription->update_meta_data('_enhance_subscription_loginUUID', $loginUUID);
				$subscription->save();
				$subscription->update_meta_data('_enhance_subscription_customerOrg', $customerOrg);
				$subscription->save();
                 update_user_meta($user_id, '_enhance_subscription_loginUUID', $loginUUID);
                 update_user_meta($user_id, '_enhance_subscription_customerOrg', $customerOrg);
        } else {
                $user_id = $subscription->get_user_id(); // This gets the user ID from the subscription

			$loginUUID = $customerExists['ownerId']; // Modify this based on your requirement
			$customerOrg = $customerExists['id']; // Modify this based on your requirement
			$subscription->update_meta_data('_enhance_subscription_loginUUID', $loginUUID);
			$subscription->save();
			$subscription->update_meta_data('_enhance_subscription_customerOrg', $customerOrg);
			$subscription->save();
           update_user_meta($user_id, '_enhance_subscription_loginUUID', $loginUUID);
         update_user_meta($user_id, '_enhance_subscription_customerOrg', $customerOrg);
        }
		
		$domainExists = $this->domainExists($order, $orgUUID, $EnhanceDomain);
		
		if (!$domainExists)
		{
				//$this->lastPlanId = $this->planCreation($order, $_enhance_plan, $customerOrg, $orgUUID);
				$planId = $this->planCreation($order, $_enhance_plan, $customerOrg, $orgUUID);	

			if ($planId)
			{
				$domainCreation = $this->domainCreation($order, $EnhanceDomain, $customerOrg, $planId);
				$subscription->update_meta_data('_enhance_subscription_plan_id', $planId);
				$subscription->save();		
			}

		}
		
    }

public function shortcodeApiCallHandler($SSOurl) {
    // Assuming you have a way to dynamically get these IDs
    $user_id = get_current_user_id();

    // Assuming you have a method to get these values
    $customerOrg = get_user_meta($user_id, '_enhance_subscription_customerOrg', true);
    $loginUUID = get_user_meta($user_id, '_enhance_subscription_loginUUID', true);
    $SSOurl = $this->createSSO($loginUUID, $customerOrg)

    ?>
    <a id="fetchSsoLink" href=<?php echo $SSOurl ?> target="_blank">Fetch SSO Link</a>
    <?php

}

public function handleOrderCompletionCancelled($subscription)
{
  

    $last_order_id = $subscription->get_last_order();
    $order = wc_get_order($last_order_id);

    if (!$order) {
        return; // Exit if the order could not be retrieved
    }

    $planId = $subscription->get_meta('_enhance_subscription_plan_id', true);
    $customerOrg = $subscription->get_meta('_enhance_subscription_customerOrg', true);


    if (!$planId || !$customerOrg) {
        return; // Exit if necessary data is missing
    }

		$url = $this->host . "/api/orgs/$customerOrg/subscriptions/$planId";

		$args = array(
			'method'    => 'DELETE',
			'headers'   => array(
				'Content-Type' => 'application/json',
				"Authorization" => "Bearer " . $this->apikey,

			),
		);

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    }
}
public function handleOrderCompletionUnHolde($subscription)
{
  

    $last_order_id = $subscription->get_last_order();
    $order = wc_get_order($last_order_id);

    if (!$order) {
        return; // Exit if the order could not be retrieved
    }

    $planId = $subscription->get_meta('_enhance_subscription_plan_id', true);
    $customerOrg = $subscription->get_meta('_enhance_subscription_customerOrg', true);


    // Ensure planId and customerOrg are valid before proceeding
    if (!$planId || !$customerOrg) {
        return; // Exit if necessary data is missing
    }

		$url = $this->host . "/api/orgs/$customerOrg/subscriptions/$planId";

		$body_data = array(
			'status' => 'active',
			'isSuspended' => false,
		);

		$body_json = wp_json_encode( $body_data );

		$args = array(
			'method'    => 'PATCH',
			'headers'   => array(
				'Content-Type' => 'application/json',
				"Authorization" => "Bearer " . $this->apikey,

			),
			'body'      => $body_json,
		);

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    }
}
public function handleOrderCompletionHolde($subscription)
{
  

    $last_order_id = $subscription->get_last_order();
    $order = wc_get_order($last_order_id);

    if (!$order) {
        return; // Exit if the order could not be retrieved
    }

    $planId = $subscription->get_meta('_enhance_subscription_plan_id', true);
    $customerOrg = $subscription->get_meta('_enhance_subscription_customerOrg', true);


    if (!$planId || !$customerOrg) {
        return; // Exit if necessary data is missing
    }

		$url = $this->host . "/api/orgs/$customerOrg/subscriptions/$planId";

		$body_data = array(
			'status' => 'active',
			'isSuspended' => true,
		);

		$body_json = wp_json_encode( $body_data );

		$args = array(
			'method'    => 'PATCH',
			'headers'   => array(
				'Content-Type' => 'application/json',
				"Authorization" => "Bearer " . $this->apikey,

			),
			'body'      => $body_json,
		);

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    }
}


    private function checkLogin($order, $name, $email, $orgUUID)
    {
        $existingCustomerId = $this->checkExistingCustomer($email, $orgUUID);

        if ($existingCustomerId !== null) {
            return $existingCustomerId;
        }
    }

    private function checkExistingCustomer($email, $orgUUID)
    {
        $response = $this->getRequest(
            "/api/orgs/$orgUUID/customers?email=$email"
        );

        if (!$response || !isset($response["items"])) {
            return null;
        }

		foreach ($response["items"] as $customer) {
			if ($customer["ownerEmail"] === $email) {
				return [
					"id" => $customer["id"],
					"ownerId" => $customer["ownerId"]
				];
			}
		}

        return null;
    }

    private function createSSO($loginUUID, $customerOrg)
    {
        $user_id = get_current_user_id();

        // Assuming you have a method to get these values
        $customerOrg = get_user_meta($user_id, '_enhance_subscription_customerOrg', true);
        $loginUUID = get_user_meta($user_id, '_enhance_subscription_loginUUID', true);
        $response = $this->getRequest(
            "/api/orgs/$customerOrg/members/$loginUUID/sso"
        );
        return $response;
    }

    private function createLogin($order, $name, $email, $password, $customerOrg)
    {
        $response = $this->postRequest("/api/logins?orgId=$customerOrg", [
            "email" => $email,
            "password" => $password,
            "name" => $name,
        ]);

        if (!$response) {
            return null;
        }
        return $response->id ?? null;
    }

    private function createCustomer($order, $name)
    {
        $response = $this->postRequest("/api/orgs/{$this->orgUUID}/customers", [
            "name" => $name,
        ]);
        if (!$response) {
            return null;
        }
        return $response->id ?? null;
    }

    private function assignRoles($order, $loginUUID, $customerOrg)
    {
        $response = $this->postRequest("/api/orgs/$customerOrg/members", [
            "loginId" => $loginUUID,
            "roles" => ["Owner"],
        ]);
        if (!$response) {
            return;
        }
    }

    private function postRequest($path, $body)
    {
        $response = wp_remote_post(esc_url_raw($this->host . $path), [
            "headers" => [
                "Authorization" => "Bearer {$this->apikey}",
                "Content-Type" => "application/json",
            ],
            "body" => json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response));
    }
    private function getRequest($path)
    {
        $response = wp_remote_get(esc_url_raw($this->host . $path), [
            "headers" => [
                "Authorization" => "Bearer {$this->apikey}",
                "Content-Type" => "application/json",
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }


    private function domainExists($order, $orgUUID, $EnhanceDomain)
    {
        $response = $this->getRequest("/api/orgs/{$orgUUID}/websites?search=$EnhanceDomain");

		if ($response !== null && isset($response['total']) && $response['total'] > 0) {
		}
    }
	
    private function domainCreation($order, $EnhanceDomain, $customerOrg, $planId)
    {
        $response = $this->postRequest("/api/orgs/$customerOrg/websites", [
            "domain" => $EnhanceDomain,
			"subscriptionId" => $planId,
        ]);

        if (!$response) {
            return null;
        }
        return $response->id ?? null;
    }
	
    private function planCreation($order, $_enhance_plan, $customerOrg, $orgUUID)
    {

        $response = $this->postRequest("/api/orgs/$orgUUID/customers/$customerOrg/subscriptions", [
            "planId" => $_enhance_plan
        ]);

        if (!$response) {
            return null;
        }
        return $response->id ?? null;
    }
	
private function planCreationId($subscription, $order) {
    $planId = $subscription->get_meta('_enhance_subscription_plan_id', true);
    $customerOrg = $subscription->get_meta('_enhance_subscription_customerOrg', true);
    $orgUUID = get_option("orgId");


    $response = $this->getRequest("/api/orgs/{$orgUUID}/customers/{$customerOrg}/subscriptions");

    if ($response && isset($response['items']) && is_array($response['items'])) {
        foreach ($response['items'] as $item) {
            if (isset($item['id']) && $item['id'] == $planId) {
                return $item['subscriberId'];
            } 
        }
    } else {
        $this->log($order, "Failed to fetch subscriptions or no subscriptions found.");
    }

    return null;
}


	
    private function generatePassword($length = 12)
    {
        $chars =
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+{}|:<>?';
        $password = "";
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    private function log($order, $note)
    {
        if ($order) {
            $order->add_order_note($note, true);
        } else {
            error_log($note);
        }
    }
}

new Subscription_Order_Handler();
