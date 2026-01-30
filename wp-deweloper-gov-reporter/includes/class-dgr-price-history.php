<?php

class DGR_Price_History {

	public function init() {
		// Run before Metaboxes save (priority 5 vs 10) to access old DB values
		add_action( 'save_post_dgr_unit', array( $this, 'check_for_price_changes' ), 5 );
	}

	public function check_for_price_changes( $post_id ) {
		// Verify Nonce (we can use the one from metaboxes as we are in the same form submission context)
		if ( ! isset( $_POST['dgr_unit_nonce'] ) || ! wp_verify_nonce( $_POST['dgr_unit_nonce'], 'dgr_save_unit_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if we have price data in POST
		if ( ! isset( $_POST['dgr_unit_price_total'] ) || ! isset( $_POST['dgr_unit_price_m2'] ) ) {
			return;
		}

		$new_price_total = sanitize_text_field( $_POST['dgr_unit_price_total'] );
		$new_price_m2    = sanitize_text_field( $_POST['dgr_unit_price_m2'] );

		// Get old values
		$old_price_total = get_post_meta( $post_id, '_dgr_unit_price_total', true );
		$old_price_m2    = get_post_meta( $post_id, '_dgr_unit_price_m2', true );

		// If this is a new post (old values empty) or values changed
		if ( $old_price_total !== $new_price_total || $old_price_m2 !== $new_price_m2 ) {
			$this->update_history( $post_id, $new_price_total, $new_price_m2 );
		}
	}

	private function update_history( $post_id, $price_total, $price_m2 ) {
		$history = get_post_meta( $post_id, '_dgr_price_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$today = current_time( 'Y-m-d' );

		// Check if last entry was today
		$last_entry = end( $history );
		$updated = false;

		if ( $last_entry && isset( $last_entry['date'] ) && $last_entry['date'] === $today ) {
			// Update today's entry
			$key = key( $history );
			$history[ $key ]['price_total'] = $price_total;
			$history[ $key ]['price_m2']    = $price_m2;
			$updated = true;
		}

		if ( ! $updated ) {
			// Append new entry
			$history[] = array(
				'date'        => $today,
				'price_total' => $price_total,
				'price_m2'    => $price_m2,
			);
		}

		update_post_meta( $post_id, '_dgr_price_history', $history );
	}

	public static function get_lowest_price_30_days( $post_id ) {
		$history = get_post_meta( $post_id, '_dgr_price_history', true );
		if ( empty( $history ) || ! is_array( $history ) ) {
			return false;
		}

		$thirty_days_ago = strtotime( '-30 days' );
		$current_time    = current_time( 'timestamp' ); // now

		$relevant_prices = array();

		foreach ( $history as $entry ) {
			$entry_time = strtotime( $entry['date'] );
			// Check if entry is within the last 30 days
			if ( $entry_time >= $thirty_days_ago && $entry_time <= $current_time ) {
				if ( isset( $entry['price_total'] ) && $entry['price_total'] > 0 ) {
					$relevant_prices[] = (float) $entry['price_total'];
				}
			}
		}

		if ( empty( $relevant_prices ) ) {
			// If no change in last 30 days, current price is technically the lowest in that period (assuming no drops)
			// But strictly speaking, Omnibus asks for lowest price *before* the reduction.
			// For this reporter, we just return the min of recorded history in that window.
			// If array empty, maybe fallback to current price?
			$current_price = get_post_meta( $post_id, '_dgr_unit_price_total', true );
			return (float) $current_price;
		}

		return min( $relevant_prices );
	}
}
