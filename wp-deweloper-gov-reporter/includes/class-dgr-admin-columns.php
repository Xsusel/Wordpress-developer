<?php

class DGR_Admin_Columns {

	public function init() {
		// Columns for Units
		add_filter( 'manage_dgr_unit_posts_columns', array( $this, 'add_unit_columns' ) );
		add_action( 'manage_dgr_unit_posts_custom_column', array( $this, 'render_unit_columns' ), 10, 2 );
		add_filter( 'manage_edit-dgr_unit_sortable_columns', array( $this, 'sortable_unit_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_unit_columns' ) );

		// Filter by Investment
		add_action( 'restrict_manage_posts', array( $this, 'filter_by_investment' ) );
		add_action( 'parse_query', array( $this, 'handle_investment_filter' ) );

		// Quick Edit
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit' ), 10, 2 );
		add_action( 'admin_footer', array( $this, 'quick_edit_javascript' ) );

		// Bulk Actions
		add_filter( 'bulk_actions-edit-dgr_unit', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-dgr_unit', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_admin_notice' ) );
	}

	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['dgr_mark_sold'] = __( 'Zmień status na Sprzedane', 'wp-deweloper-gov-reporter' );
		$bulk_actions['dgr_mark_reserved'] = __( 'Zmień status na Zarezerwowane', 'wp-deweloper-gov-reporter' );
		$bulk_actions['dgr_mark_available'] = __( 'Zmień status na Dostępne', 'wp-deweloper-gov-reporter' );
		return $bulk_actions;
	}

	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( ! in_array( $doaction, array( 'dgr_mark_sold', 'dgr_mark_reserved', 'dgr_mark_available' ) ) ) {
			return $redirect_to;
		}

		$status_map = array(
			'dgr_mark_sold'      => 'sold',
			'dgr_mark_reserved'  => 'reserved',
			'dgr_mark_available' => 'available',
		);

		$new_status = $status_map[ $doaction ];
		$changed = 0;

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_dgr_unit_status', $new_status );
			$changed++;
		}

		$redirect_to = add_query_arg( 'dgr_bulk_action_done', $changed, $redirect_to );
		$redirect_to = add_query_arg( 'dgr_action_status', $new_status, $redirect_to );
		return $redirect_to;
	}

	public function bulk_action_admin_notice() {
		if ( ! empty( $_REQUEST['dgr_bulk_action_done'] ) ) {
			$count = intval( $_REQUEST['dgr_bulk_action_done'] );
			$status = sanitize_text_field( $_REQUEST['dgr_action_status'] );
			printf( '<div id="message" class="updated notice is-dismissible"><p>' .
				_n( '%s lokal zaktualizowany na status: %s.', '%s lokali zaktualizowanych na status: %s.', $count, 'wp-deweloper-gov-reporter' ) .
				'</p></div>', $count, $status );
		}
	}

	public function add_unit_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['dgr_investment'] = __( 'Inwestycja', 'wp-deweloper-gov-reporter' );
		$new_columns['dgr_unit_id'] = __( 'Nr Lokalu', 'wp-deweloper-gov-reporter' );
		$new_columns['dgr_status'] = __( 'Status', 'wp-deweloper-gov-reporter' );
		$new_columns['dgr_area'] = __( 'Pow. (m²)', 'wp-deweloper-gov-reporter' );
		$new_columns['dgr_price_total'] = __( 'Cena (Brutto)', 'wp-deweloper-gov-reporter' );
		$new_columns['dgr_price_m2'] = __( 'Cena/m²', 'wp-deweloper-gov-reporter' );
		$new_columns['date'] = $columns['date'];
		return $new_columns;
	}

	public function render_unit_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'dgr_investment':
				$parent_id = get_post_meta( $post_id, '_dgr_unit_parent_investment', true );
				echo $parent_id ? get_the_title( $parent_id ) : '-';
				break;
			case 'dgr_unit_id':
				echo esc_html( get_post_meta( $post_id, '_dgr_unit_id', true ) );
				break;
			case 'dgr_status':
				$status = get_post_meta( $post_id, '_dgr_unit_status', true );
				echo esc_html( ucfirst( $status ) );
				echo '<input type="hidden" class="dgr_status_hidden_' . esc_attr( $post_id ) . '" value="' . esc_attr( $status ) . '">';
				break;
			case 'dgr_area':
				echo esc_html( get_post_meta( $post_id, '_dgr_unit_area', true ) );
				break;
			case 'dgr_price_total':
				$price = get_post_meta( $post_id, '_dgr_unit_price_total', true );
				echo $price ? number_format( (float)$price, 2, ',', ' ' ) . ' zł' : '-';
				echo '<input type="hidden" class="dgr_price_total_hidden_' . esc_attr( $post_id ) . '" value="' . esc_attr( $price ) . '">';
				break;
			case 'dgr_price_m2':
				$price = get_post_meta( $post_id, '_dgr_unit_price_m2', true );
				echo $price ? number_format( (float)$price, 2, ',', ' ' ) . ' zł' : '-';
				break;
		}
	}

	public function sortable_unit_columns( $columns ) {
		$columns['dgr_price_total'] = 'dgr_price_total';
		$columns['dgr_area'] = 'dgr_area';
		$columns['dgr_status'] = 'dgr_status';
		return $columns;
	}

	public function sort_unit_columns( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'dgr_price_total' === $orderby ) {
			$query->set( 'meta_key', '_dgr_unit_price_total' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'dgr_area' === $orderby ) {
			$query->set( 'meta_key', '_dgr_unit_area' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'dgr_status' === $orderby ) {
			$query->set( 'meta_key', '_dgr_unit_status' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	public function filter_by_investment( $post_type ) {
		if ( 'dgr_unit' !== $post_type ) {
			return;
		}

		$investments = get_posts( array(
			'post_type'      => 'dgr_investment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		$selected = isset( $_GET['dgr_investment_filter'] ) ? $_GET['dgr_investment_filter'] : '';

		echo '<select name="dgr_investment_filter">';
		echo '<option value="">' . __( 'Wszystkie Inwestycje', 'wp-deweloper-gov-reporter' ) . '</option>';
		foreach ( $investments as $inv ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $inv->ID ), selected( $selected, $inv->ID, false ), esc_html( $inv->post_title ) );
		}
		echo '</select>';
	}

	public function handle_investment_filter( $query ) {
		global $pagenow;
		if ( is_admin() && 'edit.php' === $pagenow && isset( $_GET['dgr_investment_filter'] ) && ! empty( $_GET['dgr_investment_filter'] ) ) {
			$query->set( 'meta_key', '_dgr_unit_parent_investment' );
			$query->set( 'meta_value', $_GET['dgr_investment_filter'] );
		}
	}

	public function render_quick_edit( $column_name, $post_type ) {
		if ( 'dgr_unit' !== $post_type || 'dgr_price_total' !== $column_name ) {
			return;
		}
		// We use dgr_price_total column to inject our fields via inline-edit JS logic usually,
		// but since WP Quick Edit is tricky, we'll just add simple fields and rely on save_post hook.
		// Note: Proper Quick Edit requires JS to populate fields from existing values.
		// For MVP, we'll skip JS population (users must re-enter or it will be empty) or use a hidden span trick if time permits.
		// Let's add the HTML structure first.
		?>
		<fieldset class="inline-edit-col-right inline-edit-dgr-unit">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php _e( 'Cena Całkowita', 'wp-deweloper-gov-reporter' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="dgr_unit_price_total" class="dgr_unit_price_total" value="">
					</span>
				</label>
				<label>
					<span class="title"><?php _e( 'Status', 'wp-deweloper-gov-reporter' ); ?></span>
					<span class="input-text-wrap">
						<select name="dgr_unit_status" class="dgr_unit_status">
							<option value="available"><?php _e( 'Dostępny', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="offer"><?php _e( 'Oferta specjalna', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="reserved"><?php _e( 'Zarezerwowany', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="reservation_agreement"><?php _e( 'Umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="developer_agreement"><?php _e( 'Umowa deweloperska', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="sold"><?php _e( 'Sprzedany', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="transferred"><?php _e( 'Przekazany', 'wp-deweloper-gov-reporter' ); ?></option>
						</select>
					</span>
				</label>
				<!-- Nonce for Quick Edit saves is tricky, usually relies on admin-ajax but since save_post triggers on quick edit too, we need the nonce field present -->
				<?php wp_nonce_field( 'dgr_save_unit_data', 'dgr_unit_nonce' ); ?>
			</div>
		</fieldset>
		<?php
	}

	public function quick_edit_javascript() {
		global $current_screen;
		if ( 'edit-dgr_unit' !== $current_screen->id ) {
			return;
		}
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			var $ = jQuery;
			var _edit = inlineEditPost.edit;
			inlineEditPost.edit = function(id) {
				var args = [].slice.call(arguments);
				_edit.apply(this, args);

				if (typeof(id) == 'object') {
					id = this.getId(id);
				}

				if (this.type == 'dgr_unit') {
					var row = $('#inline_' + id);
					var editRow = $('#edit-' + id);

					// Get values from hidden inputs in the column (we need to add them first in render_unit_columns)
					// Alternative: Fetch raw value via AJAX if not present.
					// But wait, render_unit_columns just echoes text.
					// We need to add hidden inputs to render_unit_columns to make this work reliably.

					// For now, let's grab the text content and try to parse, OR just leave fields empty but ONLY save if they are not empty.
					// But DGR_Metaboxes::save_meta_boxes saves if isset($_POST['field']).
					// So if we leave them empty, empty string is saved.

					// FIX: We must populate the fields. Let's rely on data attributes or hidden inputs we inject now.
					var priceTotal = $('.dgr_price_total_hidden_' + id).val();
					var status = $('.dgr_status_hidden_' + id).val();

					editRow.find('input[name="dgr_unit_price_total"]').val(priceTotal);
					editRow.find('select[name="dgr_unit_status"]').val(status);
				}
			};
		});
		</script>
		<?php
	}
}
