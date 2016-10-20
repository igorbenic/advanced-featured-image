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
 *
 * @package WordPress
 */

if ( ! defined( 'WPINC' ) ) {

	die;

}

/**
 * Markup for the advanced featured image metabox.
 *
 * @param string $content Content in the featured image metabox.
 * @param number $post_id Id of the post we are on.
 *
 * @return string Content for the featured image.
 */
function afi_metabox( $content, $post_id ) {

	wp_nonce_field( 'afi_metabox_' . $post_id, 'afi_metabox_nonce' );

	$image_url = get_post_meta( $post_id, '_afi_img_src', true );

	if ( ! $image_url ) {

		$post_thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( '' !== $post_thumbnail_id ) {

			$image_url = wp_get_attachment_url( $post_thumbnail_id );

		}
	}

	$content = '<div id="custom_image_container">';

	if ( $image_url ) {

		$content .= '<img src="' . $image_url . '" style="max-width:100%;" />';

	}

	$content .= '</div>' ;

	// Add & remove image links.
	$content .= '<p class="hide-if-no-js">';

		$content .= '<a class="upload-custom-img ' . ( ( $image_url  ) ? 'hidden' : '' ) . '" href="#">';

			$content .= __( 'Set custom image', 'tutsplus' );

		$content .= '</a>';

		$content .= '<a class="delete-custom-img ' . ( ( ! $image_url  ) ? 'hidden' : ''  ) . '" href="#">';

			$content .= __( 'Remove this image', 'tutsplus' );

		$content .= '</a>';

	$content .= '</p>';

	// Hidden input to set the chosen image url on post.
	$content .= '<input class="afi-img-id" name="afi-img-src" type="hidden" value="' . esc_url( $image_url ) . '" />';

	return $content;

}

add_filter( 'admin_post_thumbnail_html', 'afi_metabox', 1, 2 );

/**
 * Loads the JavaScript for the advanced featured image metabox.
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

	if ( ! isset( $_POST['afi_metabox_nonce'] ) ) {

		return;

	}

	if ( ! wp_verify_nonce( sanitize_key( $_POST['afi_metabox_nonce'] ), 'afi_metabox_' . $post_id ) ) {

		return;

	}

	// Check if user has permissions to save data.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {

		return;

	}

	// Check if not an autosave.
	if ( wp_is_post_autosave( $post_id ) ) {

		return;

	}

	// Check if not a revision.
	if ( wp_is_post_revision( $post_id ) ) {

		return;

	}

	if ( ! isset( $_POST['afi-img-src'] ) ) {

		return;

	}

	$image_url = sanitize_text_field( wp_unslash( $_POST['afi-img-src'] ) );

	if ( '' === $image_url ) {

		delete_post_meta( $post_id, '_afi_image' );
		delete_post_meta( $post_id, '_afi_img_src' );
		delete_post_meta( $post_id, '_thumbnail_id' );

		// Getting all the posts that use the AFI image.
		$posts_with_afi = get_option( 'posts_with_afi_image', array() );

		// Deleting the post ID since it is not using the AFI image anymore.
		if ( in_array( $post_id, $posts_with_afi, true ) ) {

			$index = array_search( $post_id, $posts_with_afi, true );
			unset( $posts_with_afi[ $index ] );

			// Reindexing the array.
			$posts_with_afi = array_values( $posts_with_afi );
			update_option( 'posts_with_afi_image', $posts_with_afi );

		}

		return;

	}

	$image_id = afi_get_attachment_id_from_url( $image_url );

	// Current site id.
	$current_blog_id = get_current_blog_id();

	// Flag to track if we have switched to another site.
	$switched_blog = false;
	$switched_count = 0;

	// If the image was not found, we need to look on other blog sites.
	if ( is_multisite() && ! $image_id ) {

		global $wpdb;

		$sites_args = array(
			'public' => 1,
			'archived' => 0,
			'spam' => 0,
			'deleted' => 0,
		);

		$sites = null;
		$deprecated = true;

		// WordPress 4.6 and up. We will not use the deprecated function.
		if ( function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {

			$deprecated = false;

		}

		// WordPress 4.6 and up.
		if ( ! $deprecated ) {

			$sites = get_sites( $sites_args );

		} else {

			// WordPress below 4.6.
			$sites = wp_get_sites( $sites_args );

		}

		foreach ( $sites as $site ) {

			$site_id = 0;

			// WordPress 4.6 and up.
			if ( ! $deprecated ) {

				$site_id = $site->blog_id;

			} else {

				// WordPress below 4.6.
				$site_id = $site['blog_id'];

			}

			// Skip the site if it is current site we already checked above.
			if ( $site_id === $current_blog_id ) {

				continue;

			}

			// Switch to the new site.
			switch_to_blog( $site_id );

			// Track that we have switched sites.
			$switched_blog = true;
			$switched_count++;

			// Get the image id on this latest site.
			$image_id = afi_get_attachment_id_from_url( $image_url );

			// Break from the loop if the image was found, otherwise continue searching.
			if ( false !== $image_id ) {

				break;

			}
		}
	}

	$images = array();

	if ( ! $image_id ) {

		// The Image was not found on our sites. It must be an image from another site.
		// Let's download it and save it in our main site.
		if ( is_multisite() && true === $switched_blog ) {

			for ( $i = 0; $i < $switched_count; $i++ ) {

				restore_current_blog(); // Restore for each switch.

			}

			// We have restored.
			$switched_blog = false;
		}

		$image_id = afi_save_external_image( $image_url );
		$image_url = wp_get_attachment_url( $image_id );

	}

	// Make sure we found an image.
	if ( $image_id ) {

		// Get meta data for that attachment id.
		$image_meta_data = wp_get_attachment_metadata( $image_id );

		// Get the original, uploaded image.
		$original_image = wp_get_attachment_image_src( $image_id, 'full' );

		// Add original image to array of sizes.
		$images['full'] = array(
			'url'	=> $original_image[0],
			'width'  => $image_meta_data['width'],
			'height' => $image_meta_data['height'],
		);

		// Add each generated size of the original image to the array of sizes.
		foreach ( $image_meta_data['sizes'] as $size => $size_info ) {

			$image = wp_get_attachment_image_src( $image_id, $size );

			$images[ $size ] = array(
				'url'	=> $image[0],
				'width'  => $size_info['width'],
				'height' => $size_info['height'],
			);

		}
	}

	// Return to the current site, if we switched during checking for images.
	if ( is_multisite() && true === $switched_blog ) {

		for ( $i = 0; $i < $switched_count; $i++ ) {

			// Restore for each switch.
			restore_current_blog();
		}

		$switched_blog = false;
	}

	// Getting all the posts that use the AFI image.
	$posts_with_afi = get_option( 'posts_with_afi_image', array() );

	// Saving our post ID.
	if ( ! in_array( $post_id, $posts_with_afi, true ) ) {

		$posts_with_afi[] = $post_id;
		update_option( 'posts_with_afi_image', $posts_with_afi );

	}

	// Save images to post meta data.
	update_post_meta( $post_id, '_afi_image', $images );
	update_post_meta( $post_id, '_afi_img_src', $image_url );

	// Fake the `thumbnail_id` so the `has_post_thumbnail` works as intended on other sites.
	update_post_meta( $post_id, '_thumbnail_id', $image_id );

}

add_action( 'save_post', 'afi_save_thumbnail' );

/**
 * Download the image from url and save it as an attachment.
 *
 * @param string $url The image URL.
 *
 * @return number Integer of the Attachment ID.
 */
function afi_save_external_image( $url ) {

	$temporary_file = download_url( $url );
	$real_file = pathinfo( $url );
	$basename = $real_file['basename'];
	$extension = $real_file['extension'];
	$allowed_extensions = array( 'jpg', 'png', 'gif' );

	$extension_query_position = strpos( $extension, '?' );

	// If our extension has some query strings, we must clear it.
	if ( false !== $extension_query_position ) {

		// Getting only the extension by using the query string start as length.
		$extension = substr( $extension, 0, $extension_query_position );

	}

	if ( ! in_array( strtolower( $extension ), $allowed_extensions, true ) ) {

		return 0;

	}

	$filename = str_replace( '.' . $extension, '', $basename );
	$sanitized_basename = sanitize_title( $filename ) . '.' . $extension;

	$file_array = array();
	$file_array['tmp_name'] = $temporary_file;
	$file_array['name'] = $sanitized_basename;
	$file_array['type'] = 'image/' . $extension;
	$file_array['error'] = 0;
	$file_array['size'] = filesize( $temporary_file );

	// Validate and store the image.
	$att_id = media_handle_sideload( $file_array, 0 );

	// If error storing permanently, unlink.
	if ( is_wp_error( $att_id ) ) {

		global $wp_filesystem;
		$wp_filesystem->delete( $file_array['tmp_name'] );

		return 0;

	}

	// Set as post thumbnail if desired.
	return $att_id;

}

/**
 * Gets the attachment id of the image, if it exists.
 *
 * @link https://philipnewcomer.net/2012/11/get-the-attachment-id-from-an-image-url-in-wordpress/
 *
 * @param string $attachment_url The url of the attachment image.
 *
 * @return number Attachment id of image.
 */
function afi_get_attachment_id_from_url( $attachment_url = '' ) {

	global $wpdb;

	$attachment_id = false;

	// Exit if there is no url.
	if ( '' === $attachment_url ) {

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
		$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = %s AND wposts.post_type = 'attachment'", $attachment_url ) ); // WPCS: db call ok, cache ok.

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

		 $attributes['class'] = 'wp-post-image attachment-' . $size;

	}

	// Get all the data of the image we saved.
	$images = get_post_meta( $post_id, '_afi_image', true );

	$image_url = '';

	$srcset = '';

	$sizes = '';

	// Check if we have multiple images.
	if ( $images && is_array( $images ) && ! empty( $images ) ) {

		// Get the size of the image. If the size does not exist, use the original image.
		if ( isset( $images[ $size ] ) ) {

			$image = $images[ $size ];

		} else {

			$image = $images['full'];

		}

		$image_url = $image['url'];

		// Set the width and height attributes based on image size.
		$attributes['width']  = $image['width'];
		$attributes['height'] = $image['height'];

		$sizes = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $image['width'] );

		$used_sizes = array();

		foreach ( $images as $size => $size_array ) {

			// If a width is already used, we do not need another one.
			// It can happen when there is the same image for several sizes.
			if ( in_array( $size_array['width'], $used_sizes, true ) ) {

				continue;

			}

			$used_sizes[] = $size_array['width'];

			$srcset .= $size_array['url'] . ' ' . $size_array['width'] . 'w ';

		}
	} else {

		// Get the image source from the post.
		$image_url = get_post_meta( $post_id, '_afi_img_src', true );

		if ( ! $image_url ) {

			return $html;

		}
	}

	$image_attributes = '';

	// Concatenate the image attributes, so we can append it to the image url.
	if ( is_array( $attributes ) && ! empty( $attributes ) ) {

		foreach ( $attributes as $attribute => $value ) {

			$image_attributes .= ' ' . $attribute . '="' . $value . '" ';

		}
	}

	if ( '' !== $srcset ) {

		$srcset = 'srcset="' . $srcset . '"';

	}

	if ( '' !== $sizes ) {

		$sizes = 'sizes="' . $sizes . '"';

	}

	// Create and return the image tag.
	return '<img src="' . esc_url( $image_url ) . '" ' . $srcset . ' ' . $sizes . ' ' . $image_attributes . ' />';

}

add_filter( 'post_thumbnail_html', 'afi_post_thumbnail_html', 99, 5 );

/**
 * Remove all the plugin data when plugin is deactivated.
 */
function afi_deactivate() {

	global $wpdb, $blog_id;

	// Delete from multisite if the multisite is enabled.
	if ( is_multisite() ) {

		// Get all sites.
		$sites_args = array(
			'public' => 1,
			'archived' => 0,
			'spam' => 0,
			'deleted' => 0,
		);
		$sites = wp_get_sites( $sites_args );

		foreach ( $sites as $site ) {

			switch_to_blog( $site['blog_id'] );

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

	// Get all post ids where the advanced featured image was used.
	$post_ids = get_option( 'posts_with_afi_image', array() );

	// Delete all meta data with the key `_afi_img_src`.
	delete_post_meta_by_key( '_afi_img_src' );

	// Delete all meta data with the key `_afi_image`.
	delete_post_meta_by_key( '_afi_image' );

	// Delete fake thumbnail id information for every post that had advanced featured image.
	if ( ! empty( $post_ids ) ) {

		foreach ( $post_ids as $post_id ) {

			delete_post_meta( $post_id, '_thumbnail_id' );

		}

	}

	// Deleting the record of all the posts with advanced featured image.
	delete_option( 'posts_with_afi_image' );
}

register_deactivation_hook( __FILE__, 'afi_deactivate' );
