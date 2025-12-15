/**
 * Advanced Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	Switch,
	Typography,
	Alert,
} from 'antd';
import {
	WarningOutlined,
} from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Advanced Tab - Advanced settings and danger zone
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 */
const AdvancedTab = ({ settings, updateSetting }) => {
	// wp_localize_script converts booleans to strings ("1" for true, "" for false)
	// PHP now sends 1 or 0 as integers, but they may be converted to strings "1" or "0"
	// CRITICAL: Only enable if explicitly set to truthy value - default is ALWAYS off
	const isRemoveDataEnabled = settings.remove_data_on_uninstall === true ||
		settings.remove_data_on_uninstall === '1' ||
		settings.remove_data_on_uninstall === 1;

	return (
		<div>
			{/* Data Management Section */}
			<div className="ppq-settings-section ppq-danger-zone">
				<Title level={4} className="ppq-settings-section-title">
					<WarningOutlined style={{ marginRight: 8 }} />
					{__('Danger Zone', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('These settings can result in permanent data loss. Use with caution.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item label={__('Remove Data on Uninstall', 'pressprimer-quiz')}>
						<Switch
							checked={isRemoveDataEnabled}
							onChange={(checked) => updateSetting('remove_data_on_uninstall', checked)}
						/>
						<Text type="secondary" style={{ marginLeft: 12 }}>
							{__('Remove all plugin data when uninstalling', 'pressprimer-quiz')}
						</Text>
					</Form.Item>

					<Alert
						message={__('Warning', 'pressprimer-quiz')}
						description={
							<>
								<Paragraph style={{ marginBottom: 8 }}>
									{__('If enabled, uninstalling this plugin will permanently delete:', 'pressprimer-quiz')}
								</Paragraph>
								<ul style={{ marginBottom: 8, paddingLeft: 20 }}>
									<li>{__('All quizzes', 'pressprimer-quiz')}</li>
									<li>{__('All questions and answers', 'pressprimer-quiz')}</li>
									<li>{__('All question banks', 'pressprimer-quiz')}</li>
									<li>{__('All student attempts and results', 'pressprimer-quiz')}</li>
									<li>{__('All plugin settings', 'pressprimer-quiz')}</li>
								</ul>
								<Paragraph style={{ marginBottom: 0 }}>
									<strong>{__('This action cannot be undone!', 'pressprimer-quiz')}</strong>
								</Paragraph>
							</>
						}
						type="warning"
						showIcon
						icon={<WarningOutlined />}
					/>

					<Paragraph type="secondary" style={{ marginTop: 12 }}>
						{__('By default, data is preserved when you uninstall the plugin to prevent accidental data loss. Only enable this if you are certain you want to completely remove all data.', 'pressprimer-quiz')}
					</Paragraph>
				</div>
			</div>
		</div>
	);
};

export default AdvancedTab;
