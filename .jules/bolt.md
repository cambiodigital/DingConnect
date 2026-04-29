# Bolt's Journal

## 2024-04-29 - [Cache Unoptimized API Calls]
**Learning:** Checking caching on API calls is a key optimization technique. In `class-dc-api.php`, some frequently called methods like `get_promotions` were bypassing caching, leading to unnecessary HTTP requests.
**Action:** Always check if get_transient / set_transient is used for API requests that don't need real-time data, and implement it consistently across all data-fetching methods.
