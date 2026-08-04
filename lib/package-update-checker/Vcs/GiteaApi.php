<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use WP_Error;
use InvalidArgumentException;

if ( ! class_exists( GiteaApi::class, false ) ) :

	/**
	 * Class GiteaApi
	 *
	 * This class provides methods to interact with the Gitea API for various operations
	 * such as fetching releases, tags, branches, and commits. It also handles authentication
	 * and API request construction.
	 */
	class GiteaApi extends GitHubApi {
		use ReleaseAssetSupport;
		use ReleaseFilteringFeature;

		/**
		 * @var string The host of the Gitea server.
		 */
		protected $repository_host;

		/**
		 * @var string The protocol used by the Gitea server, either "http" or "https".
		 */
		protected $repository_protocol = 'https';

		/**
		 * @var string Gitea authentication token. Optional.
		 */
		protected $access_token;

		/**
		 * @var bool Indicates if the download filter has been added.
		 */
		private $download_filter_added = false;

		/**
		 * GiteaApi constructor.
		 *
		 * @param string $repository_url The URL of the Gitea repository.
		 * @param string|null $access_token Optional Gitea access token.
		 * @throws InvalidArgumentException If the repository URL is invalid.
		 */
		public function __construct( $repository_url, $access_token = null ) {
			// Extract the port from the repository URL to support custom hosts.
			$port = wp_parse_url( $repository_url, PHP_URL_PORT );

			if ( ! empty( $port ) ) {
				$port = ':' . $port;
			}

			$this->repository_host = wp_parse_url( $repository_url, PHP_URL_HOST ) . $port;

			if ( 'gitea.com' !== $this->repository_host ) {
				// Identify the protocol used by the Gitea server.
				$this->repository_protocol = wp_parse_url( $repository_url, PHP_URL_SCHEME );
			}

			try {
				parent::__construct( $repository_url, $access_token );
			} catch ( InvalidArgumentException $e ) {
				throw new InvalidArgumentException(
					esc_html( 'Invalid Gitea repository URL: "' . $repository_url . '"' )
				);
			}
		}

		/**
		 * Check if the VCS is accessible.
		 *
		 * @param string $url The URL to check.
		 * @param string|null $access_token Optional Gitea access token.
		 * @return bool|WP_Error True if accessible, false or WP_Error otherwise.
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
				'puc-gitea-http-error',
				sprintf( 'Gitea API error. Base URL: "%s",  HTTP status code: %d.', $url, $response->code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $instance->slug );

			if ( 204 !== $response->code ) {
				return 'failed_org_check';
			}

			return true;
		}

		private function replace_host( $url, $replace = 'https://api.github.com' ) {

			if ( ! str_starts_with( $url, $replace ) ) {
				return $url;
			}
			$url = substr( $url, strlen( $replace ) );
			$url = sprintf(
				'%1$s://%2$s/api/v1%3$s',
				$this->repository_protocol,
				$this->repository_host,
				$url
			);
			return $url;
		}

		/**
		 * Construct a fully qualified URL for an API request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param array $query_params Optional query parameters.
		 * @return string The fully qualified URL.
		 */
		protected function build_api_url( $url, $query_params ) {
			return $this->replace_host( parent::build_api_url( $url, $query_params ) );
		}

		/**
		 * Generate a URL to download a ZIP archive of the specified branch/tag/etc.
		 *
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return string The download URL.
		 */
		public function build_archive_download_url( $ref = 'main' ) {
			return $this->replace_host( parent::build_archive_download_url( $ref ) );
		}

		/**
		 * Retrieve the unchanging part of a release asset URL. Used to identify download attempts.
		 *
		 * @return string The base URL for release assets.
		 */
		protected function get_asset_api_base_url() {
			return $this->replace_host( parent::get_asset_api_base_url(), '//api.github.com' );
		}

		/**
		 * Create the value for the "Authorization" header.
		 *
		 * @return string
		 */
		public function get_authorization_headers() {
			return array(
				'Authorization' => 'token ' . $this->access_token,
			);
		}
	}

endif;
