<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap upserv-wrap">
	<?php echo $header ? wp_kses_post( $header ) : ''; ?>
	<?php if ( $options['use_licenses'] ) : ?>
	<form autocomplete="off" id="upserv-licenses-list" action="" method="post">
		<h3><?php esc_html_e( 'Licenses', 'updatepulse-server' ); ?></h3>
		<?php $licenses_table->search_box( 'Search', 'updatepulse-server' ); ?>
		<?php $licenses_table->display(); ?>
	</form>
	<div id="upserv_license_panel" class="postbox">
		<div class="inside">
			<form autocomplete="off" id="upserv_license" class="panel" action="" method="post">
				<h3><span class='upserv-add-license-label'><?php esc_html_e( 'Add License', 'updatepulse-server' ); ?></span><span class='upserv-edit-license-label'><?php esc_html_e( 'Edit License', 'updatepulse-server' ); ?> (ID <span id="upserv_license_id"></span>)</span><span class="small"> (<a class="close-panel reset" href="#"><?php esc_html_e( 'cancel', 'updatepulse-server' ); ?></a>)</span></h3>
				<div class="license-column-container">
					<div class="license-column">
						<table class="form-table">
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'License Key', 'updatepulse-server' ); ?> <span class="description"><?php esc_html_e( '(required)', 'updatepulse-server' ); ?></span></th>
								<td>
									<input type="text" id="upserv_license_key" data-random_key="<?php echo esc_html( bin2hex( openssl_random_pseudo_bytes( 16 ) ) ); ?>" name="upserv_license_key" class="no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The unique license key. This auto-generated value can be changed as long as it is unique in the database.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Package Type', 'updatepulse-server' ); ?></th>
								<td>
									<select id="upserv_license_package_type">
										<option value="plugin"><?php esc_html_e( 'Plugin' ); ?></option>
										<option value="theme"><?php esc_html_e( 'Theme' ); ?></option>
										<option value="generic"><?php esc_html_e( 'Generic', 'updatepulse-server' ); ?></option>
									</select>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Package Slug', 'updatepulse-server' ); ?> <span class="description"><?php esc_html_e( '(required)', 'updatepulse-server' ); ?></th>
								<td>
									<input type="text" id="upserv_license_package_slug" name="upserv_license_package_slug" class="no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The plugin, theme, or generic package slug. Only alphanumeric characters and dashes are allowed.', 'updatepulse-server' ); ?>
										<br/>
										<?php esc_html_e( 'Example of valid value: package-slug', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'License Status', 'updatepulse-server' ); ?></th>
								<td>
									<select id="upserv_license_status">
										<?php
										foreach ( $status_options as $value => $label ) {
											echo '<option value="' . esc_html( $value ) . '">' . esc_html( $label ) . '</option>';
										}
										?>
									</select>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Date Created', 'updatepulse-server' ); ?> <span class="description"><?php esc_html_e( '(required)', 'updatepulse-server' ); ?></th>
								<td>
									<input type="date" id="upserv_license_date_created" name="upserv_license_date_created" class="upserv-license-date no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'Creation date of the license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top" class="upserv-license-show-if-edit">
								<th scope="row"><?php esc_html_e( 'Date Renewed', 'updatepulse-server' ); ?></th>
								<td>
									<input type="date" id="upserv_license_date_renewed" name="upserv_license_date_renewed" class="upserv-license-date no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'Date of the last time the license was renewed.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Expiration Date', 'updatepulse-server' ); ?></th>
								<td>
									<input type="date" id="upserv_license_date_expiry" name="upserv_license_date_expiry" class="upserv-license-date no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'Expiration date of the license. Leave empty for no expiry.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
					<div class="license-column">
						<table class="form-table">
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Registered Email', 'updatepulse-server' ); ?> <span class="description"><?php esc_html_e( '(required)', 'updatepulse-server' ); ?></th>
								<td>
									<input type="email" id="upserv_license_registered_email" name="upserv_license_registered_email" class="no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The email registered with this license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Owner Name', 'updatepulse-server' ); ?></th>
								<td>
									<input type="text" id="upserv_license_owner_name" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The full name of the owner of the license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Company', 'updatepulse-server' ); ?></th>
								<td>
									<input type="text" id="upserv_license_owner_company" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The company of the owner of this license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Max. Allowed Domains', 'updatepulse-server' ); ?> <span class="description"><?php esc_html_e( '(required)', 'updatepulse-server' ); ?></th>
								<td>
									<input type="number" min="1" id="upserv_license_max_allowed_domains" name="upserv_license_max_allowed_domains" class="no-submit" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'The maximum number of domains on which this license can be used.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top" class="upserv-license-show-if-edit">
								<th scope="row"><?php esc_html_e( 'Registered Domains', 'updatepulse-server' ); ?></th>
								<td>
									<div id="upserv_license_registered_domains">
										<ul class="upserv-domains-list">
											<li class='upserv-domain-template'>
												<button type="button" class="upserv-remove-domain">
												<span class="upserv-remove-icon" aria-hidden="true"></span>
												</button> <span class="upserv-domain-value"></span>
											</li>
										</ul>
										<span class="upserv-no-domain description"><?php esc_html_e( 'None', 'updatepulse-server' ); ?></span>
									</div>
									<p class="description">
										<?php esc_html_e( 'Domains currently allowed to use this license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Transaction ID', 'updatepulse-server' ); ?></th>
								<td>
									<input type="text" id="upserv_license_transaction_id" value="" size="30">
									<p class="description">
										<?php esc_html_e( 'If applicable, the transaction identifier associated to the purchase of the license.', 'updatepulse-server' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>
				<div class="license-form-extra-data clear">
					<h4><?php esc_html_e( 'Extra Data', 'updatepulse-server' ); ?></h4>
					<p class="description">
						<?php esc_html_e( 'Advanced - JSON-formatted custom data to add to the license.', 'updatepulse-server' ); ?><br>
						<?php esc_html_e( 'Typically used by plugins & API integrations; proceed with caution when editing.', 'updatepulse-server' ); ?><br>
					</p>
					<textarea id="upserv_license_data"></textarea>
				</div>
				<div class="license-form-actions clear">
					<?php wp_nonce_field( 'upserv_license_form_nonce', 'upserv_license_form_nonce' ); ?>
					<input type="hidden" id="upserv_license_values" name="upserv_license_values" value="">
					<input type="hidden" id="upserv_license_action" name="upserv_license_action" value="">
					<input type="submit" id="upserv_license_save" class="close-panel button button-primary" value="<?php esc_html_e( 'Save', 'updatepulse-server' ); ?>">
					<input type="button" id="upserv_license_cancel" class="close-panel button" value="<?php esc_html_e( 'Cancel', 'updatepulse-server' ); ?>">
				</div>
			</form>
		</div>
	</div>
	<hr>
	<?php endif; ?>
	<form autocomplete="off" id="upserv-licenses-settings" action="" method="post">
		<table class="form-table">
			<tr>
				<th>
					<label for="upserv_use_licenses"><?php esc_html_e( 'Enable Package Licenses', 'updatepulse-server' ); ?></label>
				</th>
				<td>
					<input type="checkbox" id="upserv_use_licenses" name="upserv_use_licenses" value="1" <?php checked( $options['use_licenses'], 1 ); ?>>
					<p class="description">
						<?php esc_html_e( 'Check to activate license checking when delivering package updates.', 'updatepulse-server' ); ?>
						<br>
						<strong><?php esc_html_e( 'It affects all the packages with a "Require License" license status delivered by this installation of UpdatePulse Server.', 'updatepulse-server' ); ?></strong>
					</p>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field( 'upserv_plugin_options', 'upserv_plugin_options_handler_nonce' ); ?>
		<p class="submit">
			<input type="submit" name="upserv_options_save" value="<?php esc_attr_e( 'Save', 'updatepulse-server' ); ?>" class="button button-primary" />
		</p>
	</form>
</div>
<div id="upserv_modal_license_details" class='upserv-modal upserv-modal-license-details hidden'>
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
