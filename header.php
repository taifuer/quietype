<!doctype html>
<html <?php language_attributes(); ?> data-reading-bg="paper">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f3f1eb">
	<script>try{document.documentElement.dataset.readingBg=localStorage.getItem('quietype-reading-bg')||'paper'}catch(e){}</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content">跳到正文</a>
<header class="site-header">
	<div class="site-header__inner">
		<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<span class="site-brand__mark" aria-hidden="true">傅</span>
			<span class="site-brand__copy">
				<strong><?php bloginfo( 'name' ); ?></strong>
				<small><?php bloginfo( 'description' ); ?></small>
			</span>
		</a>
		<div class="mobile-header-actions">
			<button class="top-button" type="button" aria-label="返回顶部" hidden>顶部</button>
			<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">菜单</button>
		</div>
		<nav class="site-nav" id="site-navigation" aria-label="主导航">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'quietype_menu_fallback',
					'depth'          => 2,
				)
			);
			?>
			<button class="search-toggle" type="button" aria-expanded="false" aria-controls="site-search" aria-label="搜索">搜索</button>
		</nav>
		<button class="nav-backdrop" type="button" aria-label="关闭菜单" hidden></button>
	</div>
	<div class="site-reading-progress" aria-hidden="true"><i></i></div>
	<div class="site-search" id="site-search" hidden>
		<?php get_search_form(); ?>
	</div>
</header>
<main id="content" class="site-main">
