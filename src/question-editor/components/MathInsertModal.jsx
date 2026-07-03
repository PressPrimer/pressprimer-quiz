/**
 * Math Insert Modal
 *
 * A small dialog for inserting LaTeX math notation: a LaTeX input, an
 * inline/block choice, and a live KaTeX preview. On insert it emits the
 * delimited string (\( ... \) inline, \[ ... \] block) for the editor to place
 * at the cursor. The live preview uses the bundled KaTeX runtime
 * (window.katex), which is loaded on the editor page when math is enabled.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Modal, Input, Radio, Typography } from 'antd';

const { Text } = Typography;

/**
 * Escape HTML-special characters in the LaTeX body so the stored markup is not
 * misparsed (e.g. `a < b`). The text node value KaTeX reads decodes back to the
 * original characters at render time.
 *
 * @param {string} str LaTeX body.
 * @return {string} Escaped body.
 */
const escapeHtml = (str) =>
	str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/**
 * Recognized math delimiter pairs (longest first) with the display mode each
 * implies.
 */
const DELIMITERS = [
	{ left: '$$', right: '$$', display: true },
	{ left: '\\[', right: '\\]', display: true },
	{ left: '\\(', right: '\\)', display: false },
	{ left: '$', right: '$', display: false },
];

/**
 * Strip one layer of recognized delimiters from the input.
 *
 * KaTeX's render() expects a bare expression, and the editor adds its own
 * delimiters on insert — so input that already carries delimiters (e.g. a
 * formula pasted from documentation) must be normalized, or the preview would
 * error and the inserted markup would be double-wrapped.
 *
 * @param {string} input Raw input.
 * @return {{ body: string, display: (boolean|null) }} The delimiter-free body
 *         and the display mode the delimiter implies (null when there was none).
 */
const stripDelimiters = (input) => {
	const s = (input || '').trim();
	for (const d of DELIMITERS) {
		if (
			s.length > d.left.length + d.right.length &&
			s.startsWith(d.left) &&
			s.endsWith(d.right)
		) {
			return {
				body: s.slice(d.left.length, s.length - d.right.length).trim(),
				display: d.display,
			};
		}
	}
	return { body: s, display: null };
};

/**
 * @param {Object}   props          Component props.
 * @param {boolean}  props.open     Whether the dialog is open.
 * @param {Function} props.onClose  Close handler.
 * @param {Function} props.onInsert Called with the delimited LaTeX string.
 * @return {JSX.Element} The dialog.
 */
const MathInsertModal = ({ open, onClose, onInsert }) => {
	const [latex, setLatex] = useState('');
	const [mode, setMode] = useState('inline');
	const previewRef = useRef(null);

	// Re-render the live preview whenever the input or mode changes. The body is
	// stripped of any delimiters first so KaTeX receives a bare expression.
	useEffect(() => {
		const el = previewRef.current;
		if (!open || !el) {
			return;
		}
		const { body } = stripDelimiters(latex);
		if (!body || !window.katex?.render) {
			el.textContent = '';
			return;
		}
		try {
			window.katex.render(body, el, {
				throwOnError: false,
				errorColor: '#cc0000',
				displayMode: mode === 'block',
				trust: false,
				strict: 'warn',
				maxSize: 100,
				maxExpand: 1000,
			});
		} catch (e) {
			el.textContent = '';
		}
	}, [latex, mode, open]);

	// When the input arrives already delimited (e.g. pasted), match the
	// inline/block choice to the delimiter so the preview and inserted markup
	// agree. The radio remains user-adjustable afterward.
	useEffect(() => {
		const { display } = stripDelimiters(latex);
		if (null !== display) {
			setMode(display ? 'block' : 'inline');
		}
	}, [latex]);

	const reset = () => {
		setLatex('');
		setMode('inline');
	};

	const handleInsert = () => {
		const { body } = stripDelimiters(latex);
		if (!body) {
			return;
		}
		const escaped = escapeHtml(body);
		const delimited =
			mode === 'block' ? `\\[${escaped}\\]` : `\\(${escaped}\\)`;
		onInsert(delimited);
		reset();
	};

	const handleCancel = () => {
		reset();
		onClose();
	};

	return (
		<Modal
			title={__('Insert math', 'pressprimer-quiz')}
			open={open}
			onCancel={handleCancel}
			onOk={handleInsert}
			okText={__('Insert', 'pressprimer-quiz')}
			okButtonProps={{ disabled: !latex.trim() }}
			destroyOnClose
		>
			<Radio.Group
				value={mode}
				onChange={(e) => setMode(e.target.value)}
				style={{ marginBottom: 12 }}
			>
				<Radio value="inline">{__('Inline', 'pressprimer-quiz')}</Radio>
				<Radio value="block">
					{__('Block (centered)', 'pressprimer-quiz')}
				</Radio>
			</Radio.Group>
			<Text
				type="secondary"
				style={{ display: 'block', marginBottom: 12, fontSize: 12 }}
			>
				{__(
					'Inline flows within a line of text; block sits centered on its own line.',
					'pressprimer-quiz'
				)}
			</Text>
			<Input.TextArea
				value={latex}
				onChange={(e) => setLatex(e.target.value)}
				placeholder={__(
					'e.g. x^2 + \\frac{a}{b}',
					'pressprimer-quiz'
				)}
				rows={3}
				/* eslint-disable-next-line jsx-a11y/no-autofocus */
				autoFocus
			/>
			<div style={{ marginTop: 12 }}>
				<Text type="secondary" style={{ fontSize: 12 }}>
					{__('Preview', 'pressprimer-quiz')}
				</Text>
				<div
					ref={previewRef}
					className="ppq-math-preview"
					style={{
						minHeight: 40,
						padding: 8,
						marginTop: 4,
						border: '1px solid #f0f0f0',
						borderRadius: 4,
						overflowX: 'auto',
					}}
				/>
			</div>
		</Modal>
	);
};

export default MathInsertModal;
