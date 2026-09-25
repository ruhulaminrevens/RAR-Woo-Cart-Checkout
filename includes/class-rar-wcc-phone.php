<?php
/**
 * Bangladesh mobile number helpers.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Phone {

	/**
	 * Convert Bangla/Arabic-Indic digits to ASCII.
	 */
	public static function ascii_digits( $value ) {
		$map = array(
			'০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		return strtr( (string) $value, $map );
	}

	/**
	 * Return the 11-digit local form (01XXXXXXXXX) or '' if not a valid BD mobile.
	 */
	public static function local( $value ) {
		$digits = preg_replace( '/\D+/', '', self::ascii_digits( $value ) );
		if ( '' === $digits ) {
			return '';
		}
		if ( str_starts_with( $digits, '00880' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( str_starts_with( $digits, '880' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = '0' . $digits;
		}
		return preg_match( '/^01[3-9]\d{8}$/', $digits ) ? $digits : '';
	}

	public static function is_valid( $value ) {
		return '' !== self::local( $value );
	}

	/**
	 * Format for storage according to the "Save phone as" setting.
	 */
	public static function format( $value, $mode = null ) {
		$mode  = $mode ?? RAR_WCC_Settings::get( 'phone_normalize', 'local' );
		$local = self::local( $value );
		if ( '' === $local || 'none' === $mode ) {
			return trim( (string) $value );
		}
		return 'intl' === $mode ? '+88' . $local : $local;
	}

	/**
	 * All stored variants of a number, for lookups across legacy formats.
	 */
	public static function variants( $value ) {
		$local = self::local( $value );
		if ( '' === $local ) {
			$raw = trim( (string) $value );
			return '' === $raw ? array() : array( $raw );
		}
		$tail = substr( $local, 1 );
		return array_values(
			array_unique(
				array(
					$local,
					'+88' . $local,
					'88' . $local,
					'+880 ' . $tail,
					'+880' . $tail,
					substr( $local, 0, 5 ) . '-' . substr( $local, 5 ),
					substr( $local, 0, 5 ) . ' ' . substr( $local, 5 ),
				)
			)
		);
	}

	public static function operator( $value ) {
		$local = self::local( $value );
		if ( '' === $local ) {
			return '';
		}
		$ops = array(
			'013' => 'Grameenphone',
			'017' => 'Grameenphone',
			'014' => 'Banglalink',
			'019' => 'Banglalink',
			'016' => 'Airtel',
			'018' => 'Robi',
			'015' => 'Teletalk',
		);
		return $ops[ substr( $local, 0, 3 ) ] ?? '';
	}

	/**
	 * wa.me link target (8801XXXXXXXXX).
	 */
	public static function whatsapp( $value ) {
		$local = self::local( $value );
		return '' === $local ? '' : '88' . $local;
	}
}
