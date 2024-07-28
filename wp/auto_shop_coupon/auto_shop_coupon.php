<?php
/**
 * @package auto_shop_coupon
 * @version 0.1
 */
/*
Plugin Name: auto shop coupon
Plugin URI: http://www.aston.sk/
Description: auto shop coupon after order
Version: 0.1
Author URI: http://www.aston.sk/
*/

/*
 create table vv_order (
   id integer not null,
   email varchar(255) not null,
   price decimal(10,2) not null,
   coupon varchar(255),
   created timestamp not null,
   PRIMARY KEY (id)
 );
 */

function total_orders_price($email){
    global $wpdb;
    $sum = $wpdb->get_var( $wpdb->prepare ( "SELECT SUM(price) FROM vv_order "
     ."WHERE coupon is null and created >= NOW() - INTERVAL 6 MONTH and email = %s", $email) );
    return $sum>0 ? $sum : 0;
}

function set_used($email, $coupon){
    global $wpdb;
    $wpdb->query( $wpdb->prepare ( "update vv_order set coupon=%s "
     ."where coupon is null and created >= NOW() - INTERVAL 6 MONTH and email = %s", $coupon, $email) );
}

function create_order($id, $email, $price){
    global $wpdb;
    $wpdb->query( $wpdb->prepare ( "insert into vv_order (id,email,price,coupon,created) "
      ."values (%d, %s, %f, null, now())", $id, $email, $price) );
}


function coupon_meta($title){
    $posts = get_posts(array(
        "post_type"=>"shop_coupon",
        "title"=> $title));
    if(count($posts)>0){
        $post = $posts[0];
        $coupon_meta = get_post_meta($post->ID);
        $coupon_meta['post'] = $post;
        return $coupon_meta;
    }
    return null;
}

function rand_code($length){
    $characters = '0123456789';
    $max = strlen($characters) -1;
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $max)];
    }
    return $randomString;
}

function copy_post_meta($post_id, $meta, $names){
    foreach($names as $name){
        if(array_key_exists($name, $meta)){
            $val = $meta[$name];
            if(is_array($val) && count($val)>0){
                $v = $val[0];
                if(str_starts_with($v, 'a:') && @unserialize($v)!== false) {
                    update_post_meta($post_id, $name, unserialize($v));
                } else {
                    update_post_meta($post_id, $name, $v);
                }
            }    
        }
    }
}

function create_coupon($order, $coupon_code, $base_coupon){
    $coupon = array(
        'post_title' => $coupon_code,
        'post_content' => '',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_excerpt' => $base_coupon['post']->post_excerpt,
        'post_type' => 'shop_coupon');

    $new_coupon_id = wp_insert_post($coupon);

    $next3M = (new DateTime())->add(new DateInterval('P3M'))->getTimestamp();
    update_post_meta($new_coupon_id, 'date_expires', $next3M);

    copy_post_meta($new_coupon_id, $base_coupon, 
        array('discount_type','coupon_amount', 'free_shipping',
        'minimum_amount', 'maximum_amount', 'individual_use', 'exclude_sale_items', 
        'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories',
        'usage_limit', 'limit_usage_to_x_items', 'usage_limit_per_user'));
    return $new_coupon_id;
}

add_action( 'woocommerce_email_before_order_table', 'custom_content_to_processing_customer_email', 10, 4 );
function custom_content_to_processing_customer_email( $order, $sent_to_admin, $plain_text, $email ) {

    if($sent_to_admin || $order->get_status()!='completed') return;
    create_order($order->get_id(), $order->get_billing_email(), $order->get_total());
    $total_prices = total_orders_price($order->get_billing_email());
    $base_coupon = null;
    if($total_prices>=200){
        $base_coupon = coupon_meta('auto_200');
    } 
    if( $base_coupon == null && $total_prices>=100){
        $base_coupon = coupon_meta('auto_100');
    }
    if($base_coupon){
        $coupon_code = 'vv-'.(rand_code(7));
        set_used($order->get_billing_email(), $coupon_code);
        create_coupon($order, $coupon_code, $base_coupon);
        echo join("", array(
            '<h1 style="text-align: center;padding: 15px;background: #ebebeb;color: #000;border-radius: 5px;font-family: inherit;">',
            'ZĽAVOVÝ KUPÓN:<br> ', $coupon_code,
            '</h1>',
            '<p>',
            $base_coupon['post']->post_excerpt,
            '</p>'
        ));
    }
}


// Simple, grouped and external products
add_filter('woocommerce_product_get_price', 'vv_custom_price', 99, 2 );
//add_filter('woocommerce_product_get_regular_price', 'vv_custom_price', 99, 2 );
add_filter('woocommerce_product_get_sale_price', 'vv_custom_price', 99, 2 );
function vv_custom_price( $price, $product ) {
  if( has_term( 67, 'product_cat', $product->get_id() ) ) {
   $ip = geoip_detect2_get_client_ip();
   $record = geoip_detect2_get_info_from_current_ip($ip);
   $codes = [ 'SK', 'CZ' ];
   if( isset($record) && in_array( $record->country->isoCode, $codes ) ) {
     return $product->get_regular_price()-5;
   }
  }
  return $price;
}