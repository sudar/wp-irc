<?php
if (isset ($_POST['wp-irc-action']) && $_POST['wp-irc-action'] == 'wp-irc-add-alert') {
    require_once(dirname(__FILE__) . '/../../../wp-config.php');
    require_once( dirname(__FILE__) . '/wp-irc.php' );

    $alert_name = sanitize_user($_POST['alert_name']);
    $alert_count = absint($_POST['alert_count']);
    $alert_email = sanitize_email($_POST['alert_email']);

//    header('Content-type: text/xml');
    echo smirc_add_alert($alert_name, $alert_count, $alert_email, $alert_mobile);
    die();
} else {
    die();
}
?>