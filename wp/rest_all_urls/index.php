<?php
/*
Plugin Name: ACF Export pre Hydrotour
Plugin URI: http://www.aston.sk
Description: Export dat pre mixer
Version: 1.0
Author: Aston
Author URI: http://www.aston.sk
License: A "Slug" license name e.g. GPL2
*/

add_action( 'rest_api_init', function () {

    register_rest_route( 'aston/api', '/animatori/', array(
        'methods' => 'GET',
        'callback' => 'aston_get_animatori',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'aston/api', '/settings/', array(
        'methods' => 'GET',
        'callback' => 'aston_get_settings',
        'permission_callback' => '__return_true',
    ) );

} );

function aston_get_settings() {

    $settings = new WP_Query(array(
        'post_type'      => 'settings',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ));
    $settings->get_posts();

    $result = array();

    foreach( $settings->posts as $setting ) {

        $fields = get_field_objects( $setting->ID );
        $values = get_fields ($setting->ID );

        $result[] = array(
            'setting' => $setting->post_name,
            'values' => normalize( $fields, $values ),
        );

    }

    return $result;

}

function aston_get_animatori()   {

    $posts = new WP_Query(array(
        'post_type'      => 'animatori',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ));
    $posts->get_posts();

    $result = array();

    foreach( $posts->posts as $post )   {

        $photo = get_field( 'photo', $post->ID );

        $result[] = array(
            'id'              => $post->ID,
            'name'            => $post->post_title,
            'order'           => get_field( 'order', $post->ID ),
            'description'     => get_field( 'description', $post->ID ),
            'hotelId'         => get_field( 'hotel_id', $post->ID ),
            'photo'           => $photo && $photo['url'] ? wp_make_link_relative($photo['url']) : null,
            'photoMedium'     => $photo && photo['sizes'] && $photo['sizes']['medium_large'] ? wp_make_link_relative($photo['sizes']['medium_large']) : null,
            'groupPhoto'      => get_field( 'is_group_photo', $post->ID ),
        );

    }

    return $result;

}

function normalize( $fields, $values )  {

    $groups = array();

    $result = array();

    foreach( $values as $key => $value )    {


        if( is_group( $key, $fields ) ) {

            $groups[] = $value;

        } else  {
            $groups[] = array(key=>$key, value=>$value);
//            $result[ $key ] = $value;

        }

    }


    if( count( $result ) )  {

//        $result[ 'groups' ] = $groups;
        return $result;

    }

    return $groups;

}

function is_group( $key, $fields )  {

    foreach( $fields as $field )    {

        if( $field[ 'name' ] == $key && $field[ 'type' ] == 'group' )    {

            return true;

        }

    }

    return false;

}