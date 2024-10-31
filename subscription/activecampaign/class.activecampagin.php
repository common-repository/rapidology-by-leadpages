<?php

/**
 * Class to grab Active Campaign forms, parse them to see if they have a name field, email field, and any required fields
 * we can not support to build a list of usable forms to submit to. It then allows you to submit to those lists
 *
 *
 * @author Brandon Braner - LeadPages
 * @version 1.0
 */

class rapidology_active_campagin
{
	/**
	 * @var string
	 * @description url to post the form to
	 */
	private $url = '';

	/**
	 * @var string
	 * @description api_key from activate campaign
	 */
	private $api_key = '';

	/**
	 * @var string
	 * @description action for the call to make. options are form_getforms(retrieves forms) or form_html(retrieves single form html)
	 */
	private $api_action ='';

	/**
	 * @var string
	 * @description output format of results. defaulting to json
	 */
	private $api_output = 'json';

	/**
	 * @var int
	 * @description id of the form to run through form_html
	 */
	private $form_id = 0;


	/**
	 * @var bool
	 * @description states if the form is qualified to be displayed
	 */
	private $qualified_form = false;

	/**
	 * @var array
	 * @description field names that are currently supported in the api, this needs changed if more are added as if its not in here, the form will be disqualified
	 */
	private $supported_fields = array(
		'fullname',
		'email'
	);

	/**
	 * @var string
	 * @description url that the form should submit to
	 */
	private $form_action = '';

	/**
	 * @var array
	 * @description checked against to see if the field is supported. if it is, it will disqualify the form.
	 */
	private $unsupported_fields = array(
		'captcha',
		'required'
	);
	/**
	 * @var array
	 * @description do not believe this is actually used, but leaving here for good messure
	 */
	private $supported_types = array(
		'text',
		'email'
	);
	/**
	 * @var bool
	 * @description if a fullname field is on the field this will be true.
	 */
	private $fullname = false;
	/**
	 * @var bool
	 * @description sets to true if an email field is found, if it is not it will disqualify the form.... this is an email capture plugin after all.
	 */
	private $email = false;



	public function __construct($url, $api_key){

		$this->url = $url;
		$this->api_key = $api_key;
	}

	private function http_request(){
		$params = array(
			'api_key'		=> $this->api_key,
			'api_action'	=> $this->api_action,
			'api_output'	=> $this->api_output,
			'extra'			=> 0,
		);
		//add on the form id if it is > 0 for the get_html action
		if($this->form_id > 0){
			$id = array(
				'id' => $this->form_id
			);
			$params =  array_merge($params, $id);
		}

		// This section takes the input fields and converts them to the proper format
		$query = "";
		foreach( $params as $key => $value ) $query .= $key . '=' . urlencode($value) . '&';
		$query = rtrim($query, '& ');

		// clean up the url
		$url = rtrim($this->url, '/ ');

		//make sure curl exists
		if ( !function_exists('curl_init') ) die('CURL not supported. (introduced in PHP 4.0.2)');

		// If JSON is used, check if json_decode is present (PHP 5.2.0+)
		if ( $params['api_output'] == 'json' && !function_exists('json_decode') ) {
			die('JSON not supported. (introduced in PHP 5.2.0)');
		}

		// define a final API request - GET
		$api = $url . '/admin/api.php?' . $query;
		$request = curl_init($api); // initiate curl object
		curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
		//curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment if you get no gateway response and are using HTTPS
		curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);

		$response = (string)curl_exec($request); // execute curl fetch and store results in $response

		// additional options may be required depending upon your server configuration
		// you can find documentation on curl options at http://www.php.net/curl_setopt
		curl_close($request); // close curl object

		if ( !$response ) {
			die('Nothing was returned. Do you have a connection to Email Marketing server?');
		}

		return $response;
	}

	public function rapidology_get_ac_forms(){
		$this->api_action = 'form_getforms';
		$results = json_decode($this->http_request());

		$forms =  array();
		$i=0;
		if($results->result_code){
			//unset unneeded rows that add blank lines to $forms
			unset($results->result_code);
			unset($results->result_message);
			unset($results->result_output);
			foreach($results as $row){
				$forms[$i]['id'] = $row->id;
				$forms[$i]['name'] = $row->name;
				$forms[$i]['subscriptions'] = $row->subscriptions;
				$forms[$i]['lists'] = $row->lists;
				$i++;
			}
		}else{
			$error_array=array(
				'status' => 'error',
				'message' => 'Error retrieving lists, please check your credientals and try again'
			);
			return $error_array;
		}
		return $forms;
	}

	/**
	 * @param $forms
	 * @return mixed | multideminsional array
	 * @description  this is the method you will call to parse the form ids, pull back all html, run through it via apidology_qualify_form
	 * once that is doen it should return an array of form information used to store in the rapidology database with the id, name, subscription count, and all the field information needed to
	 * resend the form
	 */
	public function rapidology_get_ac_html($forms){
		$i=0;

		foreach($forms as $form) {
			$this->form_id = $form['id'];
			$this->api_action = 'form_html';
			$results = $this->http_request();
			//run each form through qualify form to make sure rapidology can use it
			$qualified = $this->rapidology_qualify_form($results);
			if ($qualified) {
				$valid_forms[$form['id']] = $form;
				$valid_forms[$form['id']]['fields'] = $qualified;
				$i++;
			} else {
				$invalid_forms[$form['id']]['not_qualfied'] = 'true';
			}
		}

		return $valid_forms;
	}

	private function rapidology_qualify_form($response){
		$form = new DOMDocument;
		$form->loadHTML($response);
		$xpath = new DOMXPath($form);
		$form_fields = array();
		$i=0;
		$error = 0;
		$success = 1;
		/**
		 * Check to see if captcha exist if so automaticlly disqualify form
		 */
		$capthca =  $xpath->query("//div[@class='_field _type_captcha']");
		if($capthca->length > 0){
			return $error;
		}
		/**
		 * @description get form submit url
		 * @note ended up not being needed, leaving for good measure
		 *//*
		$formtag = $form->getElementsByTagName('form');
		foreach($formtag as $tag){
			foreach($tag->attributes as $form_att){
				if($form_att->name == 'action'){
					$form_fields['action'] = $form_att->textContent;
				}
			}
		}*/
		/**
		 * get hidden fields to submit
		 * @note ended up note being needed as there was a pre-existing api to submit forms, leaving here just incase
		 */
		/*$hidden_values = $xpath->query("//input[@type='hidden']");
		foreach ($hidden_values as $hidden_input){
			foreach($hidden_input->attributes as $hidden_input_att){
				//$name = ($hidden_input_att->name == 'name' ? $hidden_input_att->nodeValue : '');
				if($hidden_input_att->name == 'name'){
					$name = $hidden_input_att->nodeValue;
				}
				if($hidden_input_att->name == 'value'){
					$value = $hidden_input_att->value;
				}
				//$value = ($hidden_input_att->name == 'value' ? $hidden_input_att->value : '1');
				$hidden_inputs[$name] = $value;
			}
		}
		//for some reason there is always a blank hidden field ie: $hidden_fields[]=> []
		//so this hack removes it from the array
		foreach($hidden_inputs as $key => $value){
			$length = strlen($key);
			if($length == 0){
				unset ($hidden_inputs[$key]);
			}
			$form_fields['hidden'] = $hidden_inputs;
		}*/

		/**
		 * check all divs that contain an input and record them in an array for later processing
		 */
		$divs =  $xpath->query("//div[@class='_field _type_input']");
		foreach($divs as $div){
				foreach ($div->childNodes as $node) {
					if(count($node->attributes) > 0) {
						foreach ($node->attributes as $att) {
							if ($att->name == 'class' && strpos($att->nodeValue, '_label') >= 0) {
								preg_match("/[a-z0-9]/i", $node->nodeValue, $matches);
								if ($matches) {
									$form_fields[$i]['label'] = trim($node->nodeValue);

								}
							}
							if ($att->name == 'class' && strpos($att->nodeValue, '_option') >= 0) {
								foreach ($node->childNodes as $input) {
									if ($input->tagName == 'input') {
										foreach ($input->attributes as $input_att) {
											if ($input_att->name == 'type' && in_array($input_att->nodeValue, $this->supported_types)) {
												$form_fields[$i]['input_type'] = trim($input_att->nodeValue);
												$approved_type = 'approved';
											}
											if ($input_att->name == 'name') {
												$form_fields[$i]['input_name'] = trim($input_att->nodeValue);
											}
											$approved_type = false;
										}
									}
								}
							}
						}
					}
				}
				$i++;
		}

		/**
		 * grab checkboxes and record so we can check to see if they are required. if they are it will disqualify form
		 */
		$divs =  $xpath->query("//div[@class='_field _type_checkbox']");
		foreach($divs as $div){
			foreach($div->childNodes as $node){
				if(count($node->attributes) > 0) {
					foreach ($node->attributes as $att) {
						if ($att->name == 'class' && strpos($att->nodeValue, 'label') >= 0 && $att->nodeValue != '_option') {
							preg_match("/[a-z0-9]/i", $node->nodeValue, $matches);
							if ($matches) {
								$form_fields[$i]['label'] = trim($node->nodeValue);
								//know its a checkbox because of the type_checkbox selector
								$form_fields[$i]['input_type'] = 'checkbox';

							}
						}
					}
				}
			}$i++;
		}
		
		/**
		 * loop through all the form fields that have been recorded check for 1. required (if its not the email field it throws the form out) 2.check for name field, not sure what to do with this yet but I know it will have something to do with the form submitting name fields or not
		 *
		 */

		foreach($form_fields as $field){
			if(!in_array($field['input_name'], $this->supported_fields )) {
				preg_match("/[*]/i", $field['label'], $required);
			}
			if($required){
				$qualified_form = false;
			}else{
				$qualified_form = 'true';
			}
			if($qualified_form === false){
				return $error;
			}
		}

		if($qualified_form = 'true'){
			return $form_fields;
		}
	}

	public function rapidology_submit_ac_form($form_id, $first_name, $last_name, $email, $lists_array, $url ){

		$this->api_action = 'contact_add';
		$params = array(
			'api_key'		=> $this->api_key,
			'api_action'	=> $this->api_action,
			'api_output'	=> $this->api_output,
		);
		$names_array = rapidology_name_splitter($first_name, $last_name);
		$first_name = $names_array['name'];
		$last_name = $names_array['last_name'];
		$post_fields = array();
		$post_fields['first_name']	= $first_name;
		$post_fields['last_name']	= $last_name;
		$post_fields['email']		= $email;
		foreach($lists_array as $list){
			$post_fields["p[$list]"] = $list;
			$post_fields['status'] = 1;
			$post_fields["instantresponders[$list]"] = 0;
		}
		$post_fields['form'] = $form_id;

		// This section takes the input fields and converts them to the proper format
		$query = "";
		foreach( $params as $key => $value ) $query .= $key . '=' . urlencode($value) . '&';
		$query = rtrim($query, '& ');

		// This section takes the input data and converts it to the proper format
		$data = "";
		foreach( $post_fields as $key => $value ) $data .= $key . '=' . urlencode($value) . '&';
		$data = rtrim($data, '& ');
		$url = rtrim($this->url, '/ ');
		if ( !function_exists('curl_init') ) die('CURL not supported. (introduced in PHP 4.0.2)');
		// If JSON is used, check if json_decode is present (PHP 5.2.0+)
		if ( $params['api_output'] == 'json' && !function_exists('json_decode') ) {
			die('JSON not supported. (introduced in PHP 5.2.0)');
		}

		// define a final API request - GET
		$api = $url . '/admin/api.php?' . $query;
		$request = curl_init($api); // initiate curl object
		curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
		curl_setopt($request, CURLOPT_POSTFIELDS, $data); // use HTTP POST to send form data
		//curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment if you get no gateway response and are using HTTPS
		curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
		$response = (string)curl_exec($request); // execute curl post and store results in $response
		// additional options may be required depending upon your server configuration
		// you can find documentation on curl options at http://www.php.net/curl_setopt
		curl_close($request); // close curl object

		if ( !$response ) {
			die('Nothing was returned. Do you have a connection to Email Marketing server?');
		}
		$results = json_decode($response);
		$success = array();
		if($results->result_code){
			$success['result'] = 'success';
			$success['message'] = 'success';
		}else{
			$success['result'] = 'error';
			$success['message'] = 'There seems to be an issue with your form. Please check it for invalid fields.';
		}
		return $success;
	}
}




?>