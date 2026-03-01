<?php

namespace Anyape\UpdatePulse\Server\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_List_Table;
use Anyape\Utils\Utils;

/**
 * Licenses table class
 *
 * @since 1.0.0
 */
class Licenses_Table extends WP_List_Table {

	/**
	 * Bulk action error
	 *
	 * @var mixed
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
	 * Constructor
	 *
	 * Sets up the table properties and hooks
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'upserv-licenses-table',
				'plural'   => 'upserv-licenses-table',
				'ajax'     => false,
			)
		);

		$this->nonce_action = 'bulk-upserv-licenses-table';
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	/**
	 * Get table columns
	 *
	 * Define the columns for the licenses table
	 *
	 * @return array The table columns
	 * @since 1.0.0
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'col_license_key'  => __( 'License Key', 'updatepulse-server' ),
			'col_email'        => __( 'Registered Email', 'updatepulse-server' ),
			'col_status'       => __( 'Status', 'updatepulse-server' ),
			'col_package_type' => __( 'Package Type', 'updatepulse-server' ),
			'col_package_slug' => __( 'Package Slug', 'updatepulse-server' ),
			'col_date_created' => __( 'Creation Date', 'updatepulse-server' ),
			'col_date_expiry'  => __( 'Expiration Date', 'updatepulse-server' ),
			'col_id'           => __( 'ID', 'updatepulse-server' ),
		);
	}

	/**
	 * Default column rendering
	 *
	 * Default handler for displaying column data
	 *
	 * @param array $item The row item
	 * @param string $column_name The column name
	 * @return mixed The column value
	 * @since 1.0.0
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Get sortable columns
	 *
	 * Define which columns can be sorted
	 *
	 * @return array The sortable columns
	 * @since 1.0.0
	 */
	public function get_sortable_columns() {
		return array(
			'col_status'       => array( 'status', false ),
			'col_package_type' => array( 'package_type', false ),
			'col_package_slug' => array( 'package_slug', false ),
			'col_email'        => array( 'email', false ),
			'col_date_created' => array( 'date_created', false ),
			'col_date_expiry'  => array( 'date_expiry', false ),
			'col_id'           => array( 'id', false ),
		);
	}

	/**
	 * Prepare table items
	 *
	 * Query the database and set up the items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		$search     = ! empty( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where      = false;
		$where_args = false;

		if ( $search ) {

			if ( is_numeric( $search ) ) {
				$where      = ' WHERE id = %d';
				$where_args = array( abs( intval( $search ) ) );
			} else {
				$where      = " WHERE
					license_key = %s OR
					allowed_domains LIKE '%%%s%%' OR
					status = %s OR
					owner_name LIKE '%%%s%%' OR
					email LIKE '%%%s%%' OR
					company_name LIKE '%%%s%%' OR
					txn_id = %s OR
					package_slug = %s OR
					package_type = %s";
				$where_args = array(
					$search,
					$search,
					strtolower( $search ),
					$search,
					$search,
					$search,
					$search,
					str_replace( '_', '-', sanitize_title_with_dashes( $search ) ),
					strtolower( $search ),
				);
			}
		}

		$sql = "SELECT COUNT(id) FROM {$wpdb->prefix}upserv_licenses";

		if ( $search ) {
			$sql        .= $where;
			$total_items = $wpdb->get_var( $wpdb->prepare( $sql, $where_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total_items = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$offset   = 0;
		$per_page = $this->get_items_per_page( 'licenses_per_page', 10 );
		$paged    = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );
		$order_by = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'date_created'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( str_replace( 'col_', '', $order_by ), array_keys( $this->get_sortable_columns() ), true ) ) {
			$order_by = 'date_created';
		}

		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

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
		$args                  = array(
			$per_page,
			$offset,
		);
		$sql                   = "SELECT * FROM {$wpdb->prefix}upserv_licenses";

		if ( $search ) {
			$sql .= $where;

			array_push( $where_args, $per_page, $offset );

			$args = $where_args;
		}

		$sql  .= " ORDER BY $order_by $order LIMIT %d OFFSET %d";
		$query = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $items as $index => $item ) {
			$items[ $index ]['allowed_domains'] = maybe_unserialize( $item['allowed_domains'] );
		}

		$this->items = $items;
	}

	/**
	 * Display table rows
	 *
	 * Output the HTML for each row in the table
	 *
	 * @since 1.0.0
	 */
	public function display_rows() {
		$records = $this->items;
		$table   = $this;

		list( $columns, $hidden ) = $this->get_column_info();

		if ( ! empty( $records ) ) {
			$date_format = 'Y-m-d';
			$primary     = $this->get_primary_column_name();
			$page        = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			foreach ( $records as $record_key => $record ) {
				$bulk_value             = wp_json_encode(
					$record,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
						JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
				);
				$record['status_label'] = Utils::get_status_string( $record['status'] );

				upserv_get_admin_template(
					'licenses-table-row.php',
					array(
						'bulk_value'  => $bulk_value,
						'table'       => $table,
						'columns'     => $columns,
						'hidden'      => $hidden,
						'records'     => $records,
						'record_key'  => $record_key,
						'record'      => $record,
						'date_format' => $date_format,
						'primary'     => $primary,
						'page'        => $page,
					)
				);
			}
		}
	}

	// Misc. -------------------------------------------------------

	/**
	 * Set table rows
	 *
	 * Set the row data for the table
	 *
	 * @param array $rows The rows data
	 * @since 1.0.0
	 */
	public function set_rows( $rows ) {
		$this->rows = $rows;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	/**
	 * Generate row actions
	 *
	 * Create action links for each row
	 *
	 * @param array $actions The actions array
	 * @param bool $always_visible Whether actions should be always visible
	 * @return string HTML for the row actions
	 * @since 1.0.0
	 */
	protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );
		$i            = 0;

		if ( ! $action_count ) {
			return '';
		}

		$out = '<div class="' . ( $always_visible ? 'row-actions visible open-panel' : 'row-actions open-panel' ) . '">';

		foreach ( $actions as $action => $link ) {
			++$i;

			$sep  = ( $i === $action_count ) ? '' : ' | ';
			$out .= "<span class='$action'>$link$sep</span>";
		}

		$out .= '</div>';
		$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">'
			. __( 'Show more details' ) . '</span></button>';

		return $out;
	}

	/**
	 * Display extra tablenav
	 *
	 * Add additional controls above or below the table
	 *
	 * @param string $which The location ('top' or 'bottom')
	 * @since 1.0.0
	 */
	protected function extra_tablenav( $which ) {

		if ( 'bottom' === $which ) {
			print '<div class="alignleft actions bulkactions"><input id="post-query-submit" type="button" name="upserv_delete_all_licenses" value="' . esc_html( __( 'Delete All Licenses', 'updatepulse-server' ) ) . '" class="button upserv-delete-all-licenses"><input id="add_license_trigger" type="button" value="' . esc_html( __( 'Add License', 'updatepulse-server' ) ) . '" class="button button-primary open-panel"></div>';
		}
	}

	/**
	 * No items message
	 *
	 * Display a message and a button when no items are found
	 *
	 * @since 1.0.11
	 */
	public function no_items() {
		print '<div class="no-items"><p>' . esc_html( __( 'No items found.', 'updatepulse-server' ) ) . '</p><p><input id="add_license_trigger" type="button" value="' . esc_html( __( 'Add License', 'updatepulse-server' ) ) . '" class="button button-primary open-panel"></p></div>';
	}

	/**
	 * Get bulk actions
	 *
	 * Define available bulk actions for the table
	 *
	 * @return array The available bulk actions
	 * @since 1.0.0
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'pending'     => __( 'Set to Pending', 'updatepulse-server' ),
			'activated'   => __( 'Activate', 'updatepulse-server' ),
			'deactivated' => __( 'Deactivate', 'updatepulse-server' ),
			'blocked'     => __( 'Block', 'updatepulse-server' ),
			'expired'     => __( 'Expire', 'updatepulse-server' ),
			'delete'      => __( 'Delete', 'updatepulse-server' ),
		);

		return $actions;
	}

	/**
	 * Get table classes
	 *
	 * Define CSS classes for the table
	 *
	 * @return array The table CSS classes
	 * @since 1.0.0
	 */
	protected function get_table_classes() {
		$mode = get_user_setting( 'posts_list_mode', 'list' );

		$mode_class = esc_attr( 'table-view-' . $mode );

		return array( 'widefat', 'striped', $mode_class, $this->_args['plural'] );
	}
}
