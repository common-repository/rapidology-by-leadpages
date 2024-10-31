<?php

if ( ! class_exists( 'RAD_Dashboard' ) ) {
	require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php' );
}

class rapidology_salesforce extends RAD_Rapidology{

	public function __contruct(){

	}

	public function draw_salesforce_form($form_fields, $service, $field_values = ''){
		//hide verision # and hardcoded it to 34.0
		$form_fields .= sprintf('
					<div class="rad_dashboard_account_row">
						<label for="%1$s">%8$s</label>
						<input type="text" value="%15$s" id="%1$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%3$s">%10$s</label>
						<input type="password" value="%17$s" id="%3$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%4$s">%11$s</label>
						<input type="password" value="%18$s" id="%4$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%5$s">%12$s</label>
						<input type="text" value="%19$s" id="%5$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%6$s">%13$s</label>
						<input type="password" value="%20$s" id="%6$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label for="%7$s">%14$s</label>
						<input type="password" value="%21$s" id="%7$s">%22$s
					</div>
					<div class="rad_dashboard_account_row">
						<label style="display:none;" for="%2$s">%9$s</label>
						<input type="hidden" value="34.0" id="%2$s">
					</div>
					',
			esc_attr('url_'.$service),#1
			esc_attr('version_'.$service),#2
			esc_attr('client_key_'.$service),#3
			esc_attr('client_secret_'.$service),#4
			esc_attr('username_sf_'.$service),#5
			esc_attr('password_sf_'.$service),#6
			esc_attr('token_'.$service),#7
			__('Instance Number', 'rapidology'),#8
			__('Salesforce version #', 'rapidology'),#9
			__('Consumer key', 'rapidology'),#10
			__('Consumer secret', 'rapidology'),#11
			__('Salesforce username', 'rapidology'),#12
			__('Salesforce password', 'rapidology'),#13
			__('Secuirty token', 'rapidology'),#14
			( '' !== $field_values && isset( $field_values['url'] ) ) ? esc_attr( $field_values['url'] ) : '',#15
			( '' !== $field_values && isset( $field_values['version'] ) ) ? esc_attr( $field_values['version'] ) : '',#16
			( '' !== $field_values && isset( $field_values['client_key'] ) ) ? esc_attr( $field_values['client_key'] ) : '',#17
			( '' !== $field_values && isset( $field_values['client_secret'] ) ) ? esc_attr( $field_values['client_secret'] ) : '',#18
			( '' !== $field_values && isset( $field_values['username_sf'] ) ) ? esc_attr( $field_values['username'] ) : '',#19
			( '' !== $field_values && isset( $field_values['password_sf'] ) ) ? esc_attr( $field_values['password'] ) : '',#20
			( '' !== $field_values && isset( $field_values['token'] ) ) ? esc_attr( $field_values['token'] ) : '',#21
			RAD_Rapidology::generate_hint( sprintf(
				'<a href="http://www.rapidology.com/docs#'.$service.'" target="_blank">%1$s</a>',
				__( 'Click here for more information', 'rapidology' )
			), false
			)#22
		);
		return $form_fields;
	}

	/**
	 * Retrieves the campaigns via Salesforce api and updates the data in DB.
	 * @return string
	 */

	public function get_salesforce_campagins($url, $version, $client_key, $client_secret, $username_sf, $password_sf, $token, $name){
		$error_message='';
		if(!class_exists('Rapidology_SalesforceAPI')) {
			require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/salesforce/SalesforceAPI.php');
		}

		//check just instance name for naXX

		preg_match("/na[0-9]+/", $url, $matches);
		//if matches from preg_match is 0 that means that there is something wrong with the url
		if(sizeof($matches) == 0){
			$error_message = "Please check your instance name. It should be naXX. <br /> This will  be the first part url you are at once you login to salesforce.";
			return $error_message;
		}
		$url = 'https://'.$matches[0].'.salesforce.com';
		//ensure version has a . in it so 34.0 vs 34 etc
		preg_match("/[0-9]+[\.]+[0-9]+/", $version, $version_matches);
		if(sizeof($version_matches) == 0){
			$error_message = "Please check your version. It must be formatted as XX.X Example 34.0  The trailing .0 after 34 are needed";
			return $error_message;
		}
		//instantiate new salesforce class and login with your user. User needs to have access to campagins and leads
		$salesforce = new Rapidology_SalesforceAPI($url, $version, $client_key, $client_secret);

		$salesforce->login($username_sf, $password_sf, $token);

		//perform soql query to get all lead information
		$campagins = $salesforce->searchSOQL('select id, name, NumberOfLeads, NumberOfContacts from campaign where EndDate >= TODAY or EndDate = null');
		$campagin_list = array();
		foreach($campagins->records as $campaign){
			$campagin_list[$campaign->Id]['name'] = $campaign->Name;
			$campagin_list[$campaign->Id]['subscribers_count'] = $campaign->NumberOfLeads;
			$campagin_list[$campaign->Id]['growth_week'] = sanitize_text_field($this->calculate_growth_rate('salesforce_' . $campaign->Id));
		}
		//echo '<pre>';print_r($campagin_list);
		$this->update_account('salesforce', sanitize_text_field($name), array(
			'url' 			=> $url,
			'version' 		=> $version,
			'client_key' 	=> $client_key,
			'client_secret' => $client_secret,
			'username_sf' 	=> $username_sf,
			'password_sf' 	=> $password_sf,
			'token' 		=> $token,
			'lists' 		=> $campagin_list,
			'is_authorized' => 'true',
		));
		$error_message = 'success';
		return $error_message;
	}

	/**
	 * Adds Lead and adds them to selected campagain via Salesforce api and updates the data in DB.
	 * @return string
	 */
	public function subscribe_salesforce($url, $version, $client_key, $client_secret, $username_sf, $password_sf, $token, $name, $last_name, $email, $list_id){
		if(!class_exists('Rapidology_SalesforceAPI')) {
			require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/salesforce/SalesforceAPI.php');
		}
		//lastname is required so if it is not provided setting it to weblead
		if($last_name == ''){
			$last_name = 'WebLead';
		}

		//test to make sure the url appears to be properly formatted

		//instantiate new salesforce class and login with your user. User needs to have access to campagins and leads
		$salesforce = new Rapidology_SalesforceAPI($url, $version, $client_key, $client_secret);
		$salesforce->login($username_sf, $password_sf, $token);
		//perform soql query to see if email is already assigned to a lead
		$current_lead = $salesforce->searchSOQL("select id from lead where email = '".$email."'");

		$current = $current_lead->totalSize;

		if($current > 0){
			$lead_ids['id']=$current_lead->records[0]->Id;
		}else{
			$params = array(
				'firstname' => $name,
				'lastname'=>$last_name,
				'email'=>$email,
				'company'=>'WebLead'
			);
			$create_lead = $salesforce->create( 'Lead', $params );
			$lead_ids['id']=$create_lead->id;

		}
		$error_message = '';
		//return($lead_ids['id']);
		if($lead_ids['id'] == '0'){
			$error_message = 'Connection error please try again';
		}


		//check to see if lead is a member of the campagin
		$current_member = $salesforce->searchSOQL("SELECT LeadId FROM CampaignMember where CampaignId = '".$list_id."' and LeadId = '".$lead_ids['id']."' ");
		if($current_member->totalSize > 0){
			//just pass as success if they are currently a member of the campaign
			$error_message = 'success';
			return $error_message;
		}
		//hopefully create new memeber of campagain
		$args = array(
			'LeadId'		=> $lead_ids['id'],
			'CampaignId'	=> $list_id
		);
		$campaign_member = $salesforce->create( 'CampaignMember', $args );
		if($campaign_member->success == '1'){
			$error_message  =  'success';
		}else{
			$error_message = __('Lead could not be added to campaign', 'rapidology');
		}

		return $error_message;
	}

	/**
	 * Generates the output for the salesforce versions dropdown
	 * @return string
	 */
	function get_salesforce_version_lists($service){
		$response = wp_remote_get( 'https://na34.salesforce.com/services/data/' );
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode($response);
		$output = '<select id="version_'.$service.'">';
		foreach($response as $version){
			$output .= '<option value="'.$version->version.'">'.$version->label.'</option>';
		}
		$output .= '</select>';
		return $output;
	}
}