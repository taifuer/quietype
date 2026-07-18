<?php
get_header();
the_post();
$rendered = apply_filters( 'the_content', get_the_content() );
$article  = quietype_prepare_article( $rendered );
?>
<article <?php post_class( 'page-entry section-wrap' ); ?>>
	<header class="page-hero">
		<p class="eyebrow">PAGE</p>
		<h1><?php the_title(); ?></h1>
	</header>
	<div class="article-content content-width"><?php echo $article['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php if ( comments_open() || get_comments_number() ) : ?><div class="content-width"><?php comments_template(); ?></div><?php endif; ?>
</article>
<?php get_footer(); ?>
