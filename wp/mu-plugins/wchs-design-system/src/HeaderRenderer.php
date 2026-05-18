<?php
declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

class HeaderRenderer {

	private static bool $rendered = false;

	public function register(): void {
		add_action( 'wp_body_open', [ $this, 'render' ], 1 );
		add_action( 'login_header', [ $this, 'render' ], 1 );
		// Our custom upsell offer page uses get_header() which fires
		// wp_body_open normally.
	}

	public function render(): void {
		if ( self::$rendered || is_admin() ) {
			return;
		}
		if (
			function_exists( 'is_checkout' )
			&& is_checkout()
			&& ! is_wc_endpoint_url( 'order-received' )
		) {
			return;
		}
		self::$rendered = true;

		$spa_url  = $this->spa_url();
		$brand    = esc_html( get_bloginfo( 'name' ) );
		$logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : null;
		$cart_url = esc_url( add_query_arg( 'open_cart', '1', $spa_url . '/shop' ) );

		// Read header links from config (same source as SPA)
		$site_settings = class_exists( '\WCHS\Admin\AdminPage' )
			? \WCHS\Admin\AdminPage::get_site_settings()
			: [];
		$header_links = $site_settings['header_links'] ?? [
			[ 'label' => 'Home', 'url' => '/' ],
			[ 'label' => 'Shop', 'url' => '/shop' ],
			[ 'label' => 'About', 'url' => '/about' ],
			[ 'label' => 'COA Library', 'url' => '/coa-library' ],
			[ 'label' => 'Contact', 'url' => '/contact' ],
			[ 'label' => 'Account', 'url' => '/account' ],
		];

		// Mobile header settings
		$hamburger_side = $site_settings['mobile_hamburger_side'] ?? 'right';
		if ( ! in_array( $hamburger_side, [ 'left', 'right', 'off' ], true ) ) {
			$hamburger_side = 'right';
		}
		$show_toggle       = ! empty( $site_settings['header_show_toggle'] )
			&& ! empty( $site_settings['features_dark_mode'] );
		$toggle_mobile_pin = ! empty( $site_settings['header_toggle_mobile_pin'] );
		$cart_mobile_pin   = $site_settings['header_cart_mobile_pin'] ?? true;
		$toggle_accent     = $site_settings['header_toggle_accent'] ?? true;
		$cart_accent       = $site_settings['header_cart_accent'] ?? true;

		// Split items into pinned / drawer. Pinned cap = 3, overflow to drawer.
		$MAX_PINNED = 3;
		$pinned_all = [];
		$drawer_items = [];
		foreach ( $header_links as $hl ) {
			if ( ! empty( $hl['mobile_pin'] ) ) {
				$pinned_all[] = [ 'kind' => 'link', 'data' => $hl ];
			} else {
				$drawer_items[] = [ 'kind' => 'link', 'data' => $hl ];
			}
		}
		if ( $show_toggle && $toggle_mobile_pin ) {
			$pinned_all[] = [ 'kind' => 'toggle' ];
		} elseif ( $show_toggle ) {
			$drawer_items[] = [ 'kind' => 'toggle' ];
		}
		if ( $cart_mobile_pin ) {
			$pinned_all[] = [ 'kind' => 'cart' ];
		} else {
			$drawer_items[] = [ 'kind' => 'cart' ];
		}
		$pinned_items = array_slice( $pinned_all, 0, $MAX_PINNED );
		$overflow     = array_slice( $pinned_all, $MAX_PINNED );
		$drawer_items = array_merge( $drawer_items, $overflow );

		$inverted   = ! empty( $site_settings['header_inverted'] );
		$borderless = ! empty( $site_settings['header_borderless'] );
		$logo_size  = $site_settings['logo_size'] ?? 'standard';
		if ( ! in_array( $logo_size, [ 'compact', 'standard', 'prominent', 'xl' ], true ) ) {
			$logo_size = 'standard';
		}
		$brand_position = $site_settings['brand_position'] ?? 'left';
		if ( ! in_array( $brand_position, [ 'left', 'center', 'nav-center' ], true ) ) {
			$brand_position = 'left';
		}
		$header_cls = 'site-header';
		if ( $inverted ) $header_cls .= ' site-header--inverted';
		if ( $borderless ) $header_cls .= ' site-header--borderless';
		?>
<div class="site-header-stack">
		<?php $this->render_announcement_bar( $site_settings ); ?>
<header class="<?php echo esc_attr( $header_cls ); ?>" data-hamburger-side="<?php echo esc_attr( $hamburger_side ); ?>" data-logo-size="<?php echo esc_attr( $logo_url ? $logo_size : 'none' ); ?>" data-brand-position="<?php echo esc_attr( $brand_position ); ?>">
	<a class="site-header__brand" href="<?php echo esc_url( $spa_url ); ?>">
		<?php if ( $logo_url ) : ?>
			<img class="site-header__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo $brand; ?>" />
		<?php else : ?>
			<?php echo $brand; ?>
		<?php endif; ?>
	</a>
	<nav class="site-header__nav">
		<!-- Full inline nav — desktop always + mobile when hamburger='off' -->
		<div class="site-header__nav-inline">
			<div class="site-header__nav-menu">
				<?php foreach ( $header_links as $hl ) : ?>
					<?php if ( ( $hl['display'] ?? 'text' ) === 'text' ) : ?>
						<?php echo $this->render_item_inline( $hl, $spa_url ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<div class="site-header__nav-actions">
				<?php echo $this->render_search_button( $spa_url ); ?>
				<?php foreach ( $header_links as $hl ) : ?>
					<?php if ( in_array( $hl['display'] ?? 'text', [ 'icon', 'both' ], true ) ) : ?>
						<?php echo $this->render_item_inline( $hl, $spa_url ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $show_toggle ) : ?>
					<span class="<?php echo $toggle_accent ? 'is-accent-toggle' : ''; ?>">
						<?php echo $this->render_theme_toggle(); ?>
					</span>
				<?php endif; ?>
				<a href="<?php echo $cart_url; ?>" class="site-header__cart<?php echo $cart_accent ? ' is-accent' : ''; ?>" aria-label="Open cart in store">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
				</a>
			</div>
		</div>

		<?php if ( $hamburger_side !== 'off' ) : ?>
			<!-- Mobile pinned cluster — max 3 items -->
			<div class="site-header__nav-group--pinned">
				<?php echo $this->render_search_button( $spa_url ); ?>
				<?php foreach ( $pinned_items as $entry ) : ?>
					<?php if ( $entry['kind'] === 'link' ) : ?>
						<?php echo $this->render_item_inline( $entry['data'], $spa_url ); ?>
					<?php elseif ( $entry['kind'] === 'toggle' ) : ?>
						<span class="<?php echo $toggle_accent ? 'is-accent-toggle' : ''; ?>">
							<?php echo $this->render_theme_toggle(); ?>
						</span>
					<?php elseif ( $entry['kind'] === 'cart' ) : ?>
						<a href="<?php echo $cart_url; ?>" class="site-header__cart<?php echo $cart_accent ? ' is-accent' : ''; ?>" aria-label="Open cart in store">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="site-header__burger" aria-label="Open menu" aria-expanded="false" aria-controls="site-drawer" id="wchs-burger">
				<svg class="site-header__burger-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
					<path d="M3 6h18M3 12h18M3 18h18"/>
				</svg>
				<svg class="site-header__burger-close" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
					<path d="M6 6l12 12M18 6L6 18"/>
				</svg>
			</button>
		<?php endif; ?>
	</nav>
</header>
</div>
<?php if ( $hamburger_side !== 'off' ) : ?>
	<div class="site-header-drawer" id="site-drawer" role="dialog" aria-label="Navigation menu" hidden>
		<?php foreach ( $drawer_items as $entry ) : ?>
			<?php if ( $entry['kind'] === 'link' ) :
				$hl      = $entry['data'];
				$hl_url  = $hl['url'] ?? '';
				$icon    = $hl['icon'] ?? '';
				$accent  = ! empty( $hl['accent'] );
				$resolved = ( str_starts_with( $hl_url, '/' ) && ! str_starts_with( $hl_url, '//' ) )
					? esc_url( $spa_url . $hl_url )
					: esc_url( $hl_url );
			?>
				<a class="site-header-drawer__item<?php echo $accent ? ' is-accent' : ''; ?>" href="<?php echo $resolved; ?>">
					<?php if ( $icon ) echo $this->icon_svg( $icon ); ?>
					<span><?php echo esc_html( $hl['label'] ?? '' ); ?></span>
				</a>
			<?php elseif ( $entry['kind'] === 'toggle' ) : ?>
				<div class="site-header-drawer__item<?php echo $toggle_accent ? ' is-accent' : ''; ?>">
					<?php echo $this->render_theme_toggle(); ?>
					<span>Theme</span>
				</div>
			<?php elseif ( $entry['kind'] === 'cart' ) : ?>
				<a class="site-header-drawer__item<?php echo $cart_accent ? ' is-accent' : ''; ?>" href="<?php echo $cart_url; ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
					<span>Cart</span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<script>
	(function () {
		var btn = document.getElementById('wchs-burger');
		var drawer = document.getElementById('site-drawer');
		if (!btn || !drawer) return;
		if (document.querySelector('.site-announcement')) {
			document.body.classList.add('has-announcement-bar');
		}
		function syncDrawerTop() {
			var stack = document.querySelector('.site-header-stack');
			if (stack) {
				document.documentElement.style.setProperty(
					'--wchs-header-stack-height',
					stack.getBoundingClientRect().bottom + 'px'
				);
			}
		}
		function setOpen(open) {
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				syncDrawerTop();
				drawer.removeAttribute('hidden');
			} else {
				drawer.setAttribute('hidden', '');
			}
		}
		btn.addEventListener('click', function () {
			setOpen(btn.getAttribute('aria-expanded') !== 'true');
		});
		window.addEventListener('resize', syncDrawerTop);
		drawer.addEventListener('click', function (e) {
			if (e.target.closest('.site-header-drawer__item a, a.site-header-drawer__item')) setOpen(false);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') setOpen(false);
		});
	}());
	</script>
<?php endif; ?>
		<?php
	}

	private function render_search_button( string $spa_url ): string {
		$href = esc_url( add_query_arg( 'open_search', '1', $spa_url . '/shop' ) );
		return '<a href="' . $href . '" class="site-header__icon-link site-header__search-trigger" aria-label="'
			. esc_attr__( 'Search products', 'wchs' ) . '">'
			. $this->icon_svg( 'search' )
			. '</a>';
	}

	private function render_item_inline( array $hl, string $spa_url ): string {
		$hl_url   = $hl['url'] ?? '';
		$display  = $hl['display'] ?? 'text';
		$icon     = $hl['icon'] ?? '';
		$accent   = ! empty( $hl['accent'] );
		$label    = $hl['label'] ?? '';
		$resolved = ( str_starts_with( $hl_url, '/' ) && ! str_starts_with( $hl_url, '//' ) )
			? esc_url( $spa_url . $hl_url )
			: esc_url( $hl_url );
		ob_start();
		if ( $display === 'icon' || $display === 'both' ) : ?>
			<a href="<?php echo $resolved; ?>" class="site-header__icon-link<?php echo $accent ? ' is-accent' : ''; ?>" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php if ( $icon ) echo $this->icon_svg( $icon ); ?>
				<?php if ( $display === 'both' ) : ?><span><?php echo esc_html( $label ); ?></span><?php endif; ?>
			</a>
		<?php else : ?>
			<a href="<?php echo $resolved; ?>" class="site-header__nav-link<?php echo $accent ? ' is-accent' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endif;
		return (string) ob_get_clean();
	}

	private function render_theme_toggle(): string {
		ob_start();
		?>
		<button type="button" id="wchs-theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
			<svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
			</svg>
			<svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
			</svg>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	private function icon_svg( string $name ): string {
		// Must stay in sync with SPA icons.ts and admin icon_svg_paths()
		$icons = [
			'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7.5" r="3.5"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
			'cart'     => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
			'bag'      => '<circle cx="10" cy="20.5" r="1.5"/><circle cx="18" cy="20.5" r="1.5"/><path d="M2.5 2.5h3l2.7 12.4a1.5 1.5 0 0 0 1.5 1.1h7.7a1.5 1.5 0 0 0 1.4-1l2.7-7.2H7.1"/>',
			'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/>',
			'shipping' => '<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
			'lab'      => '<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8"/><path d="M9.1 14.6h5.8"/>',
			'shield'   => '<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
			'heart'    => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
			'star'     => '<path d="M12 3.6l2.3 4.9 5.4.8-3.9 3.8.9 5.3-4.7-2.5-4.8 2.5.9-5.3L4.2 9.3l5.4-.8L12 3.6z"/>',
			'lock'     => '<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>',
			'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'refresh'  => '<path d="M21.5 5v5h-5"/><path d="M2.5 19v-5h5"/><path d="M4.2 9.5a8.5 8.5 0 0 1 14-3l3.3 3.5M2.5 14l3.3 3.5a8.5 8.5 0 0 0 14-3"/>',
			'check'    => '<path d="M5 12.5l4.2 4.2L19 7"/>',
			'leaf'     => '<path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75"/>',
			'gift'     => '<path d="M4 12v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9"/><rect x="2.5" y="7.5" width="19" height="5" rx="1"/><path d="M12 22V7.5"/><path d="M12 7.5H8a2.5 2.5 0 0 1 0-5C11 2.5 12 7.5 12 7.5z"/><path d="M12 7.5h4a2.5 2.5 0 0 0 0-5C13 2.5 12 7.5 12 7.5z"/>',
			'award'    => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/>',
			'globe'    => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5a13 13 0 0 1 3.5 8.5 13 13 0 0 1-3.5 8.5 13 13 0 0 1-3.5-8.5A13 13 0 0 1 12 3.5z"/>',
			'wallet'   => '<rect x="2.5" y="5.5" width="19" height="14.5" rx="1.5"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2" fill="currentColor" stroke="none"/>',
			'zap'      => '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
			'percent'  => '<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.2"/><circle cx="17.5" cy="17.5" r="2.2"/>',
			'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.88.37 1.76.7 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.33 1.73.57 2.61.7A2 2 0 0 1 22 16.92z"/>',
			'package'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
			'thumbsup' => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
			'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
			'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
			'moon'     => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
			'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		];
		$path = $icons[ $name ] ?? '';
		if ( ! $path ) return '';
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
	}

	private function spa_url(): string {
		if ( function_exists( 'wchs_spa_origin' ) ) {
			return wchs_spa_origin();
		}
		if ( defined( 'WCHS_SPA_URL' ) && is_string( WCHS_SPA_URL ) ) {
			return rtrim( WCHS_SPA_URL, '/' );
		}
		return untrailingslashit( home_url( '/' ) );
	}

	private function render_announcement_bar( array $site_settings ): void {
		if ( empty( $site_settings['announcement_bar_enabled'] ) ) {
			return;
		}
		$items = $site_settings['announcement_bar_items'] ?? [];
		if ( ! is_array( $items ) || empty( $items ) ) {
			return;
		}
		$loop = array_merge( $items, $items );
		$check = '<svg class="site-announcement__check" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true">'
			. '<polyline points="2 6 5 9 10 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
			. '</svg>';
		?>
<div class="site-announcement" role="region" aria-label="<?php echo esc_attr__( 'Promotions and shipping', 'wchs' ); ?>">
	<div class="site-announcement__track">
		<?php foreach ( $loop as $item ) : ?>
			<span class="site-announcement__item">
				<?php echo $check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( (string) $item ); ?></span>
			</span>
		<?php endforeach; ?>
	</div>
</div>
		<?php
	}

}
