<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder
Version: 0.5.1
Author: Kaspars Dambis
Author URI: http://konstruktors.com
*/

add_filter( 'print_scripts_array', 'init_minit_js' );
add_action( 'wp_print_styles', 'init_minit_css' );

function init_minit_js( $to_do ) {
	global $wp_scripts;

	// Don't run in admin, if no scripts enqueued, or if minit is already included
	if ( is_admin() || empty( $to_do ) || in_array( 'minit-js', $to_do ) )
		return $to_do;

	$files = array();
	$files_mtime = array();
	$in_footer = apply_filters( 'minit_in_footer', true );

	// Sort scripts by groups, header scripts first, footer second
	asort( $wp_scripts->groups );

	// Use that order to sort them accordingly
	$to_do = array_keys( $wp_scripts->groups );
	
	// Resolve all script deps
	foreach ( $to_do as $i => $script )
		if ( ! empty( $wp_scripts->registered[ $script ]->deps ) )
			array_splice( $to_do, $i, 0, $wp_scripts->registered[ $script ]->deps );

	// Remove duplicates, because they were added during dep resolving
	$to_do = array_unique( array_filter( $to_do ) );

	foreach ( $to_do as $s => $script ) {
		// Remove the domain part from the scripts URL
		$src = str_replace( $wp_scripts->base_url, '', $wp_scripts->registered[ $script ]->src );
		
		// Check if this is a local file
		if ( ! file_exists( ABSPATH . $src ) )
			continue;

		$files[] = ABSPATH . $src;
		$files_mtime[] = filemtime( ABSPATH . $src );
		
		// Print extra strings inline
		$wp_scripts->print_scripts_l10n( $script );

		// We are going to include this into our combined scripts, so we remove it from the todo
		unset( $to_do[ $s ] );
	}

	// Check if there are any files that we can combine
	if ( empty( $files ) )
		return $to_do;

	// This should reflect the scripts we are combining into one
	$wp_scripts->queue = $to_do;

	// Create a hash based on minified files and their mtime
	$hash = md5( implode( '-', $files_mtime ) );

	// Check if we can get this from cache
	if ( $url = wp_cache_get( 'minit-js-' . $hash ) ) {
		wp_enqueue_script( 'minit-js', $url, null, null, $in_footer );

		return $to_do;
	}

	// Build the URLs for the combined file
	$wp_upload_dir = wp_upload_dir();
	$combined_file_path = sprintf( '%s/minit/%s.js', $wp_upload_dir['basedir'], $hash );
	$combined_file_url = sprintf( '%s/minit/%s.js', $wp_upload_dir['baseurl'], $hash );

	// Check if cache folder exists and/or can be created
	if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit' ) )
		if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit' ) )
			return $to_do;

	// Check if the combined file exists
	if ( ! file_exists( $combined_file_path ) ) {
		$files_content = array();

		// Get the content of script files
		foreach ( $files as $file )
			$files_content[] = sprintf( "\n\n/* %s */\n", $file ) . file_get_contents( $file );

		if ( ! file_put_contents( $combined_file_path, implode( "\n\n", $files_content ) ) )
			return $to_do;
	}

	wp_enqueue_script( 'minit-js', $combined_file_url, null, null, $in_footer );
	
	// Add our minited script to the queue
	$to_do[] = 'minit-js';
	$wp_scripts->groups[ 'minit-js' ] = $in_footer;

	// Store it into cache
	wp_cache_set( 'minit-js-' . $hash, $combined_file_url );

	return $to_do;
}


function init_minit_css() {
	global $wp_styles;
	
	if ( is_admin() || empty( $wp_styles->queue ) )
		return;

	$files = array();
	$files_mtime = array();
	$files_content = array();

	foreach ( $wp_styles->queue as $s => $script ) {
		$src = str_replace( $wp_styles->base_url, '', $wp_styles->registered[$script]->src );

		if ( ! file_exists( ABSPATH . $src ) )
			continue;

		$file_content = file_get_contents( ABSPATH . $src );
		$file_content = preg_replace( '|url\(\'?"?([a-zA-Z0-9=\?\&\-_\s\./]*)\'?"?\)|i', sprintf( "url('%s/$1')", dirname( $wp_styles->registered[$script]->src ) ), $file_content );

		$files[] = ABSPATH . $src;
		$files_mtime[] = filemtime( ABSPATH . $src );
		$files_content[] = $file_content;

		// Mark these styles as processed
		unset( $wp_styles->queue[$s] );
		$wp_styles->done[] = $script;
	}

	if ( empty( $files ) )
		return;

	$wp_upload_dir = wp_upload_dir();

	if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit' ) )
		if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit' ) )
			return;

	$hash = md5( implode( '-', $files_mtime ) );

	$combined_file_path = sprintf( '%s/minit/%s.css', $wp_upload_dir['basedir'], $hash );
	$combined_file_url = sprintf( '%s/minit/%s.css', $wp_upload_dir['baseurl'], $hash );

	if ( ! file_exists( $combined_file_path ) )
		if ( ! file_put_contents( $combined_file_path, implode( "\n\n", $files_content ) ) )
			return;

	wp_enqueue_style( 'minit-css', apply_filters( 'minit_url_js', $combined_file_url ), null, null );
}


add_action( 'admin_init', 'purge_minit_cache' );

function purge_minit_cache() {
	if ( ! isset( $_GET['purge_minit'] ) )
		return;

	$wp_upload_dir = wp_upload_dir();

	wp_cache_delete( 'minit-js' );
	wp_cache_delete( 'minit-css' );

	foreach ( glob( $wp_upload_dir['basedir'] . '/minit/*' ) as $minit_file )
		unlink( $minit_file );
}


/**
 * Print external scripts asynchronously in the footer, using a method similar to Google Analytics
 */

add_action( 'wp_print_footer_scripts', 'minit_add_footer_scripts_async', 5 );

function minit_add_footer_scripts_async() {
	global $wp_scripts;

	if ( ! is_object( $wp_scripts ) )
		return;

	$wp_scripts->async = array();

	if ( ! isset( $wp_scripts->queue ) || empty( $wp_scripts->queue ) )
		return;

	foreach ( $wp_scripts->queue as $handle ) {
		if ( in_array( $handle, $wp_scripts->in_footer ) && preg_match( '|^(http(s)?\:)?\/\/|i', str_replace( home_url(), '', $wp_scripts->registered[$handle]->src ) ) ) {
			$wp_scripts->async[] = $handle;
			wp_dequeue_script( $handle );
		}
	}
}

add_action( 'wp_print_footer_scripts', 'minit_print_footer_scripts_async', 20 );

function minit_print_footer_scripts_async() {
	global $wp_scripts;

	if ( ! is_object( $wp_scripts ) || empty( $wp_scripts->async ) )
		return;

	?>
	<script id="async-scripts" type="text/javascript">
	(function() {
		var js, fjs = document.getElementById('async-scripts'),
			add = function( url, id ) {
				js = document.createElement('script'); js.type = 'text/javascript'; js.src = url; js.async = true; js.id = id;
				fjs.parentNode.insertBefore(js, fjs);
			};
		<?php 
		foreach ( $wp_scripts->async as $handle )
			printf( 'add("%s", "%s"); ', $wp_scripts->registered[$handle]->src, 'async-script-' . $handle ); 
		?>
	})();
	</script>
	<?php
}

