/**
 * PressPrimer Quiz — Math (KaTeX) initializer.
 *
 * Exposes window.PressPrimerQuizMath.typeset( element ) and auto-typesets the
 * configured front-end containers on load. Enqueued only when math notation is
 * enabled and the rendered content contains math delimiters.
 *
 * @package
 * @since 3.0.0
 */

/* global renderMathInElement */
( function () {
	'use strict';

	const config = window.pressprimerQuizMathConfig || {};

	const delimiters = config.delimiters || [
		{ left: '\\(', right: '\\)', display: false },
		{ left: '\\[', right: '\\]', display: true },
		{ left: '$$', right: '$$', display: true },
	];

	// KaTeX render options, hardened: never throw (show the source in red),
	// never trust URL-bearing commands, and bound macro expansion / size so a
	// malformed or hostile expression cannot hang the browser.
	const options = {
		delimiters,
		throwOnError: false,
		errorColor: '#cc0000',
		trust: false,
		strict: 'warn',
		maxSize: 100,
		maxExpand: 1000,
		output: 'htmlAndMathml',
		ignoredTags: [
			'script',
			'noscript',
			'style',
			'textarea',
			'pre',
			'code',
			'input',
			'option',
		],
	};

	/**
	 * Replace non-breaking spaces (U+00A0) with regular spaces in an element's
	 * text nodes.
	 *
	 * Visual editors (TinyMCE) can store a space typed inside a math expression
	 * — e.g. the space after \( in "\( \int ... \)" — as a non-breaking space.
	 * KaTeX treats U+00A0 as an unknown symbol and renders the source in red, so
	 * normalizing it first lets that math render like any other.
	 *
	 * @param {HTMLElement} root Container to normalize.
	 */
	function normalizeNonBreakingSpaces( root ) {
		if ( ! root || typeof document.createTreeWalker !== 'function' ) {
			return;
		}
		const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
		let node = walker.nextNode();
		while ( node ) {
			if ( node.nodeValue && node.nodeValue.indexOf( '\u00a0' ) !== -1 ) {
				node.nodeValue = node.nodeValue.replace( /\u00a0/g, ' ' );
			}
			node = walker.nextNode();
		}
	}

	/**
	 * Typeset math within a single DOM element.
	 *
	 * @param {HTMLElement} element Container to render math within.
	 */
	function typeset( element ) {
		if ( ! element || typeof renderMathInElement !== 'function' ) {
			return;
		}
		normalizeNonBreakingSpaces( element );
		try {
			renderMathInElement( element, options );
		} catch ( e ) {
			// Never let a rendering error break the page.
		}
	}

	window.PressPrimerQuizMath = { typeset, options };

	/**
	 * Auto-typeset the configured containers (front-end quiz / results).
	 * No-ops on pages where none of the selectors match (e.g. admin React
	 * surfaces, which call typeset() explicitly).
	 */
	function autoTypeset() {
		const selectors = config.autoSelectors || [];
		selectors.forEach( ( selector ) => {
			document
				.querySelectorAll( selector )
				.forEach( ( node ) => typeset( node ) );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', autoTypeset );
	} else {
		autoTypeset();
	}
} )();
