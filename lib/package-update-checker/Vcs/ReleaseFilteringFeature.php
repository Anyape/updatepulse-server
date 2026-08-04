<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

if ( ! trait_exists( ReleaseFilteringFeature::class, false ) ) :

	/**
	 * Trait ReleaseFilteringFeature
	 *
	 * Provides functionality for filtering VCS releases based on version numbers,
	 * custom callbacks, and release types. Allows for flexible release selection
	 * through customizable filtering mechanisms.
	 */
	trait ReleaseFilteringFeature {

		/**
		 * Callback function for custom release filtering.
		 *
		 * @var callable|null
		 */
		protected $release_filter_callback = null;
		/**
		 * Maximum number of releases to check during filtering.
		 *
		 * @var int
		 */
		protected $release_filter_max_releases = 1;
		/**
		 * Release filtering type setting.
		 *
		 * @var string One of the Api::RELEASE_FILTER_* constants.
		 */
		protected $release_filter_by_type = Api::RELEASE_FILTER_SKIP_PRERELEASE;

		/**
		 * Set a custom release filter.
		 *
		 * Setting a new filter will override the old filter, if any.
		 *
		 * @param callable $callback      A callback that accepts a version number and a release
		 *                                object, and returns a boolean.
		 * @param int     $release_types  One of the Api::RELEASE_FILTER_* constants.
		 * @param int     $max_releases   Optional. The maximum number of recent releases to examine
		 *                                when trying to find a release that matches the filter. 1 to 100.
		 * @return $this
		 * @throws \InvalidArgumentException When max_releases is not between 1 and 100.
		 */
		public function set_release_filter(
			$callback,
			$release_types = Api::RELEASE_FILTER_SKIP_PRERELEASE,
			$max_releases = 20
		) {

			if ( $max_releases > 100 ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The max number of releases is too high (%d). It must be 100 or less.',
						esc_html( $max_releases )
					)
				);
			} elseif ( $max_releases < 1 ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The max number of releases is too low (%d). It must be at least 1.',
						esc_html( $max_releases )
					)
				);
			}

			$this->release_filter_callback     = $callback;
			$this->release_filter_by_type      = $release_types;
			$this->release_filter_max_releases = $max_releases;

			return $this;
		}

		/**
		 * Filter releases by their version number using a regular expression.
		 *
		 * @param string $regex                   A regular expression pattern to match version numbers.
		 * @param int    $release_types           Type of releases to filter (Api::RELEASE_FILTER_*).
		 * @param int    $max_releases_to_examine Maximum number of releases to check.
		 * @return $this
		 * @noinspection PhpUnused -- Public API
		 */
		public function set_release_version_filter(
			$regex,
			$release_types = Api::RELEASE_FILTER_SKIP_PRERELEASE,
			$max_releases_to_examine = 20
		) {
			return $this->set_release_filter(
				function ( $version_number ) use ( $regex ) {
					return ( preg_match( $regex, $version_number ) === 1 );
				},
				$release_types,
				$max_releases_to_examine
			);
		}

		/**
		 * Checks if a specific version number and release object match the custom filter criteria.
		 *
		 * @param string $version_number The detected release version number.
		 * @param object $release_object Release information object from the API.
		 * @return bool True if the release matches the filter criteria, false otherwise.
		 */
		protected function matches_custom_release_filter( $version_number, $release_object ) {

			if ( ! is_callable( $this->release_filter_callback ) ) {
				return true; //No custom filter.
			}

			return call_user_func( $this->release_filter_callback, $version_number, $release_object );
		}

		/**
		 * Determines if pre-release versions should be excluded from updates.
		 *
		 * @return bool True if pre-releases should be skipped, false otherwise.
		 */
		protected function should_skip_pre_releases() {
			//Maybe this could be a bitfield in the future, if we need to support more release types.
			return ( Api::RELEASE_FILTER_ALL !== $this->release_filter_by_type );
		}

		/**
		 * Checks if a custom release filter has been set and is callable.
		 *
		 * @return bool True if a custom filter is set and callable, false otherwise.
		 */
		protected function has_custom_release_filter() {
			return isset( $this->release_filter_callback ) && is_callable( $this->release_filter_callback );
		}
	}

endif;
