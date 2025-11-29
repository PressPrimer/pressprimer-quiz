/**
 * Sharing Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	Switch,
	Input,
	Typography,
	Space,
} from 'antd';
import {
	TwitterOutlined,
	FacebookOutlined,
	LinkedinOutlined,
} from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;
const { TextArea } = Input;

/**
 * Sharing Tab - Social sharing settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 */
const SharingTab = ({ settings, updateSetting }) => {
	return (
		<div>
			{/* Social Sharing Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Social Sharing', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Control which social networks students can share their quiz results to. All options are disabled by default.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item label={__('Enabled Platforms', 'pressprimer-quiz')}>
						<Space direction="vertical" size="middle">
							<div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
								<Switch
									checked={settings.social_sharing_twitter || false}
									onChange={(checked) => updateSetting('social_sharing_twitter', checked)}
								/>
								<TwitterOutlined style={{ fontSize: 18, color: '#1DA1F2' }} />
								<Text>{__('Twitter / X', 'pressprimer-quiz')}</Text>
							</div>

							<div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
								<Switch
									checked={settings.social_sharing_facebook || false}
									onChange={(checked) => updateSetting('social_sharing_facebook', checked)}
								/>
								<FacebookOutlined style={{ fontSize: 18, color: '#4267B2' }} />
								<Text>{__('Facebook', 'pressprimer-quiz')}</Text>
							</div>

							<div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
								<Switch
									checked={settings.social_sharing_linkedin || false}
									onChange={(checked) => updateSetting('social_sharing_linkedin', checked)}
								/>
								<LinkedinOutlined style={{ fontSize: 18, color: '#0A66C2' }} />
								<Text>{__('LinkedIn', 'pressprimer-quiz')}</Text>
							</div>
						</Space>
					</Form.Item>
				</div>
			</div>

			{/* Share Message Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Share Message', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Customize the message that appears when students share their results.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item label={__('Include Score in Share', 'pressprimer-quiz')}>
						<Switch
							checked={settings.social_sharing_include_score ?? true}
							onChange={(checked) => updateSetting('social_sharing_include_score', checked)}
						/>
						<Text type="secondary" style={{ marginLeft: 12 }}>
							{__('Include score percentage in shared message', 'pressprimer-quiz')}
						</Text>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Share Message Template', 'pressprimer-quiz')}
					>
						<TextArea
							value={settings.social_sharing_message || 'I just completed {quiz_title}!'}
							onChange={(e) => updateSetting('social_sharing_message', e.target.value)}
							rows={3}
							style={{ maxWidth: 500 }}
						/>
					</Form.Item>
				</div>

				{/* Available Tokens */}
				<div className="ppq-token-list">
					<Text strong>{__('Available Tokens:', 'pressprimer-quiz')}</Text>
					<div style={{ marginTop: 8 }}>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{quiz_title}'}</Text> - {__('Quiz name', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 4 }}>
							<Text code>{'{score}'}</Text> - {__('Score percentage (only if "Include Score" is enabled)', 'pressprimer-quiz')}
						</Paragraph>
						<Paragraph style={{ marginBottom: 0 }}>
							<Text code>{'{pass_status}'}</Text> - {__('"Passed" or "Failed"', 'pressprimer-quiz')}
						</Paragraph>
					</div>
				</div>
			</div>
		</div>
	);
};

export default SharingTab;
