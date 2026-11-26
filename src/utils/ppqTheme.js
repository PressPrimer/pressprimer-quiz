/**
 * Ant Design theme from the shell's tokens
 *
 * Maps Ant Design tokens to the front-end shell's --ppq-* CSS custom
 * properties, so components look like the shell chrome and the active theme
 * (white-label branding restyles the same properties). Free's canonical copy
 * of the addons' getPpqTheme(); the addons may switch to it.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

/**
 * Read a --ppq-* custom property from the shell container.
 *
 * @param {CSSStyleDeclaration|null} cs       Computed style of the shell container.
 * @param {string}                   name     CSS custom property name.
 * @param {string}                   fallback Fallback value.
 * @return {string} The resolved value.
 */
const readVar = (cs, name, fallback) => {
	const value = cs ? cs.getPropertyValue(name).trim() : '';
	return value || fallback;
};

/**
 * Build an Ant Design theme from the shell's --ppq-* tokens.
 *
 * @param {Element|null} el An element inside the shell (optional; the page's
 *                          shell is used otherwise).
 * @return {Object} Ant Design theme config ({ token }).
 */
export const getPpqTheme = (el = null) => {
	const root = (el && el.closest && el.closest('.ppq-shell')) || document.querySelector('.ppq-shell') || document.documentElement;
	const cs = root ? window.getComputedStyle(root) : null;

	const token = {
		colorPrimary: readVar(cs, '--ppq-primary', '#0073aa'),
		colorSuccess: readVar(cs, '--ppq-success', '#00a32a'),
		colorError: readVar(cs, '--ppq-error', '#d63638'),
		colorText: readVar(cs, '--ppq-text', '#1d2327'),
		colorBgContainer: readVar(cs, '--ppq-bg', '#fff'),
		colorBorder: readVar(cs, '--ppq-border', '#c3c4c7'),
		borderRadius: parseInt(readVar(cs, '--ppq-radius-md', '6'), 10) || 6,
	};

	// Only override Ant Design's default font when the appearance setting
	// supplies one, so sites that haven't customized it keep their font.
	const fontFamily = readVar(cs, '--ppq-font-family', '');
	if (fontFamily) {
		token.fontFamily = fontFamily;
	}

	return { token };
};

/**
 * CSS for the inside of a TinyMCE editor on the dashboard, from the shell's
 * tokens, so the editing area matches the shell rather than the theme's
 * editor styles (TinyMCE's content_style is applied after them).
 *
 * @param {Element|null} el An element inside the shell (or null).
 * @return {string} CSS for TinyMCE's content_style.
 */
export const getPpqContentStyle = (el = null) => {
	const root = (el && el.closest && el.closest('.ppq-shell')) || document.querySelector('.ppq-shell');
	const cs = root ? window.getComputedStyle(root) : null;

	const font = readVar(cs, '--ppq-font-family', '') || (cs ? cs.fontFamily : '') || 'sans-serif';
	const text = readVar(cs, '--ppq-text', '#1d2327');
	const bg = readVar(cs, '--ppq-bg', '#fff');
	const link = readVar(cs, '--ppq-primary', '#0073aa');

	return `body{font-family:${font};font-size:16px;line-height:1.6;color:${text};background:${bg};}a{color:${link};}`;
};
