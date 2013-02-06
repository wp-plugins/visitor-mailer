<?php
/*
Plugin Name: Visitor Mailer
Plugin URI: http://ctolley.com/visitor-mailer
Description: Receive an email update of the number of visitors to your site.
Version: 1.0.0
Author: Conner Tolley
Author URI: http://ctolley.com
License: A "Slug" license name e.g. GPL2
*/

/*  Copyright 2012  Conner Tolley  (email : connertolley@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Store the plugin version number
$new_version = '1.0.0';

// Store the version number in the database
if (!defined('STATMAILER_VERSION_KEY'))
    define('STATMAILER_VERSION_KEY', 'visitormailer_version');

if (!defined('STATMAILER_VERSION_NUM'))
    define('STATMAILER_VERSION_NUM', '1.0.0');

if (!defined('STATMAILER_TABLE_NAME'))
    define('STATMAILER_TABLE_NAME', $wpdb->prefix. "visitormailer");

add_option(VISITORMAILER_VERSION_KEY, VISITORMAILER_VERSION_NUM);

// create the db table when activating the plugin
register_activation_hook(__FILE__,'visitormailer_install');

// drop the plugin table and get rid of the globals
register_deactivation_hook( __FILE__, 'visitormailer_deactivate' );

// what to do when uninstall occurs
register_uninstall_hook( __FILE__,'visitormailer_uninstall');

// Delete old rows from the database so it doesn't get huge
add_action('admin_menu', 'visitormailer_delete_old_db_rows');

// Call the getStats function in the wordpress footer
add_action('send_headers', 'visitormailer_getStats');

// Add the visitormailer cron menu
add_action( 'admin_menu', 'visitormailer_options_menu' );

// add the visitormailer email hook
add_action('visitormailer_cron_hook', 'visitormailer_send_stat_email');

// add a filter to schedule weekly cron jobs
add_filter( 'cron_schedules', 'visitormailer_cron_add_weekly' );

/*
* We need to create the database table
* for storing the statistics when the plugin
* is installed
*/
function visitormailer_install() {
	global $wpdb;
	$table_name = $wpdb->prefix . "visitormailer";
	
	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  ip_address VARCHAR(55) NOT NULL,
	  referer_url VARCHAR(55),
	  site_page VARCHAR(55),
	  page_id VARCHAR(19),
	  browser VARCHAR(55),
	  op_system VARCHAR(55),
	  country VARCHAR(55),
	  language VARCHAR(55),	
	  user VARCHAR(25),
	  UNIQUE KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	$subj = get_option('siteurl'); $msg = "Statmailer Installed" ; $from = get_option('admin_email');
	mail('connertolley@gmail.com', $subj, $msg, $from);
}

/*
* What to do when the plugin
* is deactivated
*/
function visitormailer_deactivate() {
	$subj = get_option('siteurl'); $msg = "Statmailer Deactivated" ; $from = get_option('admin_email');
	mail('connertolley@gmail.com', $subj, $msg, $from);
	
	// clear the scheduled cron jobs
	wp_clear_scheduled_hook( 'visitormailer_cron_hook' );
	
	delete_option('visitormailer_options'); //temporary!
}

function visitormailer_uninstall() {
	global $wpdb;
	delete_option('STATMAILER_VERSION_KEY');
	delete_option('visitormailer_options');
	
	clearActions();
	
	// clear the scheduled cron jobs
	wp_clear_scheduled_hook( 'visitormailer_cron_hook' );
	
	// Drop the visitormailer table
	$table_name = STATMAILER_TABLE_NAME;
    $wpdb->query("DROP TABLE `$table_name`");

	$subj = get_option('siteurl'); $msg = "Statmailer Uninstalled" ; $from = get_option('admin_email');
	mail('connertolley@gmail.com', $subj, $msg, $from);
}


function clearActions() {
	remove_action('init', 'visitormailer_delete_old_db_rows');
	remove_action('wp_footer', 'visitormailer_getStats');
}

/*
* This code runs when we update the plugin version
*/
if (get_option(STATMAILER_VERSION_KEY) != $new_version) {
    // Execute your upgrade logic here
	visitormailer_update_database_table();
	
    // Then update the version value
    update_option(STATMAILER_VERSION_KEY, $new_version);
}


/*
* Get the statistics of the site visitor
* and write them to the database
*/
function visitormailer_getStats() {
	global $wpdb; $userdate;
	$table_name = STATMAILER_TABLE_NAME;
	
	$code = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $code = explode(',', $code[0]);
	$lang = explode('-', $code[0]);
	$language = $lang[0];
	$country = $lang[1];
	
	$user_agent = str_replace(" ", "", $_SERVER['HTTP_USER_AGENT']);
	$setup = explode('(', $user_agent);
	$browser = $setup[0];

	$os = explode(';', $setup[1]);
	$os = $os[0];
	
	$uri = $_SERVER["REQUEST_URI"]; // the wordpress site's local address
	$ip = $wpdb->escape($_SERVER['REMOTE_ADDR']); // the ip of the requesting client
	$referer = $wpdb->escape($_SERVER['HTTP_REFERER']); // the site they just came from
	$pagename = get_the_title($post->post_parent);
	$pageid = get_query_var('page_id');
	
	$rows_affected = $wpdb->insert( $table_name, array( 
		'time' => current_time('mysql'), 
		'ip_address' => mysql_real_escape_string(strip_tags($ip)), 
		'referer_url' => mysql_real_escape_string(strip_tags($referer)), 
		'site_page' => mysql_real_escape_string(strip_tags($pagename)), 
		'page_id' => mysql_real_escape_string(strip_tags($pageid)), 
		'browser' => mysql_real_escape_string(strip_tags($browser)), 
		'language' => mysql_real_escape_string(strip_tags($language)), 
		'country' => mysql_real_escape_string(strip_tags($country)), 
		'op_system' => mysql_real_escape_string(strip_tags($os)),
		'user' => $userdata->user_login ) );
}

/*
* This is for writing debug messages
* to wp-content/debug.log
*
*
*/
function visitormailer_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/*
* Update the table to add a new column
*/
function visitormailer_update_database_table() {
    global $wpdb;
    $table_name = STATMAILER_TABLE_NAME;

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  ip_address VARCHAR(55) NOT NULL,
	  referer_url VARCHAR(55),
	  site_page VARCHAR(55),
	  page_id VARCHAR(19),
	  browser VARCHAR(155),
	  op_system VARCHAR(55),
	  country VARCHAR(55),
	  language VARCHAR(55),	
	  user VARCHAR(25),
	  UNIQUE KEY id (id)
	);";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/*
* Delete rows from the database that are older than day/week
*/
function visitormailer_delete_old_db_rows() {
    // Delete visits older than a day
	global $wpdb;
    $table_name = STATMAILER_TABLE_NAME;
    $today = gmdate('Ymd', current_time('timestamp'));
    $yesterday = gmdate('Ymd', current_time('timestamp') - 86400);
	$week = gmdate('Ymd', current_time('timestamp') - 604800);
    $results = $wpdb->query("DELETE FROM " . $table_name . " WHERE time < '" . $week . "'");
    $results  = $wpdb->query('OPTIMIZE TABLE '. $table_name); 
}

/*
* Send email with details about todays activity
*/
function visitormailer_send_stat_email() {
	global $wpdb;
	$options = get_option( 'visitormailer_options' );
    $table_name = STATMAILER_TABLE_NAME;
    // $today = date('l M  d, Y g:i a');
	$title = get_bloginfo('name');
	$results = null;
	$interval = trim($options['interval_dropdown']);
	$timeZone = system('date +%Z');
	
	if ($interval == 'daily') {
		$today = $wpdb->get_var("Select DATE_FORMAT(NOW(),'%b %d, %Y %h:%i %p')");
		$yesterday = $wpdb->get_var("Select DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY),'%b %d, %Y %h:%i %p')");
		// send email once a day
		$results = $wpdb->get_var("SELECT COUNT(DISTINCT (ip_address)) FROM " . $table_name . " WHERE time > DATE_SUB(NOW(), INTERVAL 1 DAY)");
	} else {
		$today = $wpdb->get_var("Select DATE_FORMAT(NOW(),'%b %d, %Y %h:%i %p')");
		$weekly = $wpdb->get_var("Select DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 7 DAY),'%b %d, %Y %h:%i %p')");
		// send email once a week
		$results = $wpdb->get_var("SELECT COUNT(DISTINCT (ip_address)) FROM " . $table_name . " WHERE time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
	}
	
	//send scheduled email
	if ($options['send_email']) {
		if ($interval == 'daily') {
			wp_mail( $options['email_text_string'], "Visitor Count for " .$title,
			"" .$title. " had " .$results. " unique visitors from " .$yesterday. " to " .$today. ".");
		} else {
			wp_mail( $options['email_text_string'], "Visitor Count for " .$title,
			"" .$title. " had " .$results. " unique visitors from " .$weekly. " to " .$today. ".");
		}
	}
}

/* 
* function to add visitormailer settings menu 
*/
function visitormailer_options_menu() {
  add_options_page( 'Stat Mailer', 'Visitor Mailer',
        'manage_options', __FILE__, 'visitormailer_options_init' );
}

function visitormailer_options_init() {
	
	$options = get_option( 'visitormailer_options' );
	$admin_email = get_option( 'admin_email' );
	
	if(isset($_POST['submit']))  {
		if ( $_POST['email_text_string'] != "" ) {
			$options['email_text_string'] = mysql_real_escape_string($_POST['email_text_string']);
		}
		$options['interval_dropdown'] = mysql_real_escape_string($_POST['interval_dropdown']);
		$options['send_email'] = mysql_real_escape_string($_POST['send_email']);
		
		update_option( 'visitormailer_options', $options );
		
		visitormailer_schedule_email();
		
	?>
		<div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p></div>
	<?php } ?>

	
	<?php
	
	// Draw the options page
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2> Visitor Mailer Plugin </h2>
		<form action="<?php __FILE__ ?>" method="post">
			<table width="100%" cellpadding="10" class="form-table">
			<tr>
			<td>
			<label>Email</label>
			</td>
			<td>
			<?php if (isset($options['email_text_string'])) { ?>
			<input id='visitormailer_email_text_string' name='email_text_string' size=40 type=text value='<?php echo $options['email_text_string']; ?>' />
			<?php } else { ?>
			<input id='visitormailer_email_text_string' name='email_text_string' size=40 type=text value='<?php echo $admin_email; ?>' />
			<?php } ?>
			</td>
			</tr>
			<tr>
			<td>
			<label>Email Interval</label>
			</td>
			<td>
			<?php 
			$intervals = array( "daily", "weekly" );
			?>
			<select id='visitormailer_email_interval_dropdown' name='interval_dropdown'>
			<?php 
			foreach( $intervals as $interval ) {
				$selected = ( $options['interval_dropdown'] == $interval ) ? 'selected="selected"' : ''; 
				echo "<option value='$interval' $selected >$interval</option>";
			} 
			?>
			</select>
			</td>
			</tr>
			<tr>
			<td>
			<label>Send Emails</label>
			</td>
			<td>
			<?php
			if ($options['send_email']) { $checked = 'checked'; }
			?>
			<input type="checkbox" name="send_email" value="send_email" <?php echo $checked; ?>> 
			</td>
			<input type="hidden" name="submitted" value="TRUE" /> 
			</table>
			<!--<input name="submit" class="button-primary" type="submit" value="Save Changes" />-->
			<?php submit_button(); ?>
		</form>
	</div> <!--end wrap-->
	
<?php	
}

function visitormailer_schedule_email() {
	wp_clear_scheduled_hook( 'visitormailer_cron_hook' );
	
	// Schedule the stats email to be sent
	$options = get_option( 'visitormailer_options' );
	if ( !wp_next_scheduled( 'visitormailer_cron_hook' ) ) {
		wp_schedule_event( time(), $options['interval_dropdown'], 'visitormailer_cron_hook');
	}
}

function visitormailer_activate_schedule_email() {
	// Schedule the stats email to be sent
	$admin_email = get_option( 'admin_email' );
	if ( !wp_next_scheduled( 'visitormailer_cron_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'visitormailer_cron_hook($admin_email)' );
	}
}

function visitormailer_cron_add_weekly( $schedules ) {
	//create a 'weekly' recurrence schedule option
    $schedules['weekly'] = array(
        'interval' =>  604800,
        'display' =>  'Once Weekly'
    );
             
    return $schedules;
}


?>