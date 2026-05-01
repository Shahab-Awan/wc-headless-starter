<?php
/**
 * Fallback template. Any request that reaches here means template_redirect
 * didn't punt us to the SPA — likely a special-case page. Show a minimal
 * shell with a back-to-SPA link.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="wchs-shell">
	<a class="wchs-back-link" href="<?php echo esc_url( wchs_spa_url() ); ?>">← back to site</a>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article>
				<h1><?php the_title(); ?></h1>
				<div><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1>Not here</h1>
		<p>This request reached the WP fallback. Most pages live in the SPA.</p>
	<?php endif; ?>
</main>
<?php
get_footer();
