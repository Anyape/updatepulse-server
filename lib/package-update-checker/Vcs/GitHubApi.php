<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use WP_Error;
use InvalidArgumentException;
use LogicException;

if ( ! class_exists( GitHubApi::class, false ) ) :

	/**
	 * Class GitHubApi
	 *
	 * This class provides methods to interact with the GitHub API for various operations
	 * such as fetching releases, tags, branches, and commits. It also handles authentication
	 * and API request construction.
	 */
	class GitHubApi extends Api {
		use ReleaseAssetSupport;
		use ReleaseFilteringFeature;

		/**
		 * GitHub authentication token.
		 *
		 * @var string|null GitHub authentication token. Optional.
		 */
		protected $access_token;

		/**
		 * Whether the download filter has been added.
		 *
		 * @var bool Indicates if the download filter has been added.
		 */
		private $download_filter_added = false;

		/**
		 * GitHubApi constructor.
		 *
		 * @param string      $repository_url The URL of the GitHub repository.
		 * @param string|null $access_token Optional GitHub access token.
		 * @throws InvalidArgumentException If the repository URL is invalid.
		 */
		public function __construct( $repository_url, $access_token = null ) {
			$path = wp_parse_url( $repository_url, PHP_URL_PATH );

			if ( preg_match( '@^/?(?P<username>[^/]+?)/(?P<repository>[^/#?&]+?)/?$@', $path, $matches ) ) {
				$this->user_name       = $matches['username'];
				$this->repository_name = $matches['repository'];
			} else {
				throw new InvalidArgumentException(
					esc_html( 'Invalid GitHub repository URL: "' . $repository_url . '"' )
				);
			}

			parent::__construct( $repository_url, $access_token );
		}

		/**
		 * Verify that the credentials can access the configured user or organization.
		 *
		 * @param string      $url The URL to check.
		 * @param string|null $access_token Optional GitHub access token.
		 * @return bool|string True when the credentials are valid for the account, false when
		 *                     authentication fails, or "failed_org_check" when organization
		 *                     membership cannot be confirmed.
		 */
		public static function test( $url, $access_token = null ) {
			$instance = new self( $url . 'bogus/', $access_token );
			$endpoint = 'https://api.github.com/user';
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

			$endpoint = 'https://api.github.com/orgs/'
				. rawurlencode( $instance->user_name )
				. '/members/'
				. rawurlencode( $response->login );
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$error = new WP_Error(
				'puc-github-http-error',
				sprintf( 'GitHub API error. Base URL: "%s",  HTTP status code: %d.', $url, $response->code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $instance->slug );

			if ( 204 !== $response->code ) {
				return 'failed_org_check';
			}

			return true;
		}

		/**
		 * Retrieve the latest release from GitHub.
		 *
		 * @return Reference|null The latest release or null if not found.
		 */
		public function get_latest_release() {

			// The "latest release" endpoint returns one release and always skips pre-releases, so we can only use it if that's compatible with the current filter settings.
			if (
				$this->should_skip_pre_releases()
				&& (
					( 1 === $this->release_filter_max_releases ) || ! $this->has_custom_release_filter()
				)
			) {
				// Fetch the latest release.
				$release = $this->api( '/repos/:user/:repo/releases/latest' );

				if ( is_wp_error( $release ) || ! is_object( $release ) || ! isset( $release->tag_name ) ) {
					return null;
				}

				$found_releases = array( $release );
			} else {
				// Retrieve a list of the most recent releases.
				$found_releases = $this->api(
					'/repos/:user/:repo/releases',
					array( 'per_page' => $this->release_filter_max_releases )
				);

				if ( is_wp_error( $found_releases ) || ! is_array( $found_releases ) ) {
					return null;
				}
			}

			foreach ( $found_releases as $release ) {

				// Always skip drafts.
				if ( isset( $release->draft ) && ! empty( $release->draft ) ) {
					continue;
				}

				// Skip pre-releases unless specifically included.
				if (
					$this->should_skip_pre_releases()
					&& isset( $release->prerelease )
					&& ! empty( $release->prerelease )
				) {
					continue;
				}

				$version_number = ltrim( $release->tag_name, 'v' ); // Remove the "v" prefix from "v1.2.3".

				// Custom release filtering.
				if ( ! $this->matches_custom_release_filter( $version_number, $release ) ) {
					continue;
				}

				$reference = new Reference(
					array(
						'name'         => $release->tag_name,
						'version'      => $version_number,
						'download_url' => $release->zipball_url,
						'updated'      => $release->created_at,
						'apiResponse'  => $release,
					)
				);

				if ( isset( $release->assets[0] ) ) {
					$reference->download_count = $release->assets[0]->download_count;
				}

				if ( $this->release_assets_enabled ) {

					// Use the first release asset that matches the specified regular expression.
					if ( isset( $release->assets, $release->assets[0] ) ) {
						$matching_assets = array_values( array_filter( $release->assets, array( $this, 'matchesAssetFilter' ) ) );
					} else {
						$matching_assets = array();
					}

					if ( ! empty( $matching_assets ) ) {

						if ( $this->is_authentication_enabled() ) {
							/**
							 * Keep in mind that we'll need to add an "Accept" header to download this asset.
							 *
							 * @see set_update_download_headers()
							 */
							$reference->download_url = $matching_assets[0]->url;
						} else {
							// The browser download URL is used when the API asset URL does not need authentication.
							$reference->download_url = $matching_assets[0]->browser_download_url;
						}

						$reference->download_count = $matching_assets[0]->download_count;
					} elseif ( Api::REQUIRE_RELEASE_ASSETS === $this->release_asset_preference ) {
						// None of the assets match the filter, and we're not allowed to fall back to the auto-generated source ZIP.
						return null;
					}
				}

				return $reference;
			}

			return null;
		}

		/**
		 * Retrieve the tag that appears to be the highest version number.
		 *
		 * @return Reference|null The highest version tag or null if not found.
		 */
		public function get_latest_tag() {
			$tags = $this->api( '/repos/:user/:repo/tags' );

			if ( is_wp_error( $tags ) || ! is_array( $tags ) ) {
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
					'download_url' => $tag->zipball_url,
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
			$branch = $this->api( '/repos/:user/:repo/branches/' . $branch_name );

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

			if ( isset( $branch->commit, $branch->commit->commit, $branch->commit->commit->author->date ) ) {
				$reference->updated = $branch->commit->commit->author->date;
			}

			return $reference;
		}

		/**
		 * Retrieve the latest commit that modified the specified file.
		 *
		 * @param string $filename The name of the file.
		 * @param string $ref Reference name (e.g., branch or tag).
		 * @return \StdClass|null The latest commit or null if not found.
		 */
		public function get_latest_commit( $filename, $ref = 'main' ) {
			$commits = $this->api(
				'/repos/:user/:repo/commits',
				array(
					'path' => $filename,
					'sha'  => $ref,
				)
			);

			if ( ! is_wp_error( $commits ) && isset( $commits[0] ) ) {
				return $commits[0];
			}

			return null;
		}

		/**
		 * Retrieve the timestamp of the latest commit reachable from the specified branch or tag.
		 *
		 * @param string $ref Reference name (e.g., branch or tag).
		 * @return string|null The timestamp of the latest commit or null if not found.
		 */
		public function get_latest_commit_time( $ref ) {
			$commits = $this->api( '/repos/:user/:repo/commits', array( 'sha' => $ref ) );

			if ( ! is_wp_error( $commits ) && isset( $commits[0] ) ) {
				return $commits[0]->commit->author->date;
			}

			return null;
		}

		/**
		 * Perform a GitHub API request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param array  $query_params Optional query parameters.
		 * @param bool   $override_url Whether to override the base URL.
		 * @return mixed|WP_Error The API response or WP_Error on failure.
		 */
		protected function api( $url, $query_params = array(), $override_url = false ) {
			$base_url = $url;

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
				$response = json_decode( $body );

				if ( ! is_object( $response ) ) {
					$response = new \StdClass();
				}

				$response->code = $code;

				return $response;
			}

			$error = new WP_Error(
				'puc-github-http-error',
				sprintf( 'GitHub API error. Base URL: "%s",  HTTP status code: %d.', $base_url, $code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $this->slug );

			return $error;
		}

		/**
		 * Construct a fully qualified URL for an API request.
		 *
		 * @param string $url The API endpoint URL.
		 * @param array  $query_params Optional query parameters.
		 * @return string The fully qualified URL.
		 */
		protected function build_api_url( $url, $query_params ) {
			$variables = array(
				'user' => $this->user_name,
				'repo' => $this->repository_name,
			);

			foreach ( $variables as $name => $value ) {
				$url = str_replace( '/:' . $name, '/' . rawurlencode( $value ), $url );
			}

			$url = 'https://api.github.com' . $url;

			if ( ! empty( $query_params ) ) {
				$url = add_query_arg( $query_params, $url );
			}

			return $url;
		}

		/**
		 * Retrieve the contents of a file from a specific branch or tag.
		 *
		 * @param string $path The file path.
		 * @param string $ref The reference name (e.g., branch or tag).
		 * @return null|string The file contents or null if not found.
		 */
		public function get_remote_file( $path, $ref = 'main' ) {
			$api_url  = '/repos/:user/:repo/contents/' . $path;
			$response = $this->api( $api_url, array( 'ref' => $ref ) );

			if ( is_wp_error( $response ) || ! isset( $response->content ) || ( 'base64' !== $response->encoding ) ) {
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
				'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s',
				rawurlencode( $this->user_name ),
				rawurlencode( $this->repository_name ),
				rawurlencode( $ref )
			);

			return $url;
		}

		/**
		 * Reject direct tag lookup because this checker does not implement it.
		 *
		 * @param string $tag_name The name of the tag.
		 * @return void This method always throws.
		 * @throws LogicException Always, because direct tag lookup is not implemented.
		 */
		public function get_tag( $tag_name ) {
			// Direct tag lookup is not used by this update checker.
			throw new LogicException( 'The ' . __METHOD__ . ' method is not implemented and should not be used.' );
		}

		/**
		 * Set the GitHub authentication token.
		 *
		 * @param string|null $credentials The authentication token, or null to disable authentication.
		 */
		public function set_authentication( $credentials ) {
			parent::set_authentication( $credentials );

			$this->access_token = is_string( $credentials ) ? $credentials : null;
		}

		/**
		 * Build the ordered update detection strategies for the configured branch.
		 *
		 * Release and tag detection are used for the main or master branch unless branch-only
		 * detection is forced. Branch detection is always retained as the fallback strategy.
		 *
		 * @param string $config_branch The configuration branch.
		 * @return array The update detection strategies.
		 */
		protected function get_update_detection_strategies( $config_branch ) {
			$strategies = array();

			if (
				( 'main' === $config_branch || 'master' === $config_branch ) &&
				( ! defined( 'PUC_FORCE_BRANCH' ) || ! (bool) ( constant( 'PUC_FORCE_BRANCH' ) ) )
			) {
				// Use the latest release.
				$strategies[ self::STRATEGY_LATEST_RELEASE ] = array( $this, 'get_latest_release' );
				// Failing that, use the tag with the highest version number.
				$strategies[ self::STRATEGY_LATEST_TAG ] = array( $this, 'get_latest_tag' );
			}

			// Alternatively, just use the branch itself.
			$strategies[ self::STRATEGY_BRANCH ] = function () use ( $config_branch ) {
				return $this->get_branch( $config_branch );
			};

			return $strategies;
		}

		/**
		 * Retrieve the unchanging part of a release asset URL. Used to identify download attempts.
		 *
		 * @return string The base URL for release assets.
		 */
		protected function get_asset_api_base_url() {
			return sprintf(
				'//api.github.com/repos/%1$s/%2$s/releases/assets/',
				$this->user_name,
				$this->repository_name
			);
		}

		/**
		 * Retrieve the filterable name of a release asset.
		 *
		 * @param object $release_asset The release asset object.
		 * @return string|null The name of the release asset or null if not found.
		 */
		protected function get_filterable_asset_name( $release_asset ) {

			if ( isset( $release_asset->name ) ) {
				return $release_asset->name;
			}

			return null;
		}

		/**
		 * Install authenticated download filters when invoked by an upgrader integration.
		 *
		 * Library consumers may attach this callback to `upgrader_pre_download`. It intentionally
		 * remains unwired here because this API class does not own the update-checker lifecycle.
		 *
		 * @param mixed $result The unmodified upstream filter value.
		 * @return mixed The unmodified upstream filter value.
		 */
		public function add_http_request_filter( $result ) {

			if ( ! $this->download_filter_added && $this->is_authentication_enabled() ) {
				//phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- The callback doesn't change the timeout.
				add_filter( 'http_request_args', array( $this, 'set_update_download_headers' ), 10, 2 );
				add_action( 'requests-requests.before_redirect', array( $this, 'remove_auth_header_from_redirects' ), 10, 4 );

				$this->download_filter_added = true;
			}

			return $result;
		}

		/**
		 * Add release-asset and repository authentication headers to matching download requests.
		 *
		 * Refer to GitHub documentation:
		 *
		 * @link https://developer.github.com/v3/repos/releases/#get-a-single-release-asset
		 * @link https://developer.github.com/v3/auth/#basic-authentication
		 *
		 * @internal
		 * @param array  $request_args HTTP request arguments.
		 * @param string $url Request URL.
		 * @return array
		 */
		public function set_update_download_headers( $request_args, $url = '' ) {

			// Check if WordPress is attempting to download one of our release assets.
			if ( $this->release_assets_enabled && ( strpos( $url, $this->get_asset_api_base_url() ) !== false ) ) {
				$request_args['headers']['Accept'] = 'application/octet-stream';
			}

			// Use Basic authentication only if the download is from our repository.
			$repo_api_base_url = $this->build_api_url( '/repos/:user/:repo/', array() );

			if ( $this->is_authentication_enabled() && ( strpos( $url, $repo_api_base_url ) ) === 0 ) {
				$request_args['headers'] = array_merge( $request_args['headers'], $this->get_authorization_headers() );
			}

			return $request_args;
		}

		/**
		 * When following a redirect, the Requests library will automatically forward
		 * the authorization header to other hosts. This can cause issues with AWS downloads
		 * and may expose authorization information.
		 *
		 * @param string $location Redirect destination.
		 * @param array  $headers Request headers.
		 * @internal
		 */
		public function remove_auth_header_from_redirects( &$location, &$headers ) {
			$repo_api_base_url = $this->build_api_url( '/repos/:user/:repo/', array() );

			if ( strpos( $location, $repo_api_base_url ) === 0 ) {
				return; // This request is going to GitHub, so it's acceptable.
			}

			// Remove the authorization header.
			if ( isset( $headers['Authorization'] ) ) {
				unset( $headers['Authorization'] );
			}
		}

		/**
		 * Create the value for the "Authorization" header.
		 *
		 * @return array
		 */
		public function get_authorization_headers() {
			return array(
				'Authorization' => 'Basic ' . base64_encode( $this->user_name . ':' . $this->access_token ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}
	}

endif;
