<?php

namespace Anyape\UpdatePulse\Server\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_List_Table;
use DateTimeZone;

/**
 * Packages Table class
 *
 * Manages the display of packages in the admin area
 *
 * @since 1.0.0
 */
class Packages_Table extends WP_List_Table {

	/**
	 * Bulk action error message
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	public $bulk_action_error;
	/**
	 * Nonce action name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $nonce_action;

	/**
	 * Table rows data
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $rows;
	/**
	 * Package manager instance
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $package_manager;

	/**
	 * Constructor
	 *
	 * @param object $package_manager The package manager instance
	 * @since 1.0.0
	 */
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

	/**
	 * Get table columns
	 *
	 * Define the columns shown in the packages table.
	 *
	 * @return array Table columns
	 * @since 1.0.0
	 */
	public function get_columns() {
		/**
		 * Filter the columns shown in the packages table.
		 *
		 * @param array $columns The default columns for the packages table
		 * @return array The filtered columns
		 * @since 1.0.0
		 */
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

	/**
	 * Default column renderer
	 *
	 * Default handler for columns without specific renderers.
	 *
	 * @param array $item The current row item
	 * @param string $column_name The current column name
	 * @return mixed Column content
	 * @since 1.0.0
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Get sortable columns
	 *
	 * Define which columns can be sorted in the table.
	 *
	 * @return array Sortable columns configuration
	 * @since 1.0.0
	 */
	public function get_sortable_columns() {
		/**
		 * Filter the sortable columns in the packages table.
		 *
		 * @param array $columns The default sortable columns
		 * @return array The filtered sortable columns
		 * @since 1.0.0
		 */
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

	/**
	 * Prepare table items
	 *
	 * Process data for table display including pagination.
	 *
	 * @since 1.0.0
	 */
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

	/**
	 * Display table rows
	 *
	 * Render the rows of the packages table.
	 *
	 * @since 1.0.0
	 */
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
						'brand'      => $this->get_vcs_brand( $vcs_config ),
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

					unset( $info['vcs']['brand'] );
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

	/**
	 * Set table rows
	 *
	 * Set the rows data for the table.
	 *
	 * @param array $rows Table rows data
	 * @since 1.0.0
	 */
	public function set_rows( $rows ) {
		$this->rows = $rows;
	}

	/**
	 * Custom sorting function
	 *
	 * Sort table items based on request parameters.
	 *
	 * @param array $a First item to compare
	 * @param array $b Second item to compare
	 * @return int Comparison result
	 * @since 1.0.0
	 */
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

	/**
	 * Display extra table navigation
	 *
	 * Add additional controls above or below the table.
	 *
	 * @param string $which Position ('top' or 'bottom')
	 * @since 1.0.0
	 */
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

	/**
	 * Get table CSS classes
	 *
	 * Define the CSS classes for the table.
	 *
	 * @return array Table CSS classes
	 * @since 1.0.0
	 */
	protected function get_table_classes() {
		$mode       = get_user_setting( 'posts_list_mode', 'list' );
		$mode_class = esc_attr( 'table-view-' . $mode );

		return array( 'widefat', 'striped', $mode_class, $this->_args['plural'] );
	}

	/**
	 * Get bulk actions
	 *
	 * Define available bulk actions for the table.
	 *
	 * @return array Bulk actions
	 * @since 1.0.0
	 */
	protected function get_bulk_actions() {
		/**
		 * Filter the bulk actions available in the packages table.
		 *
		 * @param array $actions The default bulk actions
		 * @return array The filtered bulk actions
		 * @since 1.0.0
		 */
		$actions = apply_filters(
			'upserv_packages_table_bulk_actions',
			array(
				'delete'   => __( 'Delete' ),
				'download' => __( 'Download', 'updatepulse-server' ),
			)
		);

		return $actions;
	}

	/**
	 * Get VCS icon brand
	 *
	 * Get the appropriate brand icon identifier for a VCS provider.
	 *
	 * @param array $vcs_config VCS configuration
	 * @return string Brand icon identifier.
	 * @since 1.0.0
	 */
	protected function get_vcs_brand( $vcs_config ) {

		switch ( $vcs_config['type'] ) {
			case 'github':
				return 'github';
			case 'gitlab':
				return $vcs_config['self_hosted'] ? 'gitlab-self-hosted' : 'gitlab';
			case 'bitbucket':
				return 'bitbucket';
			case 'gitea':
				return 'gitea';
			case 'forgejo':
				return 'forgejo';
			case 'gitee':
				return 'gitee';
			default:
				return '';
		}
	}
}
