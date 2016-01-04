
( function( $ ) {
   
	var afi_custom_image_container	= $( '.custom-img-container' );
	var afi_delete_custom_image		= $( '.delete-custom-img' );
	var afi_upload_custom_image		= $( '.upload-custom-img' );
	
	function set_uploader( button, field ) {
		var button_to_click = $( button );
		var field_to_insert = $( field );
		// make sure both button and field are in the DOM
		if( button_to_click && field_to_insert ){ 
			// when button is clicked show thick box
		
			$(document).on('click', button, function() {
				tb_show( '', 'media-upload.php?type=image&TB_iframe=true' );

				// when the thick box is opened set send to editor button
				set_send( field );
				 
			} );
	    }

	}

	function set_send( field ) {
		// store send_to_event so at end of function normal editor works
		window.original_send_to_editor = window.send_to_editor;

		// override function so you can have multiple uploaders pre page
		window.send_to_editor = function( html ) {

			
			var image_url = $( 'img', html ).attr( 'src' );

			//If imgurl is undefined, then there is only one img from link
			if( undefined === $image_url ){

				image_url = $( html ).attr( 'src' );

			}

			$( field ).val( image_url );
			afi_custom_image_container.append( '<img src="' + image_url + '"" style="max-width:100%;" />' );
			afi_delete_custom_image.removeClass( 'hidden' );
			afi_upload_custom_image.addClass( 'hidden' );
			tb_remove();


			// Set normal uploader for editor
			window.send_to_editor = window.original_send_to_editor;
		};
	}

	function remove_image( button ){

		$( button ).click( function(){

			afi_custom_image_container.children( 'img' ).remove();

			$( '.afi-img-id' ).val( '' );
			 
			afi_delete_custom_image.addClass( 'hidden' );

			afi_upload_custom_image.removeClass( 'hidden' );

		});

	}

	set_uploader( '.upload-custom-img', '.afi-img-id' );
	remove_image( '.delete-custom-img' );

    
})( jQuery );
