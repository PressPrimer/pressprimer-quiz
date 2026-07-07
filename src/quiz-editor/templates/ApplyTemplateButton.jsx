/**
 * Apply Template control for the Quiz Builder Settings tab.
 *
 * Renders an "Apply template" dropdown (presets, then saved templates) and a
 * preview dialog listing each setting that will change (old → new) using the
 * builder's labels. Confirming fills the editor via the onApply callback;
 * nothing is persisted until the author saves the quiz. FR-003.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Dropdown,
	Modal,
	Spin,
	Alert,
	Empty,
	Typography,
} from 'antd';
import {
	AppstoreAddOutlined,
	DownOutlined,
	WarningOutlined,
} from '@ant-design/icons';
import { debugError } from '../../utils/debug';
import {
	TEMPLATE_FIELD_MAP,
	formatTemplateValue,
	normalizeForCompare,
	isAddonActive,
	addonLabel,
} from './templateFields';

const { Text } = Typography;

/**
 * Compute the change preview for a template against current editor values.
 *
 * @param {Object} template Template entry from the REST list.
 * @param {Object} form     Ant Design form instance.
 * @param {Object} quizData Quiz editor boot data (addon flags).
 * @return {{changes: Array, skipped: Array}} Preview model.
 */
function buildPreview(template, form, quizData) {
	const current = form.getFieldsValue(true);
	const settings = template.settings && typeof template.settings === 'object' ? template.settings : {};
	const changes = [];
	const skipped = [];

	Object.keys(settings).forEach((key) => {
		const meta = TEMPLATE_FIELD_MAP[key];
		const newVal = settings[key];

		// Keys this builder cannot apply: unknown (premium/add-on) or a core key
		// with no editor field, or an addon-gated key whose addon is inactive.
		if (!meta) {
			skipped.push({ key, label: key, reason: addonLabel('') });
			return;
		}
		if (meta.unappliable) {
			skipped.push({ key, label: meta.label, reason: __('not editable in this builder', 'pressprimer-quiz') });
			return;
		}
		if (meta.requiresAddon && !isAddonActive(meta.requiresAddon, quizData)) {
			skipped.push({
				key,
				label: meta.label,
				reason: sprintf(
					/* translators: %s: add-on name. */
					__('requires the %s', 'pressprimer-quiz'),
					addonLabel(meta.requiresAddon)
				),
			});
			return;
		}

		if ('json' === meta.type) {
			const currentVal = meta.formField ? current[meta.formField] : undefined;
			if (normalizeForCompare(meta, currentVal) === normalizeForCompare(meta, newVal)) {
				return;
			}
			changes.push({ key, label: meta.label, from: '', to: __('Updated', 'pressprimer-quiz'), json: true });
			return;
		}

		if (normalizeForCompare(meta, current[key]) === normalizeForCompare(meta, newVal)) {
			return;
		}

		changes.push({
			key,
			label: meta.label,
			from: formatTemplateValue(key, current[key]),
			to: formatTemplateValue(key, newVal),
		});
	});

	return { changes, skipped };
}

/**
 * Apply Template button + preview.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.form     Ant Design form instance.
 * @param {Object}   props.quizData Quiz editor boot data.
 * @param {Function} props.onApply  Called with (settings, reminders) on confirm.
 * @return {JSX.Element} The control.
 */
const ApplyTemplateButton = ({ form, quizData = {}, onApply }) => {
	const [loading, setLoading] = useState(true);
	const [items, setItems] = useState([]);
	const [selected, setSelected] = useState(null);

	useEffect(() => {
		let active = true;
		apiFetch({ path: '/ppq/v1/quiz-templates' })
			.then((res) => {
				if (active) {
					setItems(Array.isArray(res?.items) ? res.items : []);
				}
			})
			.catch((error) => {
				debugError('Failed to load quiz templates:', error);
				if (active) {
					setItems([]);
				}
			})
			.finally(() => {
				if (active) {
					setLoading(false);
				}
			});
		return () => {
			active = false;
		};
	}, []);

	const presets = items.filter((item) => 'preset' === item.source);
	const saved = items.filter((item) => 'template' === item.source);
	const myTemplates = saved.filter((item) => item.is_mine);
	const otherTemplates = saved.filter((item) => !item.is_mine);

	const menuItems = [];
	if (presets.length) {
		menuItems.push({
			type: 'group',
			label: __('Presets', 'pressprimer-quiz'),
			children: presets.map((p) => ({ key: `preset:${p.id}`, label: p.name })),
		});
	}
	if (myTemplates.length) {
		menuItems.push({
			type: 'group',
			label: __('My Templates', 'pressprimer-quiz'),
			children: myTemplates.map((t) => ({ key: `template:${t.id}`, label: t.name })),
		});
	}
	if (otherTemplates.length) {
		menuItems.push({
			type: 'group',
			label: __('Other Templates', 'pressprimer-quiz'),
			children: otherTemplates.map((t) => ({ key: `template:${t.id}`, label: t.name })),
		});
	}
	if (!menuItems.length) {
		menuItems.push({
			key: 'none',
			disabled: true,
			label: __('No templates available', 'pressprimer-quiz'),
		});
	}

	const onMenuClick = ({ key }) => {
		if ('none' === key) {
			return;
		}
		const template = items.find((item) => `${item.source}:${item.id}` === key);
		if (template) {
			setSelected(template);
		}
	};

	const preview = selected ? buildPreview(selected, form, quizData) : { changes: [], skipped: [] };
	const reminders = selected && Array.isArray(selected.reminders) ? selected.reminders : [];
	const changeCount = preview.changes.length;

	const closePreview = () => setSelected(null);

	const confirmApply = () => {
		if (selected && typeof onApply === 'function') {
			onApply(selected.settings || {}, reminders);
		}
		setSelected(null);
	};

	return (
		<>
			<Dropdown
				menu={{ items: menuItems, onClick: onMenuClick }}
				trigger={['click']}
				disabled={loading}
			>
				<Button icon={<AppstoreAddOutlined />}>
					{loading ? (
						<Spin size="small" style={{ marginRight: 8 }} />
					) : null}
					{__('Apply template', 'pressprimer-quiz')} <DownOutlined />
				</Button>
			</Dropdown>

			<Modal
				open={!!selected}
				title={
					selected
						? sprintf(
								/* translators: %s: template name. */
								__('Apply “%s”?', 'pressprimer-quiz'),
								selected.name
						  )
						: ''
				}
				onCancel={closePreview}
				onOk={confirmApply}
				okText={__('Apply to editor', 'pressprimer-quiz')}
				cancelText={__('Cancel', 'pressprimer-quiz')}
				okButtonProps={{ disabled: changeCount === 0 && preview.skipped.length === 0 }}
				width={620}
			>
				{selected && (
					<div className="ppq-template-preview">
						{changeCount === 0 && preview.skipped.length === 0 ? (
							<Empty
								image={Empty.PRESENTED_IMAGE_SIMPLE}
								description={__('This template matches the current settings — nothing will change.', 'pressprimer-quiz')}
							/>
						) : (
							<Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
								{changeCount > 0
									? sprintf(
											/* translators: %d: number of settings that will change. */
											_n(
												'This will change %d setting in the editor. Nothing is saved until you save the quiz.',
												'This will change %d settings in the editor. Nothing is saved until you save the quiz.',
												changeCount,
												'pressprimer-quiz'
											),
											changeCount
									  )
									: __('This template changes no editable settings here. Nothing is saved until you save the quiz.', 'pressprimer-quiz')}
							</Text>
						)}

						{changeCount > 0 && (
							<ul className="ppq-template-preview-list">
								{preview.changes.map((change) => (
									<li key={change.key} className="ppq-template-preview-row">
										<span className="ppq-template-preview-label">{change.label}</span>
										<span className="ppq-template-preview-values">
											{change.json ? (
												<span className="ppq-template-preview-to">{change.to}</span>
											) : (
												<>
													<span className="ppq-template-preview-from">{change.from}</span>
													<span className="ppq-template-preview-arrow" aria-hidden="true"> → </span>
													<span className="ppq-template-preview-to">{change.to}</span>
												</>
											)}
										</span>
									</li>
								))}
							</ul>
						)}

						{preview.skipped.length > 0 && (
							<div className="ppq-template-preview-skipped">
								<Text type="secondary" style={{ display: 'block', marginBottom: 4 }}>
									{__('Skipped:', 'pressprimer-quiz')}
								</Text>
								<ul className="ppq-template-preview-list">
									{preview.skipped.map((item) => (
										<li key={item.key} className="ppq-template-preview-row">
											<span className="ppq-template-preview-label">{item.label}</span>
											<span className="ppq-template-preview-skip-reason">
												{sprintf(
													/* translators: %s: reason the setting was skipped. */
													__('skipped (%s)', 'pressprimer-quiz'),
													item.reason
												)}
											</span>
										</li>
									))}
								</ul>
							</div>
						)}

						{reminders.length > 0 && (
							<Alert
								type="warning"
								showIcon
								icon={<WarningOutlined />}
								style={{ marginTop: 16 }}
								message={
									reminders.length === 1 ? (
										reminders[0]
									) : (
										<ul style={{ margin: 0, paddingLeft: 18 }}>
											{reminders.map((reminder, index) => (
												<li key={index}>{reminder}</li>
											))}
										</ul>
									)
								}
							/>
						)}
					</div>
				)}
			</Modal>
		</>
	);
};

export default ApplyTemplateButton;
