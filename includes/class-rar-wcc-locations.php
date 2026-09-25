<?php
/**
 * Bangladesh district → town/area dataset and resolvers.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Locations {

	private static $map = null;

	/**
	 * Legacy/alternate district spellings → dataset key.
	 * WooCommerce names Chapainawabganj simply "Nawabganj" (BD-45).
	 */
	public static function aliases() {
		return array(
			'chittagong'      => 'Chattogram',
			'comilla'         => 'Cumilla',
			'barisal'         => 'Barishal',
			'bogra'           => 'Bogura',
			'jessore'         => 'Jashore',
			'jhalokathi'      => 'Jhalokati',
			'nawabganj'       => 'Chapainababganj',
			'chapainawabganj' => 'Chapainababganj',
			'chapainababganj' => 'Chapainababganj',
			'chapai'          => 'Chapainababganj',
			'khagrachari'     => 'Khagrachhari',
			'coxsbazar'       => "Cox's Bazar",
			'maulvibazar'     => 'Moulvibazar',
			'netrokona'       => 'Netrakona',
			'jhenaidaha'      => 'Jhenaidah',
			'narshingdi'      => 'Narsingdi',
		);
	}

	/**
	 * Metro-city police-station areas that couriers ask for.
	 */
	public static function metro_areas() {
		return array(
			'Dhaka'       => array( 'Adabor', 'Badda', 'Banani', 'Bangshal', 'Bashundhara R/A', 'Bhashantek', 'Bhatara', 'Bimanbandar', 'Cantonment', 'Chawkbazar', 'Dakshinkhan', 'Darus Salam', 'Demra', 'Dhanmondi', 'Gendaria', 'Gulshan', 'Hatirjheel', 'Hazaribagh', 'Jatrabari', 'Kadamtali', 'Kafrul', 'Kalabagan', 'Kamrangirchar', 'Khilgaon', 'Khilkhet', 'Kotwali (Dhaka)', 'Lalbagh', 'Mirpur', 'Mohammadpur', 'Motijheel', 'Mugda', 'New Market', 'Pallabi', 'Paltan', 'Ramna', 'Rampura', 'Rupnagar', 'Sabujbagh', 'Shah Ali', 'Shahbagh', 'Shahjahanpur', 'Sher-e-Bangla Nagar', 'Shyampur', 'Sutrapur', 'Tejgaon', 'Tejgaon Industrial Area', 'Turag', 'Uttar Khan', 'Uttara East', 'Uttara West', 'Vatara', 'Wari' ),
			'Chattogram'  => array( 'Akbar Shah', 'Bakalia', 'Bandar', 'Bayazid Bostami', 'Chandgaon', 'Chawkbazar (Chattogram)', 'Double Mooring', 'EPZ', 'Halishahar', 'Khulshi', 'Kotwali (Chattogram)', 'Pahartali', 'Panchlaish', 'Patenga', 'Sadarghat' ),
			'Gazipur'     => array( 'Tongi', 'Gazipur City' ),
			'Narayanganj' => array( 'Fatullah', 'Siddhirganj', 'Narayanganj City' ),
		);
	}

	/**
	 * Final district → areas map used by PHP and JS.
	 */
	public static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}

		$map  = array();
		$file = RAR_WCC_DIR . 'assets/data/bd-cities.json';
		if ( is_readable( $file ) ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$map     = is_array( $decoded ) ? $decoded : array();
		}

		if ( RAR_WCC_Settings::yes( 'metro_areas' ) ) {
			foreach ( self::metro_areas() as $district => $areas ) {
				if ( isset( $map[ $district ] ) ) {
					$map[ $district ] = array_merge( $map[ $district ], $areas );
				}
			}
		}

		foreach ( self::parse_custom( RAR_WCC_Settings::get( 'custom_locations', '' ) ) as $district => $areas ) {
			$key = self::resolve( $district, $map );
			if ( '' === $key ) {
				continue;
			}
			$map[ $key ] = array_merge( $map[ $key ], $areas );
		}

		foreach ( $map as $district => $areas ) {
			$areas = array_values( array_unique( array_filter( array_map( 'trim', $areas ) ) ) );
			$head  = array_shift( $areas ); // Keep the district HQ first.
			sort( $areas, SORT_NATURAL | SORT_FLAG_CASE );
			$map[ $district ] = array_values( array_unique( array_merge( array( $head ), $areas ) ) );
		}

		self::$map = apply_filters( 'rar_wcc_city_map', $map );
		return self::$map;
	}

	public static function parse_custom( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $district, $list ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$areas                    = array_filter( array_map( 'trim', explode( ',', $list ) ) );
			if ( $district && $areas ) {
				$out[ $district ] = array_merge( $out[ $district ] ?? array(), $areas );
			}
		}
		return $out;
	}

	public static function norm( $value ) {
		$value = strtolower( remove_accents( (string) $value ) );
		$value = str_replace( '&', 'and', $value );
		return preg_replace( '/[^a-z0-9]/', '', $value );
	}

	/**
	 * Resolve any district name/state code to the dataset key.
	 */
	public static function resolve( $name, $map = null ) {
		$map = $map ?? self::map();
		if ( preg_match( '/^BD-\d+$/', (string) $name ) ) {
			$states = WC()->countries ? WC()->countries->get_states( 'BD' ) : array();
			$name   = $states[ $name ] ?? $name;
		}
		$k       = self::norm( $name );
		$aliases = self::aliases();
		if ( isset( $aliases[ $k ] ) && isset( $map[ $aliases[ $k ] ] ) ) {
			return $aliases[ $k ];
		}
		foreach ( array_keys( $map ) as $district ) {
			if ( self::norm( $district ) === $k ) {
				return $district;
			}
		}
		return '';
	}

	public static function district_label( $state_code ) {
		$states = WC()->countries ? WC()->countries->get_states( 'BD' ) : array();
		return trim( (string) ( $states[ $state_code ] ?? $state_code ) );
	}

	public static function city_in_district( $city, $state ) {
		$district = self::resolve( $state );
		if ( '' === $district ) {
			return true; // Unknown district — cannot judge.
		}
		$n = self::norm( $city );
		foreach ( self::map()[ $district ] as $area ) {
			if ( self::norm( $area ) === $n ) {
				return true;
			}
		}
		return false;
	}

	public static function count_areas() {
		return array_sum( array_map( 'count', self::map() ) );
	}
}
