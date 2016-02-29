'use strict';

function afiRenderMediaUploader($, imageContainer, inputField, uploadImage, deleteImage ) {

    var file_frame;
 
    /*
     * If an instance of `file_frame` already exists, then we can open it
     * rather than creating a new instance.
     */
  
    if ( undefined !== file_frame ) {
 
        file_frame.open();
    
        return;
 
    }
 
    /*
     * If we're this far, then an instance does not exist, so we need to
     * create our own.
     *
     * Use the `wp.media` library to define the settings of the Media
     * Uploader. We're opting to use the 'post' frame, which is a template
     * defined in WordPress core and is initializing the file frame
     * with the 'insert' state.
     *
     * We're also not allowing the user to select more than one image.
     */

    file_frame = wp.media({
        frame: 'post',
        multiple: false
    });
 
    file_frame.on( 'select', function() {
       
        var state = file_frame.state(),
            id = state.get( 'id' ),
            type = state.get( 'type' ),
            image;

        if ( 'embed' === id && 'image' === type ) {

            image = state.props.toJSON();
            image.url = image.url || '';

        } else {

            image = state.get( 'selection' ).single().toJSON();

        }
        
        if ( image && image.url ) {

            afiCreateImage( imageContainer, deleteImage, uploadImage, image, inputField );

        }
                
    });
    
    /*
     * Setup an event handler for what to do when an image has been
     * selected.
     *
     * Since we're using the 'view' state when initializing the
     * `file_frame`, we need to make sure that the handler is attached
     * to the insert event.
     */

    file_frame.on( 'insert', function() {
      
       var state = file_frame.state();

        // Read the JSON data returned from the Media Uploader.
        var json = state.attributes.selection.single().toJSON();
              
        // First, make sure that we have the URL of an image to display.
        if ( 0 > $.trim( json.url.length ) ) {

            return;

        }
     
        // After that, set the properties of the image and display it.
        afiCreateImage( imageContainer, deleteImage, uploadImage, json, inputField );
   
    });
 
    // Now display the actual `file_frame`.
    file_frame.open();
 
}

function afiCreateImage( $imageContainer, $deleteImage, $uploadImage, json, inputField ) {

    $imageContainer.append( '<img src="' + json.url + '" alt="' + json.caption + '" style="max-width: 100%;" />' );
    inputField.val( json.url );
    $deleteImage.removeClass( 'hidden' );
    $uploadImage.addClass( 'hidden' );

}

(function( $) {

    $( document ).ready( function() {

        var customImageContainer = $( '#custom_image_container'),
            deleteCustomImage = $( '.delete-custom-img'),
            uploadCustomImage = $( '.upload-custom-img' ),
            inputField = $( '.afi-img-id' );

        // Make sure both the button and the field exist.
        if ( uploadCustomImage && inputField ) {

            // Show the meida uploader when the button is clicked.
            uploadCustomImage.on( 'click', function() {

                afiRenderMediaUploader( $, customImageContainer, inputField, uploadCustomImage, deleteCustomImage );

            });
        }

        // Handle removing image.
        $( deleteCustomImage ).on( 'click', function( event ) {

            event.preventDefault();

            customImageContainer.empty();

            inputField.val( '' );

            deleteCustomImage.addClass( 'hidden' );
            uploadCustomImage.removeClass( 'hidden' );

        });

    });

})( jQuery );
