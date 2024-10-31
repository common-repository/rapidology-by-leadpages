<?php

if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_mailpoet extends RAD_Rapidology
{

	public function __contruct()
	{

	}

	public function draw_ontraport_form($form_fields, $service, $field_values)
	{

	}

	/**
	 * Retrieves the lists from MailPoet table and updates the data in DB.
	 * @return string
	 */
	function get_mailpoet_lists( $name ) {
		$lists = array();

		global $wpdb;
		$table_name  = $wpdb->prefix . 'wysija_list';
		$table_users = $wpdb->prefix . 'wysija_user_list';

		if ( ! class_exists( 'WYSIJA' ) ) {
			$error_message = __( 'MailPoet plugin is not installed or not activated', 'rapidology' );
		} else {
			$list_model      = WYSIJA::get( 'list', 'model' );
			$all_lists_array = $list_model->get( array( 'name', 'list_id' ), array( 'is_enabled' => '1' ) );

			$error_message = 'success';

			if ( ! empty( $all_lists_array ) ) {
				foreach ( $all_lists_array as $list_details ) {
					$lists[ $list_details['list_id'] ]['name'] = $list_details['name'];

					$user_model            = WYSIJA::get( 'user_list', 'model' );
					$all_subscribers_array = $user_model->get( array( 'user_id' ), array( 'list_id' => $list_details['list_id'] ) );

					$subscribers_count                                      = count( $all_subscribers_array );
					$lists[ $list_details['list_id'] ]['subscribers_count'] = $subscribers_count;

					$lists[ $list_details['list_id'] ]['growth_week'] = $this->calculate_growth_rate( 'mailpoet_' . $list_details['list_id'] );
				}
			}

			$this->update_account( 'mailpoet', $name, array(
				'lists'         => $lists,
				'is_authorized' => esc_html( 'true' ),
			) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to MailPoet list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_mailpoet( $list_id, $email, $name = '', $last_name = '' ) {
		global $wpdb;
		$table_user       = $wpdb->prefix . 'wysija_user';
		$table_user_lists = $wpdb->prefix . 'wysija_user_list';

		if ( ! class_exists( 'WYSIJA' ) ) {
			$error_message = __( 'MailPoet plugin is not installed or not activated', 'rapidology' );
		} else {
			$sql_count = "SELECT COUNT(*) FROM $table_user WHERE email = %s";
			$sql_args  = array(
				$email,
			);

			$subscribers_count = $wpdb->get_var( $wpdb->prepare( $sql_count, $sql_args ) );

			if ( 0 == $subscribers_count ) {

				$new_user = array(
					'user'      => array(
						'email'     => $email,
						'firstname' => $name,
						'lastname'  => $last_name
					),
					'user_list' => array( 'list_ids' => array( $list_id ) )
				);

				$mailpoet_class = WYSIJA::get( 'user', 'helper' );
				$error_message  = $mailpoet_class->addSubscriber( $new_user );
				$error_message  = is_int( $error_message ) ? 'success' : $error_message;
			} else {
				$error_message = __( 'Already Subscribed', 'rapidology' );
			}
		}

		return $error_message;
	}
}