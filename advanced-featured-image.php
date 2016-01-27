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
 * Saving the thumbnail when used on other multisites
 * @param  number $post_id ID of the post 
 */
function afi_save_thumbnail( $post_id ) {
       
    $imageURL = $_POST['afi-img-src'];

    $imageID = afi_get_attachment_id_from_url( $imageURL );

    // Current site ID
    $currentBlogID = get_current_blog_id();

    // Indicator if we have switched to other sites
    $switchedBlog = false;

    // If the imgID was not found, we need to look on other blog sites. 
    if( ! $imageID ) {

         global $wpdb;

         // Get ID  of all installed sites
         $blogList =  $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid ), ARRAY_A );
      
         foreach ( $blogList as $blog ) {

             // If the loop is for the current site, we can skip that since we already know that the $imageID for that site is null
             if ( $blog['blog_id'] == $currentBlogID ) {

                 continue;
             }

             // Switch to the new site 
             switch_to_blog( $blog['blog_id'] );

             // Indicate that we have switched
             $switchedBlog = true;

             // Get the image ID on that site
             $imageID = afi_get_attachment_id_from_url( $imageURL );

             /*
              * If we have found an ID, then we have found the image
              * Break from the loop if the image was found, otherwise continue searching
              */
             if ( $imageID != false ) {

                 break;
             }
             
         }
    }

    $images = array();
     
    // If there is an attachment we can get different sizes
    if ( false != $imageID ) {

         // Get MetaData for that attachment ID
         $imageMetaData = wp_get_attachment_metadata( $imageID );         

         // Original Image
         $imageFull = wp_get_attachment_image_src( $imageID, 'full' );
         
        // Adding to array $images a size with its values
        $images['full'] = array(
             'url'    => $imageFull[0],
             'width'  => $imageMetaData['width'],
             'height' => $imageMetaData['height']
        );

 
        // For each size in the $imageMetaData of that image, add corresponding values to the array $images
         foreach ( $imageMetaData['sizes'] as $size => $sizeInfo ) {

             $image = wp_get_attachment_image_src( $imageID, $size );

             $images[ $size ] = array(
                 'url'    => $image[0],
                 'width'  => $sizeInfo['width'],
                 'height' => $sizeInfo['height']
             );
             
        }

       
    }
     
    // If we have switched to the other site, return to the current one 
    if ( $switchedBlog ) {

        restore_current_blog();

    }
    
    // Save our data
    update_post_meta( $post_id, '_afi_image', $images );

    update_post_meta( $post_id, '_afi_img_src', $imageURL );

    // Fake the thumbnail_id so the has_post_thumbnail works as intended on other sites
    update_post_meta( $post_id, '_thumbnail_id', '1' );
}

// Add a new function to save the featured image data
add_action( 'save_post', 'afi_save_thumbnail' );

/**
 * Get the ID of the attachment if exists
 * @param  string $attachment_url  
 * @return mix                  
 */
function afi_get_attachment_id_from_url( $attachment_url = '' ) {
 
    global $wpdb;

    $attachment_id = false;
 
    // If there is no url, return.
    if ( '' == $attachment_url ) {

        return;

    }
 
    // Get the upload directory paths
    $upload_dir_paths = wp_upload_dir();
 
    // Make sure the upload path base directory exists in the attachment URL, to verify that we're working with a media library image
    if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {
 
        // If this is the URL of an auto-generated thumbnail, get the URL of the original image
        $attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );
 
        // Remove the upload path base directory from the attachment URL
        $attachment_url = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
 
        // Finally, run a custom database query to get the attachment ID from the modified attachment URL
        $attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = %s AND wposts.post_type = 'attachment'", $attachment_url ) );
 
    }
 
    return $attachment_id;
}


/**
 * Change the post thumbnail Source
 * @param  string         $html               
 * @param  number         $post_id            
 * @param  number         $post_thumbnail_id  
 * @param  string/array $size               
 * @param  array         $attr              
 * @return string                    
 */
function afi_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attributes ) {

    // If the variable $attr is not an array, make it an array
    if ( ! is_array( $attributes ) ) {
                 
         $attributes = array();

    }

    // If the attribute "class" is not set, set it
    if ( ! isset( $attributes['class'] ) ) {

         $attributes['class'] =  'wp-post-image attachment-' . $size;

    }  

      
    // Get all the data of the image we have saved
    $images = get_post_meta( $post_id, '_afi_image', true );
     
    $imageURL = "";
    
    // Check if is an array. If not then we have saved an image from URL
    if ( $images && is_array( $images ) && count( $images ) > 0 ) {
        
        // Get Image array from the requsted size
        $image = $images[ $size ];
        
        $imageURL = $image['url'];

        // Setting the width and height attributes
        $attributes['width']  = $image['width'];
        $attributes['height'] = $image['height'];

    } else {
        
        /*
         * If we do not have an array in $image or is empty, then this is an image provided from URL
         * Get only the SRC
         */
        $imageURL = get_post_meta( $post_id, '_ibenic_mufimg_src', true );

    }
    
    // Start the Tag
    $imageTag = '<img ';

    // Set the URL of the image
    $imageTag .= ' src="' . esc_url( $imageURL ) . '# ';


    /*
     * If the variable $attr is an array and it is not empty,
     * add that attribute to the image string
     */
    if ( is_array( $attributes ) && count( $attributes ) > 0 ) {

         foreach ( $attributes as $attribute => $value ) {
             
             $imageTag .= ' ' . $attribute . '="' . $value . '" ';

         }
    }

    // Closing the image tag
    $imageTag .= ' />';
    
    // Return the image
    return $imageTag;

}

add_filter( 'post_thumbnail_html', 'afi_post_thumbnail_html', 99, 5 );


/**
 * Remove all the data of the Advanced Featured Image
 * @return void
 **/
function afi_deactivate() {

 
    global $wpdb, $blog_id;


    // Delete from Multisite if the multisite is enabled
    if ( is_multisite() ) {

        // Query to select IDs from all sites
        $dbquery = "SELECT blog_id FROM $wpdb->blogs";

        // IDs from all sites
        $ids = $wpdb->get_col( $dbquery );

         
        foreach ( $ids as $id ) {

            switch_to_blog( $id );

            afi_delete_from_site();
        }

        // Get back to the original site
            switch_to_blog( $blog_id );

    } else {

        afi_delete_from_site();

    }


}

/**
 * Deleting Advanced Featured Image data from content
 * @return void
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