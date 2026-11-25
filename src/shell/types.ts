/**
 * Front-end shell type contracts.
 *
 * Exported so addon screen bundles building in TypeScript can type their
 * components against the shell's screen contract.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

/*
 * This file is pure type declarations. The base no-unused-vars rule misfires on
 * parameter names inside function-type signatures (which are documentation, not
 * runtime variables), so it is disabled for this file only.
 */
/* eslint-disable no-unused-vars */

/** Options for navigate(). */
export interface NavigateOptions {
	/** Swap the current history entry instead of adding one (v3.2). */
	replace?: boolean;
	/** Skip the navigation guard, e.g. right after saving (v3.2). */
	force?: boolean;
}

/** What a navigation guard is asked about (v3.2). */
export interface NavigationAttempt {
	/** Route the navigation goes to (without '#/'). */
	to: string;
	/** Route currently showing. */
	from: string;
	/** 'navigate' (navigate(), Sidebar), 'history' (back/forward, typed hash), 'unload' (reload, close). */
	reason: 'navigate' | 'history' | 'unload';
}

/** A navigation guard: return false to stay on the current route (v3.2). */
export type NavigationGuard = ( attempt: NavigationAttempt ) => boolean;

/** Navigate to a route; returns false when the guard refused. */
export type NavigateFn = ( route: string, options?: NavigateOptions ) => boolean | void;

export interface ShellUser {
	id: number;
	name: string;
}

export interface ShellBranding {
	logoUrl: string;
	productName: string;
	siteName: string;
}

export interface ScreenManifestEntry {
	id: string;
	label: string;
	group: string;
	icon: string;
	order: number;
	locked?: boolean;
	lockedTier?: string;
	upgradeUrl?: string;
	tierName?: string;
	tierDescription?: string;
	tierHighlights?: string[];
	tierUrl?: string;
}

export interface GroupDef {
	label: string;
	order: number;
}

export interface BootData {
	restUrl: string;
	restNonce: string;
	user: ShellUser;
	screens: ScreenManifestEntry[];
	groups: Record< string, GroupDef >;
	loginUrl: string;
	branding: ShellBranding;
}

export interface ShellRoute {
	/** First path segment, e.g. 'my-results'. Empty for the default route. */
	screenId: string;
	/** Remaining path segments owned by the screen. */
	subPath: string[];
	/** The raw route without the leading '#/'. */
	raw: string;
}

/**
 * Props every screen component receives when mounted.
 */
export interface ScreenProps {
	user: ShellUser;
	restNonce: string;
	navigate: NavigateFn;
	route: ShellRoute;
}

/** A screen component: a function of ScreenProps that renders JSX. */
export type ScreenComponent = ( props: ScreenProps ) => unknown;

/** A home-screen card component. */
export type HomeCardComponent = ( props: { user: ShellUser } ) => unknown;

export interface MyAttemptItem {
	attempt_id: number;
	quiz_id: number;
	quiz_title: string;
	started_at: string | null;
	completed_at: string | null;
	score_percent: number | null;
	passed: boolean | null;
	is_practice: boolean;
	status: string;
	results_url: string;
	resume_url: string;
}

export interface AttemptQuizOption {
	id: number;
	title: string;
}

export interface MyAttemptsResponse {
	items: MyAttemptItem[];
	total: number;
	total_pages: number;
	quizzes: AttemptQuizOption[];
}

export interface MyAttemptsParams {
	page?: number;
	per_page?: number;
	quiz_id?: number;
	status?: string;
	orderby?: string;
	order?: string;
	practice?: string;
}

/** The public API exposed on window.PPQ.shell. */
export interface ShellApi {
	registerScreen: ( id: string, component: ScreenComponent ) => void;
	registerHomeCard: ( id: string, component: HomeCardComponent ) => void;
	/** Navigate to a route (v3.2). */
	navigate: NavigateFn;
	/** Set or clear the navigation guard (v3.2). */
	setNavigationGuard: ( guard: NavigationGuard | null ) => void;
}

declare global {
	interface Window {
		PPQ?: { shell?: ShellApi };
		PPQShellData?: BootData;
	}
}
