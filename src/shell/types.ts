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

/** A `(string) => void` callback (navigation, selection). */
export type NavigateFn = ( route: string ) => void;

export interface ShellUser {
	id: number;
	name: string;
}

export interface ShellBranding {
	logoUrl: string;
	productName: string;
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
}

/** The public registration API exposed on window.PPQ.shell. */
export interface ShellApi {
	registerScreen: ( id: string, component: ScreenComponent ) => void;
	registerHomeCard: ( id: string, component: HomeCardComponent ) => void;
}

declare global {
	interface Window {
		PPQ?: { shell?: ShellApi };
		PPQShellData?: BootData;
	}
}
