---
"minit": patch
---

Place the CSS bundle in the print order at the position of its first bundled stylesheet instead of always printing it first. Stylesheets excluded from the bundle (with inline "after" CSS, RTL variants, print media queries or conditionals) that were enqueued before bundled ones — a parent theme stylesheet whose child theme gets bundled, for example — now cascade before the bundle instead of after it. Previously they printed after the bundle, which let their rules override bundled styles such as `prefers-color-scheme: dark` rules, turning dark mode off.
