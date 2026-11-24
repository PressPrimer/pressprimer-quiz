/**
 * Bank Selector Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Select, Typography, Space, Tooltip, Spin, Tag } from 'antd';
import { QuestionCircleOutlined, DatabaseOutlined, LockOutlined } from '@ant-design/icons';

const { Title, Text } = Typography;

/**
 * Bank Selector
 *
 * Offers only the banks the user can edit. Memberships in other people's
 * banks show read-only below the picker: their owners manage them, and the
 * save keeps them.
 *
 * @param {Object}   props             Component props
 * @param {number[]} props.value       Bank IDs (from Form.Item)
 * @param {Function} props.onChange    Change handler (from Form.Item)
 * @param {Object[]} props.memberships The question's banks: { id, name, can_edit }
 */
const BankSelector = ({ value, onChange, memberships = [] }) => {
	const [options, setOptions] = useState([]);
	const [loading, setLoading] = useState(false);
	const [banksMap, setBanksMap] = useState({});

	const selectedIds = (value || []).map(Number);
	const readOnlyBanks = memberships.filter(
		(bank) => !bank.can_edit && selectedIds.includes(Number(bank.id))
	);
	const readOnlyIds = readOnlyBanks.map((bank) => Number(bank.id));
	const editableIds = selectedIds.filter((id) => !readOnlyIds.includes(id));

	const handleChange = (ids) => {
		onChange?.([...ids.map(Number), ...readOnlyIds]);
	};

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

			// Questions are saved only into banks the user can edit.
			setOptions(
				banks
					.filter((bank) => bank.can_edit)
					.map((bank) => ({
						value: Number(bank.id),
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
						role="button"
						tabIndex={0}
						onClick={onClose}
						onKeyDown={(e) => {
							if (e.key === 'Enter' || e.key === ' ') {
								e.preventDefault();
								onClose(e);
							}
						}}
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
					value={editableIds}
					onChange={handleChange}
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

			{readOnlyBanks.length > 0 && (
				<div style={{ marginTop: 6 }}>
					<Text type="secondary" style={{ fontSize: 12, marginRight: 4 }}>
						{__('Also in:', 'pressprimer-quiz')}
					</Text>
					{readOnlyBanks.map((bank) => (
						<Tooltip
							key={bank.id}
							title={__('This bank is managed by its owner, so you can\'t remove the question from it here.', 'pressprimer-quiz')}
						>
							<Tag icon={<LockOutlined />} style={{ fontSize: 12, marginBottom: 2 }}>
								{bank.name}
							</Tag>
						</Tooltip>
					))}
				</div>
			)}

			<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 2, marginBottom: 0 }}>
				{__('Reuse in multiple quizzes', 'pressprimer-quiz')}
			</Text>
		</div>
	);
};

export default BankSelector;
