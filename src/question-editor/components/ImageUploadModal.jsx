/**
 * Image Upload Modal Component
 *
 * Private drag-and-drop upload widget for the Question Editor. Posts a
 * single image file to POST /ppq/v1/questions/:id/upload-image and emits
 * the resulting attachment metadata via onUpload. Deliberately does NOT
 * use wp.media() or expose the WordPress media library.
 *
 * UX is modeled on PressPrimer Assignment's submission.js drag-drop flow,
 * adapted for React and the v2.3 image-support feature.
 *
 * @package PressPrimer_Quiz
 * @since 2.3.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Modal, Alert, Space, Typography, Spin } from 'antd';
import { InboxOutlined } from '@ant-design/icons';

const { Text } = Typography;

const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_FILE_SIZE = 8 * 1024 * 1024;

/**
 * Image Upload Modal
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen     Whether the modal is visible.
 * @param {Function} props.onClose    Called when the user dismisses the modal.
 * @param {Function} props.onUpload   Called with `{ id, url, alt }` after a successful upload.
 * @param {number}   props.questionId The owning question ID (used in the REST path).
 */
const ImageUploadModal = ({ isOpen, onClose, onUpload, questionId }) => {
	const [dragging, setDragging] = useState(false);
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState('');
	const fileInputRef = useRef(null);

	// Reset transient state whenever the modal closes so the next open is fresh.
	useEffect(() => {
		if (!isOpen) {
			setDragging(false);
			setUploading(false);
			setError('');
		}
	}, [isOpen]);

	const validateFile = (file) => {
		if (!file) {
			return __('No file selected.', 'pressprimer-quiz');
		}
		if (!ALLOWED_MIMES.includes(file.type)) {
			return __('Unsupported file type. Allowed: JPG, PNG, GIF, WebP.', 'pressprimer-quiz');
		}
		if (file.size > MAX_FILE_SIZE) {
			return __('File too large. Maximum size: 8 MB.', 'pressprimer-quiz');
		}
		return '';
	};

	const handleFile = async (file) => {
		setError('');
		const validationError = validateFile(file);
		if (validationError) {
			setError(validationError);
			return;
		}

		setUploading(true);
		try {
			const formData = new FormData();
			formData.append('file', file);

			const response = await apiFetch({
				path: `/ppq/v1/questions/${questionId}/upload-image`,
				method: 'POST',
				body: formData,
			});

			if (response && response.id && response.url) {
				onUpload({
					id: response.id,
					url: response.url,
					alt: response.alt || '',
				});
			} else {
				setError(__('Upload succeeded but the server response was incomplete.', 'pressprimer-quiz'));
			}
		} catch (err) {
			setError(err?.message || __('Upload failed. Please try again.', 'pressprimer-quiz'));
		} finally {
			setUploading(false);
		}
	};

	const handleDragOver = (e) => {
		e.preventDefault();
		e.stopPropagation();
		if (!uploading) {
			setDragging(true);
		}
	};

	const handleDragLeave = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setDragging(false);
	};

	const handleDrop = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setDragging(false);
		if (uploading) return;

		const files = e.dataTransfer?.files;
		if (files && files.length > 0) {
			handleFile(files[0]);
		}
	};

	const openFilePicker = () => {
		if (uploading) return;
		fileInputRef.current?.click();
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			openFilePicker();
		}
	};

	const handleFileInputChange = (e) => {
		const files = e.target.files;
		if (files && files.length > 0) {
			handleFile(files[0]);
		}
		// Clear value so selecting the same file again still triggers onChange.
		e.target.value = '';
	};

	const zoneStyle = {
		border: dragging ? '2px solid #1890ff' : '2px dashed #d9d9d9',
		borderRadius: 8,
		padding: '40px 20px',
		textAlign: 'center',
		cursor: uploading ? 'wait' : 'pointer',
		backgroundColor: dragging ? '#e6f7ff' : '#fafafa',
		transition: 'all 0.2s ease',
		outline: 'none',
	};

	return (
		<Modal
			title={__('Upload an Image', 'pressprimer-quiz')}
			open={isOpen}
			onCancel={onClose}
			footer={null}
			width={500}
			destroyOnClose
		>
			<div
				className={`ppq-upload-zone${dragging ? ' ppq-upload-active' : ''}`}
				onDragOver={handleDragOver}
				onDragEnter={handleDragOver}
				onDragLeave={handleDragLeave}
				onDrop={handleDrop}
				onClick={openFilePicker}
				onKeyDown={handleKeyDown}
				tabIndex={uploading ? -1 : 0}
				role="button"
				aria-label={__('Click or drop a file to upload', 'pressprimer-quiz')}
				aria-disabled={uploading ? 'true' : 'false'}
				style={zoneStyle}
			>
				{uploading ? (
					<Space direction="vertical" size="small">
						<Spin size="large" />
						<Text>{__('Uploading…', 'pressprimer-quiz')}</Text>
					</Space>
				) : (
					<Space direction="vertical" size="small">
						<InboxOutlined style={{ fontSize: 48, color: '#1890ff' }} />
						<Text strong>{__('Drag and drop an image here', 'pressprimer-quiz')}</Text>
						<Text type="secondary">{__('or click to browse', 'pressprimer-quiz')}</Text>
						<Text type="secondary" style={{ fontSize: 12 }}>
							{__('JPG, PNG, GIF, or WebP. Maximum 8 MB.', 'pressprimer-quiz')}
						</Text>
					</Space>
				)}
			</div>

			<input
				ref={fileInputRef}
				type="file"
				accept={ALLOWED_MIMES.join(',')}
				style={{ display: 'none' }}
				onChange={handleFileInputChange}
				aria-hidden="true"
				tabIndex={-1}
			/>

			{error && (
				<Alert
					type="error"
					message={error}
					showIcon
					closable
					onClose={() => setError('')}
					style={{ marginTop: 16 }}
				/>
			)}
		</Modal>
	);
};

export default ImageUploadModal;
