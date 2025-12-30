/**
 * Appearance Tab Component
 *
 * Global theme style settings that apply to all quiz themes.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Form,
	Select,
	Typography,
	ColorPicker,
	InputNumber,
	Space,
	Divider,
	Button,
	Card,
	Radio,
} from 'antd';
import { UndoOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Theme-specific default colors
 */
const THEME_DEFAULTS = {
	default: {
		primary: '#0073aa',
		text: '#1d2327',
		background: '#ffffff',
		success: '#00a32a',
		error: '#d63638',
		borderRadius: 6,
	},
	modern: {
		primary: '#4f46e5',
		text: '#1e293b',
		background: '#ffffff',
		success: '#059669',
		error: '#dc2626',
		borderRadius: 10,
	},
	minimal: {
		primary: '#111827',
		text: '#111827',
		background: '#ffffff',
		success: '#059669',
		error: '#dc2626',
		borderRadius: 2,
	},
};

/**
 * Get font family options including theme font if available
 */
const getFontFamilyOptions = (themeFont) => {
	const options = [
		{
			value: '',
			label: __('Default', 'pressprimer-quiz'),
		},
	];

	// Add theme font option if available
	if (themeFont && themeFont.value) {
		options.push({
			value: themeFont.value,
			label: themeFont.name + ' ' + __('(WordPress Theme)', 'pressprimer-quiz'),
		});
	}

	// Add standard font options
	options.push(
		{
			value: 'Georgia, "Times New Roman", Times, serif',
			label: __('Georgia (Serif)', 'pressprimer-quiz'),
		},
		{
			value: '"Palatino Linotype", "Book Antiqua", Palatino, serif',
			label: __('Palatino (Serif)', 'pressprimer-quiz'),
		},
		{
			value: 'Arial, Helvetica, sans-serif',
			label: __('Arial (Sans-serif)', 'pressprimer-quiz'),
		},
		{
			value: 'Verdana, Geneva, sans-serif',
			label: __('Verdana (Sans-serif)', 'pressprimer-quiz'),
		},
		{
			value: 'Tahoma, Geneva, sans-serif',
			label: __('Tahoma (Sans-serif)', 'pressprimer-quiz'),
		},
		{
			value: '"Trebuchet MS", Helvetica, sans-serif',
			label: __('Trebuchet MS (Sans-serif)', 'pressprimer-quiz'),
		},
		{
			value: '"Courier New", Courier, monospace',
			label: __('Courier New (Monospace)', 'pressprimer-quiz'),
		}
	);

	return options;
};

/**
 * Font size options (base font size)
 */
const FONT_SIZE_OPTIONS = [
	{
		value: '',
		label: __('Default (16px)', 'pressprimer-quiz'),
	},
	{
		value: '14px',
		label: __('Small (14px)', 'pressprimer-quiz'),
	},
	{
		value: '15px',
		label: __('Medium Small (15px)', 'pressprimer-quiz'),
	},
	{
		value: '17px',
		label: __('Medium Large (17px)', 'pressprimer-quiz'),
	},
	{
		value: '18px',
		label: __('Large (18px)', 'pressprimer-quiz'),
	},
	{
		value: '20px',
		label: __('Extra Large (20px)', 'pressprimer-quiz'),
	},
];

/**
 * Convert Ant Design color to hex
 */
const colorToHex = (color) => {
	if (!color) return '';
	if (typeof color === 'string') return color;
	if (color.toHexString) return color.toHexString();
	return '';
};

/**
 * Color setting component with reset button
 */
const ColorSetting = ({ label, help, value, defaultColor, onChange }) => {
	// Check if there's a custom value set (not empty/null/undefined)
	const hasCustomValue = value !== '' && value !== null && value !== undefined;

	// The displayed color - use custom value if set, otherwise default
	const displayedColor = hasCustomValue ? value : defaultColor;

	return (
		<div className="ppq-settings-field">
			<Form.Item
				label={label}
				help={help}
			>
				<Space align="center" wrap>
					<ColorPicker
						value={displayedColor}
						onChange={(color) => onChange(colorToHex(color))}
						showText
					/>
					{hasCustomValue ? (
						<Button
							type="link"
							icon={<UndoOutlined />}
							onClick={() => onChange('')}
							size="small"
						>
							{__('Reset to Default', 'pressprimer-quiz')}
						</Button>
					) : (
						<Text type="secondary">
							({__('Default', 'pressprimer-quiz')})
						</Text>
					)}
				</Space>
			</Form.Item>
		</div>
	);
};

/**
 * Preview component showing sample quiz elements
 */
const AppearancePreview = ({ settings, themeFont }) => {
	const [selectedTheme, setSelectedTheme] = useState('default');

	// Compute preview styles based on settings AND selected theme
	const previewStyles = useMemo(() => {
		const themeDefaults = THEME_DEFAULTS[selectedTheme];

		// Use custom settings if set, otherwise fall back to selected theme's defaults
		const fontFamily = settings.appearance_font_family || '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
		const baseFontSize = settings.appearance_font_size || '16px';
		const primaryColor = settings.appearance_primary_color || themeDefaults.primary;
		const textColor = settings.appearance_text_color || themeDefaults.text;
		const bgColor = settings.appearance_background_color || themeDefaults.background;
		const successColor = settings.appearance_success_color || themeDefaults.success;
		const errorColor = settings.appearance_error_color || themeDefaults.error;
		const borderRadius = (settings.appearance_border_radius !== '' && settings.appearance_border_radius !== null && settings.appearance_border_radius !== undefined)
			? settings.appearance_border_radius
			: themeDefaults.borderRadius;

		// Calculate scaled font sizes based on base
		const basePx = parseFloat(baseFontSize) || 16;
		const fontSizes = {
			base: `${basePx}px`,
			sm: `${Math.round(basePx * 0.875)}px`,
			lg: `${Math.round(basePx * 1.125)}px`,
			xl: `${Math.round(basePx * 1.25)}px`,
		};

		return {
			container: {
				fontFamily,
				fontSize: fontSizes.base,
				color: textColor,
				backgroundColor: bgColor,
				borderRadius: `${borderRadius}px`,
				padding: '24px',
				border: '1px solid #e5e7eb',
				lineHeight: 1.6,
			},
			questionTitle: {
				margin: '0 0 16px',
				fontSize: fontSizes.xl,
				fontWeight: 600,
			},
			questionText: {
				margin: '0 0 16px',
				fontSize: fontSizes.base,
			},
			button: {
				backgroundColor: primaryColor,
				color: '#ffffff',
				border: 'none',
				padding: '10px 20px',
				borderRadius: `${borderRadius}px`,
				cursor: 'pointer',
				fontFamily,
				fontSize: fontSizes.base,
				fontWeight: 500,
			},
			buttonOutline: {
				backgroundColor: 'transparent',
				color: primaryColor,
				border: `2px solid ${primaryColor}`,
				padding: '8px 18px',
				borderRadius: `${borderRadius}px`,
				cursor: 'pointer',
				fontFamily,
				fontSize: fontSizes.base,
			},
			answerOption: {
				border: '2px solid #e5e7eb',
				borderRadius: `${borderRadius}px`,
				padding: '12px 16px',
				marginBottom: '8px',
				cursor: 'pointer',
				display: 'flex',
				alignItems: 'center',
				gap: '12px',
				backgroundColor: bgColor,
				fontSize: fontSizes.base,
			},
			answerOptionSelected: {
				border: `2px solid ${primaryColor}`,
				backgroundColor: `${primaryColor}15`,
				borderRadius: `${borderRadius}px`,
				padding: '12px 16px',
				marginBottom: '8px',
				cursor: 'pointer',
				display: 'flex',
				alignItems: 'center',
				gap: '12px',
				fontSize: fontSizes.base,
			},
			radio: {
				width: '20px',
				height: '20px',
				border: '2px solid #9ca3af',
				borderRadius: '50%',
				flexShrink: 0,
				backgroundColor: bgColor,
			},
			radioSelected: {
				width: '20px',
				height: '20px',
				border: `6px solid ${primaryColor}`,
				borderRadius: '50%',
				flexShrink: 0,
				backgroundColor: bgColor,
			},
			successBadge: {
				backgroundColor: successColor,
				color: '#ffffff',
				padding: '4px 12px',
				borderRadius: `${borderRadius}px`,
				fontSize: fontSizes.sm,
				display: 'inline-block',
				fontWeight: 500,
			},
			errorBadge: {
				backgroundColor: errorColor,
				color: '#ffffff',
				padding: '4px 12px',
				borderRadius: `${borderRadius}px`,
				fontSize: fontSizes.sm,
				display: 'inline-block',
				fontWeight: 500,
			},
		};
	}, [settings, selectedTheme]);

	return (
		<Card
			title={__('Live Preview', 'pressprimer-quiz')}
			size="small"
			extra={
				<Radio.Group
					value={selectedTheme}
					onChange={(e) => setSelectedTheme(e.target.value)}
					size="small"
					optionType="button"
					buttonStyle="solid"
				>
					<Radio.Button value="default">{__('Default', 'pressprimer-quiz')}</Radio.Button>
					<Radio.Button value="modern">{__('Modern', 'pressprimer-quiz')}</Radio.Button>
					<Radio.Button value="minimal">{__('Minimal', 'pressprimer-quiz')}</Radio.Button>
				</Radio.Group>
			}
			style={{ marginBottom: '24px' }}
		>
			<Paragraph type="secondary" style={{ marginBottom: 16 }}>
				{__('Preview how your custom settings will look on each theme. Switch themes to see the differences.', 'pressprimer-quiz')}
			</Paragraph>
			<div style={previewStyles.container}>
				<h3 style={previewStyles.questionTitle}>
					{__('Sample Question', 'pressprimer-quiz')}
				</h3>
				<p style={previewStyles.questionText}>
					{__('What is the capital of France?', 'pressprimer-quiz')}
				</p>

				<div style={previewStyles.answerOption}>
					<span style={previewStyles.radio}></span>
					<span>{__('London', 'pressprimer-quiz')}</span>
				</div>
				<div style={previewStyles.answerOptionSelected}>
					<span style={previewStyles.radioSelected}></span>
					<span>{__('Paris', 'pressprimer-quiz')}</span>
				</div>
				<div style={previewStyles.answerOption}>
					<span style={previewStyles.radio}></span>
					<span>{__('Berlin', 'pressprimer-quiz')}</span>
				</div>

				<div style={{ marginTop: '20px', display: 'flex', gap: '12px', flexWrap: 'wrap', alignItems: 'center' }}>
					<button style={previewStyles.button}>
						{__('Next Question', 'pressprimer-quiz')}
					</button>
					<button style={previewStyles.buttonOutline}>
						{__('Previous', 'pressprimer-quiz')}
					</button>
					<span style={previewStyles.successBadge}>
						{__('Passed', 'pressprimer-quiz')}
					</span>
					<span style={previewStyles.errorBadge}>
						{__('Failed', 'pressprimer-quiz')}
					</span>
				</div>
			</div>
		</Card>
	);
};

/**
 * Appearance Tab - Global theme style settings
 *
 * @param {Object} props Component props
 * @param {Object} props.settings Current settings
 * @param {Function} props.updateSetting Function to update a setting
 * @param {Object} props.settingsData Full settings data including appearance defaults
 */
const AppearanceTab = ({ settings, updateSetting, settingsData }) => {
	// Get appearance data with defaults
	const appearance = settingsData?.appearance || {};
	const themeFont = appearance.themeFont || null;

	// Use Default theme colors as the reference defaults shown in UI
	const defaultColors = THEME_DEFAULTS.default;

	// Build font family options
	const fontFamilyOptions = useMemo(() => getFontFamilyOptions(themeFont), [themeFont]);

	return (
		<div>
			{/* Live Preview */}
			<AppearancePreview
				settings={settings}
				themeFont={themeFont}
			/>

			{/* Typography Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Typography', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Customize fonts for all quiz themes. These settings apply globally to Default, Modern, and Minimal themes.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Font Family', 'pressprimer-quiz')}
						help={
							themeFont
								? __('Choose a font family. Your WordPress theme font is available as an option.', 'pressprimer-quiz')
								: __('Choose a font family for quiz text.', 'pressprimer-quiz')
						}
					>
						<Select
							value={settings.appearance_font_family || ''}
							onChange={(value) => updateSetting('appearance_font_family', value)}
							style={{ width: 350 }}
							options={fontFamilyOptions}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Base Font Size', 'pressprimer-quiz')}
						help={__('The base font size for quiz content. Other sizes scale proportionally.', 'pressprimer-quiz')}
					>
						<Select
							value={settings.appearance_font_size || ''}
							onChange={(value) => updateSetting('appearance_font_size', value)}
							style={{ width: 350 }}
							options={FONT_SIZE_OPTIONS}
						/>
					</Form.Item>
				</div>
			</div>

			<Divider />

			{/* Colors Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Colors', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Override theme colors globally. Custom colors apply to all themes. Use "Reset to Default" to restore theme-specific colors.', 'pressprimer-quiz')}
				</Paragraph>

				<ColorSetting
					label={__('Primary Color', 'pressprimer-quiz')}
					help={__('Used for buttons, links, and interactive elements.', 'pressprimer-quiz')}
					value={settings.appearance_primary_color}
					defaultColor={defaultColors.primary}
					onChange={(value) => updateSetting('appearance_primary_color', value)}
				/>

				<ColorSetting
					label={__('Text Color', 'pressprimer-quiz')}
					help={__('Primary text color for quiz content.', 'pressprimer-quiz')}
					value={settings.appearance_text_color}
					defaultColor={defaultColors.text}
					onChange={(value) => updateSetting('appearance_text_color', value)}
				/>

				<ColorSetting
					label={__('Background Color', 'pressprimer-quiz')}
					help={__('Main background color for quiz containers.', 'pressprimer-quiz')}
					value={settings.appearance_background_color}
					defaultColor={defaultColors.background}
					onChange={(value) => updateSetting('appearance_background_color', value)}
				/>

				<ColorSetting
					label={__('Success Color', 'pressprimer-quiz')}
					help={__('Color for correct answers and passing scores.', 'pressprimer-quiz')}
					value={settings.appearance_success_color}
					defaultColor={defaultColors.success}
					onChange={(value) => updateSetting('appearance_success_color', value)}
				/>

				<ColorSetting
					label={__('Error Color', 'pressprimer-quiz')}
					help={__('Color for incorrect answers and failing scores.', 'pressprimer-quiz')}
					value={settings.appearance_error_color}
					defaultColor={defaultColors.error}
					onChange={(value) => updateSetting('appearance_error_color', value)}
				/>
			</div>

			<Divider />

			{/* Layout Section */}
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Layout', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__('Adjust layout properties across all themes.', 'pressprimer-quiz')}
				</Paragraph>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Display Density', 'pressprimer-quiz')}
						help={__('Controls spacing and visual density of quiz interfaces. Condensed mode reduces padding while maintaining accessibility.', 'pressprimer-quiz')}
					>
						<Select
							value={settings.display_density || 'standard'}
							onChange={(value) => updateSetting('display_density', value)}
							style={{ width: 350 }}
							options={[
								{
									value: 'standard',
									label: __('Standard', 'pressprimer-quiz'),
								},
								{
									value: 'condensed',
									label: __('Condensed', 'pressprimer-quiz'),
								},
							]}
						/>
					</Form.Item>
				</div>

				<div className="ppq-settings-field">
					<Form.Item
						label={__('Border Radius', 'pressprimer-quiz')}
						help={__('Roundness of corners. Set to 0 for sharp corners, higher values for more rounded. Leave empty for theme default.', 'pressprimer-quiz')}
					>
						<Space>
							<InputNumber
								min={0}
								max={24}
								value={settings.appearance_border_radius ?? null}
								onChange={(value) => updateSetting('appearance_border_radius', value)}
								addonAfter="px"
								placeholder={__('Default', 'pressprimer-quiz')}
								style={{ width: 150 }}
							/>
							{(settings.appearance_border_radius !== '' && settings.appearance_border_radius !== null && settings.appearance_border_radius !== undefined) && (
								<Button
									type="link"
									icon={<UndoOutlined />}
									onClick={() => updateSetting('appearance_border_radius', null)}
									size="small"
								>
									{__('Reset to Default', 'pressprimer-quiz')}
								</Button>
							)}
						</Space>
					</Form.Item>
				</div>
			</div>
		</div>
	);
};

export default AppearanceTab;
