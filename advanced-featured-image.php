<?php

/**
 * Plugin Name: Advanced Featured Image
 * Plugin URI: http://code.tutsplus.com/tutorials/advanced-featured-image-in-wordpress--cms-25182
 * Description: Enable the regular media uploader box and select images from URL or from other sites if in Multisite.
 * Version: 1.0.0
 * Author: Igor Benić
 * Author URI: http://www.twitter.com/igorbenic
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: tutsplus
 */

if ( ! defined( 'WPINC' ) ) {

    die;

}

/**
 * Markup for the advanced featured image metabox.
 *
 * @param string $content Content in the featured image metabox.
 * @param number $postID Id of the post we are on.
 *
 * @return string Content for the featured image.
 */
function afi_metabox( $content, $postID ) {
    
    $imageURL = get_post_meta( $postID, '_afi_img_src', true );

    $content = '<div id="custom_image_container">';

        if ( $imageURL ) {

            $content .= '<img src="' . $imageURL . '" style="max-width:100%;" />';

        }

    $content .= '</div>' ;

    // Add & remove image links.
    $content .= '<p class="hide-if-no-js">';

        $content .= '<a class="upload-custom-img ' . ( ( $imageURL  ) ? 'hidden' : '' ) . '" href="#">';

            $content .= __( 'Set custom image', 'tutsplus' );

        $content .= '</a>';

        $content .= '<a class="delete-custom-img ' . ( ( ! $imageURL  ) ? 'hidden' : ''  ) . '" href="#">';

            $content .= __( 'Remove this image', 'tutsplus' );

        $content .= '</a>';

    $content .= '</p>';

    // Hidden input to set the chosen image url on post.
    $content .= '<input class="afi-img-id" name="afi-img-src" type="hidden" value="' . esc_url( $imageURL ) . '" />';

    return $content;
        
}

add_filter( 'admin_post_thumbnail_html', 'afi_metabox', 1, 2 );

/**
 * JavaScript for the advanced featured image metabox.
 */
function afi_scripts() {

    wp_enqueue_script( 'afi_js', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), true );

}

add_action( 'admin_enqueue_scripts', 'afi_scripts' );

/**
 * Saving the thumbnail when used on other multisites.
 *
 * @param number $post_id Id of the post.
 */
function afi_save_thumbnail( $post_id ) {
       
    $imageURL = $_POST['afi-img-src'];

    $imageID = afi_get_attachment_id_from_url( $imageURL );

    // Current site id.
    $currentBlogID = get_current_blog_id();

    // Flag to track if we have switched to another site.
    $switchedBlog = false;

    // If the image was not found, we need to look on other blog sites.
    if ( ! $imageID ) {

         global $wpdb;

         // Get the ids of all installed sites.
         $sites =  $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid ), ARRAY_A );
      
         foreach ( $sites as $site ) {

             // Skip the site if it is current site we already checked above.
             if ( $site['blog_id'] == $currentBlogID ) {

                 continue;
             }

             // Switch to the new site.
             switch_to_blog( $site['blog_id'] );

             // Track that we have switched sites.
             $switchedBlog = true;

             // Get the image id on this latest site.
             $imageID = afi_get_attachment_id_from_url( $imageURL );

             // Break from the loop if the image was found, otherwise continue searching.
             if ( false !== $imageID ) {

                 break;
             }
             
         }
    }

    $images = array();
     
    // Make sure we found an image.
    if ( false !== $imageID ) {

        // Get meta data for that attachment id.
        $imageMetaData = wp_get_attachment_metadata( $imageID );

        // Get the original, uploaded image.
        $originalImage = wp_get_attachment_image_src( $imageID, 'full' );
         
        // Add original image to array of sizes.
        $images['full'] = array(
            'url'    => $originalImage[0],
            'width'  => $imageMetaData['width'],
            'height' => $imageMetaData['height']
        );

        // Add each generated size of the original image to the array of sizes.
        foreach ( $imageMetaData['sizes'] as $size => $sizeInfo ) {

        $image = wp_get_attachment_image_src( $imageID, $size );

        $images[ $size ] = array(
            'url'    => $image[0],
            'width'  => $sizeInfo['width'],
            'height' => $sizeInfo['height']
        );

        }

    }
     
    // Return to the current site, if we switched during checking for images.
    if ( $switchedBlog ) {

        restore_current_blog();

    }
    
    // Save images to post meta data.
    update_post_meta( $post_id, '_afi_image', $images );
    update_post_meta( $post_id, '_afi_img_src', $imageURL );

    // Fake the `thumbnail_id` so the `has_post_thumbnail` works as intended on other sites.
    update_post_meta( $post_id, '_thumbnail_id', '1' );

}

add_action( 'save_post', 'afi_save_thumbnail' );

/**
 * Gets the attachment id of the image, if it exists.
 *
 * @param string $attachment_url The url of the attachment image.
 *
 * @return number Attachemnt id of image.
 */
function afi_get_attachment_id_from_url( $attachment_url = '' ) {
 
    global $wpdb;

    $attachment_id = false;
 
    // Exit if there is no url.
    if ( '' == $attachment_url ) {

        return;

    }
 
    // Get the upload directory paths.
    $upload_dir_paths = wp_upload_dir();
 
    // Make sure the upload path base directory exists in the attachment url, to verify that we're working with a media library image.
    if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {
 
        // If this is the url of an auto-generated thumbnail, get the url of the original image.
        $attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );
 
        // Remove the upload path base directory from the attachment url.
        $attachment_url = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
 
        // Run a custom database query to get the attachment id from the modified attachment url.
        $attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = %s AND wposts.post_type = 'attachment'", $attachment_url ) );
 
    }
 
    return $attachment_id;
}

/**
 * Customize the post thumbnail markup.
 *
 * @param string $html The post thumbnail markup.
 * @param number $post_id The post id.
 * @param number $post_thumbnail_id THe post thumbnail id.
 * @param string/array $size The post thumbnail size.
 * @param array $attributes Query string of attributes.
 *
 * @return string Image tag of the post thumbnail.
 */
function afi_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attributes ) {

    // If the `$attributes` variable is not an array, make it an array.
    if ( ! is_array( $attributes ) ) {
                 
         $attributes = array();

    }

    // If the attribute "class" is not set, set it.
    if ( ! isset( $attributes['class'] ) ) {

         $attributes['class'] =  'wp-post-image attachment-' . $size;

    }  

    // Get all the data of the image we saved.
    $images = get_post_meta( $post_id, '_afi_image', true );

    $imageURL = '';
    
    // Check if we have multiple images.
    if ( $images && is_array( $images ) && ! empty( $images ) ) {

        // Get the size of the image.
        $image = $images[ $size ];

        $imageURL = $image['url'];

        // Set the width and height attributes based on image size.
        $attributes['width']  = $image['width'];
        $attributes['height'] = $image['height'];

    } else {

        // Get the image source from the post.
        $imageURL = get_post_meta( $post_id, '_ibenic_mufimg_src', true );

    }
    
    $imageAttributes = '';

    // Concatenate the image attributes, so we can append it to the image url.
    if ( is_array( $attributes ) && ! empty( $attributes ) ) {

        $imageAttributes .= '# ';

        foreach ( $attributes as $attribute => $value ) {

            $imageAttributes .= ' ' . $attribute . '="' . $value . '" ';

        }

    }

    // Create and return the image tag.
    return '<img src="' . esc_url( $imageURL ) . $imageAttributes . ' />';

}

add_filter( 'post_thumbnail_html', 'afi_post_thumbnail_html', 99, 5 );

/**
 * Remove all the plugin data when plugin is deactivated.
 */
function afi_deactivate() {

    global $wpdb, $blog_id;

    // Delete from multisite if the multisite is enabled.
    if ( is_multisite() ) {

        // Get all ids from all sites.
        $ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

        foreach ( $ids as $id ) {

            switch_to_blog( $id );

            afi_delete_from_site();
        }

        // Get back to the original site.
        switch_to_blog( $blog_id );

    } else {

        afi_delete_from_site();

    }

}

/**
 * Deleting plugin from content.
 */
function afi_delete_from_site() {
    
    global $wpdb;

    // Query for all  post IDs where the advanced featured image was used
    $postmeta = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_afi_img_src'";

    // Get all post IDs where the advanced featured image was used
    $postIDs = $wpdb->get_col( $postmeta );

    // Delete all meta data with the key '_afi_img_src'
    delete_post_meta_by_key( '_afi_img_src' ); 

    // Delete all meta data with the key '_afi_image'
    delete_post_meta_by_key( '_afi_image' ); 

    // Delete fake thumbnail ID information for every post that had advanced featured image
    foreach ( $postIDs as $postID ) {
         
        delete_post_meta( $postID, '_thumbnail_id' );

    }

}

register_deactivation_hook( __FILE__, 'afi_deactivate' );