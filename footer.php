</main>
<footer class="site-footer">
	<div class="site-footer__inner">
		<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => 'footer-menu', 'depth' => 1 ) ); ?>
		<?php else : ?>
			<span>以文字保存思考，以留白安放阅读。</span>
		<?php endif; ?>
	</div>
</footer>
<aside class="reading-background" aria-label="阅读背景">
	<button class="reading-background__toggle" type="button" aria-expanded="false" aria-controls="reading-background-options">背景</button>
	<div class="reading-background__options" id="reading-background-options" hidden>
		<span>阅读背景</span>
		<button type="button" data-reading-bg="paper" title="纸白"><i></i><b>纸白</b></button>
		<button type="button" data-reading-bg="warm" title="米白"><i></i><b>米白</b></button>
		<button type="button" data-reading-bg="green" title="浅绿"><i></i><b>浅绿</b></button>
		<button type="button" data-reading-bg="gray" title="青灰"><i></i><b>青灰</b></button>
	</div>
</aside>
<?php wp_footer(); ?>
</body>
</html>
