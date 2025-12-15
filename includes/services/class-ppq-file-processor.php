<?php
/**
 * File Processor Service
 *
 * Handles file uploads and text extraction for AI question generation.
 * Supports PDF and Word document text extraction with multiple fallback methods.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File Processor class
 *
 * Provides file upload handling and text extraction functionality.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_File_Processor {

	/**
	 * Maximum file size in bytes (50MB for large PDFs)
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 52428800;

	/**
	 * Allowed MIME types
	 *
	 * @var array
	 */
	const ALLOWED_MIME_TYPES = [
		'application/pdf' => 'pdf',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
	];

	/**
	 * Temporary files to clean up
	 *
	 * @var array
	 */
	private $temp_files = [];

	/**
	 * Constructor
	 *
	 * Registers cleanup on shutdown.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Register cleanup handler
		register_shutdown_function( [ $this, 'cleanup_temp_files' ] );
	}

	/**
	 * Process uploaded file
	 *
	 * Handles file upload validation and text extraction.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file $_FILES array element.
	 * @return array|WP_Error Extracted text and metadata or WP_Error.
	 */
	public function process_upload( $file ) {
		// Validate file upload
		$validation = $this->validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get MIME type
		$mime_type = $this->get_mime_type( $file['tmp_name'] );
		if ( is_wp_error( $mime_type ) ) {
			return $mime_type;
		}

		// Extract text based on file type
		$file_type = self::ALLOWED_MIME_TYPES[ $mime_type ];

		switch ( $file_type ) {
			case 'pdf':
				$result = $this->extract_pdf_text( $file['tmp_name'] );
				break;

			case 'docx':
				$result = $this->extract_docx_text( $file['tmp_name'] );
				break;

			default:
				return new WP_Error(
					'ppq_unsupported_type',
					__( 'Unsupported file type.', 'pressprimer-quiz' )
				);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'text'      => $result,
			'file_name' => sanitize_file_name( $file['name'] ),
			'file_type' => $file_type,
			'file_size' => $file['size'],
			'mime_type' => $mime_type,
		];
	}

	/**
	 * Validate file upload
	 *
	 * Checks upload for errors, size, type, and security concerns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file $_FILES array element.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_upload( $file ) {
		// Check for upload errors
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new WP_Error(
				'ppq_upload_error',
				__( 'Invalid file upload.', 'pressprimer-quiz' )
			);
		}

		switch ( $file['error'] ) {
			case UPLOAD_ERR_OK:
				break;

			case UPLOAD_ERR_NO_FILE:
				return new WP_Error(
					'ppq_no_file',
					__( 'No file was uploaded.', 'pressprimer-quiz' )
				);

			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return new WP_Error(
					'ppq_file_too_large',
					__( 'The uploaded file exceeds the maximum allowed size.', 'pressprimer-quiz' )
				);

			default:
				return new WP_Error(
					'ppq_upload_error',
					__( 'An error occurred during file upload.', 'pressprimer-quiz' )
				);
		}

		// Check file size
		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'ppq_file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					__( 'File size exceeds the maximum limit of %s.', 'pressprimer-quiz' ),
					size_format( self::MAX_FILE_SIZE )
				)
			);
		}

		// Check if file exists
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error(
				'ppq_invalid_file',
				__( 'Invalid file upload.', 'pressprimer-quiz' )
			);
		}

		// Check for dangerous extensions (double extension attacks like file.pdf.php)
		$filename = isset( $file['name'] ) ? basename( $file['name'] ) : '';
		if ( preg_match( '/\.(php|phtml|php3|php4|php5|php7|php8|phar|exe|sh|bat|cmd|com|scr|msi|vbs|js|jar|cgi|pl|py|rb)[.\s]*$/i', $filename ) ) {
			return new WP_Error(
				'ppq_dangerous_extension',
				__( 'File contains a potentially dangerous extension.', 'pressprimer-quiz' )
			);
		}

		// Verify file content matches expected type using magic bytes
		$content_validation = $this->validate_file_content( $file['tmp_name'] );
		if ( is_wp_error( $content_validation ) ) {
			return $content_validation;
		}

		return true;
	}

	/**
	 * Validate file content using magic bytes
	 *
	 * Verifies that file content matches an allowed type by checking
	 * the file signature (magic bytes), not just the extension.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to uploaded file.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_file_content( $file_path ) {
		// Use finfo to detect actual content type from magic bytes
		if ( ! function_exists( 'finfo_open' ) ) {
			// If finfo is not available, we'll rely on get_mime_type() later
			return true;
		}

		$finfo         = finfo_open( FILEINFO_MIME_TYPE );
		$detected_type = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );

		if ( false === $detected_type ) {
			return new WP_Error(
				'ppq_content_detection_failed',
				__( 'Unable to verify file content.', 'pressprimer-quiz' )
			);
		}

		// Check if detected type is in our allowed list
		if ( ! isset( self::ALLOWED_MIME_TYPES[ $detected_type ] ) ) {
			return new WP_Error(
				'ppq_content_type_mismatch',
				__( 'File content does not match an allowed document type.', 'pressprimer-quiz' )
			);
		}

		return true;
	}

	/**
	 * Get MIME type
	 *
	 * Determines the MIME type of a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to file.
	 * @return string|WP_Error MIME type or WP_Error.
	 */
	private function get_mime_type( $file_path ) {
		// Use WordPress function first
		$wp_check = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );

		if ( ! empty( $wp_check['type'] ) ) {
			$mime_type = $wp_check['type'];
		} elseif ( function_exists( 'finfo_open' ) ) {
			// Fallback to finfo
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );
		} elseif ( function_exists( 'mime_content_type' ) ) {
			// Fallback to mime_content_type
			$mime_type = mime_content_type( $file_path );
		} else {
			return new WP_Error(
				'ppq_mime_detection_failed',
				__( 'Unable to determine file type.', 'pressprimer-quiz' )
			);
		}

		// Validate MIME type
		if ( ! isset( self::ALLOWED_MIME_TYPES[ $mime_type ] ) ) {
			return new WP_Error(
				'ppq_invalid_mime_type',
				sprintf(
					/* translators: %s: file types */
					__( 'Invalid file type. Allowed types: %s', 'pressprimer-quiz' ),
					'PDF, DOCX'
				)
			);
		}

		return $mime_type;
	}

	/**
	 * Extract text from PDF
	 *
	 * Attempts multiple methods to extract text from PDF files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	public function extract_pdf_text( $file_path ) {
		$text = '';

		// Method 1: Try Smalot\PdfParser if available
		if ( class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			$text = $this->extract_pdf_with_smalot( $file_path );
			if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
				return $text;
			}
		}

		// Method 2: Try pdftotext command line tool
		$text = $this->extract_pdf_with_pdftotext( $file_path );
		if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
			// Also filter pdftotext output
			$text = $this->filter_garbage_text( $text );
			if ( ! empty( trim( $text ) ) ) {
				return $text;
			}
		}

		// Do NOT fall back to basic extraction - it produces too much garbage
		// If smalot and pdftotext both failed, show a helpful error
		return new WP_Error(
			'ppq_pdf_extraction_failed',
			__( 'Unable to extract readable text from this PDF. This often happens with published books or documents that use embedded fonts. Please use the "Paste Text" tab to copy and paste the content directly from your PDF viewer.', 'pressprimer-quiz' )
		);
	}

	/**
	 * Extract PDF text using Smalot\PdfParser
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_pdf_with_smalot( $file_path ) {
		try {
			// Configure parser to exclude image binary data from text extraction
			$config = new \Smalot\PdfParser\Config();
			$config->setRetainImageContent( false );

			$parser = new \Smalot\PdfParser\Parser( [], $config );
			$pdf    = $parser->parseFile( $file_path );
			$text   = $pdf->getText();

			// Filter out garbage/binary content that can occur with custom font encodings
			$text = $this->filter_garbage_text( $text );

			return $text;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'ppq_pdf_parser_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Filter out garbage/binary content from extracted text
	 *
	 * PDFs with custom font encodings can produce unreadable characters.
	 * This method filters out lines that appear to be garbage by checking
	 * for actual recognizable words with vowels (real English words have vowels).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw extracted text.
	 * @return string Filtered text.
	 */
	private function filter_garbage_text( $text ) {
		// Split into lines for analysis
		$lines            = explode( "\n", $text );
		$filtered         = [];
		$total_real_words = 0;
		$total_lines      = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$line_length = mb_strlen( $line );

			// Count words that look like real English (contain at least one vowel)
			// This filters out garbage like "bbLb", "CHsss", "qY33" etc.
			preg_match_all( '/\b[a-zA-Z]{2,}\b/', $line, $words );
			$real_word_count = 0;
			if ( ! empty( $words[0] ) ) {
				foreach ( $words[0] as $word ) {
					// Real English words contain vowels (including 'y' as vowel)
					if ( preg_match( '/[aeiouyAEIOUY]/', $word ) ) {
						++$real_word_count;
					}
				}
			}

			// Skip lines that are long but have very few real words
			if ( $line_length > 30 && $real_word_count < 3 ) {
				continue;
			}

			// Skip lines with high density of special characters (>25%)
			$special_chars = preg_match_all( '/[^a-zA-Z0-9\s\.,!?\'\"-]/', $line );
			if ( $line_length > 15 && ( $special_chars / $line_length ) > 0.25 ) {
				continue;
			}

			// Skip lines that look like encoded data
			if ( preg_match( '/[{}\[\]|\\\\<>~`^@#$%&*+=]{3,}/', $line ) ) {
				continue;
			}

			// Skip lines with excessive repeated characters
			if ( preg_match( '/(.)\1{4,}/', $line ) ) {
				continue;
			}

			// Skip lines that are mostly uppercase consonants (common in garbage)
			$uppercase_consonants = preg_match_all( '/[BCDFGHJKLMNPQRSTVWXZ]/', $line );
			if ( $line_length > 20 && ( $uppercase_consonants / $line_length ) > 0.3 ) {
				continue;
			}

			$filtered[]        = $line;
			$total_real_words += $real_word_count;
			++$total_lines;
		}

		$result = implode( "\n", $filtered );

		// If we have very few real words overall, the extraction likely failed
		if ( $total_lines > 5 && ( $total_real_words / $total_lines ) < 2 ) {
			return '';
		}

		// If total real word count is too low for the amount of text, reject it
		if ( strlen( $result ) > 300 && $total_real_words < 30 ) {
			return '';
		}

		return $result;
	}

	/**
	 * Extract PDF text using pdftotext command
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_pdf_with_pdftotext( $file_path ) {
		// Check if pdftotext is available
		$pdftotext_path = $this->find_executable( 'pdftotext' );

		if ( ! $pdftotext_path ) {
			return new WP_Error(
				'ppq_pdftotext_not_found',
				__( 'pdftotext command not available.', 'pressprimer-quiz' )
			);
		}

		// Create temp output file
		$temp_output        = wp_tempnam( 'ppq_pdf_' );
		$this->temp_files[] = $temp_output;

		// Build command with proper escaping
		$command = sprintf(
			'%s -layout %s %s 2>&1',
			escapeshellcmd( $pdftotext_path ),
			escapeshellarg( $file_path ),
			escapeshellarg( $temp_output )
		);

		// Execute command
		$output     = [];
		$return_var = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $command, $output, $return_var );

		if ( 0 !== $return_var ) {
			return new WP_Error(
				'ppq_pdftotext_error',
				__( 'pdftotext command failed.', 'pressprimer-quiz' )
			);
		}

		// Read the output file
		if ( ! file_exists( $temp_output ) ) {
			return new WP_Error(
				'ppq_pdftotext_no_output',
				__( 'pdftotext did not produce output.', 'pressprimer-quiz' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$text = file_get_contents( $temp_output );

		// Clean up temp file immediately
		wp_delete_file( $temp_output );

		return $text;
	}

	/**
	 * Basic PDF text extraction
	 *
	 * Attempts basic text extraction from PDF without external libraries.
	 * Uses multiple techniques to handle various PDF formats.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_pdf_basic( $file_path ) {
		// Read file content
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			return new WP_Error(
				'ppq_file_read_error',
				__( 'Unable to read PDF file.', 'pressprimer-quiz' )
			);
		}

		$text = '';

		// Try to extract text between stream and endstream
		preg_match_all( '/stream\s*(.+?)\s*endstream/s', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $stream ) {
				// Try multiple decompression methods
				$decoded = $this->decode_pdf_stream( $stream );

				// Extract text using various methods
				$stream_text = $this->extract_text_from_stream( $decoded );
				if ( ! empty( $stream_text ) ) {
					$text .= $stream_text . ' ';
				}
			}
		}

		// Also try to find text in content streams directly (for simpler PDFs)
		$direct_text = $this->extract_direct_pdf_text( $content );
		if ( ! empty( $direct_text ) ) {
			$text .= $direct_text;
		}

		// Clean up extracted text
		$text = $this->clean_extracted_text( $text );

		if ( empty( trim( $text ) ) ) {
			return new WP_Error(
				'ppq_no_text_found',
				__( 'No text could be extracted from the PDF.', 'pressprimer-quiz' )
			);
		}

		return $text;
	}

	/**
	 * Decode PDF stream with multiple methods
	 *
	 * @since 1.0.0
	 *
	 * @param string $stream Raw stream data.
	 * @return string Decoded stream data.
	 */
	private function decode_pdf_stream( $stream ) {
		// Try FlateDecode (most common)
		$decoded = @gzuncompress( $stream );
		if ( false !== $decoded ) {
			return $decoded;
		}

		// Try with zlib header
		$decoded = @gzinflate( $stream );
		if ( false !== $decoded ) {
			return $decoded;
		}

		// Try removing potential header bytes and decompress
		if ( strlen( $stream ) > 2 ) {
			$decoded = @gzinflate( substr( $stream, 2 ) );
			if ( false !== $decoded ) {
				return $decoded;
			}
		}

		// Try ASCII85 decode
		if ( preg_match( '/^[A-Za-z0-9!#$%&()*+,\-./:;<=>?@\[\]^_`{|}~\s]+~>$/s', $stream ) ) {
			$decoded = $this->ascii85_decode( $stream );
			if ( false !== $decoded ) {
				return $decoded;
			}
		}

		// Return original if no decompression worked
		return $stream;
	}

	/**
	 * Extract text from decoded PDF stream
	 *
	 * @since 1.0.0
	 *
	 * @param string $stream Decoded stream content.
	 * @return string Extracted text.
	 */
	private function extract_text_from_stream( $stream ) {
		$text = '';

		// Method 1: Extract from Tj operator (single string)
		if ( preg_match_all( '/\(([^)]*)\)\s*Tj/s', $stream, $matches ) ) {
			$text .= implode( ' ', array_map( [ $this, 'decode_pdf_string' ], $matches[1] ) ) . ' ';
		}

		// Method 2: Extract from TJ operator (array of strings)
		if ( preg_match_all( '/\[((?:[^]]*\([^)]*\)[^]]*)+)\]\s*TJ/si', $stream, $matches ) ) {
			foreach ( $matches[1] as $array_content ) {
				if ( preg_match_all( '/\(([^)]*)\)/', $array_content, $strings ) ) {
					$text .= implode( '', array_map( [ $this, 'decode_pdf_string' ], $strings[1] ) ) . ' ';
				}
			}
		}

		// Method 3: Extract from BT...ET text blocks
		if ( preg_match_all( '/BT\s*(.*?)\s*ET/s', $stream, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				// Look for text operators within the block
				if ( preg_match_all( '/\(([^)]*)\)/', $block, $strings ) ) {
					$text .= implode( '', array_map( [ $this, 'decode_pdf_string' ], $strings[1] ) ) . ' ';
				}
			}
		}

		// Method 4: Look for hex strings
		if ( preg_match_all( '/<([0-9A-Fa-f\s]+)>\s*Tj/s', $stream, $matches ) ) {
			foreach ( $matches[1] as $hex ) {
				$hex     = preg_replace( '/\s/', '', $hex );
				$decoded = $this->hex_to_string( $hex );
				if ( ! empty( $decoded ) ) {
					$text .= $decoded . ' ';
				}
			}
		}

		return $text;
	}

	/**
	 * Extract text directly from PDF content (for simpler PDFs)
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Full PDF content.
	 * @return string Extracted text.
	 */
	private function extract_direct_pdf_text( $content ) {
		$text = '';

		// Look for Unicode text markers
		if ( preg_match_all( '/\(([^)]{2,})\)/s', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				// Only include if it looks like readable text
				$cleaned = $this->decode_pdf_string( $match );
				if ( preg_match( '/[a-zA-Z]{2,}/', $cleaned ) ) {
					$text .= $cleaned . ' ';
				}
			}
		}

		return $text;
	}

	/**
	 * Decode PDF string escapes
	 *
	 * @since 1.0.0
	 *
	 * @param string $str PDF string.
	 * @return string Decoded string.
	 */
	private function decode_pdf_string( $str ) {
		// Handle escape sequences
		$replacements = [
			'\\n'  => "\n",
			'\\r'  => "\r",
			'\\t'  => "\t",
			'\\b'  => "\b",
			'\\f'  => "\f",
			'\\('  => '(',
			'\\)'  => ')',
			'\\\\' => '\\',
		];

		$str = str_replace( array_keys( $replacements ), array_values( $replacements ), $str );

		// Handle octal escapes
		$str = preg_replace_callback(
			'/\\\\([0-7]{1,3})/',
			function ( $matches ) {
				return chr( octdec( $matches[1] ) );
			},
			$str
		);

		// Remove non-printable characters except common whitespace
		$str = preg_replace( '/[^\x20-\x7E\x0A\x0D\x09]/', '', $str );

		return $str;
	}

	/**
	 * Convert hex string to text
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex Hex string.
	 * @return string Decoded text.
	 */
	private function hex_to_string( $hex ) {
		$str = '';
		$len = strlen( $hex );

		for ( $i = 0; $i < $len; $i += 2 ) {
			$char_code = hexdec( substr( $hex, $i, 2 ) );
			if ( $char_code >= 32 && $char_code <= 126 ) {
				$str .= chr( $char_code );
			} elseif ( 10 === $char_code || 13 === $char_code ) {
				$str .= ' ';
			}
		}

		return $str;
	}

	/**
	 * Decode ASCII85 encoded data
	 *
	 * @since 1.0.0
	 *
	 * @param string $data ASCII85 encoded data.
	 * @return string|false Decoded data or false on failure.
	 */
	private function ascii85_decode( $data ) {
		// Remove whitespace and ~> terminator
		$data = preg_replace( '/\s/', '', $data );
		$data = rtrim( $data, '~>' );

		if ( empty( $data ) ) {
			return false;
		}

		$output = '';
		$len    = strlen( $data );
		$i      = 0;

		while ( $i < $len ) {
			// Handle 'z' shortcut for 4 zero bytes
			if ( 'z' === $data[ $i ] ) {
				$output .= "\0\0\0\0";
				++$i;
				continue;
			}

			// Process 5-character group
			$group = substr( $data, $i, 5 );
			$glen  = strlen( $group );

			if ( $glen < 5 ) {
				// Pad with 'u' (84)
				$group = str_pad( $group, 5, 'u' );
			}

			// Convert from base 85
			$value = 0;
			for ( $j = 0; $j < 5; $j++ ) {
				$value = $value * 85 + ( ord( $group[ $j ] ) - 33 );
			}

			// Convert to 4 bytes
			$bytes = pack( 'N', $value );

			// Only use the bytes we need
			$output .= substr( $bytes, 0, $glen - 1 );

			$i += $glen;
		}

		return $output;
	}

	/**
	 * Extract text from Word document
	 *
	 * Attempts multiple methods to extract text from DOCX files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to DOCX file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	public function extract_docx_text( $file_path ) {
		$text = '';

		// Method 1: Try PhpWord if available
		if ( class_exists( '\\PhpOffice\\PhpWord\\IOFactory' ) ) {
			$text = $this->extract_docx_with_phpword( $file_path );
			if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
				return $text;
			}
		}

		// Method 2: Try basic ZIP extraction (DOCX is a ZIP file)
		$text = $this->extract_docx_basic( $file_path );
		if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
			return $text;
		}

		// All methods failed
		return new WP_Error(
			'ppq_docx_extraction_failed',
			__( 'Unable to extract text from the Word document. The file may be corrupted or in an unsupported format. Please try a different file or copy and paste the text directly.', 'pressprimer-quiz' )
		);
	}

	/**
	 * Extract DOCX text using PhpWord
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to DOCX file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_docx_with_phpword( $file_path ) {
		try {
			$phpWord  = \PhpOffice\PhpWord\IOFactory::load( $file_path );
			$text     = '';
			$sections = $phpWord->getSections();

			foreach ( $sections as $section ) {
				$elements = $section->getElements();
				$text    .= $this->extract_phpword_elements( $elements );
			}

			return $text;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'ppq_phpword_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Recursively extract text from PhpWord elements
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements Array of PhpWord elements.
	 * @return string Extracted text.
	 */
	private function extract_phpword_elements( $elements ) {
		$text = '';

		foreach ( $elements as $element ) {
			// Get text from element if method exists
			if ( method_exists( $element, 'getText' ) ) {
				$element_text = $element->getText();
				if ( is_string( $element_text ) ) {
					$text .= $element_text . "\n";
				}
			}

			// Handle TextRun elements
			if ( method_exists( $element, 'getElements' ) ) {
				$text .= $this->extract_phpword_elements( $element->getElements() );
			}

			// Handle Table elements
			if ( method_exists( $element, 'getRows' ) ) {
				foreach ( $element->getRows() as $row ) {
					if ( method_exists( $row, 'getCells' ) ) {
						foreach ( $row->getCells() as $cell ) {
							if ( method_exists( $cell, 'getElements' ) ) {
								$text .= $this->extract_phpword_elements( $cell->getElements() );
							}
						}
					}
				}
				$text .= "\n";
			}
		}

		return $text;
	}

	/**
	 * Basic DOCX text extraction
	 *
	 * Extracts text from DOCX by treating it as a ZIP file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to DOCX file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_docx_basic( $file_path ) {
		// Check if ZipArchive is available
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'ppq_zip_not_available',
				__( 'ZIP support is not available on this server.', 'pressprimer-quiz' )
			);
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			return new WP_Error(
				'ppq_zip_open_failed',
				__( 'Unable to open the Word document.', 'pressprimer-quiz' )
			);
		}

		// Read the main document content
		$content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $content ) {
			return new WP_Error(
				'ppq_docx_no_content',
				__( 'Unable to read document content.', 'pressprimer-quiz' )
			);
		}

		// Parse XML and extract text
		$text = $this->extract_text_from_docx_xml( $content );

		return $text;
	}

	/**
	 * Extract text from DOCX XML content
	 *
	 * @since 1.0.0
	 *
	 * @param string $xml_content Raw XML content from document.xml.
	 * @return string Extracted text.
	 */
	private function extract_text_from_docx_xml( $xml_content ) {
		// Suppress XML errors
		$use_errors = libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$dom->loadXML( $xml_content, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING );

		// Clear errors
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		$text = '';

		// Find all text elements (w:t)
		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		// Get paragraphs
		$paragraphs = $xpath->query( '//w:p' );

		foreach ( $paragraphs as $paragraph ) {
			$paragraph_text = '';

			// Get all text nodes within paragraph
			$text_nodes = $xpath->query( './/w:t', $paragraph );
			foreach ( $text_nodes as $text_node ) {
				$paragraph_text .= $text_node->textContent;
			}

			if ( ! empty( $paragraph_text ) ) {
				$text .= $paragraph_text . "\n";
			}
		}

		return $this->clean_extracted_text( $text );
	}

	/**
	 * Clean extracted text
	 *
	 * Cleans and normalizes extracted text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw extracted text.
	 * @return string Cleaned text.
	 */
	private function clean_extracted_text( $text ) {
		// Remove null bytes
		$text = str_replace( "\0", '', $text );

		// Convert various line endings to \n
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		// Remove excessive whitespace
		$text = preg_replace( '/[^\S\n]+/', ' ', $text );

		// Remove excessive newlines
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		// Trim lines
		$lines = explode( "\n", $text );
		$lines = array_map( 'trim', $lines );
		$text  = implode( "\n", $lines );

		// Remove empty lines at start and end
		$text = trim( $text );

		return $text;
	}

	/**
	 * Find executable path
	 *
	 * Searches for an executable in common locations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Executable name.
	 * @return string|false Path to executable or false if not found.
	 */
	private function find_executable( $name ) {
		// Common paths to check
		$paths = [
			'/usr/bin/' . $name,
			'/usr/local/bin/' . $name,
			'/opt/homebrew/bin/' . $name,
			'/opt/local/bin/' . $name,
		];

		// Check each path
		foreach ( $paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		// Try 'which' command as fallback (only if exec is available)
		if ( function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ), true ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			$which_result = @exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' );

			if ( ! empty( $which_result ) && file_exists( $which_result ) ) {
				return $which_result;
			}
		}

		return false;
	}

	/**
	 * Cleanup temporary files
	 *
	 * Removes any temporary files created during processing.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_temp_files() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->temp_files = [];
	}

	/**
	 * Get supported file types
	 *
	 * Returns information about supported file types.
	 *
	 * @since 1.0.0
	 *
	 * @return array Supported file type information.
	 */
	public static function get_supported_types() {
		return [
			'pdf'  => [
				'mime_type'  => 'application/pdf',
				'extensions' => [ 'pdf' ],
				'label'      => __( 'PDF Document', 'pressprimer-quiz' ),
			],
			'docx' => [
				'mime_type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'extensions' => [ 'docx' ],
				'label'      => __( 'Word Document', 'pressprimer-quiz' ),
			],
		];
	}

	/**
	 * Get max file size
	 *
	 * Returns the maximum allowed file size.
	 *
	 * @since 1.0.0
	 *
	 * @return int Maximum file size in bytes.
	 */
	public static function get_max_file_size() {
		return self::MAX_FILE_SIZE;
	}

	/**
	 * Check extraction capabilities
	 *
	 * Returns information about available extraction methods.
	 *
	 * @since 1.0.0
	 *
	 * @return array Capability information.
	 */
	public static function get_extraction_capabilities() {
		$capabilities = [
			'pdf'  => [
				'smalot_parser' => class_exists( '\\Smalot\\PdfParser\\Parser' ),
				'pdftotext'     => false,
			],
			'docx' => [
				'phpword' => class_exists( '\\PhpOffice\\PhpWord\\IOFactory' ),
				'basic'   => class_exists( 'ZipArchive' ),
			],
		];

		// Check for pdftotext
		$processor                        = new self();
		$capabilities['pdf']['pdftotext'] = (bool) $processor->find_executable( 'pdftotext' );

		return $capabilities;
	}

	/**
	 * Process file from path
	 *
	 * Processes a file directly from a file path (not an upload).
	 * Used for processing files that are already on the server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to file.
	 * @return array|WP_Error Extracted text and metadata or WP_Error.
	 */
	public function process_file( $file_path ) {
		// Check file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'ppq_file_not_found',
				__( 'File not found.', 'pressprimer-quiz' )
			);
		}

		// Check file size
		$file_size = filesize( $file_path );
		if ( $file_size > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'ppq_file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					__( 'File size exceeds the maximum limit of %s.', 'pressprimer-quiz' ),
					size_format( self::MAX_FILE_SIZE )
				)
			);
		}

		// Get MIME type
		$mime_type = $this->get_file_mime_type( $file_path );
		if ( is_wp_error( $mime_type ) ) {
			return $mime_type;
		}

		// Extract text based on file type
		$file_type = self::ALLOWED_MIME_TYPES[ $mime_type ];

		switch ( $file_type ) {
			case 'pdf':
				$result = $this->extract_pdf_text( $file_path );
				break;

			case 'docx':
				$result = $this->extract_docx_text( $file_path );
				break;

			default:
				return new WP_Error(
					'ppq_unsupported_type',
					__( 'Unsupported file type.', 'pressprimer-quiz' )
				);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'text'      => $result,
			'file_name' => basename( $file_path ),
			'file_type' => $file_type,
			'file_size' => $file_size,
			'mime_type' => $mime_type,
		];
	}

	/**
	 * Get MIME type for existing file
	 *
	 * Determines MIME type for a file that's already on the server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to file.
	 * @return string|WP_Error MIME type or WP_Error.
	 */
	private function get_file_mime_type( $file_path ) {
		$mime_type = '';

		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );
		} elseif ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
		}

		// Fallback to extension-based detection
		if ( empty( $mime_type ) ) {
			$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			$mime_map  = [
				'pdf'  => 'application/pdf',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			];

			if ( isset( $mime_map[ $extension ] ) ) {
				$mime_type = $mime_map[ $extension ];
			}
		}

		if ( empty( $mime_type ) || ! isset( self::ALLOWED_MIME_TYPES[ $mime_type ] ) ) {
			return new WP_Error(
				'ppq_invalid_mime_type',
				sprintf(
					/* translators: %s: file types */
					__( 'Invalid file type. Allowed types: %s', 'pressprimer-quiz' ),
					'PDF, DOCX'
				)
			);
		}

		return $mime_type;
	}
}
