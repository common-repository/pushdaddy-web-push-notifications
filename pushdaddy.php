<?php
/*
 * Plugin Name: Push Notifications and WooCommerce Abandoned Cart
 * Description: Increase reach and sales on your WordPress Website and WooCommerce Store, allowing you to push real-time notifications to your website users on both mobile and desktop.
 * Author: PushDaddy
 * Author URI: https://pushdaddy.com
 * Version: 1.0.7
 */

add_action('admin_init', 'pushdaddy_admin_init');
add_action('admin_notices', 'pushdaddy_warn_onactivate');
//add_action('admin_menu', 'pushdaddy_admin_menu'); Centralized
add_action('wp_head', 'pushdaddy_append_js');

add_action('admin_init', 'pushdaddy_push_notification_box_init');
//add_action('draft_post', 'pushdaddy_save_notification');
//add_action('future_post', 'pushdaddy_save_notification');
//add_action('pending_post', 'pushdaddy_save_notification');
add_action('save_post', 'pushdaddy_save_notification');
//add_action('draft_to_publish', 'pushdaddy_send_notification');
//add_action('pending_to_publish', 'pushdaddy_send_notification');
//add_action('auto-draft_to_publish', 'pushdaddy_send_notification');
//add_action('future_to_publish', 'pushdaddy_send_notification_future');
add_action('publish_post', 'pushdaddy_send_notification_next', 10, 2);
add_action( 'publish_future_post', 'future_post_pushdaddy_send_notification' );
register_activation_hook( __FILE__, 'pushdaddy_init_options' );
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pushdaddy_plugin_settings_link');

if (isPDWooCommerceEnable()) {

   // if(get_option('_pushdaddy_abandoned_cart', 0)){
	   // 1 only for testing purpose. it should be 0 only so that once users purchase then activate
	    //   if(get_option('_pushdaddy_abandoned_cart', 1)){
        add_action('woocommerce_add_to_cart', 'pd_custom_updated_cart');
        add_action('woocommerce_cart_item_removed', 'pd_custom_updated_cart');
        add_action('woocommerce_after_cart_item_quantity_update', 'pd_custom_updated_cart');
        add_action('woocommerce_before_cart_item_quantity_zero', 'pd_custom_cart_quantity_zero');
        add_action('woocommerce_cart_is_empty', 'pd_custom_updated_cart');
        add_action('woocommerce_order_status_changed', 'pd_custom_order_completed', 10, 3);
  //  }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pushdaddy_plugin_woo_settings_link');

    add_action('init', 'pd_check_old_subscription_init');
    add_action('wp_head', 'pushdaddy_load_front_end_scripts');
    add_action('wp_footer', 'pd_check_old_subscription');

    add_action('wp_footer', 'pd_check_product_page');
    //add_action('woocommerce_before_main_content', 'pd_check_old_subscription');
    add_action( 'wp_ajax_associate_pushdaddy', 'ajax_associate_pushdaddy');


    add_action('woocommerce_account_dashboard', 'addPushDaddyEnableSettings');

    //add_filter( 'woocommerce_settings_tabs_array', 'add_settings_tab', 50 );
    //add_action( 'woocommerce_settings_tabs_pushdaddy', 'pd_settings_tab' );
    //add_action( 'woocommerce_update_options_pushdaddy', 'update_pd_settings_tab' );

    if(get_option('_pushdaddy_out_of_stock', 0) || get_option('_pushdaddy_price_drop', 0)){
        add_action( 'updated_post_meta', 'pd_woo_price_stock_update', 10, 4 );
    }
    if(get_option('_pushdaddy_shipment_alert', 0)){
        add_action( 'added_post_meta', 'pd_woo_track_shipment', 10, 4 );
    }

}
add_action('admin_menu', 'pd_register_normal_send_notification_menu_page');

function isPDWooCommerceEnable($forceFully = false){
    $is_enable = in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins')));
    if(($is_enable && !get_option('_pushdaddy_woocommerce_enable', 0) && !$forceFully) || ($forceFully && $is_enable)){
        pushdaddy_enable_ecommerce();
        pushdaddy_load_settings();

        if($forceFully){
            update_option('_pushdaddy_woocommerce_enable', 1);
        }
    }

    if(!$forceFully){
        update_option('_pushdaddy_woocommerce_enable', $is_enable?1:0);
    }

    return $is_enable;
}

function pushdaddy_plugin_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=pushdaddy-general-settings') . '">' . __('Settings', 'pushdaddy') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function pushdaddy_plugin_woo_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=pushdaddy-woocommerce-settings') . '">' . __('WooCommerce Settings', 'pushdaddy') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function pushdaddy_warn_onactivate() {
    if (is_admin()) {
        $pushdaddy_api_key = get_option('pushdaddy_api_key');
        $pushdaddy_web_id = get_option('pushdaddy_web_id');
			$translate_txt1 = __( 'PushDaddy', 'pushdaddy-web-push-notifications' );
			$translate_txt2 = __( 'REST API Key and Website ID is required. Update', 'pushdaddy-web-push-notifications' );
			$translate_txt3 = __( 'Now', 'pushdaddy-web-push-notifications' );


        if (!$pushdaddy_api_key || !$pushdaddy_web_id) {
            echo '<div class="updated"><p><strong>'.$translate_txt1.':</strong> '.$translate_txt2.' <a href="' . admin_url('admin.php?page=pushdaddy-general-settings') . '">' . __('settings', 'pushdaddy') . '</a> '.$translate_txt3.'</p></div>';
        }
    }
}

function pushdaddy_admin_init() {
    $pd_version = 7;
    if(get_option('_pushdaddy_version', 1)!=$pd_version){
		update_option('_pushdaddy_version', $pd_version);
		pushdaddy_load_settings();
			$translate_txt4 = __( 'Yes, associate users automatically when they login.', 'pushdaddy-web-push-notifications' );
			$translate_txt5 = __( 'No, prompt user to confirm association.', 'pushdaddy-web-push-notifications' );

        if(get_option('woocommerce_settings_pushdaddy_auto_assoc_yes')===false){
            add_option('woocommerce_settings_pushdaddy_auto_assoc_yes', ''.$translate_txt4.'');
            add_option('woocommerce_settings_pushdaddy_auto_assoc_no', ''.$translate_txt5.'');
            add_option('woocommerce_settings_pushdaddy_auto_assoc', 2);
        }
    }
    wp_register_style('pushdaddy_style_css', plugins_url('style.css', __FILE__), array(), $pd_version);
    wp_register_script('pushdaddy_javascript_js', plugins_url('javascript.js', __FILE__), array(), $pd_version);

    register_setting(
            'pushdaddy', 'pushdaddy_web_id'
    );

    register_setting(
            'pushdaddy', 'pushdaddy_api_key'
    );

    register_setting(
            'pushdaddy', 'pushdaddy_default_title'
    );

    register_setting(
            'pushdaddy', 'pushdaddy_utm_source'
    );

    register_setting(
            'pushdaddy', 'pushdaddy_utm_medium'
    );

    register_setting(
            'pushdaddy', 'pushdaddy_utm_campaign'
    );
}



function pushdaddy_replace_footer_admin () {

//    echo 'If you like <strong>PushDaddy</strong> please leave us a <a href="https://wordpress.org/support/view/plugin-reviews/pushdaddy-web-push-notifications?filter=5#postform" target="_blank" class="wc-rating-link" data-rated="Thanks :)">★★★★★</a> rating. A huge thanks in advance!';

}

function pushdaddy_append_js() {
    $pushdaddy_web_id = get_option('pushdaddy_web_id');
    if ($pushdaddy_web_id) {
        ?>
        <!-- PushDaddy WordPress 2.1 -->
        <script type="text/javascript">
            (function (d, t) {
                var g = d.createElement(t),
                        s = d.getElementsByTagName(t)[0];
                g.src = "//cdn.pushdaddy.com/integrate_<?php echo $pushdaddy_web_id ?>.js";
                s.parentNode.insertBefore(g, s);
            }(document, "script"));
        </script>
        <!-- End PushDaddy WordPress -->
        <?php
    }
}

function pushdaddy_push_notification_box_init() {
    $pushdaddy_api_key = get_option('pushdaddy_api_key');

// temporarily allowed for everyone
	//   if ($pushdaddy_api_key) {
        wp_enqueue_style('pushdaddy_style_css');
        wp_enqueue_script('pushdaddy_javascript_js');

        add_meta_box(
                'pushdaddy_push_notification', __('PushDaddy Notification', 'pushdaddy'), 'pushdaddy_push_notification_box', 'post', 'side', 'high'
        );
  //  }
}

function pushdaddy_push_notification_box($post) {

    $title = get_post_meta($post->ID, 'pushdaddy_notification_title', true);
    if ($title == "") {
        $title = get_option('pushdaddy_default_title');
    }
    $message = get_post_meta($post->ID, 'pushdaddy_notification_message', true);
    $enable = get_post_meta($post->ID, 'pushdaddy_notification_enable', true);
    $post_status = $post->post_status;

			$translate_txt6 = __( 'Title', 'pushdaddy-web-push-notifications' );
			$translate_txt7 = __( 'Your message here..., associate users automatically when they login.', 'pushdaddy-web-push-notifications' );
			$translate_txt8 = __( 'Copy Title', 'pushdaddy-web-push-notifications' );
			$translate_txt9 = __( 'Push notification on publish', 'pushdaddy-web-push-notifications' );

	
	
    wp_nonce_field(plugin_basename(__FILE__), 'pushdaddy_nonce_field');
    echo '<input type="text" maxlength="64" id="pushdaddy_notification_title" name="pushdaddy_notification_title" value="' . esc_attr($title) . '" placeholder="'.$translate_txt6.'">';
    echo '<textarea id="pushdaddy_notification_message" maxlength="192" name="pushdaddy_notification_message" rows="4" placeholder="'.$translate_txt7.'">' . esc_textarea($message) . '</textarea>';
    echo '<div class="pd-copy-button-container"><input type="button" name="pd_copy_title" id="pd_copy_title" value="'.$translate_txt8.'" class="button"></div>';
    echo '<label class="pushdaddy_enable_label"><input type="checkbox" name="pushdaddy_notification_enable" id="pushdaddy_notification_enable" value="1" ' . (($enable == 1) ? "checked" : "") . '> '.$translate_txt9.'</label>';
}

function pushdaddy_save_notification($ID) {
	if (!get_option('pushdaddy_api_key')) {
        return false;
    }

	if (get_post_type($ID) != 'post') {
        return false;
    }

	if (!empty($_POST)) {
        if (!isset($_POST['pushdaddy_nonce_field']) || (!wp_verify_nonce($_POST['pushdaddy_nonce_field'], plugin_basename(__FILE__)))) {
			return false;
        } else {
			$title = "";
            $message = "";
            $enable = 0;
            $utm_source = get_option('pushdaddy_utm_source');
            $utm_medium = get_option('pushdaddy_utm_medium');
            $utm_campaign = get_option('pushdaddy_utm_campaign');
            if (isset($_POST['pushdaddy_notification_title'])) {
                $title = pushdaddy_sanitize_text_field($_POST['pushdaddy_notification_title']);
            }
            if (isset($_POST['pushdaddy_notification_message'])) {
                $message = pushdaddy_sanitize_text_field($_POST['pushdaddy_notification_message']);
            }
            if (isset($_POST['pushdaddy_notification_enable']) && is_numeric($_POST['pushdaddy_notification_enable']) && $_POST['pushdaddy_notification_enable'] == 1) {
                $enable = 1;
            }

            update_post_meta($ID, 'pushdaddy_notification_title', $title);
            update_post_meta($ID, 'pushdaddy_notification_message', $message);
            update_post_meta($ID, 'pushdaddy_notification_enable', $enable);
            update_post_meta($ID, 'pushdaddy_utm_source', $utm_source);
            update_post_meta($ID, 'pushdaddy_utm_medium', $utm_medium);
            update_post_meta($ID, 'pushdaddy_utm_campaign', $utm_campaign);

			$publish_status = get_post_meta($ID, 'pushdaddy_publish_status', true);
			if($publish_status && $publish_status==1 && $enable==1){
				update_post_meta($ID, 'pushdaddy_publish_status', 2);
				$large_image = "";
				if(get_option('pushdaddy_large_image', 0) && has_post_thumbnail($ID)){
					$large_image = wp_get_attachment_image_src(get_post_thumbnail_id($ID), 'single-post-thumbnail');
					$large_image = $large_image[0];
				}
                
                $title = get_post_meta($ID, 'pushdaddy_notification_title', true);
	            $message = get_post_meta($ID, 'pushdaddy_notification_message', true);

				//add_action( 'admin_notices','pushdaddy_notification_pushed_notice');
				$url = get_permalink($ID);
				$url = pushdaddy_setGetParameter($url, "utm_source", $utm_source);
				$url = pushdaddy_setGetParameter($url, "utm_medium", $utm_medium);
				$url = pushdaddy_setGetParameter($url, "utm_campaign", $utm_campaign);

				pushdaddy_send_notification_curl($title, $message, $url, $large_image);
			}
            return true;
        }
    } else {
		return false;
    }
}

function pushdaddy_sanitize_text_field($str) {
    $filtered = wp_check_invalid_utf8($str); //html tags are fine
    $filtered = wp_kses_post( $filtered );;

      return $filtered;
}

function pushdaddy_send_notification_next($ID, $post) {
    //Multiple post published
    if (array_key_exists('post_status', $_GET) && $_GET['post_status'] == 'all') {
        return false;
    }

	$post_status = $post->post_status;
    if ($post_status == 'publish') {
        $publish_status = get_post_meta($ID, 'pushdaddy_publish_status', true);
		if($publish_status){
			return;
		}
		else{
	        update_post_meta($ID, 'pushdaddy_publish_status', 1);
		}
    }
}

function future_post_pushdaddy_send_notification($post_id) {
    $enable = get_post_meta($post_id, 'pushdaddy_notification_enable', true);
    if ($enable == 1) {
        $title = get_post_meta($post_id, 'pushdaddy_notification_title', true);
        $message = get_post_meta($post_id, 'pushdaddy_notification_message', true);
        $utm_source = get_post_meta($post_id, 'pushdaddy_utm_source', true);
        $utm_medium = get_post_meta($post_id, 'pushdaddy_utm_medium', true);
        $utm_campaign = get_post_meta($post_id, 'pushdaddy_utm_campaign', true);

        //add_action( 'admin_notices','pushdaddy_notification_pushed_notice');
        $url = get_permalink($post_id);
        $url = pushdaddy_setGetParameter($url, "utm_source", $utm_source);
        $url = pushdaddy_setGetParameter($url, "utm_medium", $utm_medium);
        $url = pushdaddy_setGetParameter($url, "utm_campaign", $utm_campaign);

        pushdaddy_send_notification_curl($title, $message, $url);
    }
}

function pushdaddy_setGetParameter($url, $paramName, $paramValue) {
    if ($paramValue == "") {
        return $url;
    }
    if (strpos($url, $paramName . "=") !== FALSE) {
        $prefix = substr($url, 0, strpos($url, $paramName));
        $suffix = substr($url, strpos($url, $paramName));
        $suffix = substr($suffix, strpos($suffix, "=" + 1));
        $suffix = (strpos($suffix, "&") !== FALSE) ? substr($suffix, strpos($suffix, "&")) : "";
        $url = $prefix . $paramName . "=" . $paramValue . $suffix;
    } else {
        if (strpos($url, "?"))
            $url = $url . "&" . $paramName . "=" . $paramValue;
        else
            $url = $url . "?" . $paramName . "=" . $paramValue;
    }
    return $url;
}

function pushdaddy_send_notification_curl($title, $message, $url, $large_image="") {
    if ($title == "" || $message == "" || $url == "") {
        return false;
    }

    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/send/";

    //POST variables
    $post_vars = array(
        "title" => $title,
        "message" => $message,
        "url" => $url,
		"api_key_s" => $apiKey,
    );

    if($large_image!=""){
        $post_vars['large_image'] = $large_image;
    }

	// disabled on 7june 2019
  //  if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){

        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
		
 //   }
}

function pushdaddy_send_to_custom($title, $message, $url, $attr_name, $attr_value, $checkout_button=false, $checkout_url=false) {
    if ($title == "" || $message == "" || $url == "") {
        return false;
    }

    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/send/custom/";

    //POST variables
    $post_vars = array(
        "title" => $title,
        "message" => $message,
        "url" => $url,
		"api_key_s" => $apiKey,
        "attributes" => json_encode(array($attr_name=>$attr_value))
    );

    if($checkout_button && $checkout_url && $checkout_button!="" && $checkout_url!=""){
        $action1 = array(
            "title"=>"➤ ".$checkout_button,
            "url"=>$checkout_url,
        );

        $post_vars['action1'] = json_encode($action1);
    }
	// disabled on 7june 2019
  //  if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){

        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
   // }
}

function pushdaddy_load_settings() {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/settings/";

    //POST variables
    $post_vars = array(
	"api_key_s" => $apiKey,
    );


    $headers = Array();
    $headers[] = "Authorization: api_key=" . $apiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    $result = json_decode($result, true);

    if($result['success']){
        foreach($result['data'] as $key=>$value){
            update_option($key, $value);
        }
    }
    else{
        return false;
    }
}

function pushdaddy_get_stats() {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/stats/";

    //POST variables
    $post_vars = array(
	"api_key_s" => $apiKey,
    );


    $headers = Array();
    $headers[] = "Authorization: api_key=" . $apiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    $result = json_decode($result, true);

    if($result['success']){
        return $result['data'];
    }
    else{
        return false;
    }
}

function pushdaddy_enable_ecommerce() {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/enableEcommerce/";

    //POST variables
    $post_vars = array(
        "type"=>"woocommerce",
		"api_key_s" => $apiKey,
    );


    $headers = Array();
    $headers[] = "Authorization: api_key=" . $apiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
}

function pushdaddy_get_attributes($subscriber_id) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/attribute/get/";

    //POST variables
    $post_vars = array(
        "subscriber" => $subscriber_id,
		"api_key_s" => $apiKey,
    );


    $headers = Array();
    $headers[] = "Authorization: api_key=" . $apiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curlUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    $result = json_decode($result, true);

    if($result['success']){
        return $result['attributes'];
    }
    else{
        return false;
    }
}

function pushdaddy_put_attributes($subscriber_id, $attr_name, $attr_value) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/attribute/put/";

    //POST variables
    $post_vars = array(
        "subscriber" => $subscriber_id,
        "attributes" => json_encode(array($attr_name=>$attr_value)),
		"api_key_s" => $apiKey,
    );
	// disabled on 7june 2019
   // if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){

        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
   // }
}

function pushdaddy_track_order($order_id, $order_total, $check_pushdaddy_woo) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/order/";

    //POST variables
    $post_vars = array(
        "order_id" => $order_id,
        "order_total" => $order_total,
        "source" => $check_pushdaddy_woo,
		"api_key_s" => $apiKey,
    );

		// disabled on 7june 2019
   // if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){
        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        //return $result['success'];
   // }
}

function pushdaddy_product_update($product_info) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/product/update/";

    //POST variables
    $post_vars = array(
        "product_info" => json_encode($product_info),
		"api_key_s" => $apiKey,

    );
		
		// disabled on 7june 2019
//    if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){
        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
  //  }
}

function pushdaddy_track_order_shipment($order_info) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/order/track/";

    //POST variables
    $post_vars = array(
        "order_info" => json_encode($order_info),
		"api_key_s" => $apiKey,
    );
	
	// disabled on 7june 2019
  //  if (!pushdaddy_backgroundPost($curlUrl . "?" . http_build_query($post_vars, '', '&'))) {
        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        //return $result['success'];
  //  }
}

function pushdaddy_add_abandoned_cart($subscriber_id, $user_info = array()) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/abandonedCart/";

    //POST variables
    $post_vars = array(
        "subscriber" => $subscriber_id,
        "extra_info" => json_encode($user_info),
		"api_key_s" => $apiKey,
    );

 
// disabled on 7june 2019
//   if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){
        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $result = json_decode($result, true);
        //return $result['success'];
  //  }

}

function pushdaddy_remove_abandoned_cart($subscriber_id) {
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $curlUrl = "https://api.pushdaddy.com/rest/v1/abandonedCart/delete/";

    //POST variables
    $post_vars = array(
        "subscriber" => $subscriber_id,
		"api_key_s" => $apiKey,
    );

// disabled on 7june 2019 
//   if(!pushdaddy_backgroundPost($curlUrl."?".http_build_query($post_vars, '', '&'))){

        $headers = Array();
        $headers[] = "Authorization: api_key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_vars, '', '&'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $result = json_decode($result, true);
        //return $result['success'];
   // }
}

function pushdaddy_backgroundPost($url){
    $apiKey = get_option('pushdaddy_api_key');
    if (!$apiKey) {
        return false;
    }

    $parts=parse_url($url);
    //print_r($parts);
    $fp = fsockopen("ssl://".$parts['host'],
            isset($parts['port'])?$parts['port']:443,
            $errno, $errstr, 30);


    if (!$fp) {
        return false;
    } else {
        $out = "POST ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "User-Agent: custom\r\n";
        $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out.= "Authorization: api_key=" . $apiKey."\r\n";
        $out.= "Content-Length: ".strlen($parts['query'])."\r\n";
        $out.= "Connection: Close\r\n\r\n";
        if (isset($parts['query'])) $out.= $parts['query'];
        fwrite($fp, $out);
        fclose($fp);
        return true;
    }
}

function pd_custom_updated_cart(){
    $total_items = sizeof( WC()->cart->get_cart() );
    if(current_filter()=="woocommerce_cart_item_removed" && $total_items==0){
        //handled by woocommerce_cart_is_empty
    }
    else{
        if($total_items==0){
            pd_clear_abandoned_cart();
        }
        else{
            //pd_clear_abandoned_cart();
            pd_init_abandoned_cart();
        }
    }
}

function pd_custom_cart_quantity_zero($cart_item_key){

    $total_items = sizeof( WC()->cart->get_cart() );
    if($total_items-1>0){
        //pd_clear_abandoned_cart();
        pd_init_abandoned_cart($total_items-1);
    }
    //else case is handled by woocommerce_cart_is_empty
}

function pd_custom_order_completed($order_id, $old_status, $new_status){

    $check_pushdaddy_woo = isset($_COOKIE['pushdaddy_woo'])?$_COOKIE['pushdaddy_woo']:false;
    if(!is_admin() && ($new_status == "processing" || $new_status == "completed") && $check_pushdaddy_woo){
        $order = new WC_Order($order_id);
        pushdaddy_track_order($order_id, $order->get_total(), $check_pushdaddy_woo);
    }
    pd_clear_abandoned_cart();
}

function pd_subscription_check(){
    return ((isset($_COOKIE['pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_status']) && $_COOKIE['pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_status']==='subscribed') && isset($_COOKIE['pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id']) && $_COOKIE['pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id']!='');
}

function pd_get_total_items($user_id){
    $saved_cart = get_user_meta( $user_id, '_woocommerce_persistent_cart', true );
    if($saved_cart && isset($saved_cart['cart'])){
     return count($saved_cart['cart']);
    }
    else{
        return 0;
    }
}
function pd_clear_abandoned_cart(){
    if(!is_admin()){
        if(!pd_subscription_check()){return;}
        $curr_user_id = get_current_user_id();
        $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
        if($curr_user_id!=0){
            pushdaddy_remove_abandoned_cart($curr_user_id);
        }
        pushdaddy_remove_abandoned_cart($pushdaddy_subs_id);
   }
}

function pd_init_abandoned_cart($total_items=false){
    if(!pd_subscription_check()){return;}

    global $woocommerce;
    $curr_user = wp_get_current_user();
    $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');

    $user_info = array();
    $user_info['cart_url'] = $woocommerce->cart->get_cart_url();
    $user_info['checkout_url'] = $woocommerce->cart->get_checkout_url();
    $user_info['total_items'] = (!$total_items ? sizeof(WC()->cart->get_cart()) : $total_items);
    $pd_check_cookie = $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))];
    if($curr_user->ID!=0 && isset($pd_check_cookie) && $pd_check_cookie=='1'){
        $user_info['first_name'] = $curr_user->first_name;
        if($user_info['first_name']==""){
            $user_info['first_name'] = $curr_user->nickname;
        }
        pushdaddy_add_abandoned_cart($curr_user->ID, $user_info);
    }
    else{
        pushdaddy_add_abandoned_cart($pushdaddy_subs_id, $user_info);
    }

}

function pushdaddy_load_front_end_scripts(){
    wp_register_script( 'custom-script', plugins_url( '/js/pushdaddy.js', __FILE__ ), array( 'jquery' ) );
    wp_localize_script( 'custom-script', 'pd_ajax', array('ajax_url' => admin_url( 'admin-ajax.php' ) ));

    wp_enqueue_script( 'custom-script' );
}

function pushdaddy_init_options() {
			$translate_txt10 = __( 'Click \'yes\' to receive personalized notifications and offers.', 'pushdaddy-web-push-notifications' );
			$translate_txt11 = __( 'Yes', 'pushdaddy-web-push-notifications' );
			$translate_txt12 = __( 'No', 'pushdaddy-web-push-notifications' );
			$translate_txt13 = __( 'Receive Personalized Notifications and Offers', 'pushdaddy-web-push-notifications' );
		
	
    if(get_option('woocommerce_settings_pushdaddy_association_css')===false){
        add_option('woocommerce_settings_pushdaddy_confirm_message', ''.$translate_txt10.'');
        add_option('woocommerce_settings_pushdaddy_button_yes', ''.$translate_txt11.'');
        add_option('woocommerce_settings_pushdaddy_button_no', ''.$translate_txt12.'');
        add_option('woocommerce_settings_pushdaddy_dashboard_option', ''.$translate_txt13.'');
        add_option('woocommerce_settings_pushdaddy_association_css', '
.pd-receive-notification{
    position: fixed;
    top: 0;
    z-index: 999999;
    left: 0;
    right: 0;
    text-align: center;
    background: #fff;
    padding: 10px;
    box-shadow: 0 0 5px rgba(0,0,0,0.2);
}

.pd-receive-notification form{
    margin:0;
}
.pd-receive-notification button{
    padding: 5px 20px;
    margin: 0 5px;
    font-weight: 400;
}
.pd-receive-notification button.yes{
    background: black;
    color: white;
}

.pd-receive-notification button.no{
    background: white;
    color: black;
}');
        add_option('pushdaddy_encrypt_key', PAGenerateRandomString());
    }
}

function PAGenerateRandomString($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function PACheckSubsID($user_id, $subs_id){
    $attributes = pushdaddy_get_attributes($subs_id);
    if(isset($attributes['user_id'])){
        return ($attributes['user_id']==$user_id);
    }
    else{
        return false;
    }
}

function PAAssociateSubsID($user_id, $subs_id){
    pushdaddy_put_attributes($subs_id, 'user_id', $user_id);
}

function PADeleteSubsID($user_id, $subs_id){
    pushdaddy_put_attributes($subs_id, 'user_id', ''); //unset user_id
}

function pd_check_old_subscription_init(){
    $check_pushdaddy_woo = filter_input(INPUT_GET, 'pushdaddy_source');
    if(isset($check_pushdaddy_woo)){
        setcookie('pushdaddy_woo', $check_pushdaddy_woo, time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
        $_COOKIE['pushdaddy_woo'] = $check_pushdaddy_woo;
    }

    if(!pd_subscription_check()){return;}

    $curr_user_id = get_current_user_id();
    $pd_check_cookie = filter_input(INPUT_COOKIE, 'pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')));
    if($curr_user_id!=0 && !isset($pd_check_cookie)){
        $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
        if(isset($pushdaddy_subs_id) && $pushdaddy_subs_id!=""){
            if(PACheckSubsID($curr_user_id, $pushdaddy_subs_id)){
                setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '1', 2147483647, COOKIEPATH, COOKIE_DOMAIN);
                $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))] = '1';
                return;
            }
        }
    }
    if(!isset($pd_check_cookie) && $curr_user_id!=0){
        setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '-5', time() + 604800, COOKIEPATH, COOKIE_DOMAIN);
        $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))] = '-5';
    }
}

function pd_check_old_subscription(){
    if(get_option('woocommerce_settings_pushdaddy_auto_assoc')==1){
        pd_assoc_subscription();
    }
    else{
        wp_nonce_field(plugin_basename(__FILE__), 'pushdaddy_action_nonce_field');
        if(!pd_subscription_check()){return;}

        $curr_user_id = get_current_user_id();
        if($curr_user_id == 0){
            return;
        }
        else{
            $pd_check_cookie = $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))];
            if(isset($pd_check_cookie) && $pd_check_cookie!="-5"){
                return;
            }
        }

        $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
        if($curr_user_id!=0 && isset($pushdaddy_subs_id) && $pushdaddy_subs_id!=""){
            if(!isset($pd_check_cookie) || (isset($pd_check_cookie) && $pd_check_cookie=="-5")){
                //pushdaddy_load_front_end_scripts();

                echo "
                    <style>
                        ".get_option('woocommerce_settings_pushdaddy_association_css')."
                    </style>
                ";

                echo "
                    <div class='pd-receive-notification'>
                        <form type='POST'>
                ";
                echo "
                            ".get_option('woocommerce_settings_pushdaddy_confirm_message')."
                            <button name='pd-rec-notf-yes' type='button' class='yes'>".get_option('woocommerce_settings_pushdaddy_button_yes')."</button>
                            <button name='pd-rec-notf-no' type='button' class='no'>".get_option('woocommerce_settings_pushdaddy_button_no')."</button>
                        </form>
                    </div>
                ";
            }
        }
    }
}

function ajax_associate_pushdaddy() {

    $curr_user_id = get_current_user_id();
    if (!isset($_POST['pd_receive_notification_nonce_field']) || (!wp_verify_nonce($_POST['pd_receive_notification_nonce_field'], plugin_basename(__FILE__))) || $curr_user_id == 0) {
        echo "-0";
        wp_die();
    } else {
        $user_action = filter_input(INPUT_POST, 'user_action');
        if($user_action=="yes"){
            $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
            if($curr_user_id!=0 && isset($pushdaddy_subs_id) && $pushdaddy_subs_id!=""){
                PAAssociateSubsID($curr_user_id, $pushdaddy_subs_id);

                global $woocommerce;
                $total_items = sizeof( WC()->cart->get_cart() );
                if($total_item>0){
                    pd_clear_abandoned_cart();
                    pd_init_abandoned_cart();
                }
                setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '1', 2147483647, COOKIEPATH, COOKIE_DOMAIN);
                echo "1";
                wp_die();
            }
        }
        else if($user_action=="delete"){
            $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
            PADeleteSubsID($curr_user_id, $pushdaddy_subs_id);
            setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '-1', 2147483647, COOKIEPATH, COOKIE_DOMAIN);
            echo "-2";
            wp_die();
        }
        else{
            setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '-1', 2147483647, COOKIEPATH, COOKIE_DOMAIN);
            echo "-1";
            wp_die();
        }
    }
    echo "0";
    wp_die();
}

function pd_assoc_subscription(){
    $curr_user_id = get_current_user_id();
    if($curr_user_id == 0){
        return;
    }
    else{
        $pd_check_cookie = $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))];
        if(isset($pd_check_cookie) && $pd_check_cookie!="-5"){
            return;
        }
        else{
            $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
            if($curr_user_id!=0 && isset($pushdaddy_subs_id) && $pushdaddy_subs_id!=""){
                PAAssociateSubsID($curr_user_id, $pushdaddy_subs_id);

                global $woocommerce;
                $total_items = sizeof( WC()->cart->get_cart() );
                if($total_item>0){
                    pd_clear_abandoned_cart();
                    pd_init_abandoned_cart();
                }
                setcookie('pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key')), '1', 2147483647, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }
}

function pd_encrypt($encrypt, $key){
    $encoded = hash_hmac('sha256', $encrypt, $key);
    return $encoded;
}

function addPushDaddyEnableSettings(){
    $checked = "";
    //pushdaddy_load_front_end_scripts();

    $curr_user_id = get_current_user_id();
    $pushdaddy_subs_id = filter_input(INPUT_COOKIE, 'pushdaddy_'.get_option('_pushdaddy_cookie_id', '').'subs_id');
    $pd_check_cookie = $_COOKIE['pushdaddy_'.pd_encrypt($curr_user_id, get_option('pushdaddy_encrypt_key'))];
    if($curr_user_id!=0 && isset($pushdaddy_subs_id) && $pushdaddy_subs_id!="" && pd_subscription_check()){
        if(isset($pd_check_cookie) && $pd_check_cookie=="1"){
            $checked = "checked";
        }
    }

    echo "<label class='pushdaddy-dashboard-option'><input type='checkbox' name='pd-dashboard-enable-notification' $checked style='height:auto'/> ".get_option('woocommerce_settings_pushdaddy_dashboard_option')."</label>";
}


/**
 * Add a new settings tab to the WooCommerce settings tabs array. Disabled - Centrailzed Now
 *
 * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
 * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
 */
/*function add_settings_tab( $settings_tabs ) {
    $settings_tabs['pushdaddy'] = __( 'PushDaddy', 'woocommerce-pushdaddy-settings' );
    return $settings_tabs;
}

function pd_settings_tab(){
    woocommerce_admin_fields(pd_get_settings());
}

function update_pd_settings_tab(){
    woocommerce_update_options(pd_get_settings());
}

function pd_get_settings() {
    $settings = array(
        'subs_id_association' => array(
            'name'     => __( 'Subscription ID Association', 'woocommerce-settings-pushdaddy-association' ),
            'type'     => 'title',
            'desc'     => 'This message is shown to associate the logged in user to the PushDaddy subscription ID. It is only shown to users, who subscribed to push notifications before logging into their account.',
            'id'       => 'woocommerce_settings_pushdaddy_association'
        ),
        'confirm_message' => array(
            'name' => __( 'Confirm Message', 'woocommerce-settings-pushdaddy-confirm-message' ),
            'type' => 'text',
            'id' => 'woocommerce_settings_pushdaddy_confirm_message',
            'class' => 'pushdaddy-woocommerce-text'
        ),
        'button_yes' => array(
            'name' => __( 'Button Yes', 'woocommerce-settings-pushdaddy-button-yes' ),
            'type' => 'text',
            'id' => 'woocommerce_settings_pushdaddy_button_yes',
            'class' => 'pushdaddy-woocommerce-text-small'
        ),
        'button_no' => array(
            'name' => __( 'Button No', 'woocommerce-settings-pushdaddy-button-no' ),
            'type' => 'text',
            'id' => 'woocommerce_settings_pushdaddy_button_no',
            'class' => 'pushdaddy-woocommerce-text-small'
        ),
        'subs_id_association_css' => array(
            'name' => __( 'CSS', 'woocommerce-settings-pushdaddy-association-css' ),
            'type' => 'textarea',
            'id' => 'woocommerce_settings_pushdaddy_association_css',
            'class' => 'pushdaddy-woocommerce-text-css'
        ),
        'dashboard_option' => array(
            'name' => __( 'Enable Notification Option Text', 'woocommerce-settings-pushdaddy-dashboard-option' ),
            'type' => 'text',
            'id' => 'woocommerce_settings_pushdaddy_dashboard_option',
            'class' => 'pushdaddy-woocommerce-text',
            'desc' => '<br/>Shown in My Account section of the WooCommerce account of your customer, where they can easily enable/disable notifications.'
        ),
        'subs_id_association_section_end' => array(
             'type' => 'sectionend'
        ),
        'ca_oos_price_drop' => array(
            'name'     => __( 'Cart Abandonment, Out of Stock, Price Drop and Shipment Notifications', 'woocommerce-settings-pushdaddy-ca-oos-price_drop' ),
            'type'     => 'title',
            'desc'     => 'Please visit <a href="https://pushdaddy.com/dashboard" target="_blank">PushDaddy Dashboard</a> to configure Cart Abandonment, Out of Stock, Price Drop and Shipment Notifications. These are only available in basic and above plans, <a href="https://pushdaddy.com/dashboard/upgrade" target="_blank">upgrade now!</a>.',
            'id'       => 'woocommerce_settings_ca_oos_price_drop'
        ),
        'ca_oos_price_drop_end' => array(
             'type' => 'sectionend'
        )
    );
    return apply_filters( 'woocommerce_settings_pushdaddy_settings', $settings );
}*/

function pd_register_normal_send_notification_menu_page() {
    //add_menu_page('Send Notifications - PushDaddy', 'Send Notification', 'manage_options', 'pushdaddy-send-notification', 'pushdaddy_send_notifications_callback', 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MCA0MCI+PHRpdGxlPlB1c2hBbGVydC1Mb2dvPC90aXRsZT48ZyBpZD0iRm9ybWFfMSIgZGF0YS1uYW1lPSJGb3JtYSAxIj48ZyBpZD0iRm9ybWFfMS0yIiBkYXRhLW5hbWU9IkZvcm1hIDEtMiI+PHBhdGggZD0iTTIwLDM3LjQ5YzIuNzIsMCw0LjkzLTEuNjksNC45My0zLjA3SDE1QzE1LDM1LjgsMTcuMjUsMzcuNDksMjAsMzcuNDlabTEyLjcxLTcuMjNoMEE4LjQsOC40LDAsMCwxLDMwLDI0LjA3VjE3LjcxYTEwLDEwLDAsMCwwLTYuMzItOS4yOVY2LjE5YTMuNjgsMy42OCwwLDAsMC03LjM2LDBWOC40MkExMCwxMCwwLDAsMCwxMCwxNy43MXY2LjM1YTguNCw4LjQsMCwwLDEtMi43MSw2LjE5aDBhMS41MywxLjUzLDAsMCwwLDEsMi43M2gyMy41QTEuNTMsMS41MywwLDAsMCwzMi42OCwzMC4yNlpNMjAsNy41OGExLjY2LDEuNjYsMCwxLDEsMS42Ni0xLjY2QTEuNjYsMS42NiwwLDAsMSwyMCw3LjU4Wk0zMC43Nyw1TDI5LjgzLDYuNDNhMTIuMiwxMi4yLDAsMCwxLDUuMjksOGwxLjY5LS4zYTEzLjkyLDEzLjkyLDAsMCwwLTYtOS4xNGgwWk0xMC4wOSw2LjQzTDkuMTQsNWExMy45MiwxMy45MiwwLDAsMC02LDkuMTRsMS42OSwwLjNhMTIuMiwxMi4yLDAsMCwxLDUuMjgtOGgwWiIgZmlsbD0iI2ZmZiIvPjwvZz48L2c+PC9zdmc+', 30);

	$translate_txt14 = __( 'PushDaddy - Web Push Notifications', 'pushdaddy-web-push-notifications' );
	$translate_txt15 = __( 'Stats', 'pushdaddy-web-push-notifications' );
	$translate_txt16 = __( 'Send Notification', 'pushdaddy-web-push-notifications' );
	$translate_txt17 = __( 'General Settings', 'pushdaddy-web-push-notifications' );
	$translate_txt18 = __( 'WooCommerce Settings', 'pushdaddy-web-push-notifications' );

	
    add_menu_page(''.$translate_txt14.'', 'PushDaddy', 'manage_options', 'pushdaddy-web-push-notifications', 'pushdaddy_stats_callback', 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MCA0MCI+PHRpdGxlPlB1c2hBbGVydC1Mb2dvPC90aXRsZT48ZyBpZD0iRm9ybWFfMSIgZGF0YS1uYW1lPSJGb3JtYSAxIj48ZyBpZD0iRm9ybWFfMS0yIiBkYXRhLW5hbWU9IkZvcm1hIDEtMiI+PHBhdGggZD0iTTIwLDM3LjQ5YzIuNzIsMCw0LjkzLTEuNjksNC45My0zLjA3SDE1QzE1LDM1LjgsMTcuMjUsMzcuNDksMjAsMzcuNDlabTEyLjcxLTcuMjNoMEE4LjQsOC40LDAsMCwxLDMwLDI0LjA3VjE3LjcxYTEwLDEwLDAsMCwwLTYuMzItOS4yOVY2LjE5YTMuNjgsMy42OCwwLDAsMC03LjM2LDBWOC40MkExMCwxMCwwLDAsMCwxMCwxNy43MXY2LjM1YTguNCw4LjQsMCwwLDEtMi43MSw2LjE5aDBhMS41MywxLjUzLDAsMCwwLDEsMi43M2gyMy41QTEuNTMsMS41MywwLDAsMCwzMi42OCwzMC4yNlpNMjAsNy41OGExLjY2LDEuNjYsMCwxLDEsMS42Ni0xLjY2QTEuNjYsMS42NiwwLDAsMSwyMCw3LjU4Wk0zMC43Nyw1TDI5LjgzLDYuNDNhMTIuMiwxMi4yLDAsMCwxLDUuMjksOGwxLjY5LS4zYTEzLjkyLDEzLjkyLDAsMCwwLTYtOS4xNGgwWk0xMC4wOSw2LjQzTDkuMTQsNWExMy45MiwxMy45MiwwLDAsMC02LDkuMTRsMS42OSwwLjNhMTIuMiwxMi4yLDAsMCwxLDUuMjgtOGgwWiIgZmlsbD0iI2ZmZiIvPjwvZz48L2c+PC9zdmc+', 30);
    add_submenu_page('pushdaddy-web-push-notifications', 'Stats - PushDaddy', ''.$translate_txt15.'', 'manage_options', 'pushdaddy-web-push-notifications', 'pushdaddy_stats_callback');

    add_submenu_page('pushdaddy-web-push-notifications', 'Send Notification - PushDaddy', ''.$translate_txt16.'', 'manage_options', 'pushdaddy-send-notification', 'pushdaddy_send_notifications_callback');

    add_submenu_page('pushdaddy-web-push-notifications', 'General Settings - PushDaddy', ''.$translate_txt17.'', 'manage_options', 'pushdaddy-general-settings', 'pushdaddy_general_settings_callback');

    if (isPDWooCommerceEnable()) {
        add_submenu_page('pushdaddy-web-push-notifications', 'WooCommerce Settings - PushDaddy', ''.$translate_txt18.'', 'manage_options', 'pushdaddy-woocommerce-settings', 'pushdaddy_woocommerce_settings_callback');
    }
}

function pushdaddy_stats_callback() {
    $pd_stats = pushdaddy_get_stats();
    $pd_domain_id = $pd_stats['domain_id'];
	$translate_txt19 = __( 'PushDaddy Stats', 'pushdaddy-web-push-notifications' );
	$translate_txt16 = __( 'Send Notification', 'pushdaddy-web-push-notifications' );
	$translate_txt21 = __( 'Total Subscribers', 'pushdaddy-web-push-notifications' );
	$translate_txt22 = __( 'More Info', 'pushdaddy-web-push-notifications' );
	$translate_txt23 = __( 'Sent Notifications', 'pushdaddy-web-push-notifications' );
	$translate_txt24 = __( 'CTR', 'pushdaddy-web-push-notifications' );
	$translate_txt25 = __( 'Last Notification', 'pushdaddy-web-push-notifications' );
	$translate_txt26 = __( 'Attempted', 'pushdaddy-web-push-notifications' );
	$translate_txt27 = __( 'Delivered', 'pushdaddy-web-push-notifications' );
	$translate_txt28 = __( 'Clicked', 'pushdaddy-web-push-notifications' );
	$translate_txt29 = __( 'Sent at', 'pushdaddy-web-push-notifications' );
	$translate_txt30 = __( 'Last 7 Notifications', 'pushdaddy-web-push-notifications' );
	$translate_txt31 = __( 'More Stats', 'pushdaddy-web-push-notifications' );

?>
    <div class="pd-dashboard-title pd-clearfix">
        <h2 class="pd-pull-left">
            <?php echo $translate_txt19;?>
        </h2>

        <div class="pd-pull-right">
            <a class="pd-btn pd-btn-primary" href="<?php echo admin_url('admin.php?page=pushdaddy-send-notification')?>"><i class="fa fa-bell-o"></i> <?php echo $translate_txt16;?></a>
        </div>
    </div>

    <div class="pushdaddy-stats-top">
        <div>
            <div class="mini-box-panel mb30">
                <div class="panel-body pd-clearfix">
                    <div class="info pd-pull-left">
                        <h4 class="text-bold mb5 mt0"><?php echo $pd_stats['subscribers']?></h4>
                        <p class="text-uppercase"><?php echo $translate_txt21;?></p>
                    </div>
                    <div class="icon bg-blue pd-pull-right"><i class="fa fa-user"></i></div>
                </div>
                <div class="panel-footer pd-clearfix bg-blue">
                    <span class="text-uppercase pd-pull-left"></span>
                    <span class="pd-pull-right"><a target="_blank" href="https://pushdaddy.com/dashboard/<?php echo $pd_domain_id?>/analytics/subscribers"><?php echo $translate_txt22;?> <i class="fa fa-chevron-circle-right"></i></a></span>
                </div>
            </div>
        </div><!--

        --><div>
            <div class="mini-box-panel mb30">
                <div class="panel-body pd-clearfix">
                    <div class="info pd-pull-left">
                        <h4 class="text-bold mb5 mt0"><?php echo $pd_stats['sent_notifications']?></h4>
                        <p class="text-uppercase"><?php echo $translate_txt23;?></p>
                    </div>
                    <div class="icon bg-green pd-pull-right"><i class="fa fa-bell"></i></div>
                </div>
                <div class="panel-footer pd-clearfix bg-green">
                    <span class="text-uppercase pd-pull-left"></span>
                    <span class="pd-pull-right"><a target="_blank" href="https://pushdaddy.com/dashboard/<?php echo $pd_domain_id?>/analytics/sent"><?php echo $translate_txt22;?> <i class="fa fa-chevron-circle-right"></i></a></span>
                </div>
            </div>
        </div><!--

        --><div>
            <div class="mini-box-panel mb30">
                <div class="panel-body pd-clearfix">
                    <div class="info pd-pull-left">
                        <h4 class="text-bold mb5 mt0">
                            <?php echo $pd_stats['ctr']?>
                        </h4>
                        <p class="text-uppercase"><?php echo $translate_txt24;?></p>
                    </div>
                    <div class="icon bg-orange pd-pull-right"><i class="fa fa-mouse-pointer"></i></div>
                </div>
                <div class="panel-footer pd-clearfix bg-orange">
                    <span class="text-uppercase pd-pull-left"></span>
                    <span class="pd-pull-right"><a target="_blank" href="https://pushdaddy.com/dashboard/<?php echo $pd_domain_id?>/analytics/sent"><?php echo $translate_txt22;?> <i class="fa fa-chevron-circle-right"></i></a></span>
                </div>
            </div>
        </div>
    </div>

    <?php
        $lastNotificationT = $pd_stats['last_notification'];
        if ($lastNotificationT == null) {
            $lastNotification['attempted'] = "N/A";
            $lastNotification['delivered'] = "N/A";
            $lastNotification['clicked'] = "N/A";
            $lastNotification['ctr'] = "N/A";
            $lastNotification['icon'] = "https://cdn.pushdaddy.com/img/pushdaddy-square-icon.png";
            $lastNotification['title'] = "Notification Title";
            $lastNotification['message'] = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.";
            $lastNotification['url'] = "https://pushdaddy.com";
            $notf_count = 1;
        } else {
            $notf_count = count($lastNotificationT);
        }
        ?>

    <div class="pd-box-panel pd-last-notification">
        <div class="panel-heading">
            <?php echo $translate_txt25;?> <?php echo ($lastNotificationT!=null && count($lastNotificationT)==2)?"- A/B":""?>
        </div>

        <?php
            for ($i = 0; $i < $notf_count; $i++) {
                if ($lastNotificationT != null) {
                    $lastNotification = $lastNotificationT[$i];
                }
                if ($lastNotification['delivered'] == 0) {
                    $lastNotification['ctr'] = 'N/A';
                } else {
                    $lastNotification['ctr'] = round(($lastNotification['clicked'] * 100) / $lastNotification['delivered'], 2) . "%";
                }

                $show_percent = false;
                $percent_table = array();
                if ($lastNotification['button1_title'] != "" && $lastNotification['clicked'] != 0) {
                    $show_percent = true;
                    $percent_table[0] = number_format(($lastNotification['clicked_main'] * 100) / $lastNotification['clicked'], 1);
                    $percent_table[1] = number_format(($lastNotification['clicked_action1'] * 100) / $lastNotification['clicked'], 1);
                    $percent_table[2] = number_format(($lastNotification['clicked_action2'] * 100) / $lastNotification['clicked'], 1);
                    for ($k = 0; $k <= 2; $k++) {
                        if ($percent_table[$k] == "100.0") {
                            $percent_table[$k] = "100";
                        }
                    }
                }
        ?>
        <div class="panel-body pos-rel <?php if($i==1) echo "pd-mt20";?>">
            <div class="pd-mini-widget-group">
                <?php if($notf_count==2){if($i==0){echo "<span class='notification-type-ab'>A</span>";}else{echo "<span class='notification-type-ab'>B</span>";}}?>
                <div class="pd-mini-widget">
                    <i class="fa fa-tasks pd-txt-orange"></i>
                    <strong><?php echo $lastNotification['attempted'] ?></strong><br>
                    <?php echo $translate_txt26;?>
                </div><!--
                --><div class="pd-mini-widget">
                    <i class="fa fa-send pd-txt-light-blue"></i>
                    <strong><?php echo $lastNotification['delivered'] ?></strong><br>
                    <?php echo $translate_txt27;?>
                </div><!--
                --><div class="pd-mini-widget">
                    <i class="fa fa-hand-pointer-o pd-txt-green"></i>
                    <strong><?php echo $lastNotification['clicked'] ?></strong><br>
                    <?php echo $translate_txt28;?>
                </div><!--
                --><div class="pd-mini-widget">
                    <i class="fa fa-mouse-pointer pd-txt-teal"></i>
                    <strong><?php echo $lastNotification['ctr'] ?></strong><br>
                    <?php echo $translate_txt24;?>
                </div>
            </div><!--
            --><div class="pd-text-center">
                <div class="pd-push-notification pd-clearfix">
                    <div class="preview-icon-container pd-pull-left">
                        <img class="icon" src="<?php echo $lastNotification['icon'] ?>" />
                    </div>
                    <div class="pd-pull-right content">
                        <span class="title"><?php echo $lastNotification['title'] ?></span>
                        <span class="message"><?php echo $lastNotification['message'] ?></span>
                        <span class="site"><?php echo $pd_stats['domain']?></span>
                        <?php if($show_percent){?>
                        <div class="click-arrow">
                            <?php echo $percent_table[0]?>%
                        </div>
                        <?php }?>
                    </div>
                    <div class="pd-clearfix"></div>
                    <?php if($lastNotification['button1_title']!=""){?>
                    <div class="preview-action-button pd-clearfix text-left" style="display: block;">
                        <?php echo json_decode('"'.$lastNotification['button1_title'].'"')?>

                        <?php if($show_percent){?>
                        <div class="click-arrow action-arrow">
                            <?php echo $percent_table[1]?>%
                        </div>
                        <?php }?>
                    </div>
                    <?php }?>
                    <?php if($lastNotification['button2_title']!=""){?>
                    <div class="preview-action-button pd-clearfix text-left" style="display: block;">
                        <?php echo json_decode('"'.$lastNotification['button2_title'].'"')?>

                        <?php if($show_percent){?>
                        <div class="click-arrow action-arrow">
                            <?php echo $percent_table[2]?>%
                        </div>
                        <?php }?>
                    </div>
                    <?php }?>
                </div>
                <?php if (isset($lastNotification['sent_time'])) {?>
                <span class="ltime-label"><?php echo $translate_txt29;?>:</span> <span class="pd-ltime"><?php echo $lastNotification['sent_time'] ?></span>
                <?php }?>
            </div>
        </div>
        <?php }?>
    </div>

    <?php
    $lastNotifications = $pd_stats['last_7notifications'];
    if ($lastNotifications == null) {
        echo '<div class="overlay-no-access"><div>No notification sent.</div></div>';
        $lastNotifications['attempted'] = "N/A";
        $lastNotifications['delivered'] = "N/A";
        $lastNotifications['clicked'] = "N/A";
        $lastNotifications['ctr'] = "N/A";
    }
    else{
        $lastNotifications['attempted']=number_format($lastNotifications['attempted']);
        $lastNotifications['delivered']=number_format($lastNotifications['delivered']);
        $lastNotifications['clicked']=number_format($lastNotifications['clicked']);
    }
    ?>
    <div class="pd-box-panel">
        <div class="panel-heading">
            <?php echo $translate_txt30;?>
        </div>
        <div class="panel-body pos-rel">
            <div class="pd-data-legend">
                <div class="pd-txt-orange">
                    <strong><?php echo ($lastNotifications['attempted']) ?></strong><br><?php echo $translate_txt26;?>
                </div><!--
                --><div class="pd-txt-light-blue">
                    <strong><?php echo ($lastNotifications['delivered']) ?></strong><br><?php echo $translate_txt27;?>
                </div><!--
                --><div class="pd-txt-green">
                    <strong><?php echo ($lastNotifications['clicked']) ?></strong><br><?php echo $translate_txt28;?>
                </div><!--
                --><div class="pd-txt-teal">
                    <strong ><?php echo ($lastNotifications['ctr']) ?></strong><br><?php echo $translate_txt24;?>
                </div>
            </div>
            <div class="pd-notification-graph">
                <div class="pd-graph-container">
                    <canvas class="mt15" id="canvas" height="230" width="400"></canvas>
                    <script>
                        var lineChartData = {
                            labels: ["Notf#1","Notf#2","Notf#3","Notf#4","Notf#5","Notf#6","Notf#7"],
                            datasets: [
                                {
                                    label: "Attempted",
                                    lineTension: 0,
                                    backgroundColor: "rgba(255,133,27,0.25)",
                                    borderColor: "rgba(255,133,27,1)",
                                    pointBackgroundColor: "rgba(255,133,27,1)",
                                    pointBorderColor: "#fff",
                                    pointHoverBorderColor: "rgba(211,84,0,1)",
                                    data: [<?php if (isset($lastNotifications['attempt_data'])) echo implode(",", array_reverse($lastNotifications['attempt_data']));
                                else echo '110,120,164,189,210,211,232'; ?>]
                                },
                                {
                                    label: "Delivered",
                                    lineTension: 0,
                                    backgroundColor: "rgba(60,141,188,0.25)",
                                    borderColor: "rgba(60,141,188,1)",
                                    pointBackgroundColor: "rgba(60,141,188,1)",
                                    pointBorderColor: "#fff",
                                    pointHoverBorderColor: "rgba(41,128,185,1)",
                                    data: [<?php if (isset($lastNotifications['deliver_data'])) echo implode(",", array_reverse($lastNotifications['deliver_data']));
                                else echo '90,110,134,129,171,168,202'; ?>]
                                },
                                {
                                    label: "Clicked",
                                    lineTension: 0,
                                    backgroundColor: "rgba(0,166,90,0.25)",
                                    borderColor: "rgba(0,166,90,1)",
                                    pointBackgroundColor: "rgba(0,166,90,1)",
                                    pointBorderColor: "#fff",
                                    pointHoverBorderColor: "rgba(22,160,133,1)",
                                    data: [<?php if (isset($lastNotifications['click_data'])) echo implode(",", array_reverse($lastNotifications['click_data']));
                                else echo '9,13,17,18,23,24,21'; ?>]
                                }
                            ]

                        }

                        var pd_ltime = document.getElementsByClassName("pd-ltime");
                        for(var k=0; k<pd_ltime.length; k++){
                            d = new Date(parseFloat(pd_ltime[k].innerText)*1000)
                            pd_ltime[k].innerText = d.toString();
                        }

                    </script>
                </div>
            </div>
        </div>
    </div>

    <div class="pd-more-stats-container">
        <a  target="_blank" href="https://pushdaddy.com/dashboard/<?php echo $pd_domain_id?>" class="button button-primary"><?php echo $translate_txt31;?></a>
    </div>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.4.0/Chart.min.js"></script>
    <script>
        Chart.defaults.global.defaultFontSize = 14;
        Chart.defaults.global.defaultFontColor = '#444';

        var myLineChart = new Chart(document.getElementById("canvas"), {
                type: 'line',
                data: lineChartData,
                options: {
                    responsive: true,
                    tooltips: {
                        mode: 'index'
                    }
                }
            });
    </script>
<?php
    add_filter('admin_footer_text', 'pushdaddy_replace_footer_admin');
}

function pushdaddy_send_notifications_callback() {
    global $title;

    echo "<h2>$title</h2>";
    if(isset($_POST['pd-send-submit'])){
        if (!isset($_POST['pushdaddy-submenu-page-save-nonce']) || (!wp_verify_nonce($_POST['pushdaddy-submenu-page-save-nonce'], plugin_basename(__FILE__)))){
            echo '<div class="error"><p>Something went wrong!</p></div>';
        }
        else{
            $success = true;
            $notification_title = filter_input(INPUT_POST, 'woocommerce_pushdaddy_send_notification_title');
            $notification_message = filter_input(INPUT_POST, 'woocommerce_pushdaddy_send_notification_message');
            $notification_url = filter_input(INPUT_POST, 'woocommerce_pushdaddy_send_notification_url');

            if (isPDWooCommerceEnable() && get_option('_pushdaddy_send_to_custom', 0)) {
                $user_id = trim(filter_input(INPUT_POST, 'woocommerce_pushdaddy_send_notification_user_id'));
                if($user_id==="0"){
                    $notification_url = pushdaddy_setGetParameter($notification_url, 'pushdaddy_source', 'dn');
                    pushdaddy_send_notification_curl($notification_title, $notification_message, $notification_url);
                }
                else{
                    if(!is_numeric($user_id)){
                        $user = get_user_by( 'email', sanitize_email($user_id));
                        if($user){
                            $user_id = $user->ID;
                        }
                        else{
                            $success = false;
                        }
                    }

                    if($success){
                        $notification_url = pushdaddy_setGetParameter($notification_url, 'pushdaddy_source', 'dn');
                        pushdaddy_send_to_custom($notification_title, $notification_message, $notification_url, 'user_id', $user_id);
                    }
                }
            }
            else{
                pushdaddy_send_notification_curl($notification_title, $notification_message, $notification_url);
            }

            if($success){
                echo '<div class="updated"><p>Notification sent successfully!</p></div>';
            }
            else{
                echo '<div class="error"><p>Invald User ID/Email!</p></div>';
            }
        }
    }
    echo '<form method="POST" action="">
        <table class="form-table">';

    if (isPDWooCommerceEnable() && get_option('_pushdaddy_send_to_custom', 0)) {
        echo'   <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woocommerce_pushdaddy_send_notification_user_id">User ID or Email</label>
                </th>
                <td class="forminp forminp-text">
                    <input name="woocommerce_pushdaddy_send_notification_user_id" id="woocommerce_pushdaddy_send_notification_user_id" placeholder="User ID or Email" required>
                    <span class="description">Use 0 to send to all or user id or email to send to a specific user.</span>
                </td>
            </tr>';
    }

    echo'   <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woocommerce_pushdaddy_send_notification_title">Notification Title</label>
                </th>
                <td class="forminp forminp-text">
                    <input name="woocommerce_pushdaddy_send_notification_title" id="woocommerce_pushdaddy_send_notification_title" placeholder="Notification Title" class="pushdaddy-woocommerce-text" required maxlength="64">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woocommerce_pushdaddy_send_notification_message">Notification Message</label>
                </th>
                <td class="forminp forminp-text">
                    <textarea name="woocommerce_pushdaddy_send_notification_message" rows="3" id="woocommerce_pushdaddy_send_notification_message" placeholder="Notification Message" class="pushdaddy-woocommerce-text" required maxlength="192"></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woocommerce_pushdaddy_send_notification_url">Target URL</label>
                </th>
                <td class="forminp forminp-text">
                    <input name="woocommerce_pushdaddy_send_notification_url" id="woocommerce_pushdaddy_send_notification_url" placeholder="Target URL" class="pushdaddy-woocommerce-text" required>
                </td>
            </tr>
        </table>';
        submit_button( 'Send Notification', 'primary', 'pd-send-submit' );
        wp_nonce_field( plugin_basename(__FILE__), 'pushdaddy-submenu-page-save-nonce' );
        echo '</form>';

        add_filter('admin_footer_text', 'pushdaddy_replace_footer_admin');
}

function pushdaddy_general_settings_callback(){

    global $title;

    echo "<h2>$title</h2>";
?>

<?php
    if(isset($_POST['pd-save-changes'])){
        if (!isset($_POST['pushdaddy-submenu-page-save-nonce']) || (!wp_verify_nonce($_POST['pushdaddy-submenu-page-save-nonce'], plugin_basename(__FILE__)))){
            echo '<div class="error"><p>Something went wrong!</p></div>';
        }
        else{
            $success = true;
            $pd_web_id = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_web_id'));
            $pd_api_key1= pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_api_key'));
			$pd_api_key= sanitize_text_field($pd_api_key1);

            $pd_default_title = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_default_title'));

            $pd_utm_source = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_utm_source'));
            $pd_utm_medium = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_utm_medium'));
            $pd_utm_campaign = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'pushdaddy_utm_campaign'));

            if (isset($_POST['pushdaddy_large_image']) && is_numeric($_POST['pushdaddy_large_image']) && $_POST['pushdaddy_large_image'] == 1) {
                $pd_large_image = 1;
            }
            else{
                $pd_large_image = 0;
            }

            update_option('pushdaddy_web_id', $pd_web_id);
            update_option('pushdaddy_api_key', $pd_api_key);
            update_option('pushdaddy_default_title', $pd_default_title);

            update_option('pushdaddy_utm_source', $pd_utm_source);
            update_option('pushdaddy_utm_medium', $pd_utm_medium);
            update_option('pushdaddy_utm_campaign', $pd_utm_campaign);

            update_option('pushdaddy_large_image', $pd_large_image);

            isPDWooCommerceEnable(true);

            echo '<div class="updated"><p>Changes saved successfully!</p></div>';

        }
    }
	
		$translate_txt32 = __( 'Get API key here', 'pushdaddy-web-push-notifications' );
		$translate_txt33 = __( 'Live Support to help in integrating', 'pushdaddy-web-push-notifications' );
		$translate_txt34 = __( 'Website Settings', 'pushdaddy-web-push-notifications' );
		$translate_txt35 = __( 'Website ID', 'pushdaddy-web-push-notifications' );
		$translate_txt36 = __( 'REST API Key', 'pushdaddy-web-push-notifications' );
		$translate_txt37 = __( 'Default Title', 'pushdaddy-web-push-notifications' );
		$translate_txt38 = __( 'UTM Params', 'pushdaddy-web-push-notifications' );
		$translate_txt39 = __( 'Source', 'pushdaddy-web-push-notifications' );
		$translate_txt40 = __( 'Medium', 'pushdaddy-web-push-notifications' );
		$translate_txt41 = __( 'Name', 'pushdaddy-web-push-notifications' );
		$translate_txt42 = __( 'Others', 'pushdaddy-web-push-notifications' );
		$translate_txt43 = __( 'Add featured image as a large image in notifications (only for HTTPS websites)', 'pushdaddy-web-push-notifications' );

	
	
?>
    <h2><?php echo $translate_txt32; ?> <a target="_blank" href="https://pushdaddy.com/">https://pushdaddy.com/</a> <?php echo $translate_txt33; ?></h2>
<small>Change default icon, welcome message icon, welcome message, action button, text, icon etc from this dashboard soon</small>
    <form method="post" action="">
    <?php settings_fields('pushdaddy'); ?>
        <table class="form-table">
            <tr><th scope="row"><h3><?php echo $translate_txt34; ?></h3></th></tr>
            <tr>
                <th scope="row"><?php echo $translate_txt35; ?></th>
                <td><input type="text" required name="pushdaddy_web_id" size="64" value="<?php echo esc_attr(get_option('pushdaddy_web_id')); ?>" placeholder="Website ID" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo $translate_txt36; ?></th>
                <td><input type="text" required name="pushdaddy_api_key" size="64" value="<?php echo esc_attr(get_option('pushdaddy_api_key')); ?>" placeholder="REST API Key" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo $translate_txt37; ?></th>
                <td><input type="text" name="pushdaddy_default_title" size="64" maxlength="64" value="<?php echo esc_attr(get_option('pushdaddy_default_title')); ?>" placeholder="Title"/></td>
            </tr>

            <tr><th scope="row"><h3><?php echo $translate_txt38; ?></h3></th></tr>
            <tr>
                <th scope="row"><?php echo $translate_txt39; ?></th>
                <td><input type="text" name="pushdaddy_utm_source" size="64" maxlength="32" value="<?php echo esc_attr(get_option('pushdaddy_utm_source')); ?>" placeholder="pushdaddy"/></td>
            </tr>
            <tr>
                <th scope="row"><?php echo $translate_txt40; ?></th>
                <td><input type="text" name="pushdaddy_utm_medium" size="64" maxlength="32" value="<?php echo esc_attr(get_option('pushdaddy_utm_medium')); ?>" placeholder="push_notification"/></td>
            </tr>
            <tr>
                <th scope="row"><?php echo $translate_txt41; ?></th>
                <td><input type="text" name="pushdaddy_utm_campaign" size="64" maxlength="32" value="<?php echo esc_attr(get_option('pushdaddy_utm_campaign')); ?>" placeholder="pushdaddy_campaign"/></td>
            </tr>

            <tr><th scope="row"><h3><?php echo $translate_txt42; ?></h3></th></tr>
            <tr>
                <th scope="row" colspan="2">
                    <label><input type="checkbox" name="pushdaddy_large_image" <?php if(get_option('pushdaddy_large_image', 0)){echo 'checked';} ?> value="1"/> <?php echo $translate_txt43; ?></label>
                </th>
            </tr>
        </table>
    <?php
        submit_button( 'Save Changes', 'primary', 'pd-save-changes' );
        wp_nonce_field( plugin_basename(__FILE__), 'pushdaddy-submenu-page-save-nonce' );
    ?>
    </form>
    <?php

    add_filter('admin_footer_text', 'pushdaddy_replace_footer_admin');
}

function pushdaddy_woocommerce_settings_callback(){
    pushdaddy_load_settings();

    global $title;
    echo "<h2>$title</h2>";
?>

<?php
    if(isset($_POST['pd-woo-save-changes'])){
        if (!isset($_POST['pushdaddy-submenu-page-save-nonce']) || (!wp_verify_nonce($_POST['pushdaddy-submenu-page-save-nonce'], plugin_basename(__FILE__)))){
            echo '<div class="error"><p>Something went wrong!</p></div>';
        }
        else{
            $success = true;
            $pd_woo_confirm_message = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_confirm_message'));
            $pd_woo_button_yes= pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_button_yes'));
            $pd_woo_button_no = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_button_no'));
            $pd_woo_auto_assoc = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_auto_assoc'));

            $pd_woo_assoc_css = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_association_css'));
            $pd_woo_dashboard_option = pushdaddy_sanitize_text_field(filter_input(INPUT_POST, 'woocommerce_settings_pushdaddy_dashboard_option'));

            update_option('woocommerce_settings_pushdaddy_confirm_message', $pd_woo_confirm_message);
            update_option('woocommerce_settings_pushdaddy_button_yes', $pd_woo_button_yes);
            update_option('woocommerce_settings_pushdaddy_button_no', $pd_woo_button_no);
            update_option('woocommerce_settings_pushdaddy_auto_assoc', $pd_woo_auto_assoc);

            update_option('woocommerce_settings_pushdaddy_association_css', $pd_woo_assoc_css);
            update_option('woocommerce_settings_pushdaddy_dashboard_option', $pd_woo_dashboard_option);

            echo '<div class="updated"><p>Changes saved successfully!</p></div>';

        }
    }
?>

    <form method="post" action="">
    <?php settings_fields('pushdaddy'); ?>
        <table class="form-table">
            <tr>
                <th class="pd-settings-woo-title" colspan="2">
                    <h3>Subscription ID Association</h3>
                    <p>This message is shown to associate the logged in user to the PushDaddy subscription ID. It is only shown to users, who subscribed to push notifications before logging into their account.</p>
                </th>
            </tr>
            <tr>
                <th scope="row">Automatic Assocication</th>
                <td>
                    <select class="pushdaddy-woocommerce-text" type="text" required name="woocommerce_settings_pushdaddy_auto_assoc">
                        <option value="1" <?php if(esc_attr(get_option('woocommerce_settings_pushdaddy_auto_assoc'))==1){echo 'selected';}; ?>><?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_auto_assoc_yes')); ?></option>
                        <option value="2" <?php if(esc_attr(get_option('woocommerce_settings_pushdaddy_auto_assoc'))==2){echo 'selected';}; ?>><?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_auto_assoc_no')); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Confirm Message</th>
                <td><input class="pushdaddy-woocommerce-text" type="text" required name="woocommerce_settings_pushdaddy_confirm_message" value="<?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_confirm_message')); ?>" placeholder="Website ID" /></td>
            </tr>
            <tr>
                <th scope="row">Button Yes</th>
                <td><input class="pushdaddy-woocommerce-text-small" type="text" required name="woocommerce_settings_pushdaddy_button_yes" value="<?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_button_yes')); ?>" placeholder="REST API Key" /></td>
            </tr>
            <tr>
                <th scope="row">Button No</th>
                <td><input class="pushdaddy-woocommerce-text-small" type="text" name="woocommerce_settings_pushdaddy_button_no" value="<?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_button_no')); ?>" placeholder="Title"/></td>
            </tr>
            <tr>
                <th scope="row">CSS</th>
                <td><textarea class="pushdaddy-woocommerce-text-css" name="woocommerce_settings_pushdaddy_association_css" rows="10"><?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_association_css')); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row">Enable Notification Option Text</th>
                <td>
                    <input class="pushdaddy-woocommerce-text" type="text" name="woocommerce_settings_pushdaddy_dashboard_option" value="<?php echo esc_attr(get_option('woocommerce_settings_pushdaddy_dashboard_option')); ?>" placeholder="Title"/>
                    <span class="description"><br>Shown in My Account section of the WooCommerce account of your customer, where they can easily enable/disable notifications.</span>
                </td>
            </tr>

            <tr>
                <th class="pd-settings-woo-title" colspan="2">
                    <h3>Cart Abandonment, Out of Stock alerts, Price Drop alerts and Shipment Notifications alerts</h3>
                    <p>Visit <a href="https://pushdaddy.com/dashboard" target="_blank">PushDaddy Dashboard</a> to configure Cart Abandonment, Out of Stock, Price Drop and Shipment Notifications.</p>
                </th>
            </tr>
        </table>
    <?php
        submit_button( 'Save Changes', 'primary', 'pd-woo-save-changes' );
        wp_nonce_field( plugin_basename(__FILE__), 'pushdaddy-submenu-page-save-nonce' );
    ?>
    </form>
    <?php

    add_filter('admin_footer_text', 'pushdaddy_replace_footer_admin');
}

function pd_check_product_page(){
    if(is_product()){
        global $product;
        if(pd_woocommerce_version_check()){
            $product_id = $product->get_id();
        }
        else{
            $product_id = $product->id;
        }
        ?>
        <script type="text/javascript">
            var pd_woo_product_info = <?php echo json_encode(
                    array(
                    'id'=>$product_id,
                    'variant_id'=> 0,
                    'title'=>$product->get_title(),
                    'price'=>$product->get_price(),
                    'price_formatted'=>strip_tags(wc_price($product->get_price())),
                    'type' =>$product->get_type(),
                    'image' => wp_get_attachment_url($product->get_image_id()),
                    'outofstock' => ($product->is_in_stock())?false:true
                    )); ?>
        </script>
        <?php
    }
}

function pd_woo_price_stock_update( $meta_id, $post_id, $meta_key, $meta_value ){
    $post_type = get_post_type($post_id);

    if($post_type=="product" || $post_type=="product_variation"){
        $what_changed=false;
        if($meta_key=="_price" && get_option('_pushdaddy_price_drop', 0)){ //price changed
            $what_changed = "price";
        }
        else if($meta_key=="_stock_status" && $meta_value=="instock" && get_option('_pushdaddy_out_of_stock', 0)){ //stock status changed
            $what_changed = "outofstock";
        }

        if($what_changed){
            $product = wc_get_product($post_id);
            if($post_type=="product_variation"){
                $product_id = wp_get_post_parent_id($post_id);
                $variant_id = $post_id;
            }
            else{
                $product_id = $post_id;
                $variant_id = 0;
            }

            $cart_url = apply_filters( 'woocommerce_get_cart_url', wc_get_page_permalink( 'cart' ) );
            $product_info = array(
                    'id'=>$product_id,
                    'variant_id'=> $variant_id,
                    'title'=>$product->get_title(),
                    'price'=>$product->get_price(),
                    'price_formatted'=>wc_price($product->get_price()),
                    'type' =>$product->get_type(),
                    'image' => wp_get_attachment_url($product->get_image_id()),
                    'outofstock' => ($product->is_in_stock())?0:1,
                    'url' => $product->get_permalink(),
                    'add_to_cart'=> pd_format_add_to_cart_link($product->add_to_cart_url(), $cart_url),
                    'changed' => $what_changed,
                    'currency' => array(get_woocommerce_currency(),get_woocommerce_currency_symbol())
                    );
            pushdaddy_product_update($product_info);
        }
    }
}

function pd_woo_track_shipment( $meta_id, $post_id, $meta_key, $meta_value ){
    if($meta_key=='_wc_shipment_tracking_items'){
        $st = WC_Shipment_Tracking_Actions::get_instance();
        //$items = json_decode($meta_value, true);
        $fromatted_links = $st->get_formatted_tracking_item( $order_id, $meta_value[0]);

        $order = new WC_Order($post_id);
        $customer_info = $order->get_user();

        if($customer_info){
            $first_name = $customer_info->first_name;
            if($first_name==""){
                $first_name = $customer_info->nickname;
            }

            $order_status_update = array(
                "order_id" => $post_id,
                "order_status" => 'shipped',
                "customer_id" => $customer_info->id,
                "first_name" => $first_name,
                "last_name" => $customer_info->last_name,
                "order_status_url" => $order->get_view_order_url(),
                "tracking_url" => $fromatted_links['formatted_tracking_link']
            );

            pushdaddy_track_order_shipment($order_status_update);
        }
    }
}


function pd_format_add_to_cart_link($ajax_add_to_cart, $cart_url){
    //admin_url( 'admin-ajax.php', 'relative')
    if(strpos($ajax_add_to_cart, "?")!==false){
       $get_part = parse_url($ajax_add_to_cart, PHP_URL_QUERY);

       $query = parse_url($cart_url, PHP_URL_QUERY);
        if ($query) {
            $cart_url .= '&'.$get_part;
        } else {
            $cart_url .= '?'.$get_part;
        }

        return $cart_url;
    }
    else{
        return pd_get_root_domain().$ajax_add_to_cart;
    }
}

function pd_get_root_domain() {
    $url_parts = parse_url( get_site_url() );
    if ( $url_parts && isset( $url_parts['host'] ) ) {
            return $url_parts['host'];
    }
    return false;
}

function pd_woocommerce_version_check( $version = '2.6' ) {
    global $woocommerce;
    if( version_compare( $woocommerce->version, $version, ">=" ) ) {
        return true;
    }
    else{
        return false;
    }
}

?>
