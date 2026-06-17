/**
 * AI Provider Settings
 *
 * Site-level AI provider selector and API key management (feature 004, FR-004).
 * Mirrors the PressPrimer Assignment School AI settings UX: a segmented
 * provider selector, the active provider's key card only, a per-provider model
 * selector, and a color-coded usage bar.
 *
 * Controlled component: the persistent AI state (active provider + per-provider
 * status/models/model) is owned by the parent so it survives tab switches.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	Button,
	Radio,
	Select,
	Progress,
	Typography,
	Space,
	Alert,
	message,
} from 'antd';
import {
	CheckCircleOutlined,
	WarningOutlined,
	EyeOutlined,
	EyeInvisibleOutlined,
	DeleteOutlined,
	LockOutlined,
	ReloadOutlined,
	SafetyCertificateOutlined,
} from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Per-provider presentation details (placeholder, hint, console link).
 * Labels prefer the server-provided label; these are UI fallbacks/extras.
 */
const PROVIDER_UI = {
	openai: {
		label: 'OpenAI',
		placeholder: 'sk-...',
		hint: __( 'Keys start with "sk-".', 'pressprimer-quiz' ),
		consoleUrl: 'https://platform.openai.com/api-keys',
		consoleLabel: 'OpenAI Platform',
	},
	anthropic: {
		label: 'Anthropic (Claude)',
		placeholder: 'sk-ant-...',
		hint: __( 'Keys start with "sk-ant-".', 'pressprimer-quiz' ),
		consoleUrl: 'https://console.anthropic.com/settings/keys',
		consoleLabel: 'Anthropic Console',
	},
};

/**
 * API key card for the active provider.
 *
 * @param {Object}   props                 Component props.
 * @param {string}   props.providerId      Provider id.
 * @param {string}   props.label           Provider label.
 * @param {Object}   props.ui              Provider UI details (placeholder/hint/console).
 * @param {Object}   props.status          Current status { configured, masked_key }.
 * @param {Function} props.onStatusChange  Called with the next status after save/clear.
 * @param {Function} props.onConfigured    Called after a key is saved (to refresh models).
 * @return {JSX.Element} The card.
 */
const ApiKeyCard = ( { providerId, label, ui, status, onStatusChange, onConfigured } ) => {
	const [ newKey, setNewKey ] = useState( '' );
	const [ showKey, setShowKey ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ clearing, setClearing ] = useState( false );
	const [ validating, setValidating ] = useState( false );
	const [ validationResult, setValidationResult ] = useState( null );

	const handleSave = async () => {
		const trimmed = ( newKey || '' ).trim();
		if ( ! trimmed ) {
			message.error( __( 'Please enter an API key.', 'pressprimer-quiz' ) );
			return;
		}
		setSaving( true );
		try {
			const res = await apiFetch( {
				path: '/ppq/v1/settings/api-key',
				method: 'POST',
				data: { provider: providerId, api_key: trimmed },
			} );
			message.success( res.message || __( 'API key saved.', 'pressprimer-quiz' ) );
			setNewKey( '' );
			setShowKey( false );
			setValidationResult( null );
			onStatusChange( { configured: true, masked_key: res.masked_key || '' } );
			onConfigured();
		} catch ( error ) {
			message.error( error.message || __( 'Failed to save API key.', 'pressprimer-quiz' ) );
		} finally {
			setSaving( false );
		}
	};

	const handleClear = async () => {
		setClearing( true );
		try {
			const res = await apiFetch( {
				path: `/ppq/v1/settings/api-key?provider=${ encodeURIComponent( providerId ) }`,
				method: 'DELETE',
			} );
			message.success( res.message || __( 'API key removed.', 'pressprimer-quiz' ) );
			setValidationResult( null );
			onStatusChange( { configured: false, masked_key: '' } );
		} catch ( error ) {
			message.error( error.message || __( 'Failed to remove API key.', 'pressprimer-quiz' ) );
		} finally {
			setClearing( false );
		}
	};

	const handleValidate = async () => {
		setValidating( true );
		setValidationResult( null );
		try {
			const res = await apiFetch( {
				path: '/ppq/v1/settings/api-key/validate',
				method: 'POST',
				data: { provider: providerId },
			} );
			setValidationResult( {
				type: 'success',
				message: res.message || __( 'API key is valid and working correctly.', 'pressprimer-quiz' ),
			} );
		} catch ( error ) {
			// A provider rate limit means we could not confirm — the key may still be valid.
			const rateLimited = error.code === 'ppq_provider_rate_limited' || error.code === 'ppq_rate_limited';
			setValidationResult( {
				type: rateLimited ? 'warning' : 'error',
				message: error.message || __( 'Invalid API key.', 'pressprimer-quiz' ),
			} );
		} finally {
			setValidating( false );
		}
	};

	return (
		<div className="ppq-api-key-card" style={ { marginBottom: 24 } }>
			<Title level={ 5 } style={ { marginTop: 0, marginBottom: 8 } }>
				{ label }
			</Title>

			<div
				className={ status.configured ? 'ppq-api-key-status ppq-api-key-status--configured' : 'ppq-api-key-status ppq-api-key-status--not-configured' }
				style={ {
					alignItems: 'center',
					background: status.configured ? '#f6ffed' : '#fffbe6',
					border: `1px solid ${ status.configured ? '#b7eb8f' : '#ffe58f' }`,
					borderRadius: 6,
					display: 'flex',
					gap: 8,
					marginBottom: 16,
					padding: '8px 12px',
				} }
			>
				{ status.configured ? (
					<>
						<CheckCircleOutlined style={ { color: '#52c41a' } } />
						<Text>
							{ __( 'API Key Configured:', 'pressprimer-quiz' ) }{ ' ' }
							<Text code>{ status.masked_key || '***' }</Text>
						</Text>
						<Space style={ { marginLeft: 'auto' } }>
							<Button size="small" icon={ <SafetyCertificateOutlined /> } onClick={ handleValidate } loading={ validating }>
								{ __( 'Validate', 'pressprimer-quiz' ) }
							</Button>
							<Button size="small" danger icon={ <DeleteOutlined /> } onClick={ handleClear } loading={ clearing }>
								{ __( 'Clear', 'pressprimer-quiz' ) }
							</Button>
						</Space>
					</>
				) : (
					<>
						<WarningOutlined style={ { color: '#faad14' } } />
						<Text>{ __( 'No API Key Configured', 'pressprimer-quiz' ) }</Text>
					</>
				) }
			</div>

			{ validationResult && (
				<Alert
					message={ validationResult.message }
					type={ validationResult.type }
					showIcon
					closable
					onClose={ () => setValidationResult( null ) }
					style={ { marginBottom: 12 } }
				/>
			) }

			<Form.Item
				label={ status.configured ? __( 'Replace API Key:', 'pressprimer-quiz' ) : __( 'API Key:', 'pressprimer-quiz' ) }
				style={ { marginBottom: 8 } }
			>
				<Space.Compact style={ { width: '100%', maxWidth: 500 } }>
					<Input
						type={ showKey ? 'text' : 'password' }
						value={ newKey }
						onChange={ ( e ) => setNewKey( e.target.value ) }
						placeholder={ ui.placeholder }
						autoComplete="off"
					/>
					<Button
						icon={ showKey ? <EyeInvisibleOutlined /> : <EyeOutlined /> }
						onClick={ () => setShowKey( ! showKey ) }
						aria-label={ showKey ? __( 'Hide API key', 'pressprimer-quiz' ) : __( 'Show API key', 'pressprimer-quiz' ) }
					/>
					<Button type="primary" onClick={ handleSave } loading={ saving }>
						{ __( 'Save Key', 'pressprimer-quiz' ) }
					</Button>
				</Space.Compact>
				<Paragraph type="secondary" style={ { marginTop: 8, marginBottom: 0 } }>
					{ __( 'Get your API key from', 'pressprimer-quiz' ) }{ ' ' }
					<a href={ ui.consoleUrl } target="_blank" rel="noopener noreferrer">{ ui.consoleLabel }</a>
					{ '. ' }
					{ ui.hint }
				</Paragraph>
			</Form.Item>
		</div>
	);
};

/**
 * Model selector for the active provider.
 *
 * @param {Object}   props                Component props.
 * @param {string}   props.providerId     Active provider id.
 * @param {string}   props.label          Active provider label.
 * @param {Array}    props.models         Available model ids.
 * @param {string}   props.selectedModel  Currently selected model.
 * @param {Function} props.onModelsChange Called with a refreshed model list.
 * @param {Function} props.onModelChange  Called when the model selection changes.
 * @return {JSX.Element} The selector.
 */
const ModelSelector = ( { providerId, label, models, selectedModel, onModelsChange, onModelChange } ) => {
	const [ loadingModels, setLoadingModels ] = useState( false );

	const fetchModels = async () => {
		setLoadingModels( true );
		try {
			const res = await apiFetch( {
				path: `/ppq/v1/settings/api-models?provider=${ encodeURIComponent( providerId ) }`,
			} );
			if ( res.models && Array.isArray( res.models ) ) {
				onModelsChange( res.models );
			}
		} catch ( error ) {
			message.error( error.message || __( 'Failed to fetch models.', 'pressprimer-quiz' ) );
		} finally {
			setLoadingModels( false );
		}
	};

	// Auto-fetch when the active provider has no models loaded yet.
	useEffect( () => {
		if ( ! models || models.length === 0 ) {
			fetchModels();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ providerId ] );

	const handleModelChange = async ( model ) => {
		onModelChange( model );
		try {
			await apiFetch( {
				path: '/ppq/v1/settings/api-model',
				method: 'POST',
				data: { provider: providerId, model },
			} );
			message.success( __( 'Model updated.', 'pressprimer-quiz' ) );
		} catch ( error ) {
			message.error( error.message || __( 'Failed to save model preference.', 'pressprimer-quiz' ) );
		}
	};

	return (
		<div style={ { marginBottom: 24 } }>
			<Form.Item
				label={ sprintf(
					/* translators: %s: AI provider name. */
					__( '%s Model:', 'pressprimer-quiz' ),
					label
				) }
				style={ { marginBottom: 8 } }
			>
				<Space>
					<Select
						value={ selectedModel || undefined }
						onChange={ handleModelChange }
						style={ { width: 300 } }
						loading={ loadingModels }
						placeholder={ __( 'Select a model', 'pressprimer-quiz' ) }
						options={ ( models || [] ).map( ( model ) => ( { value: model, label: model } ) ) }
					/>
					<Button icon={ <ReloadOutlined /> } onClick={ fetchModels } loading={ loadingModels }>
						{ __( 'Refresh', 'pressprimer-quiz' ) }
					</Button>
				</Space>
			</Form.Item>
			<Paragraph type="secondary" style={ { marginTop: 4, marginBottom: 0 } }>
				{ sprintf(
					/* translators: %s: AI provider name. */
					__( 'Select the %s model to use for question generation.', 'pressprimer-quiz' ),
					label
				) }
			</Paragraph>
		</div>
	);
};

/**
 * Site-wide usage stats with a color-coded progress bar.
 *
 * @param {Object} props           Component props.
 * @param {Object} props.usageData Usage data.
 * @return {JSX.Element} The stats.
 */
const UsageStats = ( { usageData } ) => {
	const percent = usageData.usage_percent ?? 0;
	let strokeColor = '#2271b1';
	if ( percent >= 90 ) {
		strokeColor = '#ff4d4f';
	} else if ( percent >= 70 ) {
		strokeColor = '#faad14';
	}

	return (
		<div style={ { marginBottom: 24 } }>
			<Text strong style={ { display: 'block', marginBottom: 8 } }>
				{ __( 'API Usage This Hour', 'pressprimer-quiz' ) }
			</Text>
			<div style={ { display: 'flex', alignItems: 'center', gap: 24 } }>
				<div style={ { textAlign: 'center' } }>
					<div style={ { fontSize: 20, fontWeight: 600, lineHeight: 1.2 } }>{ usageData.requests_this_hour ?? 0 }</div>
					<Text type="secondary" style={ { fontSize: 12 } }>{ __( 'Requests', 'pressprimer-quiz' ) }</Text>
				</div>
				<div style={ { textAlign: 'center' } }>
					<div style={ { fontSize: 20, fontWeight: 600, lineHeight: 1.2 } }>{ usageData.requests_remaining ?? 0 }</div>
					<Text type="secondary" style={ { fontSize: 12 } }>{ __( 'Remaining', 'pressprimer-quiz' ) }</Text>
				</div>
				<div style={ { flex: 1, minWidth: 200 } }>
					<Progress percent={ percent } showInfo={ false } strokeColor={ strokeColor } />
					<Paragraph type="secondary" style={ { marginTop: 4, marginBottom: 0 } }>
						{ sprintf(
							/* translators: %d: rate limit per hour. */
							__( 'Rate limit: %d requests per hour (shared across all users)', 'pressprimer-quiz' ),
							usageData.rate_limit ?? 0
						) }
					</Paragraph>
				</div>
			</div>
		</div>
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
const AiProviderSettings = ( { ai = {}, onChange, usageData = {} } ) => {
	const providers = ai.providers || {};
	const ids = Object.keys( providers );
	const active = ai.activeProvider || ids[ 0 ] || 'openai';
	const [ savingProvider, setSavingProvider ] = useState( false );

	const activeData = providers[ active ] || { status: { configured: false }, models: [], modelPref: '' };
	const activeStatus = activeData.status || { configured: false };
	const activeUi = PROVIDER_UI[ active ] || { label: activeData.label || active, placeholder: '', hint: '', consoleUrl: '', consoleLabel: '' };
	const activeLabel = activeUi.label || activeData.label || active;

	const patchProvider = ( id, partial ) => {
		onChange( { ...ai, providers: { ...providers, [ id ]: { ...providers[ id ], ...partial } } } );
	};

	const handleProviderChange = async ( e ) => {
		const next = e.target.value;
		const previous = active;
		onChange( { ...ai, activeProvider: next } );
		setSavingProvider( true );
		try {
			await apiFetch( {
				path: '/ppq/v1/settings/ai-provider',
				method: 'POST',
				data: { provider: next },
			} );
			message.success( __( 'Provider updated.', 'pressprimer-quiz' ) );
		} catch ( error ) {
			message.error( error.message || __( 'Failed to update provider.', 'pressprimer-quiz' ) );
			onChange( { ...ai, activeProvider: previous } );
		} finally {
			setSavingProvider( false );
		}
	};

	const refreshActiveModels = async () => {
		try {
			const res = await apiFetch( {
				path: `/ppq/v1/settings/api-models?provider=${ encodeURIComponent( active ) }`,
			} );
			if ( res.models && Array.isArray( res.models ) ) {
				patchProvider( active, { models: res.models } );
			}
		} catch ( error ) {
			// Non-blocking; the model selector has a manual Refresh.
		}
	};

	return (
		<div className="ppq-settings-section">
			<Title level={ 4 } className="ppq-settings-section-title">
				{ __( 'AI Provider', 'pressprimer-quiz' ) }
			</Title>
			<Paragraph className="ppq-settings-section-description">
				{ __( 'Configure an AI provider to enable AI-powered question generation. Keys are encrypted at rest and never sent to the browser in plaintext.', 'pressprimer-quiz' ) }
			</Paragraph>

			<Form layout="vertical">
				<Form.Item label={ __( 'AI Provider', 'pressprimer-quiz' ) } style={ { marginBottom: 24 } }>
					<Radio.Group
						value={ active }
						onChange={ handleProviderChange }
						disabled={ savingProvider }
						optionType="button"
						buttonStyle="solid"
					>
						{ ids.map( ( id ) => (
							<Radio.Button key={ id } value={ id }>
								{ ( PROVIDER_UI[ id ] && PROVIDER_UI[ id ].label ) || providers[ id ].label }
							</Radio.Button>
						) ) }
					</Radio.Group>
					<Paragraph type="secondary" style={ { marginTop: 8, marginBottom: 0 } }>
						{ __( 'The active provider is used for all AI requests across the site.', 'pressprimer-quiz' ) }
					</Paragraph>
				</Form.Item>

				{ /* Only the active provider's key card is shown. */ }
				<ApiKeyCard
					key={ active }
					providerId={ active }
					label={ activeLabel }
					ui={ activeUi }
					status={ activeStatus }
					onStatusChange={ ( next ) => patchProvider( active, { status: next } ) }
					onConfigured={ refreshActiveModels }
				/>

				{ activeStatus.configured && (
					<ModelSelector
						key={ active }
						providerId={ active }
						label={ activeLabel }
						models={ activeData.models || [] }
						selectedModel={ activeData.modelPref || '' }
						onModelsChange={ ( models ) => patchProvider( active, { models } ) }
						onModelChange={ ( model ) => patchProvider( active, { modelPref: model } ) }
					/>
				) }

				{ activeStatus.configured && <UsageStats usageData={ usageData } /> }

				<Alert
					type="info"
					showIcon
					icon={ <LockOutlined /> }
					message={ __( 'Your data, your account', 'pressprimer-quiz' ) }
					description={ __( 'API keys are encrypted using AES-256-CBC before storage. Generation content is sent only to the provider you configure — review each provider’s privacy policy and terms before enabling AI features. PressPrimer never receives your key or content.', 'pressprimer-quiz' ) }
					style={ { marginTop: 16 } }
				/>
			</Form>
		</div>
	);
};

export default AiProviderSettings;
