<?php
/**
 * Compact book card for the annual reading archive.
 *
 * @package Quietype
 */

$book_data = quietype_book_data();
$excerpt   = get_the_excerpt();
$book_url  = $book_data['douban_url'];
$book_meta = array_filter( array( $book_data['authors'], $book_data['publisher'], $book_data['publication_year'] ) );
$has_cover = has_post_thumbnail();
$cover_fallback = '<span class="book-cover__fallback"><i>' . esc_html( mb_substr( get_the_title(), 0, 4 ) ) . '</i><small>' . esc_html( $book_data['authors'] ?: get_bloginfo( 'name' ) ) . '</small></span>';
?>
<article <?php post_class( 'book-item' ); ?>>
	<?php if ( $book_url ) : ?>
		<a class="book-cover<?php echo $has_cover ? ' has-cover' : ''; ?>" href="<?php echo esc_url( $book_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo $cover_fallback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped text only. ?>
			<?php if ( $has_cover ) : ?>
				<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '《' . get_the_title() . '》封面' ) ); ?>
			<?php endif; ?>
		</a>
	<?php else : ?>
		<div class="book-cover<?php echo $has_cover ? ' has-cover' : ''; ?>">
			<?php echo $cover_fallback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped text only. ?>
			<?php if ( $has_cover ) : ?>
				<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '《' . get_the_title() . '》封面' ) ); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="book-body">
		<div class="book-title-row">
			<h3><?php if ( $book_url ) : ?><a href="<?php echo esc_url( $book_url ); ?>" target="_blank" rel="noopener noreferrer"><?php the_title(); ?></a><?php else : ?><?php the_title(); ?><?php endif; ?></h3>
			<?php if ( $book_data['douban_rating'] ) : ?><span class="douban-rating" aria-label="豆瓣评分 <?php echo esc_attr( number_format_i18n( $book_data['douban_rating'], 1 ) ); ?>"><small>豆瓣</small><strong><?php echo esc_html( number_format_i18n( $book_data['douban_rating'], 1 ) ); ?></strong></span><?php endif; ?>
		</div>
		<?php if ( $book_meta ) : ?><p class="book-meta"><?php echo esc_html( implode( ' · ', $book_meta ) ); ?></p><?php endif; ?>
		<?php $terms = quietype_book_terms_html(); ?>
		<?php if ( $terms ) : ?><div class="book-terms"><?php echo wp_kses_post( $terms ); ?></div><?php endif; ?>
		<?php if ( $excerpt ) : ?><p class="book-note"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p><?php endif; ?>
		<div class="book-evaluation">
			<div class="book-evaluation__summary">
				<span class="book-status"><?php echo esc_html( quietype_book_status_label( $book_data['status'] ) ); ?></span>
				<?php echo quietype_book_rating_html( $book_data['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( $book_data['read_date'] ) : ?><time class="book-read-date" datetime="<?php echo esc_attr( substr( $book_data['read_date'], 0, 7 ) ); ?>"><?php echo esc_html( wp_date( 'Y.m', strtotime( $book_data['read_date'] ) ) ); ?></time><?php endif; ?>
		</div>
	</div>
</article>
