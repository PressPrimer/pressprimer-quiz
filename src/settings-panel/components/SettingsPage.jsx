/**
 * Settings Page - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	message,
	Spin,
} from 'antd';
import {
	SettingOutlined,
	ApiOutlined,
	MailOutlined,
	InfoCircleOutlined,
	ToolOutlined,
	SaveOutlined,
	BgColorsOutlined,
} from '@ant-design/icons';

import GeneralTab from './GeneralTab';
import AppearanceTab from './AppearanceTab';
import IntegrationsTab from './IntegrationsTab';
import EmailTab from './EmailTab';
import StatusTab from './StatusTab';
import AdvancedTab from './AdvancedTab';

/**
 * Tab configuration
 */
const TABS = [
	{
		id: 'general',
		label: __('General', 'pressprimer-quiz'),
		icon: <SettingOutlined />,
		component: GeneralTab,
	},
	{
		id: 'appearance',
		label: __('Appearance', 'pressprimer-quiz'),
		icon: <BgColorsOutlined />,
		component: AppearanceTab,
	},
	{
		id: 'integrations',
		label: __('Integrations', 'pressprimer-quiz'),
		icon: <ApiOutlined />,
		component: IntegrationsTab,
	},
	{
		id: 'email',
		label: __('Email', 'pressprimer-quiz'),
		icon: <MailOutlined />,
		component: EmailTab,
	},
	{
		id: 'status',
		label: __('Status', 'pressprimer-quiz'),
		icon: <InfoCircleOutlined />,
		component: StatusTab,
	},
	{
		id: 'advanced',
		label: __('Advanced', 'pressprimer-quiz'),
		icon: <ToolOutlined />,
		component: AdvancedTab,
	},
];

/**
 * Settings Page Component
 *
 * @param {Object} props Component props
 * @param {Object} props.settingsData Initial settings data
 */
const SettingsPage = ({ settingsData = {} }) => {
	const [activeTab, setActiveTab] = useState('general');
	const [settings, setSettings] = useState(settingsData.settings || {});
	const [saving, setSaving] = useState(false);
	const [hasChanges, setHasChanges] = useState(false);

	// Lift API key status state to persist across tab navigation
	const [apiKeyStatus, setApiKeyStatus] = useState(settingsData.apiKeyStatus || { configured: false });
	const [apiModels, setApiModels] = useState(settingsData.apiModels || []);

	/**
	 * Update a setting value
	 */
	const updateSetting = useCallback((key, value) => {
		setSettings(prev => ({
			...prev,
			[key]: value,
		}));
		setHasChanges(true);
	}, []);

	/**
	 * Save all settings
	 */
	const handleSave = async () => {
		try {
			setSaving(true);

			const response = await apiFetch({
				path: '/ppq/v1/settings',
				method: 'POST',
				data: settings,
			});

			if (response.success) {
				message.success(__('Settings saved successfully!', 'pressprimer-quiz'));
				setHasChanges(false);
			} else {
				message.error(response.message || __('Failed to save settings.', 'pressprimer-quiz'));
			}
		} catch (error) {
			console.error('Save error:', error);
			message.error(error.message || __('Failed to save settings.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Get the active tab component
	 */
	const ActiveTabComponent = TABS.find(tab => tab.id === activeTab)?.component || GeneralTab;

	// Get the plugin URL from localized data
	const pluginUrl = window.ppqSettingsData?.pluginUrl || '';

	return (
		<div className="ppq-settings-container">
			{/* Header */}
			<div className="ppq-settings-header">
				<div className="ppq-settings-header-content">
					<h1>{__('Settings', 'pressprimer-quiz')}</h1>
					<p>{__('Configure your quiz plugin settings, integrations, and preferences.', 'pressprimer-quiz')}</p>
				</div>
				<img
					src={`${pluginUrl}assets/images/construction-mascot.png`}
					alt=""
					className="ppq-settings-header-mascot"
				/>
			</div>

			{/* Main Layout */}
			<div className="ppq-settings-layout">
				{/* Vertical Tab Navigation */}
				<nav className="ppq-settings-tabs">
					{TABS.map(tab => (
						<button
							key={tab.id}
							type="button"
							className={`ppq-settings-tab ${activeTab === tab.id ? 'ppq-settings-tab--active' : ''}`}
							onClick={() => setActiveTab(tab.id)}
						>
							{tab.icon}
							<span>{tab.label}</span>
						</button>
					))}
				</nav>

				{/* Tab Content */}
				<div className="ppq-settings-content">
					<Spin spinning={saving} tip={__('Saving...', 'pressprimer-quiz')}>
						<ActiveTabComponent
							settings={settings}
							updateSetting={updateSetting}
							settingsData={settingsData}
							apiKeyStatus={apiKeyStatus}
							setApiKeyStatus={setApiKeyStatus}
							apiModels={apiModels}
							setApiModels={setApiModels}
						/>

						{/* Save Button Footer */}
						<div className="ppq-settings-footer">
							<Button
								type="primary"
								size="large"
								icon={<SaveOutlined />}
								onClick={handleSave}
								loading={saving}
								disabled={!hasChanges}
							>
								{__('Save Settings', 'pressprimer-quiz')}
							</Button>
						</div>
					</Spin>
				</div>
			</div>
		</div>
	);
};

export default SettingsPage;
