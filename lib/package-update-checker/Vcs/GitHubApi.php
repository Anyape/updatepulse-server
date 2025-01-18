<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

use Parsedown;

if ( ! class_exists( GitHubApi::class, false ) ) :

	class GitHubApi extends Api {
		use ReleaseAssetSupport;
		use ReleaseFilteringFeature;

		/**
		 * @var string GitHub username.
		 */
		protected $user_name;
		/**
		 * @var string GitHub repository name.
		 */
		protected $repository_name;

		/**
		 * @var string Either a fully qualified repository URL, or just "user/repo-name".
		 */
		protected $repository_url;

		/**
		 * @var string GitHub authentication token. Optional.
		 */
		protected $access_token;

		/**
		 * @var bool
		 */
		private $download_filter_added = false;

		public function __construct( $repository_url, $access_token = null ) {
			$path = wp_parse_url( $repository_url, PHP_URL_PATH );

			if ( preg_match( '@^/?(?P<username>[^/]+?)/(?P<repository>[^/#?&]+?)/?$@', $path, $matches ) ) {
				$this->user_name       = $matches['username'];
				$this->repository_name = $matches['repository'];
			} else {
				throw new \InvalidArgumentException(
					esc_html( 'Invalid GitHub repository URL: "' . $repository_url . '"' )
				);
			}

			parent::__construct( $repository_url, $access_token );
		}

		/**
		 * Get the latest release from GitHub.
		 *
		 * @return Reference|null
		 */
		public function get_latest_release() {

			//The "latest release" endpoint returns one release and always skips pre-releases,
			//so we can only use it if that's compatible with the current filter settings.
			if (
				$this->should_skip_pre_releases()
				&& (
					( 1 === $this->release_filter_max_releases ) || ! $this->has_custom_release_filter()
				)
			) {
				//Just get the latest release.
				$release = $this->api( '/repos/:user/:repo/releases/latest' );

				if ( is_wp_error( $release ) || ! is_object( $release ) || ! isset( $release->tag_name ) ) {
					return null;
				}

				$found_releases = array( $release );
			} else {
				//Get a list of the most recent releases.
				$found_releases = $this->api(
					'/repos/:user/:repo/releases',
					array( 'per_page' => $this->release_filter_max_releases )
				);

				if ( is_wp_error( $found_releases ) || ! is_array( $found_releases ) ) {
					return null;
				}
			}

			foreach ( $found_releases as $release ) {

				//Always skip drafts.
				if ( isset( $release->draft ) && ! empty( $release->draft ) ) {
					continue;
				}

				//Skip pre-releases unless specifically included.
				if (
					$this->should_skip_pre_releases()
					&& isset( $release->prerelease )
					&& ! empty( $release->prerelease )
				) {
					continue;
				}

				$version_number = ltrim( $release->tag_name, 'v' ); //Remove the "v" prefix from "v1.2.3".

				//Custom release filtering.
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

					//Use the first release asset that matches the specified regular expression.
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
							//It seems that browser_download_url only works for public repositories.
							//Using an access_token doesn't help. Maybe OAuth would work?
							$reference->download_url = $matching_assets[0]->browser_download_url;
						}

						$reference->download_count = $matching_assets[0]->download_count;
					} elseif ( Api::REQUIRE_RELEASE_ASSETS === $this->release_asset_preference ) {
						//None of the assets match the filter, and we're not allowed
						//to fall back to the auto-generated source ZIP.
						return null;
					}
				}

				if ( ! empty( $release->body ) ) {
					$reference->changelog = Parsedown::instance()->text( $release->body );
				}

				return $reference;
			}

			return null;
		}

		/**
		 * Get the tag that looks like the highest version number.
		 *
		 * @return Reference|null
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
		 * Get a branch by name.
		 *
		 * @param string $branch_name
		 * @return null|Reference
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
		 * Get the latest commit that changed the specified file.
		 *
		 * @param string $filename
		 * @param string $ref Reference name ( e.g. branch or tag ).
		 * @return \StdClass|null
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
		 * Get the timestamp of the latest commit that changed the specified branch or tag.
		 *
		 * @param string $ref Reference name ( e.g. branch or tag ).
		 * @return string|null
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
		 * @param string $url
		 * @param array $query_params
		 * @return mixed|\WP_Error
		 */
		protected function api( $url, $query_params = array() ) {
			$base_url = $url;
			$url      = $this->build_api_url( $url, $query_params );

			$options = array( 'timeout' => wp_doing_cron() ? 10 : 3 );

			if ( $this->is_authentication_enabled() ) {
				$options['headers'] = array( 'Authorization' => $this->get_authorization_header() );
			}

			$response = wp_remote_get( $url, $options );

			if ( is_wp_error( $response ) ) {
				do_action( 'puc_api_error', $response, null, $url, $this->slug );
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 === $code ) {
				$document = json_decode( $body );

				return $document;
			}

			$error = new \WP_Error(
				'puc-github-http-error',
				sprintf( 'GitHub API error. Base URL: "%s",  HTTP status code: %d.', $base_url, $code )
			);

			do_action( 'puc_api_error', $error, $response, $url, $this->slug );

			return $error;
		}

		/**
		 * Build a fully qualified URL for an API request.
		 *
		 * @param string $url
		 * @param array $query_params
		 * @return string
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
		 * Get the contents of a file from a specific branch or tag.
		 *
		 * @param string $path File name.
		 * @param string $ref
		 * @return null|string Either the contents of the file, or null if the file doesn't exist or there's an error.
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
		 * @param string $ref
		 * @return string
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
		 * Get a specific tag.
		 *
		 * @param string $tag_name
		 * @return void
		 */
		public function get_tag( $tag_name ) {
			//The current GitHub update checker doesn't use get_tag, so I didn't bother to implement it.
			throw new \LogicException( 'The ' . __METHOD__ . ' method is not implemented and should not be used.' );
		}

		public function set_authentication( $credentials ) {
			parent::set_authentication( $credentials );

			$this->access_token = is_string( $credentials ) ? $credentials : null;
		}

		protected function get_update_detection_strategies( $config_branch ) {
			$strategies = array();

			if ( 'main' === $config_branch || 'master' === $config_branch ) {
				//Use the latest release.
				$strategies[ self::STRATEGY_LATEST_RELEASE ] = array( $this, 'get_latest_release' );
				//Failing that, use the tag with the highest version number.
				$strategies[ self::STRATEGY_LATEST_TAG ] = array( $this, 'get_latest_tag' );
			}

			//Alternatively, just use the branch itself.
			$strategies[ self::STRATEGY_BRANCH ] = function () use ( $config_branch ) {
				return $this->get_branch( $config_branch );
			};

			return $strategies;
		}

		/**
		 * Get the unchanging part of a release asset URL. Used to identify download attempts.
		 *
		 * @return string
		 */
		protected function get_asset_api_base_url() {
			return sprintf(
				'//api.github.com/repos/%1$s/%2$s/releases/assets/',
				$this->user_name,
				$this->repository_name
			);
		}

		protected function get_filterable_asset_name( $release_asset ) {

			if ( isset( $release_asset->name ) ) {
				return $release_asset->name;
			}

			return null;
		}

		/**
		 * @param bool $result
		 * @return bool
		 * @internal
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
		 * Set the HTTP headers that are necessary to download updates from private repositories.
		 *
		 * See GitHub docs:
		 *
		 * @link https://developer.github.com/v3/repos/releases/#get-a-single-release-asset
		 * @link https://developer.github.com/v3/auth/#basic-authentication
		 *
		 * @internal
		 * @param array $requestArgs
		 * @param string $url
		 * @return array
		 */
		public function set_update_download_headers( $request_args, $url = '' ) {

			//Is WordPress trying to download one of our release assets?
			if ( $this->release_assets_enabled && ( strpos( $url, $this->get_asset_api_base_url() ) !== false ) ) {
				$request_args['headers']['Accept'] = 'application/octet-stream';
			}

			//Use Basic authentication, but only if the download is from our repository.
			$repo_api_base_url = $this->build_api_url( '/repos/:user/:repo/', array() );

			if ( $this->is_authentication_enabled() && ( strpos( $url, $repo_api_base_url ) ) === 0 ) {
				$request_args['headers']['Authorization'] = $this->get_authorization_header();
			}

			return $request_args;
		}

		/**
		 * When following a redirect, the Requests library will automatically forward
		 * the authorization header to other hosts. We don't want that because it breaks
		 * AWS downloads and can leak authorization information.
		 *
		 * @param string $location
		 * @param array $headers
		 * @internal
		 */
		public function remove_auth_header_from_redirects( &$location, &$headers ) {
			$repo_api_base_url = $this->build_api_url( '/repos/:user/:repo/', array() );

			if ( strpos( $location, $repo_api_base_url ) === 0 ) {
				return; //This request is going to GitHub, so it's fine.
			}

			//Remove the header.
			if ( isset( $headers['Authorization'] ) ) {
				unset( $headers['Authorization'] );
			}
		}

		/**
		 * Generate the value of the "Authorization" header.
		 *
		 * @return string
		 */
		protected function get_authorization_header() {
			return 'Basic ' . base64_encode( $this->user_name . ':' . $this->access_token ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
	}

endif;
