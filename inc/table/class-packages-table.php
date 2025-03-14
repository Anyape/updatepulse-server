<?php

namespace Anyape\UpdatePulse\Server\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_List_Table;
use DateTimeZone;

class Packages_Table extends WP_List_Table {

	public $bulk_action_error;
	public $nonce_action;

	protected $rows;
	protected $package_manager;

	public function __construct( $package_manager ) {
		parent::__construct(
			array(
				'singular' => 'upserv-packages-table',
				'plural'   => 'upserv-packages-table',
				'ajax'     => false,
			)
		);

		$this->nonce_action    = 'bulk-upserv-packages-table';
		$this->package_manager = $package_manager;
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	public function get_columns() {
		$columns = apply_filters(
			'upserv_packages_table_columns',
			array(
				'cb'                     => '<input type="checkbox" />',
				'col_name'               => __( 'Package Name', 'updatepulse-server' ),
				'col_version'            => __( 'Version', 'updatepulse-server' ),
				'col_type'               => __( 'Type', 'updatepulse-server' ),
				'col_file_name'          => __( 'File Name', 'updatepulse-server' ),
				'col_file_size'          => __( 'Size', 'updatepulse-server' ),
				'col_file_last_modified' => __( 'File Modified ', 'updatepulse-server' ),
				'col_origin'             => __( 'Origin', 'updatepulse-server' ),
			)
		);

		return $columns;
	}

	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	public function get_sortable_columns() {
		$columns = apply_filters(
			'upserv_packages_table_sortable_columns',
			array(
				'col_name'               => array( 'name', false ),
				'col_version'            => array( 'version', false ),
				'col_type'               => array( 'type', false ),
				'col_file_name'          => array( 'file_name', false ),
				'col_file_size'          => array( 'file_size', false ),
				'col_file_last_modified' => array( 'file_last_modified', false ),
				'col_origin'             => array( 'origin', false ),
			)
		);

		return $columns;
	}

	public function prepare_items() {
		$total_items = count( $this->rows );
		$offset      = 0;
		$per_page    = $this->get_items_per_page( 'packages_per_page', 10 );
		$paged       = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$total_pages = ceil( $total_items / $per_page );

		if ( ! empty( $paged ) && ! empty( $per_page ) ) {
			$offset = ( $paged - 1 ) * $per_page;
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'total_pages' => $total_pages,
				'per_page'    => $per_page,
			)
		);

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->process_bulk_action();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = array_slice( $this->rows, $offset, $per_page );

		uasort( $this->items, array( &$this, 'uasort_reorder' ) );
	}


	public function display_rows() {
		$records = $this->items;
		$table   = $this;

		list( $columns, $hidden ) = $this->get_column_info();

		if ( ! empty( $records ) ) {
			$date_format = 'Y-m-d';
			$time_format = 'H:i:s';
			$time_zone   = new DateTimeZone( wp_timezone_string() );
			$primary     = $this->get_primary_column_name();

			foreach ( $records as $record_key => $record ) {

				if (
					isset(
						$record['metadata']['vcs_key'],
						$record['metadata']['origin'],
						$record['metadata']['vcs']
					) &&
					'vcs' === $record['metadata']['origin']
				) {
					$url           = untrailingslashit( $record['metadata']['vcs'] );
					$vcs_config    = upserv_get_option( 'vcs/' . $record['metadata']['vcs_key'] );
					$record['vcs'] = empty( $vcs_config ) ? array() : array(
						'url'        => $url,
						'identifier' => substr( $url, strrpos( $url, '/' ) + 1 ),
						'branch'     => $vcs_config['branch'],
						'class'      => $this->get_vcs_class( $vcs_config ),
					);
				}

				if ( ! isset( $record['metadata']['origin'] ) ) {
					$record['metadata']['origin'] = 'unknown';
				}

				$record['update_url'] = add_query_arg(
					array(
						'action'               => 'get_metadata',
						'package_id'           => $record['slug'],
						'installed_version'    => $record['version'],
						'php'                  => PHP_VERSION,
						'locale'               => get_locale(),
						'checking_for_updates' => 1,
						'update_type'          => ucfirst( $record['type'] ),
					),
					home_url( '/updatepulse-server-update-api/' )
				);

				$info           = $record;
				$unset_metadata = array( 'previous', 'branch', 'vcs_key', 'vcs', 'whitelist' );

				foreach ( $unset_metadata as $key ) {
					unset( $info['metadata'][ $key ] );
				}

				if ( isset( $info['vcs'] ) ) {
					$info['vcs']['type']        = $vcs_config['type'];
					$info['vcs']['self_hosted'] = $vcs_config['self_hosted'];

					unset( $info['vcs']['class'] );
				}

				$page           = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$search         = ! empty( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$record['info'] = wp_json_encode(
					$info,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
						JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
				);

				upserv_get_admin_template(
					'packages-table-row.php',
					array(
						'table'       => $table,
						'columns'     => $columns,
						'hidden'      => $hidden,
						'records'     => $records,
						'record_key'  => $record_key,
						'record'      => $record,
						'date_format' => $date_format,
						'time_format' => $time_format,
						'time_zone'   => $time_zone,
						'primary'     => $primary,
						'page'        => $page,
						'search'      => $search,
					)
				);
			}
		}
	}

	// Misc. -------------------------------------------------------

	public function set_rows( $rows ) {
		$this->rows = $rows;
	}

	public function uasort_reorder( $a, $b ) {
		$order_by = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result   = 0;

		if ( ! in_array( str_replace( 'col_', '', $order_by ), array_keys( $this->get_sortable_columns() ), true ) ) {
			$order_by = 'name';
		}

		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'asc';
		}

		if ( 'version' === $order_by ) {
			$result = version_compare( $a[ $order_by ], $b[ $order_by ] );
		} elseif ( 'file_size' === $order_by ) {
			$result = $a[ $order_by ] - $b[ $order_by ];
		} elseif ( 'file_last_modified' === $order_by ) {
			$result = $a[ $order_by ] - $b[ $order_by ];
		} else {
			$result = strcmp( $a[ $order_by ], $b[ $order_by ] );
		}

		return ( 'asc' === $order ) ? $result : -$result;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	protected function extra_tablenav( $which ) {

		if ( 'top' === $which ) {

			if ( 'max_file_size_exceeded' === $this->bulk_action_error ) {
				$class   = 'notice notice-error';
				$message = esc_html__( 'Download: Archive max size exceeded - try to adjust it in the settings below.', 'updatepulse-server' );

				printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->bulk_action_error = '';
			}
		} elseif ( 'bottom' === $which ) {
			print '<div class="alignleft actions bulkactions"><input id="post-query-submit" type="button" name="upserv_delete_all_packages" value="' . esc_html( __( 'Delete All Packages', 'updatepulse-server' ) ) . '" class="button upserv-delete-all-packages"></div>';
		}
	}

	protected function get_table_classes() {
		$mode       = get_user_setting( 'posts_list_mode', 'list' );
		$mode_class = esc_attr( 'table-view-' . $mode );

		return array( 'widefat', 'striped', $mode_class, $this->_args['plural'] );
	}

	protected function get_bulk_actions() {
		$actions = apply_filters(
			'upserv_packages_table_bulk_actions',
			array(
				'delete'   => __( 'Delete' ),
				'download' => __( 'Download', 'updatepulse-server' ),
			)
		);

		return $actions;
	}

	protected function get_vcs_class( $vcs_config ) {

		switch ( $vcs_config['type'] ) {
			case 'github':
				return 'fa-brands fa-github';
			case 'gitlab':
				return $vcs_config['self_hosted'] ? 'fa-brands fa-square-gitlab' : 'fa-brands fa-gitlab';
			case 'bitbucket':
				return 'fa-brands fa-bitbucket';
			default:
				return 'fa-code-commit';
		}
	}
}
