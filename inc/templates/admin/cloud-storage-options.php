<?php
/**
 * Cloud storage options template.
 *
 * @package UpdatePulse_Server
 *
 * @var bool   $use_cloud_storage Whether cloud storage is enabled.
 * @var string $virtual_dir       Virtual package directory.
 * @var array  $options           Cloud storage settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<h3><?php esc_html_e( 'Cloud Storage', 'updatepulse-server' ); ?></h3>
	<table class="form-table upserv-add-packages">
	<tr>
		<th>
			<label for="upserv_use_cloud_storage"><?php esc_html_e( 'Use Cloud Storage', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input type="checkbox" id="upserv_use_cloud_storage" name="upserv_use_cloud_storage" value="1" <?php checked( $use_cloud_storage, 1 ); ?>>
			<p class="description">
				<?php esc_html_e( 'Check this if you wish to use a Cloud Storage Service - S3 Compatible.', 'updatepulse-server' ); ?><br>
				<?php
				printf(
					// translators: %s is the packages folder
					esc_html__( 'If it does not exist, a virtual folder %s will be created in the Storage Unit chosen for package storage.', 'updatepulse-server' ),
					'<code>' . esc_html( $virtual_dir ) . '</code>',
				);
				?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_access_key"><?php esc_html_e( 'Cloud Storage Access Key', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input class="regular-text cloud-storage-setting secret" type="password" autocomplete="new-password" id="upserv_cloud_storage_access_key" name="upserv_cloud_storage_access_key" value="<?php echo esc_attr( $options['access_key'] ); ?>">
			<p class="description">
				<?php esc_html_e( 'The Access Key provided by the Cloud Storage service provider.', 'updatepulse-server' ); ?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_secret_key"><?php esc_html_e( 'Cloud Storage Secret Key', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input class="regular-text cloud-storage-setting secret" type="password" autocomplete="new-password" id="upserv_cloud_storage_secret_key" name="upserv_cloud_storage_secret_key" value="<?php echo esc_attr( $options['secret_key'] ); ?>">
			<p class="description">
				<?php esc_html_e( 'The Secret Key provided by the Cloud Storage service provider.', 'updatepulse-server' ); ?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_endpoint"><?php esc_html_e( 'Cloud Storage Endpoint', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input class="regular-text cloud-storage-setting" type="text" id="upserv_cloud_storage_endpoint" name="upserv_cloud_storage_endpoint" value="<?php echo esc_attr( $options['endpoint'] ); ?>">
			<p class="description">
				<?php
				printf(
					// translators: %1$s is <code>http://</code>, %2$s is <code>https://</code>
					esc_html__( 'The domain (without %1$s or %2$s) of the endpoint for the Cloud Storage Service.', 'updatepulse-server' ),
					'<code>http://</code>',
					'<code>https://</code>',
				);
				?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_unit"><?php esc_html_e( 'Cloud Storage Unit', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input class="regular-text cloud-storage-setting" type="text" id="upserv_cloud_storage_unit" name="upserv_cloud_storage_unit" value="<?php echo esc_attr( $options['storage_unit'] ); ?>">
			<p class="description">
				<?php esc_html_e( 'Usually known as a "bucket" or a "container" depending on the Cloud Storage service provider.', 'updatepulse-server' ); ?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_region"><?php esc_html_e( 'Cloud Storage Region', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input class="regular-text cloud-storage-setting" type="text" id="upserv_cloud_storage_region" name="upserv_cloud_storage_region" value="<?php echo esc_attr( $options['region'] ); ?>">
			<p class="description">
				<?php esc_html_e( 'The region of the Cloud Storage Unit, as indicated by the Cloud Storage service provider.', 'updatepulse-server' ); ?>
			</p>
		</td>
	</tr>
	<tr class="hide-if-no-cloud-storage <?php echo ( $use_cloud_storage ) ? '' : 'hidden'; ?>">
		<th>
			<label for="upserv_cloud_storage_test"><?php esc_html_e( 'Test Cloud Storage Settings', 'updatepulse-server' ); ?></label>
		</th>
		<td>
			<input type="button" value="<?php print esc_attr_e( 'Test Now', 'updatepulse-server' ); ?>" id="upserv_cloud_storage_test" class="button ajax-trigger" data-selector=".cloud-storage-setting" data-action="cloud_storage_test" data-type="none" />
			<p class="description">
				<?php esc_html_e( 'Send a test request to the Cloud Storage Service.', 'updatepulse-server' ); ?>
				<br/>
				<?php esc_html_e( 'The request checks whether the provider is reachable and if the Storage Unit exists and is writable.', 'updatepulse-server' ); ?><br>
				<?php
				printf(
					// translators: %s is the packages folder
					esc_html__( 'If it does not exist, a virtual folder %s will be created in the Storage Unit chosen for package storage.', 'updatepulse-server' ),
					'<code>' . esc_html( $virtual_dir ) . '</code>',
				);
				?>
			</p>
		</td>
	</tr>
</table>
<hr>
