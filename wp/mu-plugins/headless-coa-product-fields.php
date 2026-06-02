<?php
/**
 * Plugin Name: Headless COA Product Fields
 * Description: COA PDF on WooCommerce products and variations (Media Library + site COA library).
 *              Values power the PDP “Download COA” button via extensions.wchs_cro.
 * Version:     1.1.0
 */

defined( 'ABSPATH' ) || exit;

const WCHS_COA_PARENT_META_KEY = '_wchs_coa_url';

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box(
			'wchs-product-coa',
			__( 'Certificate of Analysis (COA)', 'wchs' ),
			'wchs_coa_render_product_meta_box',
			'product',
			'normal',
			'high'
		);
	},
	30
);

add_action( 'admin_enqueue_scripts', 'wchs_coa_admin_assets' );

add_action( 'woocommerce_admin_process_product_object', 'wchs_coa_save_product', 10, 1 );

add_action( 'woocommerce_product_after_variable_attributes', 'wchs_coa_render_variation_fields', 10, 3 );
add_action( 'woocommerce_save_product_variation', 'wchs_coa_save_variation', 10, 2 );

/**
 * @param string $hook
 */
function wchs_coa_admin_assets( string $hook ): void {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'product' ) {
		return;
	}

	wp_enqueue_media();
	wp_register_script( 'wchs-coa-product-admin', '', [], '1.1.0', true );
	wp_enqueue_script( 'wchs-coa-product-admin' );
	wp_localize_script(
		'wchs-coa-product-admin',
		'wchsCoaAdmin',
		[
			'restUrl' => esc_url_raw( rest_url( 'wchs/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		]
	);
	wp_add_inline_script(
		'wchs-coa-product-admin',
		<<<'JS'
(function () {
	function targetInput(btn) {
		var target = btn.getAttribute('data-target');
		return target ? document.querySelector(target) : null;
	}

	function openMediaPicker(input) {
		if (!input || typeof wp === 'undefined' || !wp.media) return;
		var frame = wp.media({
			title: 'Select COA PDF',
			button: { text: 'Use this file' },
			library: { type: [ 'application/pdf' ] },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			if (attachment && attachment.url) {
				input.value = attachment.url;
				input.dispatchEvent(new Event('input', { bubbles: true }));
			}
		});
		frame.open();
	}

	document.addEventListener('click', function (e) {
		var mediaBtn = e.target.closest('.wchs-coa-pick-media');
		if (mediaBtn) {
			e.preventDefault();
			openMediaPicker(targetInput(mediaBtn));
			return;
		}
		var libBtn = e.target.closest('.wchs-coa-pick-library');
		if (libBtn) {
			e.preventDefault();
			var input = targetInput(libBtn);
			if (input && window.wchsCoaAdmin) {
				openCoaLibraryModal(input);
			}
		}
	});

	var libraryCache = null;

	function openCoaLibraryModal(input) {
		var overlay = document.getElementById('wchs-coa-library-modal');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.id = 'wchs-coa-library-modal';
			overlay.style.cssText =
				'position:fixed;inset:0;z-index:100100;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:24px';
			overlay.innerHTML =
				'<div role="dialog" aria-modal="true" style="background:#fff;max-width:640px;width:100%;max-height:80vh;overflow:auto;border-radius:4px;box-shadow:0 8px 40px rgba(0,0,0,.2)">' +
				'<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #dcdcde">' +
				'<strong>Select COA from library</strong>' +
				'<button type="button" class="button" data-wchs-coa-lib-close>Close</button>' +
				'</div>' +
				'<div data-wchs-coa-lib-body style="padding:12px 16px"></div>' +
				'</div>';
			document.body.appendChild(overlay);
			overlay.addEventListener('click', function (ev) {
				if (ev.target === overlay || ev.target.closest('[data-wchs-coa-lib-close]')) {
					overlay.style.display = 'none';
				}
			});
		}

		var body = overlay.querySelector('[data-wchs-coa-lib-body]');
		if (!body) return;
		body.textContent = 'Loading…';
		overlay.style.display = 'flex';
		overlay._wchsTargetInput = input;

		var finish = function (items) {
			body.innerHTML = '';
			if (!items.length) {
				body.textContent = 'No COA PDFs found on other products yet. Upload a PDF or use Media Library.';
				return;
			}
			var list = document.createElement('ul');
			list.style.cssText = 'margin:0;padding:0;list-style:none';
			items.forEach(function (row) {
				var li = document.createElement('li');
				li.style.cssText = 'border-bottom:1px solid #f0f0f1;padding:10px 0';
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'button button-secondary';
				btn.style.marginRight = '8px';
				btn.textContent = 'Use this COA';
				btn.addEventListener('click', function () {
					input.value = row.url;
					input.dispatchEvent(new Event('input', { bubbles: true }));
					overlay.style.display = 'none';
				});
				var label = document.createElement('span');
				label.textContent = row.label;
				li.appendChild(btn);
				li.appendChild(label);
				list.appendChild(li);
			});
			body.appendChild(list);
		};

		if (libraryCache) {
			finish(libraryCache);
			return;
		}

		fetch(window.wchsCoaAdmin.restUrl + 'coa-library', {
			headers: { 'X-WP-Nonce': window.wchsCoaAdmin.nonce }
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				var flat = [];
				(data.products || []).forEach(function (product) {
					(product.certificates || []).forEach(function (cert) {
						if (!cert.coa_url) return;
						var label = product.name || product.slug || 'Product';
						if (cert.variation_label) {
							label += ' — ' + cert.variation_label;
						}
						if (cert.batch) {
							label += ' (batch ' + cert.batch + ')';
						}
						flat.push({ url: cert.coa_url, label: label });
					});
				});
				libraryCache = flat;
				finish(flat);
			})
			.catch(function () {
				body.textContent = 'Could not load COA library. Try Media Library instead.';
			});
	}
})();
JS
	);
}

/**
 * @param \WP_Post $post
 */
function wchs_coa_render_product_meta_box( \WP_Post $post ): void {
	$product = wc_get_product( $post->ID );
	if ( ! $product ) {
		return;
	}
	wp_nonce_field( 'wchs_coa_save_product', 'wchs_coa_nonce' );
	$is_variable = $product->is_type( 'variable' );
	?>
	<p class="description" style="margin-top:0">
		<?php
		if ( $is_variable ) {
			esc_html_e( 'Optional default COA for this product. Each variation can override with its own PDF below. Leave empty when only variations have COAs.', 'wchs' );
		} else {
			esc_html_e( 'Upload a PDF to Media Library, pick from the site COA library, or paste a file URL.', 'wchs' );
		}
		?>
	</p>
	<?php
	wchs_coa_render_url_field(
		(int) $product->get_id(),
		[
			'input_id'   => 'wchs_coa_url_parent',
			'input_name' => '_wchs_product_coa_url',
		]
	);
}

/**
 * @param int   $post_id
 * @param array{input_id: string, input_name: string} $args
 */
function wchs_coa_render_url_field( int $post_id, array $args ): void {
	$url = wchs_coa_get_stored_url( $post_id );
	?>
	<p class="form-field wchs-coa-url-field">
		<label for="<?php echo esc_attr( $args['input_id'] ); ?>"><?php esc_html_e( 'COA PDF URL', 'wchs' ); ?></label>
		<span style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;max-width:720px">
			<input
				type="url"
				class="widefat"
				id="<?php echo esc_attr( $args['input_id'] ); ?>"
				name="<?php echo esc_attr( $args['input_name'] ); ?>"
				value="<?php echo esc_attr( $url ); ?>"
				placeholder="https://yoursite.com/wp-content/uploads/.../coa.pdf"
				style="flex:1;min-width:220px"
			/>
			<button type="button" class="button wchs-coa-pick-media" data-target="#<?php echo esc_attr( $args['input_id'] ); ?>">
				<?php esc_html_e( 'Media Library', 'wchs' ); ?>
			</button>
			<button type="button" class="button wchs-coa-pick-library" data-target="#<?php echo esc_attr( $args['input_id'] ); ?>">
				<?php esc_html_e( 'COA library', 'wchs' ); ?>
			</button>
		</span>
	</p>
	<?php
}

/**
 * @param int $post_id
 */
function wchs_coa_get_stored_url( int $post_id ): string {
	$url = (string) get_post_meta( $post_id, WCHS_COA_PARENT_META_KEY, true );
	if ( $url === 'Array' ) {
		return '';
	}
	if ( $url !== '' ) {
		return $url;
	}
	return (string) get_post_meta( $post_id, 'coa_url', true );
}

/**
 * @param int      $loop
 * @param array    $variation_data
 * @param \WP_Post $variation
 */
function wchs_coa_render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
	echo '<div class="form-row form-row-full wchs-coa-variation-fields" style="border-top:1px solid #eee;margin-top:12px;padding-top:12px">';
	echo '<p><strong>' . esc_html__( 'COA (optional)', 'wchs' ) . '</strong></p>';
	echo '<p class="description">' . esc_html__( 'Override the parent COA for this variation. Leave empty to use the parent product COA, if any.', 'wchs' ) . '</p>';
	wchs_coa_render_url_field(
		(int) $variation->ID,
		[
			'input_id'   => 'wchs_coa_url_var_' . $loop,
			'input_name' => 'wchs_variation_coa_url[' . $loop . ']',
		]
	);
	echo '</div>';
}

/**
 * @param \WC_Product $product
 */
function wchs_coa_save_product( \WC_Product $product ): void {
	if ( ! isset( $_POST['wchs_coa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wchs_coa_nonce'] ) ), 'wchs_coa_save_product' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
		return;
	}

	$url_raw = $_POST['_wchs_product_coa_url'] ?? '';
	if ( is_array( $url_raw ) ) {
		$url_raw = '';
	}
	wchs_coa_persist_url( $product->get_id(), $url_raw );
}

/**
 * @param int $variation_id
 * @param int $loop
 */
function wchs_coa_save_variation( int $variation_id, int $loop ): void {
	if ( ! current_user_can( 'edit_post', $variation_id ) ) {
		return;
	}

	$urls = $_POST['wchs_variation_coa_url'] ?? null;
	if ( ! is_array( $urls ) ) {
		return;
	}

	$url_raw = $urls[ $loop ] ?? '';
	if ( is_array( $url_raw ) ) {
		$url_raw = '';
	}
	wchs_coa_persist_url( $variation_id, $url_raw );
}

/**
 * @param int                $post_id
 * @param string|array|null $url_raw
 */
function wchs_coa_persist_url( int $post_id, $url_raw ): void {
	if ( is_array( $url_raw ) ) {
		$url_raw = '';
	}
	$url = esc_url_raw( wp_unslash( (string) $url_raw ) );
	update_post_meta( $post_id, WCHS_COA_PARENT_META_KEY, $url );
	update_post_meta( $post_id, 'coa_url', $url );
}
