<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_ontraport extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_ontraport_form($form_fields, $service, $field_values)
	{
		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="password" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
			esc_attr( 'api_key_' . $service ),
			esc_attr( 'client_id_' . $service ),
			__( 'API key', 'rapidology' ),
			__( 'APP ID', 'rapidology' ),
			( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',
			( '' !== $field_values && isset( $field_values['client_id'] ) ) ? esc_attr( $field_values['client_id'] ) : '',
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false )
		);
		return $form_fields;
	}


	/**
	 * Retrieves the lists via OntraPort API and updates the data in DB.
	 * @return string
	 */
	function get_ontraport_lists($api_key, $app_id, $name)
	{
		$appid = $app_id;
		$key = $api_key;
		$lists = array();
		$list_id_array = array();

		// get sequences (lists)
		$req_type = "fetch_sequences";
		$postargs = "appid=" . $appid . "&key=" . $key . "&reqType=" . $req_type;
		$request = "https://api.ontraport.com/cdata.php";
		$result = $this->ontraport_request($postargs, $request);
		$lists_array = $this->xml_to_array($result);
		$lists_id = simplexml_load_string($result);

		foreach ($lists_id->sequence as $value) {
			$list_id_array[] = (int)$value->attributes()->id;
		}

		if (is_array($lists_array)) {
			$error_message = 'success';
			if (!empty($lists_array['sequence'])) {
				$sequence_array = is_array($lists_array['sequence'])
					? $lists_array['sequence']
					: $lists_array;

				$i = 0;

				foreach ($sequence_array as $id => $list_name) {
					$lists[$list_id_array[$i]]['name'] = $list_name;

					// we cannot get amount of subscribers for each sequence due to API limitations, so set it to 0.
					$lists[$list_id_array[$i]]['subscribers_count'] = 0;

					$lists[$list_id_array[$i]]['growth_week'] = $this->calculate_growth_rate('ontraport_' . $list_id_array[$i]);
					$i++;
				}
			}
			$this->update_account('ontraport', $name, array(
				'api_key' => esc_html($api_key),
				'client_id' => esc_html($app_id),
				'lists' => $lists,
				'is_authorized' => esc_html('true'),
			));
		} else {
			$error_message = $lists_array;
		}

		return $error_message;
	}

	public function subscribe_ontraport($app_id, $api_key, $name, $email, $list_id, $last_name = '')
	{
// Construct contact data in XML format
		$data = <<<STRING
<contact>
<Group_Tag name="Contact Information">
<field name="First Name">
STRING;
		$data .= sanitize_text_field( $name );
		$data .= <<<STRING
</field>
<field name="Last Name">
STRING;
		$data .= sanitize_text_field( $last_name );
		$data .= <<<STRING
</field>
<field name="Email">
STRING;
		$data .= $email;
		$data .= <<<STRING
</field>
</Group_Tag>
<Group_Tag name="Sequences and Tags">
<field name="Contact Tags"></field>
<field name="Sequences">*/*
STRING;
		$data .= sanitize_text_field( $list_id );
		$data .= <<<STRING
*/*</field>
</Group_Tag>
</contact>
STRING;

		$data = urlencode( urlencode( $data ) );
		$reqType = "add";
		$postargs = sprintf(
			'appid=%1$s&key=%2$s&return_id=1&reqType=%3$s&data=%4$s',
			sanitize_text_field( $app_id ),
			sanitize_text_field( $api_key ),
			sanitize_text_field( $reqType ),
			$data
		);

		$result = $this->ontraport_request( $postargs );
		$user_array = $this->xml_to_array( $result );

		if ( isset( $user_array['status'] ) && 'Success' == $user_array['status'] ) {
			$error_message = 'success';
		} else {
			$error_message = __( 'Error occured during subscription', 'bloom' );
		}
		return $error_message;
	}
	/**
	 * Performs the request to OntraPort API and handles the response
	 * @return xml
	 */
	public function ontraport_request($postargs)
	{
		if (!function_exists('curl_init')) {
			$response = __('curl_init is not defined ', 'rapidology');
		} else {
			$response = '';
			$httpCode = '';
		// Get cURL resource
			$curl = curl_init();
		// Set some options
			curl_setopt_array($curl, array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HEADER => false,
				CURLOPT_URL => "https://api.ontraport.com/cdata.php",
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $postargs,
				CURLOPT_SSL_VERIFYPEER => false, //we need this option since we perform request to https
			));
		// Send the request & save response to $resp
			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		// Close request to clear up some resources
			curl_close($curl);
			if (200 == $httpCode) {
				$response = $response;
			} else {
				$response = $httpCode;
			}
		}

		return $response;
	}
}