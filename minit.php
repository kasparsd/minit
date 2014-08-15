<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 0.9.2
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

$minit_instance = Minit::instance();


class Minit {

	static $instance;


	private function __construct() {
		
		add_filter( 'print_scripts_array', array( $this, 'init_minit_js' ) );
		add_filter( 'print_styles_array', array( $this, 'init_minit_css' ) );

	}


	public static function instance() {

		if ( ! self::$instance )
			self::$instance = new Minit();

		return self::$instance;

	}


	function init_minit_js( $todo ) {

		global $wp_scripts;

		return $this->minit_objects( $wp_scripts, $todo, 'js' );

	}


	function init_minit_css( $todo ) {

		global $wp_styles;

		return $this->minit_objects( $wp_styles, $todo, 'css' );

	}


	function minit_objects( $object, $todo, $extension ) {

		// Don't run if on admin or already processed
		if ( is_admin() || empty( $todo ) )
			return $todo;

		// Allow files to be excluded from Minit
		$minit_exclude = apply_filters( 'minit-exclude-' . $extension, array() );
		$minit_todo = array_diff( $todo, (array) $minit_exclude );

		$done = array();
		$ver = array();

		return $todo;

		// Use different cache key for SSL and non-SSL
		$ver[] = 'is_ssl-' . is_ssl();

		// Use a global cache version key to purge cache
		$ver[] = 'minit_cache_ver-' . get_option( 'minit_cache_ver' );

		// Use script version to generate a cache key
		foreach ( $minit_todo as $t => $script )
			$ver[] = sprintf( '%s-%s', $script, $object->registered[ $script ]->ver );

		$cache_ver = md5( 'minit-' . implode( '-', $ver ) . $extension );

		// Try to get queue from cache
		$cache = wp_parse_args( 
				get_transient( 'minit-' . $cache_ver ),
				array_flip( array( 'cache_ver', 'todo', 'done', 'url', 'file', 'extension' ) )
			);
		
		if ( $cache['cache_ver'] == $cache_ver && file_exists( $cache['file'] ) )
			return $this->minit_enqueue_files( $object, $cache );

		foreach ( $minit_todo as $t => $script ) {

			// Make sure this is a relative URL so that we can check if it is local
			$src = str_replace( $object->base_url, '', $object->registered[ $script ]->src );

			// Skip if the file is not hosted locally
			if ( ! file_exists( ABSPATH . $src ) || empty( $src ) )
				continue;

			$done[ $script ] = apply_filters( 
					'minit-item-' . $extension, 
					file_get_contents( ABSPATH . $src ),
					$object,
					$script
				);

		}

		if ( empty( $done ) )
			return $todo;

		$wp_upload_dir = wp_upload_dir();

		// Try to create the folder for cache
		if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit' ) )
			if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit' ) )
				return $todo;

		$combined_file_path = sprintf( '%s/minit/%s.%s', $wp_upload_dir['basedir'], $cache_ver, $extension );
		$combined_file_url = sprintf( '%s/minit/%s.%s', $wp_upload_dir['baseurl'], $cache_ver, $extension );

		// Allow other plugins to do something with the resulting URL
		$combined_file_url = apply_filters( 'minit-url-' . $extension, $combined_file_url, $done );

		// Allow other plugins to minify and obfuscate
		$done_imploded = apply_filters( 'minit-content-' . $extension, implode( "\n\n", $done ), $done );

		// Store the combined file on the filesystem
		if ( ! file_exists( $combined_file_path ) )
			if ( ! file_put_contents( $combined_file_path, $done_imploded ) )
				return $todo;

		$status = array(
				'cache_ver' => $cache_ver,
				'todo' => $todo,
				'done' => array_keys( $done ),
				'url' => $combined_file_url,
				'file' => $combined_file_path,
				'extension' => $extension
			);

		// Cache this set of scripts for 24 hours
		set_transient( 'minit-' . $cache_ver, $status, 24 * 60 * 60 );

		return $this->minit_enqueue_files( $object, $status );

	}


	function minit_enqueue_files( $object, $status ) {

		extract( $status );

		// Remove scripts that were merged
		$todo = array_diff( $object->queue, $done );

		switch ( $extension ) {
			
			case 'css':

				wp_enqueue_style( 
					'minit-' . $cache_ver, 
					$url, 
					null,
					null
				);

				break;

			case 'js':

				wp_enqueue_script( 
					'minit-' . $cache_ver, 
					$url, 
					null, 
					null,
					apply_filters( 'minit-js-in-footer', true )
				);

				// Make sure that minit JS script is placed either in header/footer
				$object->groups[ 'minit-' . $cache_ver ] = apply_filters( 'minit-js-in-footer', true );

				break;
			
			default:

				return $todo;

				break;

		}

		foreach ( $done as $done_item )
			foreach ( $object->registered[ $done_item ]->extra as $extra_field => $extra_value )
				if ( in_array( $extra_field, array( 'data', 'after' ) ) )
					$object->add_data( 'minit-' . $cache_ver, $extra_field, $extra_value );

		// This is necessary to print this out now
		$todo[] = 'minit-' . $cache_ver;

		// Add remaining elements to the queue
		$object->queue = $todo;

		// Mark these items as done
		$object->done = array_merge( $object->done, $done );

		return $todo;

	}


}


add_filter( 'minit-item-css', 'minit_comment_combined', 10, 3 );
add_filter( 'minit-item-js', 'minit_comment_combined', 10, 3 );

function minit_comment_combined( $content, $object, $script ) {

	return sprintf(
			"\n\n/* Minit: %s */\n", 
			$object->registered[ $script ]->src
		) . $content;

}


add_filter( 'minit-item-css', 'minit_resolve_css_urls', 10, 3 );

function minit_resolve_css_urls( $content, $object, $script ) {

	$src = str_replace( $object->base_url, '', $object->registered[ $script ]->src );

	if ( is_ssl() )
		$object->base_url = str_replace( 'http://', 'https://', $object->base_url );

	return preg_replace( 
			'|url\((?:[\'"]+)*(?!data:)(.*?)(?:[\'"]+)*\)|i', 
			sprintf( "url('%s/$1')", $object->base_url . dirname( $src ) ), 
			$content
		);

}


add_filter( 'minit-url-css', 'minit_maybe_ssl_url' );
add_filter( 'minit-url-js', 'minit_maybe_ssl_url' );

function minit_maybe_ssl_url( $url ) {

	if ( is_ssl() )
		return str_replace( 'http://', 'https://', $url );

	return $url;

}


/**
 * Add a Purge Cache link to the plugin list
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'minit_cache_purge_admin_link' );

function minit_cache_purge_admin_link( $links ) {

	$links[] = sprintf( 
			'<a href="%s">%s</a>', 
			wp_nonce_url( add_query_arg( 'purge_minit', true ), 'purge_minit' ), 
			__( 'Purge cache', 'minit' ) 
		);

	return $links;

}


/**
 * Maybe purge minit cache
 */
add_action( 'admin_init', 'purge_minit_cache' );

function purge_minit_cache() {

	if ( ! isset( $_GET['purge_minit'] ) )
		return;

	if ( ! check_admin_referer( 'purge_minit' ) )
		return;

	// Use this as a global cache version number
	update_option( 'minit_cache_ver', time() );

	add_action( 'admin_notices', 'minit_cache_purged_success' );

	// Allow other plugins to know that we purged
	do_action( 'minit-cache-purged' );

}


function minit_cache_purged_success() {

	printf( 
		'<div class="updated"><p>%s</p></div>', 
		__( 'Success: Minit cache purged.', 'minit' ) 
	);

}


// This can used from cron to delete all Minit cache files
add_action( 'minit-cache-purge-delete', 'minit_cache_delete_files' );

function minit_cache_delete_files() {

	$wp_upload_dir = wp_upload_dir();
	$minit_files = glob( $wp_upload_dir['basedir'] . '/minit/*' );

	if ( $minit_files ) {
		foreach ( $minit_files as $minit_file ) {
			unlink( $minit_file );
		}
	}

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
		// Check if the script is external
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
	<!-- Asynchronous scripts by Minit -->
	<script id="async-scripts" type="text/javascript">
	(function() {
		var js, fjs = document.getElementById('async-scripts'),
			add = function( url, id ) {
				js = document.createElement('script'); 
				js.type = 'text/javascript'; 
				js.src = url; 
				js.async = true; 
				js.id = id;
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

