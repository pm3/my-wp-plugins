<?php
/**
 * @package REST all urls
 * @version 0.1
 */
/*
Plugin Name: REST all urls
Plugin URI: http://www.aston.sk/
Description: /wp-json/mynamespace/v1/latest-posts
Version: 0.1
Author URI: http://www.aston.sk/
*/

/**
 *
 * Get The latest post from a category !
 * @param array $params Options for the function.
   * @return string|null Post title for the latest,? * or null if none
 *
 */


 function get_latest_urls ( $params ){
	
	$arr_pages = array();
	$arr_posts = array();
	$arr_attachments = array();
	
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields'=>'ID') );
	foreach ( $pages as $page ) {
		array_push( $arr_pages, wp_make_link_relative(get_permalink($page->ID)) );
	}

	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields'=>'ID') );
	foreach ( $posts as $post ) {
		array_push( $arr_posts, wp_make_link_relative(get_permalink($post->ID)) );
	}
	
	$images = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'fields'=>'ID', 'post_parent' => null ) );
    	foreach ( $images as $img ) {
            array_push( $arr_attachments, wp_make_link_relative(wp_get_attachment_url($img->ID)) );
    	}

	return array( 'pages' => $arr_pages, 'posts' => $arr_posts, 'attachments' => $arr_attachments );
 }

 function get_latest_menus ( $params ){
	
	$arr_menus = array();

	$menus = wp_get_nav_menus();
    	foreach ( $menus as $wp_menu ) {
		$items = wp_get_nav_menu_items($wp_menu->name);
		$items = array_map('my_filter_menu_items', $items);
		array_push($arr_menus, array( 'id' => $wp_menu->term_id, 'name' => $wp_menu->name, 'items' => $items) );
    	}

	return $arr_menus;
 }

function my_filter_menu_items( $o ){
  return array(
   'id' => $o->ID, 
   'parent' => (int)$o->menu_item_parent, 
   'order' => $o->menu_order, 
   'title' => $o->title, 
   'url' => $o->url, 
   'classes' => implode( ' ', $o->classes ) );
}

 // Register the rest route here.

 add_action( 'rest_api_init', function () {
		
		register_rest_route( 'wp/v2', 'urls',array(

			'methods'  => 'GET',
			'callback' => 'get_latest_urls'

		) );

 } );

 add_action( 'rest_api_init', function () {
		
		register_rest_route( 'wp/v2', 'menus',array(

			'methods'  => 'GET',
			'callback' => 'get_latest_menus'

		) );

 } );
 