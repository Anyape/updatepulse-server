<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class UPServ_License_Update_Server extends UPServ_Update_Server {

	protected $license_key;
	protected $license_signature;

	public function __construct(
		$use_remote_repository,
		$server_url,
		$server_directory,
		$repository_service_url,
		$repository_branch,
		$repository_credentials,
		$repository_service_self_hosted
	) {
		parent::__construct(
			$use_remote_repository,
			$server_url,
			$server_directory,
			$repository_service_url,
			$repository_branch,
			$repository_credentials,
			$repository_service_self_hosted,
		);

		$this->repository_service_url = $repository_service_url;
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	protected function initRequest( $query = null, $headers = null ) {
		$request = parent::initRequest( $query, $headers );
		$result  = false;

		if ( $request->param( 'license_key' ) ) {

			if ( $request->param( 'licensed_with' ) ) {
				$info = upserv_get_package_info( $request->slug, false );

				if (
					$info &&
					isset( $info['licensed_with'] ) &&
					$request->param( 'licensed_with' ) === $info['licensed_with']
				) {
					$main_package_info = upserv_get_package_info( $info['licensed_with'], false );
					$result            = $this->verify_license_exists(
						$info['licensed_with'],
						$main_package_info['type'],
						$request->param( 'license_key' )
					);
				}
			}

			if ( ! $result ) {
				$result = $this->verify_license_exists(
					$request->slug,
					$request->type,
					$request->param( 'license_key' )
				);
			}

			$request->license_key       = $request->param( 'license_key' );
			$request->license_signature = $request->param( 'license_signature' );
			$request->license           = $result;

			$this->license_key       = $request->license_key;
			$this->license_signature = $request->license_signature;
		}

		return $request;
	}

	protected function filterMetadata( $meta, $request ) {
		$meta              = parent::filterMetadata( $meta, $request );
		$license           = $request->license;
		$license_signature = $request->license_signature;

		if ( is_object( $license ) || is_array( $license ) ) {
			$meta['license'] = $this->prepare_license_for_output( $license );
		}

		if (
			apply_filters(
				'upserv_license_valid',
				$this->is_license_valid( $license, $license_signature ),
				$license,
				$license_signature
			)
		) {
			$args                 = array(
				'license_key'       => $request->license_key,
				'license_signature' => $request->license_signature,
			);
			$meta['download_url'] = self::addQueryArg( $args, $meta['download_url'] );
		} else {
			unset( $meta['download_url'] );
			unset( $meta['license'] );

			$meta['license_error'] = $this->get_license_error_message( $license );
		}

		return $meta;
	}

	protected function checkAuthorization( $request ) {
		parent::checkAuthorization( $request );

		$license           = $request->license;
		$license_signature = $request->license_signature;
		$valid             = $this->is_license_valid( $license, $license_signature );

		if (
			'download' === $request->action &&
			! apply_filters( 'upserv_license_valid', $valid, $license, $license_signature )
		) {
			$this->exitWithError( 'Invalid license key or signature.', 403 );
		}
	}

	protected function generateDownloadUrl( Wpup_Package $package ) {
		$query = array(
			'token'             => upserv_create_nonce( true, DAY_IN_SECONDS / 2 ),
			'license_key'       => $this->license_key,
			'license_signature' => $this->license_signature,
		);

		return self::addQueryArg( $query, parent::generateDownloadUrl( $package ) );
	}

	// Misc. -------------------------------------------------------

	protected function get_license_error_message( $license ) {

		if ( ! $license ) {
			$error = (object) array();

			return $error;
		}

		if ( ! is_object( $license ) ) {
			$error = (object) array(
				'license_key' => $this->license_key,
			);

			return $error;
		}

		switch ( $license->status ) {
			case 'blocked':
				$error = (object) array(
					'status' => 'blocked',
				);

				return $error;
			case 'expired':
				$error = (object) array(
					'status'      => 'expired',
					'date_expiry' => $license->date_expiry,
				);

				return $error;
			case 'pending':
				$error = (object) array(
					'status' => 'pending',
				);

				return $error;
			default:
				$error = (object) array(
					'status' => 'invalid',
				);

				return $error;
		}
	}

	protected function verify_license_exists( $slug, $type, $license_key ) {
		$license_server = new UPServ_License_Server();
		$payload        = array( 'license_key' => $license_key );
		$result         = $license_server->read_license( $payload );

		if (
			is_object( $result ) &&
			$slug === $result->package_slug &&
			strtolower( $type ) === strtolower( $result->package_type )
		) {
			$result->result  = 'success';
			$result->message = __( 'License key details retrieved.', 'updatepulse-server' );
		} else {
			$result = false;
		}

		return $result;
	}

	protected function prepare_license_for_output( $license ) {
		$output = json_decode( wp_json_encode( $license ), true );

		unset( $output['id'] );
		unset( $output['hmac_key'] );
		unset( $output['crypto_key'] );
		unset( $output['data'] );
		unset( $output['owner_name'] );
		unset( $output['email'] );
		unset( $output['company_name'] );

		return apply_filters( 'upserv_license_update_server_prepare_license_for_output', $output, $license );
	}

	protected function is_license_valid( $license, $license_signature ) {
		$valid = false;

		if ( is_object( $license ) && ! is_wp_error( $license ) && 'activated' === $license->status ) {

			if ( apply_filters( 'upserv_license_bypass_signature', false, $license ) ) {
				$valid = $this->license_key === $license->license_key;
			} else {
				$license_server = new UPServ_License_Server();
				$valid          = $this->license_key === $license->license_key &&
					$license_server->is_signature_valid( $license->license_key, $license_signature );
			}
		}

		return $valid;
	}
}
