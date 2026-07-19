<?php
/**
 * Template Name: 友情链接
 *
 * @package Quietype
 */
get_header();
the_post();
$bookmarks = get_bookmarks( array( 'orderby' => 'rating', 'order' => 'DESC' ) );
?>
<section class="links-page section-wrap">
	<header class="page-hero">
		<p class="eyebrow">LINKS</p>
		<h1><?php the_title(); ?></h1>
	</header>
	<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?><div class="article-content content-width links-intro"><?php the_content(); ?></div><?php endif; ?>
	<?php if ( $bookmarks ) : ?>
		<div class="link-grid">
			<?php foreach ( $bookmarks as $bookmark ) : ?>
				<a href="<?php echo esc_url( $bookmark->link_url ); ?>" target="<?php echo esc_attr( $bookmark->link_target ?: '_blank' ); ?>" rel="noopener friend">
					<strong><?php echo esc_html( $bookmark->link_name ); ?></strong>
					<span><?php echo esc_html( $bookmark->link_description ?: wp_parse_url( $bookmark->link_url, PHP_URL_HOST ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php elseif ( ! trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
		<p class="empty-state">友链正在整理中。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>
