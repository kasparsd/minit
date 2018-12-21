# Minit for WordPress


## Install

Install [using Composer](https://packagist.org/packages/kasparsd/minit):

    composer require kasparsd/minit

or by manually downloading the [latest release file](https://github.com/kasparsd/minit/releases/latest).


## How it Works

- Concatenates all CSS files and Javascript files one file for each type, and stores them in the WordPress uploads directory under `/minit`. See the configuration section below for how to exclude files from the bundle.

- Uses the combined version numbers of the enqueued assets to version the concatenated files.

- Loads the concatenated Javascript file asynchronously in the footer. This will probably break all inline scripts that rely on jQuery being available. See the configuration section below for how to disable this.

- Loads all external Javascript files asynchronously in the footer of the page.


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


## Minit Addons

- [Minit-Pro](https://github.com/markoheijnen/Minit-Pro)
- [Minit Cron Purge](https://github.com/ryanhellyer/minit-cron-purge)
- [Minit Cache Bump](https://github.com/ryanhellyer/minit-cache-bump)
- [Minit CDN](https://github.com/LQ2-apostrophe/minit-cdn)
- [Minit Manual Inclusion](https://github.com/dimadin/minit-manual-inclusion)


## Credits

Created by [Kaspars Dambis](https://kaspars.net) and [contributors](https://github.com/kasparsd/minit/graphs/contributors).
