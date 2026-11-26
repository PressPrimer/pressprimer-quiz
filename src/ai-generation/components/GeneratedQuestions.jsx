/**
 * Generated Questions
 *
 * The questions a generation returned: select, edit, remove or discard them,
 * and add the selected ones to the bank.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { forwardRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Alert, Button, Card, Checkbox, Popconfirm, Space, Tag, Tooltip, Typography } from 'antd';
import { CheckCircleFilled, CheckOutlined, DeleteOutlined, EditOutlined } from '@ant-design/icons';
import { getDifficultyLevels, getTypeLabels } from '../utils';

const { Text } = Typography;

/**
 * Generated Questions
 *
 * @param {Object}   props               Component props.
 * @param {Object[]} props.questions     Generated questions (each with a _key).
 * @param {string[]} props.selected      Keys of the selected questions.
 * @param {Object[]} props.notices       Notices about the generation ({ type, text }).
 * @param {boolean}  props.saving        Whether a save is running.
 * @param {Function} props.onSelect      Called with the new selected keys.
 * @param {Function} props.onEdit        Called with a question's key.
 * @param {Function} props.onRemove      Called with a question's key.
 * @param {Function} props.onDiscard     Called to discard them all.
 * @param {Function} props.onSave        Called to save the selected ones.
 * @param {Object}   ref                 Ref to the card (for scrolling).
 * @return {JSX.Element} The list.
 */
const GeneratedQuestions = forwardRef(({ questions, selected, notices, saving, onSelect, onEdit, onRemove, onDiscard, onSave }, ref) => {
	const typeLabels = getTypeLabels();
	const levels = getDifficultyLevels();

	const toggle = (key, checked) => {
		onSelect(checked ? [...selected, key] : selected.filter((item) => item !== key));
	};

	return (
		<div ref={ref} className="ppq-ai-results">
			<Card
				title={sprintf(
					/* translators: %d: number of generated questions. */
					__('Generated Questions (%d)', 'pressprimer-quiz'),
					questions.length
				)}
				extra={
					<Space wrap>
						<Button size="small" onClick={() => onSelect(questions.map((question) => question._key))}>
							{__('Select All', 'pressprimer-quiz')}
						</Button>
						<Button size="small" onClick={() => onSelect([])}>
							{__('Deselect All', 'pressprimer-quiz')}
						</Button>
						<Popconfirm
							title={__('Discard these generated questions?', 'pressprimer-quiz')}
							description={__('Questions you haven’t added to the bank are lost.', 'pressprimer-quiz')}
							onConfirm={onDiscard}
							okText={__('Discard', 'pressprimer-quiz')}
							cancelText={__('Cancel', 'pressprimer-quiz')}
						>
							<Button size="small" danger>
								{__('Discard All', 'pressprimer-quiz')}
							</Button>
						</Popconfirm>
					</Space>
				}
			>
				{notices.map((notice, index) => (
					'tokens' === notice.type ? (
						<Text key={index} type="secondary" className="ppq-ai-results-tokens">{notice.text}</Text>
					) : (
						<Alert key={index} type={notice.type} showIcon message={notice.text} className="ppq-ai-results-notice" />
					)
				))}

				<div className="ppq-ai-questions">
					{questions.map((question, index) => {
						const level = levels[question.difficulty];
						const isSelected = selected.includes(question._key);

						return (
							<div key={question._key} className={`ppq-ai-question${isSelected ? ' ppq-ai-question--selected' : ''}`}>
								<div className="ppq-ai-question-header">
									<Checkbox
										checked={isSelected}
										onChange={(event) => toggle(question._key, event.target.checked)}
									>
										<span className="ppq-ai-question-number">
											{sprintf(
												/* translators: %d: question number in the generated list. */
												__('Q%d', 'pressprimer-quiz'),
												index + 1
											)}
										</span>
									</Checkbox>
									<Space size={4} wrap className="ppq-ai-question-meta">
										<Tag>{typeLabels[question.type] || question.type}</Tag>
										{question.difficulty && (
											<Tag color={level?.color}>{level?.label || question.difficulty}</Tag>
										)}
									</Space>
									<Space size={4} className="ppq-ai-question-actions">
										<Button size="small" icon={<EditOutlined />} onClick={() => onEdit(question._key)}>
											{__('Edit', 'pressprimer-quiz')}
										</Button>
										<Tooltip title={__('Remove from this list', 'pressprimer-quiz')}>
											<Button
												size="small"
												danger
												icon={<DeleteOutlined />}
												aria-label={__('Remove from this list', 'pressprimer-quiz')}
												onClick={() => onRemove(question._key)}
											/>
										</Tooltip>
									</Space>
								</div>

								<div className="ppq-ai-question-body">
									<div className="ppq-ai-question-stem">{question.stem}</div>
									{/* Divs, not a list element: themes indent and number lists. */}
									<div className="ppq-ai-answers" role="list">
										{(question.answers || []).map((answer, answerIndex) => (
											<div
												key={answerIndex}
												role="listitem"
												className={`ppq-ai-answer${answer.is_correct ? ' ppq-ai-answer--correct' : ''}`}
											>
												<span className="ppq-ai-answer-letter" aria-hidden="true">
													{String.fromCharCode(65 + answerIndex)}
												</span>
												<span className="ppq-ai-answer-text">{answer.text}</span>
												{answer.is_correct && (
													<CheckCircleFilled
														className="ppq-ai-answer-correct"
														aria-label={__('Correct answer', 'pressprimer-quiz')}
													/>
												)}
											</div>
										))}
									</div>
								</div>
							</div>
						);
					})}
				</div>

				<div className="ppq-ai-save">
					<Button
						type="primary"
						icon={<CheckOutlined />}
						disabled={0 === selected.length}
						loading={saving}
						onClick={onSave}
					>
						{saving
							? __('Saving questions…', 'pressprimer-quiz')
							: sprintf(
								/* translators: %d: number of selected questions. */
								_n('Add %d Selected Question to Bank', 'Add %d Selected Questions to Bank', selected.length, 'pressprimer-quiz'),
								selected.length
							)}
					</Button>
				</div>
			</Card>
		</div>
	);
});

export default GeneratedQuestions;
