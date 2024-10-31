<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_aweber extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_aweber_form($form_fields, $service, $field_values)
	{
		$app_id               = '7365f385';
		$aweber_auth_endpoint = 'https://auth.aweber.com/1.0/oauth/authorize_app/' . $app_id;

		$form_fields .= sprintf( '
					<div class="rad_dashboard_account_row rad_dashboard_aweber_row">%1$s%2$s</div>',
			sprintf(
				__( 'Step 1: <a href="%1$s" target="_blank">Generate authorization code</a><br/>', 'rapidology' ),
				esc_url( $aweber_auth_endpoint )
			),
			sprintf( '
						%2$s
						<input type="password" value="%3$s" id="%1$s">',
				esc_attr( 'api_key_' . $service ),
				__( 'Step 2: Paste in the authorization code and click "Authorize" button: ', 'rapidology' ),
				( '' !== $field_values && isset( $field_values['api_key'] ) )
					? esc_attr( $field_values['api_key'] )
					: ''
			)
		);
		return $form_fields;
	}

	/**
	 * Retrieves the lists via AWeber API and updates the data in DB.
	 * @return string
	 */
	function get_aweber_lists( $api_key, $name ) {
		$options_array = RAD_Rapidology::get_rapidology_options();
		$lists         = array();

		if ( ! isset( $options_array['accounts']['aweber'][ $name ]['consumer_key'] ) || ( $api_key != $options_array['accounts']['aweber'][ $name ]['api_key'] ) ) {
			$error_message = $this->aweber_authorization( $api_key, $name );
		} else {
			$error_message = 'success';
		}

		if ( 'success' === $error_message ) {
			if ( ! class_exists( 'AWeberAPI' ) ) {
				require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
			}

			$account = $this->get_aweber_account( $name );

			if ( $account ) {
				$aweber_lists = $account->lists;
				if ( isset( $aweber_lists ) ) {
					foreach ( $aweber_lists as $list ) {
						$lists[ $list->id ]['name'] = $list->name;

						$total_subscribers                       = $list->total_subscribers;
						$lists[ $list->id ]['subscribers_count'] = $total_subscribers;

						$lists[ $list->id ]['growth_week'] = $this->calculate_growth_rate( 'aweber_' . $list->id );
					}
				}
			}

			$this->update_account( 'aweber', $name, array( 'lists' => $lists ) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Aweber list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_aweber( $list_id, $account_name, $email, $name = '' ) {
		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
		}

		$account = $this->get_aweber_account( $account_name );

		if ( ! $account ) {
			$error_message = __( 'Aweber: Wrong configuration data', 'rapidology' );
		}

		try {
			$list_url = "/accounts/{$account->id}/lists/{$list_id}";
			$list     = $account->loadFromUrl( $list_url );

			$new_subscriber = $list->subscribers->create(
				array(
					'email' => $email,
					'name'  => $name,
				)
			);
			print_r($new_subscriber);die();
			$error_message = 'success';
		} catch ( Exception $exc ) {
			$error_message = $exc->message;
		}

		return $error_message;
	}

	/**
	 * Retrieves the tokens from AWeber
	 * @return string
	 */
	function aweber_authorization( $api_key, $name ) {

		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( RAD_RAPIDOLOGY_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
		}

		try {
			$auth = AWeberAPI::getDataFromAweberID( $api_key );

			if ( ! ( is_array( $auth ) && 4 === count( $auth ) ) ) {
				$error_message = __( 'Authorization code is invalid. Try regenerating it and paste in the new code.', 'rapidology' );
			} else {
				$error_message = 'success';
				list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = $auth;

				$this->update_account( 'aweber', $name, array(
					'api_key'         => esc_html( $api_key ),
					'consumer_key'    => $consumer_key,
					'consumer_secret' => $consumer_secret,
					'access_key'      => $access_key,
					'access_secret'   => $access_secret,
					'is_authorized'   => esc_html( 'true' ),
				) );
			}
		} catch ( AWeberAPIException $exc ) {
			$error_message = sprintf(
				'<p>%4$s</p>
				<ul>
					<li>%5$s: %1$s</li>
					<li>%6$s: %2$s</li>
					<li>%7$s: %3$s</li>
				</ul>',
				esc_html( $exc->type ),
				esc_html( $exc->message ),
				esc_html( $exc->documentation_url ),
				esc_html__( 'AWeberAPIException.', 'rapidology' ),
				esc_html__( 'Type', 'rapidology' ),
				esc_html__( 'Message', 'rapidology' ),
				esc_html__( 'Documentation', 'rapidology' )
			);
		}

		return $error_message;
	}

	/**
	 * Creates Aweber account using the data saved to plugin's database.
	 * @return object or false
	 */
	function get_aweber_account( $name ) {
		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( get_template_directory() . '/includes/subscription/aweber/aweber_api.php' );
		}

		$options_array = RAD_Rapidology::get_rapidology_options();
		$account       = false;

		if ( isset( $options_array['accounts']['aweber'][ $name ] ) ) {
			$consumer_key    = $options_array['accounts']['aweber'][ $name ]['consumer_key'];
			$consumer_secret = $options_array['accounts']['aweber'][ $name ]['consumer_secret'];
			$access_key      = $options_array['accounts']['aweber'][ $name ]['access_key'];
			$access_secret   = $options_array['accounts']['aweber'][ $name ]['access_secret'];

			try {
				// Aweber requires curl extension to be enabled
				if ( ! function_exists( 'curl_init' ) ) {
					return false;
				}

				$aweber = new AWeberAPI( $consumer_key, $consumer_secret );

				if ( ! $aweber ) {
					return false;
				}

				$account = $aweber->getAccount( $access_key, $access_secret );
			} catch ( Exception $exc ) {
				return false;
			}
		}

		return $account;
	}
}