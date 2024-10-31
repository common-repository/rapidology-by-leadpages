<?php

if ( ! class_exists( 'RAD_Dashboard' ) ) {
	require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php' );
}

class rapidology_icontact extends RAD_Rapidology
{

	public function __contruct(){

	}

	public function draw_icontact_form($form_fields, $service, $field_values){

		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row">%1$s</div>',
			sprintf( '
						<div class="rad_dashboard_account_row">
							<label for="%1$s">%4$s</label>
							<input type="password" value="%7$s" id="%1$s">%10$s
						</div>
						<div class="rad_dashboard_account_row">
							<label for="%2$s">%5$s</label>
							<input type="password" value="%8$s" id="%2$s">%10$s
						</div>
						<div class="rad_dashboard_account_row">
							<label for="%3$s">%6$s</label>
							<input type="password" value="%9$s" id="%3$s">%10$s
						</div>',
				esc_attr( 'client_id_' . $service ),
				esc_attr( 'username_' . $service ),
				esc_attr( 'password_' . $service ),
				__( 'App ID', 'rapidology' ),
				__( 'Username', 'rapidology' ),
				__( 'Password', 'rapidology' ),
				( '' !== $field_values && isset( $field_values['client_id'] ) ) ? esc_html( $field_values['client_id'] ) : '',
				( '' !== $field_values && isset( $field_values['username'] ) ) ? esc_html( $field_values['username'] ) : '',
				( '' !== $field_values && isset( $field_values['password'] ) ) ? esc_html( $field_values['password'] ) : '',
				RAD_Rapidology::generate_hint( sprintf(
					'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
					__( 'Click here for more information', 'rapidology' )
				), false )
			)
		);
		return $form_fields;
	}

	/**
	 * Retrieves the lists via iContact API and updates the data in DB.
	 * @return string
	 */
	function get_icontact_lists( $app_id, $username, $password, $name ) {
		$lists      = array();
		$account_id = '';
		$folder_id  = '';

		$request_account_id_url = esc_url_raw( 'https://app.icontact.com/icp/a/' );

		$account_data = $this->icontacts_remote_request( $request_account_id_url, $app_id, $username, $password );

		if ( is_array( $account_data ) ) {
			$account_id = $account_data['accounts'][0]['accountId'];

			if ( '' !== $account_id ) {
				$request_folder_id_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c' );

				$folder_data = $this->icontacts_remote_request( $request_folder_id_url, $app_id, $username, $password );

				if ( is_array( $folder_data ) ) {
					$folder_id = $folder_data['clientfolders'][0]['clientFolderId'];

					$request_lists_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/lists' );
					$lists_data        = $this->icontacts_remote_request( $request_lists_url, $app_id, $username, $password );

					if ( is_array( $lists_data ) ) {
						$error_message = 'success';
						foreach ( $lists_data['lists'] as $single_list ) {
							$lists[ $single_list['listId'] ]['name']       = $single_list['name'];
							$lists[ $single_list['listId'] ]['account_id'] = $account_id;
							$lists[ $single_list['listId'] ]['folder_id']  = $folder_id;

							//request for subscribers
							$request_contacts_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts?status=total&listId=' . $single_list['listId'] );
							$subscribers_data     = $this->icontacts_remote_request( $request_contacts_url, $app_id, $username, $password );
							$total_subscribers    = isset( $subscribers_data['total'] ) ? $subscribers_data['total'] : 0;

							$lists[ $single_list['listId'] ]['subscribers_count'] = $total_subscribers;
							$lists[ $single_list['listId'] ]['growth_week']       = $this->calculate_growth_rate( 'icontact_' . $single_list['listId'] );
						}

						$this->update_account( 'icontact', $name, array(
							'client_id'     => esc_html( $app_id ),
							'username'      => esc_html( $username ),
							'password'      => esc_html( $password ),
							'lists'         => $lists,
							'is_authorized' => esc_html( 'true' ),
						) );
					} else {
						$error_message = $lists_data;
					}
				} else {
					$error_message = $folder_data;
				}
			} else {
				$error_message = __( 'Account ID is not defined', 'rapidology' );
			}
		} else {
			$error_message = $account_data;
		}

		return $error_message;
	}

	/**
	 * Subscribes to iContact list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_icontact( $app_id, $username, $password, $folder_id, $account_id, $list_id, $email, $name = '', $last_name = '' ) {
		$check_subscription_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts?email=' . rawurlencode( $email ) );
		$is_subscribed          = $this->icontacts_remote_request( $check_subscription_url, $app_id, $username, $password );
		if ( is_array( $is_subscribed ) ) {
			if ( empty( $is_subscribed['contacts'] ) ) {
				$add_body           = '[{
					"email":"' . $email . '",
					"firstName":"' . $name . '",
					"lastName":"' . $last_name . '",
					"status":"normal"
				}]';
				$add_subscriber_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts/' );

				$added_account = $this->icontacts_remote_request( $add_subscriber_url, $app_id, $username, $password, true, $add_body );
				if ( is_array( $added_account ) ) {
					if ( ! empty( $added_account['contacts'][0]['contactId'] ) ) {
						$map_contact        = '[{
							"contactId":' . $added_account['contacts'][0]['contactId'] . ',
							"listId":' . $list_id . ',
							"status":"normal"
						}]';
						$map_subscriber_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/subscriptions/' );

						$add_to_list = $this->icontacts_remote_request( $map_subscriber_url, $app_id, $username, $password, true, $map_contact );
					}
					$error_message = 'success';
				} else {
					$error_message = $added_account;
				}
			} else {
				$error_message = __( 'Already subscribed', 'rapidology' );
			}
		} else {
			$error_message = $is_subscribed;
		}

		return $error_message;
	}

	/**
	 * Executes remote request to iContacts API
	 * @return string
	 */
	function icontacts_remote_request( $request_url, $app_id, $username, $password, $is_post = false, $body = '' ) {
		if ( false === $is_post ) {
			$theme_request = wp_remote_get( $request_url, array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'Api-Version'  => '2.0',
					'Api-AppId'    => $app_id,
					'Api-Username' => $username,
					'API-Password' => $password,
				)
			) );
		} else {
			$theme_request = wp_remote_post( $request_url, array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'Api-Version'  => '2.0',
					'Api-AppId'    => $app_id,
					'Api-Username' => $username,
					'API-Password' => $password,
				),
				'body'    => $body,
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $theme_request );
		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ) {
			$theme_response = wp_remote_retrieve_body( $theme_request );
			if ( ! empty( $theme_response ) ) {
				$error_message = json_decode( wp_remote_retrieve_body( $theme_request ), true );
			} else {
				$error_message = __( 'empty response', 'rapidology' );
			}
		} else {
			$error_map     = array(
				"401" => 'Invalid App ID, Username or Password',
			);
			$error_message = $this->get_error_message( $theme_request, $response_code, $error_map );
		}

		return $error_message;
	}
}