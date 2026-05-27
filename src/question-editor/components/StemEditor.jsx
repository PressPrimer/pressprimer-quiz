/**
 * Question Stem Editor Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, Typography, Tooltip, Space, Progress } from 'antd';
import { QuestionCircleOutlined } from '@ant-design/icons';
import ImageUploadModal from './ImageUploadModal';

const { Title, Text } = Typography;

const StemEditor = ({ value, onChange, questionId = 0 }) => {
	const [charCount, setCharCount] = useState(0);
	const [imageModalOpen, setImageModalOpen] = useState(false);
	const editorRef = useRef(null);
	const editorInstanceRef = useRef(null);
	const maxChars = 10000;
	const editorId = 'ppq-stem-editor';
	const [isInitialized, setIsInitialized] = useState(false);
	const onChangeRef = useRef(onChange);
	const imageEnabledRef = useRef(questionId > 0);

	// Always keep the ref updated with the latest onChange callback
	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	// TinyMCE's setup callback closes over the initial questionId value, so
	// route the toggle through a ref to handle the case where the question
	// is saved (gets an ID) after the editor mounts.
	useEffect(() => {
		imageEnabledRef.current = questionId > 0;
	}, [questionId]);

	// Initialize TinyMCE when component mounts
	useEffect(() => {
		let timeoutId;

		const initEditor = () => {
			if (isInitialized || !editorRef.current) return;

			if (window.wp && window.wp.editor) {
				try {
					// Remove any existing editor with this ID
					if (window.wp.editor.getDefaultSettings) {
						window.wp.editor.remove(editorId);
					}

					// Toolbar: add the custom image button only when this editor
					// is wired to a known question (we need the ID for the upload
					// REST path). Falls back to the v2.2 toolbar otherwise.
					const toolbar = imageEnabledRef.current
						? 'formatselect,bold,italic,bullist,numlist,link,forecolor,removeformat,ppq_image'
						: 'formatselect,bold,italic,bullist,numlist,link,forecolor,removeformat';

					// Initialize editor
					window.wp.editor.initialize(editorId, {
						tinymce: {
							wpautop: true,
							plugins: 'lists,link,textcolor,paste,wordpress,wplink',
							toolbar1: toolbar,
							height: 300,
							setup: (editor) => {
								if (imageEnabledRef.current) {
									editor.addButton('ppq_image', {
										icon: 'image',
										tooltip: __('Insert image', 'pressprimer-quiz'),
										onclick: () => {
											setImageModalOpen(true);
										},
									});
								}
							},
							init_instance_callback: (editor) => {
								editorInstanceRef.current = editor;
								setIsInitialized(true);

								// Track character count
								const updateCount = () => {
									const content = editor.getContent({ format: 'text' });
									setCharCount(content.length);
								};

								// Set initial content and update count
								if (value) {
									editor.setContent(value);
								}
								// Update count after content is set
								updateCount();

								editor.on('input change keyup', () => {
									updateCount();
									// Always call the latest onChange using the ref
									onChangeRef.current(editor.getContent());
								});
							},
						},
						quicktags: true,
						mediaButtons: false,
					});
				} catch (error) {
					// Editor initialization failed - will retry
				}
			} else {
				// Retry if wp.editor isn't ready yet
				timeoutId = setTimeout(initEditor, 100);
			}
		};

		// Small delay to ensure DOM is ready
		timeoutId = setTimeout(initEditor, 100);

		return () => {
			clearTimeout(timeoutId);
			if (editorInstanceRef.current) {
				try {
					window.wp.editor.remove(editorId);
				} catch (e) {
					// Ignore cleanup errors
				}
				editorInstanceRef.current = null;
			}
			setIsInitialized(false);
		};
	}, []);

	// Update editor content when value prop changes
	useEffect(() => {
		if (isInitialized && editorInstanceRef.current && value !== undefined) {
			const currentContent = editorInstanceRef.current.getContent();
			// Only update if the content is different to avoid cursor jumps
			if (currentContent !== value) {
				editorInstanceRef.current.setContent(value || '');
			}
		}
	}, [value, isInitialized]);

	// Handle a successful image upload: insert the <img> at the editor caret,
	// then ask the author for alt text. The data-ppq-attachment-id attribute
	// lets the alt-text update target the exact element without URL grepping.
	const handleImageUpload = ({ id, url }) => {
		setImageModalOpen(false);

		const editor = editorInstanceRef.current;
		if (!editor) return;

		const html = `<img src="${url}" alt="" data-ppq-attachment-id="${id}" />`;
		editor.execCommand('mceInsertContent', false, html);

		// Prompt for alt text. Lightweight native prompt is intentional — the
		// v2.3 spec treats this as a follow-up nudge rather than a hard block,
		// and a second Ant Modal would feel heavy on top of the upload one.
		// eslint-disable-next-line no-alert
		const altText = window.prompt(
			__('Describe this image for accessibility (optional)', 'pressprimer-quiz'),
			''
		);
		if (altText) {
			const inserted = editor.dom.select(`img[data-ppq-attachment-id="${id}"]`)[0];
			if (inserted) {
				editor.dom.setAttrib(inserted, 'alt', altText);
				onChangeRef.current(editor.getContent());
			}
		}
	};

	const percentUsed = (charCount / maxChars) * 100;
	const getStrokeColor = () => {
		if (percentUsed > 90) {
			return '#ff4d4f';
		}
		if (percentUsed > 75) {
			return '#faad14';
		}
		return '#52c41a';
	};
	const strokeColor = getStrokeColor();

	return (
		<Card
			title={
				<Space>
					<Title level={4} style={{ margin: 0 }}>
						{__('Question Text', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
					</Title>
					<Tooltip title={__('Enter the main question text. You can use the formatting tools to add emphasis, lists, links, or images.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			}
			extra={
				<Space>
					<Text type="secondary" style={{ fontSize: 13 }}>
						{charCount.toLocaleString()} / {maxChars.toLocaleString()}
					</Text>
					<Progress
						type="circle"
						percent={percentUsed}
						strokeColor={strokeColor}
						width={40}
						format={() => ''}
					/>
				</Space>
			}
			style={{ marginBottom: 24 }}
		>
			<div>
				<textarea
					ref={editorRef}
					id={editorId}
					name="stem"
					className="wp-editor-area"
					style={{ width: '100%', minHeight: 300 }}
					defaultValue={value || ''}
				/>
			</div>
			{questionId > 0 && (
				<ImageUploadModal
					isOpen={imageModalOpen}
					onClose={() => setImageModalOpen(false)}
					onUpload={handleImageUpload}
					questionId={questionId}
				/>
			)}
		</Card>
	);
};

export default StemEditor;
