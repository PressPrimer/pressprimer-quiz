/**
 * Question Type Selector Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Card, Radio, Space, Typography, Tooltip } from 'antd';
import {
	CheckCircleOutlined,
	CheckSquareOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

const QuestionTypeSelector = ({ value, onChange }) => {
	const types = [
		{
			value: 'mc',
			label: __('Multiple Choice', 'pressprimer-quiz'),
			description: __('Students select one correct answer from multiple options', 'pressprimer-quiz'),
			icon: <CheckCircleOutlined style={{ fontSize: 24 }} />,
		},
		{
			value: 'ma',
			label: __('Multiple Answer', 'pressprimer-quiz'),
			description: __('Students can select multiple correct answers', 'pressprimer-quiz'),
			icon: <CheckSquareOutlined style={{ fontSize: 24 }} />,
		},
		{
			value: 'tf',
			label: __('True/False', 'pressprimer-quiz'),
			description: __('Students choose between True or False', 'pressprimer-quiz'),
			icon: <QuestionCircleOutlined style={{ fontSize: 24 }} />,
		},
	];

	return (
		<Card
			title={
				<Space>
					<Title level={4} style={{ margin: 0 }}>
						{__('Question Type', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
					</Title>
					<Tooltip title={__('Select the type of question you want to create. This determines how students will answer.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			}
			style={{ marginBottom: 24 }}
		>
			<Radio.Group
				value={value}
				onChange={(e) => onChange(e.target.value)}
				style={{ width: '100%' }}
			>
				<Space direction="vertical" style={{ width: '100%' }} size="middle">
					{types.map((type) => (
						<Card
							key={type.value}
							hoverable
							style={{
								border: value === type.value ? '2px solid #1890ff' : '1px solid #d9d9d9',
								backgroundColor: value === type.value ? '#e6f7ff' : '#fff',
								cursor: 'pointer',
							}}
							onClick={() => onChange(type.value)}
						>
							<Radio value={type.value} style={{ width: '100%' }}>
								<Space align="center" size={10}>
									{type.icon}
									<div>
										<div style={{ fontWeight: 600, fontSize: 16 }}>
											{type.label}
										</div>
										<Text type="secondary" style={{ fontSize: 14 }}>
											{type.description}
										</Text>
									</div>
								</Space>
							</Radio>
						</Card>
					))}
				</Space>
			</Radio.Group>
		</Card>
	);
};

export default QuestionTypeSelector;
