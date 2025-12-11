/**
 * Quiz Feedback Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	Button,
	InputNumber,
	Input,
	Space,
	Typography,
	Alert,
	Divider,
	Row,
	Col,
	Tooltip,
	Switch,
} from 'antd';
import {
	PlusOutlined,
	DeleteOutlined,
	WarningOutlined,
	CheckCircleOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Text, Title } = Typography;

/**
 * Feedback Panel Component
 *
 * Configures score-banded feedback messages.
 *
 * @param {Object} props Component props
 * @param {Function} props.onChange Callback when bands change
 * @param {Array} props.value Initial bands array
 */
const FeedbackPanel = ({ onChange, value = [] }) => {
	const [enabled, setEnabled] = useState(value && value.length > 0);
	const [bands, setBands] = useState(value && value.length > 0 ? value : []);
	const [validationErrors, setValidationErrors] = useState([]);
	const [validationWarnings, setValidationWarnings] = useState([]);

	// Initialize from value prop when it changes (e.g., after quiz data loads)
	useEffect(() => {
		if (value && value.length > 0) {
			setEnabled(true);
			setBands(value);
		}
	}, [value]);

	// Validate bands whenever they change (for display purposes only)
	useEffect(() => {
		if (enabled && bands.length > 0) {
			validateBands();
		} else {
			setValidationErrors([]);
			setValidationWarnings([]);
		}
	}, [bands, enabled]);

	/**
	 * Notify parent of changes - call this explicitly from event handlers
	 */
	const notifyParent = (newEnabled, newBands) => {
		if (onChange) {
			onChange(newEnabled ? newBands : []);
		}
	};

	/**
	 * Find the largest gap in coverage
	 */
	const findLargestGap = () => {
		if (bands.length === 0) {
			return { min: 0, max: 100 };
		}

		// Sort bands by min
		const sortedBands = [...bands].sort((a, b) => a.min - b.min);

		// Check for gap before first band
		if (sortedBands[0].min > 0) {
			return { min: 0, max: sortedBands[0].min };
		}

		// Check for gaps between bands
		let largestGap = null;
		let largestGapSize = 0;

		for (let i = 0; i < sortedBands.length - 1; i++) {
			const currentBand = sortedBands[i];
			const nextBand = sortedBands[i + 1];
			const gapStart = currentBand.max + 1;
			const gapEnd = nextBand.min - 1;

			if (gapEnd >= gapStart) {
				const gapSize = gapEnd - gapStart + 1;
				if (gapSize > largestGapSize) {
					largestGapSize = gapSize;
					largestGap = { min: gapStart, max: gapEnd };
				}
			}
		}

		// Check for gap after last band
		const lastBand = sortedBands[sortedBands.length - 1];
		if (lastBand.max < 100) {
			const gapSize = 100 - lastBand.max;
			if (gapSize > largestGapSize) {
				return { min: lastBand.max + 1, max: 100 };
			}
		}

		// If we found a gap in the middle or beginning, return it
		if (largestGap) {
			return largestGap;
		}

		// No gaps found, default to 0-100
		return { min: 0, max: 100 };
	};

	/**
	 * Add new band
	 */
	const handleAddBand = () => {
		const gap = findLargestGap();

		const newBand = {
			id: `band-${Date.now()}`,
			min: gap.min,
			max: gap.max,
			message: '',
		};

		const newBands = [...bands, newBand];
		setBands(newBands);
		notifyParent(enabled, newBands);
	};

	/**
	 * Update band field
	 */
	const handleUpdateBand = (bandId, field, newValue) => {
		const newBands = bands.map(band =>
			band.id === bandId ? { ...band, [field]: newValue } : band
		);
		setBands(newBands);
		notifyParent(enabled, newBands);
	};

	/**
	 * Remove band
	 */
	const handleRemoveBand = (bandId) => {
		if (bands.length === 1) {
			setValidationErrors([__('Must have at least one feedback band.', 'pressprimer-quiz')]);
			return;
		}
		const newBands = bands.filter(band => band.id !== bandId);
		setBands(newBands);
		notifyParent(enabled, newBands);
	};

	/**
	 * Validate bands
	 */
	const validateBands = () => {
		const errors = [];
		const warnings = [];

		// Check each band
		bands.forEach((band, index) => {
			// Min must be < max
			if (band.min >= band.max) {
				errors.push(__(`Band ${index + 1}: Minimum must be less than maximum.`, 'pressprimer-quiz'));
			}

			// Min/max must be 0-100
			if (band.min < 0 || band.min > 100) {
				errors.push(__(`Band ${index + 1}: Minimum must be between 0 and 100.`, 'pressprimer-quiz'));
			}
			if (band.max < 0 || band.max > 100) {
				errors.push(__(`Band ${index + 1}: Maximum must be between 0 and 100.`, 'pressprimer-quiz'));
			}

			// Check for overlaps with other bands
			// Note: Touching boundaries are OK (e.g., 0-79 and 80-100)
			bands.forEach((otherBand, otherIndex) => {
				if (index !== otherIndex) {
					// Overlap occurs when one band's range intrudes into another
					// Touching at boundaries is OK: band ending at 79 and another starting at 80
					const overlap = (
						(band.min < otherBand.max && band.max > otherBand.min) ||
						(band.max > otherBand.min && band.min < otherBand.max)
					);
					if (overlap) {
						errors.push(__(`Band ${index + 1} overlaps with Band ${otherIndex + 1}.`, 'pressprimer-quiz'));
					}
				}
			});
		});

		// Check coverage (warn if gaps)
		const sortedBands = [...bands].sort((a, b) => a.min - b.min);

		// Check if 0 is covered
		const coversZero = sortedBands.some(band => band.min === 0);
		if (!coversZero && bands.length > 0) {
			warnings.push(__('No band starts at 0%. Some scores may not have feedback.', 'pressprimer-quiz'));
		}

		// Check if 100 is covered
		const covers100 = sortedBands.some(band => band.max === 100);
		if (!covers100 && bands.length > 0) {
			warnings.push(__('No band ends at 100%. Some scores may not have feedback.', 'pressprimer-quiz'));
		}

		// Check for gaps between bands
		for (let i = 0; i < sortedBands.length - 1; i++) {
			const currentBand = sortedBands[i];
			const nextBand = sortedBands[i + 1];

			// Gap exists if next band doesn't start immediately after current band ends
			if (currentBand.max + 1 < nextBand.min) {
				warnings.push(__(`Gap between ${currentBand.max}% and ${nextBand.min}%. Scores in this range won't have feedback.`, 'pressprimer-quiz'));
			}
		}

		setValidationErrors([...new Set(errors)]); // Remove duplicates
		setValidationWarnings([...new Set(warnings)]);
	};

	/**
	 * Render band card
	 */
	const renderBandCard = (band, index) => {
		return (
			<Card
				key={band.id}
				size="small"
				style={{ marginBottom: 16 }}
				title={
					<Space>
						<Text strong>{__('Band', 'pressprimer-quiz')} {index + 1}</Text>
						<Text type="secondary" style={{ fontSize: 12 }}>
							({band.min}% - {band.max}%)
						</Text>
					</Space>
				}
				extra={
					bands.length > 1 && (
						<Button
							type="text"
							danger
							size="small"
							icon={<DeleteOutlined />}
							onClick={() => handleRemoveBand(band.id)}
						>
							{__('Remove', 'pressprimer-quiz')}
						</Button>
					)
				}
			>
				<Row gutter={16}>
					<Col span={6}>
						<div>
							<Space>
								<Text strong style={{ fontSize: 12 }}>
									{__('Min %', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
								</Text>
								<Tooltip title={__('Minimum score percentage for this feedback (inclusive). Scores are rounded to nearest whole number.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
							<InputNumber
								min={0}
								max={100}
								value={band.min}
								onChange={(value) => handleUpdateBand(band.id, 'min', value)}
								size="small"
								style={{ width: '100%' }}
								addonAfter="%"
							/>
							<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
								{__('Score >= this value', 'pressprimer-quiz')}
							</Text>
						</div>
					</Col>
					<Col span={6}>
						<div>
							<Space>
								<Text strong style={{ fontSize: 12 }}>
									{__('Max %', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
								</Text>
								<Tooltip title={__('Maximum score percentage for this feedback (inclusive). Scores are rounded to nearest whole number.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
							<InputNumber
								min={0}
								max={100}
								value={band.max}
								onChange={(value) => handleUpdateBand(band.id, 'max', value)}
								size="small"
								style={{ width: '100%' }}
								addonAfter="%"
							/>
							<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
								{__('Score <= this value', 'pressprimer-quiz')}
							</Text>
						</div>
					</Col>
					<Col span={12}>
						<div>
							<Space>
								<Text strong style={{ fontSize: 12 }}>
									{__('Feedback Message', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
								</Text>
								<Tooltip title={__('Message shown to students who score in this range', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
							<TextArea
								rows={3}
								value={band.message}
								onChange={(e) => handleUpdateBand(band.id, 'message', e.target.value)}
								placeholder={__('Enter encouraging feedback for this score range...', 'pressprimer-quiz')}
								size="small"
							/>
							<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
								{__('Be specific and encouraging. Guide students on next steps.', 'pressprimer-quiz')}
							</Text>
						</div>
					</Col>
				</Row>
			</Card>
		);
	};

	return (
		<Card>
			<Space direction="vertical" style={{ width: '100%' }} size="large">
				{/* Header with Enable/Disable Toggle */}
				<div>
					<Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
						<Col>
							<Space direction="vertical" size={4}>
								<Title level={4} style={{ margin: 0 }}>
									{__('Score-Banded Feedback', 'pressprimer-quiz')}
								</Title>
								<Text type="secondary">
									{__('Provide custom feedback messages based on student performance', 'pressprimer-quiz')}
								</Text>
							</Space>
						</Col>
						<Col>
							<Space>
								<Text strong>{__('Enable Feedback Bands:', 'pressprimer-quiz')}</Text>
								<Switch
									checked={enabled}
									onChange={(checked) => {
										setEnabled(checked);
										if (checked && bands.length === 0) {
											// Initialize with default bands when first enabled
											const defaultBands = [
												{
													id: 'band-1',
													min: 0,
													max: 59,
													message: __('Keep practicing! Review the material and try again.', 'pressprimer-quiz'),
												},
												{
													id: 'band-2',
													min: 60,
													max: 79,
													message: __('Good effort! You\'re on the right track.', 'pressprimer-quiz'),
												},
												{
													id: 'band-3',
													min: 80,
													max: 100,
													message: __('Excellent work! You\'ve mastered this material.', 'pressprimer-quiz'),
												},
											];
											setBands(defaultBands);
											notifyParent(checked, defaultBands);
										} else {
											notifyParent(checked, bands);
										}
									}}
								/>
							</Space>
						</Col>
					</Row>

					{/* Info box */}
					{!enabled && (
						<Card type="inner" style={{ backgroundColor: '#f0f5ff', borderColor: '#adc6ff', marginBottom: 16 }}>
							<Space direction="vertical" size="small">
								<Text strong>{__('About Score-Banded Feedback', 'pressprimer-quiz')}</Text>
								<Text style={{ fontSize: 13 }}>
									{__('Score-banded feedback is optional. When enabled, you can provide different feedback messages based on how well students perform. For example, students scoring 0-59% might see "Keep practicing" while those scoring 80-100% see "Excellent work!"', 'pressprimer-quiz')}
								</Text>
								<Text type="secondary" style={{ fontSize: 12 }}>
									{__('Toggle the switch above to enable this feature and configure your feedback bands.', 'pressprimer-quiz')}
								</Text>
							</Space>
						</Card>
					)}

					{enabled && (
						<Card type="inner" style={{ backgroundColor: '#f0f5ff', borderColor: '#adc6ff', marginBottom: 16 }}>
							<Space direction="vertical" size="small">
								<Text strong>{__('How Score Bands Work', 'pressprimer-quiz')}</Text>
								<Text style={{ fontSize: 13 }}>
									{__('Each band defines a score range (min to max) and a custom message. Both min and max are inclusive, so a score of exactly 80% would match a band of 80-100%.', 'pressprimer-quiz')}
								</Text>
								<Text style={{ fontSize: 13 }}>
									<strong>{__('Important:', 'pressprimer-quiz')}</strong> {__('Student scores are rounded to the nearest whole number before matching bands. For example, a score of 79.9% rounds to 80% and matches the 80-100% band, while 79.4% rounds to 79% and matches the 60-79% band.', 'pressprimer-quiz')}
								</Text>
								<Text type="secondary" style={{ fontSize: 12 }}>
									{__('Tip: Bands can touch at boundaries (e.g., 0-79 and 80-100) but should not overlap. When you add a new band, the system will automatically suggest the largest gap.', 'pressprimer-quiz')}
								</Text>
							</Space>
						</Card>
					)}
				</div>

				{enabled && (
					<>
						{/* Validation Errors */}
						{validationErrors.length > 0 && (
							<Alert
								message={__('Validation Errors', 'pressprimer-quiz')}
								description={
									<ul style={{ marginBottom: 0, paddingLeft: 20 }}>
										{validationErrors.map((error, index) => (
											<li key={index}>{error}</li>
										))}
									</ul>
								}
								type="error"
								icon={<WarningOutlined />}
								showIcon
							/>
						)}

						{/* Validation Warnings */}
						{validationWarnings.length > 0 && validationErrors.length === 0 && (
							<Alert
								message={__('Recommendations', 'pressprimer-quiz')}
								description={
									<ul style={{ marginBottom: 0, paddingLeft: 20 }}>
										{validationWarnings.map((warning, index) => (
											<li key={index}>{warning}</li>
										))}
									</ul>
								}
								type="warning"
								showIcon
							/>
						)}

						{/* Success message */}
						{validationErrors.length === 0 && validationWarnings.length === 0 && bands.length > 0 && (
							<Alert
								message={__('Feedback bands configured correctly', 'pressprimer-quiz')}
								description={__('All score ranges are covered without overlaps.', 'pressprimer-quiz')}
								type="success"
								icon={<CheckCircleOutlined />}
								showIcon
							/>
						)}

						<Divider />

						{/* Bands List */}
						<div>
							<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
								<Text strong>
									{__('Feedback Bands:', 'pressprimer-quiz')} {bands.length}
								</Text>
								{bands.length < 10 && (
									<Button
										type="primary"
										icon={<PlusOutlined />}
										onClick={handleAddBand}
										size="small"
									>
										{__('Add Band', 'pressprimer-quiz')}
									</Button>
								)}
							</div>

							{bands
								.sort((a, b) => a.min - b.min)
								.map((band, index) => renderBandCard(band, index))}
						</div>

						{/* Add band button at bottom */}
						{bands.length < 10 && (
							<Button
								type="dashed"
								block
								icon={<PlusOutlined />}
								onClick={handleAddBand}
							>
								{__('Add Another Band', 'pressprimer-quiz')}
							</Button>
						)}

						{bands.length >= 10 && (
							<Alert
								message={__('Maximum bands reached', 'pressprimer-quiz')}
								description={__('You can have up to 10 feedback bands. Remove a band to add a new one.', 'pressprimer-quiz')}
								type="info"
								showIcon
							/>
						)}
					</>
				)}
			</Space>
		</Card>
	);
};

export default FeedbackPanel;
