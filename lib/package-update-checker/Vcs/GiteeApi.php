<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use InvalidArgumentException;
use WP_Error;

if ( ! class_exists( GiteeApi::class, false ) ) :

	/**
	 * Gitee API v5 client.
	 */
	class GiteeApi extends Api {
		use ReleaseAssetSupport;
		use ReleaseFilteringFeature;

		/**
		 * Gitee API base URL.
		 *
		 * @var string
		 */
		const API_BASE_URL = 'https://gitee.com/api/v5';

		/**
		 * Gitee personal access token.
		 *
		 * @var string|null
		 */
		protected $access_token;

		/**
		 * Constructor.
		 *
		 * @param string      $repository_url Gitee repository URL.
		 * @param string|null $access_token   Optional Gitee personal access token.
		 * @throws InvalidArgumentException If the repository URL is invalid or not hosted on gitee.com.
		 */
		public function __construct( $repository_url, $access_token = null ) {
			$host = strtolower( (string) wp_parse_url( $repository_url, PHP_URL_HOST ) );
			$path = wp_parse_url( $repository_url, PHP_URL_PATH );

			if (
				'gitee.com' !== $host ||
				! preg_match( '@^/?(?P<owner>[^/]+?)/(?P<repository>[^/#?&]+?)/?$@', $path, $matches )
			) {
				throw new InvalidArgumentException(
					esc_html( 'Invalid Gitee repository URL: "' . $repository_url . '"' )
				);
			}

			$this->user_name       = $matches['owner'];
			$this->repository_name = $matches['repository'];

			parent::__construct( $repository_url, $access_token );
		}

		/**
		 * Check whether a Gitee user or organization namespace is accessible.
		 *
		 * Gitee API v5 accepts personal access tokens in the access_token query
		 * parameter. The authenticated user endpoint validates both the token and a
		 * configured user namespace. Organization namespaces are checked separately.
		 *
		 * @param string      $url          Gitee user or organization URL.
		 * @param string|null $access_token Optional Gitee personal access token.
		 * @return bool|string True when accessible, false on authentication failure,
		 *                     or failed_org_check when the namespace does not exist.
		 */
		public static function test( $url, $access_token = null ) {
			$instance = new self( trailingslashit( $url ) . 'bogus/', $access_token );
			$endpoint = self::API_BASE_URL . '/user';
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) || ! isset( $response->login ) ) {
				return false;
			}

			if ( strtolower( $instance->user_name ) === strtolower( $response->login ) ) {
				return true;
			}

			$endpoint = self::API_BASE_URL . '/orgs/' . rawurlencode( $instance->user_name );
			$response = $instance->api( $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if (
				isset( $response->login ) &&
				strtolower( $instance->user_name ) === strtolower( $response->login )
			) {
				return true;
			}

			return 'failed_org_check';
		}

		/**
		 * Retrieve the latest eligible Gitee release.
		 *
		 * @return Reference|null Latest release reference, or null when unavailable.
		 */
		public function get_latest_release() {
			if ( 1 === $this->release_filter_max_releases && ! $this->has_custom_release_filter() ) {
				$release        = $this->api( '/repos/:user/:repo/releases/latest' );
				$found_releases = is_object( $release ) ? array( $release ) : array();
			} else {
				$found_releases = $this->api(
					'/repos/:user/:repo/releases',
					array(
						'per_page'  => $this->release_filter_max_releases,
						'direction' => 'desc',
					)
				);
			}

			if ( is_wp_error( $found_releases ) || ! is_array( $found_releases ) ) {
				return null;
			}

			foreach ( $found_releases as $release ) {
				if ( ! is_object( $release ) || ! isset( $release->tag_name ) ) {
					continue;
				}

				if ( $this->should_skip_pre_releases() && ! empty( $release->prerelease ) ) {
					continue;
				}

				$version_number = ltrim( $release->tag_name, 'v' );

				if ( ! $this->matches_custom_release_filter( $version_number, $release ) ) {
					continue;
				}

				$download_url = $this->get_release_download_url( $release );

				if ( ! $download_url ) {
					return null;
				}

				return new Reference(
					array(
						'name'         => $release->tag_name,
						'version'      => $version_number,
						'download_url' => $download_url,
						'updated'      => isset( $release->created_at ) ? $release->created_at : null,
						'apiResponse'  => $release,
					)
				);
			}

			return null;
		}

		/**
		 * Retrieve the highest version-like Gitee tag.
		 *
		 * @return Reference|null Latest tag reference, or null when unavailable.
		 */
		public function get_latest_tag() {
			$tags = $this->api(
				'/repos/:user/:repo/tags',
				array( 'per_page' => 100 )
			);

			if ( is_wp_error( $tags ) || ! is_array( $tags ) ) {
				return null;
			}

			$version_tags = $this->sort_tags_by_version( $tags );

			if ( empty( $version_tags ) ) {
				return null;
			}

			return $this->build_tag_reference( $version_tags[0] );
		}

		/**
		 * Retrieve a specific Gitee tag.
		 *
		 * @param string $tag_name Tag name.
		 * @return Reference|null Tag reference, or null when unavailable.
		 */
		public function get_tag( $tag_name ) {
			$tags = $this->api(
				'/repos/:user/:repo/tags',
				array( 'per_page' => 100 )
			);

			if ( is_wp_error( $tags ) || ! is_array( $tags ) ) {
				return null;
			}

			foreach ( $tags as $tag ) {
				if ( isset( $tag->name ) && $tag_name === $tag->name ) {
					return $this->build_tag_reference( $tag );
				}
			}

			return null;
		}

		/**
		 * Retrieve a Gitee branch.
		 *
		 * @param string $branch_name Branch name.
		 * @return Reference|null Branch reference, or null when unavailable.
		 */
		public function get_branch( $branch_name ) {
			$branch = $this->api( '/repos/:user/:repo/branches/' . rawurlencode( $branch_name ) );

			if ( is_wp_error( $branch ) || ! is_object( $branch ) || ! isset( $branch->name ) ) {
				return null;
			}

			$reference = new Reference(
				array(
					'name'         => $branch->name,
					'download_url' => $this->build_archive_download_url( $branch->name ),
					'apiResponse'  => $branch,
				)
			);

			if ( isset( $branch->commit->commit->author->date ) ) {
				$reference->updated = $branch->commit->commit->author->date;
			}

			return $reference;
		}

		/**
		 * Retrieve the latest commit that modified a file.
		 *
		 * @param string $filename File path.
		 * @param string $ref      Branch, tag, or commit reference.
		 * @return object|null Latest commit, or null when unavailable.
		 */
		public function get_latest_commit( $filename, $ref = 'main' ) {
			$commits = $this->api(
				'/repos/:user/:repo/commits',
				array(
					'path'     => $filename,
					'sha'      => $ref,
					'per_page' => 1,
				)
			);

			return ! is_wp_error( $commits ) && isset( $commits[0] ) ? $commits[0] : null;
		}

		/**
		 * Retrieve the latest commit timestamp for a reference.
		 *
		 * @param string $ref Branch, tag, or commit reference.
		 * @return string|null Commit timestamp, or null when unavailable.
		 */
		public function get_latest_commit_time( $ref ) {
			$commits = $this->api(
				'/repos/:user/:repo/commits',
				array(
					'sha'      => $ref,
					'per_page' => 1,
				)
			);

			if ( ! is_wp_error( $commits ) && isset( $commits[0]->commit->author->date ) ) {
				return $commits[0]->commit->author->date;
			}

			return null;
		}

		/**
		 * Retrieve a file from a Gitee repository.
		 *
		 * @param string $path File path.
		 * @param string $ref  Branch, tag, or commit reference.
		 * @return string|null Decoded file contents, or null when unavailable.
		 */
		public function get_remote_file( $path, $ref = 'main' ) {
			$path     = implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) ) );
			$response = $this->api(
				'/repos/:user/:repo/contents/' . $path,
				array( 'ref' => $ref )
			);

			if ( is_wp_error( $response ) || ! isset( $response->content, $response->encoding ) || 'base64' !== $response->encoding ) {
				return null;
			}

			return base64_decode( $response->content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		/**
		 * Build a Gitee ZIP archive download URL.
		 *
		 * @param string $ref Branch, tag, or commit reference.
		 * @return string Archive URL.
		 */
		public function build_archive_download_url( $ref = 'main' ) {
			$url = $this->build_api_url(
				'/repos/:user/:repo/zipball',
				array( 'ref' => $ref )
			);

			return $this->add_access_token( $url );
		}

		/**
		 * Set Gitee authentication credentials.
		 *
		 * @param string|array|null $credentials Authentication credentials.
		 * @return void
		 */
		public function set_authentication( $credentials ) {
			parent::set_authentication( $credentials );

			$this->access_token = is_string( $credentials ) ? $credentials : null;
		}

		/**
		 * Retrieve update detection strategies.
		 *
		 * @param string $config_branch Configured branch.
		 * @return array Update detection callbacks.
		 */
		protected function get_update_detection_strategies( $config_branch ) {
			$strategies = array();

			if (
				( 'main' === $config_branch || 'master' === $config_branch ) &&
				( ! defined( 'PUC_FORCE_BRANCH' ) || ! (bool) constant( 'PUC_FORCE_BRANCH' ) )
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
		 * Perform a Gitee API request.
		 *
		 * @param string $url          API path or fully qualified URL.
		 * @param array  $query_params Optional query parameters.
		 * @param bool   $override_url Whether the supplied URL is fully qualified.
		 * @return mixed|WP_Error Decoded response or an error.
		 */
		protected function api( $url, $query_params = array(), $override_url = false ) {
			$base_url = $url;

			if ( $override_url ) {
				$url = empty( $query_params ) ? $url : add_query_arg( $query_params, $url );
			} else {
				$url = $this->build_api_url( $url, $query_params );
			}

			$request_url = $this->add_access_token( $url );
			$response    = wp_remote_get(
				$request_url,
				array(
					'timeout'    => wp_doing_cron() ? 10 : 3,
					'user-agent' => 'UpdatePulse-Gitee/1.0',
				)
			);

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
				$document = json_decode( $body );

				if ( ! is_object( $document ) ) {
					$document = new \stdClass();
				}

				$document->code = $code;

				return $document;
			}

			$error = new WP_Error(
				'puc-gitee-http-error',
				sprintf( 'Gitee API error. Base URL: "%s", HTTP status code: %d.', $base_url, $code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $this->slug );

			return $error;
		}

		/**
		 * Build a fully qualified Gitee API URL.
		 *
		 * @param string $url          API path.
		 * @param array  $query_params Optional query parameters.
		 * @return string Fully qualified URL.
		 */
		protected function build_api_url( $url, $query_params ) {
			$url = str_replace(
				array( '/:user', '/:repo' ),
				array( '/' . rawurlencode( $this->user_name ), '/' . rawurlencode( $this->repository_name ) ),
				$url
			);
			$url = self::API_BASE_URL . $url;

			return empty( $query_params ) ? $url : add_query_arg( $query_params, $url );
		}

		/**
		 * Build a Reference from a Gitee tag response.
		 *
		 * @param object $tag Gitee tag response.
		 * @return Reference Tag reference.
		 */
		protected function build_tag_reference( $tag ) {
			$reference = new Reference(
				array(
					'name'         => $tag->name,
					'version'      => ltrim( $tag->name, 'v' ),
					'download_url' => $this->build_archive_download_url( $tag->name ),
					'apiResponse'  => $tag,
				)
			);

			if ( isset( $tag->commit->date ) ) {
				$reference->updated = $tag->commit->date;
			}

			return $reference;
		}

		/**
		 * Get a release download URL.
		 *
		 * @param object $release Gitee release response.
		 * @return string|null Download URL, or null if a required attachment is unavailable.
		 */
		protected function get_release_download_url( $release ) {
			if ( $this->release_assets_enabled && isset( $release->id ) ) {
				$assets = $this->api(
					'/repos/:user/:repo/releases/' . rawurlencode( $release->id ) . '/attach_files',
					array(
						'per_page'  => 100,
						'direction' => 'desc',
					)
				);

				if ( is_array( $assets ) ) {
					foreach ( $assets as $asset ) {
						if ( ! $this->matches_asset_filter( $asset ) || ! isset( $asset->id ) ) {
							continue;
						}

						if ( isset( $asset->browser_download_url ) ) {
							return $this->add_access_token( $asset->browser_download_url );
						}

						$url = $this->build_api_url(
							'/repos/:user/:repo/releases/' . rawurlencode( $release->id ) . '/attach_files/' . rawurlencode( $asset->id ) . '/download',
							array()
						);

						return $this->add_access_token( $url );
					}
				}

				if ( Api::REQUIRE_RELEASE_ASSETS === $this->release_asset_preference ) {
					return null;
				}
			}

			return $this->build_archive_download_url( $release->tag_name );
		}

		/**
		 * Retrieve the filterable release attachment name.
		 *
		 * @param object $release_asset Gitee attachment response.
		 * @return string|null Attachment name, or null when unavailable.
		 */
		protected function get_filterable_asset_name( $release_asset ) {
			return isset( $release_asset->name ) ? $release_asset->name : null;
		}

		/**
		 * Append the Gitee PAT using the API v5 authentication parameter.
		 *
		 * @param string $url Request URL.
		 * @return string Authenticated request URL.
		 */
		protected function add_access_token( $url ) {
			if ( ! $this->is_authentication_enabled() || ! $this->access_token ) {
				return $url;
			}

			return add_query_arg( 'access_token', $this->access_token, $url );
		}
	}

endif;
