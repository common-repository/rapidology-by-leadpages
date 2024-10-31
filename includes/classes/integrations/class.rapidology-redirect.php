<?php
if (!class_exists('RAD_Dashboard')) {
	require_once(RAD_RAPIDOLOGY_PLUGIN_DIR . 'rapidology.php');
}

class rapidology_redirect extends RAD_Rapidology{

	function redirect_authorize($name){

		$current_lists = get_site_option('rapidology_redirect_lists');
		$lists = json_decode($current_lists, true);

		$this->update_account( 'redirect', sanitize_text_field( $name ), array(
			'lists'         => $lists,
			'is_authorized' => 'true',
		) );
		return 'success';
	}

	//stashing code here for now
	function rapidology_save_redirect_list(){
		wp_verify_nonce( $_POST['rapidology_premade_nonce'], 'rapidology_premade' );
		$name = sanitize_file_name($_POST['list_name']);
		$lists = array();

		$lists[ 0 ]['name']              = $name;
		$lists[ 0 ]['subscribers_count'] = 0;
		$lists[ 0 ]['growth_week']       = 0;
		$current_lists = get_site_option('rapidology_redirect_lists');
		$current_lists = json_decode($current_lists, true);
		if(is_array($current_lists)){
			$update_lists = array_merge($current_lists, $lists);
		}else{
			$update_lists = $lists;
		}
		$update_lists = json_encode($update_lists);
		update_site_option('rapidology_redirect_lists', $update_lists);
		die('success');

	}


}