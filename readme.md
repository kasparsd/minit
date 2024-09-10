# Minit for WordPress

Places all your CSS and Javascript files into dedicated bundles that can be cached by browsers and re-used between requests. It assumes that a single request with slightly larger transfer size is more performant than multiple smaller requests (even with HTTP/2 multiplexing).

## Install

Install [using Composer](https://packagist.org/packages/kasparsd/minit):

    composer require kasparsd/minit

or by manually downloading the [latest release file](https://github.com/kasparsd/minit/releases/latest).


## How it Works

- Concatenates all CSS files and Javascript files one file for each type (`.js` and `.css`), and stores them in the WordPress uploads directory under `/minit`. See the configuration section below for how to exclude files from the bundle.

- Uses the combined version numbers of the enqueued assets to version the bundles.

- Loads the concatenated Javascript file asynchronously in the footer. This will probably break all inline scripts that rely on jQuery being available. See the configuration section below for how to disable this.


## Screenshots

1. [All CSS files combined in a single file](screenshot-1.png)
2. [All external Javascript files loading asynchronously](screenshot-2.png)


## Configuration

See the [Wiki](https://github.com/kasparsd/minit/wiki) for additional documentation.

### Disable Asynchronous Javascript

Use the `minit-script-tag-async` filter to load the concatenated Javascript synchronously:

    add_filter( 'minit-script-tag-async', '__return_false' );

### Exclude Files

Use the `minit-exclude-js` and `minit-exclude-css` filters to exclude files from the concatenated bundles:

    add_filter( 'minit-exclude-js', function( $handles ) {
        $handles[] = 'jquery';

        return $handles;
    } );

### Integrate with Block Themes

Full block-based themes enqueue the individual stylesheets [only for the blocks that are required for the current request](https://github.com/WordPress/wordpress-develop/blob/b42f5f95417413ee6b05ef389e21b3a0d61d3370/src/wp-includes/global-styles-and-settings.php#L320-L339). This leads to bundles being unique between requests thus defeating the purpose or cache re-use. Use the [`should_load_separate_core_block_assets` filter](https://developer.wordpress.org/reference/hooks/should_load_separate_core_block_assets/) to enqueue a single `block-library` stylesheet instead on all requests:

    add_action(
        'plugins_loaded',
        function () {
            if ( class_exists( 'Minit_Plugin' ) ) {
                // Add late to override the default behaviour.
                add_filter( 'should_load_separate_core_block_assets', '__return_false', 20 );
            }
        },
        100 // Do it after all plugins are loaded.
    );

### Minify CSS

Use this filter to apply basic CSS minification to the created bundle:

    add_filter(
        'minit-content-css',
        function ( $css ) {
                $css = preg_replace( '/[\n\r\t]/mi', ' ', $css ); // Line breaks to spaces.
                $css = preg_replace( '/\s+/mi', ' ', $css ); // Multiple spaces to single spaces.

                return $css;
        },
        5 // Do it before the debug comment in the head.
    );

## Minit Addons

- [Minit-Pro](https://github.com/markoheijnen/Minit-Pro)
- [Minit Cron Purge](https://github.com/ryanhellyer/minit-cron-purge)
- [Minit Cache Bump](https://github.com/ryanhellyer/minit-cache-bump)
- [Minit CDN](https://github.com/LQ2-apostrophe/minit-cdn)
- [Minit Manual Inclusion](https://github.com/dimadin/minit-manual-inclusion)


## Credits

Created by [Kaspars Dambis](https://kaspars.net) and [contributors](https://github.com/kasparsd/minit/graphs/contributors).
