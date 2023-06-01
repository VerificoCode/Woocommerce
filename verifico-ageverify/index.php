<?php
/*
* Plugin Name: AgeChecked Plus+
* Plugin URI: https://www.agechecked.com/
* Description: Adds age verification to your website.
* Version: 1.2.0
* Author: Verifico
* Author URI: https://www.verifico.co.uk
*/

$GLOBALS['unityName'] = "AgeChecked Plus+";
$GLOBALS['unityUrl'] = "https://unity.sites.test";
$GLOBALS['unityVersion'] = "2_0_0";
// $GLOBALS['unityUrl'] = "https://staging.unity.agechecked.com";
// $GLOBALS['unityUrl'] = "http://localhost:32776";

if (!class_exists('woocommerce'))
{
    // return;
    // exit;
}// Exit if WooCommerce is not active

add_action( 'wp_footer', 'unity_ageverify' );

function unity_ageverify(){
    //On every page
    echo "<script>
    var script = document.createElement('script');
    script.src = \"".$GLOBALS['unityUrl']."/tr/?domain=\"+window.location.hostname+\"&v=".$GLOBALS['unityVersion']."\";
    script.async = true;
    document.getElementsByTagName('head')[0].appendChild(script);
    </script>";

}


function rj_add_query_vars_filter( $vars ){
    $vars[] = "ac_api_user";
    return $vars;
}
add_filter( 'query_vars', 'rj_add_query_vars_filter' );

function ac_orderData($order_id)
{
    //getting order object
    $order = wc_get_order($order_id);

    // Get the Customer billing email
    $billing_email  = $order->get_billing_email();
    //echo $billing_email;

    // Get the Customer billing phone
    $billing_phone  = $order->get_billing_phone();

    // Customer billing information details
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name  = $order->get_billing_last_name();
    $billing_company    = $order->get_billing_company();
    $billing_address_1  = $order->get_billing_address_1();
    $billing_address_2  = $order->get_billing_address_2();
    $billing_city       = $order->get_billing_city();
    $billing_state      = $order->get_billing_state();
    $billing_postcode   = $order->get_billing_postcode();
    $billing_country    = $order->get_billing_country();
    $billing_phone    = $order->get_billing_phone();

    // Customer shipping information details
    $shipping_first_name = $order->get_shipping_first_name();
    $shipping_last_name  = $order->get_shipping_last_name();
    $shipping_company    = $order->get_shipping_company();
    $shipping_address_1  = $order->get_shipping_address_1();
    $shipping_address_2  = $order->get_shipping_address_2();
    $shipping_city       = $order->get_shipping_city();
    $shipping_state      = $order->get_shipping_state();
    $shipping_postcode   = $order->get_shipping_postcode();
    $shipping_country    = $order->get_shipping_country();
    $shipping_phone    = $order->get_shipping_phone();

    $wc_order_id = $order->get_id();
    
    $orderArg = array(
        'customer' => $billing_email,
        'limit' => -1,
        );
    $orders = wc_get_orders($orderArg);
    $orderCount = count($orders);
    $lastOrderDate = '';
    $firstOrderDate = '';
    if($orderCount>1) {
        
        $lastOrderDate = $orders[0]->get_date_created()->__toString();
        end($orders);
        $firstOrderDate = prev($orders)->get_date_created()->__toString();

    }

    
    //$lastOrderDate = end($orders);
    // if($orders){
    //   foreach ($orders as  $orderData) {
    //                 print_r($orderData);
    //          }
    // }

    $items = $order->get_items();
    $product_js = '';

    $options = get_option( 'unity_plugin_options' );




    foreach ($items as $item_id => $item_data)
    {   
        //getting product object
        $_product = wc_get_product($item_data->get_product_id());
        $requires_av = 0;
        //getting all the product category
        $pro_cat_array = wp_get_post_terms($item_data->get_product_id(), 'product_cat', array( 'fields' => 'ids' ));
        $row_price = $item_data->get_total();
        $sku = $sku = $_product->get_sku();
        $name = $sku = $_product->get_name();
        $qty = $item_data->get_quantity();

        if(array_key_exists('category', $options)&&$options['mode']==1) {
            if(!empty($options['category'])) {
                foreach($options['category'] as $cats) {
                    if(in_array($cats, $pro_cat_array)) {
                        $requires_av = 1;
                        continue;
                    }
                }
            }
        }

        if(get_post_meta( $item_data->get_product_id(), '_ac_av_meta_key' )[0]=='on'&&$options['mode']==2) {
            $requires_av = 1;
        }

        if($options['mode']==0) {
            $requires_av = 1;
        }

        //storing all the line item as a string form
        $product_js .= '{id: "' . $sku . '",name:"' . $name . '",price:"' . $row_price . '",quantity:"' . $qty . '",requires_av:"' . $requires_av . '"},';
        
    }

    echo '<script type="text/javascript">
    var acTransaction = {
    "user":[{
    "email":"'.$billing_email.'",
    "firstname":"'.$billing_first_name.'",
    "lastname":"'.$billing_last_name.'",
    "phone":"'.$billing_phone.'",
    "order_id":"'.$order_id.'",
    "dob":"",
    "address":{
    "address1":"'.$billing_address_1.'",
    "address2":"'.$billing_address_2.'",
    "address3":"",
    "city":"'.$billing_city.'",
    "postcode":"'.$billing_postcode.'",
    "country":"'.$billing_country.'"},
    "shippingData":{
        "firstname":"'.$shipping_first_name.'",
        "lastname":"'.$shipping_last_name.'",
        "address1":"'.$shipping_address_1.'",
        "address2":"'.$shipping_address_2.'",
        "address3":"",
        "city":"'.$shipping_city.'",
        "postcode":"'.$shipping_postcode.'",
        "country":"'.$shipping_country.'",
        "phone":"'.$shipping_phone.'"}, 
    "site_transaction_ref": "'.$wc_order_id.'",
    "metaData":{
        "lifetimeOrderCount":"'.$orderCount.'",
        "lastOrderDate":"'.$lastOrderDate.'",
        "firstOrderDate":"'.$firstOrderDate.'"
    }
    }
    ],
    "cart": [{"products":[
        '.$product_js.'
    ]}]
    };
    </script>';

}

add_action('woocommerce_thankyou', 'ac_orderData');

function register_shipment_arrival_order_status() {
    register_post_status( 'wc-av-high-risk', array(
        'label'                     => 'Age Verified High Risk',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Age Verified High Risk <span class="count">(%s)</span>', 'Age Verified High Risk <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'wc-av-low-risk', array(
        'label'                     => 'Age Verified Low Risk',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Age Verified Low Risk <span class="count">(%s)</span>', 'Age Verified Low Risk <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'wc-av-pending', array(
        'label'                     => 'Pending Age Verification',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Pending Age Verification <span class="count">(%s)</span>', 'Pending Age Verification <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_shipment_arrival_order_status' );


function add_awaiting_shipment_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-av-high-risk'] = 'Age Verified High Risk';
            $new_order_statuses['wc-av-low-risk'] = 'Age Verified Low Risk';
            $new_order_statuses['wc-av-pending'] = 'Pending Age Verification';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_awaiting_shipment_to_order_statuses' );


function unity_add_settings_page() {
    add_options_page( $GLOBALS['unityName'].' Settings', $GLOBALS['unityName'].' Settings', 'manage_options', 'unity-plugin', 'unity_render_plugin_settings_page' );   
}
add_action( 'admin_menu', 'unity_add_settings_page' );



function unity_render_plugin_settings_page() {

    $options = get_option( 'unity_plugin_options' );
    $class = '';
    if($options['mode']=='1') {
        $class = 'av_hide';
    }
    $image_url = '';
    $logo = get_theme_mod( 'custom_logo' );
    $image = wp_get_attachment_image_src( $logo , 'full' );
    if($image) {
        $image_url = $image[0];
    }
    
    ?>
    <h1 class="av_h1"><?= $GLOBALS['unityName'] ?> Settings</h1>

    <?php if(current_user_can('administrator')) { ?>
    <!-- Stuff here for administrators -->
    <!--a href="?page=dbi-example-plugin&api_user=create">New User</a>
    <ol>
        <li>Click this link to <a href="<?php echo get_admin_url().'admin.php'.'?page=wc-settings&tab=advanced&section=keys&create-key=1' ?>" target="_blank">create a new Rest API user</a>.</li>
        <li>You will need to use</li>
    </ol-->
    <?php } else { ?>
        <p>You need to be logged in as an administrator to create Rest API credentials.</p>
    <?php } ?>
    
    <?php if(!api_user_exists($GLOBALS['unityName'])) { ?>
    <!--hr /-->
    <h2>Start using <?= $GLOBALS['unityName'] ?></h2>
    <p>In order to start age verifying your customers you will need to create a Woocommerce API user and register with <?= $GLOBALS['unityName'] ?>. You can do so by clicking on the button below:</p>
    <?php //echo get_bloginfo( 'name' );?>
    <?php //echo get_bloginfo( 'url' );?>
    <?php //echo 'Wordpress'; ?>
    <?php //echo get_bloginfo( 'admin_email' );?>
    <!--a href="<?php echo get_admin_url().'admin.php'.'?page=wc-settings&tab=advanced&section=keys' ?>" target="_blank">Rest API</a-->

    
    
    <div> <a class="av_button ac-api-user" href="#" target="_blank">Click here to register</a><a  style="margin-left:20px;color:#206eba;" class=" ac-api-user" target="_blank" href="#">Try me first</a></div>

    
    <?php } else { ?>
    <h2>Looks like you have already registered?</h2>
    <p>If you have already registered to use <?= $GLOBALS['unityName'] ?> then complete the settings section below with your required configuration and you can start verifying now. I'm having problems - <a class=" ac-api-user" href="#">register again</a>.</p>
    <?php }; ?>
    <script>

jQuery(document).ready(function($) {

    // jquery extend function
    $.extend(
        {
            redirectPost: function(location, args)
            {
                var form = '';
                $.each( args, function( key, value ) {
                    value = value.split('"').join('\"')
                    form += '<input type="hidden" name="'+key+'" value="'+value+'">';
                });
                $('<form action="' + location + '" method="POST">' + form + '</form>').appendTo($(document.body)).submit();
            }
        });

    $('.ac-api-user').click(function(e) {
        
        e.preventDefault();
        var data = {
            'action': 'my_action'
        };

        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        $.post(ajaxurl, data, function(response) {
            response = JSON.parse(response);
            if(response.user_id) {
                var redirect = '<?= $GLOBALS['unityUrl'] ?>/setup/auto-register/';
                $.redirectPost(redirect, {
                    'name': "<?= get_bloginfo( 'name' ) ?>", 
                    'url': "<?= get_bloginfo( 'url' ) ?>", 
                    'email': "<?= get_bloginfo( 'admin_email' ) ?>", 
                    'logo': "<?= $image_url ?>",
                    'email': "<?= get_bloginfo('admin_email') ?>",
                    'platform': 'woocommerce', 
                    'basic_auth': response.consumer_key+':'+response.consumer_secret,
                });
            }
        });
        }); 
    })

    </script>
    <hr style="margin-top:30px;"/>
     <?php  ?>
    <form action="options.php" method="post">
        <?php 
        settings_errors();
        settings_fields( 'unity_plugin_options' );
        do_settings_sections( 'unity_plugin' ); ?>
        
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
    <!--p style="margin-top:20px;color:#999;font-style:italic;">This integration is provided in partnership with Verifico</p-->
   
    <style>/*.av_categories {display:none;}*/.av_h1 {margin-bottom:40px;} .av_button {text-decoration:none;font-size:18px;background:#206eba;cursor:pointer;color:#fff;padding: 15px 20px;display:inline-block;} .av_button:hover {color:#fff;} .av_select {min-width:160px;} .av_select option {padding: 5px;} .av_hide {display:none;}</style>
    <script>jQuery(document).ready(function($){
        if($('#unity_setting_mode').value!=1) {
            //$('.av_categories').hide();
        }
$('#unity_setting_mode').change(function() {      
    if (this.value === '1') {        
        $('.av_categories').show();                   
    } else {
        $('.av_categories').hide();
    }
    if (this.value === '2') {        
        $('.av_products').show();                   
    } else {
        $('.av_products').hide();
    }
});

});</script>
    <?php
}

function api_user_exists($user){

    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_api_keys WHERE description like %s", $user.'%'));

    if($count > 0){ return true; }else{ return false; }

}


function createUser() {
    
    global $wpdb;
    $key_id = 0;
    $permissions = 'read_write';
    $description = $GLOBALS['unityName'];
    $user_id = 1;
    $results = $wpdb->get_results( 
        $wpdb->prepare("SELECT count(key_id) as total FROM {$wpdb->prefix}woocommerce_api_keys WHERE description like \"".$description."%\"") 
     );
        if($results[0]->total>0) {
            $description = $description.$results[0]->total; 
        }
    

        if ( 0 < $key_id ) {
            //update api key 
       } else {
            $status           = 2 ;
            $consumer_key     = 'ck_' . wc_rand_hash ();
            $consumer_secret = 'cs_' . wc_rand_hash ();
    
           $data = array (
                'user_id'          => $user_id ,
                'description'      => $description ,
                'permissions'      => $permissions ,
                'consumer_key'     => wc_api_hash ( $consumer_key ),
                'consumer_secret' => $consumer_secret ,
                'truncated_key'    => substr ( $consumer_key , -7 )
           );
    
           $wpdb->insert (
                $wpdb->prefix. 'woocommerce_api_keys' ,
                $data ,
                array (
                    '%d' ,
                    "%s" ,
                    "%s" ,
                    "%s" ,
                    "%s" ,
                    "%s"
               )
           );
    
           $key_id                   = $wpdb->insert_id ;
            $data [ 'consumer_key' ] = $consumer_key ;
            $data [ 'consumer_secret' ] = $consumer_secret ;
           return $data;
    
       }

    
    
    
}



function unity_register_settings() {
    $options = get_option( 'unity_plugin_options' );
    $class1 = '';
    if($options['mode']!='1') {
        $class1 = 'av_hide';
    }
    $class2 = '';
    if($options['mode']!='2') {
        $class2 = 'av_hide';
    }
    register_setting( 'unity_plugin_options', 'unity_plugin_options', 'unity_plugin_options_validate' );

    add_settings_section( 'api_settings', 'Age Verify Settings', 'unity_section_text', 'unity_plugin' );

    add_settings_field( 'unity_setting_mode', 'Verify Mode', 'unity_setting_mode', 'unity_plugin', 'api_settings' );
    add_settings_field( 'unity_setting_category', 'Catgeories that require verification', 'unity_setting_category', 'unity_plugin', 'api_settings', ['class'=>'av_categories '.$class1] );
    add_settings_field( 'unity_setting_product', '', 'unity_setting_product', 'unity_plugin', 'api_settings', ['class'=>'av_products '.$class2] );
    
}
add_action( 'admin_init', 'unity_register_settings' );

function unity_section_text() {
   //echo '<p>Here you can set all the options for using the API</p>';
}

function get_all_product_cats() {
    $options = get_option( 'unity_plugin_options' );
    // since wordpress 4.5.0
$args = array(
    'taxonomy'   => "product_cat"
);
//return get_terms($args);
/**
 * Your args from the question
 * plus turning echo off.
 * Note the change to the name (adding "[]")
 */

$args = array(
    'class'       => 'av_select',
    'hide_empty'  => false,
    //'selected'    => 19,//$options['category'],
    'name'        => 'unity_plugin_options[category][]',
    'id'          => 'unity_setting_category',
    'orderby'     => 'NAME',
    'order'       => 'ASC',
    'show_option_none'   => __('None','wpestate'),
    'taxonomy'    => 'product_cat',
    'hierarchical'=> true,
    'echo'        => 0,
);

/** get the dropdown **/
$dropdown = wp_dropdown_categories( $args );

/** insert "multiple" using str_replace **/
$multi = str_replace( '<select', '<select multiple ', $dropdown );

foreach($options['category'] as $opt) {
    if($opt=='1-') {
        continue;
    }
    $multi = str_replace( 'value="'.$opt.'"', 'value="'.$opt.'" selected="selected"', $multi );
}
$multi = $multi.'<p>To select multiple categories use CTRL/CMD click.</p>';
/** output result **/
echo $multi;
}

function unity_setting_mode() {
    $options = get_option( 'unity_plugin_options' );
    $array = ['0'=>'All Customers', '1'=>'Specific Categories', '2'=>'Specific Products'];
    $selectOptions = '';
    foreach($array as $key => $value) {
        $selected = '';
        if($options['mode']==$key) {
            $selected = 'selected="selected"';
        }
        $selectOptions .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

    }
    echo '<select name="unity_plugin_options[mode]" class="av_select" id="unity_setting_mode">'.$selectOptions.'</select>';
    }

function unity_setting_category() {
    get_all_product_cats();
    }


function unity_setting_product() {
    echo 'You can specify that a customer requires age verification on an individual product bases.</p><p>You do this in the Wordpress admin by editing the product that requires verification and placing a tick in the "'.$GLOBALS['unityName'].' - Age Verify" section checkbox and clicking on Update.';
    }

function unity_plugin_options_validate( $input ) {
    if(is_array($input['category'])) {
        //$input = implode(',', $input['category']);
    }
    //$newinput['api_key'] = trim( $input['api_key'] );
    /*if ( ! preg_match( '/^[a-z0-9]{32}$/i', $newinput['api_key'] ) ) {
        $newinput['api_key'] = '';
    }*/

    return $input;
}



add_action( 'wp_ajax_my_action', 'my_action' );

function my_action() {
	global $wpdb; // this is how you get access to the database

	// $whatever = intval( $_POST['whatever'] );

	echo json_encode(createUser());

        //echo $whatever;

	wp_die(); // this is required to terminate immediately and return a proper response
}

function wporg_add_custom_box() {
    $screens = [ 'product', 'wporg_cpt' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wporg_box_id',                 // Unique ID
            $GLOBALS['unityName'],      // Box title
            'wporg_custom_box_html',  // Content callback, must be of type callable
            $screen                            // Post type
        );
    }
}
add_action( 'add_meta_boxes', 'wporg_add_custom_box' );

function wporg_custom_box_html( $post ) {
    $value = get_post_meta( $post->ID, '_ac_av_meta_key', true );
    //echo 'Value: '. $value;
    $checked = false;
    if($value=='on') {
        $checked = true;
    }
    //echo $options
    ?>
    
    <label for="ac_av">Age verify this product: </label>
    <input type="checkbox" name="ac_av" id="ac_av" value="on" <?php checked($checked, true); //echo $checked ?>/>
    <p style="color:#999;">Put a tick in this checkbox to age verify order for this product. This option only works when 'Verify Mode' is set to 'Specific Products' in the <?= $GLOBALS['unityName'] ?> configuration.</p>
    <?php
}

function wporg_save_postdata( $post_id ) {
    if ( array_key_exists( 'ac_av', $_POST ) ) {
        update_post_meta(
            $post_id,
            '_ac_av_meta_key',
            $_POST['ac_av']
        );
    } else {
        update_post_meta(
            $post_id,
            '_ac_av_meta_key',
            'off'
        );
    }
}
add_action( 'save_post', 'wporg_save_postdata' );


add_filter( 'manage_edit-shop_order_columns', 'unity_add_new_order_admin_list_column' );
 
function unity_add_new_order_admin_list_column( $columns ) {
    $columns['unity_age_verification'] = 'Age Verification Status';
    return $columns;
}
 
add_action( 'manage_shop_order_posts_custom_column', 'unity_add_new_order_admin_list_column_content' );
 
function unity_add_new_order_admin_list_column_content( $column ) {
   
    global $post;
 
    if ( 'unity_age_verification' === $column ) {
 
        $order = wc_get_order( $post->ID );
        $avStatus = get_post_meta( $post->ID, 'unity_age_verification');
        if($avStatus) {
            echo $avStatus[0];
        } else {
            echo '<div title="This order was placed before the Age Verification plugin was installed or when the plugin was disabled">ⓘ</div>';
        }
      
    }
}

add_action('publish_post', 'unity_add_custom_field_automatically');

function unity_add_custom_field_automatically($post_ID) {

    if(!wp_is_post_revision($post_ID)) {
        if ($post->post_type != 'shop_order'){

        }
        add_post_meta($post_ID, 'unity_age_verification', '',true);
    }
}

add_action( 'woocommerce_new_order', 'unity_set_status', 1, 1 );
function unity_set_status( $order_id ) {
    add_post_meta($order_id, 'unity_age_verification', 'Pending',true);
}




// display the extra data in the order admin panel
function unity_display_order_data_in_admin( $order ){  ?>
    <div class="form-field form-field-wide wc-customer-user">
        <h3><?php _e( 'Age Verification Status' ); ?></h3>
        <?php 
            echo '<p><strong>' . __( 'Status' ) . ':</strong> ' . get_post_meta( $order->id, 'unity_age_verification', true ) . '</p>';
            // echo '<p><strong>' . __( 'Another field' ) . ':</strong>' . get_post_meta( $order->id, '_another_field', true ) . '</p>'; 
            ?>
    </div>
<?php }
add_action( 'woocommerce_admin_order_data_after_order_details', 'unity_display_order_data_in_admin' );