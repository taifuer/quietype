<article <?php post_class( 'post-row' ); ?>>
	<div class="post-row__body">
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="post-row__excerpt"><?php the_excerpt(); ?></div>
		<div class="post-row__meta">
			<time class="post-row__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></time>
			<div class="post-row__terms"><?php echo wp_kses_post( quietype_post_terms( get_the_ID(), 3 ) ); ?></div>
			<span class="post-row__views"><?php echo esc_html( quietype_post_views() ); ?> 次浏览</span>
		</div>
	</div>
</article>
