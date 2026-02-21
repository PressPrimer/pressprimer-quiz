/**
 * Status Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Typography,
	Tag,
	Space,
	Button,
	Alert,
	message,
} from 'antd';
import {
	CheckCircleOutlined,
	CloseCircleOutlined,
	ExclamationCircleOutlined,
	ToolOutlined,
	CopyOutlined,
} from '@ant-design/icons';

const { Title, Paragraph } = Typography;

/**
 * LMS display names
 */
const LMS_NAMES = {
	learndash: 'LearnDash',
	learnpress: 'LearnPress',
	lifterlms: 'LifterLMS',
	tutorlms: 'Tutor LMS',
};

/**
 * Status Tab - System information and status
 *
 * @param {Object} props Component props
 * @param {Object} props.settingsData Full settings data including system info
 */
const StatusTab = ({ settingsData }) => {
	const systemInfo = useMemo(() => settingsData.systemInfo || {}, [settingsData.systemInfo]);
	const lmsStatus = useMemo(() => settingsData.lmsStatus || {}, [settingsData.lmsStatus]);
	const initialTables = settingsData.databaseTables || [];
	const nonces = settingsData.nonces || {};

	const [databaseTables, setDatabaseTables] = useState(initialTables);
	const [isRepairing, setIsRepairing] = useState(false);

	/**
	 * Check if any tables are missing
	 */
	const hasMissingTables = databaseTables.some(table => !table.exists);

	/**
	 * Handle repair database tables
	 */
	const handleRepairTables = async () => {
		setIsRepairing(true);

		try {
			const formData = new FormData();
			formData.append('action', 'pressprimer_quiz_repair_database_tables');
			formData.append('nonce', nonces.repairTables);

			const response = await fetch(window.ajaxurl, {
				method: 'POST',
				body: formData,
			});

			const result = await response.json();

			if (result.success) {
				message.success(result.data.message);
				if (result.data.tableStatus) {
					setDatabaseTables(result.data.tableStatus);
				}
			} else {
				message.error(result.data?.message || __('Failed to repair tables.', 'pressprimer-quiz'));
			}
		} catch (error) {
			message.error(__('An error occurred. Please try again.', 'pressprimer-quiz'));
		} finally {
			setIsRepairing(false);
		}
	};

	/**
	 * Get short table name (remove prefix)
	 */
	const getShortTableName = (fullName) => {
		const match = fullName.match(/ppq_(.+)$/);
		return match ? match[1] : fullName;
	};

	/**
	 * Format PHP version with requirement check
	 */
	const formatVersionWithCheck = (current, required) => {
		const meetsRequirement = compareVersions(current, required) >= 0;
		return (
			<Space>
				<span>{current}</span>
				{meetsRequirement ? (
					<Tag icon={<CheckCircleOutlined />} color="success">{__('OK', 'pressprimer-quiz')}</Tag>
				) : (
					<Tag icon={<CloseCircleOutlined />} color="error">
						{
							/* translators: %s: minimum required version number */
							sprintf(__('Requires %s+', 'pressprimer-quiz'), required)
						}
					</Tag>
				)}
			</Space>
		);
	};

	/**
	 * Simple version comparison
	 */
	const compareVersions = (a, b) => {
		if (!a || !b) return 0;
		const aParts = String(a).split('.').map(Number);
		const bParts = String(b).split('.').map(Number);

		for (let i = 0; i < Math.max(aParts.length, bParts.length); i++) {
			const aPart = aParts[i] || 0;
			const bPart = bParts[i] || 0;
			if (aPart > bPart) return 1;
			if (aPart < bPart) return -1;
		}
		return 0;
	};

	/**
	 * Build plain-text diagnostic string for clipboard
	 */
	const buildDiagnosticText = useCallback(() => {
		const lines = [];
		const sep = '---';

		lines.push('### PressPrimer Quiz - System Status ###');
		lines.push('');

		// Plugin info.
		lines.push('## Plugin');
		lines.push(`Plugin Version: ${systemInfo.pluginVersion || 'Unknown'}`);
		lines.push(`Database Version: ${systemInfo.dbVersion || 'Not set'}`);

		// Addon versions.
		const addons = systemInfo.addonVersions || {};
		if (addons.educator) {
			lines.push(`Educator Addon: ${addons.educator}`);
		}
		if (addons.school) {
			lines.push(`School Addon: ${addons.school}`);
		}
		if (addons.enterprise) {
			lines.push(`Enterprise Addon: ${addons.enterprise}`);
		}

		// License.
		const licenseStatus = systemInfo.licenseStatus || 'inactive';
		lines.push(`License Status: ${licenseStatus}`);
		lines.push('');

		// WordPress environment.
		lines.push('## WordPress');
		lines.push(`Site URL: ${systemInfo.siteUrl || 'Unknown'}`);
		lines.push(`WordPress Version: ${systemInfo.wpVersion || 'Unknown'}`);
		lines.push(`Multisite: ${systemInfo.isMultisite ? 'Yes' : 'No'}`);
		lines.push(`Memory Limit: ${systemInfo.memoryLimit || 'Unknown'}`);
		lines.push(`Active Theme: ${systemInfo.activeTheme || 'Unknown'}`);
		lines.push('');

		// Server environment.
		lines.push('## Server');
		lines.push(`PHP Version: ${systemInfo.phpVersion || 'Unknown'}`);
		lines.push(`PHP Post Max Size: ${systemInfo.postMaxSize || 'Unknown'}`);
		lines.push(`PHP Time Limit: ${systemInfo.maxExecutionTime || 'Unknown'} seconds`);
		lines.push(`MySQL Version: ${systemInfo.mysqlVersion || 'Unknown'}`);
		lines.push('');

		// LMS integrations.
		const activeLms = Object.entries(lmsStatus)
			.filter(([, info]) => info.active)
			.map(([key, info]) => `${LMS_NAMES[key] || key} ${info.version || ''}`);

		if (activeLms.length > 0) {
			lines.push('## LMS Integrations');
			activeLms.forEach(lms => lines.push(lms));
			lines.push('');
		}

		// File extraction.
		const caps = systemInfo.extractionCapabilities || {};
		lines.push('## File Extraction');
		lines.push(`PDF Parser: ${caps?.pdf?.smalot_parser ? 'Available' : 'Not Available'}`);
		lines.push(`pdftotext: ${caps?.pdf?.pdftotext ? 'Available' : 'Not Available'}`);
		lines.push(`DOCX (ZipArchive): ${caps?.docx?.basic ? 'Available' : 'Not Available'}`);
		lines.push('');

		// Statistics.
		lines.push('## Statistics');
		lines.push(`Quizzes: ${systemInfo.totalQuizzes ?? 0}`);
		lines.push(`Questions: ${systemInfo.totalQuestions ?? 0}`);
		lines.push(`Question Banks: ${systemInfo.totalBanks ?? 0}`);
		lines.push(`Attempts: ${systemInfo.totalAttempts ?? 0}`);
		lines.push('');

		// Database tables.
		lines.push('## Database Tables');
		databaseTables.forEach(table => {
			const status = table.exists ? 'OK' : 'MISSING';
			const rows = table.exists ? ` (${table.row_count} rows)` : '';
			lines.push(`${getShortTableName(table.name)}: ${status}${rows}`);
		});
		lines.push('');

		// Active plugins.
		const plugins = systemInfo.activePlugins || [];
		if (plugins.length > 0) {
			lines.push('## Active Plugins');
			plugins.forEach(plugin => lines.push(plugin));
			lines.push('');
		}

		lines.push(sep);
		return lines.join('\n');
	}, [systemInfo, lmsStatus, databaseTables]);

	/**
	 * Copy diagnostic text to clipboard
	 */
	const handleCopyDiagnostics = useCallback(async () => {
		try {
			const text = buildDiagnosticText();
			await navigator.clipboard.writeText(text);
			message.success(__('System status copied to clipboard.', 'pressprimer-quiz'));
		} catch (error) {
			// Fallback for older browsers / non-HTTPS.
			try {
				const textArea = document.createElement('textarea');
				textArea.value = buildDiagnosticText();
				textArea.style.position = 'fixed';
				textArea.style.left = '-9999px';
				document.body.appendChild(textArea);
				textArea.select();
				document.execCommand('copy');
				document.body.removeChild(textArea);
				message.success(__('System status copied to clipboard.', 'pressprimer-quiz'));
			} catch (fallbackError) {
				message.error(__('Failed to copy. Please try again.', 'pressprimer-quiz'));
			}
		}
	}, [buildDiagnosticText]);

	/**
	 * Get license status tag
	 */
	const getLicenseTag = () => {
		const status = systemInfo.licenseStatus || 'inactive';
		const hasAnyAddon = systemInfo.addonVersions &&
			(systemInfo.addonVersions.educator || systemInfo.addonVersions.school || systemInfo.addonVersions.enterprise);

		if (!hasAnyAddon) {
			return <Tag>{__('Free Version', 'pressprimer-quiz')}</Tag>;
		}

		switch (status) {
			case 'valid':
				return <Tag icon={<CheckCircleOutlined />} color="success">{__('Active', 'pressprimer-quiz')}</Tag>;
			case 'expired':
				return <Tag icon={<ExclamationCircleOutlined />} color="warning">{__('Expired', 'pressprimer-quiz')}</Tag>;
			default:
				return <Tag icon={<CloseCircleOutlined />} color="error">{__('Inactive', 'pressprimer-quiz')}</Tag>;
		}
	};

	return (
		<div>
			{/* Copy to clipboard bar */}
			<div className="ppq-status-copy-bar">
				<span className="ppq-status-copy-bar-text">
					{__('Copy all diagnostic information to share with support.', 'pressprimer-quiz')}
				</span>
				<Button
					icon={<CopyOutlined />}
					onClick={handleCopyDiagnostics}
				>
					{__('Copy System Status', 'pressprimer-quiz')}
				</Button>
			</div>

			{/* Two-column grid for info sections */}
			<div className="ppq-status-grid">

				{/* Plugin Information */}
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{__('Plugin', 'pressprimer-quiz')}
					</Title>

					<table className="ppq-system-info">
						<tbody>
							<tr>
								<th>{__('Version', 'pressprimer-quiz')}</th>
								<td>{systemInfo.pluginVersion || '1.0.0'}</td>
							</tr>
							<tr>
								<th>{__('DB Version', 'pressprimer-quiz')}</th>
								<td>{systemInfo.dbVersion || __('Not set', 'pressprimer-quiz')}</td>
							</tr>
							{systemInfo.addonVersions?.educator && (
								<tr>
									<th>{__('Educator', 'pressprimer-quiz')}</th>
									<td>{systemInfo.addonVersions.educator}</td>
								</tr>
							)}
							{systemInfo.addonVersions?.school && (
								<tr>
									<th>{__('School', 'pressprimer-quiz')}</th>
									<td>{systemInfo.addonVersions.school}</td>
								</tr>
							)}
							{systemInfo.addonVersions?.enterprise && (
								<tr>
									<th>{__('Enterprise', 'pressprimer-quiz')}</th>
									<td>{systemInfo.addonVersions.enterprise}</td>
								</tr>
							)}
							<tr>
								<th>{__('License', 'pressprimer-quiz')}</th>
								<td>{getLicenseTag()}</td>
							</tr>
						</tbody>
					</table>
				</div>

				{/* WordPress Environment */}
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{__('WordPress', 'pressprimer-quiz')}
					</Title>

					<table className="ppq-system-info">
						<tbody>
							<tr>
								<th>{__('Site URL', 'pressprimer-quiz')}</th>
								<td><code>{systemInfo.siteUrl || 'Unknown'}</code></td>
							</tr>
							<tr>
								<th>{__('Version', 'pressprimer-quiz')}</th>
								<td>{formatVersionWithCheck(systemInfo.wpVersion, '6.0')}</td>
							</tr>
							<tr>
								<th>{__('Multisite', 'pressprimer-quiz')}</th>
								<td>
									{systemInfo.isMultisite ? (
										<Tag color="blue">{__('Yes', 'pressprimer-quiz')}</Tag>
									) : (
										<Tag>{__('No', 'pressprimer-quiz')}</Tag>
									)}
								</td>
							</tr>
							<tr>
								<th>{__('Memory Limit', 'pressprimer-quiz')}</th>
								<td>{systemInfo.memoryLimit || 'Unknown'}</td>
							</tr>
							<tr>
								<th>{__('Theme', 'pressprimer-quiz')}</th>
								<td>{systemInfo.activeTheme || 'Unknown'}</td>
							</tr>
						</tbody>
					</table>
				</div>

				{/* Server Environment */}
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{__('Server', 'pressprimer-quiz')}
					</Title>

					<table className="ppq-system-info">
						<tbody>
							<tr>
								<th>{__('PHP Version', 'pressprimer-quiz')}</th>
								<td>{formatVersionWithCheck(systemInfo.phpVersion, '7.4')}</td>
							</tr>
							<tr>
								<th>{__('Post Max Size', 'pressprimer-quiz')}</th>
								<td>{systemInfo.postMaxSize || 'Unknown'}</td>
							</tr>
							<tr>
								<th>{__('Time Limit', 'pressprimer-quiz')}</th>
								<td>
									{systemInfo.maxExecutionTime || 'Unknown'}{' '}
									{systemInfo.maxExecutionTime && __('seconds', 'pressprimer-quiz')}
								</td>
							</tr>
							<tr>
								<th>{__('MySQL Version', 'pressprimer-quiz')}</th>
								<td>{systemInfo.mysqlVersion || 'Unknown'}</td>
							</tr>
						</tbody>
					</table>
				</div>

				{/* Statistics */}
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{__('Statistics', 'pressprimer-quiz')}
					</Title>

					<table className="ppq-system-info">
						<tbody>
							<tr>
								<th>{__('Quizzes', 'pressprimer-quiz')}</th>
								<td>{(systemInfo.totalQuizzes ?? 0).toLocaleString()}</td>
							</tr>
							<tr>
								<th>{__('Questions', 'pressprimer-quiz')}</th>
								<td>{(systemInfo.totalQuestions ?? 0).toLocaleString()}</td>
							</tr>
							<tr>
								<th>{__('Question Banks', 'pressprimer-quiz')}</th>
								<td>{(systemInfo.totalBanks ?? 0).toLocaleString()}</td>
							</tr>
							<tr>
								<th>{__('Attempts', 'pressprimer-quiz')}</th>
								<td>{(systemInfo.totalAttempts ?? 0).toLocaleString()}</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

			{/* Full-width sections below the grid */}

			{/* LMS Integrations */}
			{Object.keys(lmsStatus).length > 0 && (
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{__('LMS Integrations', 'pressprimer-quiz')}
					</Title>

					<table className="ppq-system-info">
						<tbody>
							{Object.entries(lmsStatus).map(([key, info]) => (
								<tr key={key}>
									<th>{LMS_NAMES[key] || key}</th>
									<td>
										{info.active ? (
											<Space>
												<Tag icon={<CheckCircleOutlined />} color="success">
													{__('Active', 'pressprimer-quiz')}
												</Tag>
												<span>{info.version}</span>
											</Space>
										) : (
											<Tag>{__('Not Detected', 'pressprimer-quiz')}</Tag>
										)}
									</td>
								</tr>
							))}
						</tbody>
					</table>
				</div>
			)}

			{/* File Extraction Capabilities */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('File Extraction', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Available methods for extracting text from uploaded files (PDF, DOCX) for AI question generation.', 'pressprimer-quiz')}
				</Paragraph>

				<table className="ppq-system-info">
					<tbody>
						<tr>
							<th>{__('PDF Parser Library', 'pressprimer-quiz')}</th>
							<td>
								{systemInfo.extractionCapabilities?.pdf?.smalot_parser ? (
									<Tag icon={<CheckCircleOutlined />} color="success">
										{__('Available', 'pressprimer-quiz')}
									</Tag>
								) : (
									<Tag icon={<CloseCircleOutlined />} color="error">
										{__('Not Available', 'pressprimer-quiz')}
									</Tag>
								)}
							</td>
						</tr>
						<tr>
							<th>{__('pdftotext Command', 'pressprimer-quiz')}</th>
							<td>
								{systemInfo.extractionCapabilities?.pdf?.pdftotext ? (
									<Tag icon={<CheckCircleOutlined />} color="success">
										{__('Available', 'pressprimer-quiz')}
									</Tag>
								) : (
									<Tag color="default">
										{__('Not Available', 'pressprimer-quiz')}
									</Tag>
								)}
							</td>
						</tr>
						<tr>
							<th>{__('DOCX Support (ZipArchive)', 'pressprimer-quiz')}</th>
							<td>
								{systemInfo.extractionCapabilities?.docx?.basic ? (
									<Tag icon={<CheckCircleOutlined />} color="success">
										{__('Available', 'pressprimer-quiz')}
									</Tag>
								) : (
									<Tag icon={<CloseCircleOutlined />} color="error">
										{__('Not Available', 'pressprimer-quiz')}
									</Tag>
								)}
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			{/* Database Tables */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Database Tables', 'pressprimer-quiz')}
				</Title>

				{hasMissingTables && (
					<Alert
						message={__('Missing Tables Detected', 'pressprimer-quiz')}
						description={__('Some database tables are missing. Click the repair button below to recreate them.', 'pressprimer-quiz')}
						type="warning"
						showIcon
						icon={<ExclamationCircleOutlined />}
						style={{ marginBottom: 16 }}
					/>
				)}

				<table className="ppq-system-info">
					<thead>
						<tr>
							<th>{__('Table', 'pressprimer-quiz')}</th>
							<th>{__('Status', 'pressprimer-quiz')}</th>
							<th>{__('Rows', 'pressprimer-quiz')}</th>
						</tr>
					</thead>
					<tbody>
						{databaseTables.map((table, index) => (
							<tr key={index}>
								<td>
									<code>{getShortTableName(table.name)}</code>
								</td>
								<td>
									{table.exists ? (
										<Tag icon={<CheckCircleOutlined />} color="success">
											{__('OK', 'pressprimer-quiz')}
										</Tag>
									) : (
										<Tag icon={<CloseCircleOutlined />} color="error">
											{__('Missing', 'pressprimer-quiz')}
										</Tag>
									)}
								</td>
								<td>
									{table.exists ? table.row_count.toLocaleString() : '—'}
								</td>
							</tr>
						))}
					</tbody>
				</table>

				{hasMissingTables && (
					<div style={{ marginTop: 16 }}>
						<Button
							type="primary"
							danger
							icon={<ToolOutlined />}
							onClick={handleRepairTables}
							loading={isRepairing}
						>
							{__('Repair Database Tables', 'pressprimer-quiz')}
						</Button>
					</div>
				)}
			</div>

			{/* Active Plugins */}
			{systemInfo.activePlugins && systemInfo.activePlugins.length > 0 && (
				<div className="ppq-settings-section">
					<Title level={4} className="ppq-settings-section-title">
						{
							/* translators: %d: number of active plugins */
							sprintf(__('Active Plugins (%d)', 'pressprimer-quiz'), systemInfo.activePlugins.length)
						}
					</Title>

					<div className="ppq-status-plugin-list">
						{systemInfo.activePlugins.map((plugin, index) => (
							<span key={index} className="ppq-status-plugin-item">
								{plugin}
							</span>
						))}
					</div>
				</div>
			)}
		</div>
	);
};

export default StatusTab;
