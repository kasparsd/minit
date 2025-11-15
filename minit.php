<?php
/**
 * Plugin Name: Minit
 * Plugin URI: https://github.com/kasparsd/minit
 * GitHub URI: https://github.com/kasparsd/minit
 * GitHub Plugin URI: https://github.com/kasparsd/minit
 * Update URI: https://updates.wpelevator.com/wp-json/update-pilot/v1/plugins
 * Description: Combine JS and CSS files and serve them from the uploads folder.
 * Version: 3.1.1
 * Author: Kaspars Dambis
 * Author URI: https://kaspars.net
 * Require PHP: 7.4
 */

if ( ! function_exists( 'add_action' ) ) {
	return;
}

// Until we add proper autoloading.
require_once __DIR__ . '/src/minit-assets.php';
require_once __DIR__ . '/src/minit-asset-cache.php';
require_once __DIR__ . '/src/minit-js.php';
require_once __DIR__ . '/src/minit-css.php';
require_once __DIR__ . '/src/minit-plugin.php';
require_once __DIR__ . '/src/minit-admin.php';

add_action( 'plugins_loaded', array( Minit_Plugin::class, 'instance' ) );

/**
 * See the Wiki for other examples https://github.com/kasparsd/minit/wiki
 */

// Prepend the filename of the file being included.
add_filter( 'minit-item-css', 'minit_comment_combined', 15, 3 );
add_filter( 'minit-item-js', 'minit_comment_combined', 15, 3 );

function minit_comment_combined( $content, $asset, $handle ) {
	if ( ! $content ) {
		return $content;
	}

	return sprintf(
		"\n\n/* Minit: %s */\n",
		$asset->registered[ $handle ]->src
	) . $content;
}


// Add table of contents at the top of the Minit file.
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
