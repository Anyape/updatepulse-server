<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use WP_Error;

if ( ! class_exists( BitbucketApi::class, false ) ) :

	/**
	 * Class BitbucketApi
	 * Handles interactions with the Bitbucket API.
	 */
	class BitbucketApi extends Api {

		/**
		 * BitbucketApi constructor.
		 *
		 * @param string $repository_url The URL of the Bitbucket repository.
		 * @param string|null $credentials Optional. The Bitbucket credentials (email and API token) in the format "email:token".
		 * @throws \InvalidArgumentException If the repository URL is invalid.
		 */
		public function __construct( $repository_url, $credentials = null ) {
			$path = wp_parse_url( $repository_url, PHP_URL_PATH );

			if ( preg_match( '@^/?(?P<user_name>[^/]+?)/(?P<repository>[^/#?&]+?)/?$@', $path, $matches ) ) {
				$this->user_name       = $matches['user_name'];
				$this->repository_name = $matches['repository'];
			} else {
				throw new \InvalidArgumentException(
					esc_html( 'Invalid Bitbucket repository URL: "' . $repository_url . '"' )
				);
			}

			parent::__construct( $repository_url, $credentials );
		}

		/**
		 * Check if the VCS is accessible.
		 *
		 * @param string $url The URL to test.
		 * @param string|null $credentials Optional. The Bitbucket credentials (email and API token) in the format "email:token".
		 * @return bool|WP_Error True if accessible, WP_Error otherwise.
		 */
		public static function test( $url, $credentials = null ) {
			$instance = new self( $url . 'bogus/', $credentials );
			$endpoint = 'https://api.bitbucket.org/2.0/user';
			$response = $instance->api( $endpoint, '2.0', true );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! $response ) {
				return false;
			}

			if (
				$response &&
				isset( $response->username ) &&
				trailingslashit( $instance->user_name ) === trailingslashit( $response->username )
			) {
				return true;
			}

			if ( $response && isset( $response->code ) && 403 === $response->code ) {
				return 'missing_privileges';
			}

			return new WP_Error(
				'puc-bitbucket-http-error',
				sprintf( 'Bitbucket API error. Base URL: "%s",  HTTP status code: %d.', $url, $response->code )
			);
		}

		/**
		 * Get update detection strategies.
		 *
		 * @param string $config_branch The branch to check for updates.
		 * @return array The update detection strategies.
		 */
		protected function get_update_detection_strategies( $config_branch ) {
			$strategies[ self::STRATEGY_BRANCH ] = function () use ( $config_branch ) {
				return $this->get_branch( $config_branch );
			};

			if (
				( 'main' === $config_branch || 'master' === $config_branch ) &&
				( ! defined( 'PUC_FORCE_BRANCH' ) || ! (bool) ( constant( 'PUC_FORCE_BRANCH' ) ) )
			) {
				$strategies[ self::STRATEGY_LATEST_TAG ] = array( $this, 'get_latest_tag' );
			}

			return $strategies;
		}

		/**
		 * Get a specific branch.
		 *
		 * @param string $branch_name The name of the branch.
		 * @return Reference|null The branch reference or null if not found.
		 */
		public function get_branch( $branch_name ) {
			$branch = $this->api( '/refs/branches/' . $branch_name );

			if ( is_wp_error( $branch ) || empty( $branch ) ) {
				return null;
			}

			// The "/src/{something}/{path}" endpoint doesn't handle branch names with slashes.
			// If the branch name is not URL-safe, use the commit hash instead.
			$ref = $branch->name;

			if ( ( rawurlencode( $ref ) !== $ref ) && isset( $branch->target->hash ) ) {
				$ref = $branch->target->hash;
			}

			return new Reference(
				array(
					'name'         => $ref,
					'updated'      => $branch->target->date,
					'download_url' => $this->get_download_url( $branch->name ),
				)
			);
		}

		/**
		 * Get a specific tag.
		 *
		 * @param string $tag_name The name of the tag.
		 * @return Reference|null The tag reference or null if not found.
		 */
		public function get_tag( $tag_name ) {
			$tag = $this->api( '/refs/tags/' . $tag_name );

			if ( is_wp_error( $tag ) || empty( $tag ) ) {
				return null;
			}

			return new Reference(
				array(
					'name'         => $tag->name,
					'version'      => ltrim( $tag->name, 'v' ),
					'updated'      => $tag->target->date,
					'download_url' => $this->get_download_url( $tag->name ),
				)
			);
		}

		/**
		 * Get the latest tag that looks like the highest version number.
		 *
		 * @return Reference|null The latest tag reference or null if not found.
		 */
		public function get_latest_tag() {
			$tags = $this->api( '/refs/tags?sort=-target.date' );

			if ( ! isset( $tags, $tags->values ) || ! is_array( $tags->values ) ) {
				return null;
			}

			// Filter and sort the list of tags.
			$version_tags = $this->sort_tags_by_version( $tags->values );

			// Return the first result.
			if ( ! empty( $version_tags ) ) {
				$tag = $version_tags[0];

				return new Reference(
					array(
						'name'         => $tag->name,
						'version'      => ltrim( $tag->name, 'v' ),
						'updated'      => $tag->target->date,
						'download_url' => $this->get_download_url( $tag->name ),
					)
				);
			}

			return null;
		}

		/**
		 * Get the download URL for a specific reference.
		 *
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return string The download URL.
		 */
		protected function get_download_url( $ref ) {
			return sprintf(
				'https://bitbucket.org/%s/%s/get/%s.zip',
				$this->user_name,
				$this->repository_name,
				$ref
			);
		}

		/**
		 * Get the contents of a file from a specific branch or tag.
		 *
		 * @param string $path The file path.
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return null|string The file contents or null if not found.
		 */
		public function get_remote_file( $path, $ref = 'main' ) {
			$response = $this->api( 'src/' . $ref . '/' . ltrim( $path ) );

			if ( is_wp_error( $response ) || ! is_string( $response ) ) {
				return null;
			}

			return $response;
		}

		/**
		 * Get the timestamp of the latest commit that changed the specified branch or tag.
		 *
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return string|null The timestamp of the latest commit or null if not found.
		 */
		public function get_latest_commit_time( $ref ) {
			$response = $this->api( 'commits/' . $ref );

			if ( isset( $response->values, $response->values[0], $response->values[0]->date ) ) {
				return $response->values[0]->date;
			}

			return null;
		}

		/**
		 * Perform a Bitbucket API 2.0 request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param string $version The API version.
		 * @param bool $override_url Whether to override the base URL.
		 * @return mixed|WP_Error The API response or WP_Error on failure.
		 */
		public function api( $url, $version = '2.0', $override_url = false ) {
			$url             = ltrim( $url, '/' );
			$options         = array( 'timeout' => wp_doing_cron() ? 10 : 3 );
			$is_src_resource = 0 === strpos( $url, 'src/' );

			if ( ! $override_url ) {
				$url = implode(
					'/',
					array(
						'https://api.bitbucket.org',
						$version,
						'repositories',
						$this->user_name,
						$this->repository_name,
						$url,
					)
				);
			}

			if ( $this->is_authentication_enabled() ) {
				$options['headers'] = $this->get_authorization_headers();
			}

			$response = wp_remote_get( $url, $options );

			if ( is_wp_error( $response ) ) {
				do_action( 'puc_api_error', $response, null, $url, $this->slug );

				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 === $code ) {

				if ( $is_src_resource ) {
					// Most responses are JSON-encoded, but src resources return raw file contents.
					$document = $body;
				} else {
					$document = json_decode( $body );
				}

				return $document;
			}

			if ( 401 === $code || 403 === $code || 400 === $code ) {
				$error = new WP_Error(
					'puc-bitbucket-authentication-error',
					sprintf( 'Authentication failed for Bitbucket API. Base URL: "%s". HTTP status code: %d.', $url, $code )
				);

				do_action( 'puc_api_error', $error, $response, $url, $this->slug );

				return false;
			}

			if ( $override_url && ! empty( $body ) ) {
				$response = json_decode( $body );

				if ( ! is_object( $response ) ) {
					$error = new WP_Error(
						'puc-bitbucket-invalid-response',
						sprintf( 'Invalid response from Bitbucket API. Base URL: "%s". HTTP status code: %d.', $url, $code )
					);

					do_action( 'puc_api_error', $error, $response, $url, $this->slug );

					return false;
				}

				$response->code = $code;

				return $response;
			}

			$error = new WP_Error(
				'puc-bitbucket-http-error',
				sprintf( 'Bitbucket API error. Base URL: "%s",  HTTP status code: %d.', $url, $code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $this->slug );

			return $error;
		}

		/**
		 * Generate the value of the "Authorization" header.
		 *
		 * @return array The authorization headers.
		 */
		public function get_authorization_headers() {
			return array(
				'Authorization' => 'Basic ' . base64_encode( $this->credentials ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}
	}

endif;
