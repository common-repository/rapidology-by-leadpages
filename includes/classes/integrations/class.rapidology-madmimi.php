<?php

if ( ! class_exists( 'RAD_Dashboard' ) ) {
	require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php' );
}

class rapidology_madmimi extends RAD_Rapidology
{

	public function __contruct(){

	}

	public function draw_madmimi_form($form_fields, $service, $field_values){

		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="password" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
			esc_attr( 'username_' . $service ),
			esc_attr( 'api_key_' . $service ),
			__( 'Username', 'rapidology' ),
			__( 'API key', 'rapidology' ),
			( '' !== $field_values && isset( $field_values['username'] ) ) ? esc_html( $field_values['username'] ) : '',
			( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_html( $field_values['api_key'] ) : '',
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false
			)
		);
		return $form_fields;
	}

	/**
	 * Retrieves the lists via Mad Mimi API and updates the data in DB.
	 * @return string
	 */
	public function get_madmimi_lists( $username, $api_key, $name ) {
		$lists = array();

		$request_url = esc_url_raw( 'https://api.madmimi.com/audience_lists/lists.json?username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30 ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ) {
			$theme_response = json_decode( wp_remote_retrieve_body( $theme_request ), true );
			if ( ! empty( $theme_response ) ) {
				$error_message = 'success';

				foreach ( $theme_response as $list_data ) {
					$lists[ $list_data['id'] ]['name']              = $list_data['name'];
					$lists[ $list_data['id'] ]['subscribers_count'] = $list_data['list_size'];
					$lists[ $list_data['id'] ]['growth_week']       = $this->calculate_growth_rate( 'madmimi_' . $list_data['id'] );
				}

				$this->update_account( 'madmimi', $name, array(
					'api_key'       => esc_html( $api_key ),
					'username'      => esc_html( $username ),
					'lists'         => $lists,
					'is_authorized' => esc_html( 'true' ),
				) );

			} else {
				$error_message = __( 'Please make sure you have at least 1 list in your account and try again', 'rapidology' );
			}
		} else {
			$error_message = $this->get_error_message( $theme_request, $response_code, null );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Mad Mimi list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_madmimi( $username, $api_key, $list_id, $email, $name = '', $last_name = '' ) {
		// check whether the user already subscribed
		$check_user_url = esc_url_raw( 'https://api.madmimi.com/audience_members/' . rawurlencode( $email ) . '/lists.json?username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

		$check_user_request = wp_remote_get( $check_user_url, array( 'timeout' => 30 ) );

		$check_user_response_code = wp_remote_retrieve_response_code( $check_user_request );

		if ( ! is_wp_error( $check_user_request ) && $check_user_response_code == 200 ) {
			$check_user_response = json_decode( wp_remote_retrieve_body( $check_user_request ), true );

			// if user is not subscribed yet - try to subscribe
			if ( empty( $check_user_response ) ) {
				$request_url = esc_url_raw( 'https://api.madmimi.com/audience_lists/' . $list_id . '/add?email=' . rawurlencode( $email ) . '&first_name=' . $name . '&last_name=' . $last_name . '&username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

				$theme_request = wp_remote_post( $request_url, array( 'timeout' => 30 ) );

				$response_code = wp_remote_retrieve_response_code( $theme_request );

				if ( ! is_wp_error( $theme_request ) && $response_code == 200 ) {
					$error_message = 'success';
				} else {
					if ( is_wp_error( $theme_request ) ) {
						$error_message = $theme_request->get_error_message();
					} else {
						switch ( $response_code ) {
							case '401' :
								$error_message = __( 'Invalid Username or API key', 'rapidology' );
								break;
							case '400' :
								$error_message = wp_remote_retrieve_body( $theme_request );
								break;

							default :
								$error_message = $response_code;
								break;
						}
					}
				}
			} else {
				$error_message = __( 'Already subscribed', 'rapidology' );
			}
		} else {
			// TODO: Figure out how to handle this better, since $theme_request and $response_code are undef here
			$error_message = $this->get_error_message( $theme_request, $response_code, null);
		}

		return $error_message;
	}
}
