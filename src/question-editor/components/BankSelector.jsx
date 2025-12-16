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
	const [banksMap, setBanksMap] = useState({});

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

			// Create a map for quick lookup of bank names by ID
			const map = {};
			banks.forEach((bank) => {
				map[bank.id] = bank.name;
			});
			setBanksMap(map);

			setOptions(
				banks.map((bank) => ({
					value: bank.id,
					label: bank.name,
					name: bank.name,
					description: bank.description,
				}))
			);
		} catch (error) {
			// Failed to load - options will be empty
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Custom tag render to show bank name instead of just ID
	 */
	const tagRender = (props) => {
		const { label, value: tagValue, closable, onClose } = props;
		// Use the bank name from map if available, otherwise show the label or ID
		const displayName = banksMap[tagValue] || label || `Bank ${tagValue}`;
		return (
			<span
				style={{
					display: 'inline-flex',
					alignItems: 'center',
					padding: '0 7px',
					marginRight: 4,
					marginBottom: 2,
					background: '#f5f5f5',
					border: '1px solid #d9d9d9',
					borderRadius: 4,
					fontSize: 12,
					lineHeight: '20px',
				}}
			>
				{displayName}
				{closable && (
					<span
						onClick={onClose}
						style={{ marginLeft: 4, cursor: 'pointer', color: '#999' }}
					>
						×
					</span>
				)}
			</span>
		);
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
					tagRender={tagRender}
					filterOption={(input, option) =>
						(option?.name ?? '').toLowerCase().includes(input.toLowerCase())
					}
					optionRender={(option) => (
						<Space direction="vertical" size={0}>
							<Text strong style={{ fontSize: 13 }}>{option.data.name}</Text>
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
							{option.name}
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
