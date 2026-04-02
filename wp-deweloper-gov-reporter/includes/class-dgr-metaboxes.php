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
			'dgr_investment_stats',
			__( 'Statystyki Lokali', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_investment_stats_metabox' ),
			'dgr_investment',
			'side',
			'default'
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

	/**
	 * Investment Stats sidebar metabox - shows unit counts for this investment.
	 */
	public function render_investment_stats_metabox( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="dgr-stats-empty">' . esc_html__( 'Zapisz inwestycję, aby zobaczyć statystyki lokali.', 'wp-deweloper-gov-reporter' ) . '</p>';
			return;
		}

		$units = get_posts( array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_dgr_unit_parent_investment',
					'value' => $post->ID,
				),
			),
		) );

		$total     = count( $units );
		$available = 0;
		$reserved  = 0;
		$sold      = 0;
		$total_value = 0;

		foreach ( $units as $unit ) {
			$status = get_post_meta( $unit->ID, '_dgr_unit_status', true );
			$price  = (float) get_post_meta( $unit->ID, '_dgr_unit_price_total', true );

			if ( in_array( $status, array( 'available', 'offer' ), true ) ) {
				$available++;
				$total_value += $price;
			} elseif ( in_array( $status, array( 'reserved', 'reservation_agreement', 'developer_agreement' ), true ) ) {
				$reserved++;
			} elseif ( in_array( $status, array( 'sold', 'transferred' ), true ) ) {
				$sold++;
			}
		}

		?>
		<div class="dgr-stats-panel">
			<div class="dgr-stat-row">
				<span class="dgr-stat-label"><?php esc_html_e( 'Wszystkie lokale', 'wp-deweloper-gov-reporter' ); ?></span>
				<span class="dgr-stat-value"><?php echo esc_html( $total ); ?></span>
			</div>
			<div class="dgr-stat-row dgr-stat-available">
				<span class="dgr-stat-label"><?php esc_html_e( 'Dostępne', 'wp-deweloper-gov-reporter' ); ?></span>
				<span class="dgr-stat-value"><?php echo esc_html( $available ); ?></span>
			</div>
			<div class="dgr-stat-row dgr-stat-reserved">
				<span class="dgr-stat-label"><?php esc_html_e( 'Zarezerwowane', 'wp-deweloper-gov-reporter' ); ?></span>
				<span class="dgr-stat-value"><?php echo esc_html( $reserved ); ?></span>
			</div>
			<div class="dgr-stat-row dgr-stat-sold">
				<span class="dgr-stat-label"><?php esc_html_e( 'Sprzedane', 'wp-deweloper-gov-reporter' ); ?></span>
				<span class="dgr-stat-value"><?php echo esc_html( $sold ); ?></span>
			</div>
			<?php if ( $total_value > 0 ) : ?>
			<div class="dgr-stat-row dgr-stat-total-value">
				<span class="dgr-stat-label"><?php esc_html_e( 'Wartość dostępnych', 'wp-deweloper-gov-reporter' ); ?></span>
				<span class="dgr-stat-value"><?php echo esc_html( number_format( $total_value, 0, ',', ' ' ) ); ?> PLN</span>
			</div>
			<?php endif; ?>
			<?php if ( $total > 0 ) : ?>
			<div class="dgr-stat-bar">
				<?php if ( $available > 0 ) : ?>
					<div class="dgr-bar-segment dgr-bar-available" style="width: <?php echo esc_attr( ( $available / $total ) * 100 ); ?>%;" title="<?php esc_attr_e( 'Dostępne', 'wp-deweloper-gov-reporter' ); ?>"></div>
				<?php endif; ?>
				<?php if ( $reserved > 0 ) : ?>
					<div class="dgr-bar-segment dgr-bar-reserved" style="width: <?php echo esc_attr( ( $reserved / $total ) * 100 ); ?>%;" title="<?php esc_attr_e( 'Zarezerwowane', 'wp-deweloper-gov-reporter' ); ?>"></div>
				<?php endif; ?>
				<?php if ( $sold > 0 ) : ?>
					<div class="dgr-bar-segment dgr-bar-sold" style="width: <?php echo esc_attr( ( $sold / $total ) * 100 ); ?>%;" title="<?php esc_attr_e( 'Sprzedane', 'wp-deweloper-gov-reporter' ); ?>"></div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_investment_metabox( $post ) {
		wp_nonce_field( 'dgr_save_investment_data', 'dgr_investment_nonce' );

		$address       = get_post_meta( $post->ID, '_dgr_investment_address', true );
		$city          = get_post_meta( $post->ID, '_dgr_investment_city', true );
		$postal_code   = get_post_meta( $post->ID, '_dgr_investment_postal_code', true );
		$voivodeship   = get_post_meta( $post->ID, '_dgr_investment_voivodeship', true );
		$gov_id        = get_post_meta( $post->ID, '_dgr_investment_id', true );
		$nip           = get_post_meta( $post->ID, '_dgr_investment_nip', true );
		$type          = get_post_meta( $post->ID, '_dgr_investment_type', true );
		$stage         = get_post_meta( $post->ID, '_dgr_investment_stage', true );
		$start_date    = get_post_meta( $post->ID, '_dgr_investment_start_date', true );
		$end_date      = get_post_meta( $post->ID, '_dgr_investment_end_date', true );
		$permit_number = get_post_meta( $post->ID, '_dgr_investment_permit_number', true );
		$land_register = get_post_meta( $post->ID, '_dgr_investment_land_register', true );

		$voivodeships = array(
			''                    => __( '— Wybierz —', 'wp-deweloper-gov-reporter' ),
			'dolnoslaskie'        => 'Dolnośląskie',
			'kujawsko-pomorskie'  => 'Kujawsko-pomorskie',
			'lubelskie'           => 'Lubelskie',
			'lubuskie'            => 'Lubuskie',
			'lodzkie'             => 'Łódzkie',
			'malopolskie'         => 'Małopolskie',
			'mazowieckie'         => 'Mazowieckie',
			'opolskie'            => 'Opolskie',
			'podkarpackie'        => 'Podkarpackie',
			'podlaskie'           => 'Podlaskie',
			'pomorskie'           => 'Pomorskie',
			'slaskie'             => 'Śląskie',
			'swietokrzyskie'      => 'Świętokrzyskie',
			'warminsko-mazurskie' => 'Warmińsko-mazurskie',
			'wielkopolskie'       => 'Wielkopolskie',
			'zachodniopomorskie'  => 'Zachodniopomorskie',
		);
		?>
		<div class="dgr-metabox-sections">

			<!-- Section: Dane Firmy -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-businessperson"></span>
					<?php esc_html_e( 'Dane Dewelopera', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content dgr-grid-2">
					<div class="dgr-field">
						<label for="dgr_investment_nip"><?php esc_html_e( 'NIP Dewelopera', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="text" id="dgr_investment_nip" name="dgr_investment_nip" value="<?php echo esc_attr( $nip ); ?>" placeholder="0000000000" pattern="[0-9]{10}" title="<?php esc_attr_e( 'NIP: 10 cyfr', 'wp-deweloper-gov-reporter' ); ?>">
						<p class="dgr-field-hint"><?php esc_html_e( 'Wpisz NIP i kliknij "Pobierz dane z GUS" — adres uzupełni się automatycznie.', 'wp-deweloper-gov-reporter' ); ?></p>
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_id"><?php esc_html_e( 'ID Inwestycji (gov)', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="text" id="dgr_investment_id" name="dgr_investment_id" value="<?php echo esc_attr( $gov_id ); ?>" placeholder="<?php esc_attr_e( 'np. INW/2024/001', 'wp-deweloper-gov-reporter' ); ?>">
						<p class="dgr-field-hint"><?php esc_html_e( 'Identyfikator nadany przez urząd.', 'wp-deweloper-gov-reporter' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Section: Lokalizacja -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Lokalizacja', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content">
					<div class="dgr-grid-2">
						<div class="dgr-field dgr-field-full">
							<label for="dgr_investment_address"><?php esc_html_e( 'Ulica i numer', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="text" id="dgr_investment_address" name="dgr_investment_address" value="<?php echo esc_attr( $address ); ?>" placeholder="<?php esc_attr_e( 'np. ul. Marszałkowska 1', 'wp-deweloper-gov-reporter' ); ?>">
						</div>
					</div>
					<div class="dgr-grid-3">
						<div class="dgr-field">
							<label for="dgr_investment_city"><?php esc_html_e( 'Miasto', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="text" id="dgr_investment_city" name="dgr_investment_city" value="<?php echo esc_attr( $city ); ?>" placeholder="<?php esc_attr_e( 'np. Warszawa', 'wp-deweloper-gov-reporter' ); ?>">
						</div>
						<div class="dgr-field">
							<label for="dgr_investment_postal_code"><?php esc_html_e( 'Kod pocztowy', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="text" id="dgr_investment_postal_code" name="dgr_investment_postal_code" value="<?php echo esc_attr( $postal_code ); ?>" placeholder="00-000" pattern="[0-9]{2}-[0-9]{3}" title="<?php esc_attr_e( 'Format: 00-000', 'wp-deweloper-gov-reporter' ); ?>">
						</div>
						<div class="dgr-field">
							<label for="dgr_investment_voivodeship"><?php esc_html_e( 'Województwo', 'wp-deweloper-gov-reporter' ); ?></label>
							<select id="dgr_investment_voivodeship" name="dgr_investment_voivodeship">
								<?php foreach ( $voivodeships as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $voivodeship, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- Section: Szczegóły Inwestycji -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-building"></span>
					<?php esc_html_e( 'Szczegóły Inwestycji', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content dgr-grid-2">
					<div class="dgr-field">
						<label for="dgr_investment_type"><?php esc_html_e( 'Typ inwestycji', 'wp-deweloper-gov-reporter' ); ?></label>
						<select id="dgr_investment_type" name="dgr_investment_type">
							<option value="" <?php selected( $type, '' ); ?>><?php esc_html_e( '— Wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="residential" <?php selected( $type, 'residential' ); ?>><?php esc_html_e( 'Mieszkaniowa', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="commercial" <?php selected( $type, 'commercial' ); ?>><?php esc_html_e( 'Komercyjna', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="mixed" <?php selected( $type, 'mixed' ); ?>><?php esc_html_e( 'Mieszana', 'wp-deweloper-gov-reporter' ); ?></option>
						</select>
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_stage"><?php esc_html_e( 'Etap realizacji', 'wp-deweloper-gov-reporter' ); ?></label>
						<select id="dgr_investment_stage" name="dgr_investment_stage">
							<option value="" <?php selected( $stage, '' ); ?>><?php esc_html_e( '— Wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="planned" <?php selected( $stage, 'planned' ); ?>><?php esc_html_e( 'Planowana', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="pre_sale" <?php selected( $stage, 'pre_sale' ); ?>><?php esc_html_e( 'Przedsprzedaż', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="under_construction" <?php selected( $stage, 'under_construction' ); ?>><?php esc_html_e( 'W budowie', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="completed" <?php selected( $stage, 'completed' ); ?>><?php esc_html_e( 'Zakończona', 'wp-deweloper-gov-reporter' ); ?></option>
						</select>
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_start_date"><?php esc_html_e( 'Data rozpoczęcia budowy', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="date" id="dgr_investment_start_date" name="dgr_investment_start_date" value="<?php echo esc_attr( $start_date ); ?>">
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_end_date"><?php esc_html_e( 'Planowana data zakończenia', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="date" id="dgr_investment_end_date" name="dgr_investment_end_date" value="<?php echo esc_attr( $end_date ); ?>">
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_permit_number"><?php esc_html_e( 'Nr pozwolenia na budowę', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="text" id="dgr_investment_permit_number" name="dgr_investment_permit_number" value="<?php echo esc_attr( $permit_number ); ?>" placeholder="<?php esc_attr_e( 'np. AB.6740.123.2024', 'wp-deweloper-gov-reporter' ); ?>">
					</div>
					<div class="dgr-field">
						<label for="dgr_investment_land_register"><?php esc_html_e( 'Nr księgi wieczystej', 'wp-deweloper-gov-reporter' ); ?></label>
						<input type="text" id="dgr_investment_land_register" name="dgr_investment_land_register" value="<?php echo esc_attr( $land_register ); ?>" placeholder="<?php esc_attr_e( 'np. WA1M/00012345/6', 'wp-deweloper-gov-reporter' ); ?>">
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	public function render_unit_metabox( $post ) {
		wp_nonce_field( 'dgr_save_unit_data', 'dgr_unit_nonce' );

		$parent_investment = get_post_meta( $post->ID, '_dgr_unit_parent_investment', true );
		$unit_id           = get_post_meta( $post->ID, '_dgr_unit_id', true );
		$unit_type         = get_post_meta( $post->ID, '_dgr_unit_type', true );
		$building          = get_post_meta( $post->ID, '_dgr_unit_building', true );
		$price_total       = get_post_meta( $post->ID, '_dgr_unit_price_total', true );
		$price_m2          = get_post_meta( $post->ID, '_dgr_unit_price_m2', true );
		$area              = get_post_meta( $post->ID, '_dgr_unit_area', true );
		$rooms             = get_post_meta( $post->ID, '_dgr_unit_rooms', true );
		$floor             = get_post_meta( $post->ID, '_dgr_unit_floor', true );
		$balcony_area      = get_post_meta( $post->ID, '_dgr_unit_balcony_area', true );
		$garden_area       = get_post_meta( $post->ID, '_dgr_unit_garden_area', true );
		$terrace_area      = get_post_meta( $post->ID, '_dgr_unit_terrace_area', true );
		$exposure          = get_post_meta( $post->ID, '_dgr_unit_exposure', true );
		$finishing         = get_post_meta( $post->ID, '_dgr_unit_finishing', true );
		$status            = get_post_meta( $post->ID, '_dgr_unit_status', true );
		$dependencies      = get_post_meta( $post->ID, '_dgr_unit_dependencies', true );

		$investments = get_posts( array(
			'post_type'      => 'dgr_investment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		// Parse dependencies JSON for visual editor
		$deps_array = array();
		if ( ! empty( $dependencies ) ) {
			$decoded = json_decode( $dependencies, true );
			if ( is_array( $decoded ) ) {
				$deps_array = $decoded;
			}
		}
		?>
		<div class="dgr-metabox-sections">

			<!-- Section: Przypisanie -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-admin-home"></span>
					<?php esc_html_e( 'Przypisanie', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content dgr-grid-3">
					<div class="dgr-field">
						<label for="dgr_unit_parent_investment"><?php esc_html_e( 'Inwestycja', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
						<select id="dgr_unit_parent_investment" name="dgr_unit_parent_investment" required>
							<option value=""><?php esc_html_e( '— Wybierz inwestycję —', 'wp-deweloper-gov-reporter' ); ?></option>
							<?php foreach ( $investments as $investment ) : ?>
								<option value="<?php echo esc_attr( $investment->ID ); ?>" <?php selected( $parent_investment, $investment->ID ); ?>><?php echo esc_html( $investment->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="dgr-field">
						<label for="dgr_unit_id"><?php esc_html_e( 'Numer lokalu / ID', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
						<input type="text" id="dgr_unit_id" name="dgr_unit_id" value="<?php echo esc_attr( $unit_id ); ?>" placeholder="<?php esc_attr_e( 'np. A/12', 'wp-deweloper-gov-reporter' ); ?>" required>
					</div>
					<div class="dgr-field">
						<label for="dgr_unit_type"><?php esc_html_e( 'Typ lokalu', 'wp-deweloper-gov-reporter' ); ?></label>
						<select id="dgr_unit_type" name="dgr_unit_type">
							<option value="" <?php selected( $unit_type, '' ); ?>><?php esc_html_e( '— Wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="apartment" <?php selected( $unit_type, 'apartment' ); ?>><?php esc_html_e( 'Mieszkanie', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="commercial" <?php selected( $unit_type, 'commercial' ); ?>><?php esc_html_e( 'Lokal usługowy', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="office" <?php selected( $unit_type, 'office' ); ?>><?php esc_html_e( 'Biuro', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="house" <?php selected( $unit_type, 'house' ); ?>><?php esc_html_e( 'Dom', 'wp-deweloper-gov-reporter' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<!-- Section: Parametry -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-layout"></span>
					<?php esc_html_e( 'Parametry Lokalu', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content">
					<div class="dgr-grid-4">
						<div class="dgr-field">
							<label for="dgr_unit_area"><?php esc_html_e( 'Powierzchnia (m²)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
							<input type="number" step="0.01" min="0.01" id="dgr_unit_area" name="dgr_unit_area" value="<?php echo esc_attr( $area ); ?>" required>
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_rooms"><?php esc_html_e( 'Liczba pokoi', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
							<input type="number" min="1" id="dgr_unit_rooms" name="dgr_unit_rooms" value="<?php echo esc_attr( $rooms ); ?>" required>
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_floor"><?php esc_html_e( 'Piętro', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="number" min="-1" id="dgr_unit_floor" name="dgr_unit_floor" value="<?php echo esc_attr( $floor ); ?>" placeholder="0">
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_building"><?php esc_html_e( 'Budynek / Klatka', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="text" id="dgr_unit_building" name="dgr_unit_building" value="<?php echo esc_attr( $building ); ?>" placeholder="<?php esc_attr_e( 'np. A', 'wp-deweloper-gov-reporter' ); ?>">
						</div>
					</div>
					<div class="dgr-grid-4">
						<div class="dgr-field">
							<label for="dgr_unit_balcony_area"><?php esc_html_e( 'Balkon (m²)', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="number" step="0.01" min="0" id="dgr_unit_balcony_area" name="dgr_unit_balcony_area" value="<?php echo esc_attr( $balcony_area ); ?>">
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_terrace_area"><?php esc_html_e( 'Taras (m²)', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="number" step="0.01" min="0" id="dgr_unit_terrace_area" name="dgr_unit_terrace_area" value="<?php echo esc_attr( $terrace_area ); ?>">
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_garden_area"><?php esc_html_e( 'Ogródek (m²)', 'wp-deweloper-gov-reporter' ); ?></label>
							<input type="number" step="0.01" min="0" id="dgr_unit_garden_area" name="dgr_unit_garden_area" value="<?php echo esc_attr( $garden_area ); ?>">
						</div>
						<div class="dgr-field">
							<label for="dgr_unit_exposure"><?php esc_html_e( 'Ekspozycja okien', 'wp-deweloper-gov-reporter' ); ?></label>
							<select id="dgr_unit_exposure" name="dgr_unit_exposure">
								<option value="" <?php selected( $exposure, '' ); ?>><?php esc_html_e( '— Wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="N" <?php selected( $exposure, 'N' ); ?>><?php esc_html_e( 'Północ (N)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="NE" <?php selected( $exposure, 'NE' ); ?>><?php esc_html_e( 'Północny-wschód (NE)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="E" <?php selected( $exposure, 'E' ); ?>><?php esc_html_e( 'Wschód (E)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="SE" <?php selected( $exposure, 'SE' ); ?>><?php esc_html_e( 'Południowy-wschód (SE)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="S" <?php selected( $exposure, 'S' ); ?>><?php esc_html_e( 'Południe (S)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="SW" <?php selected( $exposure, 'SW' ); ?>><?php esc_html_e( 'Południowy-zachód (SW)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="W" <?php selected( $exposure, 'W' ); ?>><?php esc_html_e( 'Zachód (W)', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="NW" <?php selected( $exposure, 'NW' ); ?>><?php esc_html_e( 'Północny-zachód (NW)', 'wp-deweloper-gov-reporter' ); ?></option>
							</select>
						</div>
					</div>
					<div class="dgr-grid-2">
						<div class="dgr-field">
							<label for="dgr_unit_finishing"><?php esc_html_e( 'Standard wykończenia', 'wp-deweloper-gov-reporter' ); ?></label>
							<select id="dgr_unit_finishing" name="dgr_unit_finishing">
								<option value="" <?php selected( $finishing, '' ); ?>><?php esc_html_e( '— Wybierz —', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="shell" <?php selected( $finishing, 'shell' ); ?>><?php esc_html_e( 'Stan deweloperski', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="turnkey" <?php selected( $finishing, 'turnkey' ); ?>><?php esc_html_e( 'Pod klucz', 'wp-deweloper-gov-reporter' ); ?></option>
								<option value="premium" <?php selected( $finishing, 'premium' ); ?>><?php esc_html_e( 'Premium', 'wp-deweloper-gov-reporter' ); ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- Section: Cena -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-money-alt"></span>
					<?php esc_html_e( 'Cena', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content dgr-grid-2">
					<div class="dgr-field">
						<label for="dgr_unit_price_total"><?php esc_html_e( 'Cena całkowita brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
						<div class="dgr-input-with-suffix">
							<input type="number" step="0.01" min="0.01" id="dgr_unit_price_total" name="dgr_unit_price_total" value="<?php echo esc_attr( $price_total ); ?>" required>
							<span class="dgr-suffix">PLN</span>
						</div>
					</div>
					<div class="dgr-field">
						<label for="dgr_unit_price_m2"><?php esc_html_e( 'Cena za m² brutto (PLN)', 'wp-deweloper-gov-reporter' ); ?> <span class="required">*</span></label>
						<div class="dgr-input-with-suffix">
							<input type="number" step="0.01" min="0.01" id="dgr_unit_price_m2" name="dgr_unit_price_m2" value="<?php echo esc_attr( $price_m2 ); ?>" required>
							<span class="dgr-suffix">PLN/m²</span>
						</div>
						<p class="dgr-field-hint dgr-auto-calc-hint" style="display:none;"><?php esc_html_e( 'Wyliczono automatycznie z ceny i powierzchni.', 'wp-deweloper-gov-reporter' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Section: Status -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-flag"></span>
					<?php esc_html_e( 'Status sprzedaży', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content">
					<div class="dgr-status-selector">
						<?php
						$statuses = array(
							'available'             => array( 'label' => __( 'Dostępny', 'wp-deweloper-gov-reporter' ), 'icon' => 'yes-alt', 'color' => '#46b450' ),
							'offer'                 => array( 'label' => __( 'Oferta specjalna', 'wp-deweloper-gov-reporter' ), 'icon' => 'star-filled', 'color' => '#0073aa' ),
							'reserved'              => array( 'label' => __( 'Zarezerwowany', 'wp-deweloper-gov-reporter' ), 'icon' => 'clock', 'color' => '#f0b849' ),
							'reservation_agreement' => array( 'label' => __( 'Umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ), 'icon' => 'media-text', 'color' => '#d48a00' ),
							'developer_agreement'   => array( 'label' => __( 'Umowa deweloperska', 'wp-deweloper-gov-reporter' ), 'icon' => 'clipboard', 'color' => '#dc3232' ),
							'sold'                  => array( 'label' => __( 'Sprzedany', 'wp-deweloper-gov-reporter' ), 'icon' => 'dismiss', 'color' => '#a00' ),
							'transferred'           => array( 'label' => __( 'Przekazany', 'wp-deweloper-gov-reporter' ), 'icon' => 'saved', 'color' => '#666' ),
						);
						$current_status = $status ? $status : 'available';
						foreach ( $statuses as $key => $s ) :
						?>
						<label class="dgr-status-option <?php echo $current_status === $key ? 'active' : ''; ?>">
							<input type="radio" name="dgr_unit_status" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current_status, $key ); ?>>
							<span class="dgr-status-card" style="--status-color: <?php echo esc_attr( $s['color'] ); ?>">
								<span class="dashicons dashicons-<?php echo esc_attr( $s['icon'] ); ?>"></span>
								<span class="dgr-status-label"><?php echo esc_html( $s['label'] ); ?></span>
							</span>
						</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Section: Przynależności -->
			<div class="dgr-section">
				<h3 class="dgr-section-title">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Przynależności', 'wp-deweloper-gov-reporter' ); ?>
				</h3>
				<div class="dgr-section-content">
					<div id="dgr-dependencies-editor">
						<table class="dgr-deps-table" id="dgr-deps-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Typ', 'wp-deweloper-gov-reporter' ); ?></th>
									<th><?php esc_html_e( 'Cena (PLN)', 'wp-deweloper-gov-reporter' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="dgr-deps-body">
								<?php if ( ! empty( $deps_array ) ) : ?>
									<?php foreach ( $deps_array as $dep ) : ?>
									<tr class="dgr-dep-row">
										<td>
											<select class="dgr-dep-type">
												<option value="miejsce_postojowe" <?php selected( isset( $dep['typ'] ) ? $dep['typ'] : '', 'miejsce_postojowe' ); ?>><?php esc_html_e( 'Miejsce postojowe', 'wp-deweloper-gov-reporter' ); ?></option>
												<option value="komorka_lokatorska" <?php selected( isset( $dep['typ'] ) ? $dep['typ'] : '', 'komorka_lokatorska' ); ?>><?php esc_html_e( 'Komórka lokatorska', 'wp-deweloper-gov-reporter' ); ?></option>
												<option value="garaz" <?php selected( isset( $dep['typ'] ) ? $dep['typ'] : '', 'garaz' ); ?>><?php esc_html_e( 'Garaż', 'wp-deweloper-gov-reporter' ); ?></option>
												<option value="rowerownia" <?php selected( isset( $dep['typ'] ) ? $dep['typ'] : '', 'rowerownia' ); ?>><?php esc_html_e( 'Rowerownia', 'wp-deweloper-gov-reporter' ); ?></option>
												<option value="inne" <?php selected( isset( $dep['typ'] ) ? $dep['typ'] : '', 'inne' ); ?>><?php esc_html_e( 'Inne', 'wp-deweloper-gov-reporter' ); ?></option>
											</select>
										</td>
										<td><input type="number" step="0.01" min="0" class="dgr-dep-price" value="<?php echo esc_attr( isset( $dep['cena'] ) ? $dep['cena'] : '' ); ?>"></td>
										<td><button type="button" class="button dgr-dep-remove" title="<?php esc_attr_e( 'Usuń', 'wp-deweloper-gov-reporter' ); ?>"><span class="dashicons dashicons-trash"></span></button></td>
									</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
						<button type="button" class="button dgr-dep-add" id="dgr-dep-add">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Dodaj przynależność', 'wp-deweloper-gov-reporter' ); ?>
						</button>
						<input type="hidden" id="dgr_unit_dependencies" name="dgr_unit_dependencies" value="<?php echo esc_attr( $dependencies ); ?>">
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	public function save_meta_boxes( $post_id ) {
		// Save Investment
		if ( isset( $_POST['dgr_investment_nonce'] ) && wp_verify_nonce( $_POST['dgr_investment_nonce'], 'dgr_save_investment_data' ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$text_fields = array(
				'dgr_investment_address',
				'dgr_investment_city',
				'dgr_investment_postal_code',
				'dgr_investment_voivodeship',
				'dgr_investment_id',
				'dgr_investment_nip',
				'dgr_investment_type',
				'dgr_investment_stage',
				'dgr_investment_start_date',
				'dgr_investment_end_date',
				'dgr_investment_permit_number',
				'dgr_investment_land_register',
			);
			foreach ( $text_fields as $field ) {
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
			$text_fields = array(
				'dgr_unit_parent_investment',
				'dgr_unit_id',
				'dgr_unit_type',
				'dgr_unit_building',
				'dgr_unit_exposure',
				'dgr_unit_finishing',
				'dgr_unit_status',
			);
			foreach ( $text_fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}

			// Numeric fields - validate as positive numbers
			$numeric_fields = array(
				'dgr_unit_price_total'  => 0.01,
				'dgr_unit_price_m2'     => 0.01,
				'dgr_unit_area'         => 0.01,
				'dgr_unit_rooms'        => 1,
				'dgr_unit_floor'        => -1,
				'dgr_unit_balcony_area' => 0,
				'dgr_unit_terrace_area' => 0,
				'dgr_unit_garden_area'  => 0,
			);

			foreach ( $numeric_fields as $field => $min ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = floatval( $_POST[ $field ] );
					if ( $value >= $min ) {
						update_post_meta( $post_id, '_' . $field, $value );
					} elseif ( '' === $_POST[ $field ] && $min <= 0 ) {
						delete_post_meta( $post_id, '_' . $field );
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
				}
			}
		}
	}
}
