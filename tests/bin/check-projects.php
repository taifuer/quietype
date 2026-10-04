<?php
/** Project checks; loaded by check-wordpress.php in the disposable site only. */

$project = get_page_by_path( 'quietype-project-1', OBJECT, 'project' );
quietype_test_assert( $project instanceof WP_Post, 'project fixtures must exist' );
quietype_test_assert( '数据看板' === quietype_project_type_name( $project->ID ), 'explicit project type should be displayed' );
quietype_test_assert( false === strpos( get_permalink( $project ), '/quietype-project-1' ), 'projects must link to the archive, not a standalone detail' );
quietype_test_assert( false !== strpos( get_permalink( $project ), '#project-' . $project->ID ), 'project view links must include their record anchor' );
quietype_test_assert( '' === quietype_sanitize_project_url( 'javascript:alert(1)' ), 'executable project links must be rejected' );
quietype_test_assert( '' === quietype_sanitize_project_url( 'data:text/html,<script>alert(1)</script>' ), 'data links must be rejected' );
quietype_test_assert( 'https://example.com/tool' === quietype_sanitize_project_url( 'https://example.com/tool' ), 'HTTPS links should survive sanitization' );
quietype_test_assert( 'C++, PHP, JavaScript' === quietype_sanitize_project_languages( 'C++，PHP, PHP, JavaScript' ), 'project languages must preserve punctuation and remove duplicates' );
quietype_test_assert( '' === quietype_sanitize_project_source_state( 'unknown' ), 'source status must be explicitly chosen' );
quietype_test_assert( ! is_taxonomy_viewable( 'project_type' ), 'project types must not create public taxonomy pages' );
quietype_test_assert( ! isset( apply_filters( 'wp_sitemaps_post_types', array( 'project' => get_post_type_object( 'project' ) ) )['project'] ), 'single projects must stay out of the sitemap' );

$default_project = get_page_by_path( 'quietype-project-2', OBJECT, 'project' );
$previous_default = get_option( 'quietype_projects_default_type', 0 );
$custom = wp_insert_term( '测试自定类型', 'project_type', array( 'slug' => 'project-test-custom' ) );
update_option( 'quietype_projects_default_type', $custom['term_id'] );
quietype_test_assert( '测试自定类型' === quietype_project_type_name( $default_project->ID ), 'unset records should use the configured default' );
wp_update_term( $custom['term_id'], 'project_type', array( 'name' => '自定义类型改名' ) );
quietype_test_assert( '自定义类型改名' === quietype_project_type_name( $default_project->ID ), 'default type renames should be reflected' );
wp_delete_term( $custom['term_id'], 'project_type' );
quietype_test_assert( '个人项目' === quietype_project_type_name( $default_project->ID ), 'deleted default types should safely fall back' );
update_option( 'quietype_projects_default_type', $previous_default );

$original_url = get_post_meta( $project->ID, '_quietype_project_url', true );
$_POST['quietype_project_url'] = 'https://example.com/rejected';
quietype_save_project( $project->ID );
quietype_test_assert( $original_url === get_post_meta( $project->ID, '_quietype_project_url', true ), 'saving metadata must require a nonce and capability' );
unset( $_POST['quietype_project_url'] );

$requests = 0;
add_filter( 'quietype_project_archive_cache_purge_endpoint', static function () { return 'http://127.0.0.1/internal/projects'; } );
$intercept = static function ( $response, $args, $url ) use ( &$requests ) {
	if ( 'http://127.0.0.1/internal/projects' === $url ) {
		++$requests;
		quietype_test_assert( 'PURGE' === $args['method'], 'project cache integration must send PURGE' );
		return array( 'response' => array( 'code' => 200 ), 'body' => '' );
	}
	return $response;
};
add_filter( 'pre_http_request', $intercept, 10, 3 );
quietype_purge_project_cache();
quietype_test_assert( 1 === $requests, 'configured archive purge should be invoked' );
remove_filter( 'pre_http_request', $intercept );
remove_all_filters( 'quietype_project_archive_cache_purge_endpoint' );
