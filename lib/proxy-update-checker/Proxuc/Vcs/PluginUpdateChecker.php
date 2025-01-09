<?php

namespace Anyape\ProxyUpdateChecker\Vcs;

use YahnisElsts\PluginUpdateChecker\v5p3\Vcs\BaseChecker;
use YahnisElsts\PluginUpdateChecker\v5p3\Plugin\Package;
use YahnisElsts\PluginUpdateChecker\v5p3\Plugin\UpdateChecker;
use YahnisElsts\PluginUpdateChecker\v5p3\Plugin\PluginInfo;

if ( ! class_exists(PluginUpdateChecker::class, false) ):

	class PluginUpdateChecker extends UpdateChecker implements BaseChecker {
		protected $branch = 'main';
		protected $api = null;
		protected $package = null;

		public function __construct($api, $slug, $file_name, $container) {
			$this->api = $api;
			$this->api->setHttpFilterName('puc_plugin_request_info_options');
			$this->pluginAbsolutePath = trailingslashit($container) . $slug;
			$this->pluginFile = $slug . '/' . $file_name . '.php';
			$this->debugMode = (bool)(constant('WP_DEBUG'));
			$this->metadataUrl = $api->getRepositoryUrl();
			$this->directoryName = basename(dirname($this->pluginAbsolutePath));
			$this->slug = $slug;
			$this->package = new Package($this->pluginAbsolutePath, $this);
			$this->optionName = 'external_updates-' . $this->slug;
			$this->api->setSlug($this->slug);
		}

		public function Vcs_getAbsoluteDirectoryPath() {
			return trailingslashit($this->pluginAbsolutePath);
		}

		public function requestInfo($unused = null) {
			//We have to make several remote API requests to gather all the necessary info
			//which can take a while on slow networks.
			if ( function_exists('set_time_limit') ) {
				@set_time_limit(60);
			}

			$api = $this->api;
			$api->setLocalDirectory($this->Vcs_getAbsoluteDirectoryPath());

			$info = new PluginInfo();
			$info->filename = $this->pluginFile;
			$info->slug = $this->slug;

			$this->setInfoFromHeader($this->Vcs_getPluginHeader(), $info);

			//Pick a branch or tag.
			$updateSource = $api->chooseReference($this->branch);

			if ( $updateSource ) {
				$ref = $updateSource->name;
				$info->version = $updateSource->version;
				$info->last_updated = $updateSource->updated;
				$info->download_url = $updateSource->downloadUrl;
			} else {
				//There's probably a network problem or an authentication error.
				return new WP_Error(
					'puc-no-update-source',
					'Could not retrieve version information from the repository for '
					. $this->slug . '.'
					. 'This usually means that the update checker either can\'t connect '
					. 'to the repository or it\'s configured incorrectly.'
				);
			}

			//Get headers from the main plugin file in this branch/tag. Its "Version" header and other metadata
			//are what the WordPress install will actually see after upgrading, so they take precedence over releases/tags.
			$mainPluginFile = basename($this->pluginFile);
			$remotePlugin = $api->getRemoteFile($mainPluginFile, $ref);

			if ( !empty($remotePlugin) ) {
				$remoteHeader = $this->package->getFileHeader($remotePlugin);
				$this->setInfoFromHeader($remoteHeader, $info);
			}

			//Try parsing readme.txt. If it's formatted according to WordPress.org standards, it will contain
			//a lot of useful information like the required/tested WP version, changelog, and so on.
			if ( $this->readmeTxtExistsLocally() ) {
				$this->setInfoFromRemoteReadme($ref, $info);
			}

			if ( empty($info->last_updated) ) {
				//Fetch the latest commit that changed the tag or branch and use it as the "last_updated" date.
				$latestCommitTime = $api->getLatestCommitTime($ref);

				if ( $latestCommitTime !== null ) {
					$info->last_updated = $latestCommitTime;
				}
			}

			$info->download_url = $this->api->signDownloadUrl($info->download_url);

			$info = array(
				'type'         => 'Plugin',
				'version'      => $info->version,
				'main_file'    => $info->filename,
				'download_url' => $info->download_url,
			);

			/**
			 * Filter the info that will be returned by the update checker.
			 *
			 * @param array $info The info that will be returned by the update checker.
			 * @param YahnisElsts\PluginUpdateChecker\v5p3\Vcs\API $api The API object that was used to fetch the info.
			 * @param string $ref The tag or branch that was used to fetch the info.
			 * @param YahnisElsts\PluginUpdateChecker\v5p3\UpdateChecker $checker The update checker object calling this filter.
			 */
			$info = apply_filters(
				'puc_request_info_result',
				$info,
				$api,
				$ref,
				$this
			);

			return $info;
		}

		protected function readmeTxtExistsLocally() {
			return false;
		}

		protected function Vcs_getPluginHeader() {

			if ( ! is_file($this->pluginAbsolutePath) ) {
				//This can happen if the plugin filename is wrong OR there is no local package just yet.
				return array();
			}

			return $this->get_plugin_data();
		}

		protected function get_plugin_data() {
			$plugin_file = $this->pluginFile;

			$default_headers = array(
				'Name' => 'Plugin Name',
				'PluginURI' => 'Plugin URI',
				'Version' => 'Version',
				'Description' => 'Description',
				'Author' => 'Author',
				'AuthorURI' => 'Author URI',
				'TextDomain' => 'Text Domain',
				'DomainPath' => 'Domain Path',
				'Network' => 'Network',
				// Site Wide Only is deprecated in favor of Network.
				'_sitewide' => 'Site Wide Only',
			);

			$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

			// Site Wide Only is the old header for Network
			if ( ! $plugin_data['Network'] && $plugin_data['_sitewide'] ) {
				/* translators: 1: Site Wide Only: true, 2: Network: true */
				_deprecated_argument( __FUNCTION__, '3.0.0', sprintf( __( 'The %1$s plugin header is deprecated. Use %2$s instead.' ), '<code>Site Wide Only: true</code>', '<code>Network: true</code>' ) );
				$plugin_data['Network'] = $plugin_data['_sitewide'];
			}
			$plugin_data['Network'] = ( 'true' == strtolower( $plugin_data['Network'] ) );
			unset( $plugin_data['_sitewide'] );

			// If no text domain is defined fall back to the plugin slug.
			if ( ! $plugin_data['TextDomain'] ) {
				$plugin_slug = $this->slug;
				if ( '.' !== $plugin_slug && false === strpos( $plugin_slug, '/' ) ) {
					$plugin_data['TextDomain'] = $plugin_slug;
				}
			}

			$plugin_data['Title']      = $plugin_data['Name'];
			$plugin_data['AuthorName'] = $plugin_data['Author'];

			return $plugin_data;
		}

		protected function setInfoFromHeader($fileHeader, $pluginInfo) {
			$headerToPropertyMap = array(
				'Version' => 'version',
				'Name' => 'name',
				'PluginURI' => 'homepage',
				'Author' => 'author',
				'AuthorName' => 'author',
				'AuthorURI' => 'author_homepage',
				'Requires WP' => 'requires',
				'Tested WP' => 'tested',
				'Requires at least' => 'requires',
				'Tested up to' => 'tested',
			);

			foreach ($headerToPropertyMap as $headerName => $property) {
				if ( isset($fileHeader[$headerName]) && !empty($fileHeader[$headerName]) ) {
					$pluginInfo->$property = $fileHeader[$headerName];
				}
			}

			if ( !empty($fileHeader['Description']) ) {
				$pluginInfo->sections['description'] = $fileHeader['Description'];
			}
		}

		protected function setInfoFromRemoteReadme($ref, $pluginInfo) {
			$readme = $this->api->getRemoteReadme($ref);
			if ( empty($readme) ) {
				return;
			}

			if ( isset($readme['sections']) ) {
				$pluginInfo->sections = array_merge($pluginInfo->sections, $readme['sections']);
			}
			if ( !empty($readme['tested_up_to']) ) {
				$pluginInfo->tested = $readme['tested_up_to'];
			}
			if ( !empty($readme['requires_at_least']) ) {
				$pluginInfo->requires = $readme['requires_at_least'];
			}

			if ( isset($readme['upgrade_notice'], $readme['upgrade_notice'][$pluginInfo->version]) ) {
				$pluginInfo->upgrade_notice = $readme['upgrade_notice'][$pluginInfo->version];
			}
		}

		public function setBranch($branch) {
			$this->branch = $branch;
			return $this;
		}

		public function setAuthentication($credentials) {
			$this->api->setAuthentication($credentials);
			return $this;
		}

		public function getVcsApi() {
			return $this->api;
		}

		public function getUpdate() {
			$update = parent::getUpdate();

			if ( isset($update) && !empty($update->download_url) ) {
				$update->download_url = $this->api->signDownloadUrl($update->download_url);
			}

			return $update;
		}
	}

endif;