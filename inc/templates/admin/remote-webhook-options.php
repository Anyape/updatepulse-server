<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<tr>
	<th>
		<label for="upserv_remote_repository_use_webhooks"><?php esc_html_e( 'Use Webhooks', 'updatepulse-server' ); ?></label>
	</th>
	<td>
		<input type="checkbox" id="upserv_remote_repository_use_webhooks" data-prop="use_webhooks" name="upserv_remote_repository_use_webhooks" value="1" <?php checked( $options['use_webhooks'], 1 ); ?>>
		<p class="description">
			<?php esc_html_e( 'Check this if you wish for each repository of the Remote Repository Service to call a Webhook when updates are pushed.', 'updatepulse-server' ); ?><br>
			<?php esc_html_e( 'When checked, UpdatePulse Server will not regularly poll repositories for package version changes, but relies on events sent by the repositories to schedule a package download.', 'updatepulse-server' ); ?>
			<br/>
			<?php
			printf(
				// translators: %1$s is the webhook URL, %2$s is <code>package-type</code>, %3$s is <code>plugin</code>, %4$s is <code>theme</code>, %5$s is <code>generic</code>, %6$s is <code>package-slug</code>
				esc_html__( 'Webhook URL: %1$s - where %2$s is the package type ( %3$s or %4$s or %5$s ) and %6$s is the slug of the package needing updates.', 'updatepulse-server' ),
				'<code>' . esc_url( home_url( '/updatepulse-server-webhook/package-type/package-slug' ) ) . '</code>',
				'<code>package-type</code>',
				'<code>plugin</code>',
				'<code>theme</code>',
				'<code>generic</code>',
				'<code>package-slug</code>'
			);
			?>
			<br>
			<?php esc_html_e( 'Note that UpdatePulse Server does not rely on the content of the payload to schedule a package download, so any type of event can be used to trigger the Webhook.', 'updatepulse-server' ); ?>
		</p>
	</td>
</tr>
<tr class="webhooks <?php echo ( $options['use_webhooks'] ) ? '' : 'hidden'; ?>">
	<th>
		<label for="upserv_remote_repository_check_delay"><?php esc_html_e( 'Remote Download Delay', 'updatepulse-server' ); ?></label>
	</th>
	<td>
		<input type="number" min="0" id="upserv_remote_repository_check_delay" data-prop="check_delay" name="upserv_remote_repository_check_delay" value="<?php echo esc_attr( $options['check_delay'] ); ?>">
		<p class="description">
			<?php esc_html_e( 'Delay in minutes after which UpdatePulse Server will poll the Remote Repository for package updates when the Webhook has been called.', 'updatepulse-server' ); ?><br>
			<?php esc_html_e( 'Leave at 0 to schedule a package update during the cron run happening immediately after the Webhook was called.', 'updatepulse-server' ); ?>
		</p>
	</td>
</tr>
<tr class="webhooks <?php echo ( $options['use_webhooks'] ) ? '' : 'hidden'; ?>">
	<th>
		<label for="upserv_remote_repository_webhook_secret"><?php esc_html_e( 'Remote Repository Webhook Secret', 'updatepulse-server' ); ?></label>
	</th>
	<td>
		<input class="regular-text secret" type="password" autocomplete="new-password" id="upserv_remote_repository_webhook_secret" data-prop="webhook_secret" name="upserv_remote_repository_webhook_secret" value="<?php echo esc_attr( $options['webhook_secret'] ); ?>">
		<p class="description">
			<?php esc_html_e( 'Preferably a random string, the secret string included in the request by the repository service when calling the Webhook.', 'updatepulse-server' ); ?>
			<br>
			<strong><?php esc_html_e( 'WARNING: Changing this value will invalidate all the existing Webhooks set up on all package repositories.', 'updatepulse-server' ); ?></strong>
			<br>
			<?php esc_html_e( 'After changing this setting, make sure to update the Webhooks secrets in the repository service.', 'updatepulse-server' ); ?></strong>
		</p>
	</td>
</tr>
