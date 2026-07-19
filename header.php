<!doctype html>
<html <?php language_attributes(); ?> data-reading-bg="paper">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#fdfdfb">
	<script>document.documentElement.classList.add('js');try{document.documentElement.dataset.readingBg=localStorage.getItem('quietype-reading-bg')||'paper'}catch(e){}</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content">跳到正文</a>
<header class="site-header">
	<div class="site-header__inner">
		<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>，返回首页">
			<span class="site-brand__wordmark">太傅博客</span>
		</a>
		<div class="mobile-header-actions">
			<button class="icon-button nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="打开菜单"><?php echo quietype_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<button class="icon-button search-toggle search-toggle--mobile" type="button" aria-expanded="false" aria-controls="site-search" aria-label="搜索"><?php echo quietype_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
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
			<button class="icon-button search-toggle search-toggle--desktop" type="button" aria-expanded="false" aria-controls="site-search" aria-label="搜索" data-label="搜索"><?php echo quietype_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</nav>
		<button class="nav-backdrop" type="button" aria-label="关闭菜单" hidden></button>
	</div>
	<div class="site-reading-progress" aria-hidden="true"><i></i></div>
	<div class="site-search" id="site-search" hidden>
		<?php get_search_form(); ?>
	</div>
</header>
<main id="content" class="site-main">
