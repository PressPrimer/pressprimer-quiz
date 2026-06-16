/**
 * AI Provider Settings
 *
 * Site-level AI provider selector and per-provider API key managers (feature
 * 004, FR-004). Mirrors the PressPrimer Assignment settings UX: one key per
 * provider for the whole site, with a masked status indicator, a "Test key"
 * action, and a model selector per provider.
 *
 * Controlled component: the persistent AI state (active provider + per-provider
 * status/models/model) is owned by the parent so it survives tab switches.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Typography,
	Radio,
	Card,
	Form,
	Input,
	Button,
	Select,
	Space,
	Alert,
	Progress,
	Tag,
} from 'antd';
import {
	CheckCircleOutlined,
	WarningOutlined,
	EyeOutlined,
	EyeInvisibleOutlined,
	ReloadOutlined,
	DeleteOutlined,
	LockOutlined,
} from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Per-provider API key manager (controlled).
 *
 * Persistent values (status, models, modelPref) come from props and are pushed
 * back up via onDataChange; only transient input/busy state is local.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.providerId   Provider id (e.g. 'openai').
 * @param {Object}   props.data         Provider data { label, status, models, modelPref }.
 * @param {boolean}  props.isActive     Whether this is the active provider.
 * @param {Function} props.onDataChange Called with the next provider data.
 * @return {JSX.Element} The manager.
 */
const ProviderKeyManager = ({ providerId, data = {}, isActive, onDataChange }) => {
	const status = data.status || { configured: false };
	const models = data.models || [];
	const model = data.modelPref || '';

	const [keyInput, setKeyInput] = useState('');
	const [showKey, setShowKey] = useState(false);
	const [saving, setSaving] = useState(false);
	const [validating, setValidating] = useState(false);
	const [clearing, setClearing] = useState(false);
	const [loadingModels, setLoadingModels] = useState(false);
	const [result, setResult] = useState(null);

	const patch = (next) => onDataChange({ ...data, ...next });

	const refreshModels = async () => {
		setLoadingModels(true);
		try {
			const res = await apiFetch({
				path: `/ppq/v1/settings/api-models?provider=${encodeURIComponent(providerId)}`,
			});
			if (res.success && Array.isArray(res.models)) {
				patch({ models: res.models });
			}
		} catch (e) {
			// Leave the existing list in place on failure.
		} finally {
			setLoadingModels(false);
		}
	};

	const saveKey = async () => {
		if (!keyInput.trim()) {
			setResult({ type: 'error', message: __('Please enter an API key.', 'pressprimer-quiz') });
			return;
		}
		setSaving(true);
		setResult(null);
		try {
			const res = await apiFetch({
				path: '/ppq/v1/settings/api-key',
				method: 'POST',
				data: { provider: providerId, api_key: keyInput },
			});
			if (res.success) {
				setResult({ type: 'success', message: res.message || __('API key saved and validated.', 'pressprimer-quiz') });
				patch({ status: { configured: true, masked_key: res.masked_key || '' } });
				setKeyInput('');
				refreshModels();
			} else {
				setResult({ type: 'error', message: res.message || __('Failed to save API key.', 'pressprimer-quiz') });
			}
		} catch (e) {
			setResult({ type: 'error', message: e.message || __('Failed to save API key.', 'pressprimer-quiz') });
		} finally {
			setSaving(false);
		}
	};

	const validateKey = async () => {
		setValidating(true);
		setResult(null);
		try {
			const res = await apiFetch({
				path: '/ppq/v1/settings/api-key/validate',
				method: 'POST',
				data: { provider: providerId },
			});
			setResult(
				res.success
					? { type: 'success', message: res.message || __('API key is valid.', 'pressprimer-quiz') }
					: { type: 'error', message: res.message || __('Invalid API key.', 'pressprimer-quiz') }
			);
		} catch (e) {
			setResult({ type: 'error', message: e.message || __('Failed to validate API key.', 'pressprimer-quiz') });
		} finally {
			setValidating(false);
		}
	};

	const clearKey = async () => {
		// eslint-disable-next-line no-alert
		if (!window.confirm(__('Remove this API key? AI generation will stop working for this provider until a new key is added.', 'pressprimer-quiz'))) {
			return;
		}
		setClearing(true);
		setResult(null);
		try {
			const res = await apiFetch({
				path: '/ppq/v1/settings/api-key/clear',
				method: 'POST',
				data: { provider: providerId },
			});
			if (res.success) {
				setResult({ type: 'success', message: res.message || __('API key removed.', 'pressprimer-quiz') });
				patch({ status: { configured: false }, models: [] });
			}
		} catch (e) {
			setResult({ type: 'error', message: e.message || __('Failed to clear API key.', 'pressprimer-quiz') });
		} finally {
			setClearing(false);
		}
	};

	const changeModel = async (value) => {
		patch({ modelPref: value });
		try {
			await apiFetch({
				path: '/ppq/v1/settings/api-model',
				method: 'POST',
				data: { provider: providerId, model: value },
			});
		} catch (e) {
			// Non-blocking; the user can retry.
		}
	};

	return (
		<Card
			size="small"
			style={{ marginBottom: 16 }}
			title={
				<Space>
					<Text strong>{data.label || providerId}</Text>
					{isActive && <Tag color="blue">{__('Active', 'pressprimer-quiz')}</Tag>}
					{status.configured ? (
						<Tag color="success" icon={<CheckCircleOutlined />}>{__('Key set', 'pressprimer-quiz')}</Tag>
					) : (
						<Tag icon={<WarningOutlined />}>{__('No key', 'pressprimer-quiz')}</Tag>
					)}
				</Space>
			}
		>
			{status.configured && (
				<Paragraph style={{ marginBottom: 12 }}>
					{__('Configured key:', 'pressprimer-quiz')}{' '}
					<Text code>{status.masked_key || '••••'}</Text>
					<Space style={{ marginLeft: 12 }}>
						<Button size="small" onClick={validateKey} loading={validating}>
							{__('Test key', 'pressprimer-quiz')}
						</Button>
						<Button size="small" danger icon={<DeleteOutlined />} onClick={clearKey} loading={clearing}>
							{__('Clear', 'pressprimer-quiz')}
						</Button>
					</Space>
				</Paragraph>
			)}

			{result && (
				<Alert
					message={result.message}
					type={result.type}
					showIcon
					closable
					style={{ marginBottom: 12 }}
					onClose={() => setResult(null)}
				/>
			)}

			<Form.Item
				label={status.configured ? __('Replace API key', 'pressprimer-quiz') : __('Add API key', 'pressprimer-quiz')}
				style={{ marginBottom: 12 }}
			>
				<Space.Compact style={{ width: '100%', maxWidth: 520 }}>
					<Input
						type={showKey ? 'text' : 'password'}
						value={keyInput}
						onChange={(e) => setKeyInput(e.target.value)}
						placeholder={__('Paste the API key…', 'pressprimer-quiz')}
						autoComplete="off"
					/>
					<Button
						icon={showKey ? <EyeInvisibleOutlined /> : <EyeOutlined />}
						onClick={() => setShowKey(!showKey)}
					/>
					<Button type="primary" onClick={saveKey} loading={saving}>
						{__('Save key', 'pressprimer-quiz')}
					</Button>
				</Space.Compact>
			</Form.Item>

			{status.configured && (
				<Form.Item label={__('Model', 'pressprimer-quiz')} style={{ marginBottom: 0 }}>
					<Space>
						<Select
							value={model || undefined}
							onChange={changeModel}
							style={{ width: 320 }}
							loading={loadingModels}
							placeholder={__('Select a model', 'pressprimer-quiz')}
							options={models.map((m) => ({ value: m, label: m }))}
						/>
						<Button icon={<ReloadOutlined />} onClick={refreshModels} loading={loadingModels}>
							{__('Refresh', 'pressprimer-quiz')}
						</Button>
					</Space>
				</Form.Item>
			)}
		</Card>
	);
};

/**
 * AI provider settings section (controlled).
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.ai        AI state: { activeProvider, providers }.
 * @param {Function} props.onChange  Called with the next AI state.
 * @param {Object}   props.usageData Site-wide usage/rate-limit data.
 * @return {JSX.Element} The section.
 */
const AiProviderSettings = ({ ai = {}, onChange, usageData = {} }) => {
	const providers = ai.providers || {};
	const ids = Object.keys(providers);
	const active = ai.activeProvider || ids[0] || 'openai';
	const [switching, setSwitching] = useState(false);

	const setProviderData = (id, next) => {
		onChange({ ...ai, providers: { ...providers, [id]: next } });
	};

	const changeProvider = async (e) => {
		const next = e.target.value;
		const previous = active;
		onChange({ ...ai, activeProvider: next });
		setSwitching(true);
		try {
			await apiFetch({
				path: '/ppq/v1/settings/ai-provider',
				method: 'POST',
				data: { provider: next },
			});
		} catch (err) {
			onChange({ ...ai, activeProvider: previous }); // Revert on failure.
		} finally {
			setSwitching(false);
		}
	};

	return (
		<div className="ppq-settings-section">
			<Title level={4} className="ppq-settings-section-title">
				{__('AI Provider', 'pressprimer-quiz')}
			</Title>
			<Paragraph className="ppq-settings-section-description">
				{__('Choose your AI provider and add its API key for AI-powered question generation. Keys are stored encrypted and used site-wide; generation runs directly from your site to the provider.', 'pressprimer-quiz')}
			</Paragraph>

			{ids.length > 0 && (
				<Form.Item label={__('Active provider', 'pressprimer-quiz')}>
					<Radio.Group value={active} onChange={changeProvider} disabled={switching}>
						{ids.map((id) => (
							<Radio key={id} value={id}>{providers[id].label}</Radio>
						))}
					</Radio.Group>
				</Form.Item>
			)}

			{ids.map((id) => (
				<ProviderKeyManager
					key={id}
					providerId={id}
					data={providers[id]}
					isActive={id === active}
					onDataChange={(next) => setProviderData(id, next)}
				/>
			))}

			{/* Site-wide usage / rate limit. */}
			<div className="ppq-usage-stats">
				<div className="ppq-usage-stat">
					<span className="ppq-usage-stat-value">{usageData.requests_this_hour ?? 0}</span>
					<span className="ppq-usage-stat-label">{__('Requests', 'pressprimer-quiz')}</span>
				</div>
				<div className="ppq-usage-stat">
					<span className="ppq-usage-stat-value">{usageData.requests_remaining ?? 0}</span>
					<span className="ppq-usage-stat-label">{__('Remaining', 'pressprimer-quiz')}</span>
				</div>
				<div style={{ flex: 1, minWidth: 200 }}>
					<Progress percent={usageData.usage_percent ?? 0} showInfo={false} strokeColor="#2271b1" />
					<Paragraph type="secondary" style={{ marginTop: 4, marginBottom: 0 }}>
						{__('Rate limit:', 'pressprimer-quiz')} {usageData.rate_limit ?? 0} {__('requests per hour (shared across all users)', 'pressprimer-quiz')}
					</Paragraph>
				</div>
			</div>

			<div className="ppq-security-notice">
				<LockOutlined />
				<span>
					{__('API keys are encrypted using AES-256-CBC before storage. PressPrimer never receives your key or content.', 'pressprimer-quiz')}
				</span>
			</div>
		</div>
	);
};

export default AiProviderSettings;
