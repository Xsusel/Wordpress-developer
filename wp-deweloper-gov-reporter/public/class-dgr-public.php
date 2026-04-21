<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGR_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function init() {
		add_shortcode( 'dgr_price_history', array( $this, 'render_price_history' ) );
		add_shortcode( 'dgr_unit_details', array( $this, 'render_unit_details' ) );
		add_shortcode( 'dgr_unit_list', array( $this, 'render_unit_list' ) );
		add_shortcode( 'dgr_lokal_karta', array( $this, 'render_lokal_karta' ) );
		add_shortcode( 'dgr_lokal_karta_full', array( $this, 'render_lokal_karta_full' ) );
		add_shortcode( 'dgr_lokal_cena', array( $this, 'render_lokal_cena' ) );
		add_shortcode( 'dgr_lokal_metraz', array( $this, 'render_lokal_metraz' ) );
		add_shortcode( 'dgr_inwestycja_tabela', array( $this, 'render_inwestycja_tabela' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_dgr_filter_units', array( $this, 'ajax_filter_units' ) );
		add_action( 'wp_ajax_nopriv_dgr_filter_units', array( $this, 'ajax_filter_units' ) );
	}

	public function enqueue_scripts() {
		wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );

		wp_register_style( 'dgr-frontend-css', plugins_url( '../assets/css/dgr-frontend.css', __FILE__ ), array(), '1.1.0' );

		wp_register_script( 'dgr-frontend-js', plugins_url( '../assets/js/dgr-frontend.js', __FILE__ ), array( 'jquery' ), '1.1.0', true );
		wp_localize_script( 'dgr-frontend-js', 'dgr_ajax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dgr_filter_nonce' )
		) );
	}

	public function render_price_history( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );

		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
			'view' => 'both',
		), $atts, 'dgr_price_history' );

		$post_id = intval( $atts['id'] );
		$history = get_post_meta( $post_id, '_dgr_price_history', true );

		if ( empty( $history ) || ! is_array( $history ) ) {
			return '<p>' . esc_html__( 'Brak historii cen.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		$one_year_ago = strtotime( '-1 year' );
		$filtered_history = array_filter( $history, function( $entry ) use ( $one_year_ago ) {
			if ( ! isset( $entry['date'] ) ) return false;
			$time = strtotime( $entry['date'] );
			return false !== $time && $time >= $one_year_ago;
		});

		if ( empty( $filtered_history ) ) {
			return '<p>' . esc_html__( 'Brak historii cen w ostatnim roku.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		usort( $filtered_history, function( $a, $b ) {
			return strtotime( $a['date'] ) - strtotime( $b['date'] );
		});

		$labels = array();
		$data_total = array();
		foreach ( $filtered_history as $entry ) {
			$labels[] = $entry['date'];
			$data_total[] = floatval( $entry['price_total'] );
		}

		$table_history = array_reverse( $filtered_history );

		if ( $atts['view'] === 'chart' || $atts['view'] === 'both' ) {
			wp_enqueue_script( 'chartjs' );
		}

		ob_start();
		echo '<div class="dgr-price-history-wrapper">';

		if ( $atts['view'] === 'chart' || $atts['view'] === 'both' ) {
			$chart_id = 'dgrChart_' . wp_unique_id();
			$chart_data = array(
				'labels' => $labels,
				'data' => $data_total,
				'label' => __( 'Cena Całkowita (PLN)', 'wp-deweloper-gov-reporter' ),
			);
			?>
			<div class="dgr-chart-container" style="position: relative; height:300px; width:100%; margin-bottom: 20px;">
				<canvas id="<?php echo esc_attr( $chart_id ); ?>"></canvas>
			</div>
			<?php
			wp_enqueue_script( 'dgr-frontend-js' );
			wp_add_inline_script( 'chartjs', sprintf(
				'document.addEventListener("DOMContentLoaded", function() {
					var ctx = document.getElementById(%s);
					if (ctx) { new Chart(ctx, {
						type: "line",
						data: { labels: %s, datasets: [{ label: %s, data: %s, borderColor: "rgba(75, 192, 192, 1)", borderWidth: 2, fill: false }] },
						options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, ticks: { callback: function(v) { return v + " zł"; } } } } }
					}); }
				});',
				wp_json_encode( $chart_id ),
				wp_json_encode( $chart_data['labels'] ),
				wp_json_encode( $chart_data['label'] ),
				wp_json_encode( $chart_data['data'] )
			) );
		}

		if ( $atts['view'] === 'table' || $atts['view'] === 'both' ) {
			?>
			<h3><?php esc_html_e( 'Historia Cen (Ostatnie 12 miesięcy)', 'wp-deweloper-gov-reporter' ); ?></h3>
			<table class="dgr-table" style="width:100%; border-collapse: collapse;">
				<thead>
					<tr style="border-bottom: 1px solid #ddd;">
						<th style="text-align: left; padding: 8px;"><?php esc_html_e( 'Data', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php esc_html_e( 'Cena Całkowita', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php esc_html_e( 'Cena za m²', 'wp-deweloper-gov-reporter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $table_history as $entry ) : ?>
						<tr style="border-bottom: 1px solid #eee;">
							<td style="padding: 8px;"><?php echo esc_html( $entry['date'] ); ?></td>
							<td style="text-align: right; padding: 8px;"><?php echo esc_html( number_format( floatval( $entry['price_total'] ), 2, ',', ' ' ) ); ?> zł</td>
							<td style="text-align: right; padding: 8px;"><?php echo esc_html( number_format( floatval( $entry['price_m2'] ), 2, ',', ' ' ) ); ?> zł</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}

		echo '</div>';
		return ob_get_clean();
	}

	public function render_unit_list( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );
		wp_enqueue_script( 'dgr-frontend-js' );

		$atts = shortcode_atts( array(
			'investment_id' => '',
		), $atts, 'dgr_unit_list' );

		$params = array(
			'rooms_min' => isset( $_GET['rooms_min'] ) ? intval( $_GET['rooms_min'] ) : 0,
			'rooms_max' => isset( $_GET['rooms_max'] ) ? intval( $_GET['rooms_max'] ) : 0,
			'area_min'  => isset( $_GET['area_min'] ) ? intval( $_GET['area_min'] ) : 0,
			'area_max'  => isset( $_GET['area_max'] ) ? intval( $_GET['area_max'] ) : 0,
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
			'investment_id' => ! empty( $atts['investment_id'] ) ? intval( $atts['investment_id'] ) : ( isset( $_GET['investment_id'] ) ? intval( $_GET['investment_id'] ) : '' ),
		);

		ob_start();
		?>
		<div class="dgr-unit-list-wrapper">
			<form method="get" class="dgr-filter-form">
				<?php if ( ! empty( $atts['investment_id'] ) ) : ?>
					<input type="hidden" name="investment_id" value="<?php echo esc_attr( $atts['investment_id'] ); ?>">
				<?php endif; ?>
				<div class="dgr-filter-row">
					<div>
						<label><?php esc_html_e( 'Pokoje (min-max)', 'wp-deweloper-gov-reporter' ); ?></label><br>
						<input type="number" name="rooms_min" value="<?php echo esc_attr( $params['rooms_min'] ); ?>" min="0" placeholder="od"> -
						<input type="number" name="rooms_max" value="<?php echo esc_attr( $params['rooms_max'] ); ?>" min="0" placeholder="do">
					</div>
					<div>
						<label><?php esc_html_e( 'Powierzchnia m² (min-max)', 'wp-deweloper-gov-reporter' ); ?></label><br>
						<input type="number" name="area_min" value="<?php echo esc_attr( $params['area_min'] ); ?>" min="0" placeholder="od"> -
						<input type="number" name="area_max" value="<?php echo esc_attr( $params['area_max'] ); ?>" min="0" placeholder="do">
					</div>
					<div>
						<label><?php esc_html_e( 'Status', 'wp-deweloper-gov-reporter' ); ?></label><br>
						<select name="status">
							<option value=""><?php esc_html_e( 'Wszystkie', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="available" <?php selected( $params['status'], 'available' ); ?>><?php esc_html_e( 'Dostępne', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="offer" <?php selected( $params['status'], 'offer' ); ?>><?php esc_html_e( 'Oferta specjalna', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="reserved" <?php selected( $params['status'], 'reserved' ); ?>><?php esc_html_e( 'Zarezerwowane', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="reservation_agreement" <?php selected( $params['status'], 'reservation_agreement' ); ?>><?php esc_html_e( 'Umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="developer_agreement" <?php selected( $params['status'], 'developer_agreement' ); ?>><?php esc_html_e( 'Umowa deweloperska', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="sold" <?php selected( $params['status'], 'sold' ); ?>><?php esc_html_e( 'Sprzedane', 'wp-deweloper-gov-reporter' ); ?></option>
							<option value="transferred" <?php selected( $params['status'], 'transferred' ); ?>><?php esc_html_e( 'Przekazane', 'wp-deweloper-gov-reporter' ); ?></option>
						</select>
					</div>
					<div style="align-self: flex-end;">
						<button type="submit" class="button"><?php esc_html_e( 'Filtruj', 'wp-deweloper-gov-reporter' ); ?></button>
					</div>
				</div>
			</form>

			<div class="dgr-results">
				<?php echo $this->get_unit_list_html( $params ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function ajax_filter_units() {
		check_ajax_referer( 'dgr_filter_nonce', 'nonce' );

		$params = array(
			'rooms_min' => isset( $_GET['rooms_min'] ) ? intval( $_GET['rooms_min'] ) : 0,
			'rooms_max' => isset( $_GET['rooms_max'] ) ? intval( $_GET['rooms_max'] ) : 0,
			'area_min'  => isset( $_GET['area_min'] ) ? intval( $_GET['area_min'] ) : 0,
			'area_max'  => isset( $_GET['area_max'] ) ? intval( $_GET['area_max'] ) : 0,
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
			'investment_id' => isset( $_GET['investment_id'] ) ? intval( $_GET['investment_id'] ) : '',
		);

		$html = $this->get_unit_list_html( $params );
		wp_send_json_success( $html );
	}

	private function get_unit_list_html( $params ) {
		$meta_query = array( 'relation' => 'AND' );

		if ( ! empty( $params['investment_id'] ) ) {
			$meta_query[] = array(
				'key'   => '_dgr_unit_parent_investment',
				'value' => $params['investment_id'],
			);
		}

		if ( $params['rooms_min'] > 0 || $params['rooms_max'] > 0 ) {
			$rooms_query = array(
				'key'  => '_dgr_unit_rooms',
				'type' => 'NUMERIC',
			);
			if ( $params['rooms_min'] > 0 && $params['rooms_max'] > 0 ) {
				$rooms_query['value'] = array( $params['rooms_min'], $params['rooms_max'] );
				$rooms_query['compare'] = 'BETWEEN';
			} elseif ( $params['rooms_min'] > 0 ) {
				$rooms_query['value'] = $params['rooms_min'];
				$rooms_query['compare'] = '>=';
			} else {
				$rooms_query['value'] = $params['rooms_max'];
				$rooms_query['compare'] = '<=';
			}
			$meta_query[] = $rooms_query;
		}

		if ( $params['area_min'] > 0 || $params['area_max'] > 0 ) {
			$area_query = array(
				'key'  => '_dgr_unit_area',
				'type' => 'DECIMAL',
			);
			if ( $params['area_min'] > 0 && $params['area_max'] > 0 ) {
				$area_query['value'] = array( $params['area_min'], $params['area_max'] );
				$area_query['compare'] = 'BETWEEN';
			} elseif ( $params['area_min'] > 0 ) {
				$area_query['value'] = $params['area_min'];
				$area_query['compare'] = '>=';
			} else {
				$area_query['value'] = $params['area_max'];
				$area_query['compare'] = '<=';
			}
			$meta_query[] = $area_query;
		}

		if ( ! empty( $params['status'] ) ) {
			$meta_query[] = array(
				'key'   => '_dgr_unit_status',
				'value' => $params['status'],
			);
		}

		$args = array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => 20,
			'meta_query'     => $meta_query,
		);

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) : ?>
			<table class="dgr-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nr Lokalu', 'wp-deweloper-gov-reporter' ); ?></th>
						<th><?php esc_html_e( 'Inwestycja', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Pokoje', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'Powierzchnia', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Status', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Szczegóły', 'wp-deweloper-gov-reporter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post();
						$meta = get_post_meta( get_the_ID() );
						$unit_id = isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '-';
						$area = isset( $meta['_dgr_unit_area'][0] ) ? $meta['_dgr_unit_area'][0] : '-';
						$rooms = isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '-';
						$status = isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '-';
						$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
						$inv_name = $parent_id ? get_the_title( $parent_id ) : '-';
						$status_class = 'status-' . sanitize_html_class( $status );
					?>
						<tr>
							<td><?php echo esc_html( $unit_id ); ?></td>
							<td><?php echo esc_html( $inv_name ); ?></td>
							<td style="text-align: center;"><?php echo esc_html( $rooms ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( $area ); ?> m²</td>
							<td style="text-align: center;"><span class="dgr-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
							<td style="text-align: center;"><a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Zobacz', 'wp-deweloper-gov-reporter' ); ?></a></td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'Nie znaleziono lokali spełniających kryteria.', 'wp-deweloper-gov-reporter' ); ?></p>
		<?php endif; wp_reset_postdata();

		return ob_get_clean();
	}

	public function render_unit_details( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );

		$defaults = array(
			'id' => get_the_ID(),
			'show_investment' => 'yes',
			'show_unit_id' => 'yes',
			'show_area' => 'yes',
			'show_rooms' => 'yes',
			'show_floor' => 'yes',
			'show_status' => 'yes',
			'show_price_total' => 'yes',
			'show_price_m2' => 'yes',
			'show_omnibus' => 'no',
			'show_balconies' => 'yes',
			'show_garage' => 'yes',
			'show_storage' => 'yes',
		);
		$atts = shortcode_atts( $defaults, $atts, 'dgr_unit_details' );

		$post_id = intval( $atts['id'] );

		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$meta = get_post_meta( $post_id );

		ob_start();
		?>
		<div class="dgr-unit-details">
			<h3><?php esc_html_e( 'Szczegóły Lokalu', 'wp-deweloper-gov-reporter' ); ?></h3>
			<ul class="dgr-list">
				<?php if ( $atts['show_investment'] === 'yes' ) :
					$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
					$investment_name = $parent_id ? get_the_title( $parent_id ) : '-';
				?>
					<li><strong><?php esc_html_e( 'Inwestycja:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $investment_name ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_unit_id'] === 'yes' ) :
					$unit_id = isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '-';
				?>
					<li><strong><?php esc_html_e( 'Numer Lokalu:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $unit_id ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_area'] === 'yes' ) :
					$area = isset( $meta['_dgr_unit_area'][0] ) ? $meta['_dgr_unit_area'][0] : '-';
				?>
					<li><strong><?php esc_html_e( 'Powierzchnia:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $area ); ?> m²</li>
				<?php endif; ?>

				<?php if ( $atts['show_rooms'] === 'yes' ) :
					$rooms = isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '-';
				?>
					<li><strong><?php esc_html_e( 'Pokoje:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $rooms ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_floor'] === 'yes' ) :
					$floor = isset( $meta['_dgr_unit_floor'][0] ) ? $meta['_dgr_unit_floor'][0] : '-';
				?>
					<li><strong><?php esc_html_e( 'Piętro:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $floor ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_balconies'] === 'yes' ) :
					$balconies_count = isset( $meta['_dgr_unit_balconies_count'][0] ) ? $meta['_dgr_unit_balconies_count'][0] : '';
					$balconies_area  = isset( $meta['_dgr_unit_balconies_area'][0] ) ? $meta['_dgr_unit_balconies_area'][0] : '';
					if ( '' !== $balconies_count || '' !== $balconies_area ) :
				?>
					<li><strong><?php esc_html_e( 'Balkony:', 'wp-deweloper-gov-reporter' ); ?></strong>
						<?php echo esc_html( '' !== $balconies_count ? $balconies_count : '-' ); ?>
						<?php if ( '' !== $balconies_area ) : ?>
							(<?php echo esc_html( number_format( floatval( $balconies_area ), 2, ',', ' ' ) ); ?> m²)
						<?php endif; ?>
					</li>
				<?php endif; endif; ?>

				<?php if ( $atts['show_garage'] === 'yes' ) :
					$garage_number       = isset( $meta['_dgr_unit_garage_number'][0] ) ? $meta['_dgr_unit_garage_number'][0] : '';
					$garage_price_brutto = isset( $meta['_dgr_unit_garage_price_brutto'][0] ) ? $meta['_dgr_unit_garage_price_brutto'][0] : '';
					if ( '' !== $garage_number || '' !== $garage_price_brutto ) :
				?>
					<li><strong><?php esc_html_e( 'Garaż:', 'wp-deweloper-gov-reporter' ); ?></strong>
						<?php if ( '' !== $garage_number ) : ?>
							<?php esc_html_e( 'nr', 'wp-deweloper-gov-reporter' ); ?> <?php echo esc_html( $garage_number ); ?>
						<?php endif; ?>
						<?php if ( '' !== $garage_price_brutto ) : ?>
							<?php echo '' !== $garage_number ? '—' : ''; ?>
							<?php echo esc_html( number_format( floatval( $garage_price_brutto ), 2, ',', ' ' ) ); ?> zł brutto
						<?php endif; ?>
					</li>
				<?php endif; endif; ?>

				<?php if ( $atts['show_storage'] === 'yes' ) :
					$storage_number       = isset( $meta['_dgr_unit_storage_number'][0] ) ? $meta['_dgr_unit_storage_number'][0] : '';
					$storage_price_brutto = isset( $meta['_dgr_unit_storage_price_brutto'][0] ) ? $meta['_dgr_unit_storage_price_brutto'][0] : '';
					if ( '' !== $storage_number || '' !== $storage_price_brutto ) :
				?>
					<li><strong><?php esc_html_e( 'Komórka lokatorska:', 'wp-deweloper-gov-reporter' ); ?></strong>
						<?php if ( '' !== $storage_number ) : ?>
							<?php esc_html_e( 'nr', 'wp-deweloper-gov-reporter' ); ?> <?php echo esc_html( $storage_number ); ?>
						<?php endif; ?>
						<?php if ( '' !== $storage_price_brutto ) : ?>
							<?php echo '' !== $storage_number ? '—' : ''; ?>
							<?php echo esc_html( number_format( floatval( $storage_price_brutto ), 2, ',', ' ' ) ); ?> zł brutto
						<?php endif; ?>
					</li>
				<?php endif; endif; ?>

				<?php if ( $atts['show_status'] === 'yes' ) :
					$status = isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '-';
				?>
					<li><strong><?php esc_html_e( 'Status:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( ucfirst( $status ) ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_price_total'] === 'yes' ) :
					$price_total = isset( $meta['_dgr_unit_price_total'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_total'][0] ), 2, ',', ' ' ) . ' zł' : '-';
				?>
					<li><strong><?php esc_html_e( 'Cena Całkowita:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_total ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_price_m2'] === 'yes' ) :
					$price_m2 = isset( $meta['_dgr_unit_price_m2'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_m2'][0] ), 2, ',', ' ' ) . ' zł' : '-';
				?>
					<li><strong><?php esc_html_e( 'Cena za m²:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_m2 ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_omnibus'] === 'yes' ) :
					$lowest_price = DGR_Price_History::get_lowest_price_30_days( $post_id );
					if ( $lowest_price ) :
				?>
					<li><strong><?php esc_html_e( 'Najniższa cena (30 dni):', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( number_format( floatval( $lowest_price ), 2, ',', ' ' ) ); ?> zł</li>
				<?php endif; endif; ?>
			</ul>
		</div>

		<?php
		$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
		if ( $parent_id ) {
			$related_query = new WP_Query( array(
				'post_type' => 'dgr_unit',
				'posts_per_page' => 3,
				'post__not_in' => array( $post_id ),
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => '_dgr_unit_parent_investment',
						'value' => $parent_id,
					),
					array(
						'key' => '_dgr_unit_status',
						'value' => 'available',
					),
				),
			) );

			if ( $related_query->have_posts() ) {
				echo '<div class="dgr-related-units">';
				echo '<h3>' . esc_html__( 'Inne dostępne lokale w tej inwestycji', 'wp-deweloper-gov-reporter' ) . '</h3>';
				echo '<div class="dgr-related-grid">';
				while ( $related_query->have_posts() ) {
					$related_query->the_post();
					$rel_meta = get_post_meta( get_the_ID() );
					$rel_rooms = isset( $rel_meta['_dgr_unit_rooms'][0] ) ? $rel_meta['_dgr_unit_rooms'][0] : '-';
					$rel_area = isset( $rel_meta['_dgr_unit_area'][0] ) ? $rel_meta['_dgr_unit_area'][0] : '-';
					$rel_price = isset( $rel_meta['_dgr_unit_price_total'][0] ) ? number_format( floatval( $rel_meta['_dgr_unit_price_total'][0] ), 0, ',', ' ' ) . ' zł' : '-';

					echo '<div class="dgr-related-item">';
					echo '<h4><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4>';
					echo '<p>' . esc_html( sprintf( __( '%s pok., %s m²', 'wp-deweloper-gov-reporter' ), $rel_rooms, $rel_area ) ) . '</p>';
					echo '<p><strong>' . esc_html( $rel_price ) . '</strong></p>';
					echo '</div>';
				}
				echo '</div>';
				echo '</div>';
				wp_reset_postdata();
			}
		}
		?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Helper: format price for display.
	 */
	private function format_price( $value ) {
		$value = floatval( $value );
		if ( $value <= 0 ) {
			return '';
		}
		return number_format( $value, 0, ',', ' ' ) . ' zł';
	}

	/**
	 * Shortcode [dgr_lokal_karta id="123"]
	 *
	 * Renders a property pricing card with location, area, and prices
	 * (shell/developer, netto/brutto) - designed for Elementor or classic editor.
	 */
	public function render_lokal_karta( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );

		$atts = shortcode_atts( array(
			'id'         => get_the_ID(),
			'show_netto' => 'yes',
		), $atts, 'dgr_lokal_karta' );

		$post_id = intval( $atts['id'] );

		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$meta = get_post_meta( $post_id );

		$location = isset( $meta['_dgr_unit_location_label'][0] ) ? $meta['_dgr_unit_location_label'][0] : '';
		$area     = isset( $meta['_dgr_unit_area'][0] ) ? floatval( $meta['_dgr_unit_area'][0] ) : 0;

		$price_shell_netto      = isset( $meta['_dgr_unit_price_shell_netto'][0] ) ? floatval( $meta['_dgr_unit_price_shell_netto'][0] ) : 0;
		$price_shell_brutto     = isset( $meta['_dgr_unit_price_shell_brutto'][0] ) ? floatval( $meta['_dgr_unit_price_shell_brutto'][0] ) : 0;
		$price_developer_netto  = isset( $meta['_dgr_unit_price_developer_netto'][0] ) ? floatval( $meta['_dgr_unit_price_developer_netto'][0] ) : 0;
		$price_developer_brutto = isset( $meta['_dgr_unit_price_developer_brutto'][0] ) ? floatval( $meta['_dgr_unit_price_developer_brutto'][0] ) : 0;

		// Price per m² (from brutto)
		$shell_m2     = ( $price_shell_brutto > 0 && $area > 0 ) ? $price_shell_brutto / $area : 0;
		$developer_m2 = ( $price_developer_brutto > 0 && $area > 0 ) ? $price_developer_brutto / $area : 0;

		$show_netto = ( $atts['show_netto'] === 'yes' );

		ob_start();
		?>
		<div class="dgr-lokal-karta">
			<div class="dgr-lokal-karta__header">
				<?php if ( $location ) : ?>
					<span class="dgr-lokal-karta__location">
						<svg class="dgr-lokal-karta__icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
						<?php echo esc_html( $location ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $area > 0 ) : ?>
					<span class="dgr-lokal-karta__area">
						<svg class="dgr-lokal-karta__icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 12h-2v3h-3v2h5v-5zM7 9h3V7H5v5h2V9zm14-6H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16.01H3V4.99h18v14.02z"/></svg>
						<?php echo esc_html( number_format( $area, 2, ',', ' ' ) ); ?> m&sup2;
					</span>
				<?php endif; ?>
			</div>

			<div class="dgr-lokal-karta__prices">
				<?php if ( $price_shell_brutto > 0 ) : ?>
					<div class="dgr-lokal-karta__price-block">
						<span class="dgr-lokal-karta__price-label"><?php esc_html_e( 'Stan surowy zamknięty', 'wp-deweloper-gov-reporter' ); ?></span>
						<span class="dgr-lokal-karta__price-value"><?php echo esc_html( $this->format_price( $price_shell_brutto ) ); ?></span>
						<?php if ( $shell_m2 > 0 ) : ?>
							<span class="dgr-lokal-karta__price-m2"><?php echo esc_html( number_format( $shell_m2, 2, ',', ' ' ) ); ?> zł/m&sup2;</span>
						<?php endif; ?>
						<?php if ( $show_netto && $price_shell_netto > 0 ) : ?>
							<span class="dgr-lokal-karta__price-netto"><?php echo esc_html( sprintf( __( 'netto: %s', 'wp-deweloper-gov-reporter' ), $this->format_price( $price_shell_netto ) ) ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $price_developer_brutto > 0 ) : ?>
					<div class="dgr-lokal-karta__price-block">
						<span class="dgr-lokal-karta__price-label"><?php esc_html_e( 'Deweloperski', 'wp-deweloper-gov-reporter' ); ?></span>
						<span class="dgr-lokal-karta__price-value"><?php echo esc_html( $this->format_price( $price_developer_brutto ) ); ?></span>
						<?php if ( $developer_m2 > 0 ) : ?>
							<span class="dgr-lokal-karta__price-m2"><?php echo esc_html( number_format( $developer_m2, 2, ',', ' ' ) ); ?> zł/m&sup2;</span>
						<?php endif; ?>
						<?php if ( $show_netto && $price_developer_netto > 0 ) : ?>
							<span class="dgr-lokal-karta__price-netto"><?php echo esc_html( sprintf( __( 'netto: %s', 'wp-deweloper-gov-reporter' ), $this->format_price( $price_developer_netto ) ) ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode [dgr_lokal_karta_full id="123"]
	 *
	 * Full property card: all unit data + pricing + accessories (garage, storage, balconies).
	 */
	public function render_lokal_karta_full( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );

		$atts = shortcode_atts( array(
			'id'         => get_the_ID(),
			'show_netto' => 'yes',
		), $atts, 'dgr_lokal_karta_full' );

		$post_id = intval( $atts['id'] );

		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$meta = get_post_meta( $post_id );

		$get = function( $key ) use ( $meta ) {
			return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : '';
		};

		$parent_id       = intval( $get( '_dgr_unit_parent_investment' ) );
		$investment_name = $parent_id ? get_the_title( $parent_id ) : '';
		$investment_addr = '';
		if ( $parent_id ) {
			$inv = get_post_meta( $parent_id );
			$street  = isset( $inv['_dgr_investment_street'][0] ) ? $inv['_dgr_investment_street'][0] : '';
			$bldg    = isset( $inv['_dgr_investment_building_number'][0] ) ? $inv['_dgr_investment_building_number'][0] : '';
			$city    = isset( $inv['_dgr_investment_city'][0] ) ? $inv['_dgr_investment_city'][0] : '';
			$parts   = array_filter( array( trim( $street . ' ' . $bldg ), $city ) );
			$investment_addr = implode( ', ', $parts );
		}
		$unit_id         = $get( '_dgr_unit_id' );
		$unit_type       = $get( '_dgr_unit_type' );
		$location        = $get( '_dgr_unit_location_label' );
		$area            = floatval( $get( '_dgr_unit_area' ) );
		$rooms           = $get( '_dgr_unit_rooms' );
		$floor           = $get( '_dgr_unit_floor' );
		$status          = $get( '_dgr_unit_status' );

		$price_total  = floatval( $get( '_dgr_unit_price_total' ) );
		$price_m2     = floatval( $get( '_dgr_unit_price_m2' ) );

		$price_shell_netto      = floatval( $get( '_dgr_unit_price_shell_netto' ) );
		$price_shell_brutto     = floatval( $get( '_dgr_unit_price_shell_brutto' ) );
		$price_developer_netto  = floatval( $get( '_dgr_unit_price_developer_netto' ) );
		$price_developer_brutto = floatval( $get( '_dgr_unit_price_developer_brutto' ) );

		$shell_m2     = ( $price_shell_brutto > 0 && $area > 0 ) ? $price_shell_brutto / $area : 0;
		$developer_m2 = ( $price_developer_brutto > 0 && $area > 0 ) ? $price_developer_brutto / $area : 0;

		$balconies_count      = $get( '_dgr_unit_balconies_count' );
		$balconies_area       = $get( '_dgr_unit_balconies_area' );
		$garage_number        = $get( '_dgr_unit_garage_number' );
		$garage_price_brutto  = floatval( $get( '_dgr_unit_garage_price_brutto' ) );
		$storage_number       = $get( '_dgr_unit_storage_number' );
		$storage_price_brutto = floatval( $get( '_dgr_unit_storage_price_brutto' ) );

		$status_labels = array(
			'available'             => __( 'Dostępny', 'wp-deweloper-gov-reporter' ),
			'offer'                 => __( 'Oferta specjalna', 'wp-deweloper-gov-reporter' ),
			'reserved'              => __( 'Zarezerwowany', 'wp-deweloper-gov-reporter' ),
			'reservation_agreement' => __( 'Umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ),
			'developer_agreement'   => __( 'Umowa deweloperska', 'wp-deweloper-gov-reporter' ),
			'sold'                  => __( 'Sprzedany', 'wp-deweloper-gov-reporter' ),
			'transferred'           => __( 'Przekazany', 'wp-deweloper-gov-reporter' ),
		);
		$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
		$status_class = $status ? 'status-' . sanitize_html_class( $status ) : '';

		$show_netto = ( $atts['show_netto'] === 'yes' );

		ob_start();
		?>
		<div class="dgr-lokal-karta dgr-lokal-karta--full">
			<div class="dgr-lokal-karta__header">
				<?php if ( $location ) : ?>
					<span class="dgr-lokal-karta__location">
						<svg class="dgr-lokal-karta__icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
						<?php echo esc_html( $location ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $status ) : ?>
					<span class="dgr-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
				<?php endif; ?>
			</div>

			<ul class="dgr-lokal-karta__specs">
				<?php if ( $investment_name ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Inwestycja', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( $investment_name ); ?></span></li>
				<?php endif; ?>
				<?php if ( $investment_addr ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Adres', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( $investment_addr ); ?></span></li>
				<?php endif; ?>
				<?php if ( $unit_type ) :
					$type_labels = array( 'lokal_mieszkalny' => __( 'Lokal mieszkalny', 'wp-deweloper-gov-reporter' ), 'dom_jednorodzinny' => __( 'Dom jednorodzinny', 'wp-deweloper-gov-reporter' ) );
				?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Rodzaj', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( isset( $type_labels[ $unit_type ] ) ? $type_labels[ $unit_type ] : $unit_type ); ?></span></li>
				<?php endif; ?>
				<?php if ( '' !== $unit_id ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Nr lokalu', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( $unit_id ); ?></span></li>
				<?php endif; ?>
				<?php if ( $area > 0 ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Powierzchnia', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( number_format( $area, 2, ',', ' ' ) ); ?> m&sup2;</span></li>
				<?php endif; ?>
				<?php if ( '' !== $rooms ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Liczba pokoi', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( $rooms ); ?></span></li>
				<?php endif; ?>
				<?php if ( '' !== $floor ) : ?>
					<li><span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Piętro', 'wp-deweloper-gov-reporter' ); ?></span><span class="dgr-lokal-karta__spec-value"><?php echo esc_html( $floor ); ?></span></li>
				<?php endif; ?>
				<?php if ( '' !== $balconies_count || '' !== $balconies_area ) : ?>
					<li>
						<span class="dgr-lokal-karta__spec-label"><?php esc_html_e( 'Balkony', 'wp-deweloper-gov-reporter' ); ?></span>
						<span class="dgr-lokal-karta__spec-value">
							<?php echo esc_html( '' !== $balconies_count ? $balconies_count : '—' ); ?>
							<?php if ( '' !== $balconies_area ) : ?>
								(<?php echo esc_html( number_format( floatval( $balconies_area ), 2, ',', ' ' ) ); ?> m&sup2;)
							<?php endif; ?>
						</span>
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( $price_total > 0 || $price_shell_brutto > 0 || $price_developer_brutto > 0 ) : ?>
				<div class="dgr-lokal-karta__prices">
					<?php if ( $price_total > 0 ) : ?>
						<div class="dgr-lokal-karta__price-block">
							<span class="dgr-lokal-karta__price-label"><?php esc_html_e( 'Cena całkowita brutto', 'wp-deweloper-gov-reporter' ); ?></span>
							<span class="dgr-lokal-karta__price-value"><?php echo esc_html( $this->format_price( $price_total ) ); ?></span>
							<?php if ( $price_m2 > 0 ) : ?>
								<span class="dgr-lokal-karta__price-m2"><?php echo esc_html( number_format( $price_m2, 2, ',', ' ' ) ); ?> zł/m&sup2;</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $price_shell_brutto > 0 ) : ?>
						<div class="dgr-lokal-karta__price-block">
							<span class="dgr-lokal-karta__price-label"><?php esc_html_e( 'Stan surowy zamknięty', 'wp-deweloper-gov-reporter' ); ?></span>
							<span class="dgr-lokal-karta__price-value"><?php echo esc_html( $this->format_price( $price_shell_brutto ) ); ?></span>
							<?php if ( $shell_m2 > 0 ) : ?>
								<span class="dgr-lokal-karta__price-m2"><?php echo esc_html( number_format( $shell_m2, 2, ',', ' ' ) ); ?> zł/m&sup2;</span>
							<?php endif; ?>
							<?php if ( $show_netto && $price_shell_netto > 0 ) : ?>
								<span class="dgr-lokal-karta__price-netto"><?php echo esc_html( sprintf( __( 'netto: %s', 'wp-deweloper-gov-reporter' ), $this->format_price( $price_shell_netto ) ) ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $price_developer_brutto > 0 ) : ?>
						<div class="dgr-lokal-karta__price-block">
							<span class="dgr-lokal-karta__price-label"><?php esc_html_e( 'Deweloperski', 'wp-deweloper-gov-reporter' ); ?></span>
							<span class="dgr-lokal-karta__price-value"><?php echo esc_html( $this->format_price( $price_developer_brutto ) ); ?></span>
							<?php if ( $developer_m2 > 0 ) : ?>
								<span class="dgr-lokal-karta__price-m2"><?php echo esc_html( number_format( $developer_m2, 2, ',', ' ' ) ); ?> zł/m&sup2;</span>
							<?php endif; ?>
							<?php if ( $show_netto && $price_developer_netto > 0 ) : ?>
								<span class="dgr-lokal-karta__price-netto"><?php echo esc_html( sprintf( __( 'netto: %s', 'wp-deweloper-gov-reporter' ), $this->format_price( $price_developer_netto ) ) ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ( '' !== $garage_number || $garage_price_brutto > 0 ) || ( '' !== $storage_number || $storage_price_brutto > 0 ) ) : ?>
				<div class="dgr-lokal-karta__extras">
					<?php if ( '' !== $garage_number || $garage_price_brutto > 0 ) : ?>
						<div class="dgr-lokal-karta__extra">
							<span class="dgr-lokal-karta__extra-label"><?php esc_html_e( 'Garaż', 'wp-deweloper-gov-reporter' ); ?></span>
							<span class="dgr-lokal-karta__extra-value">
								<?php if ( '' !== $garage_number ) : ?>
									<?php echo esc_html( sprintf( __( 'nr %s', 'wp-deweloper-gov-reporter' ), $garage_number ) ); ?>
								<?php endif; ?>
								<?php if ( $garage_price_brutto > 0 ) : ?>
									<?php echo '' !== $garage_number ? ' — ' : ''; ?>
									<?php echo esc_html( $this->format_price( $garage_price_brutto ) ); ?> <?php esc_html_e( 'brutto', 'wp-deweloper-gov-reporter' ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $storage_number || $storage_price_brutto > 0 ) : ?>
						<div class="dgr-lokal-karta__extra">
							<span class="dgr-lokal-karta__extra-label"><?php esc_html_e( 'Komórka lokatorska', 'wp-deweloper-gov-reporter' ); ?></span>
							<span class="dgr-lokal-karta__extra-value">
								<?php if ( '' !== $storage_number ) : ?>
									<?php echo esc_html( sprintf( __( 'nr %s', 'wp-deweloper-gov-reporter' ), $storage_number ) ); ?>
								<?php endif; ?>
								<?php if ( $storage_price_brutto > 0 ) : ?>
									<?php echo '' !== $storage_number ? ' — ' : ''; ?>
									<?php echo esc_html( $this->format_price( $storage_price_brutto ) ); ?> <?php esc_html_e( 'brutto', 'wp-deweloper-gov-reporter' ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode [dgr_lokal_cena id="123" typ="deweloperski" vat="brutto"]
	 *
	 * Returns a single formatted price value - useful for inline use in Elementor text widgets.
	 * typ: "surowy" | "deweloperski"
	 * vat: "netto" | "brutto"
	 */
	public function render_lokal_cena( $atts ) {
		$atts = shortcode_atts( array(
			'id'  => get_the_ID(),
			'typ' => 'deweloperski',
			'vat' => 'brutto',
		), $atts, 'dgr_lokal_cena' );

		$post_id = intval( $atts['id'] );

		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$typ = sanitize_key( $atts['typ'] );
		$vat = sanitize_key( $atts['vat'] );

		$meta_key_map = array(
			'surowy_netto'        => '_dgr_unit_price_shell_netto',
			'surowy_brutto'       => '_dgr_unit_price_shell_brutto',
			'deweloperski_netto'  => '_dgr_unit_price_developer_netto',
			'deweloperski_brutto' => '_dgr_unit_price_developer_brutto',
		);

		$key = $typ . '_' . $vat;
		if ( ! isset( $meta_key_map[ $key ] ) ) {
			return '';
		}

		$value = get_post_meta( $post_id, $meta_key_map[ $key ], true );
		$formatted = $this->format_price( $value );

		if ( empty( $formatted ) ) {
			return '-';
		}

		return '<span class="dgr-lokal-cena">' . esc_html( $formatted ) . '</span>';
	}

	/**
	 * Shortcode [dgr_lokal_metraz id="123"]
	 *
	 * Returns the formatted area value.
	 */
	public function render_lokal_metraz( $atts ) {
		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
		), $atts, 'dgr_lokal_metraz' );

		$post_id = intval( $atts['id'] );

		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$area = get_post_meta( $post_id, '_dgr_unit_area', true );
		$area = floatval( $area );

		if ( $area <= 0 ) {
			return '-';
		}

		return '<span class="dgr-lokal-metraz">' . esc_html( number_format( $area, 2, ',', ' ' ) ) . ' m&sup2;</span>';
	}

	/**
	 * Shortcode [dgr_inwestycja_tabela id="123"]
	 *
	 * Renders a table with all units belonging to a given investment.
	 * Columns: Mieszkanie, Piętro, Status, Pokoje, Powierzchnia m², Cena za m², Cena całkowita, Szczegóły.
	 *
	 * Attributes:
	 *   id           – investment post ID (defaults to current post)
	 *   orderby      – sort units by: unit_id | floor | area | rooms | price (default unit_id)
	 *   order        – ASC | DESC (default ASC)
	 *   hide_sold    – yes | no (default no) – hide sold/transferred units
	 *   show_details – yes | no (default yes) – show "Szczegóły" button column
	 */
	public function render_inwestycja_tabela( $atts ) {
		wp_enqueue_style( 'dgr-frontend-css' );

		$atts = shortcode_atts( array(
			'id'           => get_the_ID(),
			'orderby'      => 'unit_id',
			'order'        => 'ASC',
			'hide_sold'    => 'no',
			'show_details' => 'yes',
		), $atts, 'dgr_inwestycja_tabela' );

		$investment_id = intval( $atts['id'] );

		if ( $investment_id <= 0 || get_post_type( $investment_id ) !== 'dgr_investment' ) {
			return '<p>' . esc_html__( 'Nie znaleziono inwestycji.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		$orderby_map = array(
			'unit_id' => array( 'meta_key' => '_dgr_unit_id',          'orderby' => 'meta_value' ),
			'floor'   => array( 'meta_key' => '_dgr_unit_floor',       'orderby' => 'meta_value_num' ),
			'area'    => array( 'meta_key' => '_dgr_unit_area',        'orderby' => 'meta_value_num' ),
			'rooms'   => array( 'meta_key' => '_dgr_unit_rooms',       'orderby' => 'meta_value_num' ),
			'price'   => array( 'meta_key' => '_dgr_unit_price_total', 'orderby' => 'meta_value_num' ),
		);
		$orderby_key = isset( $orderby_map[ $atts['orderby'] ] ) ? $atts['orderby'] : 'unit_id';
		$order       = strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$args = array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => -1,
			'meta_key'       => $orderby_map[ $orderby_key ]['meta_key'],
			'orderby'        => $orderby_map[ $orderby_key ]['orderby'],
			'order'          => $order,
			'meta_query'     => array(
				array(
					'key'   => '_dgr_unit_parent_investment',
					'value' => $investment_id,
				),
			),
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'Brak lokali w tej inwestycji.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		$status_labels = array(
			'available'             => __( 'dostępne', 'wp-deweloper-gov-reporter' ),
			'offer'                 => __( 'oferta specjalna', 'wp-deweloper-gov-reporter' ),
			'reserved'              => __( 'zarezerwowane', 'wp-deweloper-gov-reporter' ),
			'reservation_agreement' => __( 'umowa rezerwacyjna', 'wp-deweloper-gov-reporter' ),
			'developer_agreement'   => __( 'umowa deweloperska', 'wp-deweloper-gov-reporter' ),
			'sold'                  => __( 'sprzedane', 'wp-deweloper-gov-reporter' ),
			'transferred'           => __( 'przekazane', 'wp-deweloper-gov-reporter' ),
		);

		$hide_sold    = ( $atts['hide_sold'] === 'yes' );
		$show_details = ( $atts['show_details'] !== 'no' );

		ob_start();
		?>
		<div class="dgr-inwestycja-tabela-wrapper">
			<table class="dgr-table dgr-inwestycja-tabela">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Mieszkanie', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Piętro', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Status', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: center;"><?php esc_html_e( 'Pokoje', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'Powierzchnia lokalu m²', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'Cena za m²', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right;"><?php esc_html_e( 'Cena całkowita', 'wp-deweloper-gov-reporter' ); ?></th>
						<?php if ( $show_details ) : ?>
							<th style="text-align: center;"><?php esc_html_e( 'Szczegóły', 'wp-deweloper-gov-reporter' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post();
						$pid    = get_the_ID();
						$meta   = get_post_meta( $pid );
						$unit_id     = isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '';
						$floor       = isset( $meta['_dgr_unit_floor'][0] ) ? $meta['_dgr_unit_floor'][0] : '';
						$status      = isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '';
						$rooms       = isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '';
						$area        = isset( $meta['_dgr_unit_area'][0] ) ? floatval( $meta['_dgr_unit_area'][0] ) : 0;
						$price_total = isset( $meta['_dgr_unit_price_total'][0] ) ? floatval( $meta['_dgr_unit_price_total'][0] ) : 0;
						$price_m2    = isset( $meta['_dgr_unit_price_m2'][0] ) ? floatval( $meta['_dgr_unit_price_m2'][0] ) : 0;
						$custom_url  = isset( $meta['_dgr_unit_details_url'][0] ) ? $meta['_dgr_unit_details_url'][0] : '';
						$details_url = '' !== $custom_url ? $custom_url : get_permalink( $pid );

						if ( $price_m2 <= 0 && $price_total > 0 && $area > 0 ) {
							$price_m2 = $price_total / $area;
						}

						if ( $hide_sold && in_array( $status, array( 'sold', 'transferred' ), true ) ) {
							continue;
						}

						$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
						$status_class = $status ? 'status-' . sanitize_html_class( $status ) : '';
						$display_name = '' !== $unit_id ? $unit_id : get_the_title();
					?>
						<tr>
							<td><?php echo esc_html( $display_name ); ?></td>
							<td style="text-align: center;"><?php echo '' !== $floor ? esc_html( $floor ) : '—'; ?></td>
							<td style="text-align: center;">
								<?php if ( $status ) : ?>
									<span class="dgr-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td style="text-align: center;"><?php echo '' !== $rooms ? esc_html( $rooms ) : '—'; ?></td>
							<td style="text-align: right;">
								<?php echo $area > 0 ? esc_html( number_format( $area, 2, ',', ' ' ) ) . ' m²' : '—'; ?>
							</td>
							<td style="text-align: right;">
								<?php echo $price_m2 > 0 ? esc_html( number_format( $price_m2, 2, ',', ' ' ) ) . ' zł' : '—'; ?>
							</td>
							<td style="text-align: right;">
								<?php echo $price_total > 0 ? esc_html( number_format( $price_total, 2, ',', ' ' ) ) . ' zł' : '—'; ?>
							</td>
							<?php if ( $show_details ) : ?>
								<td style="text-align: center;">
									<a class="dgr-details-btn" href="<?php echo esc_url( $details_url ); ?>"><?php esc_html_e( 'Szczegóły', 'wp-deweloper-gov-reporter' ); ?></a>
								</td>
							<?php endif; ?>
						</tr>
					<?php endwhile; wp_reset_postdata(); ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}
