/**
 * My Attempts Block
 *
 * Gutenberg block for displaying user's quiz attempts.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * List icon
 */
const listIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
		<path fill="currentColor" d="M4 4h4v4H4V4zm6 1v2h10V5H10zm-6 5h4v4H4v-4zm6 1v2h10v-2H10zm-6 5h4v4H4v-4zm6 1v2h10v-2H10z" />
	</svg>
);

/**
 * Edit component for My Attempts block
 *
 * @param {Object} props Block props.
 * @return {JSX.Element} Block edit component.
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { showScore, showDate, perPage } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Display Settings', 'pressprimer-quiz' ) } initialOpen={ true }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Score', 'pressprimer-quiz' ) }
						help={ __( 'Display the score percentage for each attempt', 'pressprimer-quiz' ) }
						checked={ showScore }
						onChange={ ( value ) => setAttributes( { showScore: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Date', 'pressprimer-quiz' ) }
						help={ __( 'Display the date when each quiz was completed', 'pressprimer-quiz' ) }
						checked={ showDate }
						onChange={ ( value ) => setAttributes( { showDate: value } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Attempts Per Page', 'pressprimer-quiz' ) }
						help={ __( 'Number of attempts to show per page', 'pressprimer-quiz' ) }
						value={ perPage }
						onChange={ ( value ) => setAttributes( { perPage: value } ) }
						min={ 5 }
						max={ 100 }
						step={ 5 }
					/>
				</PanelBody>
			</InspectorControls>

			<Placeholder
				icon={ listIcon }
				label={ __( 'PPQ Quiz Attempts', 'pressprimer-quiz' ) }
				instructions={ __( "This block displays the current user's quiz attempts. Configure display options in the sidebar.", 'pressprimer-quiz' ) }
			>
				<div style={ { textAlign: 'left', width: '100%', padding: '20px' } }>
					<p style={ { margin: '0 0 10px', color: '#666', fontSize: '14px' } }>
						<strong>{ __( 'Settings:', 'pressprimer-quiz' ) }</strong>
					</p>
					<ul style={ { margin: 0, paddingLeft: '20px', color: '#666', fontSize: '13px' } }>
						<li>
							{ __( 'Show Score:', 'pressprimer-quiz' ) }{ ' ' }
							<strong>{ showScore ? __( 'Yes', 'pressprimer-quiz' ) : __( 'No', 'pressprimer-quiz' ) }</strong>
						</li>
						<li>
							{ __( 'Show Date:', 'pressprimer-quiz' ) }{ ' ' }
							<strong>{ showDate ? __( 'Yes', 'pressprimer-quiz' ) : __( 'No', 'pressprimer-quiz' ) }</strong>
						</li>
						<li>
							{ __( 'Per Page:', 'pressprimer-quiz' ) } <strong>{ perPage }</strong>
						</li>
					</ul>
					<p style={ { marginTop: '15px', color: '#999', fontSize: '12px', fontStyle: 'italic' } }>
						{ __( 'Preview not available in editor. The list will display on the frontend for logged-in users.', 'pressprimer-quiz' ) }
					</p>
				</div>
			</Placeholder>
		</div>
	);
}

/**
 * Register My Attempts block
 */
registerBlockType( 'pressprimer-quiz/my-attempts', {
	icon: listIcon,
	edit: Edit,
	save: () => null,
} );
