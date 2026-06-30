/**
 * MathContent
 *
 * Renders author HTML and, when the bundled KaTeX runtime is present, typesets
 * any LaTeX within it. Math rendering is gated entirely by the presence of
 * `window.PressPrimerQuizMath`, which the server only loads when the Math
 * Notation setting is on — so this is a plain HTML renderer when the feature is
 * off (and works the same way in addon report surfaces).
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useRef, useEffect } from '@wordpress/element';

/**
 * Whether a string contains math delimiters worth typesetting.
 *
 * @param {string} html Candidate HTML.
 * @return {boolean} True when an opening delimiter is present.
 */
const hasMath = (html) =>
	typeof html === 'string' &&
	(html.includes('\\(') || html.includes('\\[') || html.includes('$$'));

/**
 * Render HTML, typesetting math when the KaTeX runtime is available.
 *
 * @param {Object} props           Component props.
 * @param {string} props.html      HTML to render (author content).
 * @param {string} props.className Optional class for the wrapper element.
 * @param {string} props.tag       Wrapper element tag ('div' or 'span').
 * @return {JSX.Element} The rendered element.
 */
const MathContent = ({ html, className, tag = 'div' }) => {
	const ref = useRef(null);

	useEffect(() => {
		if (!ref.current || !hasMath(html)) {
			return;
		}
		if (window.PressPrimerQuizMath?.typeset) {
			window.PressPrimerQuizMath.typeset(ref.current);
		}
	}, [html]);

	const Tag = tag;

	return (
		<Tag
			ref={ref}
			className={className}
			dangerouslySetInnerHTML={{ __html: html || '' }}
		/>
	);
};

export default MathContent;
