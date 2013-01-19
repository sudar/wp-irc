/**
 * Script file needed by WP-IRC Plugin
 */
/*global WPIRC, jQuery, document*/
jQuery(document).ready(function () {

    // Refresh count
    jQuery('.irc_widget_id').each(function () {
        var $this = jQuery(this),
            widget_id = ($this.attr('id')).split('-')[1];

        jQuery.post(WPIRC.ajaxurl, {
            action: 'refresh_count',
            widget_id: widget_id,
            nonce: WPIRC.refreshNonce
        }, function (response) {
            if (response.success) {
                $this.html(response.content);
            } else {
                $this.html(WPIRC.msg.refreshcountfailed);
            }
        });
    });


    jQuery("#irc_alert").hide();

    jQuery("#get_alert").click(function (e) {
        jQuery("#irc_alert").toggle();
        e.preventDefault();
    });

    jQuery("#wp-irc-submit").click(function () {
        //TODO: validation
        jQuery.post("<?php echo SM_IRC_INC_URL; ?>", jQuery("#smirc_alert_form").serialize(), function (result) {
            jQuery("#irc_alert").html(result);
        });
    });
});
