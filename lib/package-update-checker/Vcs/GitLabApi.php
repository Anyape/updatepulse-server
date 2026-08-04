<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use WP_Error;
use InvalidArgumentException;
use LogicException;

if ( ! class_exists( GitLabApi::class, false ) ) :

	/**
	 * Class GitLabApi
	 *
	 * This class interacts with the GitLab API to fetch repository information,
	 * releases, tags, branches, and other relevant data.
	 */
	class GitLabApi extends Api {
		use ReleaseAssetSupport;
		use ReleaseFilteringFeature;

		/**
		 * @var string The host of the GitLab server.
		 */
		protected $repository_host;
		/**
		 * @var string The protocol used by the GitLab server, either "http" or "https".
		 */
		protected $repository_protocol = 'https';
		/**
		 * @var string The GitLab authentication token, which is optional.
		 */
		protected $access_token;

		/**
		 * Constructor.
		 *
		 * @param string $repository_url The URL of the GitLab repository.
		 * @param string $access_token The authentication token for GitLab.
		 * @param string $sub_group The sub-group within the GitLab repository.
		 * @throws InvalidArgumentException If the repository URL is invalid.
		 */
		public function __construct( $repository_url, $access_token = null, $sub_group = null ) {
			// Extract the port from the repository URL to support custom hosts.
			$port = wp_parse_url( $repository_url, PHP_URL_PORT );

			if ( ! empty( $port ) ) {
				$port = ':' . $port;
			}

			$this->repository_host = wp_parse_url( $repository_url, PHP_URL_HOST ) . $port;

			if ( 'gitlab.com' !== $this->repository_host ) {
				// Identify the protocol used by the GitLab server.
				$this->repository_protocol = wp_parse_url( $repository_url, PHP_URL_SCHEME );
			}

			// Extract repository information from the URL.
			$path = wp_parse_url( $repository_url, PHP_URL_PATH );

			if ( preg_match( '@^/?(?P<username>[^/]+?)/(?P<repository>[^/#?&]+?)/?$@', $path, $matches ) ) {
				$this->user_name       = $matches['username'];
				$this->repository_name = $matches['repository'];
			} elseif ( ( 'gitlab.com' === $this->repository_host ) ) {
				// Handle repositories in sub-groups, e.g., "/organization/category/repo".
				$parts = explode( '/', trim( $path, '/' ) );

				if ( count( $parts ) < 3 ) {
					throw new InvalidArgumentException(
						esc_html(
							'Invalid GitLab.com repository URL: "' . $repository_url . '"'
						)
					);
				}

				$last_part             = array_pop( $parts );
				$this->user_name       = implode( '/', $parts );
				$this->repository_name = $last_part;
			} else {

				// Handle URLs with sub-groups: gitlab.domain.com/group/sub_group/sub_group2/repository.
				if ( null === $sub_group ) {
					$path = str_replace( trailingslashit( $sub_group ), '', $path );
				}

				// Handle non-traditional URLs where GitLab is in a deeper subdirectory.
				// Extract the path segments.
				$segments = explode( '/', untrailingslashit( ltrim( $path, '/' ) ) );

				// Ensure there are at least /user-name/repository-name/ segments.
				if ( count( $segments ) < 2 ) {
					throw new InvalidArgumentException(
						esc_html(
							'Invalid GitLab repository URL: "' . $repository_url . '"'
						)
					);
				}

				// Extract the username and repository name.
				$username_repo         = array_splice( $segments, -2, 2 );
				$this->user_name       = $username_repo[0];
				$this->repository_name = $username_repo[1];

				// Append remaining segments to the host if any segments are left.
				if ( count( $segments ) > 0 ) {
					$this->repository_host = trailingslashit( $this->repository_host ) . implode( '/', $segments );
				}

				// Add sub-groups to the username if provided.
				if ( null !== $sub_group ) {
					$this->user_name = $username_repo[0] . '/' . untrailingslashit( $sub_group );
				}
			}

			parent::__construct( $repository_url, $access_token );
		}

		/**
		 * Check if the VCS is accessible.
		 *
		 * @param string $url The URL to check.
		 * @param string $access_token The authentication token for GitLab.
		 * @return bool|WP_Error True if accessible, WP_Error otherwise.
		 */
		public static function test( $url, $access_token = null ) {
			$instance = new self( $url . 'bogus/', $access_token );
			$endpoint = sprintf(
				'%1$s://%2$s/api/v4/groups/%3$s',
				$instance->repository_protocol,
				$instance->repository_host,
				rawurlencode( $instance->user_name )
			);
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $response &&
				isset( $response->path ) &&
				trailingslashit( $instance->user_name ) === trailingslashit( $response->path );
		}

		/**
		 * Retrieve the latest release from GitLab.
		 *
		 * @return Reference|null The latest release or null if not found.
		 */
		public function get_latest_release() {
			$releases = $this->api( '/:id/releases', array( 'per_page' => $this->release_filter_max_releases ) );

			if ( is_wp_error( $releases ) || empty( $releases ) || ! is_array( $releases ) ) {
				return null;
			}

			foreach ( $releases as $release ) {

				if (
					// Skip invalid or unsupported releases.
					! is_object( $release )
					|| ! isset( $release->tag_name )
					// Skip upcoming releases.
					|| (
						! empty( $release->upcoming_release )
						&& $this->should_skip_pre_releases()
					)
				) {
					continue;
				}

				$version_number = ltrim( $release->tag_name, 'v' ); // Remove the "v" prefix from "v1.2.3".

				// Apply custom filters.
				if ( ! $this->matches_custom_release_filter( $version_number, $release ) ) {
					continue;
				}

				$download_url = $this->find_release_download_url( $release );

				if ( empty( $download_url ) ) {
					// The latest release doesn't have a valid download URL.
					return null;
				}

				return new Reference(
					array(
						'name'         => $release->tag_name,
						'version'      => $version_number,
						'download_url' => $download_url,
						'updated'      => $release->released_at,
						'apiResponse'  => $release,
					)
				);
			}

			return null;
		}

		/**
		 * Locate the download URL for a release asset.
		 *
		 * @param object $release The release object.
		 * @return string|null The download URL or null if not found.
		 */
		protected function find_release_download_url( $release ) {

			if ( $this->release_assets_enabled ) {

				if ( isset( $release->assets, $release->assets->links ) ) {

					// Use the first asset link that matches the filter.
					foreach ( $release->assets->links as $link ) {

						if ( $this->matches_asset_filter( $link ) ) {
							return $link->url;
						}
					}
				}

				if ( Api::REQUIRE_RELEASE_ASSETS === $this->release_asset_preference ) {
					// Do not fall back to source archives, so return null.
					return null;
				}
			}

			// Use the first source code archive in ZIP format.
			foreach ( $release->assets->sources as $source ) {

				if ( isset( $source->format ) && ( 'zip' === $source->format ) ) {
					return $source->url;
				}
			}

			return null;
		}

		/**
		 * Retrieve the tag that appears to be the highest version number.
		 *
		 * @return Reference|null The latest tag or null if not found.
		 */
		public function get_latest_tag() {
			$tags = $this->api( '/:id/repository/tags' );

			if ( is_wp_error( $tags ) || empty( $tags ) || ! is_array( $tags ) ) {
				return null;
			}

			$version_tags = $this->sort_tags_by_version( $tags );

			if ( empty( $version_tags ) ) {
				return null;
			}

			$tag = $version_tags[0];

			return new Reference(
				array(
					'name'         => $tag->name,
					'version'      => ltrim( $tag->name, 'v' ),
					'download_url' => $this->build_archive_download_url( $tag->name ),
					'apiResponse'  => $tag,
				)
			);
		}

		/**
		 * Retrieve a branch by its name.
		 *
		 * @param string $branch_name The name of the branch.
		 * @return null|Reference The branch reference or null if not found.
		 */
		public function get_branch( $branch_name ) {
			$branch = $this->api( '/:id/repository/branches/' . $branch_name );

			if ( is_wp_error( $branch ) || empty( $branch ) ) {
				return null;
			}

			$reference = new Reference(
				array(
					'name'         => $branch->name,
					'download_url' => $this->build_archive_download_url( $branch->name ),
					'apiResponse'  => $branch,
				)
			);

			if ( isset( $branch->commit, $branch->commit->committed_date ) ) {
				$reference->updated = $branch->commit->committed_date;
			}

			return $reference;
		}

		/**
		 * Retrieve the timestamp of the latest commit that modified the specified branch or tag.
		 *
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return string|null The timestamp of the latest commit or null if not found.
		 */
		public function get_latest_commit_time( $ref ) {
			$commits = $this->api( '/:id/repository/commits/', array( 'ref_name' => $ref ) );

			if ( is_wp_error( $commits ) || ! is_array( $commits ) || ! isset( $commits[0] ) ) {
				return null;
			}

			return $commits[0]->committed_date;
		}

		/**
		 * Execute a GitLab API request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param array $query_params The query parameters for the request.
		 * @return mixed|WP_Error The API response or WP_Error on failure.
		 */
		protected function api( $url, $query_params = array(), $override_url = false ) {

			if ( ! $override_url ) {
				$url = $this->build_api_url( $url, $query_params );
			}

			$options = array( 'timeout' => wp_doing_cron() ? 10 : 3 );

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
				return json_decode( $body );
			}

			if ( $override_url ) {
				$response       = json_decode( $body );
				$response->code = $code;

				return $response;
			}

			$error = new WP_Error(
				'puc-gitlab-http-error',
				sprintf( 'GitLab API error. URL: "%s",  HTTP status code: %d.', $url, $code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $this->slug );

			return $error;
		}

		/**
		 * Construct a fully qualified URL for an API request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param array $query_params The query parameters for the request.
		 * @return string The fully qualified URL.
		 */
		protected function build_api_url( $url, $query_params ) {
			$variables = array(
				'user' => $this->user_name,
				'repo' => $this->repository_name,
				'id'   => $this->user_name . '/' . $this->repository_name,
			);

			foreach ( $variables as $name => $value ) {
				$url = str_replace( "/:{$name}", '/' . rawurlencode( $value ), $url );
			}

			$url = substr( $url, 1 );
			$url = sprintf( '%1$s://%2$s/api/v4/projects/%3$s', $this->repository_protocol, $this->repository_host, $url );

			if ( ! empty( $query_params ) ) {
				$url = add_query_arg( $query_params, $url );
			}

			return $url;
		}

		/**
		 * Retrieve the contents of a file from a specific branch or tag.
		 *
		 * @param string $path The file name.
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return null|string The file contents or null if the file doesn't exist or there's an error.
		 */
		public function get_remote_file( $path, $ref = 'main' ) {
			$response = $this->api( '/:id/repository/files/' . $path, array( 'ref' => $ref ) );

			if ( is_wp_error( $response ) || ! isset( $response->content ) || 'base64' !== $response->encoding ) {
				return null;
			}

			return base64_decode( $response->content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		/**
		 * Generate a URL to download a ZIP archive of the specified branch/tag/etc.
		 *
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return string The download URL.
		 */
		public function build_archive_download_url( $ref = 'main' ) {
			$url = sprintf(
				'%1$s://%2$s/api/v4/projects/%3$s/repository/archive.zip',
				$this->repository_protocol,
				$this->repository_host,
				rawurlencode( $this->user_name . '/' . $this->repository_name )
			);
			$url = add_query_arg( 'sha', rawurlencode( $ref ), $url );

			return $url;
		}

		/**
		 * Retrieve a specific tag.
		 *
		 * @param string $tag_name The name of the tag.
		 * @return void
		 */
		public function get_tag( $tag_name ) {
			throw new LogicException( 'The ' . __METHOD__ . ' method is not implemented and should not be used.' );
		}

		/**
		 * Get the strategies for detecting updates.
		 *
		 * @param string $config_branch The configuration branch.
		 * @return array The update detection strategies.
		 */
		protected function get_update_detection_strategies( $config_branch ) {
			$strategies = array();

			if (
				( ( 'main' === $config_branch ) || ( 'master' === $config_branch ) ) &&
				( ! defined( 'PUC_FORCE_BRANCH' ) || ! (bool) ( constant( 'PUC_FORCE_BRANCH' ) ) )
			) {
				$strategies[ self::STRATEGY_LATEST_RELEASE ] = array( $this, 'get_latest_release' );
				$strategies[ self::STRATEGY_LATEST_TAG ]     = array( $this, 'get_latest_tag' );
			}

			$strategies[ self::STRATEGY_BRANCH ] = function () use ( $config_branch ) {
				return $this->get_branch( $config_branch );
			};

			return $strategies;
		}

		/**
		 * Set the authentication credentials.
		 *
		 * @param string $credentials The authentication credentials.
		 * @return void
		 */
		public function set_authentication( $credentials ) {
			parent::set_authentication( $credentials );

			$this->access_token = is_string( $credentials ) ? $credentials : null;
		}

		/**
		 * Retrieve the filterable asset name.
		 *
		 * @param object $release_asset The release asset object.
		 * @return string|null The asset name or null if not found.
		 */
		protected function get_filterable_asset_name( $release_asset ) {

			if ( isset( $release_asset->url ) ) {
				return $release_asset->url;
			}

			return null;
		}

		/**
		 * Generate the value of the "Authorization" header.
		 *
		 * @return array The authorization header value.
		 */
		public function get_authorization_headers() {
			return array( 'PRIVATE-TOKEN' => $this->access_token );
		}
	}

endif;
