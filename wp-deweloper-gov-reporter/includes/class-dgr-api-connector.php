<?php

class DGR_API_Connector {

	public function generate_and_send_report() {
		$data = $this->generate_daily_report();
		if ( empty( $data ) ) {
			$this->log_result( 'No data to report.' );
			return;
		}

		$this->send_report( $data );
	}

	public function generate_daily_report() {
		$units = get_posts( array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		$report = array();

		foreach ( $units as $unit ) {
			$meta = get_post_meta( $unit->ID );
			$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;

			$investment_id_gov = '';
			if ( $parent_id ) {
				$investment_id_gov = get_post_meta( $parent_id, '_dgr_investment_id', true );
			}

			// Parse dependencies
			$dependencies_json = isset( $meta['_dgr_unit_dependencies'][0] ) ? $meta['_dgr_unit_dependencies'][0] : '[]';
			$dependencies = json_decode( $dependencies_json, true );
			if ( ! is_array( $dependencies ) ) {
				$dependencies = array();
			}

			// Parse history
			$history_raw = isset( $meta['_dgr_price_history'][0] ) ? maybe_unserialize( $meta['_dgr_price_history'][0] ) : array();
			$history = array();
			if ( is_array( $history_raw ) ) {
				foreach ( $history_raw as $entry ) {
					$history[] = array(
						'data'    => $entry['date'],
						'cena_m2' => (float) $entry['price_m2'],
						// Maybe add total price too if spec requires, strictly following the blog example which only showed cena_m2 in history
						'cena_calkowita' => (float) $entry['price_total'],
					);
				}
			}

			$unit_data = array(
				'inwestycja_id'   => $investment_id_gov,
				'lokal_id'        => isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '',
				'powierzchnia_m2' => isset( $meta['_dgr_unit_area'][0] ) ? (float) $meta['_dgr_unit_area'][0] : 0,
				'pokoje'          => isset( $meta['_dgr_unit_rooms'][0] ) ? (int) $meta['_dgr_unit_rooms'][0] : 0,
				'kondygnacja'     => isset( $meta['_dgr_unit_floor'][0] ) ? (int) $meta['_dgr_unit_floor'][0] : 0,
				'cena_m2'         => isset( $meta['_dgr_unit_price_m2'][0] ) ? (float) $meta['_dgr_unit_price_m2'][0] : 0,
				'cena_calkowita'  => isset( $meta['_dgr_unit_price_total'][0] ) ? (float) $meta['_dgr_unit_price_total'][0] : 0,
				'status'          => isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : 'available',
				'przynaleznosci'  => $dependencies,
				'historia_cen'    => $history,
			);

			$report[] = $unit_data;
		}

		return $report;
	}

	public function send_report( $data ) {
		$api_url = get_option( 'dgr_api_endpoint' );
		$api_key = get_option( 'dgr_api_key' );

		if ( empty( $api_url ) ) {
			$this->log_result( 'API URL not configured.' );
			return;
		}

		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key, // Assuming Bearer token auth
			),
			'body'    => json_encode( $data ),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			$this->log_result( 'Error: ' . $response->get_error_message() );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$this->log_result( "Response Code: $code. Body: $body" );
		}
	}

	private function log_result( $message ) {
		$log_entry = sprintf( "[%s] %s\n", current_time( 'mysql' ), $message );
		// Append to a log option (keeping last 1000 chars to avoid bloat) or just overwrite
		$current_log = get_option( 'dgr_api_log', '' );
		$new_log = $log_entry . substr( $current_log, 0, 5000 );
		update_option( 'dgr_api_log', $new_log );
	}
}
