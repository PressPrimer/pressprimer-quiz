/**
 * Templates Tab — quiz settings templates (FR-005, FR-006).
 *
 * Shows the built-in presets (read-only, with a settings viewer) and saved
 * templates grouped into "My Templates" and "Other Templates", with
 * rename/edit-description, delete (confirm), and a read-only payload viewer.
 * Also sets the default template for new quizzes. Manages its own CRUD via REST.
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

const emDash = () => <Text type="secondary">{__('—', 'pressprimer-quiz')}</Text>;

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

	const nameColumn = {
		title: __('Name', 'pressprimer-quiz'),
		dataIndex: 'name',
		key: 'name',
		render: (name) => <Text strong>{name}</Text>,
	};

	const descriptionColumn = {
		title: __('Description', 'pressprimer-quiz'),
		dataIndex: 'description',
		key: 'description',
		render: (description) => (description ? <Text>{description}</Text> : emDash()),
	};

	const presetColumns = [
		nameColumn,
		descriptionColumn,
		{
			title: __('Actions', 'pressprimer-quiz'),
			key: 'actions',
			width: 120,
			render: (_, preset) => (
				<Button size="small" icon={<EyeOutlined />} onClick={() => setViewTarget(preset)}>
					{__('View', 'pressprimer-quiz')}
				</Button>
			),
		},
	];

	const templateColumns = [
		nameColumn,
		descriptionColumn,
		{
			title: __('Author', 'pressprimer-quiz'),
			dataIndex: 'author_name',
			key: 'author_name',
			render: (author) => author || emDash(),
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

	const myTemplates = templates.filter((t) => t.is_mine);
	const otherTemplates = templates.filter((t) => !t.is_mine);

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
	if (myTemplates.length) {
		defaultOptions.push({
			label: __('My Templates', 'pressprimer-quiz'),
			options: myTemplates.map((t) => ({ value: `template:${t.id}`, label: t.name })),
		});
	}
	if (otherTemplates.length) {
		defaultOptions.push({
			label: __('Other Templates', 'pressprimer-quiz'),
			options: otherTemplates.map((t) => ({ value: `template:${t.id}`, label: t.name })),
		});
	}

	return (
		<div className="ppq-settings-section">
			<Title level={4} className="ppq-settings-section-title">
				{__('Quiz Settings Templates', 'pressprimer-quiz')}
			</Title>
			<Paragraph className="ppq-settings-section-description">
				{__('Templates capture a quiz’s settings (not its title, questions, or banks) so you can reuse them across quizzes. Apply them from the Quiz Builder; the built-in presets below are always available, and you can save your own templates from a quiz. Click View on any row to see exactly which settings it sets.', 'pressprimer-quiz')}
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

			{loading ? (
				<div style={{ textAlign: 'center', padding: '48px' }}>
					<Spin size="large" />
				</div>
			) : (
				<>
					{presets.length > 0 && (
						<div style={{ marginBottom: 24 }}>
							<Title level={5}>{__('Built-in presets', 'pressprimer-quiz')}</Title>
							<Table
								rowKey="id"
								columns={presetColumns}
								dataSource={presets}
								pagination={false}
								size="middle"
							/>
						</div>
					)}

					<Title level={5}>{__('Saved templates', 'pressprimer-quiz')}</Title>
					{templates.length === 0 ? (
						<Empty
							description={__('No saved templates yet. Use “Save as template” in the Quiz Builder to create one.', 'pressprimer-quiz')}
						/>
					) : (
						<>
							{myTemplates.length > 0 && (
								<div style={{ marginBottom: 24 }}>
									<Text strong style={{ display: 'block', marginBottom: 8 }}>
										{__('My Templates', 'pressprimer-quiz')}
									</Text>
									<Table
										rowKey="id"
										columns={templateColumns}
										dataSource={myTemplates}
										pagination={false}
										size="middle"
									/>
								</div>
							)}
							{otherTemplates.length > 0 && (
								<div>
									<Text strong style={{ display: 'block', marginBottom: 8 }}>
										{__('Other Templates', 'pressprimer-quiz')}
									</Text>
									<Table
										rowKey="id"
										columns={templateColumns}
										dataSource={otherTemplates}
										pagination={false}
										size="middle"
									/>
								</div>
							)}
						</>
					)}
				</>
			)}

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
				{viewTarget && viewTarget.description && (
					<Paragraph type="secondary" style={{ marginTop: 0 }}>
						{viewTarget.description}
					</Paragraph>
				)}
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
