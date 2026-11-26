/**
 * AI Generation Panel
 *
 * Generates questions from pasted text or an uploaded PDF or Word file,
 * lets the author review and edit them, and adds the chosen ones to a bank.
 * The wp-admin bank page and the dashboard bank editor (BankQuestionsPanel)
 * both render it; it talks only to the /ppq/v1/ai routes.
 *
 * After a save it fires ppq-bank-questions-added on document with
 * { bankId, questionIds } and calls onSaved with the same detail.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Alert, Button, Spin, Typography, message } from 'antd';
import { RobotOutlined, ThunderboltOutlined } from '@ant-design/icons';
import SourceInput from './SourceInput';
import GenerationParameters from './GenerationParameters';
import GeneratedQuestions from './GeneratedQuestions';
import EditGeneratedQuestionModal from './EditGeneratedQuestionModal';
import { describeError, describeTokens, formatFileSize, isSupportedFile } from '../utils';
import { emitEditorEvent, toId } from '../../editors/events';
import { debugError } from '../../utils/debug';

const { Text, Paragraph } = Typography;

// Each generated question gets a key that survives edits and removals.
let nextKey = 1;

/**
 * Scroll an element into view, if the browser can.
 *
 * @param {Object} ref React ref.
 */
const reveal = (ref) => {
	if (ref.current && typeof ref.current.scrollIntoView === 'function') {
		ref.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}
};

/**
 * AI Generation Panel
 *
 * @param {Object}   props         Component props.
 * @param {number}   props.bankId  Bank the questions go into.
 * @param {Function} props.onSaved Optional; called with { bankId, questionIds } after a save.
 * @return {JSX.Element} The panel.
 */
const AiGenerationPanel = ({ bankId, onSaved }) => {
	const [status, setStatus] = useState(null);
	const [statusFailed, setStatusFailed] = useState(false);
	const [usage, setUsage] = useState(null);
	const [categories, setCategories] = useState([]);

	const [tab, setTab] = useState('text');
	const [text, setText] = useState('');
	const [file, setFile] = useState(null);
	const [extracted, setExtracted] = useState(null);
	const [extracting, setExtracting] = useState(false);

	const [values, setValues] = useState({
		count: 5,
		answerCount: 4,
		types: ['mc'],
		difficulty: ['medium'],
		categories: [],
		generateFeedback: true,
	});

	const [generating, setGenerating] = useState(false);
	const [error, setError] = useState(null);
	const [questions, setQuestions] = useState([]);
	const [notices, setNotices] = useState([]);
	const [selected, setSelected] = useState([]);
	const [editingKey, setEditingKey] = useState(null);
	const [saving, setSaving] = useState(false);

	const resultsRef = useRef(null);
	const errorRef = useRef(null);

	useEffect(() => {
		let active = true;

		apiFetch({ path: '/ppq/v1/ai/status' })
			.then((response) => {
				if (active) {
					setStatus(response);
					setUsage(response.usage || null);
				}
			})
			.catch((fetchError) => {
				debugError('Failed to load the AI status:', fetchError);
				if (active) {
					setStatusFailed(true);
				}
			});

		// Categories are optional: without them the select is empty.
		apiFetch({ path: '/ppq/v1/taxonomies?type=category' })
			.then((response) => {
				if (active && Array.isArray(response)) {
					setCategories(response);
				}
			})
			.catch((fetchError) => debugError('Failed to load categories:', fetchError));

		return () => {
			active = false;
		};
	}, []);

	useEffect(() => {
		if (error) {
			reveal(errorRef);
		}
	}, [error]);

	if (statusFailed) {
		return <Alert type="error" showIcon message={__('AI generation couldn’t be loaded. Reload the page to try again.', 'pressprimer-quiz')} />;
	}

	if (!status) {
		return (
			<div style={{ textAlign: 'center', padding: '48px' }}>
				<Spin size="large" />
			</div>
		);
	}

	// No key: admins get a link to add one; everyone else, who to ask.
	if (!status.configured) {
		return (
			<div className="ppq-ai-panel ppq-ai-panel--no-key">
				<div className="ppq-ai-setup">
					<RobotOutlined className="ppq-ai-setup-icon" />
					<Text strong className="ppq-ai-setup-title">{__('AI Question Generation', 'pressprimer-quiz')}</Text>
					{status.can_manage_settings && status.settings_url ? (
						<>
							<Paragraph type="secondary">
								{__('Generate quiz questions from your course content with AI, using your own OpenAI or Anthropic key.', 'pressprimer-quiz')}
							</Paragraph>
							<Button type="primary" href={status.settings_url}>
								{__('Add an AI key in Settings → Integrations', 'pressprimer-quiz')}
							</Button>
						</>
					) : (
						<Paragraph type="secondary">
							{__('AI generation isn’t set up on this site. Ask your site administrator.', 'pressprimer-quiz')}
						</Paragraph>
					)}
				</div>
			</div>
		);
	}

	const maxLength = Number(status.max_content_length) || 250000;
	const maxQuestions = Number(status.max_questions) || 50;
	const limited = usage && null !== usage.limit && undefined !== usage.limit;
	const overLimit = limited && values.count > usage.remaining;

	const setValue = (field, value) => setValues((current) => ({ ...current, [field]: value }));

	/**
	 * Read a chosen file's text on the server.
	 *
	 * @param {File} chosen The file.
	 */
	const handleFile = async (chosen) => {
		setError(null);

		if (!isSupportedFile(chosen)) {
			setError({ message: __('Choose a PDF or Word (.docx) file.', 'pressprimer-quiz'), details: [] });
			return;
		}
		if (chosen.size > Number(status.max_file_size)) {
			setError({
				message: sprintf(
					/* translators: %s: largest file size (e.g. 50 MB). */
					__('This file is too large. The largest file you can upload is %s.', 'pressprimer-quiz'),
					formatFileSize(status.max_file_size)
				),
				details: [],
			});
			return;
		}

		setFile({ name: chosen.name, size: chosen.size });
		setExtracted(null);
		setExtracting(true);

		try {
			const body = new window.FormData();
			body.append('file', chosen);
			const response = await apiFetch({ path: '/ppq/v1/ai/extract', method: 'POST', body });
			setExtracted({ text: response.text || '', wasTruncated: !!response.was_truncated });
		} catch (extractError) {
			debugError('Failed to read the file:', extractError);
			setFile(null);
			setError(describeError(extractError));
		} finally {
			setExtracting(false);
		}
	};

	const handleGenerate = async () => {
		const content = 'text' === tab ? text.trim() : (extracted?.text || '');

		if (!content) {
			setError({ message: __('Please enter text or upload a file.', 'pressprimer-quiz'), details: [] });
			return;
		}

		setError(null);
		setGenerating(true);

		try {
			const response = await apiFetch({
				path: '/ppq/v1/ai/generate',
				method: 'POST',
				data: {
					content,
					count: values.count,
					types: values.types.length ? values.types : ['mc'],
					difficulty: values.difficulty.length ? values.difficulty : ['medium'],
					answer_count: values.answerCount,
					generate_feedback: values.generateFeedback,
					bank_id: bankId,
				},
			});

			const generated = (response.questions || []).map((question) => ({ ...question, _key: `q${nextKey++}` }));
			const next = [];

			if (response.content_info?.was_truncated) {
				next.push({
					type: 'info',
					text: sprintf(
						/* translators: %s: most characters read (e.g. 250,000). */
						__('Content was truncated to %s characters. Some text may not have been processed.', 'pressprimer-quiz'),
						maxLength.toLocaleString()
					),
				});
			}
			if (response.validation?.partial_success) {
				next.push({
					type: 'warning',
					text: sprintf(
						/* translators: 1: usable questions, 2: questions the AI returned. */
						__('%1$d of %2$d generated questions were usable. The others couldn’t be read and were left out.', 'pressprimer-quiz'),
						response.validation.valid_count,
						response.validation.total_generated
					),
				});
			}
			const tokens = describeTokens(response.token_usage || {});
			if (tokens) {
				next.push({ type: 'tokens', text: tokens });
			}

			setQuestions(generated);
			setNotices(next);
			setSelected([]);
			if (response.usage) {
				setUsage(response.usage);
			}

			// Wait for the list to render before scrolling to it.
			window.setTimeout(() => reveal(resultsRef), 0);
		} catch (generateError) {
			debugError('AI generation failed:', generateError);

			// At the limit, the usage line and its warning say so.
			const data = generateError?.data;
			if ('ppq_ai_limit_reached' === generateError?.code && data && null !== data.limit && undefined !== data.limit) {
				setUsage((current) => ({ ...current, limit: data.limit, units: data.used, remaining: data.remaining }));
			} else {
				setError(describeError(generateError));
			}
		} finally {
			setGenerating(false);
		}
	};

	const handleSave = async () => {
		const toSave = questions.filter((question) => selected.includes(question._key));

		if (!toSave.length) {
			setError({ message: __('Please select at least one question to save.', 'pressprimer-quiz'), details: [] });
			return;
		}

		setError(null);
		setSaving(true);

		try {
			const response = await apiFetch({
				path: `/ppq/v1/banks/${bankId}/ai-questions`,
				method: 'POST',
				data: {
					questions: toSave.map(({ _key, ...question }) => question),
					categories: values.categories,
				},
			});

			// Questions that didn't save stay in the list, still selected.
			const failedKeys = (response.failed || []).map((index) => toSave[index]?._key).filter(Boolean);
			const savedKeys = toSave.map((question) => question._key).filter((key) => !failedKeys.includes(key));

			setQuestions((current) => current.filter((question) => !savedKeys.includes(question._key)));
			setSelected(failedKeys);

			message.success(response.message || __('Questions saved to bank!', 'pressprimer-quiz'));
			if (response.errors?.length) {
				setError({ message: __('Some questions couldn’t be saved.', 'pressprimer-quiz'), details: response.errors });
			}

			const questionIds = (response.question_ids || []).map(toId).filter(Boolean);
			if (questionIds.length) {
				const detail = { bankId: toId(bankId), questionIds };
				emitEditorEvent('ppq-bank-questions-added', detail);
				if (typeof onSaved === 'function') {
					onSaved(detail);
				}
			}
		} catch (saveError) {
			debugError('Failed to save the generated questions:', saveError);
			setError(describeError(saveError));
		} finally {
			setSaving(false);
		}
	};

	const editing = questions.find((question) => question._key === editingKey) || null;

	return (
		<div className="ppq-ai-panel">
			<SourceInput
				tab={tab}
				onTabChange={setTab}
				text={text}
				onTextChange={setText}
				maxLength={maxLength}
				maxFileSize={Number(status.max_file_size) || 0}
				file={file}
				extracted={extracted}
				extracting={extracting}
				onFile={handleFile}
				onRemoveFile={() => {
					setFile(null);
					setExtracted(null);
				}}
			/>

			<GenerationParameters
				values={values}
				onChange={setValue}
				maxQuestions={maxQuestions}
				categories={categories}
			/>

			<div className="ppq-ai-actions">
				{limited && (
					<Text type="secondary" className="ppq-ai-usage">
						{sprintf(
							/* translators: 1: AI questions used this month, 2: monthly limit. */
							__('You’ve used %1$d of %2$d AI questions this month.', 'pressprimer-quiz'),
							usage.units,
							usage.limit
						)}
					</Text>
				)}
				{overLimit && (
					<Alert
						type="warning"
						showIcon
						className="ppq-ai-usage-alert"
						message={usage.remaining > 0
							? sprintf(
								/* translators: 1: questions left this month, 2: the date the limit resets. */
								_n(
									'You can generate %1$d more question this month. Your limit resets on %2$s.',
									'You can generate %1$d more questions this month. Your limit resets on %2$s.',
									usage.remaining,
									'pressprimer-quiz'
								),
								usage.remaining,
								usage.resets_on
							)
							: sprintf(
								/* translators: 1: monthly limit, 2: the date the limit resets. */
								__('You’ve used all %1$d of your AI questions for this month. Your limit resets on %2$s.', 'pressprimer-quiz'),
								usage.limit,
								usage.resets_on
							)}
					/>
				)}

				<Button
					type="primary"
					size="large"
					icon={<ThunderboltOutlined />}
					loading={generating}
					disabled={overLimit || extracting || saving}
					onClick={handleGenerate}
					className="ppq-ai-generate-button"
				>
					{generating ? __('Generating questions…', 'pressprimer-quiz') : __('Generate Questions', 'pressprimer-quiz')}
				</Button>

				{generating ? (
					<Text type="secondary" className="ppq-ai-note">
						{__('This may take a few minutes for large content. Please wait…', 'pressprimer-quiz')}
					</Text>
				) : (
					<Text type="secondary" className="ppq-ai-note">
						{sprintf(
							/* translators: %s: the AI provider (e.g. OpenAI). */
							__('Using %s for generation.', 'pressprimer-quiz'),
							status.provider_label
						)}
					</Text>
				)}
			</div>

			{error && (
				<div ref={errorRef}>
					<Alert
						type="error"
						showIcon
						closable
						onClose={() => setError(null)}
						className="ppq-ai-error"
						message={error.message}
						description={error.details?.length ? (
							<ul className="ppq-ai-error-details">
								{error.details.map((detail, index) => <li key={index}>{detail}</li>)}
							</ul>
						) : null}
					/>
				</div>
			)}

			{questions.length > 0 && (
				<GeneratedQuestions
					ref={resultsRef}
					questions={questions}
					selected={selected}
					notices={notices}
					saving={saving}
					onSelect={setSelected}
					onEdit={setEditingKey}
					onRemove={(key) => {
						setQuestions((current) => current.filter((question) => question._key !== key));
						setSelected((current) => current.filter((item) => item !== key));
					}}
					onDiscard={() => {
						setQuestions([]);
						setSelected([]);
						setNotices([]);
					}}
					onSave={handleSave}
				/>
			)}

			<EditGeneratedQuestionModal
				question={editing}
				onCancel={() => setEditingKey(null)}
				onSave={(changed) => {
					setQuestions((current) => current.map((question) => (question._key === changed._key ? changed : question)));
					setEditingKey(null);
				}}
			/>
		</div>
	);
};

export default AiGenerationPanel;
