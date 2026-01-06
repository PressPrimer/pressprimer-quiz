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
 * General Tab - Quiz defaults and general settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 */
const GeneralTab = ({ settings, updateSetting }) => {
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
		</div>
	);
};

export default GeneralTab;
