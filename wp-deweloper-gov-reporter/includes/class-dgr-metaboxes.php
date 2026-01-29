<?php

class DGR_Metaboxes {

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'dgr_investment_details',
			__( 'Szczegóły Inwestycji', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_investment_metabox' ),
			'dgr_investment',
			'normal',
			'high'
		);

		add_meta_box(
			'dgr_unit_details',
			__( 'Szczegóły Lokalu', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_unit_metabox' ),
			'dgr_unit',
			'normal',
			'high'
		);
	}

	public function render_investment_metabox( $post ) {
		wp_nonce_field( 'dgr_save_investment_data', 'dgr_investment_nonce' );

		$address = get_post_meta( $post->ID, '_dgr_investment_address', true );
		$gov_id  = get_post_meta( $post->ID, '_dgr_investment_id', true );
		$nip     = get_post_meta( $post->ID, '_dgr_investment_nip', true );
		?>
		<p>
			<label for="dgr_investment_address"><?php _e( 'Adres', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_address" name="dgr_investment_address" value="<?php echo esc_attr( $address ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_investment_id"><?php _e( 'ID Inwestycji (gov)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_id" name="dgr_investment_id" value="<?php echo esc_attr( $gov_id ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_investment_nip"><?php _e( 'NIP Dewelopera', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_nip" name="dgr_investment_nip" value="<?php echo esc_attr( $nip ); ?>" class="widefat">
		</p>
		<?php
	}

	public function render_unit_metabox( $post ) {
		wp_nonce_field( 'dgr_save_unit_data', 'dgr_unit_nonce' );

		$parent_investment = get_post_meta( $post->ID, '_dgr_unit_parent_investment', true );
		$unit_id           = get_post_meta( $post->ID, '_dgr_unit_id', true );
		$price_total       = get_post_meta( $post->ID, '_dgr_unit_price_total', true );
		$price_m2          = get_post_meta( $post->ID, '_dgr_unit_price_m2', true );
		$area              = get_post_meta( $post->ID, '_dgr_unit_area', true );
		$rooms             = get_post_meta( $post->ID, '_dgr_unit_rooms', true );
		$floor             = get_post_meta( $post->ID, '_dgr_unit_floor', true );
		$status            = get_post_meta( $post->ID, '_dgr_unit_status', true );
		$dependencies      = get_post_meta( $post->ID, '_dgr_unit_dependencies', true ); // JSON string

		// Get all investments for dropdown
		$investments = get_posts( array(
			'post_type'      => 'dgr_investment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		?>
		<p>
			<label for="dgr_unit_parent_investment"><?php _e( 'Inwestycja', 'wp-deweloper-gov-reporter' ); ?></label>
			<select id="dgr_unit_parent_investment" name="dgr_unit_parent_investment" class="widefat">
				<option value=""><?php _e( 'Wybierz Inwestycję', 'wp-deweloper-gov-reporter' ); ?></option>
				<?php foreach ( $investments as $investment ) : ?>
					<option value="<?php echo esc_attr( $investment->ID ); ?>" <?php selected( $parent_investment, $investment->ID ); ?>><?php echo esc_html( $investment->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="dgr_unit_id"><?php _e( 'Numer Lokalu / ID', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_unit_id" name="dgr_unit_id" value="<?php echo esc_attr( $unit_id ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_price_total"><?php _e( 'Cena Całkowita (Brutto PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" id="dgr_unit_price_total" name="dgr_unit_price_total" value="<?php echo esc_attr( $price_total ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_price_m2"><?php _e( 'Cena za m² (Brutto PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" id="dgr_unit_price_m2" name="dgr_unit_price_m2" value="<?php echo esc_attr( $price_m2 ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_area"><?php _e( 'Powierzchnia (m²)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" id="dgr_unit_area" name="dgr_unit_area" value="<?php echo esc_attr( $area ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_rooms"><?php _e( 'Liczba Pokoi', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" id="dgr_unit_rooms" name="dgr_unit_rooms" value="<?php echo esc_attr( $rooms ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_floor"><?php _e( 'Piętro', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" id="dgr_unit_floor" name="dgr_unit_floor" value="<?php echo esc_attr( $floor ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_status"><?php _e( 'Status', 'wp-deweloper-gov-reporter' ); ?></label>
			<select id="dgr_unit_status" name="dgr_unit_status" class="widefat">
				<option value="available" <?php selected( $status, 'available' ); ?>><?php _e( 'Dostępny', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="reserved" <?php selected( $status, 'reserved' ); ?>><?php _e( 'Zarezerwowany', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="sold" <?php selected( $status, 'sold' ); ?>><?php _e( 'Sprzedany', 'wp-deweloper-gov-reporter' ); ?></option>
			</select>
		</p>
		<p>
			<label for="dgr_unit_dependencies"><?php _e( 'Przynależności (JSON)', 'wp-deweloper-gov-reporter' ); ?></label>
			<textarea id="dgr_unit_dependencies" name="dgr_unit_dependencies" class="widefat" rows="3"><?php echo esc_textarea( $dependencies ); ?></textarea>
			<small><?php _e( 'Format: [{"typ": "miejsce_postojowe", "cena": 45000}, ...]', 'wp-deweloper-gov-reporter' ); ?></small>
		</p>
		<?php
	}

	public function save_meta_boxes( $post_id ) {
		// Save Investment
		if ( isset( $_POST['dgr_investment_nonce'] ) && wp_verify_nonce( $_POST['dgr_investment_nonce'], 'dgr_save_investment_data' ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$fields = array( 'dgr_investment_address', 'dgr_investment_id', 'dgr_investment_nip' );
			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}
		}

		// Save Unit
		if ( isset( $_POST['dgr_unit_nonce'] ) && wp_verify_nonce( $_POST['dgr_unit_nonce'], 'dgr_save_unit_data' ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$fields = array(
				'dgr_unit_parent_investment',
				'dgr_unit_id',
				'dgr_unit_price_total',
				'dgr_unit_price_m2',
				'dgr_unit_area',
				'dgr_unit_rooms',
				'dgr_unit_floor',
				'dgr_unit_status',
				'dgr_unit_dependencies'
			);
			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					if ( $field === 'dgr_unit_dependencies' ) {
						// Allow raw text for JSON, but strip tags to be safe. sanitize_textarea_field removes newlines which might break readable JSON?
						// Actually sanitize_textarea_field preserves newlines but removes HTML.
						$value = sanitize_textarea_field( $_POST[ $field ] );
					} else {
						$value = sanitize_text_field( $_POST[ $field ] );
					}
					update_post_meta( $post_id, '_' . $field, $value );
				}
			}
		}
	}
}
