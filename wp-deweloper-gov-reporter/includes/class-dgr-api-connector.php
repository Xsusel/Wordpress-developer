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
	 * Structure aligned with Rozporządzenie MRiT z 20.06.2024 (Dz.U. 2024 poz. 933).
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

				// Ustawa deweloperska (Dz.U. 2023 poz. 28) obejmuje wyłącznie
				// lokale mieszkalne i domy jednorodzinne — lokale usługowe i inne
				// są poza zakresem i nie trafiają do raportu.
				$rodzaj_meta = isset( $meta['_dgr_unit_type'][0] ) ? $meta['_dgr_unit_type'][0] : 'lokal_mieszkalny';
				if ( ! in_array( $rodzaj_meta, array( 'lokal_mieszkalny', 'dom_jednorodzinny' ), true ) ) {
					continue;
				}

				$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? intval( $meta['_dgr_unit_parent_investment'][0] ) : 0;

				$investment = array(
					'id_gov'       => '', 'name' => '', 'nip' => '',
					'wojewodztwo'  => '', 'powiat' => '', 'gmina' => '',
					'miejscowosc'  => '', 'ulica' => '', 'nr_nieruchomosci' => '',
					'kod_pocztowy' => '',
					'url_inwestycji' => '', 'url_prospektu' => '',
				);
				if ( $parent_id ) {
					$inv_meta = get_post_meta( $parent_id );
					$investment['id_gov']           = isset( $inv_meta['_dgr_investment_id'][0] ) ? $inv_meta['_dgr_investment_id'][0] : '';
					$investment['name']             = get_the_title( $parent_id );
					$investment['nip']              = isset( $inv_meta['_dgr_investment_nip'][0] ) ? $inv_meta['_dgr_investment_nip'][0] : '';
					$investment['wojewodztwo']      = isset( $inv_meta['_dgr_investment_voivodeship'][0] ) ? $inv_meta['_dgr_investment_voivodeship'][0] : '';
					$investment['powiat']           = isset( $inv_meta['_dgr_investment_county'][0] ) ? $inv_meta['_dgr_investment_county'][0] : '';
					$investment['gmina']            = isset( $inv_meta['_dgr_investment_commune'][0] ) ? $inv_meta['_dgr_investment_commune'][0] : '';
					$investment['miejscowosc']      = isset( $inv_meta['_dgr_investment_city'][0] ) ? $inv_meta['_dgr_investment_city'][0] : '';
					$investment['ulica']            = isset( $inv_meta['_dgr_investment_street'][0] ) ? $inv_meta['_dgr_investment_street'][0] : '';
					$investment['nr_nieruchomosci'] = isset( $inv_meta['_dgr_investment_building_number'][0] ) ? $inv_meta['_dgr_investment_building_number'][0] : '';
					$investment['kod_pocztowy']     = isset( $inv_meta['_dgr_investment_postal_code'][0] ) ? $inv_meta['_dgr_investment_postal_code'][0] : '';
					$investment['url_inwestycji']   = isset( $inv_meta['_dgr_investment_website_url'][0] ) ? $inv_meta['_dgr_investment_website_url'][0] : '';
					$investment['url_prospektu']    = isset( $inv_meta['_dgr_investment_prospectus_url'][0] ) ? $inv_meta['_dgr_investment_prospectus_url'][0] : '';
				}

				// Accessories: new structured fields + legacy JSON dependencies
				$przynaleznosci = array();

				if ( ! empty( $meta['_dgr_unit_garage_number'][0] ) || ! empty( $meta['_dgr_unit_garage_price_brutto'][0] ) ) {
					$przynaleznosci[] = array(
						'rodzaj'     => 'garaż',
						'oznaczenie' => isset( $meta['_dgr_unit_garage_number'][0] ) ? sanitize_text_field( $meta['_dgr_unit_garage_number'][0] ) : '',
						'cena'       => isset( $meta['_dgr_unit_garage_price_brutto'][0] ) ? floatval( $meta['_dgr_unit_garage_price_brutto'][0] ) : 0,
					);
				}
				if ( ! empty( $meta['_dgr_unit_storage_number'][0] ) || ! empty( $meta['_dgr_unit_storage_price_brutto'][0] ) ) {
					$przynaleznosci[] = array(
						'rodzaj'     => 'komórka lokatorska',
						'oznaczenie' => isset( $meta['_dgr_unit_storage_number'][0] ) ? sanitize_text_field( $meta['_dgr_unit_storage_number'][0] ) : '',
						'cena'       => isset( $meta['_dgr_unit_storage_price_brutto'][0] ) ? floatval( $meta['_dgr_unit_storage_price_brutto'][0] ) : 0,
					);
				}

				$deps_raw = isset( $meta['_dgr_unit_dependencies'][0] ) ? $meta['_dgr_unit_dependencies'][0] : '[]';
				$decoded  = json_decode( $deps_raw, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $d ) {
						if ( isset( $d['typ'] ) && isset( $d['cena'] ) ) {
							$przynaleznosci[] = array(
								'rodzaj'     => str_replace( '_', ' ', (string) $d['typ'] ),
								'oznaczenie' => isset( $d['oznaczenie'] ) ? sanitize_text_field( (string) $d['oznaczenie'] ) : '',
								'cena'       => floatval( $d['cena'] ),
							);
						}
					}
				}

				// History
				$history_raw = isset( $meta['_dgr_price_history'][0] ) ? maybe_unserialize( $meta['_dgr_price_history'][0] ) : array();
				$history = array();
				if ( is_array( $history_raw ) ) {
					foreach ( $history_raw as $entry ) {
						if ( isset( $entry['date'], $entry['price_total'], $entry['price_m2'] ) ) {
							$history[] = array(
								'date'        => sanitize_text_field( $entry['date'] ),
								'price_total' => floatval( $entry['price_total'] ),
								'price_m2'    => floatval( $entry['price_m2'] ),
							);
						}
					}
				}

				$price_total_now = isset( $meta['_dgr_unit_price_total'][0] ) ? floatval( $meta['_dgr_unit_price_total'][0] ) : 0;
				$price_m2_now    = isset( $meta['_dgr_unit_price_m2'][0] ) ? floatval( $meta['_dgr_unit_price_m2'][0] ) : 0;
				$price_total_ini = isset( $meta['_dgr_unit_price_total_initial'][0] ) ? floatval( $meta['_dgr_unit_price_total_initial'][0] ) : 0;
				if ( $price_total_ini <= 0 ) $price_total_ini = $price_total_now;
				$price_m2_ini = isset( $meta['_dgr_unit_price_m2_initial'][0] ) ? floatval( $meta['_dgr_unit_price_m2_initial'][0] ) : 0;
				if ( $price_m2_ini <= 0 ) $price_m2_ini = $price_m2_now;

				// Omnibus: najniższa cena z ostatnich 30 dni (ustawa o jawności cen).
				$lowest_30 = DGR_Price_History::get_lowest_prices_30_days( $unit->ID );
				$lowest_total_30 = ( $lowest_30 && isset( $lowest_30['price_total'] ) ) ? floatval( $lowest_30['price_total'] ) : 0;
				$lowest_m2_30    = ( $lowest_30 && isset( $lowest_30['price_m2'] ) ) ? floatval( $lowest_30['price_m2'] ) : 0;

				$all_units[] = array(
					'post_id'            => $unit->ID,
					'investment'         => $investment,
					'rodzaj'             => isset( $meta['_dgr_unit_type'][0] ) ? sanitize_text_field( $meta['_dgr_unit_type'][0] ) : 'lokal_mieszkalny',
					'lokal_id'           => isset( $meta['_dgr_unit_id'][0] ) ? sanitize_text_field( $meta['_dgr_unit_id'][0] ) : '',
					'powierzchnia_m2'    => isset( $meta['_dgr_unit_area'][0] ) ? floatval( $meta['_dgr_unit_area'][0] ) : 0,
					'pokoje'             => isset( $meta['_dgr_unit_rooms'][0] ) ? intval( $meta['_dgr_unit_rooms'][0] ) : 0,
					'kondygnacja'        => isset( $meta['_dgr_unit_floor'][0] ) ? intval( $meta['_dgr_unit_floor'][0] ) : 0,
					'cena_m2'            => $price_m2_now,
					'cena_calkowita'     => $price_total_now,
					'cena_m2_ini'        => $price_m2_ini,
					'cena_calkowita_ini' => $price_total_ini,
					'cena_m2_30d'        => $lowest_m2_30,
					'cena_calkowita_30d' => $lowest_total_30,
					'cena_sprzedazy'     => isset( $meta['_dgr_unit_price_sale_brutto'][0] ) ? floatval( $meta['_dgr_unit_price_sale_brutto'][0] ) : 0,
					'data_rozpoczecia'   => isset( $meta['_dgr_unit_sale_start_date'][0] ) ? sanitize_text_field( $meta['_dgr_unit_sale_start_date'][0] ) : '',
					'data_sprzedazy'     => isset( $meta['_dgr_unit_sale_date'][0] ) ? sanitize_text_field( $meta['_dgr_unit_sale_date'][0] ) : '',
					'data_aktualizacji'  => get_post_modified_time( 'Y-m-d', true, $unit ),
					'status'             => isset( $meta['_dgr_unit_status'][0] ) ? sanitize_text_field( $meta['_dgr_unit_status'][0] ) : 'available',
					'przynaleznosci'     => $przynaleznosci,
					'historia_cen'       => $history,
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

		// Developer-level data (settings)
		$dev_required = array(
			'dgr_developer_name'       => 'Nazwa dewelopera',
			'dgr_developer_legal_form' => 'Forma prawna',
			'dgr_developer_nip'        => 'NIP dewelopera',
			'dgr_developer_email'      => 'Email kontaktowy',
			'dgr_developer_phone'      => 'Telefon kontaktowy',
		);
		foreach ( $dev_required as $opt => $label ) {
			if ( '' === trim( (string) get_option( $opt, '' ) ) ) {
				$errors[] = sprintf( 'Ustawienia dewelopera: brak pola "%s".', $label );
			}
		}

		foreach ( $data as $unit ) {
			$label = ! empty( $unit['lokal_id'] ) ? $unit['lokal_id'] : ( 'post#' . $unit['post_id'] );

			if ( empty( $unit['lokal_id'] ) ) {
				$errors[] = sprintf( 'Lokal %s: brak numeru lokalu.', $label );
			}
			if ( ! in_array( $unit['rodzaj'], array( 'lokal_mieszkalny', 'dom_jednorodzinny' ), true ) ) {
				$errors[] = sprintf( 'Lokal %s: nieprawidłowy rodzaj.', $label );
			}

			$inv = $unit['investment'];
			$required_inv = array(
				'wojewodztwo' => 'województwo', 'powiat' => 'powiat', 'gmina' => 'gmina',
				'miejscowosc' => 'miejscowość', 'nr_nieruchomosci' => 'nr nieruchomości',
			);
			foreach ( $required_inv as $key => $name ) {
				if ( empty( $inv[ $key ] ) ) {
					$errors[] = sprintf( 'Lokal %s: brak pola inwestycji "%s".', $label, $name );
				}
			}

			if ( $unit['powierzchnia_m2'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: powierzchnia musi być > 0.', $label );
			}
			if ( $unit['cena_calkowita'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: cena całkowita musi być > 0.', $label );
			}
			if ( $unit['cena_m2'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: cena m² musi być > 0.', $label );
			}
			if ( $unit['cena_calkowita_ini'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: brak ceny początkowej (z dnia rozpoczęcia sprzedaży).', $label );
			}
			if ( empty( $unit['data_rozpoczecia'] ) ) {
				$errors[] = sprintf( 'Lokal %s: brak daty rozpoczęcia sprzedaży.', $label );
			}
			if ( 'lokal_mieszkalny' === $unit['rodzaj'] && $unit['pokoje'] <= 0 ) {
				$errors[] = sprintf( 'Lokal %s: liczba pokoi musi być > 0.', $label );
			}
			if ( in_array( $unit['status'], array( 'sold', 'transferred' ), true ) && empty( $unit['data_sprzedazy'] ) ) {
				$errors[] = sprintf( 'Lokal %s: status sprzedany/przekazany wymaga daty sprzedaży.', $label );
			}
		}

		return $errors;
	}

	/**
	 * Generate XML string from collected data.
	 *
	 * Structure aligned with Rozporządzenie MRiT z 20.06.2024 (Dz.U. 2024 poz. 933).
	 * WARNING: The official XSD published by dane.gov.pl may use different element
	 * names. Verify against the current harvester schema before registration.
	 */
	public function generate_xml( $data ) {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$legal_forms = array(
			'sp_z_oo' => 'Spółka z o.o.', 'sa' => 'Spółka akcyjna',
			'sp_j' => 'Spółka jawna', 'sp_k' => 'Spółka komandytowa',
			'sp_ka' => 'Spółka komandytowo-akcyjna', 'sp_p' => 'Spółka partnerska',
			'dzial_gosp' => 'Działalność gospodarcza', 'inna' => 'Inna',
		);
		$legal_form_key = get_option( 'dgr_developer_legal_form', '' );

		$root = $dom->createElement( 'raport' );
		$root->setAttribute( 'wersja', '1.0' );
		$root->setAttribute( 'data_generowania', wp_date( 'Y-m-d\TH:i:sP' ) );
		$dom->appendChild( $root );

		$deweloper = $dom->createElement( 'deweloper' );
		$this->append_text( $dom, $deweloper, 'nazwa', get_option( 'dgr_developer_name', '' ) );
		$this->append_text( $dom, $deweloper, 'forma_prawna', isset( $legal_forms[ $legal_form_key ] ) ? $legal_forms[ $legal_form_key ] : '' );
		$this->append_text( $dom, $deweloper, 'nip', get_option( 'dgr_developer_nip', '' ) );
		$this->append_text( $dom, $deweloper, 'regon', get_option( 'dgr_developer_regon', '' ) );
		$this->append_text( $dom, $deweloper, 'krs', get_option( 'dgr_developer_krs', '' ) );
		$this->append_text( $dom, $deweloper, 'email', get_option( 'dgr_developer_email', '' ) );
		$this->append_text( $dom, $deweloper, 'telefon', get_option( 'dgr_developer_phone', '' ) );
		$root->appendChild( $deweloper );

		// Group by investment
		$investments = array();
		foreach ( $data as $unit ) {
			$key = $unit['investment']['id_gov'] ?: ( 'post#' . ( isset( $unit['post_id'] ) ? $unit['post_id'] : '' ) );
			if ( ! isset( $investments[ $key ] ) ) {
				$investments[ $key ] = array( 'info' => $unit['investment'], 'units' => array() );
			}
			$investments[ $key ]['units'][] = $unit;
		}

		$inwestycje = $dom->createElement( 'inwestycje' );
		$root->appendChild( $inwestycje );

		$status_map = array(
			'available' => 'dostepny', 'offer' => 'dostepny',
			'reserved' => 'zarezerwowany', 'reservation_agreement' => 'umowa_rezerwacyjna',
			'developer_agreement' => 'umowa_deweloperska',
			'sold' => 'sprzedany', 'transferred' => 'przekazany',
		);

		foreach ( $investments as $inv_id => $inv ) {
			$inwestycja = $dom->createElement( 'inwestycja' );
			if ( $inv['info']['id_gov'] ) {
				$inwestycja->setAttribute( 'id_gov', $this->xml_safe_attr( $inv['info']['id_gov'] ) );
			}
			$this->append_text( $dom, $inwestycja, 'nazwa', $inv['info']['name'] );
			$this->append_text( $dom, $inwestycja, 'nip_dewelopera', $inv['info']['nip'] );

			$lok = $dom->createElement( 'lokalizacja' );
			$this->append_text( $dom, $lok, 'wojewodztwo', $inv['info']['wojewodztwo'] );
			$this->append_text( $dom, $lok, 'powiat', $inv['info']['powiat'] );
			$this->append_text( $dom, $lok, 'gmina', $inv['info']['gmina'] );
			$this->append_text( $dom, $lok, 'miejscowosc', $inv['info']['miejscowosc'] );
			$this->append_text( $dom, $lok, 'ulica', $inv['info']['ulica'] );
			$this->append_text( $dom, $lok, 'nr_nieruchomosci', $inv['info']['nr_nieruchomosci'] );
			$this->append_text( $dom, $lok, 'kod_pocztowy', $inv['info']['kod_pocztowy'] );
			$inwestycja->appendChild( $lok );

			if ( ! empty( $inv['info']['url_inwestycji'] ) ) {
				$this->append_text( $dom, $inwestycja, 'url_inwestycji', $inv['info']['url_inwestycji'] );
			}
			if ( ! empty( $inv['info']['url_prospektu'] ) ) {
				$this->append_text( $dom, $inwestycja, 'url_prospektu', $inv['info']['url_prospektu'] );
			}

			$lokale = $dom->createElement( 'lokale' );

			foreach ( $inv['units'] as $unit ) {
				$lokal = $dom->createElement( 'lokal' );
				$lokal->setAttribute( 'numer', $this->xml_safe_attr( $unit['lokal_id'] ) );
				$this->append_text( $dom, $lokal, 'rodzaj', $unit['rodzaj'] );
				$this->append_text( $dom, $lokal, 'powierzchnia_m2', number_format( $unit['powierzchnia_m2'], 2, '.', '' ) );
				if ( 'lokal_mieszkalny' === $unit['rodzaj'] ) {
					$this->append_text( $dom, $lokal, 'liczba_pokoi', (string) $unit['pokoje'] );
					$this->append_text( $dom, $lokal, 'kondygnacja', (string) $unit['kondygnacja'] );
				}

				$cm2 = $dom->createElement( 'cena_m2' );
				$this->append_text( $dom, $cm2, 'poczatkowa', number_format( $unit['cena_m2_ini'], 2, '.', '' ) );
				$this->append_text( $dom, $cm2, 'data_rozpoczecia_sprzedazy', $unit['data_rozpoczecia'] );
				$this->append_text( $dom, $cm2, 'aktualna', number_format( $unit['cena_m2'], 2, '.', '' ) );
				$this->append_text( $dom, $cm2, 'data_aktualizacji', $unit['data_aktualizacji'] );
				if ( $unit['cena_m2_30d'] > 0 ) {
					$this->append_text( $dom, $cm2, 'najnizsza_30_dni', number_format( $unit['cena_m2_30d'], 2, '.', '' ) );
				}
				$lokal->appendChild( $cm2 );

				$cc = $dom->createElement( 'cena_calkowita' );
				$this->append_text( $dom, $cc, 'poczatkowa', number_format( $unit['cena_calkowita_ini'], 2, '.', '' ) );
				$this->append_text( $dom, $cc, 'data_rozpoczecia_sprzedazy', $unit['data_rozpoczecia'] );
				$this->append_text( $dom, $cc, 'aktualna', number_format( $unit['cena_calkowita'], 2, '.', '' ) );
				$this->append_text( $dom, $cc, 'data_aktualizacji', $unit['data_aktualizacji'] );
				if ( $unit['cena_calkowita_30d'] > 0 ) {
					$this->append_text( $dom, $cc, 'najnizsza_30_dni', number_format( $unit['cena_calkowita_30d'], 2, '.', '' ) );
				}
				$lokal->appendChild( $cc );

				if ( $unit['cena_sprzedazy'] > 0 || ! empty( $unit['data_sprzedazy'] ) ) {
					$spr = $dom->createElement( 'sprzedaz' );
					if ( $unit['cena_sprzedazy'] > 0 ) {
						$this->append_text( $dom, $spr, 'cena', number_format( $unit['cena_sprzedazy'], 2, '.', '' ) );
					}
					if ( ! empty( $unit['data_sprzedazy'] ) ) {
						$this->append_text( $dom, $spr, 'data', $unit['data_sprzedazy'] );
					}
					$lokal->appendChild( $spr );
				}

				$status_pl = isset( $status_map[ $unit['status'] ] ) ? $status_map[ $unit['status'] ] : $unit['status'];
				$this->append_text( $dom, $lokal, 'status', $status_pl );

				if ( ! empty( $unit['przynaleznosci'] ) ) {
					$prz = $dom->createElement( 'przynaleznosci' );
					foreach ( $unit['przynaleznosci'] as $p ) {
						$item = $dom->createElement( 'przynaleznosc' );
						$this->append_text( $dom, $item, 'rodzaj', $p['rodzaj'] );
						if ( ! empty( $p['oznaczenie'] ) ) {
							$this->append_text( $dom, $item, 'oznaczenie', $p['oznaczenie'] );
						}
						$this->append_text( $dom, $item, 'cena_brutto', number_format( floatval( $p['cena'] ), 2, '.', '' ) );
						$prz->appendChild( $item );
					}
					$lokal->appendChild( $prz );
				}

				if ( ! empty( $unit['historia_cen'] ) ) {
					$hist = $dom->createElement( 'historia_cen' );
					foreach ( $unit['historia_cen'] as $entry ) {
						$zm = $dom->createElement( 'zmiana_ceny' );
						$this->append_text( $dom, $zm, 'data', $entry['date'] );
						$this->append_text( $dom, $zm, 'cena_m2_brutto', number_format( $entry['price_m2'], 2, '.', '' ) );
						$this->append_text( $dom, $zm, 'cena_calkowita_brutto', number_format( $entry['price_total'], 2, '.', '' ) );
						$hist->appendChild( $zm );
					}
					$lokal->appendChild( $hist );
				}

				$lokale->appendChild( $lokal );
			}

			$inwestycja->appendChild( $lokale );
			$inwestycje->appendChild( $inwestycja );
		}

		return $dom->saveXML();
	}

	private function append_text( $dom, $parent, $name, $value ) {
		$el = $dom->createElement( $name );
		$el->appendChild( $dom->createTextNode( (string) $value ) );
		$parent->appendChild( $el );
	}

	private function xml_safe_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
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

}
