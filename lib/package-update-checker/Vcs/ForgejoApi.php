<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use InvalidArgumentException;
use WP_Error;

if ( ! class_exists( ForgejoApi::class, false ) ) :

	/**
	 * Forgejo API client.
	 *
	 * Forgejo is a hard fork of Gitea and retains its compatible API v1
	 * repository endpoints and personal access token authentication scheme.
	 */
	class ForgejoApi extends GiteaApi {

		/**
		 * Constructor.
		 *
		 * @param string      $repository_url Forgejo repository URL.
		 * @param string|null $access_token   Optional Forgejo personal access token.
		 * @throws InvalidArgumentException If the repository URL is invalid.
		 */
		public function __construct( $repository_url, $access_token = null ) {
			try {
				parent::__construct( $repository_url, $access_token );
			} catch ( InvalidArgumentException $exception ) {
				throw new InvalidArgumentException(
					esc_html( 'Invalid Forgejo repository URL: "' . $repository_url . '"' )
				);
			}
		}

		/**
		 * Check whether a Forgejo account or organization is accessible.
		 *
		 * @param string      $url          Forgejo account or organization URL.
		 * @param string|null $access_token Optional Forgejo personal access token.
		 * @return bool|string True when accessible, false on authentication failure,
		 *                     or failed_org_check when organization access fails.
		 */
		public static function test( $url, $access_token = null ) {
			$instance = new self( $url . 'bogus/', $access_token );
			$endpoint = sprintf(
				'%1$s://%2$s/api/v1/user',
				$instance->repository_protocol,
				$instance->repository_host
			);
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if (
				isset( $response->html_url ) &&
				trailingslashit( $url ) === trailingslashit( $response->html_url )
			) {
				return true;
			}

			if ( ! isset( $response->login ) ) {
				return false;
			}

			$endpoint = sprintf(
				'%1$s://%2$s/api/v1/orgs/%3$s/members/%4$s',
				$instance->repository_protocol,
				$instance->repository_host,
				rawurlencode( $instance->user_name ),
				rawurlencode( $response->login )
			);
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$error = new WP_Error(
				'puc-forgejo-http-error',
				sprintf( 'Forgejo API error. Base URL: "%s", HTTP status code: %d.', $url, $response->code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $instance->slug );

			return 204 === $response->code ? true : 'failed_org_check';
		}
	}

endif;
