jQuery( document ).ready( function ( $ ) {
	$( '#pagemorph_pull_btn' ).on( 'click', function ( e ) {
		e.preventDefault();

		var stagingUrl    = $( '#pagemorph_staging_url' ).val();
		var appUsername   = $( '#pagemorph_app_username' ).val();
		var appPassword   = $( '#pagemorph_app_password' ).val();
		var stagingPostId = $( '#pagemorph_staging_post_id' ).val();
		var localPostId   = pageMorphSyncData.postId;
		var nonce         = $( '#pagemorph_pull_nonce' ).val();

		if ( ! stagingUrl || ! appUsername || ! appPassword || ! stagingPostId ) {
			alert( pageMorphSyncData.i18n.fillFields );
			return;
		}

		if ( ! confirm( pageMorphSyncData.i18n.confirmPrompt ) ) {
			return;
		}

		var $btn    = $( this );
		var $status = $( '#pagemorph_status_message' );

		$btn.prop( 'disabled', true ).text( pageMorphSyncData.i18n.purging );
		$status.css( 'color', '#333' ).text( pageMorphSyncData.i18n.connecting );

		wp.ajax.post( 'pagemorph_pull_content', {
			staging_url:  stagingUrl,
			app_username: appUsername,
			app_password: appPassword,
			staging_id:   stagingPostId,
			local_id:     localPostId,
			_ajax_nonce:  nonce,
		} )
		.done( function ( response ) {
			$status.css( 'color', 'green' ).text( response.message );
			setTimeout( function () { location.reload(); }, 1500 );
		} )
		.fail( function ( response ) {
			$btn.prop( 'disabled', false ).text( pageMorphSyncData.i18n.migrateBtn );
			var errMsg = response && response.message ? response.message : pageMorphSyncData.i18n.unknownError;
			$status.css( 'color', 'red' ).text( pageMorphSyncData.i18n.errorPrefix + errMsg );
		} );
	} );
} );
