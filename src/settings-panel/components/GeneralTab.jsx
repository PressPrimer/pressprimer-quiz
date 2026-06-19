/**
 * General Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	InputNumber,
	Radio,
	Select,
	Input,
	Switch,
	Typography,
	Space,
	Button,
	Alert,
	message,
} from 'antd';
import { PlusOutlined } from '@ant-design/icons';

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
 * @param {Object} props.settingsData Localized settings data (dashboard facts, etc.)
 */
const GeneralTab = ({ settings, updateSetting, settingsData = {} }) => {
	const maScoringMode = settings.default_ma_scoring || 'right_minus_wrong';

	// Front-end dashboard page (v3.0).
	const dashboardData = settingsData.dashboard || {};
	const dashboardPageId = settings.dashboard_page_id || 0;
	const [dashboardPages, setDashboardPages] = useState(dashboardData.pages || []);
	const [creatingPage, setCreatingPage] = useState(false);
	// View/Edit links for the page these links belong to (forId). Cleared when a
	// different page is selected, since its permalink isn't known client-side.
	const [dashboardLinks, setDashboardLinks] = useState({
		view: dashboardData.viewUrl || '',
		edit: dashboardData.editUrl || '',
		forId: dashboardPageId,
	});

	const isFrontPage = dashboardPageId > 0
		&& dashboardPageId === (dashboardData.frontPageId || 0)
		&& dashboardData.showOnFront === 'page';
	const isMissingPage = dashboardPageId > 0
		&& !dashboardPages.some((p) => p.id === dashboardPageId);

	const pageOptions = [
		{ value: 0, label: __('— None —', 'pressprimer-quiz') },
		...dashboardPages.map((p) => ({ value: p.id, label: p.title })),
	];
	if (isMissingPage) {
		pageOptions.push({
			value: dashboardPageId,
			/* translators: %d: page ID. */
			label: sprintf(__('Page #%d (unpublished or deleted)', 'pressprimer-quiz'), dashboardPageId),
		});
	}

	/**
	 * Create a dashboard page on the server, then select it.
	 */
	const handleCreateDashboardPage = async () => {
		setCreatingPage(true);
		try {
			const res = await apiFetch({ path: '/ppq/v1/dashboard-page', method: 'POST' });
			if (res && res.success && res.pageId) {
				setDashboardPages((prev) => (
					prev.some((p) => p.id === res.pageId)
						? prev
						: [{ id: res.pageId, title: res.pageTitle || __('Dashboard', 'pressprimer-quiz') }, ...prev]
				));
				updateSetting('dashboard_page_id', res.pageId);
				setDashboardLinks({
					view: res.viewUrl || '',
					edit: res.editUrl || '',
					forId: res.pageId,
				});
				message.success(__('Dashboard page created and selected.', 'pressprimer-quiz'));
			} else {
				message.error(__('Could not create the dashboard page.', 'pressprimer-quiz'));
			}
		} catch (error) {
			message.error(error.message || __('Could not create the dashboard page.', 'pressprimer-quiz'));
		} finally {
			setCreatingPage(false);
		}
	};

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

			{/* Guest Email Consent Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Guest Email Consent', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Optionally show a marketing consent checkbox on the guest email form. It is unchecked by default and never blocks starting a quiz.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Show a marketing consent checkbox', 'pressprimer-quiz')}
						help={__('Adds an unchecked, optional consent checkbox to the guest email form.', 'pressprimer-quiz')}
					>
						<Switch
							checked={!! settings.guest_consent_enabled}
							onChange={(checked) => updateSetting('guest_consent_enabled', checked)}
						/>
					</Form.Item>
				</div>

				{settings.guest_consent_enabled && (
					<>
						<div className="ppq-settings-field">
							<Form.Item
								label={__('Consent label', 'pressprimer-quiz')}
								help={__('Describe the marketing use, not quiz function. A link to your Privacy Policy is added automatically when one is set.', 'pressprimer-quiz')}
							>
								<TextArea
									value={settings.guest_consent_label || ''}
									onChange={(e) => updateSetting('guest_consent_label', e.target.value)}
									rows={2}
									style={{ maxWidth: 500 }}
								/>
							</Form.Item>
						</div>

						{! settingsData.privacyPolicyUrl && (
							<Alert
								type="info"
								showIcon
								style={{ maxWidth: 500 }}
								message={__('No privacy policy page is set', 'pressprimer-quiz')}
								description={__('Set a Privacy Policy page under Settings → Privacy so a link can be shown next to the consent checkbox.', 'pressprimer-quiz')}
							/>
						)}
					</>
				)}
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

			{/* Dashboard Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Dashboard', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Choose the page that hosts the front-end dashboard. The plugin links here from emails, results pages, and (with add-ons) instructor tools. The dashboard block can be placed on any page; this setting just records which one to link to.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Dashboard Page', 'pressprimer-quiz')}
						help={__('Select an existing page, or create one automatically.', 'pressprimer-quiz')}
					>
						<Space wrap>
							<Select
								value={dashboardPageId || 0}
								onChange={(value) => updateSetting('dashboard_page_id', value)}
								style={{ width: 320 }}
								options={pageOptions}
							/>
							<Button
								icon={<PlusOutlined />}
								onClick={handleCreateDashboardPage}
								loading={creatingPage}
							>
								{__('Create page for me', 'pressprimer-quiz')}
							</Button>
						</Space>
					</Form.Item>
				</div>

				{dashboardLinks.view && dashboardLinks.forId === dashboardPageId && (
					<div className="ppq-settings-field">
						<Space size="middle">
							<a href={dashboardLinks.view} target="_blank" rel="noopener noreferrer">
								{__('View page', 'pressprimer-quiz')}
							</a>
							{dashboardLinks.edit && (
								<a href={dashboardLinks.edit} target="_blank" rel="noopener noreferrer">
									{__('Edit page', 'pressprimer-quiz')}
								</a>
							)}
						</Space>
					</div>
				)}

				<Paragraph className="ppq-settings-section-description" style={{ marginTop: 8 }}>
					{__('Tip: if your site uses page caching, exclude the dashboard page from the cache so it always loads fresh. Most caching plugins skip logged-in users automatically.', 'pressprimer-quiz')}
				</Paragraph>

				{isFrontPage && (
					<Alert
						type="error"
						showIcon
						style={{ marginTop: 12 }}
						message={__('This page is your static front page', 'pressprimer-quiz')}
						description={__('WordPress does not reliably render app-style pages as the static front page. Choose a different page for the dashboard.', 'pressprimer-quiz')}
					/>
				)}

				{isMissingPage && (
					<Alert
						type="warning"
						showIcon
						style={{ marginTop: 12 }}
						message={__('The selected dashboard page is missing or unpublished', 'pressprimer-quiz')}
						description={__('Links to the dashboard are hidden until you select a published page.', 'pressprimer-quiz')}
					/>
				)}
			</div>
		</div>
	);
};

export default GeneralTab;
