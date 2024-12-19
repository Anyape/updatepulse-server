<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap wppus-wrap">
	<?php WP_Packages_Update_Server::get_instance()->display_settings_header( $result ); ?>
	<form autocomplete="off" id="wppus-api-settings" action="" method="post">
		<h3><?php esc_html_e( 'Package API', 'wppus' ); ?></h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="wppus_package_private_api_keys"><?php esc_html_e( 'Private API Keys', 'wppus' ); ?></label>
				</th>
				<td>
					<div class="api-keys-multiple package">
						<div class="api-keys-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-api-key-item-id" placeholder="<?php esc_attr_e( 'Package Key ID' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-api-action="all"> <?php esc_html_e( 'Grant access to all the package actions', 'wppus' ); ?> <code>(all)</code></label>
								</div>
								<?php if ( ! empty( $package_api_actions ) ) : ?>
									<?php foreach ( $package_api_actions as $action_id => $label ) : ?>
									<div class="event-container <?php echo esc_attr( $action_id ); ?>">
										<label class="top-level"><input type="checkbox" data-api-action="<?php echo esc_attr( $action_id ); ?>"> <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $action_id ); ?>)</code></label>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<button disabled="disabled" class="api-keys-add button" type="button"><?php esc_html_e( 'Add a Package API Key' ); ?></button>
						</div>
						<input type="hidden" class="api-key-values" id="wppus_package_private_api_keys" name="wppus_package_private_api_keys" value="<?php echo esc_attr( get_option( 'wppus_package_private_api_keys', '{}' ) ); ?>">
					</div>
					<p class="description">
						<?php esc_html_e( 'Used to get tokens for package administration requests and requests of signed URLs used to download packages.', 'wppus' ); ?>
						<br>
						<?php
						printf(
							// translators: %1$s is <code>-</code>, %2$s is <code>_</code>
							esc_html__( 'The Package Key ID must contain only numbers, letters, %1$s and %2$s.', 'wppus' ),
							'<code>-</code>',
							'<code>_</code>',
						);
						?>
						<br>
						<strong><?php esc_html_e( 'WARNING: Keep these keys secret, do not share any of them with customers!', 'wppus' ); ?></strong>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="wppus_package_private_api_ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'wppus' ); ?></label>
				</th>
				<td>
					<textarea class="ip-whitelist" id="wppus_package_private_api_ip_whitelist" name="wppus_package_private_api_ip_whitelist"><?php echo esc_html( implode( "\n", get_option( 'wppus_package_private_api_ip_whitelist', array() ) ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'List of IP addresses and/or CIDRs of remote sites authorized to use the Private API (one IP address or CIDR per line).', 'wprus' ); ?> <br/>
						<?php esc_html_e( 'Leave blank to allow any IP address (not recommended).', 'wprus' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<hr>
		<h3><?php esc_html_e( 'License API', 'wppus' ); ?></h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="wppus_license_private_api_keys"><?php esc_html_e( 'Private API Keys', 'wppus' ); ?></label>
				</th>
				<td>
					<div class="api-keys-multiple">
						<div class="api-keys-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-api-key-item-id" placeholder="<?php esc_attr_e( 'License Key ID' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-api-action="all"> <?php esc_html_e( 'Grant access to all the license actions affecting the records associated with the License API Key', 'wppus' ); ?> <code>(all)</code></label>
								</div>
								<?php if ( ! empty( $license_api_actions ) ) : ?>
									<?php foreach ( $license_api_actions as $action_id => $label ) : ?>
									<div class="event-container <?php echo esc_attr( $action_id ); ?>">
										<label class="top-level"><input type="checkbox" data-api-action="<?php echo esc_attr( $action_id ); ?>"> <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $action_id ); ?>)</code></label>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
								<div class="event-container other">
									<label><input type="checkbox" data-api-action="other"> <?php esc_html_e( 'Also grant access to affect other records (all records)', 'wppus' ); ?> <code>(other)</code></label>
								</div>
							</div>
							<button disabled="disabled" class="api-keys-add button" type="button"><?php esc_html_e( 'Add a License API Key' ); ?></button>
						</div>
						<input type="hidden" class="api-key-values" id="wppus_license_private_api_keys" name="wppus_license_private_api_keys" value="<?php echo esc_attr( get_option( 'wppus_license_private_api_keys', '{}' ) ); ?>">
					</div>
					<p class="description">
						<?php esc_html_e( 'Used to get tokens for license administration requests.', 'wppus' ); ?>
						<br>
						<?php
						printf(
							// translators: %1$s is <code>-</code>, %2$s is <code>_</code>
							esc_html__( 'The License Key ID must contain only numbers, letters, %1$s and %2$s.', 'wppus' ),
							'<code>-</code>',
							'<code>_</code>',
						);
						?>
						<br>
						<strong><?php esc_html_e( 'WARNING: Keep these keys secret, do not share any of them with customers!', 'wppus' ); ?></strong>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="wppus_license_private_api_ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'wppus' ); ?></label>
				</th>
				<td>
					<textarea class="ip-whitelist" id="wppus_license_private_api_ip_whitelist" name="wppus_license_private_api_ip_whitelist"><?php echo esc_html( implode( "\n", get_option( 'wppus_license_private_api_ip_whitelist', array() ) ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'List of IP addresses and/or CIDRs of remote sites authorized to use the Private API (one IP address or CIDR per line).', 'wprus' ); ?> <br/>
						<?php esc_html_e( 'Leave blank to allow any IP address (not recommended).', 'wprus' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<hr>
		<h3><?php esc_html_e( 'Webhooks', 'wppus' ); ?></h3>
		<table class="form-table">
			<tr>
				<td colspan="2">
					<div class="webhook-multiple">
						<div class="webhook-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-webhook-item-url" placeholder="<?php esc_attr_e( 'Payload URL' ); ?>">
							<input type="text" class="new-webhook-item-secret" placeholder="<?php echo esc_attr( 'secret-key' ); ?>" value="<?php echo esc_attr( bin2hex( openssl_random_pseudo_bytes( 8 ) ) ); ?>">
							<input type="text" class="show-if-license new-webhook-item-license_api_key hidden" placeholder="<?php echo esc_attr( 'License Key ID (L**...)' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-webhook-event="all"> <?php esc_html_e( 'All events', 'wppus' ); ?></label>
								</div>
								<?php foreach ( $webhook_events as $top_event => $values ) : ?>
								<div class="event-container <?php echo esc_attr( $top_event ); ?>">
									<label class="top-level"><input type="checkbox" data-webhook-event="<?php echo esc_attr( $top_event ); ?>"> <?php echo esc_html( $values['label'] ); ?> <code>(<?php echo esc_html( $top_event ); ?>)</code></label>
									<?php if ( isset( $values['events'] ) && ! empty( $values['events'] ) ) : ?>
										<?php foreach ( $values['events'] as $event => $label ) : ?>
										<label class="child"><input type="checkbox" data-webhook-event="<?php echo esc_attr( $event ); ?>"> <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $event ); ?>)</code></label>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
							</div>
							<button disabled="disabled" class="webhook-add button" type="button"><?php esc_html_e( 'Add a Webhook' ); ?></button>
						</div>
						<input type="hidden" class="webhook-values" id="wppus_webhooks" name="wppus_webhooks" value="<?php echo esc_attr( get_option( 'wppus_webhooks', '{}' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Webhooks are event notifications sent to arbitrary URLs during the next cron job (within 1 minute after the event occurs with a server cron configuration schedule to execute every minute). The event is sent along with a payload of data for third party services integration.', 'wppus' ); ?>
							<br>
							<br>
							<?php
							printf(
								// translators: %1$s is <code>secret</code>, %2$s is <code>X-WPPUS-Signature-256</code>
								esc_html__( 'To allow the recipients to authenticate the notifications, the payload is signed with a %1$s secret key using the SHA-256 algorithm ; the resulting hash is made available in the %2$s header.', 'wppus' ),
								'<code>secret-key</code>',
								'<code>X-WPPUS-Signature-256</code>'
							);
							?>
							<br>
							<strong>
							<?php
							printf(
								// translators: %s is '<code>secret-key</code>'
								esc_html__( 'The %s must be a minimum of 16 characters long, preferably a random string.', 'wppus' ),
								'<code>secret-key</code>'
							);
							?>
							</strong>
							<br>
							<?php
							printf(
								// translators: %s is <code>POST</code>
								esc_html__( 'The payload is sent in JSON format via a %s request.', 'wppus' ),
								'<code>POST</code>',
							);
							?>
							<br>
							<span class="show-if-license hidden"><br><?php esc_html_e( 'Use the License Key ID field to filter the License events sent to the payload URLs: if provided, only the events affecting license keys owned by the License Key ID will be broacasted to the Payload URL.', 'wppus' ); ?></span>
							<br>
							<strong class="show-if-license hidden"><?php esc_html_e( 'CAUTION: In case a License Key ID is not provided, events will be broacasted for all the licenses, leading to the potential leak of private data!', 'wppus' ); ?><br></strong>
							<br>
							<strong><?php esc_html_e( 'CAUTION: Only add URLs from trusted sources!', 'wppus' ); ?></strong>
						</p>
					</div>
				</td>
			</tr>
		</table>
		<hr>
		<?php wp_nonce_field( 'wppus_plugin_options', 'wppus_plugin_options_handler_nonce' ); ?>
		<p class="submit">
			<input type="submit" name="wppus_options_save" value="<?php esc_attr_e( 'Save', 'wppus' ); ?>" class="button button-primary" />
		</p>
	</form>
</div>