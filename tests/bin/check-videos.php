<?php
/** Native videos are safe, portable, lazy and independent of Markdown plugins. */
$marker = '[video src="https://media.example.test/demo.mp4?token=a&amp;part=2" poster="https://media.example.test/poster.jpg" title="演示视频"]';
$html = quietype_render_video_shortcodes( '<p>' . $marker . '</p>', true );
quietype_test_assert( str_contains( $html, '<video class="quietype-video" controls playsinline preload="none"' ), 'video uses native controls and no eager download' );
quietype_test_assert( str_contains( html_entity_decode( $html ), 'demo.mp4?token=a&part=2' ), 'signed/query media URLs survive HTML entity escaping' );
quietype_test_assert( str_contains( $html, 'poster="https://media.example.test/poster.jpg"' ) && str_contains( $html, 'aria-label="演示视频"' ), 'poster and accessible title survive rendering' );
quietype_test_assert( 1 === substr_count( apply_filters( 'the_content', $marker ), '<video ' ), 'Core/the_content never initialize two players' );
quietype_test_assert( str_contains( do_shortcode( $marker ), 'quietype-video' ), 'standard WordPress shortcode calls use the same native output' );
quietype_test_assert( ! wp_script_is( 'wp-mediaelement', 'enqueued' ) && ! wp_style_is( 'wp-mediaelement', 'enqueued' ), 'native video adds no player library' );

$literals = '<pre><code>' . $marker . '</code></pre><p><code>' . $marker . '</code></p><!-- ' . $marker . ' --><span title=\'' . $marker . '\'>属性</span>';
quietype_test_assert( quietype_render_video_shortcodes( $literals, true ) === $literals, 'code, comments and HTML attributes retain video examples byte-for-byte' );
$escaped = quietype_render_video_shortcodes( '[' . $marker . ']', true );
quietype_test_assert( ! str_contains( $escaped, '<video' ) && html_entity_decode( $escaped ) === html_entity_decode( $marker ), 'WordPress double brackets escape the shortcode' );
add_shortcode( 'quietype_test_other', static function () { return '<strong>Other shortcode</strong>'; } );
quietype_test_assert( str_contains( quietype_render_video_shortcodes( $marker . '[quietype_test_other]', true ), '<strong>Other shortcode</strong>' ), 'other public shortcodes still run normally' );
quietype_test_assert( str_contains( wp_strip_all_tags( quietype_markdown_preview_document( $marker . "\n\n[quietype_test_other]" ) ), '[quietype_test_other]' ), 'editor preview never executes unrelated shortcodes' );
remove_shortcode( 'quietype_test_other' );

foreach ( array( 'javascript:alert(1)', 'data:video/mp4;base64,AAAA', 'file:///etc/passwd', 'https://example.test/watch?v=1' ) as $url ) {
	quietype_test_assert( ! str_contains( quietype_native_video( array( 'src' => $url ) ), '<video' ), 'invalid/non-file video URLs never create a player: ' . $url );
}
$unsafe = quietype_native_video( array( 'src' => 'https://example.test/demo.webm', 'poster' => 'javascript:alert(1)', 'onerror' => 'alert(1)', 'autoplay' => true, 'preload' => 'auto', 'title' => '" onerror="alert(1)' ) );
$tag = new WP_HTML_Tag_Processor( $unsafe );
$tag->next_tag();
quietype_test_assert( null === $tag->get_attribute( 'onerror' ) && null === $tag->get_attribute( 'autoplay' ) && null === $tag->get_attribute( 'poster' ) && 'none' === $tag->get_attribute( 'preload' ), 'video attributes cannot add script, autoplay or eager loading' );
$dimensions = quietype_native_video( array( 'mp4' => 'https://example.test/demo.mp4', 'webm' => 'https://example.test/demo.webm', 'width' => '1464', 'height' => '416' ) );
quietype_test_assert( 2 === substr_count( $dimensions, '<source ' ) && str_contains( $dimensions, 'width="1464" height="416"' ), 'multiple file formats and explicit original dimensions are supported' );
quietype_test_assert( str_contains( quietype_markdown_preview_document( $marker ), 'media-src &#039;self&#039; https: http:' ), 'isolated preview allows native media without opening the script policy' );
echo "Native video checks passed.\n";
