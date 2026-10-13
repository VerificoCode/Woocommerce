<?php
/*
* Plugin Name: AgeChecked Plus+ v2
* Plugin URI: https://www.agechecked.com/
* Description: Adds age verification to your website.
* Version: 1.4.4
* Author: AgeChecked Plus+ Verifico
* Author URI: https://www.verifico.io
*/

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

function unity_is_woocommerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    )
    || ( is_multisite() && array_key_exists(
        'woocommerce/woocommerce.php',
        get_site_option('active_sitewide_plugins', [])
    ));
}

if ( ! unity_is_woocommerce_active() ) {
    return;
}

function unity_render_customer_age_verification_status( $user ) {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }

    $is_verified = 'true' === get_user_meta( $user->ID, 'merchant_age_verified', true );
    ?>
<h2><?php esc_html_e( 'Age Verification Status', 'agechecked-plus' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><label for="merchant_age_verified"><?php esc_html_e( 'Merchant Age Verified', 'agechecked-plus' ); ?></label></th>
        <td>
            <?php wp_nonce_field( 'unity_save_customer_age_verification_status', 'unity_age_verification_nonce' ); ?>
            <label for="merchant_age_verified">
                <input type="checkbox" name="merchant_age_verified" id="merchant_age_verified" value="true"
                    <?php checked( $is_verified ); ?> />
                <?php esc_html_e( 'This customer has passed age verification.', 'agechecked-plus' ); ?>
            </label>
        </td>
    </tr>
</table>
<?php
}
add_action( 'show_user_profile', 'unity_render_customer_age_verification_status' );
add_action( 'edit_user_profile', 'unity_render_customer_age_verification_status' );

function unity_save_customer_age_verification_status( $user_id ) {
    if (
        ! current_user_can( 'edit_user', $user_id ) ||
        ! isset( $_POST['unity_age_verification_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['unity_age_verification_nonce'] ) ), 'unity_save_customer_age_verification_status' )
    ) {
        return;
    }

    if ( isset( $_POST['merchant_age_verified'] ) ) {
        update_user_meta( $user_id, 'merchant_age_verified', 'true' );
        return;
    }

    delete_user_meta( $user_id, 'merchant_age_verified' );
}
add_action( 'personal_options_update', 'unity_save_customer_age_verification_status' );
add_action( 'edit_user_profile_update', 'unity_save_customer_age_verification_status' );

// -----------------------------------------------------------------------
// Age Verification Status column on the Users list (and WooCommerce
// Customers, which uses the same WP_Users_List_Table under users.php).
// Filterable via a dropdown in the list table header.
// -----------------------------------------------------------------------

function unity_add_user_age_verification_column( $columns ) {
    $columns['merchant_age_verified'] = __( 'Age Verification Status', 'agechecked-plus' );
    return $columns;
}
add_filter( 'manage_users_columns', 'unity_add_user_age_verification_column' );

function unity_render_user_age_verification_column( $value, $column_name, $user_id ) {
    if ( 'merchant_age_verified' !== $column_name ) {
        return $value;
    }

    $is_verified = 'true' === get_user_meta( $user_id, 'merchant_age_verified', true );

    return $is_verified
        ? esc_html__( 'Verified', 'agechecked-plus' )
        : esc_html__( 'Not verified', 'agechecked-plus' );
}
add_filter( 'manage_users_custom_column', 'unity_render_user_age_verification_column', 10, 3 );

function unity_render_user_age_verification_filter( $which = '' ) {
    $screen = get_current_screen();
    if ( ! $screen || 'users' !== $screen->id ) {
        return;
    }

    $selected = isset( $_GET['merchant_age_verified'] ) ? sanitize_text_field( wp_unslash( $_GET['merchant_age_verified'] ) ) : '';
    $field_id = 'merchant_age_verified_filter' . ( 'top' === $which ? '' : '_' . $which );
    ?>
    <label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Filter by age verification status', 'agechecked-plus' ); ?></label>
    <select name="merchant_age_verified" id="<?php echo esc_attr( $field_id ); ?>">
        <option value=""><?php esc_html_e( 'All age verification statuses', 'agechecked-plus' ); ?></option>
        <option value="verified" <?php selected( $selected, 'verified' ); ?>><?php esc_html_e( 'Verified', 'agechecked-plus' ); ?></option>
        <option value="not_verified" <?php selected( $selected, 'not_verified' ); ?>><?php esc_html_e( 'Not verified', 'agechecked-plus' ); ?></option>
    </select>
    <?php
    submit_button( __( 'Filter', 'agechecked-plus' ), '', 'filter_action', false );
}
add_action( 'restrict_manage_users', 'unity_render_user_age_verification_filter' );

function unity_filter_users_by_age_verification( $query ) {
    if ( ! is_admin() || ! ( $query instanceof WP_User_Query ) ) {
        return;
    }

    if ( empty( $_GET['merchant_age_verified'] ) ) {
        return;
    }

    $filter = sanitize_text_field( wp_unslash( $_GET['merchant_age_verified'] ) );

    if ( 'verified' === $filter ) {
        $meta_query = array(
            array(
                'key'   => 'merchant_age_verified',
                'value' => 'true',
            ),
        );
    } elseif ( 'not_verified' === $filter ) {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => 'merchant_age_verified',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'merchant_age_verified',
                'value'   => 'true',
                'compare' => '!=',
            ),
        );
    } else {
        return;
    }

    $existing   = $query->get( 'meta_query' );
    $meta_query = is_array( $existing ) && $existing ? array_merge( array( $existing ), array( $meta_query ) ) : $meta_query;

    $query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_users', 'unity_filter_users_by_age_verification' );

$GLOBALS['unityName'] = "AgeChecked Plus+";
$GLOBALS['unityVersion'] = "2_0_0";
// To show register link
$GLOBALS['unityRegsiter'] = 0;
$GLOBALS['unityUrl'] = "https://agechecked.verifico.io";
// $GLOBALS['unityUrl'] = "https://unity.sites.test";
// $GLOBALS['unityUrl'] = "https://unity.agechecked.com";
// $GLOBALS['unityUrl'] = "http://localhost:8000";

add_action( 'wp_footer', 'unity_ageverify' );

function unity_ageverify(){
    //On every page - a customer can verify on any page on the website
    echo "<script>
    var script = document.createElement('script');
    script.src = \"".$GLOBALS['unityUrl']."/tr/?domain=\"+window.location.hostname+\"&v=".$GLOBALS['unityVersion']."\";
    // script.src = \"".$GLOBALS['unityUrl']."/tr/?lang=cs-CZ&domain=\"+window.location.hostname+\"&v=".$GLOBALS['unityVersion']."\";
    script.async = true;
    document.getElementsByTagName('head')[0].appendChild(script);
    </script>";

}


function unity_add_query_vars_filter( $vars ){
    $vars[] = "ac_api_user";
    return $vars;
}
add_filter( 'query_vars', 'unity_add_query_vars_filter' );

function requiresAV($order_id) {
    $order = wc_get_order($order_id);
    $items = $order->get_items();
    $requires_av = 0;
    $options = get_option( 'unity_plugin_options' );

    foreach ($items as $item_id => $item_data)
    {   
        $requires_av = 0;
        //getting product object
        $_product = wc_get_product($item_data->get_product_id());
        //getting all the product category
        $pro_cat_array = wp_get_post_terms($item_data->get_product_id(), 'product_cat', array( 'fields' => 'ids' ));
        
        if(is_array($options)) {
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
        }
        
        if(is_array($options)) {
            if(array_key_exists('mode', $options)) {
                if(count(get_post_meta( $item_data->get_product_id(), '_ac_av_meta_key' ))) {
                    if(get_post_meta( $item_data->get_product_id(), '_ac_av_meta_key' )[0]=='on'&&$options['mode']==2) {
                        $requires_av = 1;
                    }
                }
            }
        }

        if(is_array($options)) {
            if(array_key_exists('mode', $options)) {
                if($options['mode']==0) {
                    $requires_av = 1;
                }
            }
        }
        
    }

    return $requires_av;

}

function unity_orderData($order_id)
{
    
    //getting order object
    $order = wc_get_order($order_id);

    // if customer has meta data attribute of merchant_age_verified = true, we can skip age verification
    $customer_id = $order->get_customer_id();
    if ( $customer_id ) {
        $merchant_age_verified = get_user_meta( $customer_id, 'merchant_age_verified', true );
        if ( $merchant_age_verified === 'true' ) {
            return [];
        }
    }

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

    // Get DOB
    $dob = $order->get_meta( 'date_of_birth' );

    if ( ! $dob ) {
        $possible_keys = [
            '_date_of_birth',
            'customer_date_of_birth',
            '_customer_date_of_birth',
            'kustom_date_of_birth',
            'billing-date_of_birth',
        ];

        foreach ( $possible_keys as $key ) {
            $value = $order->get_meta( $key );
            if ( ! empty( $value ) ) {
                $dob = $value;
                break;
            }
        }
    }

    $billing_dob = $dob ? date( 'Y-m-d', strtotime( $dob ) ) : '';

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
        // Fix for quotes in product name
        $name = $sku = str_replace('"','&quot;',$_product->get_name());
        $qty = $item_data->get_quantity();

        if(is_array($options)) {
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
        }
        
        if(is_array($options)) {
            if(array_key_exists('mode', $options)) {
                if(count(get_post_meta( $item_data->get_product_id(), '_ac_av_meta_key' ))) {
                    if(get_post_meta( $item_data->get_product_id(), '_ac_av_meta_key' )[0]=='on'&&$options['mode']==2) {
                        $requires_av = 1;
                    }
                }
            }
        }

        if(is_array($options)) {
            if(array_key_exists('mode', $options)) {
                if($options['mode']==0) {
                    $requires_av = 1;
                }
            }
        }
        

        //storing all the line item as a string form
        $product_js .= '{"id": "' . $sku . '","name":"' . $name . '","price":"' . $row_price . '","quantity":"' . $qty . '","requires_av":"' . $requires_av . '"},';
        
    }

    echo '<script type="text/javascript">
    var acTransaction = {
    "user":[{
    "email":"'.$billing_email.'",
    "firstname":"'.$billing_first_name.'",
    "lastname":"'.$billing_last_name.'",
    "phone":"'.$billing_phone.'",
    "order_id":"'.$order_id.'",
    "dob":"'.$billing_dob.'",
    "address":{
    "address1":"'.$billing_address_1.'",
    "address2":"'.$billing_address_2.'",
    "address3":"",
    "city":"'.$billing_city.'",
    "postcode":"'.$billing_postcode.'",
    "country":"'.$billing_country.'"},
    "site_transaction_ref": "'.$wc_order_id.'",
    },
    {
        "email":"'.$billing_email.'",
    "firstname":"'.$billing_first_name.'",
    "lastname":"'.$billing_last_name.'",
    "phone":"'.$billing_phone.'",
    "order_id":"'.$order_id.'",
    "dob":"'.$billing_dob.'",
    "address":{
    "address1":"'.$billing_address_1.'",
    "address2":"'.$billing_address_2.'",
    "address3":"",
    "city":"'.$billing_city.'",
    "postcode":"'.$billing_postcode.'",
    "country":"'.$billing_country.'"},
    "site_transaction_ref": "'.$wc_order_id.'",
        }
    ],
    "cart": [{"products":[
        '.$product_js.'
    ]}]
    };
    </script>';

    /*
    For potential fraud scoring
    "metaData":{
        "lifetimeOrderCount":"'.$orderCount.'",
        "lastOrderDate":"'.$lastOrderDate.'",
        "firstOrderDate":"'.$firstOrderDate.'"
    }
    */

}

// add_action('woocommerce_thankyou', 'unity_orderData');

add_action( 'template_redirect', function () {

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if ( preg_match( '#/checkout/order-received/(\d+)/#', $request_uri, $matches ) ) {
        $order_id = (int) $matches[1];

        unity_orderData( $order_id );
    }
});

function unity_register_av_states_order_status() {
    register_post_status( 'wc-av-high-risk', array(
        'label'                     => 'Age Verified High Risk',
        'public'                    => false,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Age Verified High Risk <span class="count">(%s)</span>', 'Age Verified High Risk <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'wc-av-low-risk', array(
        'label'                     => 'Age Verified Low Risk',
        'public'                    => false,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Age Verified Low Risk <span class="count">(%s)</span>', 'Age Verified Low Risk <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'wc-av-pending', array(
        'label'                     => 'Pending Age Verification',
        'public'                    => false,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Pending Age Verification <span class="count">(%s)</span>', 'Pending Age Verification <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'unity_register_av_states_order_status' );


function unity_add_av_states_to_order_statuses( $order_statuses ) {
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
add_filter( 'wc_order_statuses', 'unity_add_av_states_to_order_statuses' );


function unity_add_settings_page() {
    add_options_page( $GLOBALS['unityName'].' Settings', $GLOBALS['unityName'].' Settings', 'manage_options', 'unity-plugin', 'unity_render_plugin_settings_page' );   
}
add_action( 'admin_menu', 'unity_add_settings_page' );

add_action('admin_menu', function() {
    
    add_options_page('Test Page', 'Test Page', 'manage_options', 'test-page', function() {
        echo '<h1>It works!</h1>';
    });
});


function unity_render_plugin_settings_page() {
    $options = get_option( 'unity_plugin_options' );
    $class = '';
    if(is_array($options)) {
        if(array_key_exists('mode', $options)) {
            if($options['mode']=='1') {
                $class = 'av_hide';
            }
        }
    }
    
    
    $image_url = '';
    $logo = get_theme_mod( 'custom_logo' );
    $image = wp_get_attachment_image_src( $logo , 'full' );
    if($image) {
        $image_url = $image[0];
    }
    
    ?>
<h1 class="av_h1"><?= $GLOBALS['unityName'] ?> Settings</h1>
<?php if($GLOBALS['unityRegsiter']) { ?>
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

<?php if(!unity_api_user_exists($GLOBALS['unityName'])) { ?>
<!--hr /-->
<h2>Start using <?= $GLOBALS['unityName'] ?></h2>
<p>In order to start age verifying your customers you will need to create a Woocommerce API user and register with
    <?= $GLOBALS['unityName'] ?>. You can do so by clicking on the button below:</p>

<!--a href="<?php echo get_admin_url().'admin.php'.'?page=wc-settings&tab=advanced&section=keys' ?>" target="_blank">Rest API</a-->



<div> <a class="av_button ac-api-user" href="#" target="_blank">Click here to register</a><a
        style="margin-left:20px;color:#206eba;" class=" ac-api-user" target="_blank" href="#">Try me first</a></div>


<?php } else { ?>
<h2>Looks like you have already registered?</h2>
<p>If you have already registered to use <?= $GLOBALS['unityName'] ?> then complete the settings section below with your
    required configuration and you can start verifying now. I'm having problems - <a class=" ac-api-user"
        href="#">register again</a>.</p>
<?php }; ?>


<script>
jQuery(document).ready(function($) {

    // jquery extend function
    $.extend({
        redirectPost: function(location, args) {
            var form = '';
            $.each(args, function(key, value) {
                value = value.split('"').join('\"')
                form += '<input type="hidden" name="' + key + '" value="' + value + '">';
            });
            $('<form action="' + location + '" method="POST">' + form + '</form>').appendTo($(
                document.body)).submit();
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
            if (response.user_id) {
                var redirect = '<?= $GLOBALS['unityUrl'] ?>/setup/auto-register/';
                $.redirectPost(redirect, {
                    'name': "<?= get_bloginfo( 'name' ) ?>",
                    'url': "<?= get_bloginfo( 'url' ) ?>",
                    'email': "<?= get_bloginfo( 'admin_email' ) ?>",
                    'logo': "<?= $image_url ?>",
                    'email': "<?= get_bloginfo('admin_email') ?>",
                    'platform': 'woocommerce',
                    'basic_auth': response.consumer_key + ':' + response
                        .consumer_secret,
                });
            }
        });
    });
})
</script>
<hr style="margin-top:30px;" />
<?php }; ?>
<?php  ?>
<form action="options.php" method="post">
    <?php 
        settings_fields( 'unity_plugin_options' );
        do_settings_sections( 'unity_plugin' ); ?>

    <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
</form>
<!--p style="margin-top:20px;color:#999;font-style:italic;">This integration is provided in partnership with Verifico</p-->

<style>
/*.av_categories {display:none;}*/
.av_h1 {
    margin-bottom: 40px;
}

.av_button {
    text-decoration: none;
    font-size: 18px;
    background: #206eba;
    cursor: pointer;
    color: #fff;
    padding: 15px 20px;
    display: inline-block;
}

.av_button:hover {
    color: #fff;
}

.av_select {
    min-width: 160px;
}

.av_select option {
    padding: 5px;
}

.av_hide {
    display: none;
}
</style>
<script>
jQuery(document).ready(function($) {
    if ($('#unity_setting_mode').value != 1) {
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

});
</script>
<?php
}

function unity_api_user_exists($user){

    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_api_keys WHERE description like %s", $user.'%'));

    if($count > 0){ return true; }else{ return false; }

}


function unity_create_user() {
    
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
    $class2 = '';
    if(is_array($options)) {
        if(array_key_exists('mode', $options)) {
            if($options['mode']!='1') {
                $class1 = 'av_hide';
            }
            if($options['mode']!='2') {
                $class2 = 'av_hide';
            }
        }
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

function unity_get_all_product_cats() {
    $options = get_option( 'unity_plugin_options' );
    // since wordpress 4.5.0
    $args = array(
        'taxonomy'   => "product_cat"
    );


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

    if(is_array($options)) {
        if(array_key_exists('category', $options)) {
            foreach($options['category'] as $opt) {
                if($opt=='1-') {
                    continue;
                }
                $multi = str_replace( 'value="'.$opt.'"', 'value="'.$opt.'" selected="selected"', $multi );
            }
        }
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
        if($options) {
            if(array_key_exists('mode', $options)) {
                if($options['mode']==$key) {
                    $selected = 'selected="selected"';
                }
            }
        }
        
        $selectOptions .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';

    }
    echo '<select name="unity_plugin_options[mode]" class="av_select" id="unity_setting_mode">'.$selectOptions.'</select>';
    }

function unity_setting_category() {
    unity_get_all_product_cats();
    }


function unity_setting_product() {
    echo 'You can specify that a customer requires age verification on an individual product bases.</p><p>You do this in the Wordpress admin by editing the product that requires verification and placing a tick in the "'.$GLOBALS['unityName'].' - Age Verify" section checkbox and clicking on Update.';
    }



function unity_user() {
	global $wpdb; 

	echo json_encode(unity_create_user());

	wp_die(); 
}
add_action( 'wp_ajax_my_action', 'unity_user' );

function unity_add_custom_box() {
    $screens = [ 'product', 'unity_product_av_section' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'unity_product_av_section',                 // Unique ID
            $GLOBALS['unityName'],      // Box title
            'unity_custom_box_html',  // Content callback, must be of type callable
            $screen                            // Post type
        );
    }
}
add_action( 'add_meta_boxes', 'unity_add_custom_box' );

function unity_custom_box_html( $post ) {
    $value = get_post_meta( $post->ID, '_ac_av_meta_key', true );
    $checked = false;
    if($value=='on') {
        $checked = true;
    }
    wp_nonce_field( 'unity_save_product_av_meta', 'unity_product_av_meta_nonce' );
    ?>

<label for="_ac_av_meta_key">Age verify this product: </label>
<input type="checkbox" name="_ac_av_meta_key" id="ac_av" value="on" <?php checked($checked, true); //echo $checked ?> />
<p style="color:#999;">Put a tick in this checkbox to age verify order for this product. This option only works when
    'Verify Mode' is set to 'Specific Products' in the <?= $GLOBALS['unityName'] ?> configuration.</p>
<?php
}

function unity_save_postdata($post_id) {
    if ( ! isset( $_POST['unity_product_av_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['unity_product_av_meta_nonce'] ) ), 'unity_save_product_av_meta' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['_ac_av_meta_key'] ) ) {
        update_post_meta( $post_id, '_ac_av_meta_key', 'on' );
    } else {
        delete_post_meta( $post_id, '_ac_av_meta_key' );
    }

}
add_action( 'save_post_product', 'unity_save_postdata' );



function unity_woocommerce_shop_order_list_table_columns ( $columns ) {
	$columns['unity_age_verification'] = 'Age Verification Status';
	return $columns;
};
add_filter( 'woocommerce_shop_order_list_table_columns', 'unity_woocommerce_shop_order_list_table_columns');



function unity_woocommerce_shop_order_list_table_custom_column ( $column, $order ) {
	if ( 'unity_age_verification' !== $column ) {
		return;
	}
    if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $avStatus = "";
        
        $avStatus = [$order->get_meta('unity_age_verification')];
                
        if(!empty($avStatus[0])) {
            echo $avStatus[0];
        } else {
            echo '<div title="This order was placed before the Age Verification plugin was installed or when the plugin was disabled">ⓘ</div>';
        }
    } 
};
add_action( 'woocommerce_shop_order_list_table_custom_column', 'unity_woocommerce_shop_order_list_table_custom_column', 10, 2 );


function unity_add_new_order_admin_list_column( $columns ) {
    $columns['unity_age_verification'] = 'Age Verification Status';
    return $columns;
}
add_filter( 'manage_edit-shop_order_columns', 'unity_add_new_order_admin_list_column' );
 
 
function unity_add_new_order_admin_list_column_content( $column ) {
    
    if ( !OrderUtil::custom_orders_table_usage_is_enabled() ) {

        if ( 'unity_age_verification' === $column ) {

            global $post;
 
            $order = wc_get_order( $post->ID );
    
            $avStatus = get_post_meta( $post->ID, 'unity_age_verification');
            if($avStatus) {
                echo $avStatus[0];
            } else {
                echo '<div title="This order was placed before the Age Verification plugin was installed or when the plugin was disabled">ⓘ</div>';
            }
        }
    }  
}
add_action( 'manage_shop_order_posts_custom_column', 'unity_add_new_order_admin_list_column_content' );


function unity_set_status( $order_id ) {
    $order = wc_get_order($order_id);
    $reqAv = requiresAV($order_id);
    
    if ( $reqAv === 0) {
        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
        
            $order->add_meta_data('unity_age_verification', 'Not Required');
            $order->save();
        } else {
            add_post_meta($order_id, 'unity_age_verification', 'Not Required',true);  
        }
    } else {
        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
        
            $order->add_meta_data('unity_age_verification', 'Pending');
            $order->save();
        } else {
            add_post_meta($order_id, 'unity_age_verification', 'Pending',true);  
        }
    } 
    
}
add_action( 'woocommerce_checkout_update_order_meta', 'unity_set_status', 1, 1 );


// -----------------------------------------------------------------------
// Age Verification Status as its own meta box on the order edit screen,
// instead of being embedded inside the main "Order data" panel.
//
// Registered via the standard add_meta_box() API so it renders as a
// genuinely separate section/tab:
//  - Default WooCommerce (legacy post-based orders): its own box in the
//    order edit screen sidebar, next to (not inside) Order data.
//  - Default WooCommerce (HPOS / Orders table): same, on the HPOS order
//    screen id.
//  - BrikPanel's reskinned order admin: BrikPanel reads the same
//    WordPress meta box registry and automatically places any box it
//    doesn't recognise under its "More" tab as its own section, so no
//    BrikPanel-specific code is needed here.
// -----------------------------------------------------------------------

function unity_add_order_age_verification_meta_box() {
    $screen_id = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

    add_meta_box(
        'unity_age_verification_status',
        __( 'Age Verification Status', 'agechecked-plus' ),
        'unity_render_order_age_verification_meta_box',
        $screen_id,
        'side',
        'default'
    );

    // Belt-and-braces: also register on the legacy post type screen id in
    // case wc_get_page_screen_id() ever disagrees with it (e.g. during the
    // HPOS/legacy sync window), so the box never silently disappears.
    if ( 'shop_order' !== $screen_id ) {
        add_meta_box(
            'unity_age_verification_status',
            __( 'Age Verification Status', 'agechecked-plus' ),
            'unity_render_order_age_verification_meta_box',
            'shop_order',
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'unity_add_order_age_verification_meta_box' );

function unity_render_order_age_verification_meta_box( $post_or_order ) {
    $order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );

    if ( ! $order ) {
        return;
    }

    $status = $order->get_meta( 'unity_age_verification' );
    ?>
<p>
    <strong><?php esc_html_e( 'Status', 'agechecked-plus' ); ?>:</strong>
    <?php
    if ( $status ) {
        echo esc_html( $status );
    } else {
        echo '<span title="' . esc_attr__( 'This order was placed before the Age Verification plugin was installed or when the plugin was disabled', 'agechecked-plus' ) . '">' . esc_html__( 'Unknown', 'agechecked-plus' ) . '</span>';
    }
    ?>
</p>
<?php
}