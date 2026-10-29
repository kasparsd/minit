---
"minit": patch
---

Exclude stylesheets with RTL variants from the bundle so their `-rtl.css` file can be printed by WordPress, and include the per-asset source, print attributes and extra data in the bundle cache keys so that changed print behavior (like newly added inline styles without a version bump) is not served from a stale bundle.
