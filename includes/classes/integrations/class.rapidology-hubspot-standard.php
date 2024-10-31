<?php

if ( ! class_exists( 'RAD_Dashboard' ) ) {
	require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php' );
}

class rapidology_hubspot_standard extends RAD_Rapidology
{

	public function __contruct(){

	}

	public function draw_hubspot_standard_form($form_fields, $service, $field_values){
		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="text" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
			esc_attr('username_' . $service),#1
			esc_attr( 'api_key_' . $service ),#2
			__( 'Account Id', 'rapidology'),#3
			__( 'API key', 'rapidology' ),#4
			( '' !== $field_values && isset( $field_values['username'] ) ) ? esc_attr( $field_values['username'] ) : '',#5
			( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',#6
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false#7
			)
		);

		return $form_fields;
	}

	/**
	 * @return array
	 * @description get all forms that are valid for rapdiology
	 */

	public function get_hubspot_forms($account_id, $api_key, $name ){

		if(!class_exists('HubSpot_Forms_Rapidology')){
			include_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/hubspot/class.forms.php' );
		}

		$key = 'KEY '.$api_key;
		$forms      	= new HubSpot_Forms_Rapidology($api_key);
		$all_forms		= $forms->get_forms();
		//array to hold valid forms to return
		$valid_forms	= array();
		//only fields accepted for rapidology, check against and make sure other forms are not required
		$accepted_flds	= array(
			'firstname',
			'lastname',
			'email'
		);

		foreach ($all_forms as $form){
			$invalid_form = false;
			if($form->captchaEnabled == 1){
				$invalid_form = true;
			}
			foreach($form->fields as $field){
				if(!in_array($field->name, $accepted_flds) && $field->required  == 1){
					$invalid_form = true;
					break;
				}
			}
			if(!$invalid_form){
				$valid_forms[$form->guid]['name'] = $form->name;
				$valid_forms[$form->guid]['subscribers_count'] = 0; //set to 0 as there is no inital subscriber count for forms
				$valid_forms[$form->guid]['growth_week'] = sanitize_text_field($this->calculate_growth_rate('hubspot-standard_' . $form->guid));
			}
		}
		if(sizeof($valid_forms) > 0) {
			$this->update_account('hubspot-standard', sanitize_text_field($name), array(
				'account_id' => $account_id,
				'api_key' => $api_key,
				'lists' => $valid_forms,
				'is_authorized' => 'true',
			));
			$error_message = 'success';
		}else{
			$error_message = 'You do not appear to have any valid lists';
		}
		return $error_message;
	}


	/**
	 * @param $api_key
	 * @param $account_id
	 * @param $email
	 * @param $list_id
	 * @param $name
	 * @param $last_name
	 * @param $post_name
	 * @param $cookie
	 * @return string
	 */
	public function submit_hubspot_form($api_key, $account_id,  $email, $list_id, $name, $last_name, $post_name, $cookie){

		if(!class_exists('HubSpot_Forms_Rapidology')){
			include_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/hubspot/class.forms.php' );
		}

		$names_array = rapidology_name_splitter($name, $last_name);
		$name = $names_array['name'];
		$last_name = $names_array['last_name'];
		$submitted_form_fields = array(
			'firstname'	=> $name,
			'lastname'	=> $last_name,
			'email'		=> $email
		);
		$context = array(
			'hutk' => $cookie,
			'ipAddress'	=> $_SERVER['REMOTE_ADDR'],
			'pageUrl'	=> $_SERVER['HTTP_HOST'],
			'pageName'	=> $post_name
		);
		$forms      	= new HubSpot_Forms_Rapidology($api_key);
		$submitted_form = $forms->submit_form($account_id, $list_id, $submitted_form_fields, $context);
		if($submitted_form['error']){
			$error_message = 'There was an error submitting your form';
		}else{
			$error_message = 'success';
		}
		return $error_message;
	}
}



?>