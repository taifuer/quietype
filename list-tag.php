<?php
/**
 * Template Name: 文章标签
 */
get_header();
the_post();
?>
<section class="tags-page section-wrap">
	<header class="page-hero"><p class="eyebrow">TAGS</p><h1><?php the_title(); ?></h1><p>从关键词进入文章，也从关键词重新发现旧的记录。</p></header>
	<div class="tag-cloud"><?php wp_tag_cloud( array( 'smallest' => 13, 'largest' => 20, 'unit' => 'px', 'number' => 0, 'format' => 'flat' ) ); ?></div>
</section>
<?php get_footer(); ?>
