import {
	defineConfig,
	wordpressPluginVersion,
	wordpressStableTag,
} from '@wpelevator/wp-release';

// Single-package repository: the plugin lives at the root, so release tags
// are plain versions (the package path placeholder stays empty). Merge a
// release pull request to master and the tag workflow runs the release.
export default defineConfig( {
	baseBranch: 'master',
	readmeChangelog: true,
	// The plugin readme (readme.txt) keeps a literal "== Changelog ==" heading
	// where the release entries are inserted.
	packages: [
		{
			glob: '.',
			version: {
				from: wordpressPluginVersion(),
				to: [
					wordpressPluginVersion(),
					wordpressStableTag(),
				],
			},
		},
	],
	// Release integrity for `zip`: an unversioned checksums manifest
	// (checksums.json, WordPress.org plugin-checksums format) embedded at the
	// package root of every build. Signing is deferred until a signing key is
	// provisioned, so the manifest ships unsigned for now.
	integrity: {
		file: 'checksums.json',
		embed: true,
	},
} );
