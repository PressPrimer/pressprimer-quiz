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
import { EditorProvider, getSiteFlags } from '../../editors/EditorContext';
import { emitSaved, toId } from '../../editors/events';
import {
	useSlotRegistry,
	useSlotHost,
	useSlotEnvironment,
	getVisibleSlots,
	SlotList,
} from '../../editors/slots';
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
 * @param {Object}   props          Component props
 * @param {Object}   props.data     Editor data (editor-data service payload)
 * @param {string}   props.surface  Where the editor runs: 'admin' or 'dashboard'
 * @param {Object}   props.nav      Navigation adapter (list, edit, afterCreate, editUrl, back)
 * @param {Object}   props.slots    Host slot definitions by slot id, added to the registered ones
 * @param {Function} props.onSaved  Called after a successful save, before navigating
 */
const BankEditor = ({ data = {}, surface = 'admin', nav, slots = {}, onSaved }) => {
	const bankData = data;
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [currentBankId, setCurrentBankId] = useState(bankData.id || null);

	const isNew = !currentBankId;
	const isAdmin = bankData.userCan?.manage_all || false;

	// Addon slots (bank.fields).
	useSlotRegistry();
	const slotHost = useSlotHost(form);
	const slotEnv = useSlotEnvironment(surface);
	const site = getSiteFlags(bankData);
	const fieldSlots = getVisibleSlots('bank.fields', {
		surface,
		site,
		hostSlots: slots,
		entityId: toId(currentBankId) || 0,
	});

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
			const entityId = toId(currentBankId) || 0;
			const slotErrors = await slotHost.runValidators({ entityId, values });
			if (slotErrors.length > 0) {
				slotErrors.forEach((err) => message.error(err));
				return;
			}

			const endpoint = isNew
				? '/ppq/v1/banks'
				: `/ppq/v1/banks/${currentBankId}`;

			const method = isNew ? 'POST' : 'PUT';

			// Values slots set are sent too (only when a slot set them).
			const bankPayload = { ...slotHost.setValues(), ...values };

			const response = await apiFetch({
				path: endpoint,
				method,
				data: bankPayload,
			});

			message.success(
				isNew
					? __('Bank created successfully!', 'pressprimer-quiz')
					: __('Bank updated successfully!', 'pressprimer-quiz')
			);

			// Addon contract: fields slotted into the editor (e.g. School's
			// Practice Access) persist their own state on this event so the
			// whole form saves from the one Update Bank button. Fired on
			// create too, before the editor leaves.
			emitSaved('ppq-bank-saved', { bankId: toId(response.id), isNew }, onSaved);
			await slotHost.runAfterSave({ entityId: toId(response.id), isNew, values: bankPayload, response });

			// If new, go where questions can be added (the bank's detail page
			// in wp-admin).
			if (isNew && response.id) {
				nav.afterCreate('bank', response.id);
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
		nav.list('bank');
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

			// Back to the banks list
			setTimeout(() => {
				nav.list('bank');
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
		<EditorProvider value={{ surface, nav, site, slots }}>
		<Layout className="ppq-bank-editor">
			<Content style={{ padding: '24px 0' }}>
				<Spin spinning={loading} tip={__('Loading...', 'pressprimer-quiz')}>
					<div ref={slotEnv.rootRef} style={{ maxWidth: 800, margin: '0 auto' }}>
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
									extra={bankData.visibilityHelp || __('Private: Only you can access this bank. Shared: Teachers in your groups can access this bank.', 'pressprimer-quiz')}
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

								{/* Addon fields slot: addons render extra bank
								    fields here (React portal target — must stay
								    childless in this tree). Slotted fields save
								    on the ppq-bank-saved event. */}
								<div id="ppq-bank-editor-fields-slot"></div>

								{/* bank.fields slots render below the legacy node. */}
								<SlotList
									defs={fieldSlots}
									slotProps={{
										entityId: toId(currentBankId) || 0,
										surface,
										nav,
										getValues: () => form.getFieldsValue(true),
										setFieldValue: slotHost.setFieldValue,
										registerValidator: slotHost.registerValidator,
										registerAfterSave: slotHost.registerAfterSave,
										getPopupContainer: slotEnv.getPopupContainer,
										theme: slotEnv.theme,
									}}
								/>
							</Form>
						</Card>

						{/* Bottom action bar (mirrors the quiz editor) */}
						<div style={{
							background: '#fff',
							padding: '20px 24px',
							borderRadius: 8,
							marginTop: 20,
							display: 'flex',
							justifyContent: isNew ? 'flex-end' : 'space-between',
							alignItems: 'center',
							gap: 12,
							boxShadow: '0 2px 8px rgba(0, 0, 0, 0.06)',
						}}>
							{!isNew && (
								<Button
									danger
									onClick={handleDelete}
									disabled={saving}
								>
									{__('Delete Bank', 'pressprimer-quiz')}
								</Button>
							)}
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
					</div>
				</Spin>
			</Content>
		</Layout>
		</EditorProvider>
	);
};

export default BankEditor;
