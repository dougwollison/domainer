/* globals jQuery, domainerL10n */
jQuery( function( $ ) {
	// Get the URL scripts, the notice box, and the message template
	var $urls = $( '.domainer-auth-url' ),
		$notice = $( '.domainer-notice' ),
		$template = $( '#domainer_message_template' );

	// Move the notice box to just above the login form
	if ( $( 'body' ).hasClass( 'login' ) ) {
		$notice.insertBefore( '#loginform' );
	}

	// Counts of completed, successfull, and failed attempts
	var completed = 0,
		successCount = 0,
		errorCount = 0;

	// Update the text of the message with the appicable status
	function printMessage( $msg, type ) {
		var domain = $msg.data( 'domain' );
		var message = domainerL10n[ type ].replace( '%s', '<strong>' + domain + '</strong>' );
		$msg.find( '.text' ).html( message );
	}

	// Handle the result; update the message and check if done
	function onResult( $msg, status ) {
		completed++;
		var success = status === 'success';

		if ( success ) {
			successCount++;
		} else {
			errorCount++;
		}

		// Flag and print the message
		$msg.addClass( 'result-' + status );
		printMessage( $msg, status );

		// Call done() if we've completed all URLs
		if ( completed >= $urls.length ) {
			done();
		}
	}

	// When done, set overal status (all success, all error, mixed warning)
	function done() {
		if ( successCount === completed ) {
			$notice.addClass( 'status-success' );
		} else if ( errorCount === completed ) {
			$notice.addClass( 'status-error' );
		} else {
			$notice.addClass( 'status-warning' );
		}
	}

	// Parse the template to real element
	$template = $( $template.html() );

	// Run through each URL and initiate it
	$urls.each( function() {
		// Get the URL for the [src] and the domain from it
		var url = $( this ).data( 'url' );
		var domain = url.replace( /https?:\/\/(.+?)\/.*/, '$1' );

		// Create the message element, store the domain
		var $msg = $template.clone().data( 'domain', domain );

		// Set the load and error event
		this.onload = function() {
			onResult( $msg, 'success' );
		};
		this.onerror = function() {
			onResult( $msg, 'error' );
		};

		// Set the source to trigger the loading of the URL
		this.src = url;

		// Add to the message box, initialize with waiting message
		$msg.appendTo( $notice );
		printMessage( $msg, 'waiting' );
	} );

	// Show the notice box if there are URLs to run through
	if ( $urls.length > 0 ) {
		$notice.show();
	}
} );
