<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use Anyape\Utils\Utils;

/**
 * Zip Package Manager class
 *
 * @since 1.0.0
 */
class Zip_Package_Manager {

	/**
	 * Package slug
	 *
	 * @var string|WP_Error
	 * @since 1.0.0
	 */
	protected $package_slug;

	/**
	 * Path to the received package
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $received_package_path;

	/**
	 * Temporary directory path
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $tmp_dir;

	/**
	 * Packages directory path
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $packages_dir;

	/**
	 * Constructor
	 *
	 * @param string          $package_slug The package slug.
	 * @param string|WP_Error $received_package_path Path to the received package, or a download error.
	 * @param string          $tmp_dir Temporary directory path.
	 * @param string          $packages_dir Packages directory path.
	 * @since 1.0.0
	 */
	public function __construct( $package_slug, $received_package_path, $tmp_dir, $packages_dir ) {
		$this->package_slug          = $package_slug;
		$this->received_package_path = $received_package_path;
		$this->tmp_dir               = $tmp_dir;
		$this->packages_dir          = $packages_dir;
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Unzip package
	 *
	 * Extract a zip package to a destination.
	 *
	 * @param string $source Path to the source zip file.
	 * @param string $destination Path to the destination directory.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	public static function unzip_package( $source, $destination ) {
		return unzip_file( $source, $destination );
	}

	/**
	 * Zip package
	 *
	 * Create a zip archive from a directory or file.
	 *
	 * @param string $source Path to the source directory or file.
	 * @param string $destination Path to the destination zip file.
	 * @param string $container_dir Optional container directory within the zip.
	 * @return bool Whether the zip creation was successful.
	 * @since 1.0.0
	 */
	public static function zip_package( $source, $destination, $container_dir = '' ) {
		$zip = new ZipArchive();

		if ( ! $zip->open( $destination, ZIPARCHIVE::CREATE ) ) {
			return false;
		}

		if ( ! empty( $container_dir ) ) {
			$container_dir = trailingslashit( $container_dir );
		}

		$source = str_replace( '\\', '/', realpath( $source ) );

		if ( is_dir( $source ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$source,
					FilesystemIterator::SKIP_DOTS
				)
			);

			$it->rewind();

			while ( $it->valid() ) {
				$inner_it = $it->getInnerIterator();

				if ( $inner_it instanceof RecursiveDirectoryIterator ) {
					$file      = str_replace( '\\', '/', $it->key() );
					$file_name = $inner_it->getSubPathName();

					if ( true === is_dir( $file ) ) {
						$dir_name = $container_dir . trailingslashit( $file_name );

						$zip->addEmptyDir( $dir_name );
					} elseif ( true === is_file( $file ) ) {
						$zip->addFromString( $container_dir . $file_name, @file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}

				$it->next();
			}
		} elseif ( is_file( $source ) && '.' !== $source && '..' !== $source ) {
			$file_name = str_replace( ' ', '', basename( $source ) );

			if ( ! empty( $file_name ) ) {
				$zip->addFromString( $file_name, @file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		return $zip->close() && file_exists( $destination );
	}

	/**
	 * Clean package
	 *
	 * Clean the received package by moving and repacking it.
	 *
	 * @return bool True on success, false on failure.
	 * @since 1.0.0
	 */
	public function clean_package() {
		WP_Filesystem();

		global $wp_filesystem;

		$return        = true;
		$error_message = __METHOD__ . ': ';

		if ( $this->received_package_path instanceof WP_Error ) {
			$return         = false;
			$error_message .= $this->received_package_path->get_error_message();
		}

		if ( $return && ! $this->received_package_path ) {
			$return         = false;
			$error_message .= __( 'The received package path cannot be empty.', 'updatepulse-server' );
		}

		if ( $return && ! $wp_filesystem ) {
			$return         = false;
			$error_message .= __( 'Unavailable file system.', 'updatepulse-server' );
		}

		if ( $return ) {
			$source      = $this->received_package_path;
			$destination = $this->tmp_dir . $this->package_slug . '.zip';
			$result      = $wp_filesystem->move( $source, $destination, true );

			if ( $result ) {
				$repack_result = $this->repack_package();

				if ( ! $repack_result ) {
					$return         = false;
					$error_message .= sprintf(
						'Could not repack %s.',
						esc_html( $destination )
					);
				} else {
					$return = $repack_result;
				}
			} else {
				$return         = false;
				$error_message .= sprintf(
					'Could not move %s to %s.',
					esc_html( $source ),
					esc_html( $destination )
				);
			}
		}

		if ( $return ) {
			$source      = $this->tmp_dir . $this->package_slug . '.zip';
			$destination = trailingslashit( $this->packages_dir ) . $this->package_slug . '.zip';
			$result      = $wp_filesystem->move( $source, $destination, true );

			if ( ! $result ) {
				$return         = false;
				$error_message .= sprintf(
					'Could not move %s to %s.',
					esc_html( $source ),
					esc_html( $destination )
				);
			}
		}

		if ( ! $return ) {

			if ( (bool) ( constant( 'WP_DEBUG' ) ) ) {
				trigger_error( $error_message, E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			}

			Utils::php_log( $error_message );

			$wp_filesystem->delete( $this->received_package_path, true );
		}

		return $return;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Repack package
	 *
	 * Repack the received package by unzipping and zipping it again.
	 *
	 * @return bool True on success, false on failure.
	 * @since 1.0.0
	 */
	protected function repack_package() {
		WP_Filesystem();

		global $wp_filesystem;

		$temp_path    = trailingslashit( $this->tmp_dir . $this->package_slug );
		$archive_path = $this->tmp_dir . $this->package_slug . '.zip';

		if ( ! is_dir( $temp_path ) ) {
			wp_mkdir_p( $temp_path );
			$wp_filesystem->chmod( $temp_path, 0755, true );
		}

		$unzipped      = self::unzip_package( $archive_path, $temp_path );
		$return        = true;
		$error_message = __METHOD__ . ': ';

		$wp_filesystem->delete( $archive_path, true );

		if ( ! $unzipped ) {
			$return         = false;
			$error_message .= sprintf(
				'Could not unzip %s.',
				esc_html( $archive_path )
			);
		} else {
			$content         = array_diff( scandir( $temp_path ), array( '..', '.' ) );
			$maybe_directory = $temp_path . reset( $content );

			if ( ( 1 === count( $content ) && is_dir( $maybe_directory ) ) ) {
				$directory = $maybe_directory;

				if ( $directory !== $temp_path . $this->package_slug ) {
					$wp_filesystem->move( $directory, $temp_path . $this->package_slug );
				}

				$wp_filesystem->chmod( $temp_path, false, true );

				/**
				 * Fired during VCS package repacking, before the extracted files are packed.
				 * Fired during client update API request.
				 *
				 * @param string $package_slug The slug of the package.
				 * @param string $files_path The path of the directory where the package files are located.
				 * @param string $archive_path The path where the package archive will be located after packing.
				 */
				do_action( 'upserv_before_remote_package_zip', $this->package_slug, $temp_path, $archive_path );

				$zipped = self::zip_package( $temp_path, $archive_path );

				if ( $zipped ) {
					$wp_filesystem->chmod( $archive_path, 0755 );
				} else {
					$return         = false;
					$error_message .= sprintf(
						'Could not create archive from %s to %s - zipping failed',
						esc_html( $temp_path ),
						esc_html( $archive_path )
					);
				}
			} else {
				$return         = false;
				$error_message .= sprintf(
					'Could not create archive for %s - invalid remote package (must contain only one directory)',
					esc_html( $this->package_slug ),
				);
			}
		}

		$wp_filesystem->delete( $temp_path, true );

		if ( ! $return ) {

			if ( (bool) ( constant( 'WP_DEBUG' ) ) ) {
				trigger_error( $error_message, E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			}

			Utils::php_log( $error_message );
		}

		return $return;
	}
}
