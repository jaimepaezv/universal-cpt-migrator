<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $current_page = isset( $current_page ) ? (string) $current_page : ''; ?>
<nav class="ucm-page-nav" aria-label="<?php esc_attr_e( 'Universal CPT Migrator navigation', 'universal-cpt-migrator' ); ?>">
	<div class="ucm-page-nav__brand">
		<span class="ucm-page-nav__eyebrow"><?php esc_html_e( 'Universal CPT Migrator', 'universal-cpt-migrator' ); ?></span>
		<strong><?php esc_html_e( 'Migration Console', 'universal-cpt-migrator' ); ?></strong>
	</div>
	<div class="ucm-page-nav__links">
		<a class="ucm-page-nav__link <?php echo 'dashboard' === $current_page ? 'is-active' : ''; ?>" <?php echo 'dashboard' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator' ) ); ?>"><?php esc_html_e( 'Dashboard', 'universal-cpt-migrator' ); ?></a>
		<a class="ucm-page-nav__link <?php echo 'export' === $current_page ? 'is-active' : ''; ?>" <?php echo 'export' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-export' ) ); ?>"><?php esc_html_e( 'Export Items', 'universal-cpt-migrator' ); ?></a>
		<a class="ucm-page-nav__link <?php echo 'import' === $current_page ? 'is-active' : ''; ?>" <?php echo 'import' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-import' ) ); ?>"><?php esc_html_e( 'Import Packages', 'universal-cpt-migrator' ); ?></a>
		<a class="ucm-page-nav__link <?php echo 'diagnostics' === $current_page ? 'is-active' : ''; ?>" <?php echo 'diagnostics' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-diagnostics' ) ); ?>"><?php esc_html_e( 'Diagnostics', 'universal-cpt-migrator' ); ?></a>
		<a class="ucm-page-nav__link <?php echo 'logs' === $current_page ? 'is-active' : ''; ?>" <?php echo 'logs' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-logs' ) ); ?>"><?php esc_html_e( 'Logs', 'universal-cpt-migrator' ); ?></a>
		<a class="ucm-page-nav__link <?php echo 'settings' === $current_page ? 'is-active' : ''; ?>" <?php echo 'settings' === $current_page ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'universal-cpt-migrator' ); ?></a>
	</div>
</nav>
