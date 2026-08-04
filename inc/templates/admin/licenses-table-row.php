<?php
/**
 * Licenses table row template.
 *
 * @package UpdatePulse_Server
 *
 * @var string                                          $bulk_value  JSON-encoded license data for bulk actions.
 * @var \Anyape\UpdatePulse\Server\Table\Licenses_Table $table       Licenses list table.
 * @var array                                           $columns     Visible table columns.
 * @var array                                           $hidden      Hidden table column names.
 * @var int|string                                      $record_key  Record key.
 * @var array                                           $record      Prepared license record.
 * @var array                                           $records     All prepared license records.
 * @var string                                          $date_format Date display format.
 * @var string                                          $primary     Primary column name.
 * @var string|false                                    $page        Current admin page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<tr>
	<?php foreach ( $columns as $column_name => $column_display_name ) : ?>
		<?php
		$key     = str_replace( 'col_', '', $column_name );
		$_class  = $column_name
			. ' column-'
			. $column_name
			. ( $primary === $column_name ? ' has-row-actions column-primary' : '' );
		$style   = '';
		$actions = '';

		if ( 'col_status' === $column_name ) {
			$_class .= ' ' . $record[ $key ];
		}

		if ( in_array( $column_name, $hidden, true ) ) {
			$style = 'display:none;';
		}

		if ( 'license_key' === $key ) {
			$actions = array(
				'details' => '<a href="#" class="upserv-modal-open-handle" '
					. 'data-modal_id="upserv_modal_license_details">'
					. __( 'Details', 'updatepulse-server' )
					. '</a>',
				'edit'    => '<a href="#">' . __( 'Edit' ) . '</a>',
				'delete'  => sprintf(
					'<a href="#" data-href="?page=%s&action=%s&license_data=%s&linknonce=%s">%s</a>',
					$page,
					'delete',
					$record['id'],
					wp_create_nonce( 'linknonce' ),
					__( 'Delete' )
				),
			);
			$actions = $table->row_actions( $actions );
		}
		?>
		<?php if ( 'cb' === $column_name ) : ?>
			<th scope="row" class="check-column">
				<input type="checkbox" name="license_data[]" id="cb-select-<?php echo esc_attr( $record_key ); ?>" value="<?php echo esc_attr( $bulk_value ); ?>" />
			</th>
		<?php else : ?>
			<td class="<?php echo esc_attr( $_class ); ?>" style="<?php echo esc_attr( $style ); ?>" data-colname="<?php echo esc_attr( $column_display_name ); ?>">
				<?php if ( 'col_id' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_license_key' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
					<?php echo wp_kses_post( $actions ); ?>
				<?php elseif ( 'col_status' === $column_name ) : ?>
					<mark><span><?php echo esc_html( ucfirst( $record['status_label'] ) ); ?></span></mark>
				<?php elseif ( 'col_package_type' === $column_name ) : ?>
					<?php echo esc_html( ucfirst( $record[ $key ] ) ); ?>
				<?php elseif ( 'col_package_slug' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_email' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_date_created' === $column_name ) : ?>
					<?php
					$timezone = new DateTimeZone( wp_timezone_string() );
					$date     = new DateTime( $record[ $key ], $timezone );

					echo esc_html( $date->format( $date_format ) );
					?>
				<?php elseif ( 'col_date_expiry' === $column_name ) : ?>
					<?php if ( '0000-00-00' === $record[ $key ] ) : ?>
						<?php esc_html_e( 'N/A', 'updatepulse-server' ); ?>
					<?php else : ?>
						<?php
						$timezone = new DateTimeZone( wp_timezone_string() );
						$date     = new DateTime( $record[ $key ], $timezone );

						echo esc_html( $date->format( $date_format ) );
						?>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		<?php endif; ?>
	<?php endforeach; ?>
</tr>
