<?php
/** Adjacent captions must share the blank-line rendering, without changing code. */
$image_source = '![山林](https://example.test/image.jpg "原有标题")';
$table_source = "| 方案 | 特点 |\n| --- | --- |\n| A | 简单 |\n| B | 扩展 |";
$caption_sources = array(
	'image'           => $image_source,
	'linked image'    => '[' . $image_source . '](https://example.test/original.jpg)',
	'reference image' => '![山林][photo]',
	'HTML image'      => '<img src="https://example.test/image.jpg" alt="A &gt; B">',
	'HTML picture'    => '<picture><source srcset="a.webp" type="image/webp"><img src="a.jpg" alt="山林"></picture>',
	'table'           => $table_source,
	'table without leading pipes' => "方案 | 特点\n--- | ---\nA | 简单\nB | 扩展",
	'table without body'          => "| 方案 | 特点 |\n| --- | --- |",
	'single-column table'         => "| 方案\n| ---\n| A",
	'HTML table'      => '<table><tr><th>方案</th></tr><tr><td>A</td></tr></table>',
);
$caption_line = '[!CAPTION] **说明**与 `API`，含 | 符号和[来源](https://example.test/source)。';
foreach ( $caption_sources as $name => $media_source ) {
	$definitions = "\n\n[photo]: https://example.test/image.jpg \"原有标题\"\n";
	$spaced = quietype_render_captions( quietype_markdown_render( $media_source . "\n\n" . $caption_line . $definitions ) );
	$adjacent_source = $media_source . "\n" . $caption_line . $definitions;
	$adjacent = quietype_render_captions( quietype_markdown_render( $adjacent_source ) );
	quietype_test_assert( ! str_contains( $adjacent, '[!CAPTION]' ), $name . ': adjacent caption is consumed' );
	quietype_test_assert( $adjacent === $spaced, $name . ': both caption spellings have identical HTML' );
	quietype_test_assert( $adjacent === quietype_render_captions( $adjacent ), $name . ': repeated rendering is harmless' );
	quietype_test_assert( $adjacent === quietype_render_captions( quietype_markdown_render( str_replace( "\n", "\r\n", $adjacent_source ) ) ), $name . ': Windows line endings work too' );
}

foreach ( array( "\n", "  \n" ) as $line_break ) {
	$rendered = quietype_render_captions( quietype_markdown_render( $image_source . $line_break . $caption_line ) );
	quietype_test_assert( str_contains( $rendered, '<figcaption><strong>说明</strong>' ), 'soft and hard image line breaks both support caption formatting' );
	$feed = apply_filters( 'the_content_feed', apply_filters( 'the_content', quietype_markdown_render( $image_source . $line_break . $caption_line ) ) );
	quietype_test_assert( str_contains( $feed, '<figcaption>' ) && ! str_contains( $feed, '[!CAPTION]' ), 'adjacent captions survive the complete frontend/feed pipeline' );
}

foreach ( array( $image_source, $table_source ) as $media_source ) {
	$adjacent_source = $media_source . "\n" . $caption_line;
	$quoted = '> ' . str_replace( "\n", "\n> ", $adjacent_source );
	$quoted_html = quietype_render_captions( quietype_markdown_render( $quoted ) );
	quietype_test_assert( str_contains( $quoted_html, '<blockquote>' ) && ! str_contains( $quoted_html, '[!CAPTION]' ), 'adjacent captions also work inside blockquotes' );
	foreach ( array( "```text\n" . $adjacent_source . "\n```", "~~~\n" . $adjacent_source . "\n~~~", '    ' . str_replace( "\n", "\n    ", $adjacent_source ) ) as $code_source ) {
		$code_html = quietype_render_captions( quietype_markdown_render( $code_source ) );
		quietype_test_assert( ! preg_match( '/<(?:figure|table)\b/', $code_html ), 'fenced and indented caption examples never become media' );
		preg_match( '~<code[^>]*>(.*?)</code>~s', $code_html, $code );
		quietype_test_assert( trim( html_entity_decode( $code[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) === $adjacent_source, 'code examples retain their exact line breaks and markers' );
	}
}

$literal_sources = array(
	"普通文字\n" . $caption_line,
	$image_source . ' ' . $caption_line,
	"文字 " . $image_source . "\n" . $caption_line,
	$image_source . $image_source . "\n" . $caption_line,
	$image_source . "\n\n中间有正文。\n" . $caption_line,
	"不是表格 | 文字\n" . $caption_line,
	"<figure><img src=\"a.jpg\"><figcaption>原生题注</figcaption></figure>\n" . $caption_line,
);
foreach ( $literal_sources as $literal_source ) {
	$html = quietype_markdown_render( $literal_source );
	quietype_test_assert( $html === quietype_render_captions( $html ), 'same-line, unrelated, inline, multi-image and native markup is unchanged' );
}
$unsafe_caption = quietype_render_captions( quietype_markdown_render( $image_source . "\n[!CAPTION] <b onclick=\"alert(1)\">说明</b> <a href=\"javascript:alert(1)\">来源</a>" ) );
quietype_test_assert( str_contains( $unsafe_caption, '<figcaption>' ) && ! preg_match( '/onclick|javascript:/', $unsafe_caption ), 'adjacent captions use the same HTML sanitization as spaced captions' );
echo "Adjacent Markdown caption checks passed.\n";
