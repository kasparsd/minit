<?php

// See the Wiki for other examples https://github.com/kasparsd/minit/wiki

// Prepend the filename of the file being included
add_filter( 'minit-item-css', 'minit_comment_combined', 15, 3 );
add_filter( 'minit-item-js', 'minit_comment_combined', 15, 3 );

function minit_comment_combined( $content, $object, $handle ) {

	if ( ! $content ) {
		return $content;
	}

	return sprintf(
		"\n\n/* Minit: %s */\n",
		$object->registered[ $handle ]->src
	) . $content;

}


// Add table of contents at the top of the Minit file
add_filter( 'minit-content-css', 'minit_add_toc', 100, 2 );
add_filter( 'minit-content-js', 'minit_add_toc', 100, 2 );

function minit_add_toc( $content, $items ) {

	if ( ! $content || empty( $items ) ) {
		return $content;
	}

	$toc = array();

	foreach ( $items as $handle => $item_content ) {
		$toc[] = sprintf( ' - %s', $handle );
	}

	return sprintf( "/* Contents:\n%s\n*/", implode( "\n", $toc ) ) . $content;

}


// Make sure that all Minit files are served from the correct protocol
add_filter( 'minit-url-css', 'minit_maybe_ssl_url' );
add_filter( 'minit-url-js', 'minit_maybe_ssl_url' );

function minit_maybe_ssl_url( $url ) {

	if ( is_ssl() ) {
		return str_replace( 'http://', 'https://', $url );
	}

	return $url;

}
