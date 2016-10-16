( function ( $ ) {
	'use strict';

	/**
	 * Opens the Media Uploader and reopens it on every subsequent call.
	 *
	 * @param object imageContainer jQuery object of the image container to display image.
	 * @param object inputField jQuery object of the input field to store image.
	 * @param object uploadImage jQuery object of the upload image container (displayed when there is no image).
	 * @param object deleteImage jQuery object of the delete image container (displayed when we uploaded the image).
	 *
	 * @return void
	 */
	function afiRenderMediaUploader( imageContainer, inputField, uploadImage, deleteImage ) {

		var fileFrame;

		/**
		 * If an instance of fileFrame already exists, then we can open it
		 * rather than creating a new instance.
		 */

		if ( undefined !== fileFrame ) {

			fileFrame.open();

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
		fileFrame = wp.media({
			frame: 'post',
			multiple: false
		} );

		fileFrame.on( 'select', function () {

			var state = fileFrame.state(),
				id = state.get( 'id' ),
				type = state.get( 'type' ),
				image = null;

			if ( id === 'embed' && type === 'image' ) {

				image = state.props.toJSON();
				image.url = image.url || '';

			} else {

				image = state.get( 'selection' ).single().toJSON();

			}

			if ( image && image.url ) {

				afiCreateImage( imageContainer, deleteImage, uploadImage, image, inputField );

			}

		} );

		/**
		 * Setup an event handler for what to do when an image has been
		 * selected.
		 *
		 * Since we're using the 'view' state when initializing
		 * the fileFrame, we need to make sure that the handler is attached
		 * to the insert event.
		 */
		fileFrame.on( 'insert', function() {

			var state = fileFrame.state(),
				json = state.attributes.selection.single().toJSON(); // Read the JSON data returned from the Media Uploader.

			// First, make sure that we have the URL of an image to display.
			if ( 0 > $.trim( json.url.length ) ) {

				return;

			}

			// After that, set the properties of the image and display it.
			afiCreateImage( imageContainer, deleteImage, uploadImage, json, inputField );

		} );

		// Now display the actual fileFrame.
		fileFrame.open();

	}

	/**
	 * Creating the image and appending it to the image container. Getting the image URL
	 * and adding it as value to the input field.
	 *
	 * @param object imageContainer jQuery object of the image container to display image.
	 * @param object uploadImage jQuery object of the upload image container (displayed when there is no image).
	 * @param object deleteImage jQuery object of the delete image container (displayed when we uploaded the image).
	 * @param object json Attachment JSON.
	 * @param object inputField jQuery object of the input field to store image.
	 *
	 * @return void
	 */
	function afiCreateImage( $imageContainer, $deleteImage, $uploadImage, json, inputField){
		$imageContainer.append( '<img src="' + json.url + '" alt="' + json.caption + '" style="max-width: 100%;" />' );
		inputField.val( json.url );
		$deleteImage.removeClass( 'hidden' );
		$uploadImage.addClass( 'hidden' );
	}

	$( document ).ready( function () {

		var afiCustomImageContainer = $( '#custom_image_container' ),
			afiDeleteCustomImage = $( '.delete-custom-img' ),
			afiUploadCustomImage = $( '.upload-custom-img' ),
			inputField = $( '.afi-img-id' );

		// Make sure both the button and the field exist.
		if ( afiUploadCustomImage && inputField ) {

			// Show the thick box when the button is clicked.
			afiUploadCustomImage.on( 'click', function () {

				afiRenderMediaUploader( afiCustomImageContainer, inputField, afiUploadCustomImage, afiDeleteCustomImage );

			} );

			/**
			 * Replacing old thickbox functionality used by Network Shared Media.
			 */

			// Store the original send_to_event function.
			window.original_send_to_editor = window.send_to_editor;

			// Override the function send_to_event so you can have multiple uploaders pre page.
			window.send_to_editor = function ( html ) {

				// HTML provided is an img element itself.
				var imgurl = $( 'img', html ).attr( 'src' );

				// If imgurl is undefined, then there is only one img from link.
				if ( typeof imgurl === 'undefined' ) {

					imgurl = $( html ).attr( 'src' );

				}

				inputField.val( imgurl );
				afiCustomImageContainer.append( '<img src="' + imgurl + '" style="max-width:100%;" />' );
				afiDeleteCustomImage.removeClass( 'hidden' );
				afiUploadCustomImage.addClass( 'hidden' );

				// Remove the thickbox.
				tb_remove();

				// Set normal uploader for editor.
				window.send_to_editor = window.original_send_to_editor;

			};
		}

		// Handle removing image.
		afiDeleteCustomImage.on( 'click', function ( event ) {

			event.preventDefault();

			afiCustomImageContainer.empty();

			inputField.val( '' );

			afiDeleteCustomImage.addClass( 'hidden' );
			afiUploadCustomImage.removeClass( 'hidden' );

		});

	});

} )( jQuery );
