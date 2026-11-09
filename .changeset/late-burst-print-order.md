---
"minit": patch
---

Never bundle assets in a later printing burst after the bundle has already been printed in the same request. WordPress cannot re-register the bundle handle with a new URL, so bundling again marked the handles as done while their combined stylesheet or script was never printed, silently dropping them. Assets that arrive late now print as-is instead.

Also clears the CSS queue between printing bursts so late bursts start from clean state and stop re-merging handles from earlier bursts.
