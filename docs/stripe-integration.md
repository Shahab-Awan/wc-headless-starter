# Stripe Integration Notes

WCHS does not include a canonical Stripe plugin. Checkout is native
WooCommerce, so Stripe support is a per-site gateway decision made in
wp-admin.

## Current Contract

- WooCommerce is the only universal plugin dependency.
- The selected Stripe gateway must be installed and configured by the
  site owner or launch engineer.
- WCHS offline gateways live in `wp/mu-plugins/headless-offline-gateways.php`.
- WCHS one-click upsells live in `wp/mu-plugins/headless-one-click-upsell.php`.
- For card-based one-click upsells, the chosen gateway must store reusable
  customer/payment method metadata on the order. If it does not, the upsell
  engine must decline the add-on safely and leave the original order intact.

## Site Setup

1. Install the Stripe gateway plugin chosen for the site.
2. Configure test keys in `wp-admin -> WooCommerce -> Settings -> Payments`.
3. Configure the webhook endpoint in Stripe Dashboard using the endpoint
   required by that plugin.
4. Place live keys only after a full test checkout succeeds.
5. Confirm mobile checkout on iOS Safari and Android Chrome before launch.

## WCHS Upsell Expectations

Offline gateways are first-class: accepting an upsell adds the item to the
order and updates the balance/instructions.

Card gateways are conditional: accepting an upsell can only charge the
customer automatically when the gateway has saved reusable payment method
metadata. If metadata is absent or the card requires off-session
authentication, WCHS records an order note and does not disturb the original
order.

## Production Checklist

- Real test order succeeds through checkout.
- Webhook endpoint is reachable and verified by the Stripe Dashboard.
- Order status transitions are correct after payment.
- Customer receives exactly one final order email after upsell accept,
  decline, or timeout.
- Refund path is exercised in wp-admin and reflected in Stripe.
- Payment method labels and customer-facing checkout copy match the live
  gateway.
- Domain cutover checklist has been run before switching keys or webhooks.
