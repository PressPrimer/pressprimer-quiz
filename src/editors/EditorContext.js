/**
 * Editor context
 *
 * What the quiz, question and bank editors share with their child components
 * instead of reading window globals: where the editor runs (surface), the
 * host's navigation adapter, the site flags from the editor data, and the
 * addon slots.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { createContext, useContext } from '@wordpress/element';

const EditorContext = createContext({
	surface: 'admin',
	nav: null,
	site: {},
	slots: {},
});

export const EditorProvider = EditorContext.Provider;

/**
 * Read the editor context.
 *
 * @return {{surface: string, nav: Object|null, site: Object, slots: Object}} Context value.
 */
export const useEditorContext = () => useContext(EditorContext);

/**
 * Pick the site flags out of an editor's data.
 *
 * wp-admin localizes top-level scalars as strings ("1" or ""), so flags are
 * read as truthy values.
 *
 * @param {Object} data Editor data (editor-data service payload).
 * @return {Object} mathEnabled, isAdmin, canManageSettings and userCan.
 */
export const getSiteFlags = (data = {}) => ({
	mathEnabled: !!data.mathEnabled,
	isAdmin: !!data.isAdmin,
	canManageSettings: !!data.canManageSettings,
	userCan: data.userCan && typeof data.userCan === 'object' ? data.userCan : {},
});
