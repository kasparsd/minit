## 3.2.1 (2026-10-29)

- Handle styles excluded from the bundle by printing them separately instead of silently dropping them. This affects stylesheets with inline "after" CSS and those with non-screen media queries (or conditional attributes): they are now printed by WordPress in their original position, with their inline styles intact and in the correct order.
- Adopt changeset-driven releases with @wpelevator/wp-release: automated version bumps, draft GitHub releases and standalone ZIP packages with an embedded checksums manifest. Releases are distributed as self-hosted downloads; the plugin is not published to the WordPress.org repository.

