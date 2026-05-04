# Bolt's Journal

## 2024-04-29 - [Cache Unoptimized API Calls]
**Learning:** Checking caching on API calls is a key optimization technique. In `class-dc-api.php`, some frequently called methods like `get_promotions` were bypassing caching, leading to unnecessary HTTP requests.
**Action:** Always check if get_transient / set_transient is used for API requests that don't need real-time data, and implement it consistently across all data-fetching methods.
## 2026-04-16 - Eliminate Redundant Array Lookups and Expensive Closure Calls in UI Dropdowns
**Learning:** In scenarios where arrays contain duplicate elements that map to identical UI components (such as duplicate `country_iso` codes in an array of bundles), iterating over them using `foreach` and calling an expensive closure for each iteration introduces significant overhead. Functions like `array_column` can extract values at the C-level much faster, and `array_unique` can be used to eliminate duplicate processing altogether.
**Action:** When mapping array values to UI choices, proactively ensure the extraction and de-duplication steps (`array_unique(array_column(...))`) are performed *before* running expensive mapping functions or rendering logic. Additionally, safely cache database option lookups within methods using `static` properties to prevent repeated DB fetches on fallback paths, without altering signatures that might cause caller regressions.
