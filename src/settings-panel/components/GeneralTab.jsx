/**
 * General Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	InputNumber,
	Radio,
	Select,
	Input,
	Typography,
	Space,
} from 'antd';

const { Title, Paragraph } = Typography;
const { TextArea } = Input;

/**
 * Description shown under the Default Multiple-Answer Scoring field for each
 * mode. Keyed by the internal mode string so the help text can update without
 * imperative JS — React re-renders whenever the Select value changes.
 */
const MA_SCORING_DESCRIPTIONS = {
	right_minus_wrong: __('Each wrong selection cancels out one correct selection. Score never goes below zero.', 'pressprimer-quiz'),
	proportional: __('Each correct selection earns proportional credit. Wrong selections are ignored.', 'pressprimer-quiz'),
	partial_no_wrong: __('Each correct selection earns proportional credit, but any wrong selection results in zero.', 'pressprimer-quiz'),
	all_or_nothing: __('Full credit only when every correct answer is selected and no wrong answers are selected.', 'pressprimer-quiz'),
};

/**
 * General Tab - Quiz defaults and general settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 */
const GeneralTab = ({ settings, updateSetting }) => {
	const maScoringMode = settings.default_ma_scoring || 'right_minus_wrong';

	return (
		<div>
			{/* Quiz Defaults Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Quiz Defaults', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Default values used when creating new quizzes.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Default Passing Score', 'pressprimer-quiz')}
						help={__('Percentage score required to pass quizzes (0-100).', 'pressprimer-quiz')}
					>
						<InputNumber
							min={0}
							max={100}
							value={settings.default_passing_score ?? 70}
							onChange={(value) => updateSetting('default_passing_score', value)}
							addonAfter="%"
							style={{ width: 150 }}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Default Quiz Mode', 'pressprimer-quiz')}
						help={__('Default mode for new quizzes.', 'pressprimer-quiz')}
					>
						<Select
							value={settings.default_quiz_mode || 'tutorial'}
							onChange={(value) => updateSetting('default_quiz_mode', value)}
							style={{ width: 300 }}
							options={[
								{
									value: 'tutorial',
									label: __('Tutorial Mode (immediate feedback)', 'pressprimer-quiz'),
								},
								{
									value: 'timed',
									label: __('Test Mode (feedback at end)', 'pressprimer-quiz'),
								},
							]}
						/>
					</Form.Item>
				</div>
			</div>

			{/* Guest Access Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Guest Access', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Control how guests (non-logged-in users) can access quizzes.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Default Access Mode', 'pressprimer-quiz')}
						help={__('Default access mode for new quizzes. Can be overridden per quiz.', 'pressprimer-quiz')}
					>
						<Radio.Group
							value={settings.default_access_mode || 'guest_optional'}
							onChange={(e) => updateSetting('default_access_mode', e.target.value)}
						>
							<Space direction="vertical">
								<Radio value="guest_optional">
									{__('Allow guests (email optional)', 'pressprimer-quiz')}
								</Radio>
								<Radio value="guest_required">
									{__('Allow guests (email required)', 'pressprimer-quiz')}
								</Radio>
								<Radio value="login_required">
									{__('Require login', 'pressprimer-quiz')}
								</Radio>
							</Space>
						</Radio.Group>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Login Message', 'pressprimer-quiz')}
						help={__('Message shown to guests when login is required.', 'pressprimer-quiz')}
					>
						<TextArea
							value={settings.login_message_default || __('Please log in to take this quiz.', 'pressprimer-quiz')}
							onChange={(e) => updateSetting('login_message_default', e.target.value)}
							rows={2}
							style={{ maxWidth: 500 }}
						/>
					</Form.Item>
				</div>
			</div>

			{/* Scoring Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Scoring', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('How multiple-answer questions are scored across the site. Individual quizzes can override this default.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Default Multiple-Answer Scoring', 'pressprimer-quiz')}
						help={MA_SCORING_DESCRIPTIONS[maScoringMode]}
					>
						<Select
							value={maScoringMode}
							onChange={(value) => updateSetting('default_ma_scoring', value)}
							style={{ width: 300 }}
							options={[
								{
									value: 'right_minus_wrong',
									label: __('Right Minus Wrong', 'pressprimer-quiz'),
								},
								{
									value: 'proportional',
									label: __('Partial Credit', 'pressprimer-quiz'),
								},
								{
									value: 'partial_no_wrong',
									label: __('Partial Credit, No Wrong Answers', 'pressprimer-quiz'),
								},
								{
									value: 'all_or_nothing',
									label: __('All or Nothing', 'pressprimer-quiz'),
								},
							]}
						/>
					</Form.Item>
				</div>
			</div>
		</div>
	);
};

export default GeneralTab;
