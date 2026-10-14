<?php
/** Optional parser/editor persistence checks, without any Markdown plugin. */
$markdown_setting = get_option( 'quietype_markdown_enabled', false );
quietype_test_assert( ! quietype_markdown_enabled(), 'Markdown is off by default' );
update_option( 'quietype_markdown_enabled', true );
wp_set_current_user( 1 );
require __DIR__ . '/check-markdown-math.php';
require __DIR__ . '/check-markdown-captions.php';
$source = file_get_contents( __DIR__ . '/../fixtures/markdown.md' );
$html = quietype_markdown_render( $source );
foreach ( array( '<h2>', '<strong>', '<del>', '<table>', 'language-python', 'class="mermaid"', 'quietype-math', 'footnotes' ) as $feature ) {
	quietype_test_assert( str_contains( $html, $feature ), 'Markdown renders ' . $feature );
}
quietype_test_assert( 3 === substr_count( $html, 'data-display="false"' ), 'only actual inline math is parsed' );
quietype_test_assert( 2 === substr_count( $html, 'data-display="true"' ), 'both dollar and fenced display math render' );
quietype_test_assert( str_contains( $html, '<code>$literal$</code>' ), 'inline code dollars remain literal' );
quietype_test_assert( str_contains( $html, 'href="#fn-note"' ) && str_contains( $html, 'id="fn-note"' ), 'footnote links survive WordPress protocol sanitization' );
quietype_test_assert( ! str_contains( $html, '&amp;#xFE0E;' ), 'footnote backlinks do not leak a rejected variation-selector entity' );
$literal_code = quietype_markdown_render( "```text\n\$\$\n\\alpha_i\n\$\$\n```\n\n    \$a_b\$\n\n`` \$x_y\$ ``\n\n[price](https://example.test/\$item\$)" );
quietype_test_assert( ! str_contains( $literal_code, 'quietype-math' ), 'code blocks/spans and link targets never become math' );
$tex = "a -- b + x'' + \\text{a...b}";
$math_html = apply_filters( 'the_content', quietype_markdown_render( '$' . $tex . '$' ) );
quietype_test_assert( str_contains( html_entity_decode( wp_strip_all_tags( $math_html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $tex ), 'WordPress typography leaves TeX punctuation intact' );
quietype_test_assert( str_contains( quietype_render_captions( $html ), '<caption>' ), 'Markdown tables retain theme captions' );
$unsafe = quietype_markdown_render( '<script>alert(1)</script><img src=x onerror="alert(1)"><a href="javascript:alert(1)">test</a>' );
quietype_test_assert( ! preg_match( '/<script|onerror|href="javascript:/i', $unsafe ), 'Markdown sanitizes active HTML even for administrators' );
$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'auto-draft', 'post_title' => 'Markdown persistence test' ) );
$_POST = wp_slash( array( 'post_ID' => $id, 'quietype_markdown_nonce' => wp_create_nonce( 'quietype_markdown_' . $id ), 'content' => $source ) );
wp_update_post( wp_slash( array( 'ID' => $id, 'post_status' => 'draft', 'post_content' => $source ) ) );
$_POST = array();
$saved = get_post( $id );
quietype_test_assert( $saved->post_content_filtered === $source, 'source survives quotes, dollar signs and backslashes exactly' );
quietype_test_assert( $saved->post_content === $html, 'stored article contains standard HTML' );
quietype_test_assert( quietype_markdown_post( $id ), 'saved Markdown is owned by the module' );
quietype_test_assert( get_post( $id, OBJECT, 'edit' )->post_content === esc_textarea( $source ), 'native edit screen receives escaped source, not HTML' );
quietype_test_assert( quietype_markdown_post( get_post( $id, OBJECT, 'edit' ) ), 'edit-context objects do not break ownership detection' );
wp_update_post( array( 'ID' => $id, 'post_title' => 'Quick edit only' ) );
quietype_test_assert( get_post( $id )->post_content_filtered === $source && get_post( $id )->post_content === $html, 'quick edits do not reparse HTML or erase source' );
$revisions = wp_get_post_revisions( $id );
$original_revision = reset( $revisions );
quietype_test_assert( $original_revision && $original_revision->post_content_filtered === $source, 'revisions preserve Markdown source' );

require_once ABSPATH . 'wp-admin/includes/post.php';
$autosave_source = $source . "\n\nAutosave \\alpha and **bold**.";
$autosave = array( 'post_id' => $id, 'post_type' => 'post', 'post_title' => 'Autosave title', 'content' => $autosave_source, '_wpnonce' => wp_create_nonce( 'update-post_' . $id ), 'quietype_markdown_nonce' => wp_create_nonce( 'quietype_markdown_' . $id ) );
$_POST = wp_slash( array( 'data' => array( 'wp_autosave' => $autosave ) ) );
$autosave_id = wp_autosave( $autosave );
$_POST = array();
quietype_test_assert( ! is_wp_error( $autosave_id ) && get_post( $autosave_id )->post_content_filtered === $autosave_source, 'heartbeat autosave retains original source' );
wp_restore_post_revision( $original_revision->ID );
quietype_test_assert( get_post( $id )->post_content_filtered === $source && get_post( $id )->post_content === $html && quietype_markdown_post( $id ), 'revision restore restores source, HTML and editor ownership together' );

// Published posts autosave into a revision, not the public article.
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
$_POST = wp_slash( array( 'data' => array( 'wp_autosave' => $autosave ) ) );
$published_autosave_id = wp_autosave( $autosave );
$_POST = array();
quietype_test_assert( ! is_wp_error( $published_autosave_id ) && wp_is_post_autosave( $published_autosave_id ) === $id, 'published Markdown uses a separate autosave revision' );
quietype_test_assert( get_post( $published_autosave_id )->post_content_filtered === $autosave_source && get_post( $published_autosave_id )->post_content === quietype_markdown_render( $autosave_source ), 'published autosave revision stores source and HTML' );
quietype_test_assert( get_post( $id )->post_content === $html, 'autosave never publishes unsaved changes' );

$_POST = wp_slash( array( 'post_ID' => $id, 'quietype_markdown_nonce' => wp_create_nonce( 'quietype_markdown_' . $id ), 'content' => '' ) );
wp_update_post( array( 'ID' => $id, 'post_content' => '' ) );
$_POST = array();
quietype_test_assert( '' === get_post( $id )->post_content_filtered && quietype_markdown_post( $id ), 'intentionally empty source is saved, not mistaken for a missing field' );
wp_restore_post_revision( $original_revision->ID );

update_option( 'quietype_markdown_enabled', false );
quietype_test_assert( get_post( $id, OBJECT, 'edit' )->post_content === esc_textarea( $html ), 'switching module off exposes normal HTML, not raw Markdown' );
wp_update_post( array( 'ID' => $id, 'post_content' => '<p>Edited as HTML</p>' ) );
update_option( 'quietype_markdown_enabled', true );
quietype_test_assert( ! quietype_markdown_post( $id ), 'later HTML edits are not overwritten by stale source' );
quietype_test_assert( get_post( $id )->post_content_filtered === $source, 'stale original source remains stored for recovery' );
wp_delete_post( $id, true );

$legacy_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => '<p>Legacy</p>', 'post_content_filtered' => '**Legacy**', 'meta_input' => array( '_wpcom_is_markdown' => true ) ) );
quietype_test_assert( quietype_markdown_post( $legacy_id ), 'Editor.md original content is recognized without rewriting it' );
$_POST = array( 'post_ID' => $legacy_id, 'quietype_markdown_nonce' => 'invalid', 'content' => 'forged' );
quietype_test_assert( is_wp_error( quietype_markdown_request( $legacy_id ) ), 'invalid nonce cannot access the Markdown pipeline' );
$_POST['quietype_markdown_nonce'] = wp_create_nonce( 'quietype_markdown_' . $legacy_id );
quietype_test_assert( is_wp_error( quietype_markdown_request( $legacy_id + 1 ) ), 'nonce cannot be reused for another post' );
wp_set_current_user( 0 );
quietype_test_assert( is_wp_error( quietype_markdown_request( $legacy_id ) ), 'anonymous users cannot parse or save private draft source' );
wp_set_current_user( 1 );
$_POST = array();
wp_delete_post( $legacy_id, true );
$plain_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => '<!-- wp:paragraph --><p>Not Markdown</p><!-- /wp:paragraph -->' ) );
quietype_test_assert( ! quietype_markdown_post( $plain_id ), 'existing Gutenberg/HTML articles are never automatically converted' );
wp_delete_post( $plain_id, true );
update_option( 'quietype_markdown_enabled', $markdown_setting );
echo "Optional Markdown parser and persistence checks passed.\n";
