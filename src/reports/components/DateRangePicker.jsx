/**
 * Date Range Picker Component
 *
 * Allows selecting a date range for reports filtering.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Radio, DatePicker, Space } from 'antd';
import { CalendarOutlined } from '@ant-design/icons';

const { RangePicker } = DatePicker;

/**
 * Date Range Picker Component
 *
 * @param {Object} props Component props
 * @param {string} props.value Current preset value
 * @param {Object} props.customDates Custom date range
 * @param {Function} props.onChange Change handler
 */
const DateRangePicker = ({ value, customDates, onChange }) => {
	const [showCustom, setShowCustom] = useState(value === 'custom');

	const presets = [
		{ value: '7days', label: __('Last 7 days', 'pressprimer-quiz') },
		{ value: '30days', label: __('Last 30 days', 'pressprimer-quiz') },
		{ value: '90days', label: __('Last 90 days', 'pressprimer-quiz') },
		{ value: 'all', label: __('All time', 'pressprimer-quiz') },
		{ value: 'custom', label: __('Custom', 'pressprimer-quiz') },
	];

	const handlePresetChange = (e) => {
		const newValue = e.target.value;
		setShowCustom(newValue === 'custom');

		if (newValue !== 'custom') {
			onChange(newValue);
		}
	};

	const handleCustomChange = (dates) => {
		if (dates && dates[0] && dates[1]) {
			onChange('custom', {
				from: dates[0].format('YYYY-MM-DD'),
				to: dates[1].format('YYYY-MM-DD'),
			});
		}
	};

	return (
		<div className="ppq-date-range-picker">
			<div className="ppq-date-range-label">
				<CalendarOutlined style={{ marginRight: 8 }} />
				{__('Date Range:', 'pressprimer-quiz')}
			</div>
			<Space size="middle" wrap>
				<Radio.Group
					value={value}
					onChange={handlePresetChange}
					optionType="button"
					buttonStyle="solid"
				>
					{presets.map((preset) => (
						<Radio.Button key={preset.value} value={preset.value}>
							{preset.label}
						</Radio.Button>
					))}
				</Radio.Group>

				{showCustom && (
					<RangePicker
						onChange={handleCustomChange}
						format="YYYY-MM-DD"
						allowClear={false}
					/>
				)}
			</Space>
		</div>
	);
};

export default DateRangePicker;
