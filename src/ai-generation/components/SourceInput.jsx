/**
 * Source Input
 *
 * Where the content comes from: pasted text, or the text of an uploaded PDF
 * or Word file (extracted on the server).
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { Alert, Button, Input, Spin, Tabs, Typography, Upload } from 'antd';
import { DeleteOutlined, EditOutlined, FileTextOutlined, InboxOutlined, UploadOutlined } from '@ant-design/icons';
import { FILE_ACCEPT, formatFileSize } from '../utils';

const { TextArea } = Input;
const { Text } = Typography;
const { Dragger } = Upload;

// How much of an extracted file the preview shows.
const PREVIEW_LENGTH = 1000;

/**
 * Source Input
 *
 * @param {Object}      props               Component props.
 * @param {string}      props.tab           'text' or 'file'.
 * @param {Function}    props.onTabChange   Called with the new tab.
 * @param {string}      props.text          Pasted text.
 * @param {Function}    props.onTextChange  Called with the new text.
 * @param {number}      props.maxLength     Characters generation reads.
 * @param {number}      props.maxFileSize   Largest upload, in bytes.
 * @param {Object|null} props.file          The chosen file ({ name, size }).
 * @param {Object|null} props.extracted     Its text ({ text, wasTruncated }).
 * @param {boolean}     props.extracting    Whether extraction is running.
 * @param {Function}    props.onFile        Called with a chosen File.
 * @param {Function}    props.onRemoveFile  Called to clear the file.
 * @return {JSX.Element} The source tabs.
 */
const SourceInput = ({ tab, onTabChange, text, onTextChange, maxLength, maxFileSize, file, extracted, extracting, onFile, onRemoveFile }) => {
	const over = text.length > maxLength;

	const textPane = (
		<div className="ppq-ai-source">
			<label htmlFor="ppq-ai-content" className="ppq-ai-label">
				<Text strong>{__('Source Content', 'pressprimer-quiz')}</Text>
				<Text type="secondary" className="ppq-ai-hint">
					{__('Paste the text you want to generate questions from.', 'pressprimer-quiz')}
				</Text>
			</label>
			<TextArea
				id="ppq-ai-content"
				rows={10}
				value={text}
				onChange={(event) => onTextChange(event.target.value)}
				placeholder={__('Paste your educational content here…', 'pressprimer-quiz')}
			/>
			<Text type={over ? 'danger' : 'secondary'} className="ppq-ai-char-count">
				{sprintf(
					/* translators: 1: characters entered, 2: most characters read. */
					__('%1$s / %2$s characters', 'pressprimer-quiz'),
					text.length.toLocaleString(),
					maxLength.toLocaleString()
				)}
			</Text>
		</div>
	);

	const filePane = (
		<div className="ppq-ai-source">
			<div className="ppq-ai-label">
				<Text strong>{__('Upload Document', 'pressprimer-quiz')}</Text>
				<Text type="secondary" className="ppq-ai-hint">
					{sprintf(
						/* translators: %s: largest file size (e.g. 50 MB). */
						__('Supported formats: PDF, DOCX. Maximum size: %s', 'pressprimer-quiz'),
						formatFileSize(maxFileSize)
					)}
				</Text>
			</div>

			{!file && (
				<Dragger
					accept={FILE_ACCEPT}
					multiple={false}
					showUploadList={false}
					fileList={[]}
					beforeUpload={(chosen) => {
						onFile(chosen);
						return false;
					}}
				>
					<p className="ant-upload-drag-icon"><InboxOutlined /></p>
					<p className="ant-upload-text">
						{__('Drag and drop a file here, or click to select', 'pressprimer-quiz')}
					</p>
				</Dragger>
			)}

			{file && (
				<Spin spinning={extracting} tip={__('Processing file…', 'pressprimer-quiz')}>
					<div className="ppq-ai-file">
						<FileTextOutlined className="ppq-ai-file-icon" />
						<Text strong className="ppq-ai-file-name">{file.name}</Text>
						<Text type="secondary">{formatFileSize(file.size)}</Text>
						<Button
							type="text"
							size="small"
							icon={<DeleteOutlined />}
							onClick={onRemoveFile}
							disabled={extracting}
							aria-label={__('Remove file', 'pressprimer-quiz')}
						/>
					</div>

					{extracted && (
						<div className="ppq-ai-extracted">
							<Text strong>{__('Extracted Text Preview', 'pressprimer-quiz')}</Text>
							<div className="ppq-ai-extracted-text">
								{extracted.text.length > PREVIEW_LENGTH
									? extracted.text.substring(0, PREVIEW_LENGTH) + '…'
									: extracted.text}
							</div>
							{extracted.wasTruncated && (
								<Alert
									type="info"
									showIcon
									message={sprintf(
										/* translators: %s: most characters read (e.g. 250,000). */
										__('This file has more text than generation reads. Only its first %s characters are used.', 'pressprimer-quiz'),
										maxLength.toLocaleString()
									)}
								/>
							)}
						</div>
					)}
				</Spin>
			)}
		</div>
	);

	return (
		<Tabs
			type="card"
			activeKey={tab}
			onChange={onTabChange}
			className="ppq-ai-source-tabs"
			items={[
				{ key: 'text', label: <span><EditOutlined /> {__('Paste Text', 'pressprimer-quiz')}</span>, children: textPane },
				{ key: 'file', label: <span><UploadOutlined /> {__('Upload File', 'pressprimer-quiz')}</span>, children: filePane },
			]}
		/>
	);
};

export default SourceInput;
