/**
 * Settings Page - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debugError } from '../../utils/debug';
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
	ExperimentOutlined,
	SkinOutlined,
	AuditOutlined,
} from '@ant-design/icons';

import GeneralTab from './GeneralTab';
import AppearanceTab from './AppearanceTab';
import IntegrationsTab from './IntegrationsTab';
import EmailTab from './EmailTab';
import StatusTab from './StatusTab';
import AdvancedTab from './AdvancedTab';

/**
 * Icon map for addon tabs
 */
const ADDON_ICONS = {
	xapi: <ExperimentOutlined />,
	'white-label': <SkinOutlined />,
	'audit-log': <AuditOutlined />,
	default: <SettingOutlined />,
};

/**
 * Core tab configuration (built into free plugin)
 * These tabs have React components in this plugin.
 * Order values match the server-side settingsTabs filter.
 */
const CORE_TABS = [
	{
		id: 'general',
		label: __('General', 'pressprimer-quiz'),
		icon: <SettingOutlined />,
		component: GeneralTab,
		order: 10,
	},
	{
		id: 'appearance',
		label: __('Appearance', 'pressprimer-quiz'),
		icon: <BgColorsOutlined />,
		component: AppearanceTab,
		order: 20,
	},
	{
		id: 'email',
		label: __('Email', 'pressprimer-quiz'),
		icon: <MailOutlined />,
		component: EmailTab,
		order: 30,
	},
	{
		id: 'integrations',
		label: __('Integrations', 'pressprimer-quiz'),
		icon: <ApiOutlined />,
		component: IntegrationsTab,
		order: 50,
	},
	{
		id: 'status',
		label: __('Status', 'pressprimer-quiz'),
		icon: <InfoCircleOutlined />,
		component: StatusTab,
		order: 90,
	},
	{
		id: 'advanced',
		label: __('Advanced', 'pressprimer-quiz'),
		icon: <ToolOutlined />,
		component: AdvancedTab,
		order: 100,
	},
];

/**
 * Tab IDs that exist in the server-side settingsTabs filter but are
 * handled by existing core tab components (not addon tabs).
 * These are tabs like 'ai' and 'integration' that may be in server config
 * but their content is part of an existing React component.
 */
const IGNORED_SERVER_TABS = ['ai', 'integration'];

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
	 * Build combined tabs from core tabs and addon tabs from settingsTabs
	 * Addon tabs are those in settingsTabs that:
	 * 1. Don't have a matching core component (not in CORE_TABS by id)
	 * 2. Are not in IGNORED_SERVER_TABS (legacy tabs handled elsewhere)
	 * 3. Have isAddon: true in their config (explicitly marked as addon tab)
	 */
	const allTabs = useMemo(() => {
		const serverTabs = settingsData.settingsTabs || {};
		const combined = [];

		// Add core tabs with their components (these are the main tabs)
		CORE_TABS.forEach(coreTab => {
			combined.push({
				...coreTab,
				isAddon: false,
			});
		});

		// Add addon tabs - only those explicitly marked with isAddon: true
		// This prevents legacy server tabs (ai, integration, educator) from showing as blank
		const coreIds = CORE_TABS.map(t => t.id);
		Object.entries(serverTabs).forEach(([id, tabConfig]) => {
			// Skip if it's a core tab or ignored server tab
			if (coreIds.includes(id) || IGNORED_SERVER_TABS.includes(id)) {
				return;
			}

			// Only add if explicitly marked as addon tab
			// Addon plugins should set isAddon: true when registering their tab
			if (tabConfig.isAddon === true) {
				combined.push({
					id,
					label: tabConfig.label || id,
					icon: ADDON_ICONS[id] || ADDON_ICONS.default,
					component: null, // Addon tabs render via mount points
					order: tabConfig.order ?? 50,
					isAddon: true,
				});
			}
		});

		// Sort by order
		combined.sort((a, b) => a.order - b.order);

		return combined;
	}, [settingsData.settingsTabs]);

	/**
	 * Check if active tab is an addon tab
	 */
	const activeTabConfig = allTabs.find(tab => tab.id === activeTab);
	const isAddonTab = activeTabConfig?.isAddon ?? false;

	/**
	 * Dispatch custom event when addon tab becomes active
	 * Addon scripts can listen for this to mount their React components
	 */
	useEffect(() => {
		if (isAddonTab) {
			const event = new CustomEvent('ppq-settings-addon-tab-active', {
				detail: { tab: activeTab }
			});
			window.dispatchEvent(event);
		}
	}, [activeTab, isAddonTab]);

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
			debugError('Failed to save settings:', error);
			message.error(error.message || __('Failed to save settings.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Get the active tab component (for core tabs)
	 */
	const ActiveTabComponent = activeTabConfig?.component || null;

	// Get the plugin URL from localized data
	const pluginUrl = window.pressprimerQuizSettingsData?.pluginUrl || '';

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
					{allTabs.map(tab => (
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
					{/* Core tab content */}
					{!isAddonTab && ActiveTabComponent && (
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
					)}

					{/* Addon tab mount points - addons render their React content here */}
					{allTabs.filter(t => t.isAddon).map(tab => (
						<div
							key={tab.id}
							id={`ppq-settings-addon-${tab.id}`}
							className="ppq-settings-addon-content"
							style={{ display: activeTab === tab.id ? 'block' : 'none' }}
						/>
					))}
				</div>
			</div>
		</div>
	);
};

export default SettingsPage;
