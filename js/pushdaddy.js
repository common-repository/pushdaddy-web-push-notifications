jQuery(document).ready(function() {
    jQuery('.pd-receive-notification button[name="pd-rec-notf-yes"]').click(function(){
        jQuery.post(
            pd_ajax.ajax_url, 
            {
                'action': 'associate_pushdaddy',
                'pd_receive_notification_nonce_field': jQuery('input[name="pushdaddy_action_nonce_field"]').val(),
                'user_action': 'yes'
            }, 
            function(response){
                //console.log('The server responded: ' + response);
                jQuery('input[name=pd-dashboard-enable-notification]').attr('checked', 'checked');
            }
        );
        jQuery('.pd-receive-notification').remove();
    });
    
    jQuery('.pd-receive-notification button[name="pd-rec-notf-no"]').click(function(){
        jQuery.post(
            pd_ajax.ajax_url, 
            {
                'action': 'associate_pushdaddy',
                'pd_receive_notification_nonce_field': jQuery('input[name="pushdaddy_action_nonce_field"]').val(),
                'user_action': 'no'
            }, 
            function(response){
                //console.log('The server responded: ' + response);
            }
        );
        jQuery('.pd-receive-notification').remove();
    });
    
    jQuery('input[name=pd-dashboard-enable-notification]').change(function(){        
        var enabled = '';
        if(jQuery('input[name=pd-dashboard-enable-notification]').is(':checked')){
            enabled = 'yes';
            if(PushDaddyCo.subs_id==""){
                PushDaddyCo.forceSubscribe();
                return;
            }
        }
        else{
            enabled = 'delete';
            if(PushDaddyCo.subs_id==""){
                return;
            }
        }

        jQuery.post(
            pd_ajax.ajax_url, 
            {
                'action': 'associate_pushdaddy',
                'pd_receive_notification_nonce_field': jQuery('input[name="pushdaddy_action_nonce_field"]').val(),
                'user_action': enabled
            }, 
            function(response){
                //console.log('The server responded: ' + response);
            }
        );
    });
});

(pushdaddybyiw = window.pushdaddybyiw || Array()).push(['onSuccess', PACallbackOnSuccess]);
function PACallbackOnSuccess(result) {
    if(!result.alreadySubscribed){
        jQuery.post(
            pd_ajax.ajax_url, 
            {
                'action': 'associate_pushdaddy',
                'pd_receive_notification_nonce_field': jQuery('input[name="pushdaddy_action_nonce_field"]').val(),
                'user_action': 'yes',
                'action_from': 'pushdaddy'
            }, 
            function(response){
                //console.log('The server responded: ' + response);
                jQuery('input[name=pd-dashboard-enable-notification]').attr('checked', 'checked');
            }
        );
    }
}

(pushdaddybyiw = window.pushdaddybyiw || Array()).push(['onFailure', PACallbackOnFailure]);
function PACallbackOnFailure(result) {
    if(result.now){
        jQuery.post(
            pd_ajax.ajax_url, 
            {
                'action': 'associate_pushdaddy',
                'pd_receive_notification_nonce_field': jQuery('input[name="pushdaddy_action_nonce_field"]').val(),
                'user_action': 'delete',
                'action_from': 'pushdaddy'
            }, 
            function(response){
                //console.log('The server responded: ' + response);
                jQuery('input[name=pd-dashboard-enable-notification]').removeAttr('checked');
            }
        );
    }
}