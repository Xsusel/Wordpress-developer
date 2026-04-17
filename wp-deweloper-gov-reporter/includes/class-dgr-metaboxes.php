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

		$gov_id         = get_post_meta( $post->ID, '_dgr_investment_id', true );
		$nip            = get_post_meta( $post->ID, '_dgr_investment_nip', true );
		$voivodeship    = get_post_meta( $post->ID, '_dgr_investment_voivodeship', true );
		$county         = get_post_meta( $post->ID, '_dgr_investment_county', true );
		$commune        = get_post_meta( $post->ID, '_dgr_investment_commune', true );
		$city           = get_post_meta( $post->ID, '_dgr_investment_city', true );
		$street         = get_post_meta( $post->ID, '_dgr_investment_street', true );
		$building_no    = get_post_meta( $post->ID, '_dgr_investment_building_number', true );
		$postal_code    = get_post_meta( $post->ID, '_dgr_investment_postal_code', true );

		$voivodeships = array(
			'dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie',
			'małopolskie', 'mazowieckie', 'opolskie', 'podkarpackie', 'podlaskie',
			'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie',
			'wielkopolskie', 'zachodniopomorskie',
		);
		?>
		<p>
			<label for="dgr_investment_id"><?php esc_html_e( 'ID Inwestycji (gov)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_id" name="dgr_investment_id" value="<?php echo esc_attr( $gov_id ); ?>" class="widefat">
			<small><?php esc_html_e( 'Identyfikator nadany przez urząd.', 'wp-deweloper-gov-reporter' ); ?></small>
		</p>
		<p>
			<label for="dgr_investment_nip"><?php esc_html_e( 'NIP Dewelopera (inwestycji)', 'wp-deweloper-gov-reporter' ); ?></label>
			<input type="text" id="dgr_investment_nip" name="dgr_investment_nip" value="<?php echo esc_attr( $nip ); ?>" class="widefat" pattern="[0-9]{10}" title="<?php esc_attr_e( 'NIP: 10 cyfr', 'wp-deweloper-gov-reporter' ); ?>">
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Lokalizacja inwestycji (wymagane przez ustawę)', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_voivodeship"><?php esc_html_e( 'Województwo', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<select id="dgr_investment_voivodeship" name="dgr_investment_voivodeship" class="widefat">
					<option value=""><?php esc_html_e( '— wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
					<?php foreach ( $voivodeships as $v ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $voivodeship, $v ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_county"><?php esc_html_e( 'Powiat', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<input type="text" id="dgr_investment_county" name="dgr_investment_county" value="<?php echo esc_attr( $county ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_commune"><?php esc_html_e( 'Gmina', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<input type="text" id="dgr_investment_commune" name="dgr_investment_commune" value="<?php echo esc_attr( $commune ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_city"><?php esc_html_e( 'Miejscowość', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<input type="text" id="dgr_investment_city" name="dgr_investment_city" value="<?php echo esc_attr( $city ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_street"><?php esc_html_e( 'Ulica', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="text" id="dgr_investment_street" name="dgr_investment_street" value="<?php echo esc_attr( $street ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_building_number"><?php esc_html_e( 'Nr nieruchomości', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<input type="text" id="dgr_investment_building_number" name="dgr_investment_building_number" value="<?php echo esc_attr( $building_no ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_investment_postal_code"><?php esc_html_e( 'Kod pocztowy', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="text" id="dgr_investment_postal_code" name="dgr_investment_postal_code" value="<?php echo esc_attr( $postal_code ); ?>" class="widefat" pattern="[0-9]{2}-[0-9]{3}" placeholder="00-000">
			</p>
		</div>
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

		$balconies_count        = get_post_meta( $post->ID, '_dgr_unit_balconies_count', true );
		$balconies_area         = get_post_meta( $post->ID, '_dgr_unit_balconies_area', true );
		$garage_number          = get_post_meta( $post->ID, '_dgr_unit_garage_number', true );
		$garage_price_brutto    = get_post_meta( $post->ID, '_dgr_unit_garage_price_brutto', true );
		$storage_number         = get_post_meta( $post->ID, '_dgr_unit_storage_number', true );
		$storage_price_brutto   = get_post_meta( $post->ID, '_dgr_unit_storage_price_brutto', true );

		// Pricing fields
		$location_label          = get_post_meta( $post->ID, '_dgr_unit_location_label', true );
		$vat_rate                = get_post_meta( $post->ID, '_dgr_unit_vat_rate', true );
		if ( '' === $vat_rate ) $vat_rate = 8;
		$price_shell_netto       = get_post_meta( $post->ID, '_dgr_unit_price_shell_netto', true );
		$price_shell_brutto      = get_post_meta( $post->ID, '_dgr_unit_price_shell_brutto', true );
		$price_developer_netto   = get_post_meta( $post->ID, '_dgr_unit_price_developer_netto', true );
		$price_developer_brutto  = get_post_meta( $post->ID, '_dgr_unit_price_developer_brutto', true );

		// Ustawa deweloperska
		$unit_type           = get_post_meta( $post->ID, '_dgr_unit_type', true );
		if ( '' === $unit_type ) $unit_type = 'lokal_mieszkalny';
		$price_total_initial = get_post_meta( $post->ID, '_dgr_unit_price_total_initial', true );
		$price_m2_initial    = get_post_meta( $post->ID, '_dgr_unit_price_m2_initial', true );
		$sale_start_date     = get_post_meta( $post->ID, '_dgr_unit_sale_start_date', true );
		$sale_date           = get_post_meta( $post->ID, '_dgr_unit_sale_date', true );
		$price_sale_brutto   = get_post_meta( $post->ID, '_dgr_unit_price_sale_brutto', true );

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
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_type"><?php esc_html_e( 'Rodzaj', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<select id="dgr_unit_type" name="dgr_unit_type" class="widefat" required>
					<option value="lokal_mieszkalny" <?php selected( $unit_type, 'lokal_mieszkalny' ); ?>><?php esc_html_e( 'Lokal mieszkalny', 'wp-deweloper-gov-reporter' ); ?></option>
					<option value="dom_jednorodzinny" <?php selected( $unit_type, 'dom_jednorodzinny' ); ?>><?php esc_html_e( 'Dom jednorodzinny', 'wp-deweloper-gov-reporter' ); ?></option>
				</select>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_id"><?php esc_html_e( 'Numer Lokalu / ID', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
				<input type="text" id="dgr_unit_id" name="dgr_unit_id" value="<?php echo esc_attr( $unit_id ); ?>" class="widefat" required>
			</p>
		</div>
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
			<label><?php esc_html_e( 'Cena za m² Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?> <em style="font-weight:normal;color:#888;"><?php esc_html_e( '— obliczana automatycznie', 'wp-deweloper-gov-reporter' ); ?></em></label>
			<input type="text" id="dgr_unit_price_m2_display" value="<?php echo $price_m2 ? esc_attr( number_format( floatval( $price_m2 ), 2, ',', ' ' ) . ' zł/m²' ) : '—'; ?>" class="widefat" readonly style="background:#f9f9f9;color:#555;">
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Stawka VAT', 'wp-deweloper-gov-reporter' ); ?></h4>
		<p>
			<label for="dgr_unit_vat_rate"><?php esc_html_e( 'VAT (%)', 'wp-deweloper-gov-reporter' ); ?></label>
			<select id="dgr_unit_vat_rate" name="dgr_unit_vat_rate" style="width:120px;">
				<option value="8" <?php selected( $vat_rate, 8 ); ?>>8%</option>
				<option value="23" <?php selected( $vat_rate, 23 ); ?>>23%</option>
			</select>
			<small style="color:#888;"><?php esc_html_e( 'Wpisz netto LUB brutto — drugie pole uzupełni się samo.', 'wp-deweloper-gov-reporter' ); ?></small>
		</p>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Cennik - Stan Surowy Zamknięty', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_shell_netto"><?php esc_html_e( 'Cena Netto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_shell_netto" name="dgr_unit_price_shell_netto" value="<?php echo esc_attr( $price_shell_netto ); ?>" class="widefat dgr-vat-netto" data-pair="dgr_unit_price_shell_brutto">
				<span id="dgr_m2_shell_netto" class="dgr-m2-calc"></span>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_shell_brutto"><?php esc_html_e( 'Cena Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_shell_brutto" name="dgr_unit_price_shell_brutto" value="<?php echo esc_attr( $price_shell_brutto ); ?>" class="widefat dgr-vat-brutto" data-pair="dgr_unit_price_shell_netto">
				<span id="dgr_m2_shell_brutto" class="dgr-m2-calc"></span>
			</p>
		</div>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Cennik - Stan Deweloperski', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_developer_netto"><?php esc_html_e( 'Cena Netto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_developer_netto" name="dgr_unit_price_developer_netto" value="<?php echo esc_attr( $price_developer_netto ); ?>" class="widefat dgr-vat-netto" data-pair="dgr_unit_price_developer_brutto">
				<span id="dgr_m2_dev_netto" class="dgr-m2-calc"></span>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_developer_brutto"><?php esc_html_e( 'Cena Brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_developer_brutto" name="dgr_unit_price_developer_brutto" value="<?php echo esc_attr( $price_developer_brutto ); ?>" class="widefat dgr-vat-brutto" data-pair="dgr_unit_price_developer_netto">
				<span id="dgr_m2_dev_brutto" class="dgr-m2-calc"></span>
			</p>
		</div>

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

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Balkony', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_balconies_count"><?php esc_html_e( 'Ilość balkonów', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" min="0" step="1" id="dgr_unit_balconies_count" name="dgr_unit_balconies_count" value="<?php echo esc_attr( $balconies_count ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_balconies_area"><?php esc_html_e( 'Powierzchnia balkonów (m²)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" min="0" step="0.01" id="dgr_unit_balconies_area" name="dgr_unit_balconies_area" value="<?php echo esc_attr( $balconies_area ); ?>" class="widefat">
			</p>
		</div>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Garaż', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_garage_number"><?php esc_html_e( 'Nr garażu', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="text" id="dgr_unit_garage_number" name="dgr_unit_garage_number" value="<?php echo esc_attr( $garage_number ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_garage_price_brutto"><?php esc_html_e( 'Cena brutto garażu (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" min="0" step="0.01" id="dgr_unit_garage_price_brutto" name="dgr_unit_garage_price_brutto" value="<?php echo esc_attr( $garage_price_brutto ); ?>" class="widefat">
			</p>
		</div>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Komórka lokatorska', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_storage_number"><?php esc_html_e( 'Nr komórki', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="text" id="dgr_unit_storage_number" name="dgr_unit_storage_number" value="<?php echo esc_attr( $storage_number ); ?>" class="widefat">
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_storage_price_brutto"><?php esc_html_e( 'Cena brutto komórki (PLN)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" min="0" step="0.01" id="dgr_unit_storage_price_brutto" name="dgr_unit_storage_price_brutto" value="<?php echo esc_attr( $storage_price_brutto ); ?>" class="widefat">
			</p>
		</div>

		<hr>
		<h4 style="margin-bottom:5px;"><?php esc_html_e( 'Dane wymagane przez ustawę (art. 19b)', 'wp-deweloper-gov-reporter' ); ?></h4>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_sale_start_date"><?php esc_html_e( 'Data rozpoczęcia sprzedaży', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="date" id="dgr_unit_sale_start_date" name="dgr_unit_sale_start_date" value="<?php echo esc_attr( $sale_start_date ); ?>" class="widefat">
				<small style="color:#888;"><?php esc_html_e( 'Auto-uzupełniana przy pierwszym zapisie.', 'wp-deweloper-gov-reporter' ); ?></small>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_total_initial"><?php esc_html_e( 'Cena całkowita z dnia rozpoczęcia sprzedaży (PLN brutto)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_total_initial" name="dgr_unit_price_total_initial" value="<?php echo esc_attr( $price_total_initial ); ?>" class="widefat">
				<small style="color:#888;"><?php esc_html_e( 'Auto-uzupełniana przy pierwszym zapisie.', 'wp-deweloper-gov-reporter' ); ?></small>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_m2_initial"><?php esc_html_e( 'Cena m² z dnia rozpoczęcia sprzedaży (PLN brutto)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_m2_initial" name="dgr_unit_price_m2_initial" value="<?php echo esc_attr( $price_m2_initial ); ?>" class="widefat">
				<small style="color:#888;"><?php esc_html_e( 'Auto-uzupełniana przy pierwszym zapisie.', 'wp-deweloper-gov-reporter' ); ?></small>
			</p>
		</div>
		<div style="display:flex;gap:15px;flex-wrap:wrap;">
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_sale_date"><?php esc_html_e( 'Data sprzedaży', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="date" id="dgr_unit_sale_date" name="dgr_unit_sale_date" value="<?php echo esc_attr( $sale_date ); ?>" class="widefat">
				<small style="color:#888;"><?php esc_html_e( 'Wypełnij przy statusie "sprzedany".', 'wp-deweloper-gov-reporter' ); ?></small>
			</p>
			<p style="flex:1;min-width:200px;">
				<label for="dgr_unit_price_sale_brutto"><?php esc_html_e( 'Cena sprzedaży (PLN brutto)', 'wp-deweloper-gov-reporter' ); ?></label>
				<input type="number" step="0.01" min="0" id="dgr_unit_price_sale_brutto" name="dgr_unit_price_sale_brutto" value="<?php echo esc_attr( $price_sale_brutto ); ?>" class="widefat">
			</p>
		</div>

		<hr>
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
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_karta_full id="<?php echo esc_attr( $post->ID ); ?>"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_cena id="<?php echo esc_attr( $post->ID ); ?>" typ="deweloperski" vat="brutto"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_cena id="<?php echo esc_attr( $post->ID ); ?>" typ="surowy" vat="netto"]</code>
		<code style="display:block;background:#f1f1f1;padding:8px;margin:4px 0;font-size:12px;">[dgr_lokal_metraz id="<?php echo esc_attr( $post->ID ); ?>"]</code>

		<style>.dgr-m2-calc{display:block;margin-top:3px;font-size:12px;color:#666;font-style:italic;}</style>
		<script>
		(function(){
			var areaField = document.getElementById('dgr_unit_area');
			var govPrice = document.getElementById('dgr_unit_price_total');
			var govDisplay = document.getElementById('dgr_unit_price_m2_display');
			var vatSelect = document.getElementById('dgr_unit_vat_rate');

			function getVat() { return parseFloat(vatSelect.value) || 8; }
			function getArea() { return parseFloat(areaField.value) || 0; }

			function fmtM2(v) {
				return v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',') + ' zł/m²';
			}

			// Netto→Brutto and Brutto→Netto auto-fill
			function onNetto(e) {
				var netto = parseFloat(e.target.value) || 0;
				var pairId = e.target.getAttribute('data-pair');
				var brutto = document.getElementById(pairId);
				if (netto > 0) {
					brutto.value = (netto * (1 + getVat() / 100)).toFixed(2);
				} else {
					brutto.value = '';
				}
				calcM2();
			}
			function onBrutto(e) {
				var brutto = parseFloat(e.target.value) || 0;
				var pairId = e.target.getAttribute('data-pair');
				var netto = document.getElementById(pairId);
				if (brutto > 0) {
					netto.value = (brutto / (1 + getVat() / 100)).toFixed(2);
				} else {
					netto.value = '';
				}
				calcM2();
			}

			document.querySelectorAll('.dgr-vat-netto').forEach(function(el) {
				el.addEventListener('input', onNetto);
			});
			document.querySelectorAll('.dgr-vat-brutto').forEach(function(el) {
				el.addEventListener('input', onBrutto);
			});

			// Recalculate all when VAT rate changes
			vatSelect.addEventListener('change', function() {
				// Recalculate brutto from netto for all pairs
				document.querySelectorAll('.dgr-vat-netto').forEach(function(el) {
					var netto = parseFloat(el.value) || 0;
					if (netto > 0) {
						var pairId = el.getAttribute('data-pair');
						document.getElementById(pairId).value = (netto * (1 + getVat() / 100)).toFixed(2);
					}
				});
				calcM2();
			});

			// Price per m² calculations (always from brutto)
			function calcM2() {
				var area = getArea();

				// Gov report price/m²
				var gov = parseFloat(govPrice.value) || 0;
				govDisplay.value = (gov > 0 && area > 0) ? fmtM2(gov / area) : '—';

				// Variant prices/m² - show under brutto fields
				var bruttoFields = [
					{input: 'dgr_unit_price_shell_brutto', display: 'dgr_m2_shell_brutto'},
					{input: 'dgr_unit_price_developer_brutto', display: 'dgr_m2_dev_brutto'},
				];
				bruttoFields.forEach(function(f) {
					var price = parseFloat(document.getElementById(f.input).value) || 0;
					var span = document.getElementById(f.display);
					span.textContent = (price > 0 && area > 0) ? fmtM2(price / area) : '';
				});

				// Netto m² display
				var nettoFields = [
					{input: 'dgr_unit_price_shell_netto', display: 'dgr_m2_shell_netto'},
					{input: 'dgr_unit_price_developer_netto', display: 'dgr_m2_dev_netto'},
				];
				nettoFields.forEach(function(f) {
					var price = parseFloat(document.getElementById(f.input).value) || 0;
					var span = document.getElementById(f.display);
					span.textContent = (price > 0 && area > 0) ? fmtM2(price / area) : '';
				});
			}

			areaField.addEventListener('input', calcM2);
			govPrice.addEventListener('input', calcM2);
			calcM2();
		})();
		</script>
		<?php
	}

	public function save_meta_boxes( $post_id ) {
		// Save Investment
		if ( isset( $_POST['dgr_investment_nonce'] ) && wp_verify_nonce( $_POST['dgr_investment_nonce'], 'dgr_save_investment_data' ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$fields = array(
				'dgr_investment_id',
				'dgr_investment_nip',
				'dgr_investment_voivodeship',
				'dgr_investment_county',
				'dgr_investment_commune',
				'dgr_investment_city',
				'dgr_investment_street',
				'dgr_investment_building_number',
				'dgr_investment_postal_code',
			);
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

			// VAT rate
			if ( isset( $_POST['dgr_unit_vat_rate'] ) ) {
				$vat = intval( $_POST['dgr_unit_vat_rate'] );
				if ( in_array( $vat, array( 8, 23 ), true ) ) {
					update_post_meta( $post_id, '_dgr_unit_vat_rate', $vat );
				}
			}

			// Text fields
			$text_fields = array( 'dgr_unit_parent_investment', 'dgr_unit_id', 'dgr_unit_status', 'dgr_unit_location_label', 'dgr_unit_garage_number', 'dgr_unit_storage_number' );
			foreach ( $text_fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}

			// Rodzaj lokalu (whitelist)
			if ( isset( $_POST['dgr_unit_type'] ) ) {
				$type = sanitize_key( $_POST['dgr_unit_type'] );
				if ( in_array( $type, array( 'lokal_mieszkalny', 'dom_jednorodzinny' ), true ) ) {
					update_post_meta( $post_id, '_dgr_unit_type', $type );
				}
			}

			// Date fields (Y-m-d)
			foreach ( array( 'dgr_unit_sale_start_date', 'dgr_unit_sale_date' ) as $date_field ) {
				if ( isset( $_POST[ $date_field ] ) ) {
					$date = sanitize_text_field( $_POST[ $date_field ] );
					if ( '' === $date || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
						update_post_meta( $post_id, '_' . $date_field, $date );
					}
				}
			}

			// Numeric fields - validate as positive numbers
			$numeric_fields = array(
				'dgr_unit_price_total'           => 0.01,
				'dgr_unit_area'                  => 0.01,
				'dgr_unit_rooms'                 => 1,
				'dgr_unit_floor'                 => 0,
				'dgr_unit_price_shell_netto'     => 0,
				'dgr_unit_price_shell_brutto'    => 0,
				'dgr_unit_price_developer_netto' => 0,
				'dgr_unit_price_developer_brutto'=> 0,
				'dgr_unit_balconies_count'       => 0,
				'dgr_unit_balconies_area'        => 0,
				'dgr_unit_garage_price_brutto'   => 0,
				'dgr_unit_storage_price_brutto'  => 0,
				'dgr_unit_price_total_initial'   => 0,
				'dgr_unit_price_m2_initial'      => 0,
				'dgr_unit_price_sale_brutto'     => 0,
			);

			foreach ( $numeric_fields as $field => $min ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = floatval( $_POST[ $field ] );
					if ( $value >= $min ) {
						update_post_meta( $post_id, '_' . $field, $value );
					}
				}
			}

			// Auto-calculate price per m² for all variants
			$area = floatval( get_post_meta( $post_id, '_dgr_unit_area', true ) );
			if ( $area > 0 ) {
				$price_fields_m2 = array(
					'_dgr_unit_price_total'           => '_dgr_unit_price_m2',
					'_dgr_unit_price_shell_netto'     => '_dgr_unit_price_shell_netto_m2',
					'_dgr_unit_price_shell_brutto'    => '_dgr_unit_price_shell_brutto_m2',
					'_dgr_unit_price_developer_netto'  => '_dgr_unit_price_developer_netto_m2',
					'_dgr_unit_price_developer_brutto' => '_dgr_unit_price_developer_brutto_m2',
				);
				foreach ( $price_fields_m2 as $price_key => $m2_key ) {
					$price = floatval( get_post_meta( $post_id, $price_key, true ) );
					if ( $price > 0 ) {
						update_post_meta( $post_id, $m2_key, round( $price / $area, 2 ) );
					} else {
						delete_post_meta( $post_id, $m2_key );
					}
				}
			}

			// Auto-populate initial price + sale start date on first save (ustawa deweloperska)
			$current_price_total = floatval( get_post_meta( $post_id, '_dgr_unit_price_total', true ) );
			$current_price_m2    = floatval( get_post_meta( $post_id, '_dgr_unit_price_m2', true ) );
			if ( $current_price_total > 0 ) {
				$initial_total = get_post_meta( $post_id, '_dgr_unit_price_total_initial', true );
				if ( '' === $initial_total || floatval( $initial_total ) <= 0 ) {
					update_post_meta( $post_id, '_dgr_unit_price_total_initial', $current_price_total );
				}
				$initial_m2 = get_post_meta( $post_id, '_dgr_unit_price_m2_initial', true );
				if ( ( '' === $initial_m2 || floatval( $initial_m2 ) <= 0 ) && $current_price_m2 > 0 ) {
					update_post_meta( $post_id, '_dgr_unit_price_m2_initial', $current_price_m2 );
				}
				$sale_start = get_post_meta( $post_id, '_dgr_unit_sale_start_date', true );
				if ( '' === $sale_start ) {
					update_post_meta( $post_id, '_dgr_unit_sale_start_date', wp_date( 'Y-m-d' ) );
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
