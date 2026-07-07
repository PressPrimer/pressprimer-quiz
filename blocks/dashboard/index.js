/**
 * Dashboard Block
 *
 * Gutenberg block that mounts the front-end app shell on a page. Dynamic block:
 * the editor shows only a static placeholder, and the live shell renders on the
 * front end via the PHP render callback (PressPrimer_Quiz_Shell::render).
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Dashboard (grid) icon.
 */
const dashboardIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
		<path
			fill="currentColor"
			d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm8-2h8v8h-8v-8zm2 2v4h4v-4h-4z"
		/>
	</svg>
);

/**
 * Editor placeholder. Intentionally static — never the live application.
 *
 * @return {JSX.Element} Block edit component.
 */
function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ dashboardIcon }
				label={ __( 'PressPrimer Quiz Dashboard', 'pressprimer-quiz' ) }
				instructions={ __(
					'The front-end dashboard renders here on the published page. This placeholder is shown only in the editor. Only one dashboard can appear per page.',
					'pressprimer-quiz'
				) }
			/>
		</div>
	);
}

/**
 * Register the Dashboard block (editor side; render is server-side).
 */
registerBlockType( 'pressprimer-quiz/dashboard', {
	icon: dashboardIcon,
	edit: Edit,
	save: () => null,
} );
