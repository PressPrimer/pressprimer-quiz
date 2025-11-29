/**
 * Status Tab Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Typography,
	Tag,
	Space,
} from 'antd';
import {
	CheckCircleOutlined,
	CloseCircleOutlined,
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

	/**
	 * Format PHP version with requirement check
	 */
	const formatVersionWithCheck = (current, required, label) => {
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
		</div>
	);
};

export default StatusTab;
