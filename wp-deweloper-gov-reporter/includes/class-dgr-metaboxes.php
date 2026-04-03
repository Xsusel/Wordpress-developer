<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			<label for="dgr_investment_address"><?php esc_html_e( 'Adres', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_address" name="dgr_investment_address" value="<?php echo esc_attr( $address ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_investment_id"><?php esc_html_e( 'ID Inwestycji (gov)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_id" name="dgr_investment_id" value="<?php echo esc_attr( $gov_id ); ?>" class="widefat">
			<small><?php esc_html_e( 'Identyfikator nadany przez urząd.', 'wp-deweloper-gov-reporter' ); ?></small>
		</p>
		<p>
			<label for="dgr_investment_nip"><?php esc_html_e( 'NIP Dewelopera', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_nip" name="dgr_investment_nip" value="<?php echo esc_attr( $nip ); ?>" class="widefat" pattern="[0-9]{10}" title="<?php esc_attr_e( 'NIP: 10 cyfr', 'wp-deweloper-gov-reporter' ); ?>">
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
		$dependencies      = get_post_meta( $post->ID, '_dgr_unit_dependencies', true );

		// Pricing fields
		$location_label          = get_post_meta( $post->ID, '_dgr_unit_location_label', true );
		$price_shell_netto       = get_post_meta( $post->ID, '_dgr_unit_price_shell_netto', true );
		$price_shell_brutto      = get_post_meta( $post->ID, '_dgr_unit_price_shell_brutto', true );
		$price_developer_netto   = get_post_meta( $post->ID, '_dgr_unit_price_developer_netto', true );
		$price_developer_brutto  = get_post_meta( $post->ID, '_dgr_unit_price_developer_brutto', true );

		$investments = get_posts( array(
			'post_type'      => 'dgr_investment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		?>
		<p>
			<label for="dgr_unit_parent_investment"><?php esc_html_e( 'Inwestycja', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<select id="dgr_unit_parent_investment" name="dgr_unit_parent_investment" class="widefat" required>
				<option value=""><?php esc_html_e( 'Wybierz Inwestycję', 'wp-deweloper-gov-reporter' ); ?></option>
				<?php foreach ( $investments as $investment ) : ?>
					<option value="<?php echo esc_attr( $investment->ID ); ?>" <?php selected( $parent_investment, $investment->ID ); ?>><?php echo esc_html( $investment->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="dgr_unit_id"><?php esc_html_e( 'Numer Lokalu / ID', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<input type="text" id="dgr_unit_id" name="dgr_unit_id" value="<?php echo esc_attr( $unit_id ); ?>" class="widefat" required>
		</p>
		<p>
			<label for="dgr_unit_location_label"><?php esc_html_e( 'Lokalizacja (etykieta)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_unit_location_label" name="dgr_unit_location_label" value="<?php echo esc_attr( $location_label ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'np. BOLMIN (25 KM OD KIELC)', 'wp-deweloper-gov-reporter' ); ?>">
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Cena Całkowita (raport gov)', 'wp-deweloper-gov-reporter' ); ?></h4>
		<p>
			<label for="dgr_unit_price_total"><?php esc_html_e( 'Cena Całkowita Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<input type="number" step="0.01" min="0.01" id="dgr_unit_price_total" name="dgr_unit_price_total" value="<?php echo esc_attr( $price_total ); ?>" class="widefat" required>
		</p>
		<p>
			<label for="dgr_unit_price_m2"><?php esc_html_e( 'Cena za m² Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<input type="number" step="0.01" min="0.01" id="dgr_unit_price_m2" name="dgr_unit_price_m2" value="<?php echo esc_attr( $price_m2 ); ?>" class="widefat" required>
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Cennik - Stan Surowy Zamknięty', 'wp-deweloper-gov-reporter' ); ?></h4>
		<p>
			<label for="dgr_unit_price_shell_netto"><?php esc_html_e( 'Cena Netto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" min="0" id="dgr_unit_price_shell_netto" name="dgr_unit_price_shell_netto" value="<?php echo esc_attr( $price_shell_netto ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_price_shell_brutto"><?php esc_html_e( 'Cena Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" min="0" id="dgr_unit_price_shell_brutto" name="dgr_unit_price_shell_brutto" value="<?php echo esc_attr( $price_shell_brutto ); ?>" class="widefat">
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Cennik - Stan Deweloperski', 'wp-deweloper-gov-reporter' ); ?></h4>
		<p>
			<label for="dgr_unit_price_developer_netto"><?php esc_html_e( 'Cena Netto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" min="0" id="dgr_unit_price_developer_netto" name="dgr_unit_price_developer_netto" value="<?php echo esc_attr( $price_developer_netto ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_price_developer_brutto"><?php esc_html_e( 'Cena Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" step="0.01" min="0" id="dgr_unit_price_developer_brutto" name="dgr_unit_price_developer_brutto" value="<?php echo esc_attr( $price_developer_brutto ); ?>" class="widefat">
		</p>

		<hr>
		<p>
			<label for="dgr_unit_area"><?php esc_html_e( 'Powierzchnia (m²)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<input type="number" step="0.01" min="0.01" id="dgr_unit_area" name="dgr_unit_area" value="<?php echo esc_attr( $area ); ?>" class="widefat" required>
		</p>
		<p>
			<label for="dgr_unit_rooms"><?php esc_html_e( 'Liczba Pokoi', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
			<input type="number" min="1" id="dgr_unit_rooms" name="dgr_unit_rooms" value="<?php echo esc_attr( $rooms ); ?>" class="widefat" required>
		</p>
		<p>
			<label for="dgr_unit_floor"><?php esc_html_e( 'Piętro', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="number" min="0" id="dgr_unit_floor" name="dgr_unit_floor" value="<?php echo esc_attr( $floor ); ?>" class="widefat">
		</p>
		<p>
			<label for="dgr_unit_status"><?php esc_html_e( 'Status', 'wp-deweloper-gov-reporter' ); ?></label>
			<select id="dgr_unit_status" name="dgr_unit_status" class="widefat">
				<option value="available" <?php selected( $status, 'available' ); ?>><?php esc_html_e( 'Dostępny', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="offer" <?php selected( $status, 'offer' ); ?>><?php esc_html_e( 'Oferta specjalna', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="reserved" <?php selected( $status, 'reserved' ); ?>><?php esc_html_e( 'Zarezerwowany', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="reservation_agreement" <?php selected( $status, 'reservation_agreement' ); ?>><?php esc_html_e( 'Umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="developer_agreement" <?php selected( $status, 'developer_agreement' ); ?>><?php esc_html_e( 'Umowa deweloperska', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="sold" <?php selected( $status, 'sold' ); ?>><?php esc_html_e( 'Sprzedany', 'wp-deweloper-gov-reporter' ); ?></option>
				<option value="transferred" <?php selected( $status, 'transferred' ); ?>><?php esc_html_e( 'Przekazany', 'wp-deweloper-gov-reporter' ); ?></option>
			</select>
		</p>
		<p>
			<label for="dgr_unit_dependencies"><?php esc_html_e( 'Przynależności (JSON)', 'wp-deweloper-gov-reporter' ); ?></label>
			<textarea id="dgr_unit_dependencies" name="dgr_unit_dependencies" class="widefat" rows="3"><?php echo esc_textarea( $dependencies ); ?></textarea>
			<small><?php esc_html_e( 'Format: [{"typ": "miejsce_postojowe", "cena": 45000}, {"typ": "komórka_lokatorska", "cena": 18000}]', 'wp-deweloper-gov-reporter' ); ?></small>
		</p>

		<hr>
		<h4><?php esc_html_e( 'Shortcodes dla tego lokalu', 'wp-deweloper-gov-reporter' ); ?></h4>
		<p><small><?php esc_html_e( 'Skopiuj i wklej na stronę (Elementor lub edytor):', 'wp-deweloper-gov-reporter' ); ?></small></p>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_karta id="<?php echo esc_attr( $post->ID ); ?>"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_cena id="<?php echo esc_attr( $post->ID ); ?>" typ="deweloperski" vat="brutto"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_cena id="<?php echo esc_attr( $post->ID ); ?>" typ="surowy" vat="netto"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_metraz id="<?php echo esc_attr( $post->ID ); ?>"]</code>
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

			// Text fields
			$text_fields = array( 'dgr_unit_parent_investment', 'dgr_unit_id', 'dgr_unit_status', 'dgr_unit_location_label' );
			foreach ( $text_fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}

			// Numeric fields - validate as positive numbers
			$numeric_fields = array(
				'dgr_unit_price_total'           => 0.01,
				'dgr_unit_price_m2'              => 0.01,
				'dgr_unit_area'                  => 0.01,
				'dgr_unit_rooms'                 => 1,
				'dgr_unit_floor'                 => 0,
				'dgr_unit_price_shell_netto'     => 0,
				'dgr_unit_price_shell_brutto'    => 0,
				'dgr_unit_price_developer_netto' => 0,
				'dgr_unit_price_developer_brutto'=> 0,
			);

			foreach ( $numeric_fields as $field => $min ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = floatval( $_POST[ $field ] );
					if ( $value >= $min ) {
						update_post_meta( $post_id, '_' . $field, $value );
					}
				}
			}

			// Dependencies - validate as JSON
			if ( isset( $_POST['dgr_unit_dependencies'] ) ) {
				$raw = sanitize_textarea_field( $_POST['dgr_unit_dependencies'] );
				if ( empty( trim( $raw ) ) ) {
					update_post_meta( $post_id, '_dgr_unit_dependencies', '' );
				} else {
					$decoded = json_decode( $raw, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
						update_post_meta( $post_id, '_dgr_unit_dependencies', $raw );
					}
					// If invalid JSON, don't update - keep old value
				}
			}
		}
	}
}
