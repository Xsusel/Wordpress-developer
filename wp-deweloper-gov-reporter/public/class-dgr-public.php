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
	}

	public function render_price_history( $atts ) {
		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
		), $atts, 'dgr_price_history' );

		$post_id = intval( $atts['id'] );
		$history = get_post_meta( $post_id, '_dgr_price_history', true );

		if ( empty( $history ) || ! is_array( $history ) ) {
			return '<p>' . __( 'Brak historii cen.', 'wp-deweloper-gov-reporter' ) . '</p>';
		}

		// Sort by date desc
		usort( $history, function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		});

		ob_start();
		?>
		<div class="dgr-price-history">
			<h3><?php _e( 'Historia Cen', 'wp-deweloper-gov-reporter' ); ?></h3>
			<table class="dgr-table" style="width:100%; border-collapse: collapse;">
				<thead>
					<tr style="border-bottom: 1px solid #ddd;">
						<th style="text-align: left; padding: 8px;"><?php _e( 'Data', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php _e( 'Cena Całkowita', 'wp-deweloper-gov-reporter' ); ?></th>
						<th style="text-align: right; padding: 8px;"><?php _e( 'Cena za m²', 'wp-deweloper-gov-reporter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $entry ) : ?>
						<tr style="border-bottom: 1px solid #eee;">
							<td style="padding: 8px;"><?php echo esc_html( $entry['date'] ); ?></td>
							<td style="text-align: right; padding: 8px;"><?php echo esc_html( number_format( floatval( $entry['price_total'] ), 2, ',', ' ' ) ); ?> zł</td>
							<td style="text-align: right; padding: 8px;"><?php echo esc_html( number_format( floatval( $entry['price_m2'] ), 2, ',', ' ' ) ); ?> zł</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_unit_details( $atts ) {
		$atts = shortcode_atts( array(
			'id' => get_the_ID(),
		), $atts, 'dgr_unit_details' );

		$post_id = intval( $atts['id'] );

		// Ensure it's a unit
		if ( get_post_type( $post_id ) !== 'dgr_unit' ) {
			return '';
		}

		$meta = get_post_meta( $post_id );

		// Parent Investment
		$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
		$investment_name = $parent_id ? get_the_title( $parent_id ) : '-';

		$unit_id     = isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '-';
		$area        = isset( $meta['_dgr_unit_area'][0] ) ? $meta['_dgr_unit_area'][0] : '-';
		$rooms       = isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '-';
		$floor       = isset( $meta['_dgr_unit_floor'][0] ) ? $meta['_dgr_unit_floor'][0] : '-';
		$status      = isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '-';
		$price_total = isset( $meta['_dgr_unit_price_total'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_total'][0] ), 2, ',', ' ' ) . ' zł' : '-';
		$price_m2    = isset( $meta['_dgr_unit_price_m2'][0] ) ? number_format( floatval( $meta['_dgr_unit_price_m2'][0] ), 2, ',', ' ' ) . ' zł' : '-';

		ob_start();
		?>
		<div class="dgr-unit-details">
			<h3><?php _e( 'Szczegóły Lokalu', 'wp-deweloper-gov-reporter' ); ?></h3>
			<ul class="dgr-list">
				<li><strong><?php _e( 'Inwestycja:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $investment_name ); ?></li>
				<li><strong><?php _e( 'Numer Lokalu:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $unit_id ); ?></li>
				<li><strong><?php _e( 'Powierzchnia:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $area ); ?> m²</li>
				<li><strong><?php _e( 'Pokoje:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $rooms ); ?></li>
				<li><strong><?php _e( 'Piętro:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $floor ); ?></li>
				<li><strong><?php _e( 'Status:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( ucfirst( $status ) ); ?></li>
				<li><strong><?php _e( 'Cena Całkowita:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_total ); ?></li>
				<li><strong><?php _e( 'Cena za m²:', 'wp-deweloper-gov-reporter' ); ?></strong> <?php echo esc_html( $price_m2 ); ?></li>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}
}
