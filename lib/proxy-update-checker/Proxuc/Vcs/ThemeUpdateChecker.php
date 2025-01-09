<?php

namespace Anyape\ProxyUpdateChecker\Vcs;

use YahnisElsts\PluginUpdateChecker\v5p3\Utils;
use YahnisElsts\PluginUpdateChecker\v5p3\Vcs\BaseChecker;
use YahnisElsts\PluginUpdateChecker\v5p3\Theme\Package;
use YahnisElsts\PluginUpdateChecker\v5p3\Theme\Update;
use YahnisElsts\PluginUpdateChecker\v5p3\Theme\UpdateChecker;

if (! class_exists(ThemeUpdateChecker::class, false)):

	class ThemeUpdateChecker extends UpdateChecker implements BaseChecker {
		public $themeAbsolutePath = '';
		protected $branch = 'main';
		protected $api = null;

		public function __construct($api, $slug, $container) {
			$this->api = $api;
			$this->api->setHttpFilterName('puc_theme_request_update_options');
			$this->stylesheet = $slug;
			$this->themeAbsolutePath = trailingslashit($container) . $slug;
			$this->debugMode = (bool)(constant('WP_DEBUG'));
			$this->metadataUrl = $api->getRepositoryUrl();
			$this->directoryName = basename(dirname($this->themeAbsolutePath));
			$this->slug = !empty($slug) ? $slug : $this->directoryName;
			$this->optionName = 'external_updates-' . $this->slug;
			$this->package = new Package($this->themeAbsolutePath, $this);
			$this->api->setSlug($this->slug);
		}

		public function Vcs_getAbsoluteDirectoryPath() {
			return trailingslashit($this->themeAbsolutePath);
		}

		public function requestInfo($unused = null) {
			$api = $this->api;
			$api->setLocalDirectory($this->Vcs_getAbsoluteDirectoryPath());

			$update = new Update();
			$update->slug = $this->slug;
			$update->version = null;

			//Figure out which reference (tag or branch) we'll use to get the latest version of the theme.
			$updateSource = $api->chooseReference($this->branch);

			if ($updateSource) {
				$ref = $updateSource->name;
				$update->download_url = $updateSource->downloadUrl;
			} else {
				return 'source_not_found';
			}

			//Get headers from the main stylesheet in this branch/tag. Its "Version" header and other metadata
			//are what the WordPress install will actually see after upgrading, so they take precedence over releases/tags.
			$file = $api->getRemoteFile('style.css', $ref);

			if (!empty($file)) {
				$remoteHeader = $this->package->getFileHeader($file);
				$update->version = Utils::findNotEmpty(array(
					$remoteHeader['Version'],
					Utils::get($updateSource, 'version'),
				));
			}

			if (empty($update->version)) {
				//It looks like we didn't find a valid update after all.
				$update = null;
			}

			$update = $this->filterUpdateResult($update);
			$info   = null;

			if ($update && 'source_not_found' !== $update) {

				if (!empty($update->download_url)) {
					$update->download_url = $this->api->signDownloadUrl($update->download_url);
				}

				$info = array(
					'type'         => 'Theme',
					'version'      => $update->version,
					'main_file'    => 'style.css',
					'download_url' => $update->download_url,
				);
			} elseif ('source_not_found' === $update) {
				return new WP_Error(
					'puc-no-update-source',
					'Could not retrieve version information from the repository for '
					. $this->slug . '.'
					. 'This usually means that the update checker either can\'t connect '
					. 'to the repository or it\'s configured incorrectly.'
				);
			}

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

			if (isset($update) && !empty($update->download_url)) {
				$update->download_url = $this->api->signDownloadUrl($update->download_url);
			}

			return $update;
		}
	}

endif;