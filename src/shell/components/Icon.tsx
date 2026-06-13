/**
 * Shell icons.
 *
 * Maps whitelisted icon keys (from the boot manifest) to small inline SVGs.
 * Decorative — hidden from assistive tech; the adjacent label conveys meaning.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

const PATHS: Record< string, string > = {
	home: 'M12 3 2 12h3v8h6v-6h2v6h6v-8h3L12 3z',
	results: 'M4 4h2v16H4V4zm5 8h2v8H9v-8zm5-5h2v13h-2V7zm5 8h2v5h-2v-5z',
	teaching: 'M12 3 1 9l11 6 9-4.9V17h2V9L12 3zM5 13.2V17c0 1.7 3.1 3 7 3s7-1.3 7-3v-3.8l-7 3.8-7-3.8z',
	reports: 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm2 12h2v3H7v-3zm4-6h2v9h-2V9zm4 3h2v6h-2v-6z',
	tools: 'M21 6.5 17.5 3 9 11.5l3.5 3.5L21 6.5zM3 17l5 5 1.5-4.5L7 15l-4 2z',
	groups: 'M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1 .4-1.8 1-2.5C9.9 13.1 8.7 13 8 13zm8 0c-.3 0-.7 0-1.1.1 1.3 1 2.1 2.3 2.1 3.9v3h7v-3c0-2.7-5.3-4-8-4z',
	assignments: 'M19 3h-4.2A3 3 0 0 0 9.2 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 0a1 1 0 1 1 0 2 1 1 1 0 0 1 0-2zm-1 14-4-4 1.4-1.4L11 14.2l5.6-5.6L18 10l-7 7z',
	planner: 'M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zM5 9h14v10H5V9z',
	proctoring: 'M12 5C5 5 1 12 1 12s4 7 11 7 11-7 11-7-4-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z',
	students: 'M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.3 0-8 1.7-8 5v3h16v-3c0-3.3-4.7-5-8-5z',
	settings: 'M19.4 13a7.8 7.8 0 0 0 0-2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-1.7-1l-.4-2.6h-3.8l-.4 2.6a7.6 7.6 0 0 0-1.7 1l-2.4-1-2 3.4L4.6 11a7.8 7.8 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 1.7 1l.4 2.6h3.8l.4-2.6a7.6 7.6 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z',
	lock: 'M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5zm3 8H9V6a3 3 0 0 1 6 0v3z',
	menu: 'M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z',
	default: 'M4 4h16v16H4V4zm2 2v12h12V6H6z',
};

interface IconProps {
	name: string;
	size?: number;
}

/**
 * Render a shell icon by key.
 *
 * @param {IconProps} props Icon props.
 * @return {JSX.Element} The icon.
 */
export default function Icon( { name, size = 20 }: IconProps ) {
	const path = PATHS[ name ] || PATHS.default;

	return (
		<svg
			className="ppq-shell-icon"
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path fill="currentColor" d={ path } />
		</svg>
	);
}
