<?php
/** Small dialect extensions on a pinned, namespace-isolated Markdown Extra parser. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/assets/vendor/php-markdown/MarkdownInterface.php';
require_once get_template_directory() . '/assets/vendor/php-markdown/Markdown.php';
require_once get_template_directory() . '/assets/vendor/php-markdown/MarkdownExtra.php';

class Quietype_Markdown_Parser extends \QuietypeVendor\Michelf\MarkdownExtra {
	public function __construct() {
		$this->block_gamut['quietype_block_math'] = 6;
		$this->span_gamut['quietype_inline_math'] = -40;
		$this->span_gamut['quietype_strikethrough'] = 55;
		parent::__construct();
		$this->code_class_prefix = 'language-';
		$this->hashtag_protection = true;
		$this->fn_backlink_title = '返回注释引用';
		$this->fn_link_title = '脚注 ^^';
		// Core's entity whitelist rejects Extra's default variation selector.
		$this->fn_backlink_html = '&#8617;';
	}

	/** Math is escaped, never evaluated server-side or treated as raw HTML. */
	protected function quietype_math( $source, $display = false ) {
		return quietype_markdown_math_markup( $source, $display, $display );
	}

	protected function quietype_block_math( $text ) {
		return preg_replace_callback(
			'~^[ ]{0,3}(?:\$\$[ \t]*\n?(.*?)\n?[ \t]*\$\$|\\\\\[[ \t]*\n?(.*?)\n?[ \t]*\\\\\])[ \t]*(?:\n|$)~ms',
			function ( $match ) {
				return "\n\n" . $this->hashBlock( $this->quietype_math( '' !== $match[1] ? $match[1] : ( $match[2] ?? '' ), true ) ) . "\n\n";
			},
			$text
		);
	}

	protected function quietype_inline_math( $text ) {
		// Do not consume code, escaped dollars, HTML attributes or link targets.
		return preg_replace_callback(
			quietype_markdown_math_pattern( true ),
			function ( $match ) {
				return $this->hashPart( quietype_markdown_math_match( $match ) );
			},
			$text
		);
	}

	protected function quietype_strikethrough( $text ) {
		return preg_replace( '/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text );
	}

	/** A caption on the next line terminates a table, just like a blank line. */
	protected function doTables( $text ) {
		if ( false === stripos( $text, '[!CAPTION]' ) ) {
			return parent::doTables( $text );
		}
		// Fenced code and raw HTML are already protected by the parent parser.
		// Probe complete table candidates with Extra itself; do not split prose,
		// indented code or a caption's own pipes into additional table cells.
		$text = preg_replace_callback(
			'~^([ ]{0,3}(?=\S)[^\n]*\|[^\n]*\n[ ]{0,3}\|?[ ]*[-:][-| :]*\n(?:[ ]{0,3}(?![ \t]|\[!CAPTION\])[^\n]*\|[^\n]*\n)*)(?=[ ]{0,3}\[!CAPTION\][ \t]+\S)~mi',
			function ( $match ) {
				$table = parent::doTables( $match[1] . "\n" );
				// Only a single, fully parsed table may create the new boundary.
				return preg_match( '/\AB\x1A[0-9]+B\n+\z/', $table ) ? $table . "\n" : $match[1];
			},
			$text
		) ?? $text;
		return parent::doTables( $text );
	}

	protected function _doFencedCodeBlocks_callback( $matches ) {
		$language = strtolower( ltrim( $matches[2], '.' ) );
		if ( in_array( $language, array( 'math', 'latex', 'katex' ), true ) ) {
			return "\n\n" . $this->hashBlock( $this->quietype_math( $matches[4], true ) ) . "\n\n";
		}
		if ( 'mermaid' === $language ) {
			return "\n\n" . $this->hashBlock( '<pre class="mermaid">' . esc_html( $matches[4] ) . '</pre>' ) . "\n\n";
		}
		return parent::_doFencedCodeBlocks_callback( $matches );
	}
}
