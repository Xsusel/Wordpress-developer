<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGR_Price_History {

	public function init() {
		add_action( 'save_post_dgr_unit', array( $this, 'check_for_price_changes' ), 5 );
	}

	public function check_for_price_changes( $post_id ) {
		if ( ! isset( $_POST['dgr_unit_nonce'] ) || ! wp_verify_nonce( $_POST['dgr_unit_nonce'], 'dgr_save_unit_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['dgr_unit_price_total'] ) || ! isset( $_POST['dgr_unit_price_m2'] ) ) {
			return;
		}

		$new_price_total = floatval( $_POST['dgr_unit_price_total'] );
		$new_price_m2    = floatval( $_POST['dgr_unit_price_m2'] );

		// Skip invalid values
		if ( $new_price_total <= 0 || $new_price_m2 <= 0 ) {
			return;
		}

		$old_price_total = floatval( get_post_meta( $post_id, '_dgr_unit_price_total', true ) );
		$old_price_m2    = floatval( get_post_meta( $post_id, '_dgr_unit_price_m2', true ) );

		if ( abs( $old_price_total - $new_price_total ) > 0.001 || abs( $old_price_m2 - $new_price_m2 ) > 0.001 ) {
			$this->update_history( $post_id, $new_price_total, $new_price_m2 );
		}
	}

	private function update_history( $post_id, $price_total, $price_m2 ) {
		$history = get_post_meta( $post_id, '_dgr_price_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$today = wp_date( 'Y-m-d' );

		// Check if last entry was today - update instead of adding duplicate
		$last_entry = end( $history );
		$updated = false;

		if ( $last_entry && isset( $last_entry['date'] ) && $last_entry['date'] === $today ) {
			$key = key( $history );
			$history[ $key ]['price_total'] = $price_total;
			$history[ $key ]['price_m2']    = $price_m2;
			$updated = true;
		}

		if ( ! $updated ) {
			$history[] = array(
				'date'        => $today,
				'price_total' => $price_total,
				'price_m2'    => $price_m2,
			);
		}

		update_post_meta( $post_id, '_dgr_price_history', $history );
	}

	public static function get_lowest_price_30_days( $post_id ) {
		$lowest = self::get_lowest_prices_30_days( $post_id );
		return false !== $lowest ? $lowest['price_total'] : false;
	}

	/**
	 * Return both lowest cena_calkowita and lowest cena_m2 from the last 30 days.
	 * Falls back to current values if there are no entries in the window.
	 *
	 * @return array{price_total:float,price_m2:float}|false
	 */
	public static function get_lowest_prices_30_days( $post_id ) {
		$history = get_post_meta( $post_id, '_dgr_price_history', true );
		$thirty_days_ago = strtotime( '-30 days', time() );

		$lowest_total = null;
		$lowest_m2    = null;

		if ( is_array( $history ) ) {
			foreach ( $history as $entry ) {
				if ( ! isset( $entry['date'] ) ) {
					continue;
				}
				$entry_time = strtotime( $entry['date'] );
				if ( false === $entry_time || $entry_time < $thirty_days_ago ) {
					continue;
				}
				$total = isset( $entry['price_total'] ) ? floatval( $entry['price_total'] ) : 0;
				$m2    = isset( $entry['price_m2'] ) ? floatval( $entry['price_m2'] ) : 0;
				if ( $total > 0 && ( null === $lowest_total || $total < $lowest_total ) ) {
					$lowest_total = $total;
				}
				if ( $m2 > 0 && ( null === $lowest_m2 || $m2 < $lowest_m2 ) ) {
					$lowest_m2 = $m2;
				}
			}
		}

		if ( null === $lowest_total ) {
			$current_total = floatval( get_post_meta( $post_id, '_dgr_unit_price_total', true ) );
			$lowest_total  = $current_total > 0 ? $current_total : 0;
		}
		if ( null === $lowest_m2 ) {
			$current_m2 = floatval( get_post_meta( $post_id, '_dgr_unit_price_m2', true ) );
			$lowest_m2  = $current_m2 > 0 ? $current_m2 : 0;
		}

		if ( $lowest_total <= 0 && $lowest_m2 <= 0 ) {
			return false;
		}

		return array(
			'price_total' => $lowest_total,
			'price_m2'    => $lowest_m2,
		);
	}
}
