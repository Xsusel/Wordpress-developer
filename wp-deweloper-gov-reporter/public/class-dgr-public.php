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
}
