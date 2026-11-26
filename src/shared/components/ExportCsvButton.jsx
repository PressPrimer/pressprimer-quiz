/**
 * Export CSV Button
 *
 * Fetches a CSV export endpoint ({ filename, content, type }) and downloads
 * the result as a Blob. The suite's export architecture (Educator's
 * ReportExportButton, School's item-analysis export); free's reports use it,
 * and the dashboard can too.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, message } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';

/**
 * Export CSV Button
 *
 * A failed export shows the server's message (e.g. too many rows) as an error
 * toast.
 *
 * @param {Object}  props           Component props.
 * @param {string}  props.path      Relative REST path of the export, with its
 *                                  query (e.g. '/ppq/v1/statistics/attempts/export?quiz_id=5').
 * @param {boolean} props.disabled  Whether the button is disabled.
 * @param {string}  props.className Extra class name.
 * @return {JSX.Element} The button.
 */
const ExportCsvButton = ({ path, disabled = false, className }) => {
	const [exporting, setExporting] = useState(false);

	const handleExport = async () => {
		setExporting(true);

		try {
			const result = await apiFetch({ path });

			if (!result || 'string' !== typeof result.content) {
				throw new Error(__('The export returned no file.', 'pressprimer-quiz'));
			}

			const blob = new window.Blob([result.content], {
				type: result.type || 'text/csv;charset=utf-8',
			});
			const url = window.URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = result.filename || 'export.csv';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			window.URL.revokeObjectURL(url);

			message.success(__('Export complete.', 'pressprimer-quiz'));
		} catch (error) {
			message.error(error?.message || __('The export failed. Please try again.', 'pressprimer-quiz'));
		} finally {
			setExporting(false);
		}
	};

	return (
		<Button
			icon={<DownloadOutlined />}
			onClick={handleExport}
			loading={exporting}
			disabled={disabled}
			className={className}
		>
			{__('Export CSV', 'pressprimer-quiz')}
		</Button>
	);
};

export default ExportCsvButton;
