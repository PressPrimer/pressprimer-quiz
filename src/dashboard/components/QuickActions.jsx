/**
 * Quick Actions Component
 *
 * Provides shortcuts to common actions.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Button } from 'antd';
import {
	PlusOutlined,
	FileTextOutlined,
	BarChartOutlined,
	FolderAddOutlined,
} from '@ant-design/icons';

/**
 * Quick Actions Component
 *
 * @param {Object} props Component props
 * @param {Object} props.urls URL mappings for actions
 */
const QuickActions = ({ urls = {} }) => {
	const actions = [
		{
			key: 'create_quiz',
			label: __('Create Quiz', 'pressprimer-quiz'),
			icon: <PlusOutlined />,
			url: urls.create_quiz || 'admin.php?page=ppq-quizzes&action=new',
			type: 'primary',
		},
		{
			key: 'add_question',
			label: __('Add Question', 'pressprimer-quiz'),
			icon: <FileTextOutlined />,
			url: urls.add_question || 'admin.php?page=ppq-questions&action=new',
			type: 'default',
		},
		{
			key: 'create_bank',
			label: __('Create Bank', 'pressprimer-quiz'),
			icon: <FolderAddOutlined />,
			url: urls.create_bank || 'admin.php?page=ppq-banks&action=new',
			type: 'default',
		},
		{
			key: 'view_reports',
			label: __('View Reports', 'pressprimer-quiz'),
			icon: <BarChartOutlined />,
			url: urls.reports || 'admin.php?page=ppq-reports',
			type: 'default',
		},
	];

	return (
		<div className="ppq-dashboard-card">
			<h3 className="ppq-dashboard-card-title">
				{__('Quick Actions', 'pressprimer-quiz')}
			</h3>
			<div className="ppq-quick-actions">
				{actions.map((action) => (
					<Button
						key={action.key}
						type={action.type}
						icon={action.icon}
						href={action.url}
						block
						className="ppq-quick-action-button"
					>
						{action.label}
					</Button>
				))}
			</div>
		</div>
	);
};

export default QuickActions;
