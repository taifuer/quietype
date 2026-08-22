<?php
/**
 * Template Name: 友情链接
 *
 * @package Quietype
 */
get_header();
the_post();
$link_groups = quietype_link_groups();
?>
<section class="links-page section-wrap">
	<header class="page-hero">
		<h1><?php the_title(); ?></h1>
	</header>
	<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?><div class="article-content content-width links-intro"><?php the_content(); ?></div><?php endif; ?>
	<?php if ( $link_groups ) : ?>
		<div class="link-groups">
			<?php foreach ( $link_groups as $group ) : ?>
				<section class="link-group" aria-labelledby="link-category-<?php echo esc_attr( $group['term']->term_id ); ?>">
					<header class="link-group__header"><h2 id="link-category-<?php echo esc_attr( $group['term']->term_id ); ?>"><?php echo esc_html( $group['term']->name ); ?></h2><span><?php echo esc_html( count( $group['links'] ) ); ?></span></header>
					<div class="link-grid">
						<?php foreach ( $group['links'] as $bookmark ) : ?>
							<?php $link_state = quietype_link_state( $bookmark->link_id ); ?>
							<a class="link-card<?php echo 'offline' === $link_state['status'] ? ' is-offline' : ''; ?>" href="<?php echo esc_url( $bookmark->link_url ); ?>" target="<?php echo esc_attr( $bookmark->link_target ?: '_blank' ); ?>" rel="noopener friend"<?php echo 'offline' === $link_state['status'] ? ' title="该友链当前标记为失联，仍可尝试访问"' : ''; ?>>
								<strong><?php echo esc_html( $bookmark->link_name ); ?></strong>
								<span class="link-card__description"><?php echo esc_html( $bookmark->link_description ?: wp_parse_url( $bookmark->link_url, PHP_URL_HOST ) ); ?></span>
								<?php if ( 'offline' === $link_state['status'] ) : ?><span class="link-card__status">失联</span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	<?php elseif ( ! trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
		<p class="empty-state">友链正在整理中。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>
