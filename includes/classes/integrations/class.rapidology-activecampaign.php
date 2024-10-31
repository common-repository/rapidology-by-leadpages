<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_activecampaign extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_activecampaign_form($form_fields, $service, $field_values)
	{
		$form_fields .= sprintf('
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="text" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="text" value="%6$s" id="%2$s">%7$s
					</div>
					',
			esc_attr('url_'.$service),#1
			esc_attr('api_key_'.$service),#2
			__('API URL', 'rapidology'),#3
			__('API Key', 'rapidology'),#4
			( '' !== $field_values && isset( $field_values['url'] ) ) ? esc_attr( $field_values['url'] ) : '',#5
			( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',#6
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false
			)#7
		);
		return $form_fields;
	}
	/**
	 * get Active Campaign forms
	 * @return string
	 */

	function get_active_campagin_forms($url, $api_key, $name){
		require_once(RAD_RAPIDOLOGY_PLUGIN_DIR .'subscription/activecampaign/class.activecampagin.php');
		$ac_requests = new rapidology_active_campagin($url, $api_key);
		$forms = $ac_requests->rapidology_get_ac_forms();
		if($forms['status'] == 'error'){
			$error_message = $forms['message'];
			return $error_message;
		}

		$verfied_forms = $ac_requests->rapidology_get_ac_html($forms);

		foreach($verfied_forms as $form){
			$form_list[$form['id']]['name'] = $form['name'];
			$form_list[$form['id']]['subscribers_count'] = $form['subscriptions'];
			$form_list[$form['id']]['growth_week'] = sanitize_text_field($this->calculate_growth_rate('activecampagin' . $form['id']));
			$form_list[$form['id']]['list_ids'] = $form['lists'];
		}
		$this->update_account('activecampaign', sanitize_text_field($name), array(
			'url' 			=> $url,
			'api_key' 		=> $api_key,
			'lists' 		=> $form_list,
			'is_authorized' => 'true',
		));
		$error_message = 'success';
		return $error_message;

	}

	/**
	 * submit user to form and lists active campaign
	 * @return string
	 */

	function subscribe_active_campaign($url, $api_key, $first_name , $last_name, $email, $lists, $form_id){
		require_once(RAD_RAPIDOLOGY_PLUGIN_DIR .'subscription/activecampaign/class.activecampagin.php');
		$ac_requests = new rapidology_active_campagin($url, $api_key);
		$result = $ac_requests->rapidology_submit_ac_form($form_id, $first_name, $last_name, $email, $lists, $url );
		$error_message = $result;
		return $error_message['message'];

	}
}