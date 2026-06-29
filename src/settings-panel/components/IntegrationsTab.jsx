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
	Typography,
	Alert,
	Descriptions,
	Tag,
	Spin,
	Collapse,
} from 'antd';
import {
	CheckCircleOutlined,
	SettingOutlined,
} from '@ant-design/icons';
import AiProviderSettings from './AiProviderSettings';

const { Title, Paragraph, Text } = Typography;

/**
 * Integrations Tab - API keys and third-party integrations
 *
 * @param {Object}   props Component props
 * @param {Object}   props.settingsData Full settings data including AI provider status
 * @param {Object}   props.aiState      Lifted AI provider state (persists across tabs)
 * @param {Function} props.setAiState   Setter for the lifted AI provider state
 */
const IntegrationsTab = ({ settingsData, aiState, setAiState }) => {
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

	const [learnpressStatus, setLearnpressStatus] = useState(
		lmsStatus.learnpress?.active ? { active: true, version: lmsStatus.learnpress.version } : { active: false }
	);
	const [loadingLearnpress, setLoadingLearnpress] = useState(lmsStatus.learnpress?.active || false);

	const [lifterlmsStatus, setLifterlmsStatus] = useState(
		lmsStatus.lifterlms?.active ? { active: true, version: lmsStatus.lifterlms.version } : { active: false }
	);
	const [loadingLifterlms, setLoadingLifterlms] = useState(lmsStatus.lifterlms?.active || false);

	const [tutorlmsStatus, setTutorlmsStatus] = useState(
		lmsStatus.tutorlms?.active ? { active: true, version: lmsStatus.tutorlms.version } : { active: false }
	);
	const [loadingTutorlms, setLoadingTutorlms] = useState(lmsStatus.tutorlms?.active || false);

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
			} finally {
				setLoadingLearndash(false);
			}
		};

		fetchLearndashStatus();
	}, [lmsStatus.learndash?.active]);

	// Fetch LearnPress extended status only if LearnPress is active
	useEffect(() => {
		if (!lmsStatus.learnpress?.active) {
			setLoadingLearnpress(false);
			return;
		}

		const fetchLearnpressStatus = async () => {
			try {
				const response = await apiFetch({
					path: '/ppq/v1/learnpress/status',
					method: 'GET',
				});

				if (response.success) {
					setLearnpressStatus(response.status);
				}
			} catch (error) {
				// Keep the basic status from PHP
			} finally {
				setLoadingLearnpress(false);
			}
		};

		fetchLearnpressStatus();
	}, [lmsStatus.learnpress?.active]);

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
			} finally {
				setLoadingLifterlms(false);
			}
		};

		fetchLifterlmsStatus();
	}, [lmsStatus.lifterlms?.active]);

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
			} finally {
				setLoadingTutorlms(false);
			}
		};

		fetchTutorlmsStatus();
	}, [lmsStatus.tutorlms?.active]);

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
			// Silently fail - settings may not save but user can retry
		} finally {
			setSavingLearndash(false);
		}
	};

	/**
	 * Render LMS integration content based on loading and status
	 */
	const renderLmsContent = (loading, status, notDetectedMessage, notDetectedDescription, extraContent = null) => {
		if (loading) {
			return (
				<div style={{ padding: '16px', textAlign: 'center' }}>
					<Spin size="small" />
				</div>
			);
		}

		if (status?.active) {
			return (
				<>
					<Descriptions column={1} size="small" style={{ marginTop: 12 }}>
						<Descriptions.Item label={__('Status', 'pressprimer-quiz')}>
							<Tag color="success" icon={<CheckCircleOutlined />}>
								{__('Active', 'pressprimer-quiz')}
							</Tag>
						</Descriptions.Item>
						<Descriptions.Item label={__('Version', 'pressprimer-quiz')}>
							{status.version}
						</Descriptions.Item>
						<Descriptions.Item label={__('Integration', 'pressprimer-quiz')}>
							<Tag color="blue">
								{__('Working', 'pressprimer-quiz')}
							</Tag>
						</Descriptions.Item>
						{status.attached_quizzes > 0 && (
							<Descriptions.Item label={__('Attached Quizzes', 'pressprimer-quiz')}>
								{status.attached_quizzes}
							</Descriptions.Item>
						)}
					</Descriptions>
					{extraContent}
				</>
			);
		}

		return (
			<Alert
				message={notDetectedMessage}
				description={notDetectedDescription}
				type="info"
				showIcon
				style={{ marginTop: 12 }}
			/>
		);
	};

	return (
		<div>
			{/* AI Provider Section (site-level keys, provider selector). */}
			<AiProviderSettings ai={ aiState } onChange={ setAiState } usageData={ usageData } />

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
						<Text strong>LearnDash</Text>
					</div>

					{renderLmsContent(
						loadingLearndash,
						learndashStatus,
						__('LearnDash Not Detected', 'pressprimer-quiz'),
						__('Install and activate LearnDash to enable this integration. Once active, you can attach PressPrimer quizzes to courses, lessons, and topics.', 'pressprimer-quiz'),
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
					)}
				</div>

				{/* LearnPress */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<Text strong>LearnPress</Text>
					</div>

					{renderLmsContent(
						loadingLearnpress,
						learnpressStatus,
						__('LearnPress Not Detected', 'pressprimer-quiz'),
						__('Install and activate LearnPress to enable this integration. Once active, you can attach PressPrimer quizzes to lessons.', 'pressprimer-quiz')
					)}
				</div>

				{/* LifterLMS */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<Text strong>LifterLMS</Text>
					</div>

					{renderLmsContent(
						loadingLifterlms,
						lifterlmsStatus,
						__('LifterLMS Not Detected', 'pressprimer-quiz'),
						__('Install and activate LifterLMS to enable this integration. Once active, you can attach PressPrimer quizzes to lessons.', 'pressprimer-quiz')
					)}
				</div>

				{/* Tutor LMS */}
				<div className="ppq-lms-integration">
					<div className="ppq-lms-integration-header">
						<Text strong>Tutor LMS</Text>
					</div>

					{renderLmsContent(
						loadingTutorlms,
						tutorlmsStatus,
						__('Tutor LMS Not Detected', 'pressprimer-quiz'),
						__('Install and activate Tutor LMS to enable this integration. Once active, you can attach PressPrimer quizzes to lessons.', 'pressprimer-quiz')
					)}
				</div>
			</div>

			{/* Addon integrations mount point. Addons (e.g. the School addon's WP
				Fusion section) render their own integration UI here when the
				Integrations tab is shown. */}
			<div id="ppq-settings-integrations-addons" className="ppq-settings-integrations-addons" />
		</div>
	);
};

export default IntegrationsTab;
