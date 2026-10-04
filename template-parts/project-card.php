<?php
/**
 * One project: screenshot, purpose, optional facts and useful destinations.
 *
 * @package Quietype
 */

$data = quietype_project_data( get_the_ID() );
$title = get_the_title();
$title_url = $data['url'] ?: ( $data['source_url'] ?: $data['writeup_url'] );
$source_label = array( 'open' => '开源', 'closed' => '未开源' )[ $data['source_state'] ] ?? '';
$description = wp_strip_all_tags( get_post_field( 'post_excerpt', get_the_ID() ) );
?>
<article class="project-card" id="project-<?php the_ID(); ?>" aria-labelledby="project-title-<?php the_ID(); ?>">
	<?php if ( $data['image_url'] ) : ?>
		<a class="project-preview" href="<?php echo esc_url( $data['image_url'] ); ?>" data-pswp-src="<?php echo esc_url( $data['image_url'] ); ?>" data-pswp-width="<?php echo esc_attr( $data['image_width'] ); ?>" data-pswp-height="<?php echo esc_attr( $data['image_height'] ); ?>" data-photo-title="<?php echo esc_attr( $title ); ?>" data-preview-title="<?php echo esc_attr( $title ); ?>" aria-label="<?php echo esc_attr( $title . '，查看截图' ); ?>">
			<?php if ( ! $data['is_external'] && $data['attachment_id'] ) : ?>
				<?php echo wp_get_attachment_image( $data['attachment_id'], 'large', false, array( 'alt' => $title . ' 项目截图', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 760px) calc(100vw - 36px), (max-width: 1008px) calc((100vw - 76px) / 2), 466px' ) ); ?>
			<?php else : ?>
				<img src="<?php echo esc_url( $data['thumbnail_url'] ); ?>" alt="<?php echo esc_attr( $title . ' 项目截图' ); ?>" loading="lazy" decoding="async">
			<?php endif; ?>
			<span class="project-preview__hint" aria-hidden="true"><?php echo quietype_icon( 'expand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed icon markup. ?></span>
		</a>
	<?php endif; ?>
	<div class="project-body">
		<div class="project-heading">
			<h2 id="project-title-<?php the_ID(); ?>"><?php if ( $title_url ) : ?><a href="<?php echo esc_url( $title_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a><?php else : ?><?php echo esc_html( $title ); ?><?php endif; ?></h2>
			<span class="project-kind"><?php echo esc_html( $data['type'] ); ?></span>
		</div>
		<?php if ( $description ) : ?><p class="project-description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
		<?php if ( $source_label || $data['languages'] ) : ?>
			<p class="project-meta<?php echo $data['languages'] ? ' has-languages' : ''; ?>">
				<?php if ( $source_label ) : ?><span class="project-source"><?php echo esc_html( $source_label ); ?></span><?php endif; ?>
				<?php if ( $data['languages'] ) : ?><span class="project-languages"><?php echo esc_html( str_replace( ', ', ' / ', $data['languages'] ) ); ?></span><?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( $data['url'] || $data['source_url'] || $data['writeup_url'] ) : ?>
			<div class="project-actions">
				<?php foreach ( array( 'url' => '在线访问', 'source_url' => '源码', 'writeup_url' => '项目介绍' ) as $key => $label ) : ?>
					<?php if ( ! $data[ $key ] ) { continue; } ?>
					<?php $is_github = 'source_url' === $key && 'github.com' === strtolower( (string) wp_parse_url( $data[ $key ], PHP_URL_HOST ) ); ?>
					<a href="<?php echo esc_url( $data[ $key ] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo quietype_icon( $is_github ? 'github' : 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed icon markup. ?><?php echo esc_html( $is_github ? 'GitHub' : $label ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
