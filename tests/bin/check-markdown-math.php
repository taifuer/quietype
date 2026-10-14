<?php
/** Formula boundaries must survive the complete WordPress reading pipeline. */
$legacy = file_get_contents( __DIR__ . '/../fixtures/legacy-math.html' );
$prepared = quietype_prepare_article( apply_filters( 'the_content', $legacy ) )['content'];
quietype_test_assert( 13 === substr_count( $prepared, 'class="quietype-math"' ), 'all old formulas are protected before typography and word wrapping' );
preg_match_all( '~<(?:span|div) class="quietype-math"[^>]*>(.*?)</(?:span|div)>~s', $prepared, $math_nodes );
foreach ( $math_nodes[1] as $node ) {
	quietype_test_assert( ! str_contains( $node, '<wbr' ), 'math has no inserted word-break node' );
}
quietype_test_assert( str_contains( $prepared, 'vir<wbr>Connect<wbr>Domain' ), 'ordinary technical identifiers still wrap' );
quietype_test_assert( str_contains( $prepared, 'title="$attribute$ &gt; keepValue"' ), 'literal attributes are not modified by math or wrapping' );
$quoted_attribute = '<p><a title="$value$ > keepValue">linkText</a></p>';
quietype_test_assert( str_contains( quietype_prepare_article( quietype_markdown_legacy_math( $quoted_attribute ) )['content'], 'title="$value$ > keepValue"' ), 'a quoted > does not become article text' );
$literal_math_html = '<pre><code><span class="katex math inline">$P(H)$</span></code></pre>';
quietype_test_assert( quietype_markdown_legacy_math( $literal_math_html ) === $literal_math_html, 'literal code subtrees are never normalized as legacy math' );
quietype_test_assert( str_contains( $prepared, '<code>$P(H|E)$</code>' ), 'inline math examples remain code' );
quietype_test_assert( str_contains( $prepared, 'user:~$ gcc' ), 'Shell prompts remain code' );
quietype_test_assert( ! str_contains( $prepared, '&amp;#' ), 'formula character entities are not double-encoded' );
$protected = quietype_markdown_legacy_math( $legacy );
quietype_test_assert( $protected === quietype_markdown_legacy_math( $protected ), 'legacy formula wrapping is idempotent' );

$cases = array(
	'$P(H|E)$' => 'P(H|E)',
	'$$0$$' => '0',
	'Before $$x^2$$ after.' => 'x^2',
	'Before \\[x_1\\] after.' => 'x_1',
	'\\(P(E)\\)' => 'P(E)',
	'$\\text{\\$20}$' => '\\text{\\$20}',
	"\$a -- b + x'' + \\text{a...b}\$" => "a -- b + x'' + \\text{a...b}",
);
foreach ( $cases as $source_math => $tex ) {
	$rendered_math = quietype_markdown_render( $source_math );
	preg_match( '~class="quietype-math"[^>]*>(.*?)</(?:span|div)>~s', $rendered_math, $node );
	quietype_test_assert( isset( $node[1] ) && html_entity_decode( $node[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === $tex, 'Markdown preserves TeX: ' . $source_math );
}
foreach ( array( '\\$not_math\\$', '$20 and $30', '`$x$`', '``$x$``', '[URL](https://example.test/$x$)' ) as $literal ) {
	quietype_test_assert( ! str_contains( quietype_markdown_render( $literal ), 'quietype-math' ), 'escaped dollars, prices, URLs and code are literal' );
}
quietype_test_assert( ! quietype_content_has_math( '<pre><code>echo "$HOME" "$USER" "$$"</code></pre><p><code>$x$</code></p>' ), 'code-only pages do not require a formula renderer' );
quietype_test_assert( quietype_content_has_math( '<p>中文$P(H|E)$中文</p>' ), 'Chinese-adjacent formulas load the renderer' );
quietype_test_assert( quietype_content_has_math( '<p>\\(P(H)\\)</p>' ), 'parenthesis formulas load the renderer' );
quietype_test_assert( ! quietype_content_has_math( '<p>Price $20 and $30</p>' ), 'prices do not load formula libraries' );
echo "Markdown math boundaries and reading compatibility checks passed.\n";
