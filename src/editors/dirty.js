/**
 * Unsaved-changes tracking
 *
 * Tells a host (mount()'s onDirtyChange) when an editor gains or loses
 * unsaved changes, so it can guard navigation. Only the author's own edits
 * count: values the editor sets while loading don't.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useRef, useMemo, useEffect } from '@wordpress/element';
import { debugError } from '../utils/debug';

/**
 * Track whether the editor has unsaved changes.
 *
 * The callback runs only when the state changes, and is called with false
 * before the editor navigates after a save or a confirmed Cancel, so a host
 * guard reading it lets that navigation through.
 *
 * @param {Function} onDirtyChange Called with true or false (optional).
 * @return {{markDirty: Function, markClean: Function, isDirty: Function}} Tracker.
 */
export const useDirtyTracker = (onDirtyChange) => {
	const dirty = useRef(false);
	const callback = useRef(onDirtyChange);

	useEffect(() => {
		callback.current = onDirtyChange;
	}, [onDirtyChange]);

	return useMemo(() => {
		const set = (value) => {
			if (dirty.current === value) {
				return;
			}
			dirty.current = value;
			if (typeof callback.current === 'function') {
				try {
					callback.current(value);
				} catch (error) {
					// A host callback that throws never breaks the editor.
					debugError('onDirtyChange callback failed:', error);
				}
			}
		};

		return {
			markDirty: () => set(true),
			markClean: () => set(false),
			isDirty: () => dirty.current,
		};
	}, []);
};
