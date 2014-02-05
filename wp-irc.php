<?php
/**
Plugin Name: WP IRC
Plugin Script: wp-irc.php
Plugin URI: http://sudarmuthu.com/wordpress/wp-irc
Description: Retrieves the number of people who are online in an IRC Channel, which can be displayed in the sidebar using a widget.
Version: 1.2.1
License: GPL
Author: Sudar
Author URI: http://sudarmuthu.com/ 
Donate Link: http://sudarmuthu.com/if-you-wanna-thank-me
Text Domain: bulk-delete
Domain Path: languages/

=== RELEASE NOTES ===
Check readme file for full release notes
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

//TODO: Create a settings page where people can test connection
//TODO: Add support for alerts
//TODO: Add support for shortcode

// so that the script doesn't timeout
set_time_limit(0);

/**
 * The main Plugin class
 *
 * @package WP IRC
 * @author Sudar
 */
class WP_IRC {

    private $version       = '1.2.1';
    private $js_handle     = "wp-irc";
    private $js_variable   = "WPIRC";
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

        if (false === ($users = get_transient($this->js_handle . $widget_id))) {
            $users = IRC::get_irc_channel_users($instance['server'], $instance['port'], $instance['channel'], $instance['nickname'], $instance['password'], $instance['verbose']);
            set_transient($this->js_handle . $widget_id, $users, $instance['interval']); 
        }

        $content = $instance['content'];
        if (is_array($users)) {
            $content = str_replace("[users]", implode(" ",$users), $content);
        }
        $content = str_replace("[count]", count($users), $content);
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

        // JavaScript messages
        $msg = array(
            'refreshcountfailed' => __('Unable to fetch user count. Kindly try after sometime', 'wp-irc')
        );
        $translation_array = array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'refreshNonce' => wp_create_nonce($this->refresh_nonce), 'msg' => $msg );
        wp_localize_script( $this->js_handle, $this->js_variable, $translation_array );
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'WP_IRC' ); function WP_IRC() { global $WP_IRC; $WP_IRC = new WP_IRC(); }

/**
 * Adds IRC_Widget widget
 *
 * @package default
 * @author Sudar
 */
class IRC_Widget extends WP_Widget {

    /**
     * Register widget with WordPress.
     */
    public function __construct() {
        parent::__construct(
            'irc_widget', // Base ID
            __( 'WP irc Widget' ), // Name
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
            <img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading list-ajax-loading" alt="content-loading" />
        </div>
<?php      
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
        $instance['password'] = strip_tags( $new_instance['password'] );
        
        if( strip_tags( $new_instance['verbose'] ) == "verbose") {
            $instance['verbose'] = true;
        } else {
            $instance['verbose'] = false;
        }
        
        $instance['content'] = ( $new_instance['content'] );
        $instance['interval'] = intval( $new_instance['interval'] );

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
        } else {
            $title = __( 'New title', 'wp-irc' );
        }

        if ( isset( $instance[ 'content' ] ) ) {
            $content = $instance[ 'content' ];
        } else {
            $content = __( 'There are currently [count] people in [channel]: [users]', 'wp-irc' );
        }

        if ( isset( $instance[ 'server' ] ) ) {
            $server = $instance[ 'server' ];
        } else {
            $server = __( 'irc.freenode.net', 'wp-irc' );
        }

        if ( isset( $instance[ 'port' ] ) ) {
            $port = $instance[ 'port' ];
        } else {
            $port = __( '6667', 'wp-irc' );
        }

        if ( isset( $instance[ 'channel' ] ) ) {
            $channel = $instance[ 'channel' ];
        } else {
            $channel = __( 'wordpress', 'wp-irc' );
        }

        if ( isset( $instance[ 'nickname' ] ) ) {
            $nickname = $instance[ 'nickname' ];
        } else {
            $nickname = __( 'wp-irc-bot', 'wp-irc' );
        }
        
        if ( isset( $instance[ 'password' ] ) ) {
            $password = $instance[ 'password' ];
        } else {
            $password = __( '', 'wp-irc' );
        }     
        
        if ( isset( $instance[ 'verbose' ] ) ) {
            $verbose = $instance[ 'verbose' ];
        } else {
            $verbose = __( 'false', 'wp-irc' );
        }        

        if ( isset( $instance[ 'interval' ] ) ) {
            $interval = $instance[ 'interval' ];
        } else {
            $interval = __( '300', 'wp-irc' );
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
        <label for="<?php echo $this->get_field_id( 'password' ); ?>"><?php _e( 'Passsword (only enter if required):' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'password' ); ?>" name="<?php echo $this->get_field_name( 'password' ); ?>" type="text" value="<?php echo esc_attr( $password ); ?>" />
        </p>        

        <p>
        <label for="<?php echo $this->get_field_id( 'content' ); ?>"><?php _e( 'Content:' ); ?></label> 
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>" name="<?php echo $this->get_field_name( 'content' ); ?>" ><?php echo esc_attr( $content ); ?></textarea>
        <?php _e('You can use the following template tags [users], [count], [channel], [server]'); ?>
        </p>

        <p>
        <?php _e('If set to true, the bot joins the channel to see invisible users'); ?>
        <br />
        <input id="<?php echo $this->get_field_id( 'verbose' ); ?>" name="<?php echo $this->get_field_name( 'verbose' ); ?>" type="checkbox" <?php if($verbose) {echo "checked";} ?> value="verbose"><?php _e( ' Verbose' ); ?></input>
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'interval' ); ?>"><?php _e( 'Interval: (in seconds)' ); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id( 'interval' ); ?>" name="<?php echo $this->get_field_name( 'interval' ); ?>" type="text" value="<?php echo esc_attr( $interval ); ?>" />
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
    static function is_irc_command_error($command) {
        if( intval($command) >= 400 && intval($command) <= 599 ) // 400 to 599 - Error commands
            return true;
            
        if( (strpos($command, "ERR_") !== false) ) // Errors start with ERR_
            return true;
            
        return false;
    }
 
    static function send_irc_command($socket, $command) {
        $command = $command."\n\r";
        
        if($socket)
            @fwrite($socket, $command, strlen($command));
    }
    
    static function connection_valid($socket) {
        flush(); 
        return feof($socket) ? false : true;
    }
 
    static function get_irc_channel_users($irc_server, $port, $channel, $nickname = "wp-irc-bot", $password = "", $verbose = false) {
        $retVal = array();

        // Check if channel has # in front or not
        if ( '#' != $channel[0] ) {
            $channel = '#' . $channel;
        }

        // Connect to server
        $socket = fsockopen($irc_server, $port, $errno, $errstr);
        
        // Return if failed
        if(!$socket) {
            error_log("[WP-IRC] Could not connect to server");            
            return null;
        }
        
        // Login at server
        if( strlen($password) > 0 )                                     // Password if given
            IRC::send_irc_command($socket, "PASS $password"); 
            
        IRC::send_irc_command($socket, "NICK $nickname");               // Nickname
        IRC::send_irc_command($socket, "USER $nickname 0 * WPIRC");     // RFC2812: <user> <mode> <unused> <realname>

        // Receive package loop
        while(IRC::connection_valid($socket)) {
            // Read command
            $messageData = fgets($socket, 512); // Max size (RFC2812)
            $message = explode(' ', $messageData ); // Delimiter is space
            
            $prefix = "";
            $command = "";
            $target = "";
            
            // Invalid message
            if( count($message) <= 0 )
                continue;   

            // Get prefix
            if( strpos($message[0], ":") !== false ) {
                $prefix = $message[0];
                array_splice($message, 0, 1);
            }
        
            // Get command
            $command = $message[0];
            array_splice($message, 0, 1);
            
            // Get target
            $target = $message[0];
            array_splice($message, 0, 1);
        
            // Check if error
            if( IRC::is_irc_command_error($command) ) {
                error_log("[WP-IRC] ".$messageData);
                return null;
            }
            
            // Message handling  
            {
                // End of MOTD
                if( $command == "RPL_ENDOFMOTD" || intval($command) == 376 ) {
                     if( $verbose ) // Server sends NAMES reply automatically on JOIN
                        IRC::send_irc_command($socket, "JOIN $channel"); // JOIN channel
                     else
                        IRC::send_irc_command($socket, "NAMES $channel"); // Request NAMES           
                }
                
                // Result of NAMES request
                if( $command == "RPL_NAMREPLY" || intval($command) == 353 ) {
                    array_splice($message, 0, 1); // Ignore channel type 
                    array_splice($message, 0, 1); // Ignore channel name
                    
                    // Iterate names
                    foreach($message as $name) {
                        // Remove introducing : on first name if neccesary
                        if( strpos($name, ":") !== false )
                            $name = substr($name, 1);
                            
                        // Remove flags
                        if( strpos($name, "@") !== false || strpos($name, "+") !== false )
                            $name = substr($name, 1);
                            
                        // Add if not self                        
                        if( $name != $nickname )
                            array_push($retVal, $name);
                    }
                }
      
                // End of NAMES request
                if( $command == "RPL_ENDOFNAMES" || intval($command) == 366 ) {
                    if( $verbose )
                        IRC::send_irc_command($socket, "PART $channel"); // PART channel
                        
                    IRC::send_irc_command($socket, "QUIT"); // Close session
                }  
                
                // Server acknoledged QUIT
                if( $command == "ERROR")                
                    break;
            }   
        }

        // Close socket
        fclose($socket); 
        
        return $retVal;
    }
}
?>
