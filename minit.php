<?php
/*
Plugin Name: Minit
Plugin URI: http://konstruktors.com
Description: Combine JS and CSS files and serve them from the upload's folder
Version: 0.5
Author: Kaspars Dambis
Author URI: http://konstruktors.com
*/

if ( ! is_admin() ) {
	add_action( 'print_scripts_array', 'init_minit_js' );
	add_action( 'wp_print_styles', 'init_minit_css' );
}

function init_minit_js( $to_do ) {
	global $wp_scripts;

	$time_start = microtime(true);

	if ( empty( $to_do ) || in_array( 'minit-js', $to_do ) )
		return $to_do;

	$files = array();
	$files_mtime = array();
	$files_content = array();

	// Sort scripts by groups, header scripts first, footer second
	asort( $wp_scripts->groups );

	// Use that order to sort them accordingly
	$to_do = array_keys( $wp_scripts->groups );

	foreach ( $to_do as $script ) {
		if ( ! empty( $wp_scripts->registered[ $script ]->deps ) ) {
			foreach ( $wp_scripts->registered[ $script ]->deps as $dep_script ) {
				$dep_pos = array_search( $dep_script, $to_do );

				if ( $dep_pos !== false ) {
					array_splice( $to_do, $dep_pos, 1, array( $dep_script, $script ) );
					$to_do = array_values( array_unique( $to_do ) );
				}
			}
		}
	}

	foreach ( $to_do as $s => $script ) {
		$src = str_replace( $wp_scripts->base_url, '', $wp_scripts->registered[ $script ]->src );

		if ( ! file_exists( ABSPATH . $src ) )
			continue;

		$files[] = ABSPATH . $src;
		$files_mtime[] = filemtime( ABSPATH . $src );
		$files_content[] = file_get_contents( ABSPATH . $src );

		unset( $to_do[ $s ] );
	}

	if ( empty( $files ) )
		return $to_do;

	$wp_scripts->queue = $to_do;

	$wp_upload_dir = wp_upload_dir();

	if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit/' ) )
		if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit/' ) )
			return $to_do;

	$combined_file_path = $wp_upload_dir['basedir'] . '/minit/' . md5( implode( '', $files_mtime ) ) . '.js';
	$combined_file_url = $wp_upload_dir['baseurl'] . '/minit/' . md5( implode( '', $files_mtime ) ) . '.js';

	if ( ! file_exists( $combined_file_path ) )
		if ( ! file_put_contents( $combined_file_path, implode( ' ', $files_content ) ) )
			return $to_do;

	wp_enqueue_script( 'minit-js', $combined_file_url, null, null, true );

	$time_exec = microtime(true) - $time_start;
	echo "<!-- minit: $time_exec -->";

	return $to_do;
}


function init_minit_css() {
	global $wp_styles;

	if ( empty( $wp_styles->queue ) )
		return;

	$files = array();
	$files_mtime = array();
	$files_content = array();

	foreach ( $wp_styles->queue as $s => $script ) {
		$src = str_replace( $wp_styles->base_url, '', $wp_styles->registered[$script]->src );

		if ( ! file_exists( ABSPATH . $src ) )
			continue;

		$file_content = file_get_contents( ABSPATH . $src );
		$file_content = preg_replace( '/url\(([\'"]?)(?!https?:)(.*?)([\'"]?)\)/i', 'url(' . dirname( $wp_styles->registered[$script]->src ) . '/$2)', $file_content );

		$files[] = ABSPATH . $src;
		$files_mtime[] = filemtime( ABSPATH . $src );
		$files_content[] = $file_content;
	}

	if ( empty( $files ) )
		return;

	$wp_upload_dir = wp_upload_dir();

	if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit/' ) )
		if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit/' ) )
			return;

	$combined_file_path = $wp_upload_dir['basedir'] . '/minit/' . md5( implode( '', $files_mtime ) ) . '.css';
	$combined_file_url = $wp_upload_dir['baseurl'] . '/minit/' . md5( implode( '', $files_mtime ) ) . '.css';

	//if ( ! file_exists( $combined_file_path ) )
		if ( ! file_put_contents( $combined_file_path, implode( ' ', $files_content ) ) )
			return;

	$wp_styles->done = $wp_styles->done + $wp_styles->queue;
	$wp_styles->queue = array();

	wp_enqueue_style( 'minit-css', apply_filters( 'minit_url_js', $combined_file_url ), null, null );
}

add_action( 'admin_init', 'purge_minit_cache' );

function purge_minit_cache() {
	if ( ! isset( $_GET['purge_minit'] ) )
		return;

	$wp_upload_dir = wp_upload_dir();

	foreach ( glob( $wp_upload_dir['basedir'] . '/minit/*.*' ) as $minit_file )
		unlink( $minit_file );

}


/**
 * Print external scripts asynchronously in the footer, using a method similar to Google Analytics
 */

add_action( 'wp_print_footer_scripts', 'add_footer_scripts_async', 5 );

function add_footer_scripts_async() {
	global $wp_scripts;

	$wp_scripts->async = array();

	foreach ( $wp_scripts->queue as $handle ) {
		if ( in_array( $handle, $wp_scripts->in_footer ) && preg_match( '|^(http(s)?\:)?\/\/|i', str_replace( home_url(), '', $wp_scripts->registered[$handle]->src ) ) ) {
			$wp_scripts->async[] = $handle;
			wp_dequeue_script( $handle );
		}
	}
}

add_action( 'wp_print_footer_scripts', 'print_footer_scripts_async', 20 );

function print_footer_scripts_async() {
	global $wp_scripts;

	if ( empty( $wp_scripts->async ) )
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

