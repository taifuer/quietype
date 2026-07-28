<?php
/**
 * Lightweight, conflict-aware SEO metadata.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Do not compete with dedicated SEO plugins. */
function quietype_has_dedicated_seo_plugin() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| class_exists( 'The_SEO_Framework\Load' );
}

/** Keep disabled single-author archives out of the WordPress sitemap index. */
function quietype_filter_sitemap_provider( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}
	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'quietype_filter_sitemap_provider', 10, 2 );

/** Register the two public archive pages without exposing redirect-only records. */
function quietype_register_archive_sitemap_provider() {
	if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
		return;
	}

	$provider = new class() extends WP_Sitemaps_Provider {
		public function __construct() {
			$this->name        = 'quietypearchives';
			$this->object_type = 'archive';
		}

		public function get_url_list( $page_num, $object_subtype = '' ) {
			if ( 1 !== (int) $page_num ) {
				return array();
			}
			$entries = array();
			foreach ( array( 'book', 'photo' ) as $post_type ) {
				$latest = get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'fields'         => 'ids',
					)
				);
				$archive_url = get_post_type_archive_link( $post_type );
				if ( ! $latest || ! $archive_url ) {
					continue;
				}
				$entry   = array( 'loc' => $archive_url );
				$lastmod = get_post_modified_time( DATE_W3C, true, $latest[0] );
				if ( $lastmod ) {
					$entry['lastmod'] = $lastmod;
				}
				$entries[] = $entry;
			}
			return $entries;
		}

		public function get_max_num_pages( $object_subtype = '' ) {
			return 1;
		}
	};

	wp_register_sitemap_provider( 'quietypearchives', $provider );
}
add_action( 'wp_sitemaps_init', 'quietype_register_archive_sitemap_provider' );

/** Normalize text for a search-result description. */
function quietype_normalize_meta_text( $value, $length = 180 ) {
	$value = html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $value ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	$value = trim( preg_replace( '/\s+/u', ' ', $value ) );
	if ( mb_strlen( $value ) > $length ) {
		$value = rtrim( mb_substr( $value, 0, $length - 1 ) ) . '…';
	}
	return $value;
}

/** Resolve the best description for the current request. */
function quietype_get_meta_description() {
	if ( is_front_page() || is_home() ) {
		$description = quietype_get_setting( 'quietype_seo_description', '' );
		return quietype_normalize_meta_text( $description ?: get_bloginfo( 'description' ) );
	}
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$custom  = get_post_meta( $post_id, '_quietype_seo_description', true );
		if ( $custom ) {
			return quietype_normalize_meta_text( $custom );
		}
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$source = $post->post_excerpt ?: get_the_excerpt( $post );
			if ( $source ) {
				return quietype_normalize_meta_text( $source );
			}
		}
	}
	if ( is_post_type_archive( 'book' ) ) {
		$description = quietype_archive_page_text( 'book', 'intro' );
		if ( $description ) {
			return quietype_normalize_meta_text( $description );
		}
	}
	if ( is_post_type_archive( 'photo' ) ) {
		$description = quietype_archive_page_text( 'photo', 'intro' );
		if ( $description ) {
			return quietype_normalize_meta_text( $description );
		}
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();
		if ( $description ) {
			return quietype_normalize_meta_text( $description );
		}
	}
	if ( is_post_type_archive() || is_date() || is_author() ) {
		$description = get_the_archive_description();
		if ( $description ) {
			return quietype_normalize_meta_text( $description );
		}
	}
	if ( is_search() ) {
		return quietype_normalize_meta_text( sprintf( '“%s”的站内搜索结果。', get_search_query() ) );
	}
	$description = quietype_get_setting( 'quietype_seo_description', '' );
	return quietype_normalize_meta_text( $description ?: get_bloginfo( 'description' ) );
}

/** Use per-entry keywords, tags, categories, then site-level keywords. */
function quietype_get_meta_keywords() {
	$keywords = array();
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$custom  = get_post_meta( $post_id, '_quietype_seo_keywords', true );
		if ( $custom ) {
			$keywords = preg_split( '/[,，、]+/u', $custom );
		} elseif ( 'post' === get_post_type( $post_id ) ) {
			$keywords = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			if ( ! $keywords ) {
				$keywords = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$keywords[] = $term->name;
		}
	}
	$global = preg_split( '/[,，、]+/u', quietype_get_setting( 'quietype_seo_keywords', '' ) );
	$keywords = array_filter( array_map( 'trim', array_merge( (array) $keywords, (array) $global ) ) );
	return implode( ',', array_unique( $keywords ) );
}

/** Resolve the canonical social URL without replacing WordPress core canonical tags. */
function quietype_get_meta_url() {
	if ( is_singular() ) {
		return get_permalink();
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$url = get_term_link( get_queried_object() );
		return is_wp_error( $url ) ? home_url( '/' ) : $url;
	}
	if ( is_home() || is_front_page() ) {
		return home_url( '/' );
	}
	return get_pagenum_link();
}

/** Resolve a useful social preview without requiring every article to set a featured image. */
function quietype_get_social_image() {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		if ( has_post_thumbnail( $post_id ) ) {
			return (string) wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
		}
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$images = quietype_remote_image_urls( $post->post_content );
			if ( $images ) {
				return $images[0];
			}
		}
	}
	$custom = (string) quietype_get_setting( 'quietype_social_image_url', '' );
	return $custom ?: get_template_directory_uri() . '/screenshot.png';
}

/** Print concise metadata only when no dedicated SEO plugin owns the page. */
function quietype_print_seo_meta() {
	if ( is_admin() || is_feed() || is_robots() || ! quietype_get_setting( 'quietype_seo_enabled', true ) || quietype_has_dedicated_seo_plugin() ) {
		return;
	}
	$description = quietype_get_meta_description();
	$keywords    = quietype_get_meta_keywords();
	$title       = wp_get_document_title();
	$url         = quietype_get_meta_url();
	$type        = is_singular( 'post' ) ? 'article' : 'website';
	$image       = quietype_get_social_image();

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
	}
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	if ( $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $description ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $image ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( DATE_W3C ) ) . '">' . "\n";
		foreach ( wp_get_post_tags( get_queried_object_id(), array( 'fields' => 'names' ) ) as $tag ) {
			echo '<meta property="article:tag" content="' . esc_attr( $tag ) . '">' . "\n";
		}
	}

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => is_singular( 'post' ) ? 'BlogPosting' : ( is_front_page() || is_home() ? 'WebSite' : 'WebPage' ),
		'name'        => $title,
		'url'         => $url,
		'inLanguage'  => get_bloginfo( 'language' ),
		'description' => $description,
	);
	if ( is_singular( 'post' ) ) {
		$post = get_queried_object();
		$schema['headline']         = get_the_title();
		$schema['datePublished']    = get_the_date( DATE_W3C );
		$schema['dateModified']     = get_the_modified_date( DATE_W3C );
		$schema['mainEntityOfPage'] = array( '@type' => 'WebPage', '@id' => $url );
		$schema['author']           = array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) );
		$schema['publisher']        = array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) );
		$site_icon                  = get_site_icon_url( 512 );
		if ( $site_icon ) {
			$schema['publisher']['logo'] = array( '@type' => 'ImageObject', 'url' => $site_icon );
		}
		if ( $image ) {
			$schema['image'] = $image;
		}
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array_filter( $schema ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'quietype_print_seo_meta', 2 );

/** Add optional per-entry overrides; blank fields keep automatic behavior. */
function quietype_add_seo_meta_box() {
	add_meta_box( 'quietype-seo', 'Quietype SEO', 'quietype_render_seo_meta_box', array( 'post', 'page' ), 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'quietype_add_seo_meta_box' );

function quietype_render_seo_meta_box( $post ) {
	wp_nonce_field( 'quietype_save_seo_meta', 'quietype_seo_nonce' );
	?>
	<p><label for="quietype_seo_post_description"><strong>自定义描述</strong></label></p>
	<textarea class="widefat" id="quietype_seo_post_description" name="quietype_seo_post_description" rows="3" maxlength="300" placeholder="留空则使用文章摘要或自动提取正文"><?php echo esc_textarea( get_post_meta( $post->ID, '_quietype_seo_description', true ) ); ?></textarea>
	<p><label for="quietype_seo_post_keywords"><strong>自定义关键词</strong></label></p>
	<input class="widefat" id="quietype_seo_post_keywords" name="quietype_seo_post_keywords" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_quietype_seo_keywords', true ) ); ?>" placeholder="留空则使用文章标签，英文逗号分隔">
	<?php
}

/** Save per-entry SEO overrides with standard autosave and capability guards. */
function quietype_save_seo_meta( $post_id ) {
	if ( ! isset( $_POST['quietype_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_seo_nonce'] ) ), 'quietype_save_seo_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$description = isset( $_POST['quietype_seo_post_description'] ) ? quietype_sanitize_seo_description( wp_unslash( $_POST['quietype_seo_post_description'] ) ) : '';
	$keywords    = isset( $_POST['quietype_seo_post_keywords'] ) ? quietype_sanitize_seo_keywords( wp_unslash( $_POST['quietype_seo_post_keywords'] ) ) : '';
	foreach ( array( '_quietype_seo_description' => $description, '_quietype_seo_keywords' => $keywords ) as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post', 'quietype_save_seo_meta' );
