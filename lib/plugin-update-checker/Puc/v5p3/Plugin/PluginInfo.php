<?php
namespace Anyape\PluginUpdateChecker\v5p3\Plugin;

use Anyape\PluginUpdateChecker\v5p3\Metadata;

if ( !class_exists(PluginInfo::class, false) ):

	/**
	 * A container class for holding and transforming various plugin metadata.
	 *
	 * @author Janis Elsts
	 * @copyright 2016
	 * @access public
	 */
	class PluginInfo extends Metadata {
		//Most fields map directly to the contents of the plugin's info.json file.
		//See the relevant docs for a description of their meaning.
		public $name;
		public $slug;
		public $version;
		public $homepage;
		public $sections = array();
		public $download_url;

		public $banners;
		public $icons = array();
		public $translations = array();

		public $author;
		public $author_homepage;

		public $requires;
		public $tested;
		public $requires_php;
		public $upgrade_notice;

		public $rating;
		public $num_ratings;
		public $downloaded;
		public $active_installs;
		public $last_updated;

		public $id = 0; //The native WP.org API returns numeric plugin IDs, but they're not used for anything.

		public $filename; //Plugin filename relative to the plugins directory.

		/**
		 * Create a new instance of Plugin Info from JSON-encoded plugin info
		 * returned by an external update API.
		 *
		 * @param string $json Valid JSON string representing plugin info.
		 * @return self|null New instance of Plugin Info, or NULL on error.
		 */
		public static function fromJson($json){
			$instance = new self();

			if ( !parent::createFromJson($json, $instance) ) {
				return null;
			}

			//json_decode decodes assoc. arrays as objects. We want them as arrays.
			$instance->sections = (array)$instance->sections;
			$instance->icons = (array)$instance->icons;

			return $instance;
		}

		/**
		 * Very, very basic validation.
		 *
		 * @param \StdClass $apiResponse
		 * @return bool|\WP_Error
		 */
		protected function validateMetadata($apiResponse) {
			if (
				!isset($apiResponse->name, $apiResponse->version)
				|| empty($apiResponse->name)
				|| empty($apiResponse->version)
			) {
				return new \WP_Error(
					'puc-invalid-metadata',
					"The plugin metadata file does not contain the required 'name' and/or 'version' keys."
				);
			}
			return true;
		}

		protected function getFormattedAuthor() {
			if ( !empty($this->author_homepage) ){
				/** @noinspection HtmlUnknownTarget */
				return sprintf('<a href="%s">%s</a>', $this->author_homepage, $this->author);
			}
			return $this->author;
		}
	}

endif;
