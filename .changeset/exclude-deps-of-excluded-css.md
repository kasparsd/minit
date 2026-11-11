---
"minit": patch
---

Place the CSS bundle in the print order at the position of its last bundled stylesheet instead of its first. Bundled handles cannot be split around a handle that is printed separately, so the bundle prints as one block; anchoring it at its last member keeps the cascade closest to the enqueue order: stylesheets excluded from the bundle (inline customizer CSS on a parent theme stylesheet, the admin-bar styles when logged in) cascade before the bundle, and anything enqueued after the bundled content cascades after it.

This fixes dark mode when logged in: the excluded admin-bar stylesheet pulls its `dashicons` dependency into the bundle, and with the bundle anchored at that first handle it printed above the parent theme stylesheet whose light mode rules then overrode the bundled `prefers-color-scheme: dark` rules.
