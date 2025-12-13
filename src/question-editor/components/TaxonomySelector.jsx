/**
 * Taxonomy Selector Component (Categories/Tags)
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Select, Typography, Space, Tooltip, message, Spin } from 'antd';
import {
	QuestionCircleOutlined,
	FolderOutlined,
	TagOutlined,
	PlusOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

const TaxonomySelector = ({ type, value, onChange }) => {
	const [options, setOptions] = useState([]);
	const [loading, setLoading] = useState(false);
	const [creating, setCreating] = useState(false);

	const isCategory = type === 'category';
	const icon = isCategory ? <FolderOutlined /> : <TagOutlined />;
	const label = isCategory
		? __('Categories', 'pressprimer-quiz')
		: __('Tags', 'pressprimer-quiz');
	const tooltip = isCategory
		? __('Organize questions into hierarchical categories. Type to search or create new categories.', 'pressprimer-quiz')
		: __('Add tags to make questions easier to find. Type to search or create new tags.', 'pressprimer-quiz');

	/**
	 * Load existing options
	 */
	useEffect(() => {
		loadOptions();
	}, [type]);

	const loadOptions = async () => {
		try {
			setLoading(true);
			const items = await apiFetch({
				path: `/ppq/v1/taxonomies?type=${type}`,
			});

			setOptions(
				items.map((item) => ({
					value: item.id,
					label: item.name,
					...item,
				}))
			);
		} catch (error) {
			// Failed to load - options will be empty
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Handle creating new taxonomy term
	 */
	const handleCreate = async (inputValue) => {
		if (!inputValue || inputValue.trim() === '') return;

		try {
			setCreating(true);

			const newTerm = await apiFetch({
				path: '/ppq/v1/taxonomies',
				method: 'POST',
				data: {
					name: inputValue.trim(),
					taxonomy: type,
				},
			});

			// Add to options
			const newOption = {
				value: newTerm.id,
				label: newTerm.name,
				...newTerm,
			};

			setOptions([...options, newOption]);

			// Add to selected values
			onChange([...(value || []), newTerm.id]);

			message.success(
				isCategory
					? __('Category created successfully!', 'pressprimer-quiz')
					: __('Tag created successfully!', 'pressprimer-quiz')
			);
		} catch (error) {
			message.error(error.message || __('Failed to create.', 'pressprimer-quiz'));
		} finally {
			setCreating(false);
		}
	};

	return (
		<div style={{ marginBottom: 0 }}>
			<Title level={5} style={{ marginBottom: 6, marginTop: 0 }}>
				<Space size={4}>
					{icon}
					<span>{label}</span>
					<Tooltip title={tooltip}>
						<QuestionCircleOutlined style={{ fontSize: 14, color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			</Title>

			<Spin spinning={creating} tip={__('Creating...', 'pressprimer-quiz')}>
				<Select
					mode="multiple"
					size="small"
					placeholder={
						isCategory
							? __('Select or create...', 'pressprimer-quiz')
							: __('Select or create...', 'pressprimer-quiz')
					}
					value={value}
					onChange={onChange}
					options={options}
					loading={loading}
					filterOption={(input, option) =>
						(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
					}
					dropdownRender={(menu) => (
						<>
							{menu}
							<div style={{ padding: '6px 10px', borderTop: '1px solid #f0f0f0' }}>
								<Text type="secondary" style={{ fontSize: 11 }}>
									<PlusOutlined style={{ marginRight: 4 }} />
									{__('Press Enter to create', 'pressprimer-quiz')}
								</Text>
							</div>
						</>
					)}
					onSearch={(value) => {
						// This allows creation on Enter key
					}}
					onInputKeyDown={(e) => {
						if (e.key === 'Enter' && e.target.value) {
							e.preventDefault();
							handleCreate(e.target.value);
						}
					}}
					style={{ width: '100%' }}
					maxTagCount={3}
					listHeight={160}
				/>
			</Spin>

			<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 2, marginBottom: 0 }}>
				{isCategory
					? __('Press Enter to create new', 'pressprimer-quiz')
					: __('Press Enter to create new', 'pressprimer-quiz')
				}
			</Text>
		</div>
	);
};

export default TaxonomySelector;
