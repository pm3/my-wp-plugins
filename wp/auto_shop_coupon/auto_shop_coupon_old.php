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

function total_orders_price($email){
    $sum = 0;

    $beforeHalfYear = (new DateTime())->sub(new DateInterval('P6M'))->format('Y-m-d');
    $orders = wc_get_orders(array(
        'customer' => $email,
        'status' => array('completed'),
        'date_created' => '>='.$beforeHalfYear,
        'orderby' => 'id',
        'order' => 'DESC',
    ));
    foreach ($orders as $order) {
		//echo join(" ", array('<p>', 'sum',$order->get_id(), '-', $order->get_total(), '-', $order->meta_exists('generated_coupon'), '</p>' ));
		if($order->meta_exists('generated_coupon')) break;
        $sum = $sum + $order->get_total();        
        $codes = $order->get_coupon_codes();
        $auto_codes = array_filter($codes, function($c){ return str_starts_with($c, "vv-"); });            
        if(count($auto_codes)>0) break; 
    }

    return $sum;
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
		
	$order->add_meta_data('generated_coupon', $new_coupon_id);
	$order->save_meta_data();

    return $new_coupon_id;
}

add_action( 'woocommerce_email_before_order_table', 'custom_content_to_processing_customer_email', 10, 4 );
function custom_content_to_processing_customer_email( $order, $sent_to_admin, $plain_text, $email ) {

    if($sent_to_admin || $order->get_status()!='completed') return;
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
