jQuery(document).ready(function() {
    if(jQuery('#publish').attr('name')==="publish" && jQuery('#pushdaddy_notification_enable').length>0){
        jQuery('#publish').click(function() {
            if(jQuery('#pushdaddy_notification_enable').is(":checked")){
                if(jQuery('#pushdaddy_notification_title').val()==="" || jQuery('#pushdaddy_notification_message').val()===""){
                    alert("PushDaddy: Notification title and message cannot be empty!");
                    return false;
                }
            }
        });
    }

    jQuery('#pd_copy_title').click(function() {
        if(jQuery("input[name=post_title]").length>0){
        	jQuery("#pushdaddy_notification_message").val(jQuery("input[name=post_title]").val());
		}
		else{
			jQuery("#pushdaddy_notification_message").val(jQuery("#post-title-0").val());
		}
    });
});
