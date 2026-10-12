<?php
/** Caption checks shared by the WordPress compatibility jobs. */

$image = '<p class="aligncenter"><img src="https://images.example.test/test.jpg" alt="A &gt; B" width="800" height="500"></p>';
$caption = '<p>[!CAPTION] 图 1：<strong>架构</strong>与 <code>API</code>，<a href="https://example.test/source" title="来源">资料来源</a>。</p>';
$rendered = quietype_render_captions( $image . "\n\n" . $caption );
quietype_test_assert( false !== strpos( $rendered, '<figure class="article-figure">' . $image ), 'a standalone image should retain its attributes inside a figure' );
quietype_test_assert( false !== strpos( $rendered, '<figcaption>图 1：<strong>架构</strong>与 <code>API</code>' ), 'captions should preserve safe inline markup' );
quietype_test_assert( false === strpos( $rendered, '[!CAPTION]' ), 'a consumed caption marker should not leak into output' );
quietype_test_assert( $rendered === quietype_render_captions( $rendered ), 'caption rendering must be idempotent' );

$linked = '<p><a href="https://images.example.test/full.jpg"><picture><source srcset="a.webp" type="image/webp"><img src="a.jpg" alt="A > B"></picture></a></p>';
$rendered = quietype_render_captions( $linked . $caption );
quietype_test_assert( false !== strpos( $rendered, '<figure class="article-figure">' . $linked ), 'linked pictures and quoted > characters must survive unchanged' );

$table = '<table class="custom"><thead><tr><th>名称</th></tr></thead><tbody><tr><td>A</td></tr></tbody></table>';
$table_caption = '<p>[!CAPTION] 表 1：方案对比</p>';
$rendered = quietype_render_captions( $table . $table_caption );
quietype_test_assert( 0 === strpos( $rendered, '<table class="custom"><caption>表 1：方案对比</caption><thead>' ), 'a table title must be its first child' );
quietype_test_assert( $rendered === quietype_render_captions( $rendered ), 'table captions must not duplicate on repeat filtering' );
quietype_test_assert( $table . '<p>普通段落</p>' . $rendered === quietype_render_captions( $table . '<p>普通段落</p>' . $table . $table_caption ), 'an earlier uncaptioned table must not consume a later captioned table' );

$unchanged = array(
	$image,
	$image . '<p>中间有普通段落。</p>' . $caption,
	'<p>段内 <img src="a.jpg" alt="A"> 不是独立图片。</p>' . $caption,
	'<p><img src="a.jpg"><img src="b.jpg"></p>' . $caption,
	'<p>[!CAPTION] 没有关联的图片或表格。</p>',
	$image . '<p>[!CAPTION] </p>',
	'<pre>' . $image . $caption . '</pre>',
	'<PRE>' . $image . $caption . '</PRE>',
	'<code>' . $image . $caption . '</code>',
	'<script type="text/plain">' . $image . $caption . '</script>',
	'<!-- ' . $image . $caption . ' -->',
	'<textarea>' . $image . $caption . '</textarea>',
	'<figure>' . $image . '<figcaption>原生题注</figcaption></figure>' . $caption,
	'<div class="wp-caption">' . $image . '<p class="wp-caption-text">原生题注</p></div>' . $caption,
	str_replace( '<thead>', '<caption>原有表题</caption><thead>', $table ) . $table_caption,
	'<table><tr><td>' . $table . '</td></tr></table>' . $table_caption,
	$image . '<p>[!CAPTION] ' . "\xFF" . 'invalid UTF-8</p>',
);
foreach ( $unchanged as $index => $html ) {
	quietype_test_assert( $html === quietype_render_captions( $html ), 'unrelated/native/protected markup must stay unchanged (case ' . $index . ')' );
}
$nested = '<blockquote>' . $image . $caption . '</blockquote>';
quietype_test_assert( false !== strpos( quietype_render_captions( $nested ), '<blockquote><figure' ), 'standalone media may be captioned inside a blockquote or list' );
$unsafe = quietype_render_captions( $image . '<p>[!CAPTION] <strong onclick="alert(1)">说明</strong><a href="javascript:alert(1)">来源</a><img src=x onerror=alert(1)><script>alert(1)</script></p>' );
quietype_test_assert( false === strpos( $unsafe, 'onclick' ) && false === strpos( $unsafe, 'onerror' ) && false === strpos( $unsafe, 'javascript:' ) && false === strpos( $unsafe, '<script' ), 'caption markup must not introduce active content or executable links' );

// Excerpt and feed rendering must use the same server-side transform.
$excerpt_post = clone get_page_by_path( 'quietype-captions-test', OBJECT, 'page' );
$excerpt_post->post_content = $image . $caption;
$excerpt_post->post_excerpt = '';
$excerpt = wp_trim_excerpt( '', $excerpt_post );
quietype_test_assert( false === strpos( $excerpt, '[!CAPTION]' ) && false !== strpos( $excerpt, '架构' ), 'generated excerpts must retain caption text without its directive' );
$feed = apply_filters( 'the_content_feed', apply_filters( 'the_content', $image . $caption ) );
quietype_test_assert( false !== strpos( $feed, '<figcaption>' ) && false === strpos( $feed, '[!CAPTION]' ), 'full-content feeds must receive semantic captions without JavaScript' );
quietype_test_assert( 11 === has_filter( 'the_content', 'quietype_optimize_content_images' ), 'image dimensions must be added before Core media optimization at priority 12' );

$previous_query = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $excerpt_post->ID ) );
$source_url = 'https://images.example.test/captions/lead.jpg';
$remote_requests = 0;
$count_remote = static function ( $response ) use ( &$remote_requests ) {
	++$remote_requests;
	return new WP_Error( 'unexpected_remote_read', 'Rendering must not fetch image metadata.' );
};
add_filter( 'pre_http_request', $count_remote );
$image = new WP_HTML_Tag_Processor( quietype_optimize_content_images( '<img src="' . $source_url . '" alt="说明">' ) );
$image->next_tag();
quietype_test_assert( '960' === $image->get_attribute( 'width' ) && '593' === $image->get_attribute( 'height' ), 'cached dimensions should be present before Core sees a remote image' );
$specified = '<img src="' . $source_url . '" alt="说明" width="480" height="296" loading="eager" decoding="sync" fetchpriority="low">';
quietype_test_assert( $specified === quietype_optimize_content_images( $specified ), 'explicit size, loading, decoding and priority attributes must be preserved' );
$partial = new WP_HTML_Tag_Processor( quietype_optimize_content_images( '<img src="' . $source_url . '" width="240" loading="lazy">' ) );
$partial->next_tag();
quietype_test_assert( '240' === $partial->get_attribute( 'width' ) && null === $partial->get_attribute( 'height' ), 'the theme must not invent a mismatched height for an author-sized image' );
$unknown = new WP_HTML_Tag_Processor( quietype_optimize_content_images( '<img src="https://images.example.test/unknown.jpg">' ) );
$unknown->next_tag();
quietype_test_assert( null === $unknown->get_attribute( 'width' ) && null === $unknown->get_attribute( 'height' ), 'unknown image dimensions must not be fabricated' );
quietype_test_assert( 0 === $remote_requests, 'render-time image optimization must never perform network requests' );
remove_filter( 'pre_http_request', $count_remote );
$GLOBALS['wp_query'] = $previous_query;

printf( "Caption compatibility checks passed.\n" );
