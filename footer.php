</main>
<?php if ( has_nav_menu( 'prefooter' ) ) : ?>
	<nav class="prefooter-nav" aria-label="页尾导航">
		<div class="prefooter-nav__inner">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'prefooter',
					'container'      => false,
					'menu_class'     => 'prefooter-nav__menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</div>
	</nav>
<?php endif; ?>
<footer class="site-footer">
	<div class="site-footer__inner">
		<div class="footer-legal">
			<?php $current_year = (int) gmdate( 'Y' ); ?>
			<?php $start_year = (int) quietype_get_setting( 'quietype_start_year', $current_year ); ?>
			<?php $year_label = $start_year < $current_year ? $start_year . '–' . $current_year : (string) $current_year; ?>
			<span class="footer-copyright">© <?php echo esc_html( $year_label ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></span>
			<div class="footer-policies">
				<?php $privacy_url = get_privacy_policy_url(); ?>
				<?php if ( $privacy_url ) : ?><a class="footer-privacy" href="<?php echo esc_url( $privacy_url ); ?>">隐私政策</a><?php endif; ?>
				<?php $icp_number = quietype_get_setting( 'quietype_icp_number', '' ); ?>
				<?php if ( $icp_number ) : ?><a class="footer-icp" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $icp_number ); ?></a><?php endif; ?>
			</div>
		</div>
		<?php $github_url = quietype_get_setting( 'quietype_github_url', '' ); ?>
		<?php $contact_email = quietype_get_setting( 'quietype_contact_email', '' ); ?>
		<?php if ( $github_url || $contact_email ) : ?>
			<nav class="footer-contact" aria-label="联系方式">
				<?php if ( $contact_email ) : ?><a href="mailto:<?php echo esc_attr( antispambot( $contact_email ) ); ?>" aria-label="发送邮件"><?php echo quietype_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
				<?php if ( $github_url ) : ?><a href="<?php echo esc_url( $github_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub"><?php echo quietype_icon( 'github' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</footer>
<aside class="reading-tools" aria-label="阅读工具">
	<div class="reading-background">
		<button class="tool-button reading-background__toggle" type="button" aria-expanded="false" aria-controls="reading-background-options" aria-label="切换阅读背景" data-label="背景"><?php echo quietype_icon( 'palette' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<div class="reading-background__options" id="reading-background-options" hidden>
			<span>阅读背景</span>
			<button type="button" data-reading-bg="paper"><i></i><b>纸白</b><small>清透暖白</small></button>
			<button type="button" data-reading-bg="warm"><i></i><b>米杏</b><small>柔和温暖</small></button>
			<button type="button" data-reading-bg="green"><i></i><b>浅绿</b><small>舒缓自然</small></button>
		</div>
	</div>
	<div class="reading-tools__navigation">
		<button class="tool-button top-button" type="button" aria-label="返回顶部" data-label="顶部" hidden><?php echo quietype_icon( 'up' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<button class="tool-button bottom-button" type="button" aria-label="前往页面底部" data-label="底部"><?php echo quietype_icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	</div>
</aside>
<?php wp_footer(); ?>
</body>
</html>
