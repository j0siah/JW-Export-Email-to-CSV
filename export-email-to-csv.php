<?php
/**
 * @package JW Export_Email_to_CSV
 * @version 1.0.0
 */
/*
Plugin Name: JW Export Email to CSV
Plugin URI: http://wordpress.org/extend/plugins/jw-export-email-to-csv/
Description: Export Emails to a csv file for use in address books and mailing lists.
Version: 1.0.0
Author: Josiah Williams
Author URI: http://josiahdwilliams.com
License: MIT
Text Domain: jw-export-email-to-csv
*/
/*  Copyright (c) 2014  Josiah Williams  (http://josiahdwilliams.com/)

    Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
*/

//load_plugin_textdomain( 'jw-export-email-to-csv', false, basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * Main plugin class
 *
 * @since 0.1
 **/
class PP_EU_Export_Email {

	/**
	 * Class contructor
	 *
	 * @since 0.1
	 **/
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'init', array( $this, 'generate_csv' ) );
		add_filter( 'pp_eu_exclude_data', array( $this, 'exclude_data' ) );
	}

	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
		add_users_page( __( 'Export Emails', 'jw-export-email-to-csv' ), __( 'Export to Email', 'jw-export-email-to-csv' ), 'list_users', 'jw-export-email-to-csv', array( $this, 'users_page' ) );
	}

	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public function generate_csv() {
		if ( isset( $_POST['_wpnonce-pp-eu-export-users-users-page_export'] ) ) {
			check_admin_referer( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' );

			$args = array(
				'fields' => 'all_with_meta',
				'role' => stripslashes( $_POST['role'] )
			);

			add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
			$users = get_users( $args );
			remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

			if ( ! $users ) {
				$referer = add_query_arg( 'error', 'empty', wp_get_referer() );
				wp_redirect( $referer );
				exit;
			}

			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			if ( ! empty( $sitename ) )
				$sitename .= '.';
			$filename = $sitename . 'users.' . date( 'Y-m-d-H-i-s' ) . '.csv';

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

			$exclude_data = apply_filters( 'pp_eu_exclude_data', array() );

			global $wpdb;

			$data_keys = array( //change to only export email 
				'ID', 'user_login', 'user_pass',
				'user_nicename', 'user_email', 'user_url',
				'user_registered', 'user_activation_key', 'user_status',
				'display_name'
			);
			$jw_keys = array( //change to only export email 
				 'user_email'
				
			);
			$meta_keys = $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" );
			$meta_keys = wp_list_pluck( $meta_keys, 'meta_key' );
			//$fields = array_merge( $jw_keys, $meta_keys ); get rigd of meta keys

			$fields = $jw_keys;

			$headers = array();
			foreach ( $fields as $key => $field ) {
				if ( in_array( $field, $exclude_data ) )
					unset( $fields[$key] );
				else
					$headers[] = '"' . strtolower( $field ) . '"';
			}
			echo implode( ',', $headers ) . "\n";

			foreach ( $users as $user ) {
				$data = array();
				foreach ( $fields as $field ) {
					$value = isset( $user->{$field} ) ? $user->{$field} : '';
					$value = is_array( $value ) ? serialize( $value ) : $value;
					$data[] = '"' . str_replace( '"', '""', $value ) . '"';
				}
				echo implode( ',', $data ) . "\n";
			}

			exit;
		}
	}

	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public function users_page() {
		if ( ! current_user_can( 'list_users' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'jw-export-email-to-csv' ) );
?>

<div class="wrap">
	<h2><?php _e( 'Export users to a CSV file', 'jw-export-email-to-csv' ); ?></h2>
	<?php
	if ( isset( $_GET['error'] ) ) {
		echo '<div class="updated"><p><strong>' . __( 'No user found.', 'jw-export-email-to-csv' ) . '</strong></p></div>';
	}
	?>
	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for"pp_eu_users_role"><?php _e( 'Role', 'jw-export-email-to-csv' ); ?></label></th>
				<td>
					<select name="role" id="pp_eu_users_role">
						<?php
						echo '<option value="">' . __( 'Every Role', 'jw-export-email-to-csv' ) . '</option>';
						global $wp_roles;
						foreach ( $wp_roles->role_names as $role => $name ) {
							echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label><?php _e( 'Date range', 'jw-export-email-to-csv' ); ?></label></th>
				<td>
					<select name="start_date" id="pp_eu_users_start_date">
						<option value="0"><?php _e( 'Start Date', 'jw-export-email-to-csv' ); ?></option>
						<?php $this->export_date_options(); ?>
					</select>
					<select name="end_date" id="pp_eu_users_end_date">
						<option value="0"><?php _e( 'End Date', 'jw-export-email-to-csv' ); ?></option>
						<?php $this->export_date_options(); ?>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
			<input type="submit" class="button-primary" value="<?php _e( 'Export', 'jw-export-email-to-csv' ); ?>" />
		</p>
	</form>
<?php
	}

	public function exclude_data() {
		$exclude = array( 'user_pass', 'user_activation_key' );

		return $exclude;
	}

	public function pre_user_query( $user_search ) {
		global $wpdb;

		$where = '';

		if ( ! empty( $_POST['start_date'] ) )
			$where .= $wpdb->prepare( " AND $wpdb->users.user_registered >= %s", date( 'Y-m-d', strtotime( $_POST['start_date'] ) ) );

		if ( ! empty( $_POST['end_date'] ) )
			$where .= $wpdb->prepare( " AND $wpdb->users.user_registered < %s", date( 'Y-m-d', strtotime( '+1 month', strtotime( $_POST['end_date'] ) ) ) );

		if ( ! empty( $where ) )
			$user_search->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1$where", $user_search->query_where );

		return $user_search;
	}

	private function export_date_options() {
		global $wpdb, $wp_locale;

		$months = $wpdb->get_results( "
			SELECT DISTINCT YEAR( user_registered ) AS year, MONTH( user_registered ) AS month
			FROM $wpdb->users
			ORDER BY user_registered DESC
		" );

		$month_count = count( $months );
		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		foreach ( $months as $date ) {
			if ( 0 == $date->year )
				continue;

			$month = zeroise( $date->month, 2 );
			echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
		}
	}
}

new PP_EU_Export_Email;
