<?php
/**
 * API settings page template.
 *
 * @package UpdatePulse_Server
 *
 * @var string $header              Rendered admin settings header.
 * @var array  $options             API settings.
 * @var array  $license_api_actions Available License API actions.
 * @var array  $package_api_actions Available Package API actions.
 * @var array  $webhook_events      Available webhook events grouped by resource type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<div class="wrap upserv-wrap">
	<?php echo $header ? wp_kses_post( $header ) : ''; ?>
	<form autocomplete="off" id="upserv-api-settings" action="" method="post">
		<h3><?php esc_html_e( 'Package API', 'updatepulse-server' ); ?></h3>
		<table class="form-table">
			<tr>
				<th class="inline">
					<label for="upserv_package_private_api_keys"><?php esc_html_e( 'Private API Keys', 'updatepulse-server' ); ?><br><small><a href="#" class="upserv-modal-open-handle" data-modal_id="upserv_modal_api_details" data-title="<?php esc_html_e( 'Package Private API Keys', 'updatepulse-server' ); ?>" data-selector="#upserv_package_private_api_keys"><?php esc_html_e( 'Details', 'updatepulse-server' ); ?></a></small></label>
				</th>
			</tr>
			<tr>
				<td colspan="2">
					<div class="api-keys-multiple package" data-prefix="UPDATEPULSE_P_">
						<div class="api-keys-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-api-key-item-id" placeholder="<?php esc_attr_e( 'Package Key ID', 'updatepulse-server' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-api-action="all"> <?php esc_html_e( 'Grant access to all the package actions', 'updatepulse-server' ); ?> <code>(all)</code></label>
								</div>
								<?php if ( ! empty( $package_api_actions ) ) : ?>
									<?php foreach ( $package_api_actions as $action_id => $label ) : ?>
									<div class="event-container <?php echo esc_attr( $action_id ); ?>">
										<label class="top-level"><input type="checkbox" data-api-action="<?php echo esc_attr( $action_id ); ?>"> <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $action_id ); ?>)</code></label>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<button disabled="disabled" class="api-keys-add button" type="button"><?php esc_html_e( 'Add a Package API Key', 'updatepulse-server' ); ?></button>
						</div>
						<input type="hidden" class="api-key-values api-data" id="upserv_package_private_api_keys" name="upserv_package_private_api_keys" value="<?php echo esc_attr( $options['package_private_api_keys'] ); ?>">
					</div>
					<p class="description">
						<?php esc_html_e( 'Used to get tokens for package administration requests and requests of signed URLs used to download packages.', 'updatepulse-server' ); ?>
						<br>
						<?php
						printf(
							// translators: %1$s is <code>-</code>, %2$s is <code>_</code>
							esc_html__( 'The Package Key ID must contain only numbers, letters, %1$s and %2$s.', 'updatepulse-server' ),
							'<code>-</code>',
							'<code>_</code>',
						);
						?>
						<br>
						<strong><?php esc_html_e( 'WARNING: Keep these keys secret, do not share any of them with customers!', 'updatepulse-server' ); ?></strong>
					</p>
				</td>
			</tr>
			<tr>
				<th class="inline">
					<label for="upserv_package_private_api_ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'updatepulse-server' ); ?></label>
				</th>
			</tr>
			<tr>
				<td>
					<textarea class="ip-whitelist" id="upserv_package_private_api_ip_whitelist" name="upserv_package_private_api_ip_whitelist"><?php echo esc_html( implode( "\n", $options['package_private_api_ip_whitelist'] ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'List of IP addresses and/or CIDRs of remote sites authorized to use the Private API (one IP address or CIDR per line).', 'updatepulse-server' ); ?> <br/>
						<?php esc_html_e( 'Leave blank to allow any IP address (not recommended).', 'updatepulse-server' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="upserv_options_save" value="<?php esc_attr_e( 'Save', 'updatepulse-server' ); ?>" class="button button-primary" />
		</p>
		<hr>
		<h3><?php esc_html_e( 'License API', 'updatepulse-server' ); ?></h3>
		<table class="form-table">
			<tr>
				<th class="inline">
					<label for="upserv_license_private_api_keys"><?php esc_html_e( 'Private API Keys', 'updatepulse-server' ); ?><br><small><a href="#" class="upserv-modal-open-handle" data-modal_id="upserv_modal_api_details" data-title="<?php esc_html_e( 'License Private API Keys', 'updatepulse-server' ); ?>" data-selector="#upserv_license_private_api_keys"><?php esc_html_e( 'Details', 'updatepulse-server' ); ?></a></small></label>
				</th>
			</tr>
			<tr>
				<td colspan="2">
					<div class="api-keys-multiple license" data-prefix="UPDATEPULSE_L_">
						<div class="api-keys-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-api-key-item-id" placeholder="<?php esc_attr_e( 'License Key ID', 'updatepulse-server' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-api-action="all"> <?php esc_html_e( 'Grant access to all the license actions affecting the records associated with the License API Key', 'updatepulse-server' ); ?> <code>(all)</code></label>
								</div>
								<?php if ( ! empty( $license_api_actions ) ) : ?>
									<?php foreach ( $license_api_actions as $action_id => $label ) : ?>
									<div class="event-container <?php echo esc_attr( $action_id ); ?>">
										<label class="top-level"><input type="checkbox" data-api-action="<?php echo esc_attr( $action_id ); ?>"> <?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $action_id ); ?>)</code></label>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
								<div class="event-container other">
									<label><input type="checkbox" data-api-action="other"> <?php esc_html_e( 'Also grant access to affect other records (all records)', 'updatepulse-server' ); ?> <code>(other)</code></label>
								</div>
							</div>
							<button disabled="disabled" class="api-keys-add button" type="button"><?php esc_html_e( 'Add a License API Key', 'updatepulse-server' ); ?></button>
						</div>
						<input type="hidden" class="api-key-values api-data" id="upserv_license_private_api_keys" name="upserv_license_private_api_keys" value="<?php echo esc_attr( $options['license_private_api_keys'] ); ?>">
					</div>
					<p class="description">
						<?php esc_html_e( 'Used to get tokens for license administration requests.', 'updatepulse-server' ); ?>
						<br>
						<?php
						printf(
							// translators: %1$s is <code>-</code>, %2$s is <code>_</code>
							esc_html__( 'The License Key ID must contain only numbers, letters, %1$s and %2$s.', 'updatepulse-server' ),
							'<code>-</code>',
							'<code>_</code>',
						);
						?>
						<br>
						<strong><?php esc_html_e( 'WARNING: Keep these keys secret, do not share any of them with customers!', 'updatepulse-server' ); ?></strong>
					</p>
				</td>
			</tr>
			<tr>
				<th class="inline">
					<label for="upserv_license_private_api_ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'updatepulse-server' ); ?></label>
				</th>
			</tr>
			<tr>
				<td>
					<textarea class="ip-whitelist" id="upserv_license_private_api_ip_whitelist" name="upserv_license_private_api_ip_whitelist"><?php echo esc_html( implode( "\n", $options['license_private_api_ip_whitelist'] ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'List of IP addresses and/or CIDRs of remote sites authorized to use the Private API (one IP address or CIDR per line).', 'updatepulse-server' ); ?> <br/>
						<?php esc_html_e( 'Leave blank to allow any IP address (not recommended).', 'updatepulse-server' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="upserv_options_save" value="<?php esc_attr_e( 'Save', 'updatepulse-server' ); ?>" class="button button-primary" />
		</p>
		<hr>
		<h3><?php esc_html_e( 'Webhooks', 'updatepulse-server' ); ?><br><small><a href="#" class="upserv-modal-open-handle" data-modal_id="upserv_modal_api_details" data-title="<?php esc_html_e( 'Webhooks', 'updatepulse-server' ); ?>" data-selector="#upserv_webhooks"><?php esc_html_e( 'Details', 'updatepulse-server' ); ?></a></small></h3>
		<table class="form-table">
			<tr>
				<td style="padding-top: 0;">
					<div class="webhook-multiple">
						<div class="webhook-items empty">
						</div>
						<div class="add-controls">
							<input type="text" class="new-webhook-item-url" placeholder="<?php esc_attr_e( 'Payload URL', 'updatepulse-server' ); ?>">
							<input type="text" class="new-webhook-item-secret" placeholder="<?php echo esc_attr( 'secret-key' ); ?>" value="<?php echo esc_attr( bin2hex( openssl_random_pseudo_bytes( 8 ) ) ); ?>">
							<input type="text" class="show-if-license new-webhook-item-license_api_key hidden" placeholder="<?php echo esc_attr( 'License Key ID (UPDATEPULSE_L_...)' ); ?>">
							<div class="event-types">
								<div class="event-container all">
									<label><input type="checkbox" data-webhook-event="all"> <?php esc_html_e( 'All events', 'updatepulse-server' ); ?></label>
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
							<button disabled="disabled" class="webhook-add button" type="button"><?php esc_html_e( 'Add a Webhook', 'updatepulse-server' ); ?></button>
						</div>
						<input type="hidden" class="webhook-values api-data" id="upserv_webhooks" name="upserv_webhooks" value="<?php echo esc_attr( $options['webhooks'] ); ?>">
						<p class="description">
							<?php esc_html_e( 'Webhooks are event notifications sent to arbitrary URLs during the next cron job (within 1 minute after the event occurs with a server cron configuration schedule to execute every minute). The event is sent along with a payload of data for third party services integration.', 'updatepulse-server' ); ?>
							<br>
							<br>
							<?php
							printf(
								// translators: %1$s is <code>secret</code>, %2$s is <code>X-UPDATEPULSE-Signature-256</code>
								esc_html__( 'To allow the recipients to authenticate the notifications, the payload is signed with a %1$s secret key using the SHA-256 algorithm; the resulting hash is made available in the %2$s header.', 'updatepulse-server' ),
								'<code>secret-key</code>',
								'<code>X-UpdatePulse-Signature-256</code>'
							);
							?>
							<br>
							<strong>
							<?php
							printf(
								// translators: %s is '<code>secret-key</code>'
								esc_html__( 'The %s must be a minimum of 16 characters long, preferably a random string.', 'updatepulse-server' ),
								'<code>secret-key</code>'
							);
							?>
							</strong>
							<br>
							<?php
							printf(
								// translators: %s is <code>POST</code>
								esc_html__( 'The payload is sent in JSON format via a %s request.', 'updatepulse-server' ),
								'<code>POST</code>',
							);
							?>
							<br>
							<span class="show-if-license hidden"><br><?php esc_html_e( 'Use the License Key ID field to filter the License events sent to the payload URLs: if provided, only the events affecting license keys owned by the License Key ID are broacasted to the Payload URL.', 'updatepulse-server' ); ?></span>
							<br>
							<strong class="show-if-license hidden"><?php esc_html_e( 'CAUTION: In case a License Key ID is not provided, events WILL be broacasted for ALL the licenses, leading to the potential leak of private data!', 'updatepulse-server' ); ?><br></strong>
							<br>
							<strong><?php esc_html_e( 'CAUTION: Only add URLs from trusted sources!', 'updatepulse-server' ); ?></strong>
						</p>
					</div>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field( 'upserv_plugin_options', 'upserv_plugin_options_handler_nonce' ); ?>
		<p class="submit">
			<input type="submit" name="upserv_options_save" value="<?php esc_attr_e( 'Save', 'updatepulse-server' ); ?>" class="button button-primary" />
		</p>
	</form>
</div>
<div id="upserv_modal_api_details" data-selector="" class='upserv-modal upserv-modal-api-details hidden'>
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
