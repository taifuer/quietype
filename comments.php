<?php
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title"><?php echo esc_html( get_comments_number() ); ?> 条评论</h2>
		<ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ) ); ?></ol>
		<?php the_comments_pagination( array( 'prev_text' => '← 较早评论', 'next_text' => '较新评论 →' ) ); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>
