/* globals jQuery, domainerL10n */
jQuery( function( $ ) {
	var $urls = $( '.domainer-auth-url' ),
		$notice = $( '.domainer-notice' );

	if ( $( 'body' ).hasClass( 'login' ) ) {
		$notice.insertBefore( '#loginform' );
	}

	var completed = 0,
		successCount = 0,
		errorCount = 0;

	function callback( url, result ) {
		completed++;
		var success = result === 'success';

		if ( success ) {
			successCount++;
		} else {
			errorCount++;
		}

		var domain = url.replace( /https?:\/\/(.+?)\/.*/, '$1' );
		$notice.show().append( '<p class="result-' + result + '">' +
			'<span class="dashicons dashicons-' + ( success ? 'yes' : 'no-alt' ) + '"></span> ' +
			domainerL10n[ result ].replace( '%s', '<strong>' + domain + '</strong>' ) +
		'</p>' );

		if ( completed >= $urls.length ) {
			done();
		}
	}

	function done() {
		if ( successCount === completed ) {
			$notice.addClass( 'status-success' );
		} else if ( errorCount === completed ) {
			$notice.addClass( 'status-error' );
		} else {
			$notice.addClass( 'status-warning' );
		}
	}

	$urls.each( function() {
		var url = $( this ).data( 'url' );

		this.onload = function() {
			callback( url, 'success' );
		};

		this.onerror = function() {
			callback( url, 'error' );
		};

		this.src = url;
	} );
} );
