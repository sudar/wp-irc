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
});
