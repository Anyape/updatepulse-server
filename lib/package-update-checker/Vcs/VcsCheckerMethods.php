<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName

namespace Anyape\PackageUpdateChecker\Vcs;

if ( ! trait_exists( VcsCheckerMethods::class, false ) ) :

	/**
	 * Trait VcsCheckerMethods
	 *
	 * Provides common functionality for version control system (VCS) update checking.
	 * Implements methods for branch management, authentication, and configuration display.
	 */
	trait VcsCheckerMethods {

		/**
		 * @var string The branch where to look for updates. Defaults to "main".
		 */
		protected $branch = 'main';
		/**
		 * @var Api Repository API client.
		 */
		protected $api = null;

		/**
		 * Sets the branch to check for updates.
		 *
		 * @param string $branch The branch name to set.
		 * @return $this For method chaining.
		 */
		public function set_branch( $branch ) {
			$this->branch = $branch;

			return $this;
		}

		/**
		 * Set authentication credentials.
		 *
		 * @param array|string $credentials Authentication credentials for the VCS API.
		 * @return $this For method chaining.
		 */
		public function set_authentication( $credentials ) {
			$this->api->set_authentication( $credentials );

			return $this;
		}

		/**
		 * Gets the VCS API client instance.
		 *
		 * @return Api The VCS API client instance.
		 */
		public function get_vcs_api() {
			return $this->api;
		}

		/**
		 * Displays the configuration information in the given panel.
		 *
		 * @param object $panel The panel object used to display configuration.
		 * @return void
		 */
		public function on_display_configuration( $panel ) {
			parent::on_display_configuration( $panel );

			$panel->row( 'Branch', $this->branch );
			$panel->row( 'Authentication enabled', $this->api->is_authentication_enabled() ? 'Yes' : 'No' );
			$panel->row( 'API client', get_class( $this->api ) );
		}
	}

endif;
