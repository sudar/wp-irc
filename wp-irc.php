<?php
/*
Plugin Name: WP IRC
Plugin Script: wp-irc.php
Plugin URI: http://sudarmuthu.com/wordpress/wp-irc
Description: Retrieves the number of people who are online in an IRC Channel, which can be displayed in the sidebar using a widget.
Version: 0.3
License: GPL
Author: Sudar
Author URI: http://sudarmuthu.com/ 

=== RELEASE NOTES ===
2009-07-29 - v0.1 - first version
2012-01-31 - v0.2 - Fixed issue with textarea in the widget
*/
/*  Copyright 2009  Sudar Muthu  (email : sudar@sudarmuthu.com)

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

// so that the script doesn't timeout
set_time_limit(0);

global $wpdb;

global $smirc_db_version;
$smirc_db_version = "0.1";

global $smirc_table_name;
$smirc_table_name = $wpdb->prefix . "irc_alerts";

/**
* Guess the wp-content and plugin urls/paths
*/
if ( !defined('WP_CONTENT_URL') )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

if (!defined('PLUGIN_URL'))
    define('PLUGIN_URL', WP_CONTENT_URL . '/plugins/');
if (!defined('PLUGIN_PATH'))
    define('PLUGIN_PATH', WP_CONTENT_DIR . '/plugins/');

define('SM_IRC_INC_URL', PLUGIN_URL . dirname(plugin_basename(__FILE__)) . '/wp-irc-ajax.php');

/**
 * Request Handler
 */
if (!function_exists('smirc_request_handler')) {
    function smirc_request_handler() {

        if ($_POST['smirc_action'] == "update wp irc") {

            check_admin_referer( 'wp-irc-update-config');
            $smirc_settings = array();

            $smirc_settings["smirc_server"] = sanitize_user($_POST['smirc_server'], true);
            $smirc_settings["smirc_port"] = absint($_POST['smirc_port']);
            $smirc_settings["smirc_channel"] = '#' . sanitize_user($_POST['smirc_channel'], true);
            $smirc_settings["smirc_nickname"] = sanitize_user($_POST['smirc_nickname'], true);
            $smirc_settings['smirc_interval'] = absint($_POST['smirc_interval']);
            $smirc_settings['smirc_enable_alert'] = $_POST['smirc_enable_alert'];

            update_option("smirc_settings", $smirc_settings);

            // Manually fire the event when values are changed
            smirc_event_function();

            // hook the admin notices action
            add_action( 'admin_notices', 'smirc_change_notice', 9 );
        }
    }
}

function smirc_change_notice() {
    echo '<br clear="all" /> <div id="message" class="updated fade"><p><strong>Option saved. </strong></p></div>';
}

/**
 * Show the Admin page
 */
if (!function_exists('smirc_displayOptions')) {
    function smirc_displayOptions() {

        $smirc_settings = get_option("smirc_settings");

        $smirc_server_v = $smirc_settings["smirc_server"];
        $smirc_port_v = $smirc_settings["smirc_port"];
        $smirc_channel_v = sanitize_user($smirc_settings["smirc_channel"], true);
        $smirc_nickname_v = $smirc_settings["smirc_nickname"];
        $smirc_interval_v = $smirc_settings['smirc_interval'];
        $smirc_enable_alert_v = $smirc_settings['smirc_enable_alert'];
        
        if ($smirc_server_v == "") {
            $smirc_server_v = "irc.freenode.net";
        }

        if ($smirc_port_v == "") {
            $smirc_port_v = "6667";
        }

        if ($smirc_channel_v == "") {
            $smirc_channel_v = "wordpress";
        }

        if ($smirc_nickname_v == "") {
            $smirc_nickname_v = "wp-irc-bot";
        }

        if ($smirc_interval_v == '') {
            $smirc_interval_v = 5;
        }

        if ($smirc_enable_alert_v == '') {
            $smirc_enable_alert_v = false;
        }

		print('<div class="wrap">');
		print('<h2>WP IRC Options</h2>');
        
        print ('<form name="smirc_form" action="'. get_bloginfo("wpurl") . '/wp-admin/options-general.php?page=wp-irc.php' .'" method="post">');
?>
        <fieldset class="options">
        
		<table class="optiontable">
		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_server">Server</label>
		 	</th>
		 	<td>
		 		<input name="smirc_server"  id="smirc_server" value = "<? echo $smirc_server_v; ?>" size="50" />
		 	</td>
		 </tr>
		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_port">Port</label>
		 	</th>
		 	<td>
		 		<input name="smirc_port"  id="smirc_port" value = "<? echo $smirc_port_v; ?>" size="6" />
		 	</td>
		 </tr>
		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_channel">Channel</label>
		 	</th>
		 	<td>
		 		#<input name="smirc_channel"  id="smirc_channel" value = "<? echo $smirc_channel_v; ?>" size="25" />
		 	</td>
		 </tr>
		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_nickname">Nickname</label>
		 	</th>
		 	<td>
		 		<input name="smirc_nickname"  id="smirc_nickname" value = "<? echo $smirc_nickname_v; ?>" size="25" />
		 	</td>
		 </tr>

		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_interval">Update Interval</label>
		 	</th>
		 	<td>
		 		<input name="smirc_interval"  id="smirc_interval" value = "<? echo $smirc_interval_v; ?>" size="5" /> Minutes
		 	</td>
		 </tr>

		 <tr>
		 	<th scope="row" >
		 	 	<label for="smirc_enable_alert">Enable Alerts</label>
		 	</th>
		 	<td>
                <input name="smirc_enable_alert"  id="smirc_enable_alert" type="checkbox" value = "true" <? echo checked(true, $smirc_enable_alert_v) ?>  /> (If enabled users can subscribe for alerts)
		 	</td>
		 </tr>

		</table>

		</fieldset>
        <p class="submit">
				<input type="submit" name="submit" value="Save &raquo;">        
        </p>

		<input type="hidden" name="smirc_action" value="update wp irc" />
<?php wp_nonce_field('wp-irc-update-config'); ?>
		</form>
		</div>
        
<?php
    // Display credits in IRCter
    add_action( 'in_admin_ircter', 'smirc_admin_footer' );

    }
}



/**
 * Event Hook
 */
function smirc_event_function() {

    global $wpdb;
    global $smirc_table_name;

    $smirc_settings = get_option("smirc_settings");

    $smirc_server_v = $smirc_settings["smirc_server"];
    $smirc_port_v = $smirc_settings["smirc_port"];
    $smirc_channel_v = $smirc_settings["smirc_channel"];
    $smirc_nickname_v = $smirc_settings["smirc_nickname"];
    $smirc_enable_alert_v = $smirc_settings['smirc_enable_alert'];

    $users_count = smirc_get_irc_channel_users(
        $smirc_server_v,
        $smirc_port_v,
        $smirc_channel_v,
        $smirc_nickname_v
        );

    update_option("smirc_wp_irc_users", $users_count);

    if ($smirc_enable_alert) {
        $alerts = $wpdb->get_results("select * from $smirc_table_name where alert_sent = 0 and alert_count <= $users_count");

        if (count($alerts) > 0) {
            foreach ($alerts as $alert) {
                smirc_send_alert_email($alert, $users_count);

    //            $wpdb->update($smirc_table_name, array('alert_sent' => '1', 'alert_sent_on' => 'NOW()'), array('id' => $alert->id));
                $wpdb->query("update $smirc_table_name set
                                    alert_sent = '1',
                                    alert_sent_on = NOW() where id = $alert->id");
            }
        }
    }
}

function smirc_send_alert_email($alert, $user_count) {
    $smirc_settings = get_option("smirc_settings");

    $subject = "There are $user_count users in " . $smirc_settings['smirc_channel'] . ' channel at ' . $smirc_settings['smirc_server'];
    $message = $subject;

    wp_mail($alert->email, $subject, $message);
}

/**
 * add alert to the database
 * @global <type> $wpdb
 * @global <type> $smirc_table_name
 * @param <type> $alert_name
 * @param <type> $alert_count
 * @param <type> $alert_email
 * @param <type> $alert_mobile
 * @return <type>
 */
function smirc_add_alert($alert_name, $alert_count, $alert_email, $alert_mobile) {
    global $wpdb;
    global $smirc_table_name;

    // if 2.7
//    $wpdb->insert($smirc_table_name, array(
//            'name' => $alert_name,
//            'alert_count' => $alert_count,
//            'email' => $alert_email,
//            'mobile' => $alert_mobile
//        ));

    $query = "Insert into $smirc_table_name (name, alert_count, email) values (
        '$alert_name',
        '$alert_count',
        '$alert_email'
    )    ";

    $wpdb->query($query);
    return 'Alert Added';
}

function smirc_scripts() {
    wp_enqueue_script('jquery');
}

/**
 * 
 */
if(!function_exists('smirc_add_menu')) {
	function smirc_add_menu() {
	    
	    //Add a submenu to Options
        add_options_page("WP IRC", "WP IRC", 8, basename(__FILE__), "smirc_displayOptions");
	}
}

/**
 * Schdule the event
 */
function smirc_install ()     {

    global $wpdb;
    global $smirc_table_name;

   if($wpdb->get_var("show tables like '$smirc_table_name'") != $smirc_table_name) {

      $sql = "CREATE TABLE " . $smirc_table_name . " (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          name VARCHAR(20) NOT NULL ,
          alert_count int(4) NOT NULL ,
          email VARCHAR(100) NULL,
          alert_created_on timestamp default CURRENT_TIMESTAMP,
          alert_sent_on timestamp NULL,
          alert_sent CHAR(1) NOT NULL default '0',
          UNIQUE KEY id (id)
        )";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      add_option("smmf_db_version", $smirc_db_version);
   }

    // Schedule the event
	wp_schedule_event( time(), 'wp-irc', 'smirc_event_function_hook' );
}

/**
 * Add a new value to control schedules
 * @return <type> 
 */
function smirc_add_schedule() {
    $smirc_settings = get_option("smirc_settings");

    if ($smirc_settings['smirc_interval'] == '') {
        $interval = 300;
    } else {
        $interval = absint($smirc_settings['smirc_interval'] * 60);
    }

    return array (
      'wp-irc' =>  array('interval'=>$interval, 'display'=>"wp-irc config time")
    );
}

/**
 * When uninstalled
 */
function smirc_uninstall ()     {
	remove_action('smirc_event_function_hook', 'smirc_event_function');
	wp_clear_scheduled_hook('smirc_event_function_hook');
}

/**
 * Adds the settings link in the Plugin page. Based on http://striderweb.com/nerdaphernalia/2008/06/wp-use-action-links/
 * @staticvar <type> $this_plugin
 * @param <type> $links
 * @param <type> $file
 */
function smirc_filter_plugin_actions($links, $file) {
    static $this_plugin;
    if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

    if( $file == $this_plugin ) {
        $settings_link = '<a href="options-general.php?page=wp-irc.php">' . _('Manage') . '</a>';
        array_unshift( $links, $settings_link ); // before other links
    }
    return $links;
}

/**
 * Adds IRCter links. Based on http://striderweb.com/nerdaphernalia/2008/06/give-your-wordpress-plugin-credit/
 */
function smirc_admin_footer() {
	$plugin_data = get_plugin_data( __FILE__ );
    printf('%1$s ' . __("plugin") .' | ' . __("Version") . ' %2$s | '. __('by') . ' %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']);
}

add_filter( 'plugin_action_links', 'smirc_filter_plugin_actions', 10, 2 );

//add_action('activity_box_end', 'smirc_wp_irc_stats');
add_action('smirc_event_function_hook', 'smirc_event_function');
add_action('plugins_loaded', 'smirc_widget_wp_irc_init');
add_action('admin_menu', 'smirc_add_menu');
add_action('init', 'smirc_request_handler');

register_activation_hook(__FILE__,'smirc_install');
register_deactivation_hook(__FILE__, 'smirc_uninstall');

add_filter('cron_schedules', 'smirc_add_schedule');
add_action( 'wp_print_scripts', 'smirc_scripts');
add_action( 'wp_head', 'smirc_head' );

/**
 * The main Plugin class
 *
 * @package WP IRC
 * @subpackage default
 * @author Sudar
 */
class WP_IRC {

    private $version = "0.3";
    private $js_handle = "wp-irc";
    private $js_variable = "WPIRC";
    private $refresh_nonce = "wp-irc-refresh-count";

    /**
     * Initalize the plugin by registering the hooks
      */
    function __construct() {

        // Load localization domain
        load_plugin_textdomain( 'wp-irc', false, dirname(plugin_basename(__FILE__)) .  '/languages' );

        // Register hooks
        add_action('wp_enqueue_scripts', array(&$this, 'add_script'));
        
        add_action('wp_ajax_refresh_count', array(&$this, 'refresh_count'));
        add_action('wp_ajax_nopriv_refresh_count', array(&$this, 'refresh_count'));
    }

    /**
     * Refresh count for a widget
     *
     * @return void
     */
    function refresh_count() {
            
        if ( ! wp_verify_nonce( $_POST['nonce'], $this->refresh_nonce ) ) {
            die ( 'Are you trying something funny?');
        }

        header( "Content-Type: application/json" );

        $widget_id = absint($_POST['widget_id']);
        $option = get_option('widget_irc_widget');
        $instance = $option[$widget_id];

        $count = IRC::get_irc_channel_users($instance['server'], $instance['port'], $instace['channel'], $instance['nickname']);

        $content = str_replace("[count]", $count, $instance['content']);
        $content = str_replace("[channel]", $instance["channel"], $content);
        $content = str_replace("[server]", $instance["server"], $content);

        // generate the response
        $response = json_encode( array( 'success' => true, 'content' => $content ) );

        echo $response;
    
        exit;
    }

    /**
     * Add the requried JavaScript files
     */
    function add_script() {
        wp_enqueue_script($this->js_handle, plugins_url('/js/wp-irc.js', __FILE__), array('jquery'), $this->version, TRUE);

        // AJAX messages
        $msg = array(
            'refreshcountfailed' => __('Unable to fetch user count. Kindly try after sometime', 'wp-irc')
        );
        $translation_array = array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'refreshNonce' => wp_create_nonce($this->refresh_nonce), 'msg' => $msg );
        wp_localize_script( $this->js_handle, $this->js_variable, $translation_array );
    }

    // PHP4 compatibility
    function WP_IRC() {
        $this->__construct();
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'WP_IRC' ); function WP_IRC() { global $WP_IRC; $WP_IRC = new WP_IRC(); }


/**
 * Adds IRC_Widget widget
 *
 * @package default
 * @subpackage default
 * @author Sudar
 */
class IRC_Widget extends WP_Widget {

    /**
    * Register widget with WordPress.
    */
    public function __construct() {
        parent::__construct(
            'irc_widget', // Base ID
            'IRC_Widget', // Name
            array( 'description' => __( 'An IRC Widget', 'wp-irc' ), ) // Args
        );
    }

    /**
    * Front-end display of widget.
    *
    * @see WP_Widget::widget()
    *
    * @param array $args     Widget arguments.
    * @param array $instance Saved values from database.
    */
    public function widget( $args, $instance ) {
        extract( $args );
        $title = apply_filters( 'widget_title', $instance['title'] );

        echo $before_widget;
        if ( ! empty( $title ) ) {
            echo $before_title . $title . $after_title;
        }
        //echo $this->id . "<br>";
        $this->getWidgetContent($instance);
        echo $after_widget;
    }

    /**
     * Get the content for the widget
     *
     */
    private function getWidgetContent($instance) {
?>
        <div id = "<?php echo $this->id; ?>" class = "irc_widget_id">
            <img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading list-ajax-loading" alt="" />
        </div>
<?php      
        if ($instance['alerts']) {
?>
            <p><a id = "get_alert" href="#">Get alert when ..</a></p>
            <div id="irc_alert">
                <form method="post" action = "<?php echo SM_IRC_INC_URL; ?>" id="smirc_alert_form">
                    <label for ="alert_count">users count reach more than <input type ="text" name = "alert_count" id="alert_count" size="5" maxlength="5" /></label> <br /><br />
                    <label for ="alert_name">Your name <input type ="text" name = "alert_name" id="alert_name" size="20" maxlength="25" /></label> <br />
                    <label for ="alert_email">Email &nbsp; &nbsp; &nbsp; &nbsp; <input type ="text" name = "alert_email" id="alert_email" size="20" maxlength="30" /></label><br />
                    <input type ="hidden" id="wp-irc-action" name = "wp-irc-action" value="wp-irc-add-alert" />
                    <input type ="button" id="wp-irc-submit" name = "wp-irc-submit" value="Get Alert" />
                </form>
            </div>
<?php
        }
    }

    /**
    * Sanitize widget form values as they are saved.
    *
    * @see WP_Widget::update()
    *
    * @param array $new_instance Values just sent to be saved.
    * @param array $old_instance Previously saved values from database.
    *
    * @return array Updated safe values to be saved.
    */
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        $instance['title'] = strip_tags( $new_instance['title'] );
        $instance['server'] = strip_tags( $new_instance['server'] );
        $instance['port'] = intval( $new_instance['port'] );
        $instance['channel'] = strip_tags( $new_instance['channel'] );
        $instance['nickname'] = strip_tags( $new_instance['nickname'] );
        $instance['content'] = ( $new_instance['content'] );
        $instance['interval'] = intval( $new_instance['interval'] );
        $instance['alerts'] = (bool) $new_instance['alerts'];

        return $instance;
    }

    /**
    * Back-end widget form.
    *
    * @see WP_Widget::form()
    *
    * @param array $instance Previously saved values from database.
    */
    public function form( $instance ) {
        if ( isset( $instance[ 'title' ] ) ) {
            $title = $instance[ 'title' ];
        }
        else {
            $title = __( 'New title', 'wp-irc' );
        }

        if ( isset( $instance[ 'content' ] ) ) {
            $content = $instance[ 'content' ];
        }
        else {
            $content = __( 'There are currently [count] people in [channel]', 'wp-irc' );
        }

        if ( isset( $instance[ 'server' ] ) ) {
            $server = $instance[ 'server' ];
        }
        else {
            $server = __( 'irc.freenode.net', 'wp-irc' );
        }

        if ( isset( $instance[ 'port' ] ) ) {
            $port = $instance[ 'port' ];
        }
        else {
            $port = __( '6667', 'wp-irc' );
        }

        if ( isset( $instance[ 'channel' ] ) ) {
            $channel = $instance[ 'channel' ];
        }
        else {
            $channel = __( 'wordpress', 'wp-irc' );
        }

        if ( isset( $instance[ 'nickname' ] ) ) {
            $nickname = $instance[ 'nickname' ];
        }
        else {
            $nickname = __( 'wp-irc-bot', 'wp-irc' );
        }

        if ( isset( $instance[ 'interval' ] ) ) {
            $interval = $instance[ 'interval' ];
        }
        else {
            $interval = __( '5', 'wp-irc' );
        }

        if ( isset( $instance[ 'alerts' ] ) ) {
            $alerts = $instance[ 'alerts' ];
        }
        else {
            $alerts = FALSE;
        }

?>
        <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'server' ); ?>"><?php _e( 'Server:' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'server' ); ?>" name="<?php echo $this->get_field_name( 'server' ); ?>" type="text" value="<?php echo esc_attr( $server ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'port' ); ?>"><?php _e( 'Port:' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'port' ); ?>" name="<?php echo $this->get_field_name( 'port' ); ?>" type="text" value="<?php echo esc_attr( $port ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'channel' ); ?>"><?php _e( 'Channel:' ); ?></label> 
        #<input class="widefat" id="<?php echo $this->get_field_id( 'channel' ); ?>" name="<?php echo $this->get_field_name( 'channel' ); ?>" type="text" value="<?php echo esc_attr( $channel ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'nickname' ); ?>"><?php _e( 'Nickname:' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'nickname' ); ?>" name="<?php echo $this->get_field_name( 'nickname' ); ?>" type="text" value="<?php echo esc_attr( $nickname ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'content' ); ?>"><?php _e( 'Content:' ); ?></label> 
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>" name="<?php echo $this->get_field_name( 'content' ); ?>" ><?php echo esc_attr( $content ); ?></textarea>
        <?php _e('You can use the following template tags [count], [channel], [server]'); ?>
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'interval' ); ?>"><?php _e( 'Interval: (in minutes)' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'interval' ); ?>" name="<?php echo $this->get_field_name( 'interval' ); ?>" type="text" value="<?php echo esc_attr( $interval ); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'alerts' ); ?>"><?php _e( 'Enable Alerts:' ); ?></label> 
        <input class="checkbox" id="<?php echo $this->get_field_id( 'alerts' ); ?>" name="<?php echo $this->get_field_name( 'alerts' ); ?>" type="checkbox" value="true" <?php checked($alerts, true);  ?> />
        </p>

<?php 
    }

} // class IRC_Widget

// register IRC_Widget widget
add_action( 'widgets_init', create_function( '', 'register_widget( "IRC_Widget" );' ) );

/**
 * IRC Class
 */
class IRC {

/**
 * Get the list of users who are active in a IRC Channel
 *
 * @param <type> $server
 * @param <type> $port
 * @param <type> $channel
 * @param <type> $nickname
 * @return <type>
 */
    static function get_irc_channel_users($irc_server, $port, $channel, $nickname = "wp-irc-bot") {
        $server = array(); //we will use an array to store all the server data.
        $count = 0;
        //Open the socket connection to the IRC server
        $fp = fsockopen($irc_server, $port, $errno, $errstr);
        if($fp) {
            //Ok, we have connected to the server, now we have to send the login commands.

            @fwrite($fp, "PASS NOPASS\n\r", strlen("PASS NOPASS\n\r")); //Sends the password not needed for most servers
            @fwrite($fp, "NICK $nickname\n\r", strlen("NICK $nickname\n\r")); //sends the nickname
            @fwrite($fp, "USER $nickname USING WP IRC Plugin\n\r", strlen("USER $nickname USING WP IRC Plugin\n\r"));

            $names = "";
            while(!feof($fp)) //while we are connected to the server
            {
                $server['READ_BUFFER'] = fgets($fp, 1024); //get a line of data from the server
    //            echo "[RECIVE] ".$server['READ_BUFFER']."<br>\n\r"; //display the recived data from the server

                //Now lets check to see if we have joined the server
                if(strpos($server['READ_BUFFER'], "/MOTD")){
                    //MOTD (The last thing displayed after a successful connection)
                    //If we have joined the server
                    @fwrite($fp, "LIST $channel\n\r", strlen("LIST $channel\n\r")); //get information about the chanel
                }

                if (strpos($server['READ_BUFFER'], "322")) { // Result for LIST Command
                    preg_match("/$channel ([0-9]+)/", $server['READ_BUFFER'], $matches);
    //            	echo "count : " . $matches[1] . "<br>\n\r";
                    $count = $matches[1];
                }
                
                if(strpos($server['READ_BUFFER'], "/LIST")) { //End of LIST
                    //Get the list of users from channel
                    @fwrite($fp, "QUIT\n\r", strlen("QUIT\n\r")); //Quit the channel
    //                @fwrite($fp, "NAMES $channel\n\r", strlen("NAMES $channel\n\r")); //get information about the chanel
                }
                //TODO: Need to get the list of people who are active. There seems to be some problem with the below code. Need to debug it
    //            if (strpos($server['READ_BUFFER'], "353")) { // Result for NAMES Command
    //            	$names .= substr($server['READ_BUFFER'], strpos($server['READ_BUFFER'], ":", 2) + 1);
    //            }

    //            if(strpos($server['READ_BUFFER'], "/NAMES")) { //End of Names
                    //Quit the chanel
    //                @fwrite($fp, "QUIT\n\r", strlen("QUIT\n\r"));
    //            }

                if(substr($server['READ_BUFFER'], 0, 6) == "PING :") {//If the server has sent the ping command
                    //Reply with pong
                    @fwrite($fp, "PONG :".substr($server['READ_BUFFER'], 6)."\n\r", strlen("PONG :" . substr($server['READ_BUFFER'], 6) . "\n\r"));
                    //As you can see i dont have it reply with just "PONG"
                    //It sends PONG and the data recived after the "PING" text on that recived line
                    //Reason being is some irc servers have a "No Spoof" feature that sends a key after the PING
                    //Command that must be replied with PONG and the same key sent.
                }
                flush(); //This flushes the output buffer forcing the text in the while loop to be displayed "On demand"
            }
        // close the socket
        fclose($fp);
        } else {
            // If there is some error
            //TODO:Include better error handling
        }
        return $count;
    }
}
?>
