<?php
/*
Plugin Name: RND Active Login
Plugin URI: http://www.rndexperts.com
Description: This plugin will keep the track of User's login and logout time. Even if the user has closed the brower without logging out from their account. Admin can check the IP address of the user and history as well. 
Version: 2.2
Author: RND Experts
Tag: Login, Login Status, Login History, Active Login, Last Login, User IP
Donation: If you like this plugin, please donate for the enhancement and support in future. Email @ webrndexperts@gmail.com
Author URI: http://rndexperts.com
License: GPL2
 Copyright (coffee) 2013 RND Experts  (email : webrndexperts@gmail.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 1.0, as 
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, RND Experts
*/

class RND_Active_Login_History{

	private $admin_ajax_interval;
	private $guest_ajax_interval;
	private $plugin_path;
	private $plugin_url;
	private $log_file_path;
	private $log_file_url;
	/*
	* Plugin Constructor
	*/
	public function __construct() {
		
		$this->admin_ajax_interval = 100000;
		$this->guest_ajax_interval = 90000;	
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->log_file_path = $this->plugin_path.'logs/';
		$this->plugin_url = plugins_url().'/rnd-active-login/';
		$this->log_file_url = $this->plugin_url.'logs/';
		
		add_action( 'wp_dashboard_setup', array( $this, 'rnd_la_admin_dashboard_customise' ) );
		add_action( 'wp_login', array( $this, 'rnd_la_set_user_login_status' ), 10, 2);
		add_action( 'wp_logout', array( $this, 'rnd_la_user_logout_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'rnd_al_enqueue' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'rnd_al_enqueue' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'rnd_al_admin_enqueue' ), 10 );
		add_action('wp_ajax_nopriv_getCurrentLoginStatuss', array( $this, 'getCurrentLoginStatus' ) );
		add_action( 'wp_ajax_getCurrentLoginStatus', array( $this, 'getCurrentLoginStatus' ) );
		add_action( 'wp_ajax_checkUserStatus', array( $this, 'checkUserStatus' ) );
		add_action( 'wp_ajax_viewHistory', array( $this, 'viewHistory' ) );

		add_filter( 'manage_users_columns', array($this, 'rndal_modify_user_table' ) );
		add_filter( 'edit_user_profile', array($this, 'rndal_contact_methods') );
		add_filter( 'show_user_profile', array($this, 'rndal_contact_methods') );
		add_filter( 'manage_users_custom_column', array($this, 'rndal_modify_user_table_row'), 10, 3 );
	}

	/*
	* Enqueuing admin style
	*/
	function rnd_al_admin_enqueue(){
		wp_register_style( 'rndal-style', $this->plugin_url . '/rndal-style.css', false, '1.0.0' );
        wp_enqueue_style( 'rndal-style' );
	}

	/*
	* Enqueuing javascript and ajax localization
	*/
	function rnd_al_enqueue() {
	    wp_enqueue_script( 'ajax-script-1', plugins_url( '/js/rnd-al-script.js', __FILE__ ) );
		wp_localize_script( 'ajax-script-1', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'admin_interval' => $this->admin_ajax_interval, 'guest_interval' => $this->guest_ajax_interval ) );
	}

	/*
	* Adding widget to the Admin Dashboard
	*/
	function rnd_la_admin_dashboard_customise() {

		if( !current_user_can( 'administrator' ) ) return;
		// Globalise and get access to the widgets and current user info
		global $wp_meta_boxes, $current_user;
		// Remove Incoming Links widget for authors and editors
		if(in_array('author', $current_user->roles) || in_array('editor', $current_user->roles)){
			unset($wp_meta_boxes['dashboard']['normal ']['core']['dashboard_incoming_links']);
		}

		wp_add_dashboard_widget('custom_help_widget_', 'Active Login Log', array( $this, 'rnd_al_admin_dashboard_help_widget' ) );
	}

	
	/*
	* Adding Login status widget to the Dashboard Area
	*/
	function rnd_al_admin_dashboard_help_widget() {
		
		$blogusers = get_users( 'number=5' );

		echo '<span id="update-info"></span>';
		echo '<span class="spinner" id="history-spiner-1">Updating...</span>';
		echo '<table class="wp-list-table widefat fixed">';
		echo '<thead>';
		echo '	<tr>';
		echo '		<th width="30px;">ID</th>';
		echo '		<th width="30px;">&nbsp;</th>';
		echo '		<th>Name</th>';
		echo '		<th>Role</th>';
		echo '		<th>Last Login</th>';
		echo '		<th>&nbsp;</th>';
		echo '	</tr>';
		echo '</thead>';
		
		echo '<tfoot>';
		echo '	<tr>';
		echo '		<th>ID</th>';
		echo '		<th>&nbsp;</th>';
		echo '		<th>Name</th>';
		echo '		<th>Role</th>';
		echo '		<th>Last Login</th>';
		echo '		<th>&nbsp;</th>';
		echo '	</tr>';
		echo '</tfoot>';

		echo '	<tbody id="user-login-detail">';
		foreach ($blogusers as $key => $user) {
			$logged_in = get_user_meta( $user->ID, 'rndlm_login_status',true ) == 1 ? 'status-box logged-in' : 'status-box logged-out'; 
			
			echo '<tr>';
			echo '	<td>'.$user->ID.'</td>';
			echo '	<td><span class="'.$logged_in.'"></span></td>';
			echo '	<td>'.$user->display_name.'</td>';
			echo '	<td>'.$user->roles[0].'</td>';
			echo '	<td>'. get_user_meta($user->ID, 'rndlm_last_login', true).'</td>';
			echo '	<td><a href="'.get_edit_user_link($user->ID).'">History</a></td>';
			echo '</tr>';

		}
		echo '	</tbody>';
		echo '</table>';
		echo '<span class="spinner" id="history-spiner" >Updating...</span>';
		echo '<a class="button button-primary" href="'.admin_url('users.php').'">See more</a>';
		
	}

	/*
	* Update log file 
	*/
	function update_log_json($uid, $activity) {

		$file_path = $this->log_file_path.$uid.'.json';
	    $read_file = fopen($file_path, 'r');
	    $contents = fread($read_file, filesize($file_path) );
	    $json_to_array = json_decode($contents, true);

	    $logs = array(
    		'datetime' => date('Y-m-d h:i:s'),
    		'ip' => $_SERVER['REMOTE_ADDR'],
    		'activity' => $activity
    	);
	    if( !empty($json_to_array) > 0 ) {
	    	array_push($json_to_array, $logs);
	    	$log_to_json = $json_to_array;
	    } else {
	    	$log_to_json[] = $logs;
	    }

	    $write_file = fopen($file_path, 'w');
	    fwrite($write_file, json_encode($log_to_json));
		fclose($write_file);
	}


	/*
	* Updating user meta on User Login
	*/
	function rnd_la_set_user_login_status($user_login, $user) {
	    $user_id = $user->ID;
	    update_user_meta( $user_id, 'rndlm_login_status', 1 );
	    $this->update_log_json($user_id, 'Login');
	}

	/*
	* Updating user meta on User Logout
	*/
	function rnd_la_user_logout_status( ) {
	    $current_user = wp_get_current_user(); 
	  	$user_id = $current_user->ID;
	  	update_user_meta( $user_id, 'rndlm_login_status', 0 );
	  	$this->update_log_json($user_id, 'Logout');
	}

	/*
	* {Ajax method} Get and update the timestamp of Logged in User
	*/
	function getCurrentLoginStatus(){
		if( $_SERVER['REQUEST_METHOD'] == 'POST' || is_user_logged_in() ) :
			/*Get current User ID*/
			$user_id = get_current_user_id();
			
			/*Check if we've some last update*/
			$old_update = get_user_meta( $user_id, 'last_update', true );
			if( $old_update == "" ){
				update_user_meta( $user_id, 'last_update', strtotime( date( 'Y-m-d h:i:s' ) ) );	
			} else {

				$old_data_array = explode( "|", $old_update );
				
				/*Pust new timestamp to the array*/
				array_push( $old_data_array , strtotime( date( 'Y-m-d h:i:s' ) ) );
				$new_data = implode( "|", $old_data_array );
				
				/*Update new timestamp*/
				update_user_meta( $user_id, 'last_update', $new_data);
			}
		endif;
		die();
	}

	/*
	* {Ajax method} Check User status and update if user is logged out
	*/
	function checkUserStatus(){
		
		if( $_SERVER['REQUEST_METHOD'] == 'POST' || is_user_logged_in() ) :
			/*Get all user's ID*/
			$args = array( 'fields' => 'ID' );
			$all_users = get_users( $args );
			
			foreach ( $all_users as $key => $user ) {
				
				/*Get last updates*/
				$old_data = get_user_meta( $user, 'last_update', ture );
				
				/*If we have some old updates*/
				if( $old_data != "" ) {
					
					/*Explode timestamp data*/
					$old_data_array = explode( "|", $old_data );
					print_r($old_data_array);
					/*Calculate diff*/
					$last_update_timestamp_diff = strtotime(date('Y-m-d h:i:s')) - $old_data_array[ count($old_data_array) - 1 ];
					
					/*Check difference between the last Ajax call and Current time stamp.*/
					if( $last_update_timestamp_diff > ( ($this->guest_ajax_interval * 3)/1000 ) ) {
						update_user_meta( $user, 'rndlm_login_status', 0 );
						update_user_meta( $user, 'rndlm_last_logout', date('Y-m-d h:i:s') );
						update_user_meta( $user, 'last_update', '' );

						/*Update logout History*/
						$history_data = "[".date('Y-m-d h:i:s')."]";
		    			$login_history = get_user_meta( $user, 'rndlm_login_history', true);
		    			if( $login_history == "" ) {
					    	update_user_meta( $user, 'rndlm_login_history', $history_data.'|' );
					    } else {
					    	update_user_meta( $user, 'rndlm_login_history', $login_history.$history_data.'|' );
					    }
					} 
				}
			}

			$all_users = get_users();
			$latest_status_data = "";
			foreach ($all_users as $key => $user) {
				$latest_status_data .= '<tr>';
				$logged_in = get_user_meta( $user->ID, 'rndlm_login_status',true ) == 1 ? 'status-box logged-in' : 'status-box logged-out'; 
				$latest_status_data .= '	<td>'.$user->ID.'</td>';
				$latest_status_data .= '	<td><span class="'.$logged_in.'"></span></td>';
				$latest_status_data .= '	<td>'.$user->display_name.'</td>';
				$latest_status_data .= '	<td>'.$user->roles[0].'</td>';
				$latest_status_data .= '	<td>'. get_user_meta($user->ID, 'rndlm_last_login', true).'</td>';
				$latest_status_data .= '	<td><a href="javascript:void(0)" class="view-login-history" attr-id='.$user->ID.'>History</a></td>';
				$latest_status_data .= '</tr>';
			}
			echo json_encode($latest_status_data);
		endif;

		die();
	}

	/*
	* {Ajax method} View history of user
	*/
	function viewHistory(){
		if( $_SERVER['REQUEST_METHOD'] == 'POST' || is_user_logged_in() ) :
			$history = get_user_meta( $_POST['user_id'], 'rndlm_login_history', true );
			$history = str_replace("|", "<br>", $history);
			if( $history == "" ):
				$history = "No history found!";
			endif;
			echo json_encode($history);
		endif;
		die();
	}

	/*
	* Section of login log in Edit user screen.
	*/
	function rndal_contact_methods( $user ) {
		$login_status = get_user_meta($user->ID, 'rndlm_login_status', true);
		$logged_in = get_user_meta( $user->ID, 'rndlm_login_status',true ) == 1 ? 'status-box logged-in' : 'status-box logged-out'; 
		$current_status = "";
		if ( $login_status ) {
			$current_status .= "<p><span class='$logged_in'></span>&nbsp;&nbsp;Logged in</p>";
		} else {
			$current_status .= "<p><span class='$logged_in'></span>&nbsp;&nbsp;Logged out</p>";
		}

        echo '<h3>';
        _e("Active Login", "my_theme_domain"); 
		echo $current_status;
        echo '</h3>';
        echo '<table class="form-table" id="active-login">
            <tr class="user-description-wrap">
                '.$this->log_table($user->ID).'
            </tr>
        </table>';
    
	}
	
	/*
	* Add to user listing column
	*/
	function rndal_modify_user_table( $column ) {
	    $column['login_status'] = 'Login Status';
	    return $column;
	}
	
	/*
	* Add to user listing column
	*/
	function rndal_modify_user_table_row( $val, $column_name, $user_id ) {
	    switch ($column_name) {
	        case 'login_status' :
	        	$login_status = get_the_author_meta( 'rndlm_login_status', $user_id ); 
	        	$logged_in = get_user_meta( $user_id, 'rndlm_login_status',true ) == 1 ? 'status-box logged-in' : 'status-box logged-out'; 
                $span = "<span class='$logged_in'></span>&nbsp;&nbsp;";
	        	if ( $login_status ) {
	        		return $span.'Logged in';
	        	} else {
	        		return $span.'Logged out';
	        	}
	            
	        default:
	    }
	    return $val;
	}

	/*
	* Display log table
	*/
	function log_table( $uid ) {
	    $file_path = $this->log_file_path.$uid.'.json';
	    $read_file = fopen($file_path, 'r');
	    $contents = fread($read_file, filesize($file_path) );
	    $json_to_array = json_decode($contents, true);
	   
	    echo "<div class='log-table-container'> ";
	    echo '	<div class="log_table heading">
	       			<div><p>Date &amp; time</p></div>
	       			<div><p>IP</p></div>
	       			<div><p>Activity</p></div>
	       		</div>';
	    foreach ($json_to_array as $key => $value) {
	   		echo '<div class="log_table">
	   			<div><p>'.$value['datetime'].'</p></div>
	   			<div><p>'.$value['ip'].'</p></div>
	   			<div><p>'.$value['activity'].'</p></div>
	   		</div>';
		}
		echo "</div>";

	}


}


$obj = new RND_Active_Login_History();

?>