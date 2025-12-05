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
	 * Checks upload for errors, size, and type.
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
			return $text;
		}

		// Method 3: Try basic PHP extraction
		$text = $this->extract_pdf_basic( $file_path );
		if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
			return $text;
		}

		// All methods failed
		return new WP_Error(
			'ppq_pdf_extraction_failed',
			__( 'Unable to extract text from the PDF file. The file may be scanned (image-based) or corrupted. Please try a different file or copy and paste the text directly.', 'pressprimer-quiz' )
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
			$parser = new \Smalot\PdfParser\Parser();
			$pdf    = $parser->parseFile( $file_path );
			$text   = $pdf->getText();

			return $text;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'ppq_pdf_parser_error',
				$e->getMessage()
			);
		}
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
	 * This is a fallback method and may not work with all PDFs.
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
		// This is a very basic approach that works with some PDFs
		preg_match_all( '/stream\s*(.+?)\s*endstream/s', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $stream ) {
				// Try to decode if compressed
				$decoded = @gzuncompress( $stream );
				if ( false !== $decoded ) {
					$stream = $decoded;
				}

				// Extract text content
				// Look for text showing operators: Tj, TJ, ', "
				if ( preg_match_all( '/\(([^)]+)\)\s*Tj/s', $stream, $text_matches ) ) {
					$text .= implode( ' ', $text_matches[1] ) . ' ';
				}

				// Also try BT...ET text blocks
				if ( preg_match_all( '/BT\s*(.+?)\s*ET/s', $stream, $block_matches ) ) {
					foreach ( $block_matches[1] as $block ) {
						if ( preg_match_all( '/\(([^)]+)\)/s', $block, $inner_matches ) ) {
							$text .= implode( ' ', $inner_matches[1] ) . ' ';
						}
					}
				}
			}
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

		// Try 'which' command as fallback
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$which_result = exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' );

		if ( ! empty( $which_result ) && file_exists( $which_result ) ) {
			return $which_result;
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
				'basic'         => true,
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
