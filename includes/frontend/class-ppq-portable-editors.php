<?php
/**
 * Portable editors
 *
 * Gets the quiz, question and bank editors onto a front-end dashboard page
 * for a host (Educator's authoring screens). The page carries a small loader
 * (ppq-editors-loader) and a manifest; PPQ.editors.load() adds the editors
 * bundle (ppq-editors), its translations and the addon extension handles the
 * first time the host opens an editor.
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 * @since 3.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portable editors class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_Portable_Editors {

	/**
	 * The editors bundle's script handle (registered, never enqueued here).
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const HANDLE = 'ppq-editors';

	/**
	 * The loader's script handle.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const LOADER_HANDLE = 'ppq-editors-loader';

	/**
	 * Whether enqueue() already ran on this request.
	 *
	 * @since 3.2.0
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Register the editors bundle and the loader.
	 *
	 * @since 3.2.0
	 */
	public static function register() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		$asset = self::get_asset( 'editors' );

		wp_register_script(
			self::HANDLE,
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/editors.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Only used when a host enqueues ppq-editors itself; load() carries
		// the translations in the manifest.
		wp_set_script_translations( self::HANDLE, 'pressprimer-quiz', PRESSPRIMER_QUIZ_PLUGIN_PATH . 'languages' );

		$loader = self::get_asset( 'editors-loader' );

		// The loader brings the editors' WordPress dependencies with it, so
		// the bundle finds them when load() adds it.
		wp_register_script(
			self::LOADER_HANDLE,
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/editors-loader.js',
			array_values( array_unique( array_merge( $loader['dependencies'], $asset['dependencies'] ) ) ),
			$loader['version'],
			true
		);
	}

	/**
	 * Enqueue what a host page needs to open the editors.
	 *
	 * The loader and its manifest, TinyMCE (wp_enqueue_editor()), the math
	 * assets when math is on, and the extension handles' dependencies. Never
	 * the wp-editor package, the media library or wp-admin's Ant Design reset.
	 *
	 * @since 3.2.0
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		self::register();

		$extensions = self::get_extensions();

		// The extension scripts load after the editors, so their WordPress
		// dependencies come with the loader.
		$loader = wp_scripts()->query( self::LOADER_HANDLE, 'registered' );
		if ( $loader ) {
			foreach ( $extensions as $extension ) {
				foreach ( $extension['deps'] as $dep ) {
					if ( ! in_array( $dep, $loader->deps, true ) ) {
						$loader->deps[] = $dep;
					}
				}
			}
		}

		wp_enqueue_script( self::LOADER_HANDLE );

		wp_add_inline_script(
			self::LOADER_HANDLE,
			'window.pressprimerQuizEditorsManifest = ' . wp_json_encode( self::get_manifest( $extensions ) ) . ';',
			'before'
		);

		// Extension handles' localized data and "before" scripts run now, as
		// they would for an enqueued script.
		foreach ( $extensions as $extension ) {
			foreach ( $extension['before'] as $code ) {
				wp_add_inline_script( self::LOADER_HANDLE, $code, 'before' );
			}
		}

		// TinyMCE: WordPress has no supported way to load it later.
		wp_enqueue_editor();

		if ( pressprimer_quiz_math_enabled() ) {
			pressprimer_quiz_enqueue_math_assets();
		}
	}

	/**
	 * The manifest load() reads.
	 *
	 * @since 3.2.0
	 *
	 * @param array[] $extensions Extensions from get_extensions().
	 * @return array Manifest.
	 */
	private static function get_manifest( $extensions ) {
		$asset = self::get_asset( 'editors' );

		$styles = array();
		foreach ( array( 'style-editors', 'editors' ) as $name ) {
			$file = 'build/' . $name . ( is_rtl() ? '-rtl' : '' ) . '.css';
			if ( file_exists( PRESSPRIMER_QUIZ_PLUGIN_PATH . $file ) ) {
				$styles[] = array(
					'id'   => 'ppq-' . $name . '-css',
					'href' => add_query_arg( 'ver', $asset['version'], PRESSPRIMER_QUIZ_PLUGIN_URL . $file ),
				);
			}
		}

		$manifest_extensions = array();
		foreach ( $extensions as $extension ) {
			$manifest_extensions[] = array(
				'handle'       => $extension['handle'],
				'src'          => $extension['src'],
				'styles'       => $extension['styles'],
				'translations' => $extension['translations'],
			);
		}

		return array(
			'version'      => $asset['version'],
			'src'          => self::script_url( self::HANDLE ),
			'styles'       => $styles,
			'styleAnchor'  => class_exists( 'PressPrimer_Quiz_Shell' ) ? PressPrimer_Quiz_Shell::HANDLE . '-inline-css' : 'ppq-shell-inline-css',
			'translations' => self::get_translations( self::HANDLE, 'pressprimer-quiz', PRESSPRIMER_QUIZ_PLUGIN_PATH . 'languages' ),
			'extensions'   => $manifest_extensions,
		);
	}

	/**
	 * The dashboard extension handles, with what load() needs for each.
	 *
	 * A style registered under the same handle loads with the script. An
	 * extension's localized data and "before" inline scripts are printed with
	 * the loader; "after" inline scripts aren't supported.
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Each: handle, src, deps, styles, translations, before.
	 */
	private static function get_extensions() {
		$handles    = PressPrimer_Quiz_Editor_Extensions::get_handles( 'dashboard' );
		$scripts    = wp_scripts();
		$own        = array_merge( array( self::HANDLE, self::LOADER_HANDLE ), $handles );
		$extensions = array();

		foreach ( $handles as $handle ) {
			$script = $scripts->query( $handle, 'registered' );
			$src    = self::script_url( $handle );

			if ( ! $script || '' === $src ) {
				continue;
			}

			$before = array();
			$data   = $scripts->get_data( $handle, 'data' );
			if ( is_string( $data ) && '' !== $data ) {
				$before[] = $data;
			}
			$inline = $scripts->get_data( $handle, 'before' );
			if ( is_array( $inline ) ) {
				$before = array_merge( $before, array_filter( $inline, 'is_string' ) );
			}

			$styles = array();
			$style  = wp_styles()->query( $handle, 'registered' );
			if ( $style && is_string( $style->src ) && '' !== $style->src ) {
				$styles[] = array(
					'id'   => $handle . '-css',
					'href' => self::style_url( $handle ),
				);
			}

			$extensions[] = array(
				'handle'       => $handle,
				'src'          => $src,
				'deps'         => array_values( array_diff( (array) $script->deps, $own ) ),
				'styles'       => $styles,
				'translations' => empty( $script->textdomain ) ? null : self::get_translations( $handle, $script->textdomain, $script->translations_path ),
				'before'       => $before,
			);
		}

		return $extensions;
	}

	/**
	 * A script's translations, as WordPress would print them for it.
	 *
	 * @since 3.2.0
	 *
	 * @param string $handle Script handle.
	 * @param string $domain Text domain.
	 * @param string $path   Translations path (optional).
	 * @return array|null { domain, data } or null when there are none.
	 */
	private static function get_translations( $handle, $domain, $path = '' ) {
		$json = load_script_textdomain( $handle, $domain, $path );

		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['locale_data'] ) || ! is_array( $data['locale_data'] ) ) {
			return null;
		}

		return array(
			'domain' => $domain,
			'data'   => $data,
		);
	}

	/**
	 * The URL a registered script loads from, with its version.
	 *
	 * @since 3.2.0
	 *
	 * @param string $handle Script handle.
	 * @return string URL, or '' when the handle has no source.
	 */
	private static function script_url( $handle ) {
		$scripts = wp_scripts();
		$script  = $scripts->query( $handle, 'registered' );

		if ( ! $script || ! is_string( $script->src ) || '' === $script->src ) {
			return '';
		}

		$src = self::absolute_src( $script->src, $scripts->base_url );
		$ver = null === $script->ver ? '' : ( $script->ver ? $script->ver : $scripts->default_version );

		if ( '' !== $ver ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}

		return $src;
	}

	/**
	 * The URL a registered style loads from, with its version.
	 *
	 * @since 3.2.0
	 *
	 * @param string $handle Style handle.
	 * @return string URL.
	 */
	private static function style_url( $handle ) {
		$styles = wp_styles();
		$style  = $styles->query( $handle, 'registered' );
		$src    = $style->src;

		// Right-to-left sites use the style's RTL file when it has one.
		if ( is_rtl() && ! empty( $style->extra['rtl'] ) ) {
			$src = 'replace' === $style->extra['rtl']
				? str_replace( '.css', '-rtl.css', $src )
				: (string) $style->extra['rtl'];
		}

		$src = self::absolute_src( $src, $styles->base_url );
		$ver = null === $style->ver ? '' : ( $style->ver ? $style->ver : $styles->default_version );

		if ( '' !== $ver ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}

		return $src;
	}

	/**
	 * Make a registered source absolute (core's relative paths are relative
	 * to the site URL).
	 *
	 * @since 3.2.0
	 *
	 * @param string $src      Registered source.
	 * @param string $base_url The dependency class's base URL.
	 * @return string Absolute URL.
	 */
	private static function absolute_src( $src, $base_url ) {
		if ( preg_match( '|^(https?:)?//|', $src ) ) {
			return $src;
		}

		return $base_url . $src;
	}

	/**
	 * A build asset manifest (dependencies and version).
	 *
	 * @since 3.2.0
	 *
	 * @param string $name Entry name.
	 * @return array dependencies and version.
	 */
	private static function get_asset( $name ) {
		$file  = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/' . $name . '.asset.php';
		$asset = file_exists( $file ) ? require $file : array();

		return array(
			'dependencies' => isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			'version'      => isset( $asset['version'] ) ? $asset['version'] : PRESSPRIMER_QUIZ_VERSION,
		);
	}
}
