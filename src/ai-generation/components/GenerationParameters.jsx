/**
 * Generation Parameters
 *
 * How many questions, of what types and levels, how many answers, which
 * categories they get, and whether the AI writes feedback.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { Checkbox, InputNumber, Select, Switch, Typography } from 'antd';
import { getDifficultyLevels, getTypeLabels } from '../utils';

const { Text } = Typography;

/**
 * Generation Parameters
 *
 * @param {Object}   props              Component props.
 * @param {Object}   props.values       { count, answerCount, types, difficulty, categories, generateFeedback }.
 * @param {Function} props.onChange     Called with a field and its value.
 * @param {number}   props.maxQuestions Most questions per generation.
 * @param {Object[]} props.categories   Categories ({ id, name }).
 * @return {JSX.Element} The parameters.
 */
const GenerationParameters = ({ values, onChange, maxQuestions, categories }) => {
	const typeOptions = Object.entries(getTypeLabels()).map(([value, label]) => ({ value, label }));
	const levelOptions = Object.entries(getDifficultyLevels()).map(([value, level]) => ({ value, label: level.label }));

	return (
		<div className="ppq-ai-parameters">
			<Text strong className="ppq-ai-parameters-title">{__('Generation Parameters', 'pressprimer-quiz')}</Text>

			<div className="ppq-ai-parameters-grid">
				<div className="ppq-ai-param">
					<label htmlFor="ppq-ai-count"><Text strong>{__('Number of Questions', 'pressprimer-quiz')}</Text></label>
					<InputNumber
						id="ppq-ai-count"
						min={1}
						max={maxQuestions}
						precision={0}
						value={values.count}
						onChange={(value) => onChange('count', value || 1)}
						style={{ width: 120 }}
					/>
					<Text type="secondary" className="ppq-ai-hint">
						{sprintf(
							/* translators: %d: most questions per generation. */
							__('1–%d', 'pressprimer-quiz'),
							maxQuestions
						)}
					</Text>
				</div>

				<div className="ppq-ai-param">
					<label htmlFor="ppq-ai-answer-count"><Text strong>{__('Answers per Question', 'pressprimer-quiz')}</Text></label>
					<InputNumber
						id="ppq-ai-answer-count"
						min={3}
						max={6}
						precision={0}
						value={values.answerCount}
						onChange={(value) => onChange('answerCount', value || 4)}
						style={{ width: 120 }}
					/>
					<Text type="secondary" className="ppq-ai-hint">{__('3–6 (for multiple choice and multiple answer)', 'pressprimer-quiz')}</Text>
				</div>

				<div className="ppq-ai-param">
					<Text strong id="ppq-ai-types-label">{__('Question Types', 'pressprimer-quiz')}</Text>
					<Checkbox.Group
						aria-labelledby="ppq-ai-types-label"
						className="ppq-ai-checkboxes"
						options={typeOptions}
						value={values.types}
						onChange={(value) => onChange('types', value)}
					/>
				</div>

				<div className="ppq-ai-param">
					<Text strong id="ppq-ai-difficulty-label">{__('Difficulty Levels', 'pressprimer-quiz')}</Text>
					<Checkbox.Group
						aria-labelledby="ppq-ai-difficulty-label"
						className="ppq-ai-checkboxes"
						options={levelOptions}
						value={values.difficulty}
						onChange={(value) => onChange('difficulty', value)}
					/>
				</div>

				<div className="ppq-ai-param ppq-ai-param--wide">
					<label htmlFor="ppq-ai-categories"><Text strong>{__('Assign Categories', 'pressprimer-quiz')}</Text></label>
					<Select
						id="ppq-ai-categories"
						mode="multiple"
						allowClear
						optionFilterProp="label"
						placeholder={__('No categories', 'pressprimer-quiz')}
						options={categories.map((category) => ({ value: Number(category.id), label: category.name }))}
						value={values.categories}
						onChange={(value) => onChange('categories', value)}
						style={{ width: '100%', maxWidth: 400 }}
					/>
					<Text type="secondary" className="ppq-ai-hint">{__('The questions you add to the bank get these categories.', 'pressprimer-quiz')}</Text>
				</div>

				<div className="ppq-ai-param ppq-ai-param--wide">
					<div className="ppq-ai-switch">
						<Switch
							id="ppq-ai-generate-feedback"
							checked={values.generateFeedback}
							onChange={(checked) => onChange('generateFeedback', checked)}
						/>
						<label htmlFor="ppq-ai-generate-feedback">
							{__('Generate correct and incorrect question feedback', 'pressprimer-quiz')}
						</label>
					</div>
					<Text type="secondary" className="ppq-ai-hint">
						{__('Disabling saves tokens but questions will have no feedback text.', 'pressprimer-quiz')}
					</Text>
				</div>
			</div>
		</div>
	);
};

export default GenerationParameters;
