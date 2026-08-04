<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<tr id="<?php echo esc_attr( $record_key ); ?>">
	<?php
	$actions      = array();
	$query_string = '?page=%s&action=%s&packages=%s&linknonce=%s';
	$args         = array( $page, 'download', $record_key, wp_create_nonce( 'linknonce' ) );

	if ( ! empty( $search ) ) {
		$args[]        = $search;
		$query_string .= '&s=%s';
	}

	$actions['details']         = '<a href="#" class="upserv-modal-open-handle" '
		. 'data-modal_id="upserv_modal_package_details" '
		. 'data-info="' . esc_attr( $record['info'] ) . '">'
		. __( 'Details', 'updatepulse-server' )
		. '</a>';
	$args[]                     = __( 'Download', 'updatepulse-server' );
	$actions['download']        = vsprintf( '<a href="' . $query_string . '">%s</a>', $args );
	$args[1]                    = 'delete';
	$args[ count( $args ) - 1 ] = __( 'Delete' );
	$actions['delete']          = vsprintf( '<a href="#" data-href="' . $query_string . '">%s</a>', $args );
	$actions                    = $table->row_actions(
		apply_filters( 'upserv_packages_table_row_actions', $actions, $args, $query_string, $record_key )
	);
	?>
	<?php foreach ( $columns as $column_name => $column_display_name ) : ?>
		<?php
		$key   = str_replace( 'col_', '', $column_name );
		$class = $column_name
			. ' column-'
			. $column_name
			. ( $primary === $column_name ? ' has-row-actions column-primary' : '' );
		$style = '';

		if ( in_array( $column_name, $hidden, true ) ) {
			$style = 'display:none;';
		}
		?>
		<?php if ( 'cb' === $column_name ) : ?>
			<th scope="row" class="check-column">
				<input type="checkbox" name="packages[]" id="cb-select-<?php echo esc_attr( $record_key ); ?>" value="<?php echo esc_attr( $record_key ); ?>" />
			</th>
		<?php else : ?>
			<td class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $style ); ?>" data-colname="<?php echo esc_attr( $column_display_name ); ?>">
				<?php if ( 'col_name' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
					<?php echo wp_kses_post( $actions ); ?>
				<?php elseif ( 'col_version' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_type' === $column_name ) : ?>
					<?php if ( 'theme' === $record[ $key ] ) : ?>
						<?php esc_html_e( 'Theme', 'updatepulse-server' ); ?>
					<?php elseif ( 'plugin' === $record[ $key ] ) : ?>
						<?php esc_html_e( 'Plugin', 'updatepulse-server' ); ?>
					<?php elseif ( 'generic' === $record[ $key ] ) : ?>
						<?php esc_html_e( 'Generic', 'updatepulse-server' ); ?>
					<?php endif; ?>
				<?php elseif ( 'col_file_name' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_file_size' === $column_name ) : ?>
					<?php echo esc_html( size_format( $record[ $key ] ) ); ?>
				<?php elseif ( 'col_file_last_modified' === $column_name ) : ?>
					<?php echo esc_html( wp_date( $date_format, $record[ $key ], $time_zone ) ); ?><br />
					<?php echo esc_html( wp_date( $time_format, $record[ $key ], $time_zone ) ); ?>
				<?php elseif ( 'col_origin' === $column_name ) : ?>
				<div class="item">
					<?php if ( 'manual' === $record['metadata'][ $key ] ) : ?>
						<code>
							<i class="fa-solid fa-hand"></i><span class="manual">
								<?php esc_html_e( 'Manual', 'updatepulse-server' ); ?>
							</span>
						</code>
					<?php elseif ( 'vcs' === $record['metadata'][ $key ] ) : ?>
						<code>
							<?php echo upserv_get_brand_icon( $record['vcs']['brand'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from an allowlisted registry. ?><span class="identifier">
								<?php echo esc_html( $record['vcs']['identifier'] ); ?>
							</span>
						</code>
						<code>
							<i class="fa-solid fa-code-branch"></i> <span class="branch">
								<?php echo esc_html( $record['vcs']['branch'] ); ?>
							</span>
						</code>
					<?php else : ?>
						<code>
							<i class="fa-solid fa-question"></i><span class="manual">
								<?php esc_html_e( 'Unknown', 'updatepulse-server' ); ?>
							</span>
						</code>
					<?php endif; ?>
				</div>
				<?php else : ?>
					<?php do_action( 'upserv_packages_table_cell', $column_name, $record, $record_key ); ?>
				<?php endif; ?>
			</td>
		<?php endif; ?>
	<?php endforeach; ?>
</tr>
