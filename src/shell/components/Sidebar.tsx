/**
 * Shell navigation.
 *
 * Grouped nav built from the boot manifest: a left sidebar on desktop and a
 * slide-in panel on mobile (the same markup; CSS handles the layout). FR-009.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import type {
	ScreenManifestEntry,
	GroupDef,
	ShellBranding,
	NavigateFn,
} from '../types';

interface SidebarProps {
	screens: ScreenManifestEntry[];
	groups: Record< string, GroupDef >;
	branding: ShellBranding;
	activeId: string;
	open: boolean;
	onNavigate: NavigateFn;
}

/**
 * Render the grouped navigation.
 *
 * @param {SidebarProps} props Props.
 * @return {JSX.Element} Navigation.
 */
export default function Sidebar( {
	screens,
	groups,
	branding,
	activeId,
	open,
	onNavigate,
}: SidebarProps ) {
	// Screens arrive pre-sorted by group order then entry order, so collecting
	// groups in first-seen order preserves the intended ordering.
	const groupOrder: string[] = [];
	screens.forEach( ( screen ) => {
		if ( ! groupOrder.includes( screen.group ) ) {
			groupOrder.push( screen.group );
		}
	} );

	return (
		<nav
			id="ppq-shell-nav"
			className={ 'ppq-shell-nav' + ( open ? ' is-open' : '' ) }
			aria-label={ __( 'Dashboard navigation', 'pressprimer-quiz' ) }
		>
			{ ( branding.logoUrl || branding.productName ) && (
				<div className="ppq-shell-brand">
					{ branding.logoUrl ? (
						<img
							className="ppq-shell-brand-logo"
							src={ branding.logoUrl }
							alt={ branding.productName }
						/>
					) : (
						<span className="ppq-shell-brand-name">
							{ branding.productName }
						</span>
					) }
				</div>
			) }

			{ groupOrder.map( ( groupKey ) => {
				const groupScreens = screens.filter(
					( screen ) => screen.group === groupKey
				);

				// Don't render a nav group that has no visible screens (e.g.
				// the Teaching group with no Educator add-on installed).
				if ( groupScreens.length === 0 ) {
					return null;
				}

				const groupLabel = groups[ groupKey ]
					? groups[ groupKey ].label
					: groupKey;

				return (
					<div className="ppq-shell-nav-group" key={ groupKey }>
						<h2 className="ppq-shell-nav-group-title">{ groupLabel }</h2>
						<ul className="ppq-shell-nav-list">
							{ groupScreens.map( ( screen ) => {
								const isActive = screen.id === activeId;
								return (
									<li key={ screen.id }>
										<button
											type="button"
											className={
												'ppq-shell-nav-item' +
												( isActive ? ' is-active' : '' ) +
												( screen.locked ? ' is-locked' : '' )
											}
											aria-current={ isActive ? 'page' : undefined }
											onClick={ () => onNavigate( screen.id ) }
										>
											<Icon name={ screen.icon } />
											<span className="ppq-shell-nav-label">
												{ screen.label }
											</span>
											{ screen.locked && (
												<Icon name="lock" size={ 14 } />
											) }
										</button>
									</li>
								);
							} ) }
						</ul>
					</div>
				);
			} ) }
		</nav>
	);
}
