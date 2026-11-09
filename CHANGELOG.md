## 3.2.3 (2026-11-09)

- Never bundle assets in a later printing burst after the bundle has already been printed in the same request. WordPress cannot re-register the bundle handle with a new URL, so bundling again marked the handles as done while their combined stylesheet or script was never printed, silently dropping them. Assets that arrive late now print as-is instead.
  
  Also clears the CSS queue between printing bursts so late bursts start from clean state and stop re-merging handles from earlier bursts.

## 3.2.2 (2026-11-09)

- Place the CSS bundle in the print order at the position of its first bundled stylesheet instead of always printing it first. Stylesheets excluded from the bundle (with inline "after" CSS, RTL variants, print media queries or conditionals) that were enqueued before bundled ones — a parent theme stylesheet whose child theme gets bundled, for example — now cascade before the bundle instead of after it. Previously they printed after the bundle, which let their rules override bundled styles such as `prefers-color-scheme: dark` rules, turning dark mode off.

## 3.2.1 (2026-10-29)

- Handle styles excluded from the bundle by printing them separately instead of silently dropping them. This affects stylesheets with inline "after" CSS and those with non-screen media queries (or conditional attributes): they are now printed by WordPress in their original position, with their inline styles intact and in the correct order.
- Adopt changeset-driven releases with @wpelevator/wp-release: automated version bumps, draft GitHub releases and standalone ZIP packages with an embedded checksums manifest. Releases are distributed as self-hosted downloads; the plugin is not published to the WordPress.org repository.

