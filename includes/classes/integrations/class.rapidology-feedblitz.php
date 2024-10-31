<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_feedblitz extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_feedbliz_form($form_fields, $service, $field_values)
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
	 * Retrieves the lists via feedblitz API and updates the data in DB.
	 * @return string
	 */
	function get_feedblitz_lists( $api_key, $name ) {
		$lists = array();

		$request_url = esc_url_raw( 'https://api.feedblitz.com/f.api/syndications?key=' . $api_key );

		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30, 'sslverify' => false ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ) {
			$theme_response = $this->xml_to_array( wp_remote_retrieve_body( $theme_request ) );

			if ( ! empty( $theme_response ) ) {
				if ( 'ok' == $theme_response['rsp']['@attributes']['stat'] ) {
					$error_message = 'success';
					$lists_array   = $theme_response['syndications']['syndication'];

					if ( ! empty( $lists_array ) ) {
						foreach ( $lists_array as $list_data ) {
							$lists[ $list_data['id'] ]['name']              = $list_data['name'];
							$lists[ $list_data['id'] ]['subscribers_count'] = $list_data['subscribersummary']['subscribers'];

							$lists[ $list_data['id'] ]['growth_week'] = $this->calculate_growth_rate( 'feedblitz_' . $list_data['id'] );
						}
					}

					$this->update_account( 'feedblitz', $name, array(
						'api_key'       => esc_html( $api_key ),
						'lists'         => $lists,
						'is_authorized' => esc_html( 'true' ),
					) );
				} else {
					$error_message = isset( $theme_response['rsp']['err']['@attributes']['msg'] ) ? $theme_response['rsp']['err']['@attributes']['msg'] : __( 'Unknown error', 'rapidology' );
				}

			} else {
				$error_message = __( 'empty response', 'rapidology' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				$error_message = $response_code;
			}
		}

		return $error_message;

	}

	/**
	 * Subscribes to feedblitz list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_feedblitz( $api_key, $list_id, $name, $email = '', $last_name = '' ) {
		$request_url   = esc_url_raw( 'https://www.feedblitz.com/f?SimpleApiSubscribe&key=' . $api_key . '&email=' . rawurlencode( $email ) . '&listid=' . $list_id . '&FirstName=' . $name . '&LastName=' . $last_name );
		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30, 'sslverify' => false ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ) {
			$theme_response = $this->xml_to_array( wp_remote_retrieve_body( $theme_request ) );
			if ( ! empty( $theme_response ) ) {
				if ( 'ok' == $theme_response['rsp']['@attributes']['stat'] ) {
					if ( empty( $theme_response['rsp']['success']['@attributes']['msg'] ) ) {
						$error_message = 'success';
					} else {
						$error_message = $theme_response['rsp']['success']['@attributes']['msg'];
					}
				} else {
					$error_message = isset( $theme_response['rsp']['err']['@attributes']['msg'] ) ? $theme_response['rsp']['err']['@attributes']['msg'] : __( 'Unknown error', 'rapidology' );
				}
			} else {
				$error_message = __( 'empty response', 'rapidology' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				$error_message = $response_code;
			}
		}

		return $error_message;
	}


}