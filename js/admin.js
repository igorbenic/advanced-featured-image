(function( $ ) {
    'use strict';
   
    $( document ).ready(function(){

        var afi_custom_image_container = $( '#custom_image_container'),
            afi_delete_custom_image = $( '.delete-custom-img'),
            afi_upload_custom_image = $( '.upload-custom-img' );
         
        function set_uploader( button, field ) {

            var button_to_click = $( button),
                field_to_insert = $( field );

            // Make sure both the button and the field exist.
            if ( button_to_click && field_to_insert ) {

                // Show the thick box when the button is clicked.
                button_to_click.on( 'click', function() {

                    tb_show( '', 'media-upload.php?type=image&TB_iframe=true' );

                    // When the thick box is opened, set send to editor button.
                    set_send( field );
                     
                });
            }

        }

        function set_send( field ) {

            // Store `send_to_event`, so at end of the function, the editor works correctly.
            window.original_send_to_editor = window.send_to_editor;

            // Override function so you can have multiple uploaders pre page.
            window.send_to_editor = function( html ) {

                var image_url = $( 'img', html ).attr( 'src' );

                // If image url is undefined, then there is only one image from link.
                if ( undefined === image_url ) {

                    image_url = $( html ).attr( 'src' );

                }

                $( field ).val( image_url );

                afi_custom_image_container.append( '<img src="' + image_url + '" style="max-width: 100%;" />' );
                afi_delete_custom_image.removeClass( 'hidden' );
                afi_upload_custom_image.addClass( 'hidden' );

                tb_remove();

                // Set normal uploader for the editor.
                window.send_to_editor = window.original_send_to_editor;
            };
        }

        function remove_image( button ) {

            $( button ).on( 'click', function( event ) {
                 
                event.preventDefault();

                afi_custom_image_container.empty();

                $( '.afi-img-id' ).val( '' );

                afi_delete_custom_image.addClass( 'hidden' );
                afi_upload_custom_image.removeClass( 'hidden' );

            });

        }

        set_uploader( '.upload-custom-img', '.afi-img-id' );
        remove_image( '.delete-custom-img' );

    });

})( jQuery );
