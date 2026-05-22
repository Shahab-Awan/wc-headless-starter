<?php
declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Aura-style checkout chrome: centered logo header, reservation timer,
 * sidebar trust grid, guarantee callout, and review carousel.
 */
class CheckoutEnhancements {

	public function register(): void {
		add_filter( 'body_class', [ $this, 'body_class' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 1000 );
		add_action( 'wp', [ $this, 'relocate_checkout_payment' ] );
		add_filter( 'woocommerce_checkout_order_review_heading', [ $this, 'order_review_heading' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'render_top_chrome' ], 5 );
		// Inside #order_review: after the order table, before payment (which we move left).
		add_action( 'woocommerce_review_order_after_order_table', [ $this, 'render_sidebar_extras' ], 10 );
	}

	/**
	 * Aura layout: payment + place order live in the left column with billing/shipping.
	 */
	public function relocate_checkout_payment(): void {
		if ( ! $this->is_enhanced_checkout() ) {
			return;
		}
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
		add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'render_payment_column' ], 20 );
	}

	public function render_payment_column(): void {
		if ( ! $this->is_enhanced_checkout() || ! function_exists( 'woocommerce_checkout_payment' ) ) {
			return;
		}
		echo '<div class="wchs-checkout-payment-column">';
		woocommerce_checkout_payment();
		echo '</div>';
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( $this->is_enhanced_checkout() ) {
			$classes[] = 'wchs-checkout-enhanced';
		}
		return $classes;
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_enhanced_checkout() ) {
			return;
		}
		wp_enqueue_style(
			'wchs-ds-checkout',
			WCHS_DS_URL . '/assets/checkout-enhancements.css',
			[ 'wchs-ds-wc-overrides' ],
			WCHS_DS_VERSION
		);
		wp_enqueue_script(
			'wchs-ds-checkout',
			WCHS_DS_URL . '/assets/checkout-enhancements.js',
			[],
			WCHS_DS_VERSION,
			true
		);
	}

	public function order_review_heading(): string {
		return __( 'Your order', 'wchs' );
	}

	public function render_top_chrome(): void {
		if ( ! $this->is_enhanced_checkout() ) {
			return;
		}
		$spa_url  = $this->spa_url();
		$brand    = esc_html( get_bloginfo( 'name' ) );
		$logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$badge    = esc_html( $this->happy_customers_label() );
		?>
		<div class="wchs-checkout-hero" aria-label="<?php esc_attr_e( 'Checkout', 'wchs' ); ?>">
			<a class="wchs-checkout-hero__brand" href="<?php echo esc_url( $spa_url ); ?>">
				<?php if ( $logo_url ) : ?>
					<img class="wchs-checkout-hero__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo $brand; ?>" width="160" height="48" decoding="async" />
				<?php else : ?>
					<span class="wchs-checkout-hero__name"><?php echo $brand; ?></span>
				<?php endif; ?>
			</a>
			<div class="wchs-checkout-hero__badge">
				<span class="wchs-checkout-hero__avatars" aria-hidden="true">
					<span></span><span></span><span></span>
				</span>
				<span class="wchs-checkout-hero__badge-text"><?php echo $badge; ?></span>
			</div>
			<ul class="wchs-checkout-hero__trust" aria-label="<?php esc_attr_e( 'Store guarantees', 'wchs' ); ?>">
				<li><?php echo $this->icon( 'shipping' ); ?><span><?php esc_html_e( 'Fast shipping', 'wchs' ); ?></span></li>
				<li><?php echo $this->icon( 'shield' ); ?><span><?php esc_html_e( '30-day guarantee', 'wchs' ); ?></span></li>
				<li><?php echo $this->icon( 'lock' ); ?><span><?php esc_html_e( 'Secure checkout', 'wchs' ); ?></span></li>
			</ul>
		</div>
		<div class="wchs-checkout-timer" role="status" aria-live="polite">
			<?php echo $this->icon( 'clock' ); ?>
			<p class="wchs-checkout-timer__text">
				<?php esc_html_e( 'Due to high demand, your order is reserved for', 'wchs' ); ?>
				<strong class="wchs-checkout-timer__value" data-wchs-checkout-timer>6:00</strong>
			</p>
		</div>
		<?php
	}

	public function render_sidebar_extras(): void {
		if ( ! $this->is_enhanced_checkout() ) {
			return;
		}
		$reviews = $this->reviews();
		?>
		<div class="wchs-checkout-sidebar" aria-label="<?php esc_attr_e( 'Checkout highlights', 'wchs' ); ?>">
			<div class="wchs-checkout-trust-grid">
				<div class="wchs-checkout-trust-grid__item">
					<?php echo $this->icon( 'lab' ); ?>
					<span><?php esc_html_e( '3rd party tested', 'wchs' ); ?></span>
				</div>
				<div class="wchs-checkout-trust-grid__item">
					<?php echo $this->icon( 'lock' ); ?>
					<span><?php esc_html_e( 'Secure checkout', 'wchs' ); ?></span>
				</div>
				<div class="wchs-checkout-trust-grid__item">
					<?php echo $this->icon( 'shipping' ); ?>
					<span><?php esc_html_e( 'Fast shipping', 'wchs' ); ?></span>
				</div>
				<div class="wchs-checkout-trust-grid__item">
					<?php echo $this->icon( 'support' ); ?>
					<span><?php esc_html_e( 'US-based support', 'wchs' ); ?></span>
				</div>
			</div>

			<div class="wchs-checkout-guarantee">
				<div class="wchs-checkout-guarantee__icon" aria-hidden="true">
					<?php echo $this->icon( 'shield' ); ?>
				</div>
				<div class="wchs-checkout-guarantee__body">
					<p class="wchs-checkout-guarantee__title"><?php esc_html_e( 'Satisfaction guaranteed', 'wchs' ); ?></p>
					<p class="wchs-checkout-guarantee__text">
						<?php esc_html_e( 'Every order ships with a Certificate of Analysis. Not satisfied? Contact our US-based support team.', 'wchs' ); ?>
					</p>
				</div>
			</div>

			<div class="wchs-checkout-reviews" data-wchs-reviews>
				<div class="wchs-checkout-reviews__head">
					<p class="wchs-checkout-reviews__label"><?php esc_html_e( 'Customer reviews', 'wchs' ); ?></p>
					<div class="wchs-checkout-reviews__stars" aria-hidden="true">
						<?php for ( $i = 0; $i < 5; $i++ ) : ?>
							<svg viewBox="0 0 20 20" width="14" height="14"><path fill="currentColor" d="M10 1.5l2.47 5.01 5.53.8-4 3.9.94 5.5L10 14.77l-4.94 2.6.94-5.5-4-3.9 5.53-.8L10 1.5z"/></svg>
						<?php endfor; ?>
					</div>
				</div>
				<div class="wchs-checkout-reviews__track" data-wchs-reviews-track>
					<?php foreach ( $reviews as $index => $review ) : ?>
						<article
							class="wchs-checkout-reviews__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-wchs-review-slide
							<?php echo 0 === $index ? '' : ' hidden'; ?>
						>
							<blockquote class="wchs-checkout-reviews__quote">“<?php echo esc_html( $review['quote'] ); ?>”</blockquote>
							<div class="wchs-checkout-reviews__author">
								<span class="wchs-checkout-reviews__avatar" aria-hidden="true"><?php echo esc_html( $review['initial'] ); ?></span>
								<div>
									<p class="wchs-checkout-reviews__name"><?php echo esc_html( $review['name'] ); ?></p>
									<p class="wchs-checkout-reviews__meta"><?php esc_html_e( 'Verified buyer', 'wchs' ); ?></p>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="wchs-checkout-reviews__dots" data-wchs-reviews-dots role="tablist" aria-label="<?php esc_attr_e( 'Review slides', 'wchs' ); ?>">
					<?php foreach ( $reviews as $index => $review ) : ?>
						<button
							type="button"
							class="wchs-checkout-reviews__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-wchs-review-dot="<?php echo esc_attr( (string) $index ); ?>"
							role="tab"
							aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( __( 'Review %d', 'wchs' ), $index + 1 ) ); ?>"
						></button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="wchs-checkout-payments" aria-hidden="true">
				<span class="wchs-checkout-payments__brand">VISA</span>
				<span class="wchs-checkout-payments__brand">MC</span>
				<span class="wchs-checkout-payments__brand">AMEX</span>
				<span class="wchs-checkout-payments__brand">DISC</span>
			</div>
			<p class="wchs-checkout-shipped">
				<strong><?php echo esc_html( $this->orders_shipped_label() ); ?></strong>
				<?php esc_html_e( 'orders shipped & counting', 'wchs' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * @return array<int, array{quote: string, name: string, initial: string}>
	 */
	private function reviews(): array {
		return [
			[
				'quote'   => 'Ordering was straightforward and delivery arrived sooner than I expected. Quality has been consistent every time.',
				'name'    => 'Marcus V.',
				'initial' => 'M',
			],
			[
				'quote'   => 'Support answered my question quickly and the checkout process was simple from start to finish.',
				'name'    => 'Elena P.',
				'initial' => 'E',
			],
			[
				'quote'   => 'Certificate of Analysis on every batch gives me confidence. Packaging was discreet and professional.',
				'name'    => 'Jordan K.',
				'initial' => 'J',
			],
			[
				'quote'   => 'Great experience overall. Fast shipping and clear communication after I placed my order.',
				'name'    => 'Nina S.',
				'initial' => 'N',
			],
		];
	}

	private function happy_customers_label(): string {
		$settings = class_exists( '\WCHS\Admin\AdminPage' )
			? \WCHS\Admin\AdminPage::get_site_settings()
			: [];
		$custom = trim( (string) ( $settings['checkout_happy_customers'] ?? '' ) );
		return $custom !== '' ? $custom : '100,000+ HAPPY CUSTOMERS';
	}

	private function orders_shipped_label(): string {
		$settings = class_exists( '\WCHS\Admin\AdminPage' )
			? \WCHS\Admin\AdminPage::get_site_settings()
			: [];
		$custom = trim( (string) ( $settings['checkout_orders_shipped'] ?? '' ) );
		return $custom !== '' ? $custom : '4,800+';
	}

	private function icon( string $name ): string {
		$paths = [
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'shipping' => '<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
			'shield'   => '<path d="M12 3.5 19 7v5.5c0 4.2-2.9 7.4-7 9-4.1-1.6-7-4.8-7-9V7l7-3.5z"/><path d="m8.5 12 2 2 4.5-4.5"/>',
			'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'lab'      => '<path d="M9 3h6v6l5 9a2 2 0 0 1-1.7 3H5.7a2 2 0 0 1-1.7-3l5-9V3z"/><path d="M9 9h6"/>',
			'support'  => '<path d="M4 6.5A8 8 0 0 1 20 6.5v5a3 3 0 0 1-3 3h-1.2l-1.8 2.2a1 1 0 0 1-1.6-.8V14.5H7a3 3 0 0 1-3-3v-5z"/>',
		];
		$d = $paths[ $name ] ?? $paths['shield'];
		return '<svg class="wchs-checkout-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}

	private function spa_url(): string {
		if ( function_exists( 'wchs_spa_origin' ) ) {
			return wchs_spa_origin();
		}
		if ( defined( 'WCHS_SPA_URL' ) && is_string( WCHS_SPA_URL ) ) {
			return WCHS_SPA_URL;
		}
		return home_url( '/' );
	}

	private function is_enhanced_checkout(): bool {
		if ( function_exists( 'wchs_use_wchs_checkout_enhancements' ) && ! wchs_use_wchs_checkout_enhancements() ) {
			return false;
		}
		return function_exists( 'is_checkout' )
			&& is_checkout()
			&& ! is_wc_endpoint_url( 'order-received' );
	}
}
