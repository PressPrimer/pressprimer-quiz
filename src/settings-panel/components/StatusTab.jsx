/**
 * Status Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
} from '@ant-design/icons';

const { Title, Paragraph } = Typography;

/**
 * Status Tab - System information and status
 *
 * @param {Object} props Component props
 * @param {Object} props.settingsData Full settings data including system info
 */
const StatusTab = ({ settingsData }) => {
	const systemInfo = settingsData.systemInfo || {};
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
				// Update table status with new data
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
		// Remove wp_ or other prefix and ppq_
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
					<Tag icon={<CheckCircleOutlined />} color="success">OK</Tag>
				) : (
					<Tag icon={<CloseCircleOutlined />} color="error">
						{__('Requires', 'pressprimer-quiz')} {required}+
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

	return (
		<div>
			{/* Plugin Information */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Plugin Information', 'pressprimer-quiz')}
				</Title>

				<table className="ppq-system-info">
					<tbody>
						<tr>
							<th>{__('Plugin Version', 'pressprimer-quiz')}</th>
							<td>{systemInfo.pluginVersion || '1.0.0'}</td>
						</tr>
						<tr>
							<th>{__('Database Version', 'pressprimer-quiz')}</th>
							<td>{systemInfo.dbVersion || __('Not set', 'pressprimer-quiz')}</td>
						</tr>
					</tbody>
				</table>
			</div>

			{/* WordPress Environment */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('WordPress Environment', 'pressprimer-quiz')}
				</Title>

				<table className="ppq-system-info">
					<tbody>
						<tr>
							<th>{__('WordPress Version', 'pressprimer-quiz')}</th>
							<td>{formatVersionWithCheck(systemInfo.wpVersion, '6.0', 'WordPress')}</td>
						</tr>
						<tr>
							<th>{__('Memory Limit', 'pressprimer-quiz')}</th>
							<td>{systemInfo.memoryLimit || 'Unknown'}</td>
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
					</tbody>
				</table>
			</div>

			{/* Server Environment */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Server Environment', 'pressprimer-quiz')}
				</Title>

				<table className="ppq-system-info">
					<tbody>
						<tr>
							<th>{__('PHP Version', 'pressprimer-quiz')}</th>
							<td>{formatVersionWithCheck(systemInfo.phpVersion, '7.4', 'PHP')}</td>
						</tr>
						<tr>
							<th>{__('PHP Post Max Size', 'pressprimer-quiz')}</th>
							<td>{systemInfo.postMaxSize || 'Unknown'}</td>
						</tr>
						<tr>
							<th>{__('PHP Time Limit', 'pressprimer-quiz')}</th>
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

			{/* Plugin Statistics */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Statistics', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Overview of your quiz content.', 'pressprimer-quiz')}
				</Paragraph>

				<table className="ppq-system-info">
					<tbody>
						<tr>
							<th>{__('Total Quizzes', 'pressprimer-quiz')}</th>
							<td>{systemInfo.totalQuizzes ?? 0}</td>
						</tr>
						<tr>
							<th>{__('Total Questions', 'pressprimer-quiz')}</th>
							<td>{systemInfo.totalQuestions ?? 0}</td>
						</tr>
						<tr>
							<th>{__('Total Question Banks', 'pressprimer-quiz')}</th>
							<td>{systemInfo.totalBanks ?? 0}</td>
						</tr>
						<tr>
							<th>{__('Total Attempts', 'pressprimer-quiz')}</th>
							<td>{systemInfo.totalAttempts ?? 0}</td>
						</tr>
					</tbody>
				</table>
			</div>

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
				<Paragraph className="ppq-settings-section-description">
					{__('Status of plugin database tables.', 'pressprimer-quiz')}
				</Paragraph>

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
		</div>
	);
};

export default StatusTab;
