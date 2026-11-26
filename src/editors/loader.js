/**
 * Portable editors loader (build/editors-loader.js, handle ppq-editors-loader)
 *
 * A small script a dashboard page carries instead of the editors bundle.
 * PPQ.editors.load() adds the editors' stylesheets, translations and script
 * the first time a host needs an editor, then the extension handles' scripts,
 * and resolves with window.PPQ.editors once mount() exists.
 *
 * pressprimer_quiz_enqueue_portable_editors() prints the manifest it reads
 * (window.pressprimerQuizEditorsManifest) and enqueues the editors'
 * WordPress dependencies with it, so the bundle finds them on the page.
 *
 * Style order: the editors' stylesheets go just before the shell's inline
 * style (#ppq-shell-inline-css), so the site's custom shell CSS (Enterprise
 * white-label) still wins ties with them.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { setLocaleData } from '@wordpress/i18n';

let loading = null;

/**
 * The manifest printed with this script.
 *
 * @return {Object} Manifest.
 */
const getManifest = () => window.pressprimerQuizEditorsManifest || {};

/**
 * Apply a script's translations, as WordPress does for an enqueued script.
 *
 * @param {Object|null} translations { domain, data } (Jed locale data).
 */
const applyTranslations = (translations) => {
	if (!translations || !translations.domain || !translations.data || !translations.data.locale_data) {
		return;
	}
	const { domain, data } = translations;
	const localeData = data.locale_data[domain] || data.locale_data.messages;
	if (!localeData || !localeData['']) {
		return;
	}
	localeData[''].domain = domain;
	setLocaleData(localeData, domain);
};

/**
 * Add a stylesheet before the shell's inline style (or at the end of head).
 *
 * @param {Object} style { id, href }.
 * @return {Promise} Resolves once it loads or fails (styles never block).
 */
const addStyle = (style) => new Promise((resolve) => {
	if (!style || !style.href || document.getElementById(style.id)) {
		resolve();
		return;
	}
	const link = document.createElement('link');
	link.rel = 'stylesheet';
	link.id = style.id;
	link.href = style.href;
	link.onload = () => resolve();
	link.onerror = () => resolve();

	const anchor = document.getElementById(getManifest().styleAnchor || 'ppq-shell-inline-css');
	if (anchor && anchor.parentNode) {
		anchor.parentNode.insertBefore(link, anchor);
	} else {
		document.head.appendChild(link);
	}
});

/**
 * Add a script and wait for it to run.
 *
 * @param {Object} script { id, src }.
 * @return {Promise} Resolves once it has run; rejects if it fails to load.
 */
const addScript = (script) => new Promise((resolve, reject) => {
	const element = document.createElement('script');
	element.id = script.id;
	element.src = script.src;
	element.async = false;
	element.onload = () => resolve();
	element.onerror = () => reject(new Error(`PressPrimer Quiz couldn't load ${script.src}`));
	document.body.appendChild(element);
});

/**
 * Load an extension handle (its styles, translations and script). A failing
 * extension is skipped, so the editors still load.
 *
 * @param {Object} extension { handle, src, styles, translations }.
 * @return {Promise} Resolves when it has run or failed.
 */
const loadExtension = (extension) =>
	Promise.all((extension.styles || []).map(addStyle))
		.then(() => {
			applyTranslations(extension.translations);
			return addScript({ id: `${extension.handle}-js`, src: extension.src });
		})
		.catch(() => {});

/**
 * Load the editors once.
 *
 * @return {Promise<Object>} Resolves with window.PPQ.editors.
 */
const load = () => {
	const editors = window.PPQ.editors;
	if (typeof editors.mount === 'function') {
		return Promise.resolve(editors);
	}
	if (loading) {
		return loading;
	}

	const manifest = getManifest();
	if (!manifest.src) {
		return Promise.reject(new Error('PressPrimer Quiz: the editors aren’t available on this page.'));
	}

	loading = Promise.all((manifest.styles || []).map(addStyle))
		.then(() => {
			// Before the bundle runs, so its first render is translated.
			applyTranslations(manifest.translations);
			return addScript({ id: 'ppq-editors-js', src: manifest.src });
		})
		.then(() => {
			if (typeof window.PPQ.editors.mount !== 'function') {
				throw new Error('PressPrimer Quiz: the editors didn’t start.');
			}
			// In order, so an extension can rely on one listed before it.
			return (manifest.extensions || []).reduce(
				(chain, extension) => chain.then(() => loadExtension(extension)),
				Promise.resolve()
			);
		})
		.then(() => window.PPQ.editors);

	// A failed load can be tried again (e.g. after a network blip).
	loading.catch(() => {
		loading = null;
		const failed = document.getElementById('ppq-editors-js');
		if (failed && typeof window.PPQ.editors.mount !== 'function') {
			failed.remove();
		}
	});

	return loading;
};

window.PPQ = window.PPQ || {};
window.PPQ.editors = window.PPQ.editors || {};
window.PPQ.editors.load = load;
