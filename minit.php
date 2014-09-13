<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.0
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

$minit_instance = Minit::instance();

class Minit {

	static $instance;
	private $minit_done = array();
	private $async_queue = array();


	private function __construct() {

		add_filter( 'print_scripts_array', array( $this, 'init_minit_js' ) );
		add_filter( 'print_styles_array', array( $this, 'init_minit_css' ) );

		// Print external scripts asynchronously in the footer
		add_action( 'wp_print_footer_scripts', array( $this, 'async_init' ), 5 );
		add_action( 'wp_print_footer_scripts', array( $this, 'async_print' ), 20 );

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


	function minit_objects( &$object, $todo, $extension ) {

		// Don't run if on admin or already processed
		if ( is_admin() || empty( $todo ) )
			return $todo;

		// Allow files to be excluded from Minit
		$minit_exclude = (array) apply_filters( 'minit-exclude-' . $extension, array() );

		// Exluce all minit items by default
		$minit_exclude = array_merge( $minit_exclude, $this->get_done() );

		$minit_todo = array_diff( $todo, $minit_exclude );

		if ( empty( $minit_todo ) )
			return $todo;

		$done = array();
		$ver = array();

		// Bust cache on Minit plugin update
		$ver[] = 'minit-ver-1.0.0';

		// Debug enable
		// $ver[] = 'debug-' . time();

		// Use different cache key for SSL and non-SSL
		$ver[] = 'is_ssl-' . is_ssl();

		// Use a global cache version key to purge cache
		$ver[] = 'minit_cache_ver-' . get_option( 'minit_cache_ver' );

		// Use script version to generate a cache key
		foreach ( $minit_todo as $t => $script )
			$ver[] = sprintf( '%s-%s', $script, $object->registered[ $script ]->ver );

		$cache_ver = md5( 'minit-' . implode( '-', $ver ) . $extension );

		// Try to get queue from cache
		$cache = get_transient( 'minit-' . $cache_ver );

		if ( isset( $cache['cache_ver'] ) && $cache['cache_ver'] == $cache_ver && file_exists( $cache['file'] ) )
			return $this->minit_enqueue_files( $object, $cache );

		foreach ( $minit_todo as $script ) {

			// Get the relative URL of the asset
			$src = self::get_asset_relative_path(
					$object->base_url,
					$object->registered[ $script ]->src
				);

			// Add support for pseudo packages such as jquery which return src as empty string
			if ( empty( $object->registered[ $script ]->src ) || '' == $object->registered[ $script ]->src )
				$done[ $script ] = null;

			// Skip if the file is not hosted locally
			if ( ! $src || ! file_exists( ABSPATH . $src ) )
				continue;

			$script_content = apply_filters(
					'minit-item-' . $extension,
					file_get_contents( ABSPATH . $src ),
					$object,
					$script
				);

			if ( false !== $script_content )
				$done[ $script ] = $script_content;

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

		$this->set_done( $cache_ver );

		return $this->minit_enqueue_files( $object, $status );

	}


	function minit_enqueue_files( &$object, $status ) {

		extract( $status );

		switch ( $extension ) {

			case 'css':

				wp_enqueue_style(
					'minit-' . $cache_ver,
					$url,
					null,
					null
				);

				// Add inline styles for all minited styles
				foreach ( $done as $script ) {

					$inline_style = $object->get_data( $script, 'after' );

					if ( ! empty( $inline_style ) )
						$object->add_inline_style( 'minit-' . $cache_ver, $inline_style );

				}

				break;

			case 'js':

				wp_enqueue_script(
					'minit-' . $cache_ver,
					$url,
					null,
					null,
					apply_filters( 'minit-js-in-footer', true )
				);

				// Add to the correct
				$object->set_group(
					'minit-' . $cache_ver,
					false,
					apply_filters( 'minit-js-in-footer', true )
				);

				$inline_data = array();

				// Add inline scripts for all minited scripts
				foreach ( $done as $script )
					$inline_data[] = $object->get_data( $script, 'data' );

				// Filter out empty elements
				$inline_data = array_filter( $inline_data );

				if ( ! empty( $inline_data ) )
					$object->add_data( 'minit-' . $cache_ver, 'data', implode( "\n", $inline_data ) );

				break;

			default:

				return $todo;

		}

		// Remove scripts that were merged
		$todo = array_diff( $todo, $done );

		$todo[] = 'minit-' . $cache_ver;

		// Mark these items as done
		$object->done = array_merge( $object->done, $done );

		// Remove Minit items from the queue
		$object->queue = array_diff( $object->queue, $done );

		return $todo;

	}


	function set_done( $handle ) {

		$this->minit_done[] = 'minit-' . $handle;

	}


	function get_done() {

		return $this->minit_done;

	}


	public static function get_asset_relative_path( $base_url, $item_url ) {

		// Remove protocol reference from the local base URL
		$base_url = preg_replace( '/^(https?:\/\/|\/\/)/i', '', $base_url );

		// Check if this is a local asset which we can include
		$src_parts = explode( $base_url, $item_url );

		// Get the trailing part of the local URL
		$maybe_relative = end( $src_parts );

		if ( ! file_exists( ABSPATH . $maybe_relative ) )
			return false;

		return $maybe_relative;

	}


	public function async_init() {

		global $wp_scripts;

		if ( ! is_object( $wp_scripts ) || empty( $wp_scripts->queue ) )
			return;

		$base_url = site_url();
		$minit_exclude = (array) apply_filters( 'minit-exclude-js', array() );

		foreach ( $wp_scripts->queue as $handle ) {

			// Skip asyncing explicitly excluded script handles
			if ( in_array( $handle, $minit_exclude ) ) {
				continue;
			}

			$script_relative_path = Minit::get_asset_relative_path(
				$base_url,
				$wp_scripts->registered[$handle]->src
			);

			if ( ! $script_relative_path ) {
				// Add this script to our async queue
				$this->async_queue[] = $handle;

				// Remove this script from being printed the regular way
				wp_dequeue_script( $handle );
			}

		}

	}


	public function async_print() {

		global $wp_scripts;

		if ( empty( $this->async_queue ) )
			return;

		?>
		<!-- Asynchronous scripts by Minit -->
		<script id="minit-async-scripts" type="text/javascript">
		(function() {
			var js, fjs = document.getElementById('minit-async-scripts'),
				add = function( url, id ) {
					js = document.createElement('script');
					js.type = 'text/javascript';
					js.src = url;
					js.async = true;
					js.id = id;
					fjs.parentNode.insertBefore(js, fjs);
				};
			<?php
			foreach ( $this->async_queue as $handle ) {
				printf(
					'add("%s", "%s"); ',
					$wp_scripts->registered[$handle]->src,
					'async-script-' . esc_attr( $handle )
				);
			}
			?>
		})();
		</script>
		<?php

	}


}


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

	return sprintf( "/* TOC:\n%s\n*/", implode( "\n", $toc ) ) . $content;

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


// Add a Purge Cache link to the plugin list
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

