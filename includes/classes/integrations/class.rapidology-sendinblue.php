<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_sendinblue extends RAD_Rapidology
{

	public function __contruct()
	{

	}


	public function draw_sendinblue_form($form_fields, $service, $field_values)
	{
		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%2$s</label>
						<input type="password" value="%3$s" id="%1$s">%4$s
					</div>',
			esc_attr( 'api_key_' . $service ),
			__( 'API key', 'rapidology' ),
			( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false
			)
		);
		return $form_fields;
	}
	/**
	 * Retrieves the lists via Sendinblue API and updates the data in DB.
	 * @return string
	 */
	function get_sendinblue_lists($api_key, $name)
	{
		$lists = array();

		if (!function_exists('curl_init')) {
			return __('curl_init is not defined ', 'rapidology');
		}

		if (!class_exists('Mailin')) {
			require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/sendinblue-v2.0/mailin.php');
		}

		$mailin = new Mailin('https://api.sendinblue.com/v2.0', $api_key);
		$page = 1;
		$page_limit = 50;
		$all_lists = array();
		$need_request = true;

		while (true == $need_request) {
			$lists_array = $mailin->get_lists($page, $page_limit);
			$all_lists = array_merge($all_lists, $lists_array);
			if (50 > count($lists_array)) {
				$need_request = false;
			} else {
				$page++;
			}
		}

		if (!empty($all_lists)) {
			if (isset($all_lists['code']) && 'success' === $all_lists['code']) {
				$error_message = 'success';

				if (!empty($all_lists['data']['lists'])) {
					foreach ($all_lists['data']['lists'] as $single_list) {
						$lists[$single_list['id']]['name'] = $single_list['name'];

						$total_contacts = isset($single_list['total_subscribers']) ? $single_list['total_subscribers'] : 0;
						$lists[$single_list['id']]['subscribers_count'] = $total_contacts;

						$lists[$single_list['id']]['growth_week'] = $this->calculate_growth_rate('sendinblue_' . $single_list['id']);
					}
				}

				$this->update_account('sendinblue', $name, array(
					'api_key' => esc_html($api_key),
					'lists' => $lists,
					'is_authorized' => esc_html('true'),
				));
			} else {
				$error_message = $all_lists['message'];
			}
		} else {
			$error_message = __('Invalid API key or something went wrong during Authorization request', 'rapidology');
		}

		return $error_message;
	}

	/**
	 * Subscribes to Sendinblue list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_sendinblue($api_key, $email, $list_id, $name, $last_name = '')
	{
		if (!function_exists('curl_init')) {
			return __('curl_init is not defined ', 'rapidology');
		}

		if (!class_exists('Mailin')) {
			require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/sendinblue-v2.0/mailin.php');
		}

		$mailin = new Mailin('https://api.sendinblue.com/v2.0', $api_key);
		$user = $mailin->get_user($email);
		if ('failure' == $user['code']) {
			$attributes = array(
				"NAME" => $name,
				"SURNAME" => $last_name,
			);
			$blacklisted = 0;
			$listid = array($list_id);
			$listid_unlink = array();
			$blacklisted_sms = 0;

			$result = $mailin->create_update_user($email, $attributes, $blacklisted, $listid, $listid_unlink, $blacklisted_sms);

			if ('success' == $result['code']) {
				$error_message = 'success';
			} else {
				if (!empty($result['message'])) {
					$error_message = $result['message'];
				} else {
					$error_message = __('Unknown error', 'rapidology');
				}
			}
		} else {
			$error_message = __('Already subscribed', 'rapidology');
		}

		return $error_message;
	}
}