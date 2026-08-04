<?php
/**
 * Packages administration page template.
 *
 * @package UpdatePulse_Server
 *
 * @var string                                          $header         Rendered admin settings header.
 * @var \Anyape\UpdatePulse\Server\Table\Packages_Table $packages_table Packages list table.
 * @var string                                          $cache_size     Formatted cache directory size.
 * @var string                                          $logs_size      Formatted logs directory size.
 * @var array                                           $package_rows   Prepared package records.
 * @var string                                          $packages_dir   Package storage directory.
 * @var array                                           $vcs_options    Available VCS configurations.
 * @var array                                           $options        Package settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap upserv-wrap">
	<?php echo $header ? wp_kses_post( $header ) : ''; ?>
	<form autocomplete="off" id="upserv-packages-list" action="" method="post">
		<h3><?php esc_html_e( 'Packages', 'updatepulse-server' ); ?></h3>
		<?php $packages_table->search_box( 'Search', 'updatepulse-server' ); ?>
		<?php $packages_table->display(); ?>
		<?php if ( $options['use_vcs'] ) : ?>
		<ul class="description">
			<li>
				<?php
				printf(
					// translators: %s <a href="admin.php?page=upserv-page-help">initialize packages</a>
					esc_html__( 'It is necessary to %s associated with a Version Control System for them to be available in UpdatePulse Server.', 'updatepulse-server' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=upserv-page-help' ) ) . '">' . esc_html__( 'register packages', 'updatepulse-server' ) . '</a>'
				);
				?>
			</li>
			<li>
				<?php esc_html_e( 'If a registered package is deleted using this interface, it needs to be registered again to become available in UpdatePulse Server.', 'updatepulse-server' ); ?>
			</li>
		</ul>
		<?php endif; ?>
	</form>
	<br>
	<hr>
	<?php do_action( 'upserv_template_package_manager_option_before_add_packages' ); ?>
	<h3><?php esc_html_e( 'Add Packages', 'updatepulse-server' ); ?></h3>
	<table class="form-table upserv-add-packages">
		<?php if ( $options['use_vcs'] ) : ?>
		<tr>
			<th>
				<label for="upserv_register_package_slug"><?php esc_html_e( 'Register a package using a VCS', 'updatepulse-server' ); ?></label>
			</th>
			<td>
				<div class="register-package-container">
					<input type="text" id="upserv_register_package_slug" placeholder="<?php echo esc_attr( 'package-slug' ); ?>" name="upserv_register_package_slug" value="">
					<select id="upserv_vcs_select">
						<option value=""><?php esc_html_e( 'Select a VCS', 'updatepulse-server' ); ?></option>
						<?php

						foreach ( $vcs_options as $key => $name ) {
							echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $name ) . '</option>';
						}
						?>
					</select>
					<input type="button" id="upserv_register_package_trigger" value="<?php print esc_attr__( 'Get remote package', 'updatepulse-server' ); ?>" class="button button-primary" disabled /><div class="spinner"></div>
				</div>
				<p class="description">
					<?php
					printf(
						// translators: %s is <code>$packages_dir</code>
						esc_html__( 'Get an archive of a package from a Version Control System and put it in the %s directory by entering the package slug.', 'updatepulse-server' ),
						'<code>' . esc_html( $packages_dir ) . '</code>',
					);
					?>
					<br>
					<?php
					printf(
						// translators: %s is <code>package-slug</code>
						esc_html__( 'The repository name should be %s and all the files should be located at the root of the repository.', 'updatepulse-server' ),
						'<code>package-slug</code>',
					);
					?>
					<br>
					<?php
					printf(
						// translators: %1$s is <code>package-slug</code>, %2$s is <code>package-slug.php</code>
						esc_html__( 'In the case of a plugin, the main plugin file must have the same name as the repository name - for example, the main plugin file in %1$s repository would be %2$s.', 'updatepulse-server' ),
						'<code>package-slug</code>',
						'<code>package-slug.php</code>',
					);
					?>
					<br>
					<?php esc_html_e( 'Using this method adds the package to the list if not present or forcefully downloads its latest version from the Version Control System and overwrites the existing package.', 'updatepulse-server' ); ?>
					<br>
					<?php esc_html_e( 'Note: registered packages get overwritten automatically with their counterpart from the Version Control System when a newer version is made available.', 'updatepulse-server' ); ?>
				</p>
			</td>
		</tr>
		<?php endif; ?>
		<tr id="upserv_manual_package_upload_dropzone">
			<th>
				<label for="upserv_manual_package_upload"><?php esc_html_e( 'Upload a package', 'updatepulse-server' ); ?>
				<?php if ( $options['use_vcs'] ) : ?>
					<?php esc_html_e( ' (discouraged)', 'updatepulse-server' ); ?>
				<?php endif; ?>
				</label>
			</th>
			<td>
				<input class="input-file hidden" type="file" id="upserv_manual_package_upload" name="upserv_manual_package_upload" value=""><label for="upserv_manual_package_upload" class="button"><?php esc_html_e( 'Choose package archive', 'updatepulse-server' ); ?></label> <input type="text" id="upserv_manual_package_upload_filename" placeholder="package-slug.zip" value="" disabled> <input type="button" value="<?php print esc_attr_e( 'Upload package', 'updatepulse-server' ); ?>" class="button button-primary manual-package-upload-trigger" id="upserv_manual_package_upload_trigger" disabled /><div class="spinner"></div>
				<p class="description">
					<?php
					printf(
						// translators: %s is <code>$packages_dir</code>
						esc_html__( 'Add a package zip archive to the %s directory.', 'updatepulse-server' ),
						'<code>' . esc_html( $packages_dir ) . '</code>',
					);
					?>
					<br>
					<?php esc_html_e( 'The archive needs to be a valid generic package, or a valid WordPress plugin or theme package.', 'updatepulse-server' ); ?>
					<br>
					<?php
					printf(
						// translators: %1$s is <code>package-slug.zip</code>, %2$s is <code>package-slug.php</code>
						esc_html__( 'In the case of a plugin, the main plugin file must have the same name as the zip archive - for example, the main plugin file in %1$s would be %2$s.', 'updatepulse-server' ),
						'<code>package-slug.zip</code>',
						'<code>package-slug.php</code>',
					);
					?>
					<br>
					<?php esc_html_e( 'This method adds the package to the list if it is not already present, or it overwrites the existing package.', 'updatepulse-server' ); ?>
					<?php if ( $options['use_vcs'] ) : ?>
					<br>
						<?php esc_html_e( 'Note: packages uploaded this way get updated only by manually uploading a new version again.', 'updatepulse-server' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	</table>
	<hr>
	<form autocomplete="off" action="" method="post">
		<?php do_action( 'upserv_template_package_manager_option_before_miscellaneous' ); ?>
		<h3><?php esc_html_e( 'Miscellaneous', 'updatepulse-server' ); ?></h3>
		<table class="form-table general-options">
			<tr>
				<th>
					<label for="upserv_archive_max_size"><?php esc_html_e( 'Archive max size (in MB)', 'updatepulse-server' ); ?></label>
				</th>
				<td>
					<input class="regular-text" type="number" id="upserv_archive_max_size" name="upserv_archive_max_size" value="<?php echo esc_attr( $options['archive_max_size'] ); ?>">
					<p class="description">
						<?php esc_html_e( 'Maximum file size when uploading or downloading packages.', 'updatepulse-server' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="upserv_cache_max_size"><?php esc_html_e( 'Cache max size (in MB)', 'updatepulse-server' ); ?></label>
				</th>
				<td>
					<input class="regular-text" type="number" id="upserv_cache_max_size" name="upserv_cache_max_size" value="<?php echo esc_attr( $options['cache_max_size'] ); ?>"> <input type="button" value="<?php print esc_attr_e( 'Force Clean', 'updatepulse-server' ); ?> (<?php print esc_attr( $cache_size ); ?>)" class="button ajax-trigger" data-action="force_clean" data-type="cache" />
					<p class="description">
						<?php
						printf(
							// translators: %s is <code>cache_dir_path</code>
							esc_html__( 'Maximum size in MB for the %s directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.', 'updatepulse-server' ),
							'<code>' . esc_html( upserv_get_cache_data_dir() ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="upserv_logs_max_size"><?php esc_html_e( 'Logs max size (in MB)', 'updatepulse-server' ); ?></label>
				</th>
				<td>
					<input class="regular-text" type="number" id="upserv_logs_max_size" name="upserv_logs_max_size" value="<?php echo esc_attr( $options['logs_max_size'] ); ?>"> <input type="button" value="<?php print esc_attr_e( 'Force Clean', 'updatepulse-server' ); ?> (<?php print esc_attr( $logs_size ); ?>)" class="button ajax-trigger" data-action="force_clean" data-type="logs" />
					<p class="description">
						<?php
						printf(
							// translators: %s is <code>logs_dir_path</code>
							esc_html__( 'Maximum size in MB for the %s directory. If the size of the directory grows larger, its content will be deleted at next cron run (checked hourly). The size indicated in the "Force Clean" button is the real current size.', 'updatepulse-server' ),
							'<code>' . esc_html( upserv_get_logs_data_dir() ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="upserv_settings_section" value="general-options">
		<?php wp_nonce_field( 'upserv_plugin_options', 'upserv_plugin_options_handler_nonce' ); ?>
		<p class="submit">
			<input type="submit" name="upserv_options_save" value="<?php esc_attr_e( 'Save', 'updatepulse-server' ); ?>" class="button button-primary" />
		</p>
	</form>
</div>
<div id="upserv_modal_package_details" class='upserv-modal upserv-modal-package-details hidden'>
	<div class='upserv-modal-content'>
		<div class='upserv-modal-header'>
			<span class='upserv-modal-close'>&times;</span>
			<h2></h2>
		</div>
		<div class='upserv-modal-body'>
			<pre></pre>
		</div>
	</div>
</div>
