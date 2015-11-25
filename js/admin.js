jQuery(function($){

	function set_uploader( button, field ) {

		// make sure both button and field are in the DOM
		if( jQuery( button ) && jQuery( field ) ) {
			// when button is clicked show thick box
			jQuery( button ).click(function() {
				tb_show( '', 'media-upload.php?type=image&TB_iframe=true' );

				// when the thick box is opened set send to editor button
				set_send( field );
				return false;
			});
		}

	}

	function set_send( field ) {
		// store send_to_event so at end of function normal editor works
		window.original_send_to_editor = window.send_to_editor;

		// override function so you can have multiple uploaders pre page
		window.send_to_editor = function( html ) {

			 
			imgurl = $( 'img', html ).attr( 'src' );
			//If imgurl is undefined, then there is only one img from link
			if( typeof imgurl == 'undefined' ){

				imgurl = $( html ).attr( 'src' );

			}

			$( field ).val( imgurl );
			$( ".custom-img-container" ).append( "<img src='" + imgurl + "' style='max-width:100%;' />" );
			$( ".delete-custom-img" ).removeClass( "hidden" );
			$( ".upload-custom-img" ).addClass( "hidden" );
			tb_remove();


			// Set normal uploader for editor
			window.send_to_editor = window.original_send_to_editor;
		};
	}

	function remove_img( button ){

		jQuery( button ).click( function(){

			$( ".custom-img-container" ).children( "img" ).remove();

			$( ".afi-img-id" ).val("");
			 
			$( ".delete-custom-img" ).addClass( "hidden" );

			$( ".upload-custom-img" ).removeClass( "hidden" );

			return false;

		});

	}

	set_uploader(".upload-custom-img", ".afi-img-id");
	remove_img(".delete-custom-img");

    
});
