jQuery( document ).ready( function ( $ ) {
	if ( wp.media.view.Attachment.Details.TwoColumn ) {
		// Extend and override the default AttachmentDetails view
		var originalRender = wp.media.view.Attachment.Details.TwoColumn.prototype.render;
		var AttachmentDetailsTwo = wp.media.view.Attachment.Details.TwoColumn.extend( {
			render: function () {
				// Call the original render function
				//OriginalDetailsTwo.render.call(this);
				originalRender.call( this );

				// Add the custom button next to the Alt Text input if it doesn't exist
				if ( ! $( '.custom-alt-button' ).length ) {
					this.$el
						.find( '.setting[data-setting="alt"]' )
						.after( '<span class="setting"><span class="name"></span><button style="float: right" class="button generate-alt-text">Generate Alt</button></span>' );
				}
			},
			events: {
				...wp.media.view.Attachment.Details.TwoColumn.prototype.events,
				'click .generate-alt-text': 'generateAltText',
			},
			async generateAltText(e) {
				this.$el.find( '.generate-alt-text' ).text( 'Generating...' ).prop( 'disabled', true );

				try {
					const response = await wp.apiFetch( {
						path: '/ai/v1/alt-text',
						method: 'POST',
						data: {
							attachment_id: this.model.get( 'id' ),
						},
					} );
					this.$el.find( '.setting[data-setting="alt"] textarea' ).val( response.alt_text );
					this.model.set( 'alt', response.alt_text );
					this.model.save();
				} catch ( e ) {
					alert( JSON.stringify( e ) );
				}

				this.$el.find( '.generate-alt-text' ).text( 'Generate Alt' ).prop( 'disabled', false );
			},
		} );

		// Replace the default AttachmentDetails view with our custom one
		wp.media.view.Attachment.Details.TwoColumn = AttachmentDetailsTwo;
	}
});
