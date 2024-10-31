<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_campaign_monitor extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_campaign_monitor_form($form_fields, $service, $field_values)
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
	 * Retrieves the lists via Campaign Monitor API and updates the data in DB.
	 * @return string
	 */
	function get_campaign_monitor_lists( $api_key, $name ) {
		require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_clients.php' );
		require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_lists.php' );

		$auth = array(
			'api_key' => $api_key,
		);

		$request_url    = esc_url_raw( 'https://api.createsend.com/api/v3.1/clients.json?pretty=true' );
		$all_clients_id = array();
		$all_lists      = array();

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'rapidology' );
		}

		// Get cURL resource
		$curl = curl_init();
		// Set some options
		curl_setopt_array( $curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL            => $request_url,
			CURLOPT_SSL_VERIFYPEER => false, //we need this option since we perform request to https
			CURLOPT_USERPWD        => $api_key . ':x'
		) );
		// Send the request & save response to $resp
		$resp     = curl_exec( $curl );
		$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		// Close request to clear up some resources
		curl_close( $curl );

		$clients_array = json_decode( $resp, true );

		if ( '200' == $httpCode ) {
			$error_message = 'success';

			foreach ( $clients_array as $client => $client_details ) {
				$all_clients_id[] = $client_details['ClientID'];
			}

			if ( ! empty( $all_clients_id ) ) {
				foreach ( $all_clients_id as $client ) {
					$wrap       = new CS_REST_Clients( $client, $auth );
					$lists_data = $wrap->get_lists();

					foreach ( $lists_data->response as $list => $single_list ) {
						$all_lists[ $single_list->ListID ]['name'] = $single_list->Name;

						$wrap_stats                                             = new CS_REST_Lists( $single_list->ListID, $auth );
						$result_stats                                           = $wrap_stats->get_stats();
						$all_lists[ $single_list->ListID ]['subscribers_count'] = sanitize_text_field( $result_stats->response->TotalActiveSubscribers );
						$all_lists[ $single_list->ListID ]['growth_week']       = sanitize_text_field( $this->calculate_growth_rate( 'campaign_monitor_' . $single_list->ListID ) );
					}
				}
			}

			$this->update_account( 'campaign_monitor', sanitize_text_field( $name ), array(
				'api_key'       => sanitize_text_field( $api_key ),
				'lists'         => $all_lists,
				'is_authorized' => 'true',
			) );
		} else {
			if ( '401' == $httpCode ) {
				$error_message = __( 'invalid API key', 'rapidology' );
			} else {
				$error_message = $httpCode;
			}
		}

		return $error_message;
	}

	/**
	 * Subscribes to Campaign Monitor list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_campaign_monitor( $api_key, $email, $list_id, $name = '' ) {
		require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_subscribers.php' );
		$auth          = array(
			'api_key' => $api_key,
		);
		$wrap          = new CS_REST_Subscribers( $list_id, $auth );
		$is_subscribed = $wrap->get( $email );

		if ( $is_subscribed->was_successful() ) {
			$error_message = __( 'Already subscribed', 'rapidology' );
		} else {
			$result = $wrap->add( array(
				'EmailAddress' => $email,
				'Name'         => $name,
				'Resubscribe'  => false,
			) );
			if ( $result->was_successful() ) {
				$error_message = 'success';
			} else {
				$error_message = $result->response->message;
			}
		}

		return $error_message;
	}
}