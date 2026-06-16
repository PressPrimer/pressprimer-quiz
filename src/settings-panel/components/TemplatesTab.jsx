/**
 * Templates Tab — manage saved quiz settings templates (FR-005).
 *
 * Lists saved templates (name, description, author, updated) with rename/edit
 * description, delete (confirm), and a read-only payload viewer. Built-in
 * presets are code-only and are not listed here; they appear in the Quiz
 * Builder's Apply menu. This tab manages its own CRUD via REST.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Table,
	Button,
	Modal,
	Form,
	Input,
	Select,
	Alert,
	Popconfirm,
	Descriptions,
	Typography,
	Space,
	Empty,
	Spin,
	message,
} from 'antd';
import { EyeOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import { debugError } from '../../utils/debug';
import {
	TEMPLATE_FIELD_MAP,
	formatTemplateValue,
} from '../../quiz-editor/templates/templateFields';

const { Title, Paragraph, Text } = Typography;
const { TextArea } = Input;

/**
 * Templates management tab.
 *
 * @return {JSX.Element} The tab.
 */
const TemplatesTab = () => {
	const [form] = Form.useForm();
	const [templates, setTemplates] = useState([]);
	const [presets, setPresets] = useState([]);
	const [defaultValue, setDefaultValue] = useState('');
	const [defaultCleared, setDefaultCleared] = useState(false);
	const [savingDefault, setSavingDefault] = useState(false);
	const [loading, setLoading] = useState(true);
	const [editTarget, setEditTarget] = useState(null);
	const [viewTarget, setViewTarget] = useState(null);
	const [savingEdit, setSavingEdit] = useState(false);

	const loadTemplates = useCallback(() => {
		setLoading(true);
		apiFetch({ path: '/ppq/v1/quiz-templates' })
			.then((res) => {
				const items = Array.isArray(res?.items) ? res.items : [];
				setTemplates(items.filter((item) => 'template' === item.source));
				setPresets(items.filter((item) => 'preset' === item.source));
				setDefaultValue(typeof res?.default === 'string' ? res.default : '');
				setDefaultCleared(!!res?.default_cleared);
			})
			.catch((error) => {
				debugError('Failed to load templates:', error);
				setTemplates([]);
				setPresets([]);
			})
			.finally(() => setLoading(false));
	}, []);

	const changeDefault = async (value) => {
		setSavingDefault(true);
		try {
			const res = await apiFetch({
				path: '/ppq/v1/quiz-templates/default',
				method: 'POST',
				data: { value },
			});
			setDefaultValue(typeof res?.default === 'string' ? res.default : '');
			setDefaultCleared(false);
			message.success(__('Default template updated.', 'pressprimer-quiz'));
		} catch (error) {
			debugError('Failed to set default template:', error);
			message.error(error.message || __('Failed to update the default template.', 'pressprimer-quiz'));
		} finally {
			setSavingDefault(false);
		}
	};

	useEffect(() => {
		loadTemplates();
	}, [loadTemplates]);

	const openEdit = (template) => {
		setEditTarget(template);
		form.setFieldsValue({ name: template.name, description: template.description || '' });
	};

	const closeEdit = () => {
		if (!savingEdit) {
			setEditTarget(null);
			form.resetFields();
		}
	};

	const submitEdit = async () => {
		let values;
		try {
			values = await form.validateFields();
		} catch (e) {
			return;
		}
		setSavingEdit(true);
		try {
			await apiFetch({
				path: `/ppq/v1/quiz-templates/${editTarget.id}`,
				method: 'PUT',
				data: {
					name: (values.name || '').trim(),
					description: (values.description || '').trim(),
				},
			});
			message.success(__('Template updated.', 'pressprimer-quiz'));
			setEditTarget(null);
			form.resetFields();
			loadTemplates();
		} catch (error) {
			debugError('Failed to update template:', error);
			message.error(error.message || __('Failed to update template.', 'pressprimer-quiz'));
		} finally {
			setSavingEdit(false);
		}
	};

	const deleteTemplate = async (template) => {
		try {
			await apiFetch({
				path: `/ppq/v1/quiz-templates/${template.id}`,
				method: 'DELETE',
			});
			message.success(__('Template deleted.', 'pressprimer-quiz'));
			loadTemplates();
		} catch (error) {
			debugError('Failed to delete template:', error);
			message.error(error.message || __('Failed to delete template.', 'pressprimer-quiz'));
		}
	};

	const columns = [
		{
			title: __('Name', 'pressprimer-quiz'),
			dataIndex: 'name',
			key: 'name',
			render: (name) => <Text strong>{name}</Text>,
		},
		{
			title: __('Description', 'pressprimer-quiz'),
			dataIndex: 'description',
			key: 'description',
			render: (description) =>
				description ? (
					<Text>{description}</Text>
				) : (
					<Text type="secondary">{__('—', 'pressprimer-quiz')}</Text>
				),
		},
		{
			title: __('Author', 'pressprimer-quiz'),
			dataIndex: 'author_name',
			key: 'author_name',
			render: (author) => author || <Text type="secondary">{__('—', 'pressprimer-quiz')}</Text>,
		},
		{
			title: __('Updated', 'pressprimer-quiz'),
			dataIndex: 'updated_at',
			key: 'updated_at',
			render: (updated) => (updated ? String(updated).slice(0, 16) : ''),
		},
		{
			title: __('Actions', 'pressprimer-quiz'),
			key: 'actions',
			width: 220,
			render: (_, template) => (
				<Space size="small">
					<Button size="small" icon={<EyeOutlined />} onClick={() => setViewTarget(template)}>
						{__('View', 'pressprimer-quiz')}
					</Button>
					<Button size="small" icon={<EditOutlined />} onClick={() => openEdit(template)}>
						{__('Edit', 'pressprimer-quiz')}
					</Button>
					<Popconfirm
						title={__('Delete this template?', 'pressprimer-quiz')}
						description={__('This cannot be undone.', 'pressprimer-quiz')}
						okText={__('Delete', 'pressprimer-quiz')}
						cancelText={__('Cancel', 'pressprimer-quiz')}
						okButtonProps={{ danger: true }}
						onConfirm={() => deleteTemplate(template)}
					>
						<Button size="small" danger icon={<DeleteOutlined />}>
							{__('Delete', 'pressprimer-quiz')}
						</Button>
					</Popconfirm>
				</Space>
			),
		},
	];

	const viewEntries =
		viewTarget && viewTarget.settings && typeof viewTarget.settings === 'object'
			? Object.entries(viewTarget.settings)
			: [];

	const defaultOptions = [
		{ value: '', label: __('— None (use built-in defaults) —', 'pressprimer-quiz') },
	];
	if (presets.length) {
		defaultOptions.push({
			label: __('Presets', 'pressprimer-quiz'),
			options: presets.map((p) => ({ value: `preset:${p.id}`, label: p.name })),
		});
	}
	if (templates.length) {
		defaultOptions.push({
			label: __('Saved Templates', 'pressprimer-quiz'),
			options: templates.map((t) => ({ value: `template:${t.id}`, label: t.name })),
		});
	}

	let listContent;
	if (loading) {
		listContent = (
			<div style={{ textAlign: 'center', padding: '48px' }}>
				<Spin size="large" />
			</div>
		);
	} else if (templates.length === 0) {
		listContent = (
			<Empty
				description={__('No saved templates yet. Use “Save as template” in the Quiz Builder to create one.', 'pressprimer-quiz')}
			/>
		);
	} else {
		listContent = (
			<Table
				rowKey="id"
				columns={columns}
				dataSource={templates}
				pagination={false}
				size="middle"
			/>
		);
	}

	return (
		<div className="ppq-settings-section">
			<Title level={4} className="ppq-settings-section-title">
				{__('Quiz Settings Templates', 'pressprimer-quiz')}
			</Title>
			<Paragraph className="ppq-settings-section-description">
				{__('Saved templates capture a quiz’s settings so you can apply them to other quizzes from the Quiz Builder. Built-in presets (such as Exam Simulation) are always available there and are not listed here.', 'pressprimer-quiz')}
			</Paragraph>

			{defaultCleared && (
				<Alert
					type="warning"
					showIcon
					style={{ marginBottom: 16 }}
					message={__('The default template for new quizzes was removed, so it has been cleared. New quizzes now use the built-in defaults.', 'pressprimer-quiz')}
				/>
			)}

			<div className="ppq-settings-field" style={{ marginBottom: 24 }}>
				<Text strong style={{ display: 'block', marginBottom: 4 }}>
					{__('Default template for new quizzes', 'pressprimer-quiz')}
				</Text>
				<Text type="secondary" style={{ display: 'block', marginBottom: 8, fontSize: 13 }}>
					{__('New quizzes start pre-filled with this template’s settings. Authors can change anything before saving.', 'pressprimer-quiz')}
				</Text>
				<Select
					value={defaultValue}
					onChange={changeDefault}
					loading={savingDefault}
					disabled={loading}
					options={defaultOptions}
					style={{ width: 360, maxWidth: '100%' }}
				/>
			</div>

			{listContent}

			{/* Edit (rename / description) */}
			<Modal
				open={!!editTarget}
				title={__('Edit template', 'pressprimer-quiz')}
				onCancel={closeEdit}
				onOk={submitEdit}
				okText={__('Save', 'pressprimer-quiz')}
				cancelText={__('Cancel', 'pressprimer-quiz')}
				confirmLoading={savingEdit}
				destroyOnClose
			>
				<Form form={form} layout="vertical" preserve={false}>
					<Form.Item
						name="name"
						label={__('Template name', 'pressprimer-quiz')}
						rules={[
							{ required: true, message: __('Please enter a template name.', 'pressprimer-quiz') },
							{ max: 100, message: __('Name must be 100 characters or fewer.', 'pressprimer-quiz') },
						]}
					>
						<Input maxLength={100} />
					</Form.Item>
					<Form.Item name="description" label={__('Description (optional)', 'pressprimer-quiz')}>
						<TextArea rows={3} />
					</Form.Item>
				</Form>
			</Modal>

			{/* Read-only payload viewer */}
			<Modal
				open={!!viewTarget}
				title={viewTarget ? viewTarget.name : ''}
				onCancel={() => setViewTarget(null)}
				footer={[
					<Button key="close" onClick={() => setViewTarget(null)}>
						{__('Close', 'pressprimer-quiz')}
					</Button>,
				]}
				width={560}
			>
				{viewEntries.length === 0 ? (
					<Empty
						image={Empty.PRESENTED_IMAGE_SIMPLE}
						description={__('This template stores no settings.', 'pressprimer-quiz')}
					/>
				) : (
					<Descriptions column={1} size="small" bordered>
						{viewEntries.map(([key, value]) => {
							const meta = TEMPLATE_FIELD_MAP[key];
							return (
								<Descriptions.Item key={key} label={meta ? meta.label : key}>
									{meta ? formatTemplateValue(key, value) : String(value)}
								</Descriptions.Item>
							);
						})}
					</Descriptions>
				)}
			</Modal>
		</div>
	);
};

export default TemplatesTab;
