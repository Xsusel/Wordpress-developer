<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGR_API_Connector {

	/**
	 * Directory name for generated files inside wp-content/uploads.
	 */
	const UPLOAD_DIR = 'dgr-gov-reports';

	/**
	 * Generate the daily XML report file and MD5 checksum.
	 * Called by WP-Cron and the manual "Generate Now" button.
	 */
	public function generate_and_send_report() {
		// Lock to prevent duplicate cron runs
		$lock = get_transient( 'dgr_report_lock' );
		if ( $lock ) {
			$this->log_result( 'Pominięto - raport jest już generowany (lock aktywny).' );
			return;
		}
		set_transient( 'dgr_report_lock', true, 5 * MINUTE_IN_SECONDS );

		try {
			$data = $this->collect_unit_data();

			if ( empty( $data ) ) {
				$this->log_result( 'Brak lokali do raportowania.' );
				delete_transient( 'dgr_report_lock' );
				return;
			}

			$validation_errors = $this->validate_data( $data );
			if ( ! empty( $validation_errors ) ) {
				$error_msg = "Błędy walidacji danych:\n" . implode( "\n", $validation_errors );
				$this->log_result( $error_msg );
				$this->send_admin_notification( 'Błąd walidacji raportu DGR', $error_msg );
				delete_transient( 'dgr_report_lock' );
				return;
			}

			$xml_content = $this->generate_xml( $data );
			$result = $this->save_files( $xml_content );

			if ( is_wp_error( $result ) ) {
				$error_msg = 'Błąd zapisu plików: ' . $result->get_error_message();
				$this->log_result( $error_msg );
				$this->send_admin_notification( 'Błąd generowania raportu DGR', $error_msg );
			} else {
				$this->log_result( 'Raport XML wygenerowany pomyślnie. Pliki zaktualizowane.' );
				update_option( 'dgr_last_successful_report', current_time( 'mysql' ), false );
			}
		} catch ( Exception $e ) {
			$this->log_result( 'Wyjątek: ' . $e->getMessage() );
			$this->send_admin_notification( 'Krytyczny błąd DGR', $e->getMessage() );
		}

		delete_transient( 'dgr_report_lock' );
	}

	/**
	 * Collect all published unit data with their investment info.
	 * Uses pagination to avoid memory exhaustion.
	 */
	public function collect_unit_data() {
		$all_units = array();
		$page = 1;
		$per_page = 100;

		do {
			$query = new WP_Query( array(
				'post_type'      => 'dgr_unit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => 'publish',
			) );

			foreach ( $query->posts as $unit ) {
				$meta = get_post_meta( $unit->ID );
				$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? intval( $meta['_dgr_unit_parent_investment'][0] ) : 0;

				$investment_data = array(
					'id_gov'  => '',
					'name'    => '',
					'address' => '',
					'nip'     => '',
				);

				if ( $parent_id ) {
					$investment_data['id_gov']  = get_post_meta( $parent_id, '_dgr_investment_id', true );
					$investment_data['name']    = get_the_title( $parent_id );
					$investment_data['address'] = get_post_meta( $parent_id, '_dgr_investment_address', true );
					$investment_data['nip']     = get_post_meta( $parent_id, '_dgr_investment_nip', true );
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
						if ( isset( $entry['date'] ) && isset( $entry['price_total'] ) && isset( $entry['price_m2'] ) ) {
							$history[] = array(
								'date'        => sanitize_text_field( $entry['date'] ),
								'price_total' => floatval( $entry['price_total'] ),
								'price_m2'    => floatval( $entry['price_m2'] ),
							);
						}
					}
				}

				$all_units[] = array(
					'post_id'         => $unit->ID,
					'investment'      => $investment_data,
					'lokal_id'        => isset( $meta['_dgr_unit_id'][0] ) ? sanitize_text_field( $meta['_dgr_unit_id'][0] ) : '',
					'powierzchnia_m2' => isset( $meta['_dgr_unit_area'][0] ) ? floatval( $meta['_dgr_unit_area'][0] ) : 0,
					'pokoje'          => isset( $meta['_dgr_unit_rooms'][0] ) ? intval( $meta['_dgr_unit_rooms'][0] ) : 0,
					'kondygnacja'     => isset( $meta['_dgr_unit_floor'][0] ) ? intval( $meta['_dgr_unit_floor'][0] ) : 0,
					'cena_m2'         => isset( $meta['_dgr_unit_price_m2'][0] ) ? floatval( $meta['_dgr_unit_price_m2'][0] ) : 0,
					'cena_calkowita'  => isset( $meta['_dgr_unit_price_total'][0] ) ? floatval( $meta['_dgr_unit_price_total'][0] ) : 0,
					'status'          => isset( $meta['_dgr_unit_status'][0] ) ? sanitize_text_field( $meta['_dgr_unit_status'][0] ) : 'available',
					'przynaleznosci'  => $dependencies,
					'historia_cen'    => $history,
				);
			}

			$max_pages = $query->max_num_pages;
			wp_reset_postdata();
			$page++;
		} while ( $page <= $max_pages );

		return $all_units;
	}

	/**
	 * Validate collected data before generating XML.
	 *
	 * @return array List of validation error messages. Empty if all valid.
	 */
	public function validate_data( $data ) {
		$errors = array();

		foreach ( $data as $index => $unit ) {
			$label = ! empty( $unit['lokal_id'] ) ? $unit['lokal_id'] : ( 'post#' . $unit['post_id'] );

			if ( empty( $unit['lokal_id'] ) ) {
				$errors[] = sprintf( 'Lokal %s: brak numeru lokalu (ID).', $label );
			}

			if ( empty( $unit['investment']['id_gov'] ) ) {
				$errors[] = sprintf( 'Lokal %s: brak ID inwestycji (gov) w powiązanej inwestycji.', $label );
			}

			if ( $unit['powierzchnia_m2'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: powierzchnia musi być większa od 0 (jest: %s).', $label, $unit['powierzchnia_m2'] );
			}

			if ( $unit['cena_calkowita'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: cena całkowita musi być większa od 0 (jest: %s).', $label, $unit['cena_calkowita'] );
			}

			if ( $unit['cena_m2'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: cena za m² musi być większa od 0 (jest: %s).', $label, $unit['cena_m2'] );
			}

			if ( $unit['pokoje'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: liczba pokoi musi być większa od 0.', $label );
			}
		}

		return $errors;
	}

	/**
	 * Generate XML string from collected data.
	 * Format compatible with dane.gov.pl harvester requirements.
	 */
	public function generate_xml( $data ) {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$developer_nip = get_option( 'dgr_developer_nip', '' );
		$developer_name = get_option( 'dgr_developer_name', '' );

		// Root element
		$root = $dom->createElement( 'raport_cenowy' );
		$root->setAttribute( 'xmlns', 'urn:dane-gov-pl:raport-cenowy:1.0' );
		$root->setAttribute( 'wersja', '1.0' );
		$root->setAttribute( 'data_generowania', wp_date( 'Y-m-d\TH:i:s' ) );
		$dom->appendChild( $root );

		// Developer info
		$deweloper = $dom->createElement( 'deweloper' );
		$deweloper->appendChild( $dom->createElement( 'nazwa', $this->xml_safe( $developer_name ) ) );
		$deweloper->appendChild( $dom->createElement( 'nip', $this->xml_safe( $developer_nip ) ) );
		$root->appendChild( $deweloper );

		// Group units by investment
		$investments = array();
		foreach ( $data as $unit ) {
			$inv_id = $unit['investment']['id_gov'];
			if ( ! isset( $investments[ $inv_id ] ) ) {
				$investments[ $inv_id ] = array(
					'info'  => $unit['investment'],
					'units' => array(),
				);
			}
			$investments[ $inv_id ]['units'][] = $unit;
		}

		foreach ( $investments as $inv_id => $inv ) {
			$inwestycja = $dom->createElement( 'inwestycja' );
			$inwestycja->setAttribute( 'id', $this->xml_safe( $inv_id ) );
			$inwestycja->appendChild( $dom->createElement( 'nazwa', $this->xml_safe( $inv['info']['name'] ) ) );
			$inwestycja->appendChild( $dom->createElement( 'adres', $this->xml_safe( $inv['info']['address'] ) ) );
			$inwestycja->appendChild( $dom->createElement( 'nip_dewelopera', $this->xml_safe( $inv['info']['nip'] ) ) );

			$lokale = $dom->createElement( 'lokale' );

			foreach ( $inv['units'] as $unit ) {
				$lokal = $dom->createElement( 'lokal' );
				$lokal->setAttribute( 'id', $this->xml_safe( $unit['lokal_id'] ) );

				$lokal->appendChild( $dom->createElement( 'powierzchnia_m2', number_format( $unit['powierzchnia_m2'], 2, '.', '' ) ) );
				$lokal->appendChild( $dom->createElement( 'liczba_pokoi', $unit['pokoje'] ) );
				$lokal->appendChild( $dom->createElement( 'kondygnacja', $unit['kondygnacja'] ) );
				$lokal->appendChild( $dom->createElement( 'cena_m2_brutto', number_format( $unit['cena_m2'], 2, '.', '' ) ) );
				$lokal->appendChild( $dom->createElement( 'cena_calkowita_brutto', number_format( $unit['cena_calkowita'], 2, '.', '' ) ) );

				// Map status to Polish
				$status_map = array(
					'available'             => 'dostepny',
					'offer'                 => 'dostepny',
					'reserved'              => 'zarezerwowany',
					'reservation_agreement' => 'umowa_rezerwacyjna',
					'developer_agreement'   => 'umowa_deweloperska',
					'sold'                  => 'sprzedany',
					'transferred'           => 'przekazany',
				);
				$status_pl = isset( $status_map[ $unit['status'] ] ) ? $status_map[ $unit['status'] ] : $unit['status'];
				$lokal->appendChild( $dom->createElement( 'status', $status_pl ) );

				// Dependencies / accessories
				if ( ! empty( $unit['przynaleznosci'] ) ) {
					$przynaleznosci = $dom->createElement( 'przynaleznosci' );
					foreach ( $unit['przynaleznosci'] as $dep ) {
						if ( isset( $dep['typ'] ) && isset( $dep['cena'] ) ) {
							$item = $dom->createElement( 'przynaleznosc' );
							$item->appendChild( $dom->createElement( 'typ', $this->xml_safe( $dep['typ'] ) ) );
							$item->appendChild( $dom->createElement( 'cena_brutto', number_format( floatval( $dep['cena'] ), 2, '.', '' ) ) );
							$przynaleznosci->appendChild( $item );
						}
					}
					$lokal->appendChild( $przynaleznosci );
				}

				// Price history
				if ( ! empty( $unit['historia_cen'] ) ) {
					$historia = $dom->createElement( 'historia_cen' );
					foreach ( $unit['historia_cen'] as $entry ) {
						$zmiana = $dom->createElement( 'zmiana_ceny' );
						$zmiana->appendChild( $dom->createElement( 'data', $entry['date'] ) );
						$zmiana->appendChild( $dom->createElement( 'cena_m2_brutto', number_format( $entry['price_m2'], 2, '.', '' ) ) );
						$zmiana->appendChild( $dom->createElement( 'cena_calkowita_brutto', number_format( $entry['price_total'], 2, '.', '' ) ) );
						$historia->appendChild( $zmiana );
					}
					$lokal->appendChild( $historia );
				}

				$lokal->appendChild( $dom->createElement( 'data_aktualizacji', wp_date( 'Y-m-d' ) ) );

				$lokale->appendChild( $lokal );
			}

			$inwestycja->appendChild( $lokale );
			$root->appendChild( $inwestycja );
		}

		return $dom->saveXML();
	}

	/**
	 * Save XML and MD5 files to uploads directory.
	 *
	 * @return true|WP_Error
	 */
	public function save_files( $xml_content ) {
		$upload_dir = wp_upload_dir();
		$dgr_dir = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_DIR;

		// Create directory if it doesn't exist
		if ( ! file_exists( $dgr_dir ) ) {
			if ( ! wp_mkdir_p( $dgr_dir ) ) {
				return new WP_Error( 'dir_create_failed', 'Nie można utworzyć katalogu: ' . $dgr_dir );
			}
		}

		// Protect directory with .htaccess (allow XML/MD5 but deny PHP)
		$htaccess = $dgr_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "<FilesMatch \"\\.(php|phtml)$\">\nDeny from all\n</FilesMatch>\n" );
		}

		// Write XML file
		$xml_path = $dgr_dir . '/oferta.xml';
		$written = file_put_contents( $xml_path, $xml_content );
		if ( false === $written ) {
			return new WP_Error( 'xml_write_failed', 'Nie można zapisać pliku XML: ' . $xml_path );
		}

		// Write MD5 checksum file
		$md5 = md5( $xml_content );
		$md5_path = $dgr_dir . '/oferta.xml.md5';
		$written = file_put_contents( $md5_path, $md5 );
		if ( false === $written ) {
			return new WP_Error( 'md5_write_failed', 'Nie można zapisać pliku MD5: ' . $md5_path );
		}

		// Store URLs in options for admin display
		$base_url = trailingslashit( $upload_dir['baseurl'] ) . self::UPLOAD_DIR;
		update_option( 'dgr_xml_url', $base_url . '/oferta.xml', false );
		update_option( 'dgr_md5_url', $base_url . '/oferta.xml.md5', false );

		return true;
	}

	/**
	 * Get public URLs for the generated files.
	 */
	public static function get_file_urls() {
		return array(
			'xml' => get_option( 'dgr_xml_url', '' ),
			'md5' => get_option( 'dgr_md5_url', '' ),
		);
	}

	/**
	 * Check if report files exist and return their info.
	 */
	public static function get_file_status() {
		$upload_dir = wp_upload_dir();
		$dgr_dir = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_DIR;
		$xml_path = $dgr_dir . '/oferta.xml';
		$md5_path = $dgr_dir . '/oferta.xml.md5';

		return array(
			'xml_exists'    => file_exists( $xml_path ),
			'md5_exists'    => file_exists( $md5_path ),
			'xml_size'      => file_exists( $xml_path ) ? filesize( $xml_path ) : 0,
			'xml_modified'  => file_exists( $xml_path ) ? wp_date( 'Y-m-d H:i:s', filemtime( $xml_path ) ) : null,
			'last_success'  => get_option( 'dgr_last_successful_report', '' ),
		);
	}

	/**
	 * Send email notification to admin on errors.
	 */
	private function send_admin_notification( $subject, $message ) {
		$admin_email = get_option( 'admin_email' );
		$site_name = get_bloginfo( 'name' );

		$full_subject = sprintf( '[%s] %s', $site_name, $subject );
		$full_message = sprintf(
			"Witaj,\n\nWtyczka WP Deweloper Gov Reporter napotkała problem:\n\n%s\n\nData: %s\nStrona: %s\n\nSprawdź ustawienia wtyczki w panelu WP.",
			$message,
			wp_date( 'Y-m-d H:i:s' ),
			home_url()
		);

		wp_mail( $admin_email, $full_subject, $full_message );
	}

	/**
	 * Log a result message.
	 */
	private function log_result( $message ) {
		$log_entry = sprintf( "[%s] %s\n", wp_date( 'Y-m-d H:i:s' ), $message );
		$current_log = get_option( 'dgr_api_log', '' );
		$new_log = $log_entry . substr( $current_log, 0, 5000 );
		update_option( 'dgr_api_log', $new_log, false );
	}

	/**
	 * Sanitize a string for safe inclusion in XML.
	 */
	private function xml_safe( $string ) {
		return htmlspecialchars( (string) $string, ENT_XML1, 'UTF-8' );
	}
}
