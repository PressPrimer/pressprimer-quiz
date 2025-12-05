/**
 * Email Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	Switch,
	Typography,
	Button,
	message,
	Space,
} from 'antd';
import { SendOutlined, PictureOutlined, DeleteOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;
const { TextArea } = Input;

/**
 * Copy text to clipboard with fallback for non-HTTPS contexts
 */
const copyToClipboard = (text) => {
	// Try modern clipboard API first
	if (navigator.clipboard && window.isSecureContext) {
		return navigator.clipboard.writeText(text);
	}

	// Fallback for non-HTTPS (like local WordPress admin)
	const textArea = document.createElement('textarea');
	textArea.value = text;
	textArea.style.position = 'fixed';
	textArea.style.left = '-999999px';
	textArea.style.top = '-999999px';
	document.body.appendChild(textArea);
	textArea.focus();
	textArea.select();

	return new Promise((resolve, reject) => {
		if (document.execCommand('copy')) {
			resolve();
		} else {
			reject(new Error('execCommand failed'));
		}
		textArea.remove();
	});
};

/**
 * Token item with click-to-copy functionality
 */
const TokenItem = ({ token, description }) => {
	const handleCopy = async () => {
		try {
			await copyToClipboard(token);
			message.success(__('Copied!', 'pressprimer-quiz'));
		} catch (err) {
			message.error(__('Failed to copy', 'pressprimer-quiz'));
		}
	};

	return (
		<Paragraph style={{ marginBottom: 4 }}>
			<Text
				code
				onClick={handleCopy}
				style={{ cursor: 'pointer' }}
				title={__('Click to copy', 'pressprimer-quiz')}
			>
				{token}
			</Text>{' '}
			- {description}
		</Paragraph>
	);
};

/**
 * Email Tab - Email notification settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 * @param {Object} props.settingsData Full settings data
 */
const EmailTab = ({ settings, updateSetting, settingsData }) => {
	const [testEmail, setTestEmail] = useState(settingsData.defaults?.adminEmail || '');
	const [sendingTest, setSendingTest] = useState(false);

	const defaultEmailBody = `{results_summary}

Hi {first_name},

You recently completed the quiz "{quiz_title}".

Here are your results:
- Score: {score}
- Status: {passed}
- Date: {date}

Good luck with your studies!

{results_url}`;

	/**
	 * Open media library to select logo
	 */
	const handleSelectLogo = () => {
		const frame = wp.media({
			title: __('Select Email Logo', 'pressprimer-quiz'),
			button: { text: __('Use this image', 'pressprimer-quiz') },
			multiple: false,
			library: { type: 'image' },
		});

		frame.on('select', () => {
			const attachment = frame.state().get('selection').first().toJSON();
			updateSetting('email_logo_url', attachment.url);
			updateSetting('email_logo_id', attachment.id);
		});

		frame.open();
	};

	/**
	 * Remove selected logo
	 */
	const handleRemoveLogo = () => {
		updateSetting('email_logo_url', '');
		updateSetting('email_logo_id', '');
	};

	/**
	 * Send test email
	 */
	const handleSendTestEmail = async () => {
		if (!testEmail || !testEmail.includes('@')) {
			message.error(__('Please enter a valid email address', 'pressprimer-quiz'));
			return;
		}

		setSendingTest(true);
		try {
			const response = await apiFetch({
				path: '/ppq/v1/email/test',
				method: 'POST',
				data: { email: testEmail },
			});

			if (response.success) {
				message.success(__('Test email sent successfully!', 'pressprimer-quiz'));
			} else {
				message.error(response.message || __('Failed to send test email', 'pressprimer-quiz'));
			}
		} catch (err) {
			message.error(err.message || __('Failed to send test email', 'pressprimer-quiz'));
		} finally {
			setSendingTest(false);
		}
	};

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
						label={__('Email Logo', 'pressprimer-quiz')}
						help={__('Logo displayed at the top of emails. Max width 400px, max height 150px.', 'pressprimer-quiz')}
					>
						{settings.email_logo_url ? (
							<div style={{ display: 'flex', alignItems: 'flex-start', gap: 16 }}>
								<div style={{
									border: '1px solid #d9d9d9',
									borderRadius: 8,
									padding: 12,
									background: '#fafafa',
									maxWidth: 200,
								}}>
									<img
										src={settings.email_logo_url}
										alt={__('Email logo', 'pressprimer-quiz')}
										style={{ maxWidth: '100%', maxHeight: 100, display: 'block' }}
									/>
								</div>
								<Button
									icon={<DeleteOutlined />}
									onClick={handleRemoveLogo}
									danger
								>
									{__('Remove', 'pressprimer-quiz')}
								</Button>
							</div>
						) : (
							<Button
								icon={<PictureOutlined />}
								onClick={handleSelectLogo}
							>
								{__('Select Logo', 'pressprimer-quiz')}
							</Button>
						)}
					</Form.Item>
				</div>

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
					<Text type="secondary" style={{ marginLeft: 8 }}>
						{__('(click to copy)', 'pressprimer-quiz')}
					</Text>
					<div style={{ marginTop: 8 }}>
						<TokenItem
							token="{results_summary}"
							description={__('Visual score box with percentage, correct/total, and pass/fail status', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{first_name}"
							description={__("Student's first name", 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{quiz_title}"
							description={__('Quiz name', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{score}"
							description={__('Score percentage', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{passed}"
							description={__('"Passed" or "Failed"', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{date}"
							description={__('Completion date', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{points}"
							description={__('Points earned', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{max_points}"
							description={__('Maximum points', 'pressprimer-quiz')}
						/>
						<TokenItem
							token="{results_url}"
							description={__('Button that links to full results', 'pressprimer-quiz')}
						/>
					</div>
				</div>
			</div>

			{/* Test Email Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Test Email', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Send a test email to verify your email settings are working correctly.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Send Test Email To', 'pressprimer-quiz')}
					>
						<Space.Compact style={{ maxWidth: 400 }}>
							<Input
								type="email"
								value={testEmail}
								onChange={(e) => setTestEmail(e.target.value)}
								placeholder={__('Enter email address', 'pressprimer-quiz')}
								style={{ width: 280 }}
							/>
							<Button
								type="primary"
								icon={<SendOutlined />}
								onClick={handleSendTestEmail}
								loading={sendingTest}
							>
								{__('Send Test', 'pressprimer-quiz')}
							</Button>
						</Space.Compact>
						<div style={{ marginTop: 8 }}>
							<Text type="secondary">
								{__('A sample email will be sent using your current template settings.', 'pressprimer-quiz')}
							</Text>
						</div>
					</Form.Item>
				</div>
			</div>
		</div>
	);
};

export default EmailTab;
