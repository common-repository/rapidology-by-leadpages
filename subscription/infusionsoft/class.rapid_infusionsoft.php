<?php

/*class rapid_infusionsoft extends RAD_Rapidology{

	public $app_id = '';
	public $api_key ='';
	public $name ='';
	public $list_id='';
	public $email = '';
	public $last_name='';

	public function __construct($app_id, $api_key, $name, $list_id='', $email='', $name = '', $last_name = ''){
		$this->app_id = $app_id;
		$this->api_key = $api_key;
		$this->name = $name;
		$this->list_id = $list_id;
		$this->email = $email;
		$this->last_name = $last_name;
	}

	function get_infusionsoft_lists() {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'rapidology' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		$lists = array();

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $this->app_id, $this->api_key, 'throw' );
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

			parent::update_account( 'infusionsoft', sanitize_text_field( $this->name ), array(
				'lists'         => $lists,
				'api_key'       => sanitize_text_field( $this->api_key ),
				'client_id'     => sanitize_text_field( $this->app_id ),
				'is_authorized' => 'true',
			) );
		}

		return $error_message;
	}
*/
	/**
	 * Subscribes to Infusionsoft list. Returns either "success" string or error message.
	 * @return string
	 */
/*
	function subscribe_infusionsoft() {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'rapidology' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $this->app_id, $this->api_key, 'throw' );
		} catch ( iSDKException $e ) {
			$error_message = $e->getMessage();
		}


		$contact_details = array(
			'FirstName' => $this->name,
			'LastName'  => $this->last_name,
			'Email'     => $this->email,
		);
		$new_contact_id = $infusion_app->addWithDupCheck($contact_details, $checkType = 'Email');
		$infusion_app->optIn($contact_details['Email']);
		$response = $infusion_app->grpAssign( $new_contact_id, $this->list_id );
		if($response) {
			$error_message = 'success';
		}else{
			$error_message = esc_html__( 'Already In List', 'rapidology' );
		}


		return $error_message;
	}*/



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
