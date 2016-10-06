/**
 * JavaScript for Advanced Featured Image.
 *
 * This file contains functions that will handle the WordPress Media Uploader.
 *
 * @package WordPress
 */

/**
 * Opens the Media Uploader and reopens it on every subsequent call
 *
 * @param  object $              jQuery reference
 * @param  object imageContainer jQuery object of the image container to display image
 * @param  object inputField     jQuery object of the input field to store image
 * @param  object uploadImage    jQuery object of the upload image container (displayed when there is no image)
 * @param  object deleteImage    jQuery object of the delete image container (displayed when we uploaded the image)
 * @return void
 */
function afi_renderMediaUploader( $, imageContainer, inputField, uploadImage, deleteImage ) {
	'use strict';

	var file_frame, image_data;

	/**
	 * If an instance of file_frame already exists, then we can open it
	 * rather than creating a new instance.
	 */

	if ( undefined !== file_frame ) {

		file_frame.open();

		return;

	}

	/**
	 * If we're this far, then an instance does not exist, so we need to
	 * create our own.
	 *
	 * Here, use the wp.media library to define the settings of the Media
	 * Uploader. We're opting to use the 'post' frame which is a template
	 * defined in WordPress core and are initializing the file frame
	 * with the 'insert' state.
	 *
	 * We're also not allowing the user to select more than one image.
	 */
	file_frame = wp.media({
		frame: 'post',
		multiple: false,
	});

	file_frame.on( 'select', function( selection ){

		var state = file_frame.state();
		var id = state.get( 'id' );
		var type = state.get( 'type' );
		var image = null;
		if ( id == 'embed' && type == 'image' ) {
			image = state.props.toJSON();
			image.url = image.url || '';
		} else {
			image = state.get( 'selection' ).single().toJSON();
		}

		if ( image && image.url ) {
			createImage( imageContainer, deleteImage, uploadImage, image, inputField );
		}

	});

	/**
	 * Setup an event handler for what to do when an image has been
	 * selected.
	 *
	 * Since we're using the 'view' state when initializing
	 * the file_frame, we need to make sure that the handler is attached
	 * to the insert event.
	 */
	file_frame.on( 'insert', function( selection ) {

		var state = file_frame.state();
		// Read the JSON data returned from the Media Uploader.
		var json = state.attributes.selection.single().toJSON();

		// First, make sure that we have the URL of an image to display.
		if ( 0 > $.trim( json.url.length ) ) {
			return;
		}

		// After that, set the properties of the image and display it.
		createImage( imageContainer, deleteImage, uploadImage, json, inputField );

	});

	// Now display the actual file_frame.
	file_frame.open();

}

function createImage( $imageContainer, $deleteImage, $uploadImage, json, inputField){
	$imageContainer.append( '<img src="' + json.url + '" alt="' + json.caption + '" style="max-width: 100%;" />' );
	inputField.val( json.url );
	$deleteImage.removeClass( 'hidden' );
	$uploadImage.addClass( 'hidden' );
}



(function( $ ) {
	'use strict';

	$( document ).ready(function(){

		var afi_custom_image_container = $( '#custom_image_container' ),
			afi_delete_custom_image = $( '.delete-custom-img' ),
			afi_upload_custom_image = $( '.upload-custom-img' ),
			inputField = $( '.afi-img-id' );

		// Make sure both the button and the field exist.
		if ( afi_upload_custom_image && inputField ) {

			// Show the thick box when the button is clicked.
			afi_upload_custom_image.on( 'click', function() {

				afi_renderMediaUploader( $, afi_custom_image_container, inputField, afi_upload_custom_image, afi_delete_custom_image );

			});

			/**
			 * Replacing old thickbox functionality used by Network Shared Media.
			 */

			// Store the original send_to_event function.
			window.original_send_to_editor = window.send_to_editor;

			// Override the function send_to_event so you can have multiple uploaders pre page.
			window.send_to_editor = function(html) {

				// HTML provided is an img element itself.
				var imgurl = $( 'img', html ).attr( 'src' );

				// If imgurl is undefined, then there is only one img from link.
				if (typeof imgurl == 'undefined') {
					imgurl = $( html ).attr( 'src' );
				}

				inputField.val( imgurl );
				afi_custom_image_container.append( "<img src='" + imgurl + "' style='max-width:100%;' />" );
				afi_delete_custom_image.removeClass( "hidden" );
				afi_upload_custom_image.addClass( "hidden" );
				// Remove the thickbox.
				tb_remove();

				// Set normal uploader for editor.
				window.send_to_editor = window.original_send_to_editor;
			};
		}

		// Handle removing image.
		afi_delete_custom_image.on( 'click', function( event ) {

			event.preventDefault();

			afi_custom_image_container.empty();

			inputField.val( '' );

			afi_delete_custom_image.addClass( 'hidden' );
			afi_upload_custom_image.removeClass( 'hidden' );

		});

	});

})( jQuery );
