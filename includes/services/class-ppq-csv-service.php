<?php
/**
 * CSV Service
 *
 * Builds CSV exports the way the suite does (Educator's report exports):
 * a UTF-8 byte order mark so Excel reads accents, every cell checked for
 * spreadsheet formula injection, and a { filename, content, type } payload
 * that the React export button turns into a download.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Service class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_CSV_Service {

	/**
	 * Build CSV content from a header row and data rows.
	 *
	 * Every cell, headers included, goes through safe_cell(). Output follows
	 * RFC 4180 (quotes doubled, no escape character), which also avoids PHP
	 * 8.4's deprecation of fputcsv()'s default escape argument.
	 *
	 * @since 3.2.0
	 *
	 * @param array $headers Header row.
	 * @param array $rows    Data rows (arrays of cells).
	 * @return string CSV content, starting with a UTF-8 BOM.
	 */
	public static function build( $headers, $rows ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream for fputcsv().
		$output = fopen( 'php://temp', 'r+' );

		fputcsv( $output, array_map( [ __CLASS__, 'safe_cell' ], array_values( (array) $headers ) ), ',', '"', '' );

		foreach ( $rows as $row ) {
			fputcsv( $output, array_map( [ __CLASS__, 'safe_cell' ], array_values( (array) $row ) ), ',', '"', '' );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the in-memory stream.
		fclose( $output );

		// UTF-8 BOM so Excel reads non-Latin characters correctly.
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Neutralize spreadsheet formula injection in a cell.
	 *
	 * Text starting with =, +, -, @, a tab or a carriage return gets a
	 * leading apostrophe so spreadsheets treat it as text (OWASP CSV
	 * injection guidance). Numbers are left alone: a number can't carry a
	 * formula, and prefixing one would turn it into text.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $value Cell value.
	 * @return string Safe cell value.
	 */
	public static function safe_cell( $value ) {
		if ( null === $value || is_bool( $value ) ) {
			$value = $value ? '1' : '';
		}

		$value = (string) $value;

		if ( '' === $value || is_numeric( $value ) ) {
			return $value;
		}

		if ( in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Build an export filename: {report}-{context}-{Y-m-d}.csv.
	 *
	 * Report and context are reduced to lowercase letters, digits and
	 * hyphens; an empty context is left out. The date is the site's.
	 *
	 * @since 3.2.0
	 *
	 * @param string $report  Report slug (e.g. 'attempts').
	 * @param string $context Selection (e.g. a quiz slug), or ''.
	 * @return string Filename.
	 */
	public static function filename( $report, $context = '' ) {
		$parts = array_filter( [ self::slug( $report ), self::slug( $context ) ] );

		return implode( '-', $parts ) . '-' . wp_date( 'Y-m-d' ) . '.csv';
	}

	/**
	 * Reduce a value to lowercase letters, digits and single hyphens.
	 *
	 * @since 3.2.0
	 *
	 * @param string $value Value.
	 * @return string Slug ('' when nothing usable is left).
	 */
	public static function slug( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9-]+/', '-', $value );

		return trim( preg_replace( '/-{2,}/', '-', $value ), '-' );
	}

	/**
	 * The export payload the React button downloads.
	 *
	 * @since 3.2.0
	 *
	 * @param string $filename Download filename.
	 * @param string $content  CSV content.
	 * @return array { filename, content, type }
	 */
	public static function response( $filename, $content ) {
		return [
			'filename' => $filename,
			'content'  => $content,
			'type'     => 'text/csv',
		];
	}
}
