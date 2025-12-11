/**
 * Integrations Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	Button,
	Select,
	Progress,
	Typography,
	Space,
	Alert,
	Descriptions,
	Tag,
	Spin,
	Collapse,
} from 'antd';
import {
	CheckCircleOutlined,
	WarningOutlined,
	EyeOutlined,
	EyeInvisibleOutlined,
	ReloadOutlined,
	DeleteOutlined,
	LockOutlined,
	BookOutlined,
	SettingOutlined,
} from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Integrations Tab - API keys and third-party integrations
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 * @param {Object} props.settingsData Full settings data including API status
 * @param {Object} props.apiKeyStatus API key status (lifted state from parent)
 * @param {Function} props.setApiKeyStatus Function to update API key status
 * @param {Array} props.apiModels Available API models (lifted state from parent)
 * @param {Function} props.setApiModels Function to update API models
 */
const IntegrationsTab = ({ settings, updateSetting, settingsData, apiKeyStatus, setApiKeyStatus, apiModels, setApiModels }) => {
	const [showApiKey, setShowApiKey] = useState(false);
	const [newApiKey, setNewApiKey] = useState('');
	const [savingKey, setSavingKey] = useState(false);
	const [validatingKey, setValidatingKey] = useState(false);
	const [clearingKey, setClearingKey] = useState(false);
	const [loadingModels, setLoadingModels] = useState(false);
	const [validationResult, setValidationResult] = useState(null);
	const [selectedModel, setSelectedModel] = useState(settingsData.modelPref || '');

	// Use lifted state from parent, with fallback to settingsData for backwards compatibility
	const apiStatus = apiKeyStatus || settingsData.apiKeyStatus || { configured: false };
	const setApiStatus = setApiKeyStatus || (() => {});
	const models = apiModels || settingsData.apiModels || [];
	const setModels = setApiModels || (() => {});

	// LMS Integration states - use pre-loaded data from PHP
	const lmsStatus = settingsData.lmsStatus || {};
	const [learndashStatus, setLearndashStatus] = useState(
		lmsStatus.learndash?.active ? { active: true, version: lmsStatus.learndash.version } : { active: false }
	);
	const [loadingLearndash, setLoadingLearndash] = useState(lmsStatus.learndash?.active || false);
	const [learndashSettings, setLearndashSettings] = useState({
		restriction_message: '',
	});
	const [savingLearndash, setSavingLearndash] = useState(false);

	const [tutorlmsStatus, setTutorlmsStatus] = useState(
		lmsStatus.tutorlms?.active ? { active: true, version: lmsStatus.tutorlms.version } : { active: false }
	);
	const [loadingTutorlms, setLoadingTutorlms] = useState(lmsStatus.tutorlms?.active || false);

	const [lifterlmsStatus, setLifterlmsStatus] = useState(
		lmsStatus.lifterlms?.active ? { active: true, version: lmsStatus.lifterlms.version } : { active: false }
	);
	const [loadingLifterlms, setLoadingLifterlms] = useState(lmsStatus.lifterlms?.active || false);

	const usageData = settingsData.usageData || {
		requests_this_hour: 0,
		requests_remaining: 20,
		rate_limit: 20,
		usage_percent: 0,
	};

	// Fetch LearnDash extended status only if LearnDash is active
	useEffect(() => {
		if (!lmsStatus.learndash?.active) {
			setLoadingLearndash(false);
			return;
		}

		const fetchLearndashStatus = async () => {
			try {
				const response = await apiFetch({
					path: '/ppq/v1/learndash/status',
					method: 'GET',
				});

				if (response.success) {
					setLearndashStatus(response.status);
					if (response.settings) {
						setLearndashSettings(response.settings);
					}
				}
			} catch (error) {
				// Keep the basic status from PHP
				console.error('Failed to fetch LearnDash extended status:', error);
			} finally {
				setLoadingLearndash(false);
			}
		};

		fetchLearndashStatus();
	}, [lmsStatus.learndash?.active]);

	// Fetch TutorLMS extended status only if TutorLMS is active
	useEffect(() => {
		if (!lmsStatus.tutorlms?.active) {
			setLoadingTutorlms(false);
			return;
		}

		const fetchTutorlmsStatus = async () => {
			try {
				const response = await apiFetch({
					path: '/ppq/v1/tutorlms/status',
					method: 'GET',
				});

				if (response.success) {
					setTutorlmsStatus(response.status);
				}
			} catch (error) {
				// Keep the basic status from PHP
				console.error('Failed to fetch TutorLMS extended status:', error);
			} finally {
				setLoadingTutorlms(false);
			}
		};

		fetchTutorlmsStatus();
	}, [lmsStatus.tutorlms?.active]);

	// Fetch LifterLMS extended status only if LifterLMS is active
	useEffect(() => {
		if (!lmsStatus.lifterlms?.active) {
			setLoadingLifterlms(false);
			return;
		}

		const fetchLifterlmsStatus = async () => {
			try {
				const response = await apiFetch({
					path: '/ppq/v1/lifterlms/status',
					method: 'GET',
				});

				if (response.success) {
					setLifterlmsStatus(response.status);
				}
			} catch (error) {
				// Keep the basic status from PHP
				console.error('Failed to fetch LifterLMS extended status:', error);
			} finally {
				setLoadingLifterlms(false);
			}
		};

		fetchLifterlmsStatus();
	}, [lmsStatus.lifterlms?.active]);

	/**
	 * Save LearnDash settings
	 */
	const handleSaveLearndashSettings = async () => {
		try {
			setSavingLearndash(true);
			await apiFetch({
				path: '/ppq/v1/learndash/settings',
				method: 'POST',
				data: learndashSettings,
			});
		} catch (error) {
			console.error('Failed to save LearnDash settings:', error);
		} finally {
			setSavingLearndash(false);
		}
	};

	/**
	 * Save new API key
	 */
	const handleSaveApiKey = async () => {
		if (!newApiKey.trim()) {
			setValidationResult({ type: 'error', message: __('Please enter an API key.', 'pressprimer-quiz') });
			return;
		}

		if (!newApiKey.startsWith('sk-')) {
			setValidationResult({ type: 'error', message: __('Invalid API key format. Keys should start with "sk-".', 'pressprimer-quiz') });
			return;
		}

		try {
			setSavingKey(true);
			setValidationResult(null);

			const response = await apiFetch({
				path: '/ppq/v1/settings/api-key',
				method: 'POST',
				data: { api_key: newApiKey },
			});

			if (response.success) {
				setValidationResult({ type: 'success', message: __('API key saved and validated successfully.', 'pressprimer-quiz') });
				setApiStatus({ configured: true, masked_key: response.masked_key || 'sk-....' });
				setNewApiKey('');
				// Refresh models
				handleRefreshModels();
			} else {
				setValidationResult({ type: 'error', message: response.message || __('Failed to save API key.', 'pressprimer-quiz') });
			}
		} catch (error) {
			setValidationResult({ type: 'error', message: error.message || __('Failed to save API key.', 'pressprimer-quiz') });
		} finally {
			setSavingKey(false);
		}
	};

	/**
	 * Validate existing API key
	 */
	const handleValidateKey = async () => {
		try {
			setValidatingKey(true);
			setValidationResult(null);

			const response = await apiFetch({
				path: '/ppq/v1/settings/api-key/validate',
				method: 'POST',
			});

			if (response.success) {
				setValidationResult({ type: 'success', message: __('API key is valid and working correctly.', 'pressprimer-quiz') });
			} else {
				setValidationResult({ type: 'error', message: response.message || __('Invalid API key.', 'pressprimer-quiz') });
			}
		} catch (error) {
			setValidationResult({ type: 'error', message: error.message || __('Failed to validate API key.', 'pressprimer-quiz') });
		} finally {
			setValidatingKey(false);
		}
	};

	/**
	 * Clear API key
	 */
	const handleClearKey = async () => {
		if (!window.confirm(__('Are you sure you want to remove your API key? You will not be able to use AI generation until you add a new key.', 'pressprimer-quiz'))) {
			return;
		}

		try {
			setClearingKey(true);
			setValidationResult(null);

			const response = await apiFetch({
				path: '/ppq/v1/settings/api-key',
				method: 'DELETE',
			});

			if (response.success) {
				setValidationResult({ type: 'success', message: __('API key removed successfully.', 'pressprimer-quiz') });
				setApiStatus({ configured: false });
				setModels([]);
			} else {
				setValidationResult({ type: 'error', message: response.message || __('Failed to clear API key.', 'pressprimer-quiz') });
			}
		} catch (error) {
			setValidationResult({ type: 'error', message: error.message || __('Failed to clear API key.', 'pressprimer-quiz') });
		} finally {
			setClearingKey(false);
		}
	};

	/**
	 * Refresh available models
	 */
	const handleRefreshModels = async () => {
		try {
			setLoadingModels(true);

			const response = await apiFetch({
				path: '/ppq/v1/settings/api-models',
				method: 'GET',
			});

			if (response.success && response.models) {
				setModels(response.models);
			}
		} catch (error) {
			console.error('Failed to fetch models:', error);
		} finally {
			setLoadingModels(false);
		}
	};

	/**
	 * Save model preference
	 */
	const handleModelChange = async (model) => {
		setSelectedModel(model);

		try {
			await apiFetch({
				path: '/ppq/v1/settings/api-model',
				method: 'POST',
				data: { model },
			});
		} catch (error) {
			console.error('Failed to save model preference:', error);
		}
	};

	return (
		<div>
			{/* OpenAI Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('OpenAI API', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Configure your OpenAI API key for AI-powered question generation. Your key is stored securely and encrypted.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-api-key-manager">
					{/* API Key Status */}
					<div className={`ppq-api-key-status ${apiStatus.configured ? 'ppq-api-key-status--configured' : 'ppq-api-key-status--not-configured'}`}>
						{apiStatus.configured ? (
							<>
								<CheckCircleOutlined className="ppq-api-key-status-icon" />
								<Text>
									{__('API Key Configured:', 'pressprimer-quiz')}{' '}
									<Text code>{apiStatus.masked_key || 'sk-****'}</Text>
								</Text>
								<Space style={{ marginLeft: 'auto' }}>
									<Button
										size="small"
										onClick={handleValidateKey}
										loading={validatingKey}
									>
										{__('Validate', 'pressprimer-quiz')}
									</Button>
									<Button
										size="small"
										danger
										icon={<DeleteOutlined />}
										onClick={handleClearKey}
										loading={clearingKey}
									>
										{__('Clear', 'pressprimer-quiz')}
									</Button>
								</Space>
							</>
						) : (
							<>
								<WarningOutlined className="ppq-api-key-status-icon" />
								<Text>{__('No API Key Configured', 'pressprimer-quiz')}</Text>
							</>
						)}
					</div>

					{/* Validation Result */}
					{validationResult && (
						<Alert
							message={validationResult.message}
							type={validationResult.type}
							showIcon
							closable
							style={{ marginBottom: 16 }}
							onClose={() => setValidationResult(null)}
						/>
					)}

					{/* API Key Input */}
					<Form.Item
						label={apiStatus.configured
							? __('Enter New API Key:', 'pressprimer-quiz')
							: __('Enter Your OpenAI API Key:', 'pressprimer-quiz')
						}
					>
						<Space.Compact style={{ width: '100%', maxWidth: 500 }}>
							<Input
								type={showApiKey ? 'text' : 'password'}
								value={newApiKey}
								onChange={(e) => setNewApiKey(e.target.value)}
								placeholder="sk-..."
								autoComplete="off"
							/>
							<Button
								icon={showApiKey ? <EyeInvisibleOutlined /> : <EyeOutlined />}
								onClick={() => setShowApiKey(!showApiKey)}
							/>
							<Button
								type="primary"
								onClick={handleSaveApiKey}
								loading={savingKey}
							>
								{__('Save Key', 'pressprimer-quiz')}
							</Button>
						</Space.Compact>
						<Paragraph type="secondary" style={{ marginTop: 8 }}>
							{__('Get your API key from', 'pressprimer-quiz')}{' '}
							<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">
								OpenAI Platform
							</a>
							{'. '}
							{__('Keys start with "sk-".', 'pressprimer-quiz')}
						</Paragraph>
					</Form.Item>

					{/* Model Selection - Only show if API key is configured */}
					{apiStatus.configured && (
						<div className="ppq-model-section">
							<Form.Item label={__('Preferred Model:', 'pressprimer-quiz')}>
								<Space>
									<Select
										value={selectedModel || undefined}
										onChange={handleModelChange}
										style={{ width: 300 }}
										loading={loadingModels}
										placeholder={models.length === 0 ? __('Click refresh to load models', 'pressprimer-quiz') : __('Select a model', 'pressprimer-quiz')}
										options={models.map(model => ({
											value: model,
											label: model,
										}))}
									/>
									<Button
										icon={<ReloadOutlined />}
										onClick={handleRefreshModels}
										loading={loadingModels}
									>
										{__('Refresh', 'pressprimer-quiz')}
									</Button>
								</Space>
								<Paragraph type="secondary" style={{ marginTop: 8 }}>
									{__('Select the OpenAI model to use for question generation.', 'pressprimer-quiz')}
								</Paragraph>
							</Form.Item>
						</div>
					)}

					{/* Usage Statistics - Only show if API key is configured */}
					{apiStatus.configured && (
						<div className="ppq-usage-stats">
							<div className="ppq-usage-stat">
								<span className="ppq-usage-stat-value">{usageData.requests_this_hour}</span>
								<span className="ppq-usage-stat-label">{__('Requests', 'pressprimer-quiz')}</span>
							</div>
							<div className="ppq-usage-stat">
								<span className="ppq-usage-stat-value">{usageData.requests_remaining}</span>
								<span className="ppq-usage-stat-label">{__('Remaining', 'pressprimer-quiz')}</span>
							</div>
							<div style={{ flex: 1, minWidth: 200 }}>
								<Progress
									percent={usageData.usage_percent}
									showInfo={false}
									strokeColor="#2271b1"
								/>
								<Paragraph type="secondary" style={{ marginTop: 4, marginBottom: 0 }}>
									{__('Rate limit:', 'pressprimer-quiz')} {usageData.rate_limit} {__('requests per hour', 'pressprimer-quiz')}
								</Paragraph>
							</div>
						</div>
					)}

					{/* Security Notice */}
					<div className="ppq-security-notice">
						<LockOutlined />
						<span>
							{__('Your API key is encrypted using AES-256-CBC before storage and is only accessible to your account.', 'pressprimer-quiz')}
						</span>
					</div>
				</div>
			</div>

			{/* LMS Integrations Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('LMS Integrations', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Connect with popular Learning Management Systems.', 'pressprimer-quiz')}
				</Paragraph>

				{/* LearnDash */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<BookOutlined style={{ fontSize: 20, marginRight: 8 }} />
						<Text strong>LearnDash</Text>
					</div>

					{loadingLearndash ? (
						<div style={{ padding: '16px', textAlign: 'center' }}>
							<Spin size="small" />
						</div>
					) : learndashStatus?.active ? (
						<>
							<Descriptions column={1} size="small" style={{ marginTop: 12 }}>
								<Descriptions.Item label={__('Status', 'pressprimer-quiz')}>
									<Tag color="success" icon={<CheckCircleOutlined />}>
										{__('Active', 'pressprimer-quiz')}
									</Tag>
								</Descriptions.Item>
								<Descriptions.Item label={__('Version', 'pressprimer-quiz')}>
									{learndashStatus.version}
								</Descriptions.Item>
								<Descriptions.Item label={__('Integration', 'pressprimer-quiz')}>
									<Tag color="blue">
										{__('Working', 'pressprimer-quiz')}
									</Tag>
								</Descriptions.Item>
								{learndashStatus.attached_quizzes > 0 && (
									<Descriptions.Item label={__('Attached Quizzes', 'pressprimer-quiz')}>
										{learndashStatus.attached_quizzes}
									</Descriptions.Item>
								)}
							</Descriptions>

							<Collapse
								style={{ marginTop: 16 }}
								items={[
									{
										key: 'settings',
										label: (
											<span>
												<SettingOutlined style={{ marginRight: 8 }} />
												{__('LearnDash Settings', 'pressprimer-quiz')}
											</span>
										),
										children: (
											<div className="ppq-learndash-settings">
												<Form.Item
													label={__('Message shown when users cannot yet access course-level quizzes', 'pressprimer-quiz')}
													style={{ marginBottom: 16 }}
												>
													<Input.TextArea
														rows={2}
														value={learndashSettings.restriction_message}
														onChange={(e) => setLearndashSettings({
															...learndashSettings,
															restriction_message: e.target.value
														})}
														placeholder={__('Complete all lessons and topics to unlock this quiz.', 'pressprimer-quiz')}
													/>
													<Paragraph type="secondary" style={{ marginTop: 8, marginBottom: 0 }}>
														{__('This message appears on course pages when the quiz is restricted until all content is completed. Leave blank to use the default message.', 'pressprimer-quiz')}
													</Paragraph>
												</Form.Item>
												<Button
													type="primary"
													onClick={handleSaveLearndashSettings}
													loading={savingLearndash}
												>
													{__('Save Settings', 'pressprimer-quiz')}
												</Button>
											</div>
										),
									},
								]}
							/>
						</>
					) : (
						<Alert
							message={__('LearnDash Not Detected', 'pressprimer-quiz')}
							description={__('Install and activate LearnDash to enable this integration. Once active, you can attach PressPrimer quizzes to courses, lessons, and topics.', 'pressprimer-quiz')}
							type="info"
							showIcon
							style={{ marginTop: 12 }}
						/>
					)}
				</div>

				{/* TutorLMS */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<BookOutlined style={{ fontSize: 20, marginRight: 8 }} />
						<Text strong>Tutor LMS</Text>
					</div>

					{loadingTutorlms ? (
						<div style={{ padding: '16px', textAlign: 'center' }}>
							<Spin size="small" />
						</div>
					) : tutorlmsStatus?.active ? (
						<Descriptions column={1} size="small" style={{ marginTop: 12 }}>
							<Descriptions.Item label={__('Status', 'pressprimer-quiz')}>
								<Tag color="success" icon={<CheckCircleOutlined />}>
									{__('Active', 'pressprimer-quiz')}
								</Tag>
							</Descriptions.Item>
							<Descriptions.Item label={__('Version', 'pressprimer-quiz')}>
								{tutorlmsStatus.version}
							</Descriptions.Item>
							<Descriptions.Item label={__('Integration', 'pressprimer-quiz')}>
								<Tag color="blue">
									{__('Working', 'pressprimer-quiz')}
								</Tag>
							</Descriptions.Item>
							{tutorlmsStatus.attached_quizzes > 0 && (
								<Descriptions.Item label={__('Attached Quizzes', 'pressprimer-quiz')}>
									{tutorlmsStatus.attached_quizzes}
								</Descriptions.Item>
							)}
						</Descriptions>
					) : (
						<Alert
							message={__('Tutor LMS Not Detected', 'pressprimer-quiz')}
							description={__('Install and activate Tutor LMS to enable this integration. Once active, you can attach PressPrimer quizzes to lessons.', 'pressprimer-quiz')}
							type="info"
							showIcon
							style={{ marginTop: 12 }}
						/>
					)}
				</div>

				{/* LifterLMS */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<BookOutlined style={{ fontSize: 20, marginRight: 8 }} />
						<Text strong>LifterLMS</Text>
					</div>

					{loadingLifterlms ? (
						<div style={{ padding: '16px', textAlign: 'center' }}>
							<Spin size="small" />
						</div>
					) : lifterlmsStatus?.active ? (
						<Descriptions column={1} size="small" style={{ marginTop: 12 }}>
							<Descriptions.Item label={__('Status', 'pressprimer-quiz')}>
								<Tag color="success" icon={<CheckCircleOutlined />}>
									{__('Active', 'pressprimer-quiz')}
								</Tag>
							</Descriptions.Item>
							<Descriptions.Item label={__('Version', 'pressprimer-quiz')}>
								{lifterlmsStatus.version}
							</Descriptions.Item>
							<Descriptions.Item label={__('Integration', 'pressprimer-quiz')}>
								<Tag color="blue">
									{__('Working', 'pressprimer-quiz')}
								</Tag>
							</Descriptions.Item>
							{lifterlmsStatus.attached_quizzes > 0 && (
								<Descriptions.Item label={__('Attached Quizzes', 'pressprimer-quiz')}>
									{lifterlmsStatus.attached_quizzes}
								</Descriptions.Item>
							)}
						</Descriptions>
					) : (
						<Alert
							message={__('LifterLMS Not Detected', 'pressprimer-quiz')}
							description={__('Install and activate LifterLMS to enable this integration. Once active, you can attach PressPrimer quizzes to lessons.', 'pressprimer-quiz')}
							type="info"
							showIcon
							style={{ marginTop: 12 }}
						/>
					)}
				</div>
			</div>
		</div>
	);
};

export default IntegrationsTab;
