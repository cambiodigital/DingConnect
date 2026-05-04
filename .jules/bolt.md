# Bolt's Journal

## 2026-04-14 - Aggregate Post Meta Counts with Custom SQL
**Learning:** Using multiple `WP_Query` instances to count posts grouped by a meta value causes an N+1 query problem, generating many small queries and high object initialization overhead in WordPress.
**Action:** Replace multiple `WP_Query` loops with a single custom `$wpdb` SQL query using `GROUP BY` and an `IN` clause to retrieve all required aggregate counts efficiently in one round trip.
