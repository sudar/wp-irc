<?php
/*
Plugin Name: WP IRC
Plugin Script: wp-irc.php
Plugin URI: http://sudarmuthu.com/wordpress/wp-irc
Description: Retrieves the number of people who are online in an IRC Channel, which can be displayed in the sidebar using a widget.
Version: 1.0
License: GPL
Author: Sudar
Author URI: http://sudarmuthu.com/ 

=== RELEASE NOTES ===
2009-07-29 - v0.1 - first version
2012-01-31 - v0.2 - Fixed issue with textarea in the widget
2013-01-21 - v1.0 - (Dev Time: 20 hours)
                  - Complete rewrite and added support for AJAX
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
//TODO: Add support for caching
//TODO: Honor refresh interval
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
            $content = __( 'There are currently [count] people in [channel]', 'wp-irc' );
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

        if ( isset( $instance[ 'interval' ] ) ) {
            $interval = $instance[ 'interval' ];
        } else {
            $interval = __( '5', 'wp-irc' );
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
        //TODO: Clean up this function
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
