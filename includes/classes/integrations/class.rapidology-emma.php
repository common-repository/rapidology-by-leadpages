<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_emma extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_emma_form($form_fields, $service, $field_values)
	{
		$form_fields .= sprintf( '
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
			esc_attr( 'api_key_' . $service ),
			esc_attr( 'client_id_' . $service ),
			esc_attr( 'username_' . $service ),
			__( 'Public API Key', 'rapidology' ),
			__( 'Private API key', 'rapidology' ),
			__( 'Account ID', 'rapidology' ),
			( '' !== $field_values && isset( $field_values['api_key_'] ) ) ? esc_html( $field_values['api_key_'] ) : '',
			( '' !== $field_values && isset( $field_values['client_id_'] ) ) ? esc_html( $field_values['client_id_'] ) : '',
			( '' !== $field_values && isset( $field_values['username_'] ) ) ? esc_html( $field_values['username_'] ) : '',
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false
			)
		);
		return $form_fields;
	}

	function get_emma_groups( $public_key, $private_key, $account_id, $name ) {
		if ( ! class_exists( 'Emma_Rapidology' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/emma/Emma.php' );
		}

		$emma = new Emma_Rapidology( $account_id, $public_key, $private_key, false ); //true set for debug
		try {
			$error_message = 'success';
			$response      = $emma->myGroups();
			$response      = json_decode( $response );

			$all_lists = array();
			foreach ($response as $obj){
				$all_lists[$obj->member_group_id]['name'] = $obj->group_name;
				$all_lists[$obj->member_group_id]['subscribers_count'] = sanitize_text_field($obj->active_count);
				$all_lists[$obj->member_group_id]['growth_week'] = sanitize_text_field( $this->calculate_growth_rate( 'emma_' . $obj->account_id ) );
			}
			$this->update_account( 'emma', sanitize_text_field( $name ), array(
				'api_key'       => sanitize_text_field( $public_key ),
				'client_id'     => sanitize_text_field( $private_key ),
				'username'      => sanitize_text_field( $account_id ),
				'lists'         => $all_lists,
				'is_authorized' => 'true',
			) );

			return $error_message;
		} catch ( exception $e ) {
			$error_message = $e;

			return $error_message;
		}


	}

	function emma_member_subscribe($public_key, $private_key, $account_id, $email, $list_id, $first_name='', $last_name=''){
		if(!class_exists('Emma_Rapidology')){
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/emma/Emma.php' );
		}
		//TODO add some checking into see if they are already part of the group they are opting into skilled because it adds extra seemingly unneed processing
		$emma = new Emma_Rapidology( $account_id, $public_key, $private_key, false ); //true set for debug
		//arguments to pass to send to emma to sign up user
		$args = array(
			'email'     => $email,
			'group_ids' => array(
				$list_id
			),
			'fields' => array(
				"first_name" => $first_name,
				"last_name" => $last_name
			)
		);
		try {
			$emma->membersAddSingle( $args );

			return $error_message = "success";
		} catch ( exception $e ) {
			$error_message = $e;

			return $error_message;
		}

	}
}