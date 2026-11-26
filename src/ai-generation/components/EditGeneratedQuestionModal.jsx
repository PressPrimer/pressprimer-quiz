/**
 * Edit Generated Question Modal
 *
 * Changes one generated question before it's saved: its text, answers,
 * which answers are correct, and its feedback.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Modal, Input, Radio, Checkbox, Typography, Alert } from 'antd';

const { TextArea } = Input;
const { Text } = Typography;

/**
 * Edit Generated Question Modal
 *
 * @param {Object}      props          Component props.
 * @param {Object|null} props.question The question being edited (null: closed).
 * @param {Function}    props.onSave   Called with the changed question.
 * @param {Function}    props.onCancel Called to close without changes.
 * @return {JSX.Element} The dialog.
 */
const EditGeneratedQuestionModal = ({ question, onSave, onCancel }) => {
	const [draft, setDraft] = useState(null);
	const [problem, setProblem] = useState('');

	useEffect(() => {
		setDraft(question ? {
			...question,
			answers: (question.answers || []).map((answer) => ({ ...answer })),
		} : null);
		setProblem('');
	}, [question]);

	if (!draft) {
		return null;
	}

	const multiple = 'ma' === draft.type;

	const setField = (field, value) => setDraft((current) => ({ ...current, [field]: value }));

	const setAnswerText = (index, text) => setDraft((current) => ({
		...current,
		answers: current.answers.map((answer, i) => (i === index ? { ...answer, text } : answer)),
	}));

	const setCorrect = (index, checked) => setDraft((current) => ({
		...current,
		answers: current.answers.map((answer, i) => {
			if (multiple) {
				return i === index ? { ...answer, is_correct: checked } : answer;
			}
			return { ...answer, is_correct: i === index };
		}),
	}));

	const handleSave = () => {
		if (!String(draft.stem || '').trim()) {
			setProblem(__('Enter the question text.', 'pressprimer-quiz'));
			return;
		}
		if (draft.answers.some((answer) => !String(answer.text || '').trim())) {
			setProblem(__('Every answer needs text.', 'pressprimer-quiz'));
			return;
		}
		if (!draft.answers.some((answer) => answer.is_correct)) {
			setProblem(__('Mark at least one answer correct.', 'pressprimer-quiz'));
			return;
		}
		onSave(draft);
	};

	const answerRow = (answer, index) => (
		<div className="ppq-ai-edit-answer" key={index}>
			{multiple ? (
				<Checkbox
					checked={!!answer.is_correct}
					onChange={(event) => setCorrect(index, event.target.checked)}
					aria-label={sprintf(
						/* translators: %d: answer number. */
						__('Answer %d is correct', 'pressprimer-quiz'),
						index + 1
					)}
				/>
			) : (
				<Radio
					value={index}
					aria-label={sprintf(
						/* translators: %d: answer number. */
						__('Answer %d is correct', 'pressprimer-quiz'),
						index + 1
					)}
				/>
			)}
			<Input
				value={answer.text}
				onChange={(event) => setAnswerText(index, event.target.value)}
				aria-label={sprintf(
					/* translators: %d: answer number. */
					__('Answer %d', 'pressprimer-quiz'),
					index + 1
				)}
			/>
		</div>
	);

	const correctIndex = draft.answers.findIndex((answer) => answer.is_correct);

	return (
		<Modal
			open
			title={__('Edit Question', 'pressprimer-quiz')}
			onOk={handleSave}
			onCancel={onCancel}
			okText={__('Save Changes', 'pressprimer-quiz')}
			cancelText={__('Cancel', 'pressprimer-quiz')}
			width={640}
			className="ppq-ai-edit-modal"
		>
			<div className="ppq-ai-edit-field">
				<label htmlFor="ppq-ai-edit-stem">
					<Text strong>{__('Question Text', 'pressprimer-quiz')}</Text>
				</label>
				<TextArea
					id="ppq-ai-edit-stem"
					rows={4}
					value={draft.stem}
					onChange={(event) => setField('stem', event.target.value)}
				/>
			</div>

			<div className="ppq-ai-edit-field">
				<Text strong>{__('Answers', 'pressprimer-quiz')}</Text>
				<Text type="secondary" className="ppq-ai-hint">
					{multiple
						? __('Tick every correct answer.', 'pressprimer-quiz')
						: __('Choose the correct answer.', 'pressprimer-quiz')}
				</Text>
				{multiple ? (
					draft.answers.map(answerRow)
				) : (
					<Radio.Group
						value={correctIndex}
						onChange={(event) => setCorrect(event.target.value, true)}
						className="ppq-ai-edit-answers"
					>
						{draft.answers.map(answerRow)}
					</Radio.Group>
				)}
			</div>

			<div className="ppq-ai-edit-field">
				<label htmlFor="ppq-ai-edit-feedback-correct">
					<Text strong>{__('Feedback (Correct)', 'pressprimer-quiz')}</Text>
				</label>
				<TextArea
					id="ppq-ai-edit-feedback-correct"
					rows={2}
					value={draft.feedback_correct || ''}
					onChange={(event) => setField('feedback_correct', event.target.value)}
				/>
			</div>

			<div className="ppq-ai-edit-field">
				<label htmlFor="ppq-ai-edit-feedback-incorrect">
					<Text strong>{__('Feedback (Incorrect)', 'pressprimer-quiz')}</Text>
				</label>
				<TextArea
					id="ppq-ai-edit-feedback-incorrect"
					rows={2}
					value={draft.feedback_incorrect || ''}
					onChange={(event) => setField('feedback_incorrect', event.target.value)}
				/>
			</div>

			{problem && <Alert type="error" showIcon message={problem} />}
		</Modal>
	);
};

export default EditGeneratedQuestionModal;
