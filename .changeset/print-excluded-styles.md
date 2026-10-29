---
"minit": patch
---

Handle styles excluded from the bundle by printing them separately instead of silently dropping them. This affects stylesheets with inline "after" CSS and those with non-screen media queries (or conditional attributes): they are now printed by WordPress in their original position, with their inline styles intact and in the correct order.
