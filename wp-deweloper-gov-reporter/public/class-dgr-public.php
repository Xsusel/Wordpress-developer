<?php

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		// Enqueue Chart.js from CDN
		wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
	}

	public function render_price_history( $atts ) {
		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
			'view' => 'both', // table, chart, both
		), $atts, 'dgr_price_history' );

		$post_id = intval( $atts['id'] );
		$history = get_post_meta( $post_id, '_dgr_price_history', true );

		if ( empty( $history ) || ! is_array( $history ) ) {
			return '<p>' . __( 'Brak historii cen.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		// Filter for last 12 months (or 365 days)
		$one_year_ago = strtotime( '-1 year' );
		$filtered_history = array_filter( $history, function( $entry ) use ( $one_year_ago ) {
			return strtotime( $entry['date'] ) >= $one_year_ago;
		});

		// If no history in last year, maybe show message or just show nothing?
		// User asked for "last year", so we stick to filtered.
		if ( empty( $filtered_history ) ) {
			return '<p>' . __( 'Brak historii cen w ostatnim roku.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		// Sort by date asc for Chart, desc for Table
		usort( $filtered_history, function( $a, $b ) {
			return strtotime( $a['date'] ) - strtotime( $b['date'] );
		});

		// Prepare data for Chart
		$labels = array();
		$data_total = array();
		$data_m2 = array();
		foreach ( $filtered_history as $entry ) {
			$labels[] = $entry['date'];
			$data_total[] = (float) $entry['price_total'];
			$data_m2[] = (float) $entry['price_m2'];
		}

		// Reverse for table
		$table_history = array_reverse( $filtered_history );

		// Enqueue Chart.js if needed
		if ( $atts['view'] === 'chart' || $atts['view'] === 'both' ) {
			wp_enqueue_script( 'chartjs' );
		}

		ob_start();
		echo '<div class="dgr-price-history-wrapper">';

		// Render Chart
		if ( $atts['view'] === 'chart' || $atts['view'] === 'both' ) {
			$chart_id = 'dgrChart_' . uniqid();
			?>
			<div class="dgr-chart-container" style="position: relative; height:300px; width:100%; margin-bottom: 20px;">
				<canvas id="<?php echo esc_attr( $chart_id ); ?>"></canvas>
			</div>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					const ctx = document.getElementById('<?php echo esc_js( $chart_id ); ?>');
					new Chart(ctx, {
						type: 'line',
						data: {
							labels: <?php echo json_encode( $labels ); ?>,
							datasets: [{
								label: '<?php _e( 'Cena Całkowita (PLN)', 'wp-deweloper-gov-reporter' ); ?>',
								data: <?php echo json_encode( $data_total ); ?>,
								borderColor: 'rgba(75, 192, 192, 1)',
								borderWidth: 2,
								fill: false,
								yAxisID: 'y'
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								y: {
									beginAtZero: false,
									ticks: {
										callback: function(value, index, values) {
											return value + ' zł';
										}
									}
								}
							}
						}
					});
				});
			</script>
			<?php
		}

		// Render Table
		if ( $atts['view'] === 'table' || $atts['view'] === 'both' ) {
			?>
			<h3><?php _e( 'Historia Cen (Ostatnie 12 miesięcy)', 'wp-deweloper-gov-reporter' ); ?></h3>
			<table class="dgr-table" style="width:100%; border-collapse: collapse;">
				<thead>
					<tr style="border-bottom: 1px solid #ddd;">
						<th style="text-align: left; padding: 8px;"><?php _e( 'Data', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php _e( 'Cena Całkowita', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php _e( 'Cena za m²', 'wp-deweloper-gov-reporter' ); ?></th>
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

	public function render_unit_details( $atts ) {
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
		);
		$atts = shortcode_atts( $defaults, $atts, 'dgr_unit_details' );

		$post_id = intval( $atts['id'] );

		// Ensure it's a unit
		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$meta = get_post_meta( $post_id );

		ob_start();
		?>
		<div class="dgr-unit-details">
			<h3><?php _e( 'Szczegóły Lokalu', 'wp-deweloper-gov-reporter' ); ?></h3>
			<ul class="dgr-list">
				<?php if ( $atts['show_investment'] === 'yes' ) :
					$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
					$investment_name = $parent_id ? get_the_title( $parent_id ) : '-';
				?>
					<li><strong><?php _e( 'Inwestycja:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $investment_name ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_unit_id'] === 'yes' ) :
					$unit_id = isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '-';
				?>
					<li><strong><?php _e( 'Numer Lokalu:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $unit_id ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_area'] === 'yes' ) :
					$area = isset( $meta['_dgr_unit_area'][0] ) ? $meta['_dgr_unit_area'][0] : '-';
				?>
					<li><strong><?php _e( 'Powierzchnia:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $area ); ?> m²</li>
				<?php endif; ?>

				<?php if ( $atts['show_rooms'] === 'yes' ) :
					$rooms = isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '-';
				?>
					<li><strong><?php _e( 'Pokoje:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $rooms ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_floor'] === 'yes' ) :
					$floor = isset( $meta['_dgr_unit_floor'][0] ) ? $meta['_dgr_unit_floor'][0] : '-';
				?>
					<li><strong><?php _e( 'Piętro:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $floor ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_status'] === 'yes' ) :
					$status = isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '-';
				?>
					<li><strong><?php _e( 'Status:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( ucfirst( $status ) ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_price_total'] === 'yes' ) :
					$price_total = isset( $meta['_dgr_unit_price_total'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_total'][0] ), 2, ',', ' ' ) . ' zł' : '-';
				?>
					<li><strong><?php _e( 'Cena Całkowita:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_total ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_price_m2'] === 'yes' ) :
					$price_m2 = isset( $meta['_dgr_unit_price_m2'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_m2'][0] ), 2, ',', ' ' ) . ' zł' : '-';
				?>
					<li><strong><?php _e( 'Cena za m²:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_m2 ); ?></li>
				<?php endif; ?>

				<?php if ( $atts['show_omnibus'] === 'yes' && method_exists( 'DGR_Price_History', 'get_lowest_price_30_days' ) ) :
					$lowest_price = DGR_Price_History::get_lowest_price_30_days( $post_id );
					if ( $lowest_price ) :
				?>
					<li><strong><?php _e( 'Najniższa cena (30 dni):', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( number_format( floatval( $lowest_price ), 2, ',', ' ' ) ); ?> zł</li>
				<?php endif; endif; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}
}
