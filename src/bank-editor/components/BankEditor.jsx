/**
 * Bank Editor - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debugError } from '../../utils/debug';
import {
	Layout,
	Form,
	Input,
	Button,
	message,
	Spin,
	Space,
	Typography,
	Card,
	Select,
} from 'antd';
import {
	SaveOutlined,
	CloseOutlined,
} from '@ant-design/icons';

const { Content } = Layout;
const { Title } = Typography;
const { TextArea } = Input;

/**
 * Main Bank Editor Component
 *
 * @param {Object} props Component props
 * @param {Object} props.bankData Initial bank data
 */
const BankEditor = ({ bankData = {} }) => {
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [currentBankId, setCurrentBankId] = useState(bankData.id || null);

	const isNew = !currentBankId;
	const isAdmin = bankData.userCan?.manage_all || false;

	// Initialize form with bank data
	useEffect(() => {
		if (bankData.id) {
			form.setFieldsValue({
				name: bankData.name || '',
				description: bankData.description || '',
				visibility: bankData.visibility || 'private',
			});
		}
	}, [bankData, form]);

	/**
	 * Handle form submission
	 */
	const handleSubmit = async (values) => {
		setSaving(true);

		try {
			const endpoint = isNew
				? '/ppq/v1/banks'
				: `/ppq/v1/banks/${currentBankId}`;

			const method = isNew ? 'POST' : 'PUT';

			const response = await apiFetch({
				path: endpoint,
				method,
				data: values,
			});

			message.success(
				isNew
					? __('Bank created successfully!', 'pressprimer-quiz')
					: __('Bank updated successfully!', 'pressprimer-quiz')
			);

			// If new, redirect to detail page where questions can be added
			if (isNew && response.id) {
				window.location.href = `admin.php?page=pressprimer-quiz-banks&action=view&bank_id=${response.id}&message=bank_created`;
			} else {
				setCurrentBankId(response.id);
			}
		} catch (error) {
			debugError('Failed to save bank:', error);
			message.error(
				error.message || __('Failed to save bank. Please try again.', 'pressprimer-quiz')
			);
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Handle cancel/back button
	 */
	const handleCancel = () => {
		window.location.href = 'admin.php?page=pressprimer-quiz-banks';
	};

	/**
	 * Handle delete
	 */
	const handleDelete = async () => {
		if (!currentBankId) return;

		if (!confirm(__('Are you sure you want to delete this bank? This action cannot be undone.', 'pressprimer-quiz'))) {
			return;
		}

		setLoading(true);

		try {
			await apiFetch({
				path: `/ppq/v1/banks/${currentBankId}`,
				method: 'DELETE',
			});

			message.success(__('Bank deleted successfully!', 'pressprimer-quiz'));

			// Redirect to banks list
			setTimeout(() => {
				window.location.href = 'admin.php?page=pressprimer-quiz-banks';
			}, 500);
		} catch (error) {
			debugError('Failed to delete bank:', error);
			message.error(
				error.message || __('Failed to delete bank. Please try again.', 'pressprimer-quiz')
			);
			setLoading(false);
		}
	};

	return (
		<Layout className="ppq-bank-editor">
			<Content style={{ padding: '24px 0' }}>
				<Spin spinning={loading} tip={__('Loading...', 'pressprimer-quiz')}>
					<div style={{ maxWidth: 800, margin: '0 auto' }}>
						{/* Header */}
						<div style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
							<Title level={2} style={{ margin: 0 }}>
								{isNew
									? __('Add Question Bank', 'pressprimer-quiz')
									: __('Edit Question Bank', 'pressprimer-quiz')}
							</Title>
							<Space>
								<Button
									icon={<CloseOutlined />}
									onClick={handleCancel}
								>
									{__('Cancel', 'pressprimer-quiz')}
								</Button>
								<Button
									type="primary"
									icon={<SaveOutlined />}
									loading={saving}
									onClick={() => form.submit()}
								>
									{isNew
										? __('Create Bank', 'pressprimer-quiz')
										: __('Update Bank', 'pressprimer-quiz')}
								</Button>
							</Space>
						</div>

						{/* Form */}
						<Card>
							<Form
								form={form}
								layout="vertical"
								onFinish={handleSubmit}
								autoComplete="off"
							>
								<Form.Item
									name="name"
									label={__('Name', 'pressprimer-quiz')}
									rules={[
										{
											required: true,
											message: __('Please enter a bank name', 'pressprimer-quiz'),
										},
										{
											max: 200,
											message: __('Name cannot exceed 200 characters', 'pressprimer-quiz'),
										},
									]}
								>
									<Input
										placeholder={__('Enter a descriptive name for this question bank', 'pressprimer-quiz')}
										size="large"
									/>
								</Form.Item>

								<Form.Item
									name="description"
									label={
										<span style={{ fontSize: '14px', fontWeight: 600 }}>
											{__('Description', 'pressprimer-quiz')}
										</span>
									}
									extra={
										<span style={{ fontSize: '13px', fontStyle: 'italic' }}>
											{__("Optional description of the bank's purpose or contents", 'pressprimer-quiz')}
										</span>
									}
									rules={[
										{
											max: 2000,
											message: __('Description cannot exceed 2,000 characters', 'pressprimer-quiz'),
										},
									]}
								>
									<TextArea
										rows={6}
										placeholder={__('Enter description (optional)', 'pressprimer-quiz')}
										style={{
											fontSize: '14px',
											lineHeight: '1.6',
										}}
									/>
								</Form.Item>

								<Form.Item
									name="visibility"
									label={__('Visibility', 'pressprimer-quiz')}
									extra={__('Private: Only you can access this bank. Shared: Teachers in your groups can access this bank.', 'pressprimer-quiz')}
									initialValue="private"
								>
									<Select size="large">
										<Select.Option value="private">
											{__('Private - Only Me', 'pressprimer-quiz')}
										</Select.Option>
										<Select.Option value="shared">
											{__('Shared - My Groups', 'pressprimer-quiz')}
										</Select.Option>
									</Select>
								</Form.Item>
							</Form>
						</Card>

						{/* Delete Button (for existing banks) */}
						{!isNew && (
							<div style={{ marginTop: 24, textAlign: 'right' }}>
								<Button
									danger
									onClick={handleDelete}
									disabled={saving}
								>
									{__('Delete Bank', 'pressprimer-quiz')}
								</Button>
							</div>
						)}
					</div>
				</Spin>
			</Content>
		</Layout>
	);
};

export default BankEditor;
