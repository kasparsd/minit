<?php


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
