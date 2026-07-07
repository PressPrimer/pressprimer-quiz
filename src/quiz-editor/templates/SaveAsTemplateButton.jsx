/**
 * Save As Template control for the Quiz Builder Settings tab.
 *
 * Captures the current editor settings as a named template (FR-004). Prompts
 * for a name (required, <=100 chars) and optional description; if a saved
 * template of the same name exists, offers to overwrite it (PUT) instead of
 * creating a duplicate. Gated by the manage-settings capability in the caller.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Form, Input, message } from 'antd';
import { SaveOutlined } from '@ant-design/icons';
import { debugError } from '../../utils/debug';

const { TextArea } = Input;

/**
 * Save As Template button + modal.
 *
 * @param {Object}   props                  Component props.
 * @param {Function} props.onCollectSettings Returns the current settings payload.
 * @return {JSX.Element} The control.
 */
const SaveAsTemplateButton = ({ onCollectSettings }) => {
	const [form] = Form.useForm();
	const [open, setOpen] = useState(false);
	const [saving, setSaving] = useState(false);

	const openModal = () => {
		form.resetFields();
		setOpen(true);
	};

	const closeModal = () => {
		if (!saving) {
			setOpen(false);
		}
	};

	/**
	 * Persist the template, creating or overwriting by id.
	 *
	 * @param {string}      name        Template name.
	 * @param {string}      description Template description.
	 * @param {Object}      settings    Settings payload.
	 * @param {number|null} overwriteId Existing template id to overwrite, or null.
	 */
	const persist = async (name, description, settings, overwriteId) => {
		setSaving(true);
		try {
			const path = overwriteId
				? `/ppq/v1/quiz-templates/${overwriteId}`
				: '/ppq/v1/quiz-templates';
			await apiFetch({
				path,
				method: overwriteId ? 'PUT' : 'POST',
				data: { name, description, settings },
			});
			message.success(
				overwriteId
					? __('Template updated.', 'pressprimer-quiz')
					: __('Template saved.', 'pressprimer-quiz')
			);
			setOpen(false);
			form.resetFields();
		} catch (error) {
			debugError('Failed to save template:', error);
			message.error(error.message || __('Failed to save template.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	const handleSubmit = async () => {
		let values;
		try {
			values = await form.validateFields();
		} catch (e) {
			return; // Validation errors are shown inline.
		}

		const name = (values.name || '').trim();
		const description = (values.description || '').trim();
		const settings = typeof onCollectSettings === 'function' ? onCollectSettings() : {};

		// Look for an existing saved template of the same name to offer overwrite.
		let existingId = null;
		try {
			const res = await apiFetch({ path: '/ppq/v1/quiz-templates' });
			const items = Array.isArray(res?.items) ? res.items : [];
			const match = items.find(
				(item) =>
					'template' === item.source &&
					(item.name || '').toLowerCase() === name.toLowerCase()
			);
			existingId = match ? match.id : null;
		} catch (error) {
			debugError('Failed to check existing templates:', error);
		}

		if (existingId) {
			Modal.confirm({
				title: __('Overwrite template?', 'pressprimer-quiz'),
				content: __(
					'A template with this name already exists. Overwrite it with the current settings?',
					'pressprimer-quiz'
				),
				okText: __('Overwrite', 'pressprimer-quiz'),
				cancelText: __('Cancel', 'pressprimer-quiz'),
				onOk: () => persist(name, description, settings, existingId),
			});
			return;
		}

		persist(name, description, settings, null);
	};

	return (
		<>
			<Button icon={<SaveOutlined />} onClick={openModal}>
				{__('Save as template…', 'pressprimer-quiz')}
			</Button>

			<Modal
				open={open}
				title={__('Save settings as a template', 'pressprimer-quiz')}
				onCancel={closeModal}
				onOk={handleSubmit}
				okText={__('Save template', 'pressprimer-quiz')}
				cancelText={__('Cancel', 'pressprimer-quiz')}
				confirmLoading={saving}
				destroyOnClose
			>
				<p style={{ marginTop: 0, color: '#8c8c8c' }}>
					{__('Captures this quiz’s current settings (not its title, questions, or banks) so you can apply them to other quizzes.', 'pressprimer-quiz')}
				</p>
				<Form form={form} layout="vertical" preserve={false}>
					<Form.Item
						name="name"
						label={__('Template name', 'pressprimer-quiz')}
						rules={[
							{ required: true, message: __('Please enter a template name.', 'pressprimer-quiz') },
							{ max: 100, message: __('Name must be 100 characters or fewer.', 'pressprimer-quiz') },
						]}
					>
						<Input
							placeholder={__('e.g., Department Standard', 'pressprimer-quiz')}
							maxLength={100}
						/>
					</Form.Item>
					<Form.Item
						name="description"
						label={__('Description (optional)', 'pressprimer-quiz')}
					>
						<TextArea
							rows={3}
							placeholder={__('What this template is for…', 'pressprimer-quiz')}
						/>
					</Form.Item>
				</Form>
			</Modal>
		</>
	);
};

export default SaveAsTemplateButton;
