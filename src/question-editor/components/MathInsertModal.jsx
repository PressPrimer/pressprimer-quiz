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

	// Re-render the live preview whenever the input or mode changes.
	useEffect(() => {
		const el = previewRef.current;
		if (!open || !el) {
			return;
		}
		const trimmed = latex.trim();
		if (!trimmed || !window.katex?.render) {
			el.textContent = '';
			return;
		}
		try {
			window.katex.render(trimmed, el, {
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

	const reset = () => {
		setLatex('');
		setMode('inline');
	};

	const handleInsert = () => {
		const trimmed = latex.trim();
		if (!trimmed) {
			return;
		}
		const body = escapeHtml(trimmed);
		const delimited =
			mode === 'block' ? `\\[ ${body} \\]` : `\\( ${body} \\)`;
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
