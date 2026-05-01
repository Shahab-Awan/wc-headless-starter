# Examples

Optional one-shot scripts for common migration cleanup jobs. They are not
part of the canonical deploy flow. Read each script before use and test on
a staging copy first.

## Contents

| File | What it does | Use case |
|---|---|---|
| `cleanup-orphan-order-items.php` | Deletes stale `woocommerce_order_items` / `woocommerce_order_itemmeta` rows whose `order_id` no longer exists in HPOS, preventing recycled order IDs from inheriting dead line items | imported order-history cleanup |
| `strip-shopify-title-attr.php` | Removes `<span>` wrappers Shopify adds around variant titles in product descriptions | Same |
| `review-count-rebuild.php` | Rebuilds `_wc_review_count` + `_wc_average_rating` postmeta for all products (useful after bulk review imports or `wp_comments` surgery) | Post-migration review re-sync |

## How to use

1. Read the script. Each has a top-of-file comment explaining assumptions.
2. Copy into `scripts/` on a target site (they all expect to run from the site root).
3. Run under `wp eval-file` or by direct CLI invocation, depending on the script.
4. Verify results before moving on.

None of these are idempotent across different datasets — run on a staging
copy first, diff the DB, then apply to prod.
