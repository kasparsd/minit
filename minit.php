<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder
Version: 0.6.1
Author: Kaspars Dambis
Author URI: http://konstruktors.com
*/


add_filter( 'print_scripts_array', 'init_minit_js' );

function init_minit_js( $todo ) {
	global $wp_scripts;
	
	return minit_objects( $wp_scripts, $todo, 'js' );
}


add_filter( 'print_styles_array', 'init_minit_css' );

function init_minit_css( $todo ) {
	global $wp_styles;
	
	return minit_objects( $wp_styles, $todo, 'css' );
}


function minit_objects( $object, $todo, $extension ) {
	if ( is_admin() || empty( $todo ) )
		return $todo;

	$files = array();
	$files_mtime = array();

	foreach ( $todo as $t => $script ) {
		// Make sure this is a relative URL so that we can check if it is local
		$src = str_replace( $object->base_url, '', $object->registered[ $script ]->src );

		// Skip if the file is not hosted locally
		if ( ! file_exists( ABSPATH . $src ) )
			continue;

		$files[ $script ] = apply_filters( 
				'minit-content-' . $extension, 
				file_get_contents( ABSPATH . $src ),
				$object,
				$script
			);

		// Print extra scripts for this item now because we will be removing 
		// this script from the list of items to print.
		if ( method_exists( $object, 'print_extra_script' ) )
			$object->print_extra_script( $script );

		$files_mtime[] = filemtime( ABSPATH . $src );
	}

	if ( empty( $files ) )
		return $todo;

	$wp_upload_dir = wp_upload_dir();

	// Try to create the folder for cache
	if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit' ) )
		if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit' ) )
			return $todo;

	// Generate a unique
	$hash = md5( implode( '-', $files_mtime ) );

	$combined_file_path = sprintf( '%s/minit/%s.%s', $wp_upload_dir['basedir'], $hash, $extension );
	$combined_file_url = sprintf( '%s/minit/%s.%s', $wp_upload_dir['baseurl'], $hash, $extension );

	// Store the combined file on the filesystem
	if ( ! file_exists( $combined_file_path ) )
		if ( ! file_put_contents( $combined_file_path, implode( "\n\n", $files ) ) )
			return $todo;

	// Remove scripts that were merged
	$todo = array_diff( $todo, array_keys( $files ) );

	// Enqueue the minit file
	if ( $extension == 'css' )
		wp_enqueue_style( 
			'minit-' . $extension, 
			apply_filters( 'minit-url-' . $extension, $combined_file_url ), 
			null, 
			null
		);
	else
		wp_enqueue_script( 
			'minit-' . $extension, 
			apply_filters( 'minit-url-' . $extension, $combined_file_url ), 
			null, 
			null,
			apply_filters( 'minit-js-in-footer', true )
		);

	// Set processed minit scripts as done
	// $object->done = array_merge( $object->done, array_keys( $files ) );

	// This is necessary to print this out now
	$todo[] = 'minit-' . $extension;

	// Add remaining elements to the queue
	$object->queue = $todo;

	// Make sure that minit JS script is placed either in header/footer
	if ( $extension == 'js' )
		$object->groups[ 'minit-' . $extension ] = apply_filters( 'minit-js-in-footer', true );

	return $todo;
}


add_filter( 'minit-content-css', 'minit_comment_combined', 10, 3 );
add_filter( 'minit-content-js', 'minit_comment_combined', 10, 3 );

function minit_comment_combined( $content, $object, $script ) {
	return sprintf( 
			"\n\n/* Minit: %s */\n", 
			$object->registered[ $script ]->src
		) . $content;
}


add_filter( 'minit-content-css', 'minit_resolve_css_urls', 10, 3 );

function minit_resolve_css_urls( $content, $object, $script ) {
	$src = str_replace( $object->base_url, '', $object->registered[ $script ]->src );

	return preg_replace( 
			'|url\(\'?"?([a-zA-Z0-9=\?\&\-_\s\./]*)\'?"?\)|i', 
			sprintf( "url('%s/$1')", $object->base_url . dirname( $src ) ), 
			$content
		);
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
		if ( in_array( $handle, $wp_scripts->in_footer ) && preg_match( '|^(https?:)?//|', str_replace( home_url(), '', $wp_scripts->registered[$handle]->src ) ) ) {
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

