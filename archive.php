<?php
get_header();

$archive_title = get_the_archive_title();
$category      = is_category() ? get_queried_object() : null;
$category_path = array();

if ( $category instanceof WP_Term ) {
	$archive_title = single_cat_title( '', false );
	$ancestor_ids  = array_reverse( get_ancestors( $category->term_id, 'category', 'taxonomy' ) );
	foreach ( $ancestor_ids as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'category' );
		if ( $ancestor instanceof WP_Term ) {
			$category_path[] = $ancestor;
		}
	}
	$category_path[] = $category;
} elseif ( is_tag() ) {
	$archive_title = single_tag_title( '', false );
} elseif ( is_tax() ) {
	$archive_title = single_term_title( '', false );
} else {
	$archive_title = wp_strip_all_tags( $archive_title );
}
?>
<section class="archive-index section-wrap">
	<header class="page-hero">
		<?php if ( count( $category_path ) > 1 ) : ?>
			<nav class="taxonomy-path" aria-label="分类层级">
				<?php foreach ( $category_path as $path_index => $path_term ) : ?>
					<?php if ( $path_index > 0 ) : ?><span aria-hidden="true">/</span><?php endif; ?>
					<?php if ( $path_term->term_id === $category->term_id ) : ?>
						<span aria-current="page"><?php echo esc_html( $path_term->name ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( get_category_link( $path_term->term_id ) ); ?>"><?php echo esc_html( $path_term->name ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<h1><?php echo esc_html( $archive_title ); ?></h1>
		<?php the_archive_description( '<div class="page-description">', '</div>' ); ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'template-parts/post', 'row' ); ?>
			<?php endwhile; ?>
		</div>
		<?php quietype_pagination(); ?>
	<?php else : ?>
		<p class="empty-state">这个分类中暂时没有文章。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>
