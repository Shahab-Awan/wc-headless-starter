#!/usr/bin/env bash
# Install WP core, activate plugins, create a test admin, seed a couple of products.
# Idempotent — safe to re-run.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

# Load credentials from .env. Required — we do not accept defaults for
# admin passwords or DB passwords anywhere in the committed code.
if [[ ! -f "$ROOT/.env" ]]; then
  echo "error: $ROOT/.env not found. Copy .env.example to .env and fill in values." >&2
  exit 1
fi
set -a
# shellcheck disable=SC1091
. "$ROOT/.env"
set +a

: "${WP_SITE_URL:?WP_SITE_URL missing in .env}"
: "${WP_SITE_TITLE:?WP_SITE_TITLE missing in .env}"
: "${WP_ADMIN_USER:?WP_ADMIN_USER missing in .env}"
: "${WP_ADMIN_PASS:?WP_ADMIN_PASS missing in .env}"
: "${WP_ADMIN_EMAIL:?WP_ADMIN_EMAIL missing in .env}"

wp() {
  # Bump memory for WP admin operations (plugin activation, dashboard loads).
  # The default 128M OOMs on anything non-trivial.
  docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp "$@"
}

if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="$WP_SITE_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

wp option update blogdescription "Headless WooCommerce dev starter"
wp rewrite structure '/%postname%/' --hard

wp theme activate headless-shim || true

# Install core plugins from the wp.org repo. WooCommerce is the only
# universal runtime dependency; payment gateways are per-site choices.
wp plugin install woocommerce --activate || true

# WC setup minimums
wp option update woocommerce_store_address "1 Dev Lane"
wp option update woocommerce_store_city "Localhost"
wp option update woocommerce_default_country "US:CA"
wp option update woocommerce_store_postcode "94110"
wp option update woocommerce_currency "USD"
wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json

# Disable WC "Coming Soon" launch mode — enabled by default for new stores,
# blocks guest visits to /checkout with a maintenance page.
wp option update woocommerce_coming_soon "no"
wp option update woocommerce_store_pages_only "no"

# Seed products if the catalog is empty
if [[ "$(wp post list --post_type=product --format=count)" == "0" ]]; then
  # Two simple products
  wp wc product create --name="Canvas Tote" --regular_price="49.00" --sku="WCHS-001" --type=simple --manage_stock=true --stock_quantity=100 --user=admin
  wp wc product create --name="Desk Lamp" --regular_price="129.00" --sku="WCHS-002" --type=simple --manage_stock=true --stock_quantity=50 --user=admin

  # Additional simple products for shop/filter testing
  wp wc product create --name="Travel Mug" --regular_price="69.00" --sku="WCHS-003" --type=simple --manage_stock=true --stock_quantity=80 --user=admin
  wp wc product create --name="Notebook Set" --regular_price="199.00" --sku="WCHS-004" --type=simple --manage_stock=true --stock_quantity=25 --user=admin
  wp wc product create --name="Utility Pouch" --regular_price="89.00" --sku="WCHS-005" --type=simple --manage_stock=true --stock_quantity=40 --user=admin
  wp wc product create --name="Cable Organizer" --regular_price="39.00" --sku="WCHS-006" --type=simple --manage_stock=true --stock_quantity=120 --user=admin
fi

# Variable product with 2 attributes and 4 variations (one out of stock).
# Idempotent — skipped if already present.
VARIABLE_EXISTS=$(wp eval 'echo ( get_page_by_path( "variable-test-backpack", OBJECT, "product" ) || wc_get_product_id_by_sku( "WCHS-VAR-001" ) ) ? "1" : "0";' 2>/dev/null || echo 0)
if [[ "$VARIABLE_EXISTS" == "0" ]]; then
  # 1) Create a variable product (requires parent set up before variations)
  PARENT_ID=$(wp wc product create \
    --name="Variable Test Backpack" \
    --slug="variable-test-backpack" \
    --type=variable \
    --sku="WCHS-VAR-001" \
    --porcelain \
    --user=admin)
  echo "created variable parent id=$PARENT_ID"

  # 2) Attach attributes (via wp-cli WC attributes add to product)
  wp wc product update "$PARENT_ID" \
    --attributes='[{"name":"Size","position":0,"visible":true,"variation":true,"options":["Small","Medium","Large"]},{"name":"Color","position":1,"visible":true,"variation":true,"options":["Black","Natural"]}]' \
    --user=admin

  # 3) Create variations. Note: custom (non-taxonomy) attributes use the
  # display name, lowercased, as the attribute key in the variation. WC
  # normalizes "Size" → "size" when storing variation attributes.
  wp wc product_variation create "$PARENT_ID" --regular_price="59.00" --sku="WCHS-VAR-S-BLK" --attributes='[{"name":"Size","option":"Small"},{"name":"Color","option":"Black"}]' --manage_stock=true --stock_quantity=30 --user=admin
  wp wc product_variation create "$PARENT_ID" --regular_price="79.00" --sku="WCHS-VAR-S-NAT" --attributes='[{"name":"Size","option":"Small"},{"name":"Color","option":"Natural"}]' --manage_stock=true --stock_quantity=20 --user=admin
  wp wc product_variation create "$PARENT_ID" --regular_price="109.00" --sku="WCHS-VAR-M-BLK" --attributes='[{"name":"Size","option":"Medium"},{"name":"Color","option":"Black"}]' --manage_stock=true --stock_quantity=15 --user=admin
  wp wc product_variation create "$PARENT_ID" --regular_price="139.00" --sku="WCHS-VAR-M-NAT" --attributes='[{"name":"Size","option":"Medium"},{"name":"Color","option":"Natural"}]' --manage_stock=true --stock_quantity=0 --user=admin
  wp wc product_variation create "$PARENT_ID" --regular_price="209.00" --sku="WCHS-VAR-L-NAT" --attributes='[{"name":"Size","option":"Large"},{"name":"Color","option":"Natural"}]' --manage_stock=true --stock_quantity=5  --user=admin
fi

# ---------------------------------------------------------------------------
# Product images — attach picsum placeholders so the SPA doesn't render empty
# placeholder icons. Only runs if the target product has no featured image.
# Idempotent: skips any product that already has an image.
# ---------------------------------------------------------------------------
if ! wp eval 'echo wc_get_product(23) && wc_get_product(23)->get_image_id() ? "hasimg" : "noimg";' 2>/dev/null | grep -q hasimg; then
  echo "seeding product images..."
  # Stage images inside the wpcli container via picsum
  docker compose exec -T -u 0:0 wpcli sh -c '
    mkdir -p /tmp/wchs-images
    cd /tmp/wchs-images
    for i in 1 2 3 4 5 6; do
      if [ ! -f "wchs-${i}.jpg" ]; then
        curl -sL "https://picsum.photos/seed/wchs-${i}/800/800" -o "wchs-${i}.jpg" || true
      fi
    done
  '
  wp eval '
    $map = [
      23 => "/tmp/wchs-images/wchs-1.jpg",
      22 => "/tmp/wchs-images/wchs-2.jpg",
      21 => "/tmp/wchs-images/wchs-3.jpg",
      20 => "/tmp/wchs-images/wchs-4.jpg",
      12 => "/tmp/wchs-images/wchs-5.jpg",
      13 => "/tmp/wchs-images/wchs-6.jpg",
    ];
    require_once ABSPATH . "wp-admin/includes/image.php";
    require_once ABSPATH . "wp-admin/includes/file.php";
    require_once ABSPATH . "wp-admin/includes/media.php";
    foreach ($map as $pid => $file) {
      if (!file_exists($file)) continue;
      $p = wc_get_product($pid);
      if (!$p || $p->get_image_id()) continue;
      $upload = wp_upload_bits(basename($file), null, file_get_contents($file));
      if ($upload["error"]) continue;
      $aid = wp_insert_attachment([
        "post_mime_type" => "image/jpeg",
        "post_title"     => $p->get_name(),
        "post_status"    => "inherit",
      ], $upload["file"], $pid);
      wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $upload["file"]));
      $p->set_image_id($aid);
      $p->save();
    }
  '
fi

# ---------------------------------------------------------------------------
# CRO: tier pricing + WC cross-sells
#
# - Tier Pricing Table (free tier forces fixed prices): apply 3 tiers to
#   every simple/variable product. qty 2 = -5%, qty 4 = -10%, qty 8 = -15%.
#   Applies server-side in cart totals so the SPA picks it up via Store API
#   with zero code changes.
# - Cross-sells: round-robin pick 3 other products per product. Populates
#   the `cross_sell_ids` post meta so the SPA (or a future endpoint) can
#   render a "you might also like" strip.
# Both are idempotent — re-running overwrites with the same values.
# ---------------------------------------------------------------------------
wp eval '
$pct = [2 => 0.95, 4 => 0.90, 8 => 0.85];
$products = wc_get_products([
    "status" => "publish",
    "limit"  => -1,
    "type"   => ["simple", "variable"],
]);
$ids = array_map(fn($p) => $p->get_id(), $products);
foreach ($products as $i => $p) {
    $id    = $p->get_id();
    $price = (float) $p->get_regular_price();
    if ($price > 0) {
        $rules = [];
        foreach ($pct as $qty => $mult) {
            $rules[$qty] = round($price * $mult, 2);
        }
        update_post_meta($id, "_tiered_price_rules_type", "fixed");
        update_post_meta($id, "_fixed_price_rules", $rules);
    }
    $pool  = array_values(array_filter($ids, fn($x) => $x !== $id));
    $picks = [];
    for ($j = 0; $j < 3 && $j < count($pool); $j++) {
        $picks[] = $pool[($i + $j) % count($pool)];
    }
    $p->set_cross_sell_ids($picks);
    $p->save();
}
echo "applied tier pricing + cross-sells to " . count($products) . " products\n";
'

echo
echo "==========================================="
echo " wp admin:   ${WP_SITE_URL}/wp-admin"
echo " user:       ${WP_ADMIN_USER}"
echo " pass:       (see .env — WP_ADMIN_PASS)"
echo " spa:        http://localhost:5175 (after npm run dev)"
echo "==========================================="
