<?php
/** Exercise the legacy plugin's real math callbacks without changing any data. */
define( 'WP_USE_THEMES', false );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'QUIETYPE_TEST_PORT' ) ?: '8888' );
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	exit( "Refusing to test a non-local site.\n" );
}
if ( ! class_exists( 'EditormdApp\\KaTeX' ) ) {
	exit( "Editor.md must be enabled for math integration checks.\n" );
}
$plugin = ( new ReflectionClass( 'EditormdApp\\KaTeX' ) )->newInstanceWithoutConstructor();
$parse = static function ( $content ) use ( $plugin ) {
	$content = quietype_filter_editor_math( $content, array( $plugin, 'katex_markup_double' ) );
	return quietype_filter_editor_math( $content, array( $plugin, 'katex_markup_single' ) );
};
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};
$literals = array(
	'attributed pre' => '<pre data-example="a > b"><code class="language-bash">user:~$ gcc main.c -o main' . "\n" . 'user:~$ ./main</code></pre>',
	'uppercase tags' => '<PRE class="sample"><CODE>echo "$HOME" "${USER}" "$(pwd)" "$$"</CODE></PRE>',
	'inline code' => '<code>$x$ and $$y$$</code>',
	'highlighted code' => '<pre><code><span>echo</span> "$HOME" "$USER"</code></pre>',
	'attributes' => '<a title="$x<em>a</em>$" href="/literal-$a$">link</a>',
	'comment' => '<!-- example $x<em>a</em>$ -->',
	'script' => '<script type="text/plain">const example = "$x<em>a</em>$";</script>',
	'style' => '<style>.example::before { content: "$x$"; }</style>',
	'textarea' => '<textarea>$x$ and $$y$$</textarea>',
	'keyboard and output' => '<kbd>echo "$HOME"</kbd><samp>echo "$USER"</samp>',
	'svg and mathml' => '<svg><text>$x$</text></svg><math><mtext>$x$</mtext></math>',
	'existing math' => '<div class="katex math multi-line"><div>$x$</div> $$y$$</div>',
	'native math' => '<span class="quietype-math">$x$</span>',
	'unclosed pre' => '<pre><code>$HOME $USER',
	'no dollar' => '<p>Plain text &amp; unchanged entities.</p>',
);
foreach ( $literals as $label => $html ) {
	$assert( $parse( $html ) === $html, "Literal {$label} must remain byte-identical." );
}
$formulas = array(
	'<p>Inline $a^2 + b^2 = c^2$ formula.</p>',
	'<p>Display $$\\sum_{i=1}^n i$$ formula.</p>',
	'<p>Inline $x$ followed by $$y^2$$.</p>',
);
foreach ( $formulas as $html ) {
	$legacy = $plugin->katex_markup_single( $plugin->katex_markup_double( $html ) );
	$assert( str_contains( $legacy, 'katex math' ), 'The reference formula must actually render: ' . $html );
	$assert( $parse( $html ) === $legacy, 'Valid prose formulas must retain their legacy markup.' );
}
$emphasis = '<p>Legacy emphasis $x<em>1 + x</em>2$ unchanged.</p>';
$assert( $parse( $emphasis ) === $plugin->katex_markup_single( $plugin->katex_markup_double( $emphasis ) ), 'Existing emphasis handling is unchanged.' );
$mixed = '<p>Formula $x^2$.</p>' . $literals['attributed pre'] . '<p>Formula $$y^2$$.</p>';
$parsed = $parse( $mixed );
$assert( str_contains( $parsed, $literals['attributed pre'] ), 'Mixed content preserves the complete code block.' );
$assert( 2 === substr_count( $parsed, 'katex math' ), 'Mixed content renders only the two prose formulas.' );
$assert( $parse( $parsed ) === $parsed, 'Filtering already processed content is idempotent.' );

$hook_code = str_replace( 'data-example="a > b"', 'class="shell"', $literals['attributed pre'] );
$hook_content = str_replace( $literals['attributed pre'], $hook_code, $mixed );
foreach ( array( 'the_content', 'comment_text' ) as $hook ) {
	$before_count = array_sum( array_map( 'count', $GLOBALS['wp_filter'][ $hook ]->callbacks ) );
	quietype_protect_editor_math();
	$assert( $before_count === array_sum( array_map( 'count', $GLOBALS['wp_filter'][ $hook ]->callbacks ) ), 'Repeated registration must not add duplicate filters.' );
	$filtered = apply_filters( $hook, $hook_content );
	$assert( str_contains( $filtered, $hook_code ), "{$hook} preserves literal code through the real hook chain." );
	$assert( 2 === substr_count( $filtered, 'katex math' ), "{$hook} still renders normal formulas." );
}
echo "Editor.md math protection: {$checks} checks passed.\n";
