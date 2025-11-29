/**
 * Email Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	Input,
	Switch,
	Typography,
} from 'antd';

const { Title, Paragraph, Text } = Typography;
const { TextArea } = Input;

/**
 * Email Tab - Email notification settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 * @param {Object} props.settingsData Full settings data
 */
const EmailTab = ({ settings, updateSetting, settingsData }) => {
	const defaultEmailBody = `Hi {student_name},

You recently completed the quiz "{quiz_title}".

Here are your results:
- Score: {score}
- Status: {passed}
- Date: {date}

Click the button below to view your full results and review your answers.

Good luck with your studies!`;

	return (
		<div>
			{/* Email Settings Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Email Settings', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Configure email notifications sent by the plugin.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('From Name', 'pressprimer-quiz')}
						help={__('Name shown in the "From" field of emails sent by the plugin.', 'pressprimer-quiz')}
					>
						<Input
							value={settings.email_from_name || settingsData.defaults?.siteName || ''}
							onChange={(e) => updateSetting('email_from_name', e.target.value)}
							placeholder={settingsData.defaults?.siteName || ''}
							style={{ maxWidth: 400 }}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('From Email Address', 'pressprimer-quiz')}
						help={__('Email address shown in the "From" field of emails sent by the plugin.', 'pressprimer-quiz')}
					>
						<Input
							type="email"
							value={settings.email_from_email || settingsData.defaults?.adminEmail || ''}
							onChange={(e) => updateSetting('email_from_email', e.target.value)}
							placeholder={settingsData.defaults?.adminEmail || ''}
							style={{ maxWidth: 400 }}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Auto-send Results', 'pressprimer-quiz')}
					>
						<Switch
							checked={settings.email_results_auto_send || false}
							onChange={(checked) => updateSetting('email_results_auto_send', checked)}
						/>
						<Text type="secondary" style={{ marginLeft: 12 }}>
							{__('Automatically email results to students when they complete a quiz', 'pressprimer-quiz')}
						</Text>
					</Form.Item>
				</div>
			</div>

			{/* Email Templates Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Results Email Template', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Customize the email sent to students with their quiz results.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Subject Line', 'pressprimer-quiz')}
					>
						<Input
							value={settings.email_results_subject || __('Your results for {quiz_title}', 'pressprimer-quiz')}
							onChange={(e) => updateSetting('email_results_subject', e.target.value)}
							style={{ maxWidth: 500 }}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Email Body', 'pressprimer-quiz')}
					>
						<TextArea
							value={settings.email_results_body || defaultEmailBody}
							onChange={(e) => updateSetting('email_results_body', e.target.value)}
							rows={12}
							style={{ fontFamily: 'monospace' }}
						/>
					</Form.Item>
				</div>

				{/* Available Tokens */}
				<div className="ppq-token-list">
					<Text strong>{__('Available Tokens:', 'pressprimer-quiz')}</Text>
					<div style={{ marginTop: 8 }}>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{student_name}'}</Text> - {__('Student name', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{quiz_title}'}</Text> - {__('Quiz name', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{score}'}</Text> - {__('Score percentage', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{passed}'}</Text> - {__('"Passed" or "Failed"', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{date}'}</Text> - {__('Completion date', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{points}'}</Text> - {__('Points earned', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{max_points}'}</Text> - {__('Maximum points', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 0 }}>
							<Text code>{'{results_url}'}</Text> - {__('Link to view full results', 'pressprimer-quiz')}
						</Paragraph>
					</div>
				</div>
			</div>
		</div>
	);
};

export default EmailTab;
