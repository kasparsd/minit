<?php

// Prepend the filename of the file being included
add_filter( 'minit-item-css', 'minit_comment_combined', 15, 3 );
add_filter( 'minit-item-js', 'minit_comment_combined', 15, 3 );

function minit_comment_combined( $content, $object, $script ) {

	if ( ! $content )
		return $content;

	return sprintf(
			"\n\n/* Minit: %s */\n",
			$object->registered[ $script ]->src
		) . $content;

}


// Add table of contents at the top of the Minit file
add_filter( 'minit-content-css', 'minit_add_toc', 100, 2 );
add_filter( 'minit-content-js', 'minit_add_toc', 100, 2 );

function minit_add_toc( $content, $items ) {

	if ( ! $content || empty( $items ) )
		return $content;

	$toc = array();

	foreach ( $items as $handle => $item_content )
		$toc[] = sprintf( ' - %s', $handle );

	return sprintf( "/* Contents:\n%s\n*/", implode( "\n", $toc ) ) . $content;

}


// Turn all local asset URLs into absolute URLs
add_filter( 'minit-item-css', 'minit_resolve_css_urls', 10, 3 );

function minit_resolve_css_urls( $content, $object, $script ) {

	if ( ! $content )
		return $content;

	$src = Minit::get_asset_relative_path(
			$object->base_url,
			$object->registered[ $script ]->src
		);

	// Make all local asset URLs absolute
	$content = preg_replace(
			'/url\(["\' ]?+(?!data:|https?:|\/\/)(.*?)["\' ]?\)/i',
			sprintf( "url('%s/$1')", $object->base_url . dirname( $src ) ),
			$content
		);

	return $content;

}


// Add support for relative CSS imports
add_filter( 'minit-item-css', 'minit_resolve_css_imports', 10, 3 );

function minit_resolve_css_imports( $content, $object, $script ) {

	if ( ! $content )
		return $content;

	$src = Minit::get_asset_relative_path(
			$object->base_url,
			$object->registered[ $script ]->src
		);

	// Make all import asset URLs absolute
	$content = preg_replace(
			'/@import\s+(url\()?["\'](?!https?:|\/\/)(.*?)["\'](\)?)/i',
			sprintf( "@import url('%s/$2')", $object->base_url . dirname( $src ) ),
			$content
		);

	return $content;

}


// Exclude styles with media queries from being included in Minit
add_filter( 'minit-item-css', 'minit_exclude_css_with_media_query', 10, 3 );

function minit_exclude_css_with_media_query( $content, $object, $script ) {

	if ( ! $content )
		return $content;

	$whitelist = array( '', 'all', 'screen' );

	// Exclude from Minit if media query specified
	if ( ! in_array( $object->registered[ $script ]->args, $whitelist ) )
		return false;

	return $content;

}


// Make sure that all Minit files are served from the correct protocol
add_filter( 'minit-url-css', 'minit_maybe_ssl_url' );
add_filter( 'minit-url-js', 'minit_maybe_ssl_url' );

function minit_maybe_ssl_url( $url ) {

	if ( is_ssl() )
		return str_replace( 'http://', 'https://', $url );

	return $url;

}
