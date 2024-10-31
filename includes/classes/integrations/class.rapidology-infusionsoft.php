<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_infusionsoft extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_infusionsoft_form($form_fields, $service, $field_values)
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
			__( 'API Key', 'rapidology' ),
			__( 'Application name', 'rapidology' ),
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
	 * Retrieves the lists via Infusionsoft API and updates the data in DB.
	 * @return string
	 */

	function get_infusionsoft_lists( $app_id, $api_key, $name ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'rapidology' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		$lists = array();

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $app_id, $api_key, 'throw' );
		} catch ( iSDKException $e ) {
			$error_message = $e->getMessage();
		}

		if ( empty( $error_message ) ) {
			$need_request = true;
			$page         = 0;
			$all_lists    = array();

			while ( true == $need_request ) {
				$error_message = 'success';
				$lists_data = $infusion_app->dsQuery(
					'ContactGroup',
					1000,
					$page,
					array( 'Id' => '%' ),
					array( 'Id', 'GroupName' )
				);
				$all_lists     = array_merge( $all_lists, $lists_data );

				if ( 1000 > count( $lists_data ) ) {
					$need_request = false;
				} else {
					$page ++;
				}
			}
		}

		if ( ! empty( $all_lists ) ) {
			foreach ( $all_lists as $list ) {
				$group_query                               = '%' . $list['Id'] . '%';
				$subscribers_count                         = $infusion_app->dsCount( 'Contact', array( 'Groups' => $group_query ) );
				$lists[ $list['Id'] ]['name']              = sanitize_text_field( $list['GroupName'] );
				$lists[ $list['Id'] ]['subscribers_count'] = sanitize_text_field( $subscribers_count );
				$lists[ $list['Id'] ]['growth_week']       = sanitize_text_field( $this->calculate_growth_rate( 'infusionsoft_' . $list['Id'] ) );
			}

			$this->update_account( 'infusionsoft', sanitize_text_field( $name ), array(
				'lists'         => $lists,
				'api_key'       => sanitize_text_field( $api_key ),
				'client_id'     => sanitize_text_field( $app_id ),
				'is_authorized' => 'true',
			) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Infusionsoft list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_infusionsoft( $api_key, $app_id, $list_id, $email, $name = '', $last_name = '' ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'rapidology' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $app_id, $api_key, 'throw' );
		} catch ( iSDKException $e ) {
			$error_message = $e->getMessage();
		}


		$contact_details = array(
			'FirstName' => $name,
			'LastName'  => $last_name,
			'Email'     => $email,
		);
		$new_contact_id = $infusion_app->addWithDupCheck($contact_details, $checkType = 'Email');
		$infusion_app->optIn($contact_details['Email']);
		$response = $infusion_app->grpAssign( $new_contact_id, $list_id );
		if($response) {
			$error_message = 'success';
		}else{
			$error_message = esc_html__( 'Already In List', 'rapidology' );
		}


		return $error_message;
	}

}