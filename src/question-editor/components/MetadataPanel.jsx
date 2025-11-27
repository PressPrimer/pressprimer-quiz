/**
 * Metadata Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Form, Select, InputNumber, Typography, Space, Tooltip } from 'antd';
import {
	QuestionCircleOutlined,
	TrophyOutlined,
	ClockCircleOutlined,
	FireOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

const MetadataPanel = ({ form }) => {
	const difficultyOptions = [
		{
			value: 'beginner',
			label: __('Beginner', 'pressprimer-quiz'),
			emoji: '🟢',
		},
		{
			value: 'intermediate',
			label: __('Intermediate', 'pressprimer-quiz'),
			emoji: '🟡',
		},
		{
			value: 'advanced',
			label: __('Advanced', 'pressprimer-quiz'),
			emoji: '🟠',
		},
		{
			value: 'expert',
			label: __('Expert', 'pressprimer-quiz'),
			emoji: '🔴',
		},
	];

	const getDifficultyEmoji = (value) => {
		const option = difficultyOptions.find(opt => opt.value === value);
		return option ? option.emoji : '';
	};

	return (
		<Space direction="vertical" style={{ width: '100%' }} size="small">
			<div>
				<Title level={5} style={{ marginBottom: 8, marginTop: 0 }}>
					<Space>
						{__('Question Settings', 'pressprimer-quiz')}
						<Tooltip title={__('Configure difficulty, time limit, and point value for this question', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ fontSize: 14, color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				</Title>
			</div>

			{/* Difficulty */}
			<Form.Item
				name="difficulty"
				label={
					<Space>
						<FireOutlined />
						<span>{__('Difficulty', 'pressprimer-quiz')}</span>
						<Tooltip title={__('Set the difficulty level to help categorize questions (optional)', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
			>
				<Select
					allowClear
					placeholder={__('Choose a Difficulty', 'pressprimer-quiz')}
					options={difficultyOptions}
					labelRender={(option) => {
						const emoji = getDifficultyEmoji(option.value);
						return (
							<Space>
								<span style={{ fontSize: 16 }}>{emoji}</span>
								<span>{option.label}</span>
							</Space>
						);
					}}
					optionRender={(option) => (
						<Space>
							<span style={{ fontSize: 16 }}>{option.data.emoji}</span>
							<span>{option.data.label}</span>
						</Space>
					)}
				/>
			</Form.Item>

			{/* Time Limit */}
			<Form.Item
				name="timeLimit"
				label={
					<Space>
						<ClockCircleOutlined />
						<span>{__('Time Limit (seconds)', 'pressprimer-quiz')}</span>
						<Tooltip title={__('Set to 0 for no time limit, or specify seconds. This applies when used in Test Mode quizzes.', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
			>
				<InputNumber
					min={0}
					max={3600}
					size="small"
					style={{ width: '100%' }}
					placeholder={__('0 = No limit', 'pressprimer-quiz')}
					addonAfter={__('sec', 'pressprimer-quiz')}
				/>
			</Form.Item>

			{/* Points */}
			<Form.Item
				name="points"
				label={
					<Space>
						<TrophyOutlined />
						<span>{__('Points', 'pressprimer-quiz')}</span>
						<Tooltip title={__('How many points is this question worth?', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				rules={[
					{ required: true, message: __('Please set point value', 'pressprimer-quiz') },
					{
						validator: (_, value) => {
							if (value && value >= 0.01) {
								return Promise.resolve();
							}
							return Promise.reject(new Error(__('Points must be positive', 'pressprimer-quiz')));
						},
					},
				]}
			>
				<InputNumber
					min={0.01}
					max={1000}
					step={0.5}
					size="small"
					style={{ width: '100%' }}
					placeholder={__('e.g., 1', 'pressprimer-quiz')}
				/>
			</Form.Item>

			<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8, marginBottom: 0 }}>
				{__('Use fractions like 0.5 or 1.5 for weighted scoring', 'pressprimer-quiz')}
			</Text>
		</Space>
	);
};

export default MetadataPanel;
