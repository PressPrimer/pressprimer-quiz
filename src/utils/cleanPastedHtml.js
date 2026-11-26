/**
 * Clean pasted HTML
 *
 * Text pasted into a question's stem or answers from Word, Google Docs, web
 * pages or AI tools carries wrapper markup and attributes the quiz doesn't
 * use. The question editors run this as TinyMCE's paste_preprocess, after
 * TinyMCE's own Word and Google Docs filter. It:
 *
 * - removes class, id, data-* (except data-ppq-attachment-id), dir, lang and
 *   style attributes;
 * - unwraps <span>s left with no attributes, and every <font>;
 * - turns <div>s into paragraphs (a <div> holding blocks is unwrapped, so
 *   nested ones flatten);
 * - drops <meta>, <style>, <script>, <link>, <title> and comments;
 * - collapses runs of non-breaking spaces to one space.
 *
 * Headings, lists, tables, links, images, inline formatting and math
 * delimiters (as text) are kept. stylePluginImages() then gives a plugin
 * image back the centring the image button gives it (paste_postprocess,
 * after every filter that removes styles, WordPress's own included).
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

const STRIPPED_ATTRIBUTES = ['class', 'id', 'dir', 'lang', 'style'];

// The style the editors' image button puts on a plugin image.
const PLUGIN_IMAGE_STYLE = 'display: block; margin: 0 auto;';

const DROPPED_ELEMENTS = 'meta, style, script, link, title';

// Elements a <div> can't become a paragraph around.
const BLOCK_ELEMENTS = [
	'ADDRESS', 'ARTICLE', 'ASIDE', 'BLOCKQUOTE', 'DD', 'DIV', 'DL', 'DT',
	'FIELDSET', 'FIGURE', 'FOOTER', 'FORM', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
	'HEADER', 'HR', 'LI', 'MAIN', 'NAV', 'OL', 'P', 'PRE', 'SECTION', 'TABLE',
	'UL',
];

/**
 * Replace an element with its children.
 *
 * @param {Element} element Element to unwrap.
 */
const unwrap = (element) => {
	element.replaceWith(...Array.from(element.childNodes));
};

/**
 * Remove the attributes pasted content shouldn't keep.
 *
 * @param {Element} element Element to clean.
 */
const stripAttributes = (element) => {
	Array.from(element.attributes).forEach((attribute) => {
		const name = attribute.name.toLowerCase();

		if ('data-ppq-attachment-id' === name) {
			return;
		}

		if (STRIPPED_ATTRIBUTES.includes(name) || name.startsWith('data-')) {
			element.removeAttribute(attribute.name);
		}
	});
};

/**
 * Clean pasted HTML.
 *
 * @param {string} html Pasted HTML.
 * @return {string} Cleaned HTML.
 */
export const cleanPastedHtml = (html) => {
	if ('string' !== typeof html || '' === html) {
		return html;
	}

	// A template's content is inert: nothing in it loads or runs.
	const template = document.createElement('template');
	template.innerHTML = html;
	const root = template.content;

	// Comments, then the elements that don't belong in a question.
	const comments = [];
	const commentWalker = document.createTreeWalker(root, NodeFilter.SHOW_COMMENT);
	while (commentWalker.nextNode()) {
		comments.push(commentWalker.currentNode);
	}
	comments.forEach((comment) => comment.remove());
	root.querySelectorAll(DROPPED_ELEMENTS).forEach((element) => element.remove());

	root.querySelectorAll('*').forEach(stripAttributes);

	// Innermost first, so a wrapper's children are settled before it is.
	Array.from(root.querySelectorAll('span, font')).reverse().forEach((element) => {
		if ('FONT' === element.tagName || 0 === element.attributes.length) {
			unwrap(element);
		}
	});

	Array.from(root.querySelectorAll('div')).reverse().forEach((div) => {
		if (Array.from(div.children).some((child) => BLOCK_ELEMENTS.includes(child.tagName))) {
			unwrap(div);
			return;
		}
		const paragraph = document.createElement('p');
		paragraph.append(...Array.from(div.childNodes));
		div.replaceWith(paragraph);
	});

	// Runs of non-breaking spaces (with or without plain spaces) become one space.
	const textWalker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
	while (textWalker.nextNode()) {
		const node = textWalker.currentNode;
		if (node.nodeValue.indexOf('\u00A0') !== -1) {
			node.nodeValue = node.nodeValue.replace(/[ \u00A0]{2,}/g, (run) => (run.indexOf('\u00A0') !== -1 ? ' ' : run));
		}
	}

	return template.innerHTML;
};

/**
 * Give pasted plugin images (data-ppq-attachment-id) the centring style the
 * image button gives them.
 *
 * @param {Element} node The pasted content (TinyMCE's PastePostProcess node).
 */
export const stylePluginImages = (node) => {
	if (!node || 'function' !== typeof node.querySelectorAll) {
		return;
	}
	node.querySelectorAll('img[data-ppq-attachment-id]').forEach((image) => {
		image.setAttribute('style', PLUGIN_IMAGE_STYLE);
	});
};

export default cleanPastedHtml;
