/**
 * Mount API
 *
 * window.PPQ.editors.mount( el, options ) renders the quiz, question or bank
 * editor into a host's element (the front-end dashboard, in 3.2). It fetches
 * the editor's starting data from GET /ppq/v1/editor-data, themes Ant Design
 * from the shell's tokens, and keeps popups inside the shell. It returns a
 * function that unmounts the editor.
 *
 * The remount rule: after the quiz's first save the editor stays open and
 * calls nav.edit( 'quiz', id, { replace: true } ); a new question or bank
 * calls nav.afterCreate( type, id ) and, if the host keeps it mounted, goes
 * on editing the new entity. A host must not remount for the ID an editor
 * just reported in onSaved.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { createRoot, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { ConfigProvider, Spin, Alert } from 'antd';
import QuizEditor from '../quiz-editor/components/QuizEditor';
import QuestionEditor from '../question-editor/components/QuestionEditor';
import BankEditor from '../bank-editor/components/BankEditor';
import { getPpqTheme } from '../utils/ppqTheme';
import { debugError } from '../utils/debug';

const EDITORS = {
	quiz: QuizEditor,
	question: QuestionEditor,
	bank: BankEditor,
};

const NAV_METHODS = ['list', 'edit', 'afterCreate', 'editUrl', 'back'];

// Optional: an editor shows the control that uses one only when the host
// supplies it. create( type, initial ) opens a new entity's editor (the bank
// editor's "New question", with { bankIds }).
const OPTIONAL_NAV_METHODS = ['create'];

// Mounted roots by element, so mounting into the same element again replaces
// the editor there.
const mounted = new WeakMap();

/**
 * Fill a host's navigation adapter: a method it leaves out does nothing, and
 * editUrl() returns '' (the editor then shows no "open in a new tab" link).
 * Optional methods are passed through only when the host has them.
 *
 * @param {Object} nav Host adapter.
 * @return {Object} Adapter with every method.
 */
const normalizeNav = (nav) => {
	const adapter = {};
	NAV_METHODS.forEach((method) => {
		if (nav && typeof nav[method] === 'function') {
			adapter[method] = (...args) => nav[method](...args);
		} else {
			adapter[method] = 'editUrl' === method ? () => '' : () => {};
		}
	});
	OPTIONAL_NAV_METHODS.forEach((method) => {
		if (nav && typeof nav[method] === 'function') {
			adapter[method] = (...args) => nav[method](...args);
		}
	});
	return adapter;
};

/**
 * Fetches the editor data, then renders the editor.
 *
 * @param {Object}  props           Component props.
 * @param {string}  props.type      'quiz', 'question' or 'bank'.
 * @param {number}  props.id        Entity ID; 0 for new.
 * @param {string}  props.surface   'dashboard' or 'admin'.
 * @param {Object}  props.options   The rest of mount()'s options.
 * @param {Element} props.container The element the editor is mounted in.
 * @return {JSX.Element|null} The editor, a loading state or an error.
 */
const MountedEditor = ({ type, id, surface, options, container }) => {
	const [data, setData] = useState(null);
	const [failed, setFailed] = useState(false);
	const [nav] = useState(() => normalizeNav(options.nav));

	useEffect(() => {
		let active = true;

		apiFetch({ path: `/ppq/v1/editor-data/${type}/${id}?surface=${surface}` })
			.then((response) => {
				if (active) {
					setData(response && typeof response === 'object' ? response : {});
				}
			})
			.catch((error) => {
				if (!active) {
					return;
				}
				debugError('Failed to load the editor data:', error);
				setFailed(true);
				// The host shows its own state (e.g. not found) instead of
				// an error inside the editor.
				if (typeof options.onError === 'function') {
					try {
						options.onError({
							status: error?.data?.status || 0,
							code: error?.code || '',
						});
					} catch (callbackError) {
						debugError('onError callback failed:', callbackError);
					}
				}
			});

		return () => {
			active = false;
		};
		// Mount-time only: a host changes the entity by mounting again.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	if (failed) {
		if (typeof options.onError === 'function') {
			return null;
		}
		return (
			<Alert
				type="error"
				showIcon
				message={__('This editor couldn’t be loaded. Reload the page to try again.', 'pressprimer-quiz')}
			/>
		);
	}

	if (!data) {
		return (
			<div className="ppq-editor-mount-loading" style={{ textAlign: 'center', padding: '48px' }}>
				<Spin size="large" />
			</div>
		);
	}

	const Editor = EDITORS[type];

	return (
		<ConfigProvider
			theme={getPpqTheme(container)}
			getPopupContainer={() => container.closest('.ppq-shell') || document.body}
		>
			<Editor
				key={`${type}-${id || 'new'}`}
				data={data}
				surface={surface}
				nav={nav}
				slots={options.slots && typeof options.slots === 'object' ? options.slots : {}}
				initial={options.initial && typeof options.initial === 'object' ? options.initial : {}}
				onSaved={options.onSaved}
				onDirtyChange={options.onDirtyChange}
			/>
		</ConfigProvider>
	);
};

/**
 * Mount an editor.
 *
 * @param {Element} el                    Element to render into.
 * @param {Object}  options               Options.
 * @param {string}  options.type          'quiz', 'question' or 'bank'.
 * @param {number}  options.id            Entity ID; 0 (the default) for new.
 * @param {string}  options.surface       'dashboard' (the default) or 'admin'.
 * @param {Object}  options.nav           Navigation adapter: list, edit, afterCreate, editUrl, back; optional create.
 * @param {Object}  options.initial       Preset values for a new entity (question: bankIds; quiz: templateId).
 * @param {Object}  options.slots         Host slot definitions by slot id.
 * @param {Function} options.onSaved       Called after each save with the saved event's detail.
 * @param {Function} options.onError       Called with { status, code } when the editor data can't be loaded.
 * @param {Function} options.onDirtyChange Called with true or false when unsaved changes appear or go.
 * @return {Function} Unmounts the editor.
 */
export const mount = (el, options = {}) => {
	if (!el || typeof el.appendChild !== 'function') {
		throw new TypeError('PPQ.editors.mount() needs an element to render into.');
	}

	const type = options.type;
	if (!EDITORS[type]) {
		throw new TypeError(`PPQ.editors.mount(): unknown editor type "${type}".`);
	}

	const id = parseInt(options.id, 10) > 0 ? parseInt(options.id, 10) : 0;
	const surface = 'admin' === options.surface ? 'admin' : 'dashboard';

	if (mounted.has(el)) {
		mounted.get(el)();
	}

	const root = createRoot(el);
	root.render(
		<MountedEditor
			type={type}
			id={id}
			surface={surface}
			options={options}
			container={el}
		/>
	);

	let active = true;
	const unmount = () => {
		if (!active) {
			return;
		}
		active = false;
		mounted.delete(el);
		root.unmount();
	};

	mounted.set(el, unmount);

	return unmount;
};
