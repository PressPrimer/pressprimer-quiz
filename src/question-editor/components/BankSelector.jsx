/**
 * Bank Selector Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Select, Typography, Space, Tooltip, Spin } from 'antd';
import { QuestionCircleOutlined, DatabaseOutlined } from '@ant-design/icons';

const { Title, Text } = Typography;

const BankSelector = ({ value, onChange }) => {
	const [options, setOptions] = useState([]);
	const [loading, setLoading] = useState(false);

	/**
	 * Load available banks
	 */
	useEffect(() => {
		loadBanks();
	}, []);

	const loadBanks = async () => {
		try {
			setLoading(true);
			const banks = await apiFetch({
				path: '/ppq/v1/banks',
			});

			setOptions(
				banks.map((bank) => ({
					value: bank.id,
					label: bank.name,
					description: bank.description,
				}))
			);
		} catch (error) {
			console.error('Failed to load banks:', error);
		} finally {
			setLoading(false);
		}
	};

	return (
		<div style={{ marginBottom: 0 }}>
			<Title level={5} style={{ marginBottom: 6, marginTop: 0 }}>
				<Space size={4}>
					<DatabaseOutlined />
					<span>{__('Question Banks', 'pressprimer-quiz')}</span>
					<Tooltip title={__('Add this question to one or more question banks for easy reuse in quizzes.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ fontSize: 14, color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			</Title>

			<Spin spinning={loading}>
				<Select
					mode="multiple"
					size="small"
					placeholder={__('Select banks...', 'pressprimer-quiz')}
					value={value}
					onChange={onChange}
					loading={loading}
					filterOption={(input, option) =>
						(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
					}
					optionRender={(option) => (
						<Space direction="vertical" size={0}>
							<Text strong style={{ fontSize: 13 }}>{option.data.label}</Text>
							{option.data.description && (
								<Text type="secondary" style={{ fontSize: 11 }}>
									{option.data.description}
								</Text>
							)}
						</Space>
					)}
					style={{ width: '100%' }}
					maxTagCount={3}
					listHeight={160}
				>
					{options.map((option) => (
						<Select.Option key={option.value} value={option.value} {...option}>
							{option.label}
						</Select.Option>
					))}
				</Select>
			</Spin>

			<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 2, marginBottom: 0 }}>
				{__('Reuse in multiple quizzes', 'pressprimer-quiz')}
			</Text>
		</div>
	);
};

export default BankSelector;
