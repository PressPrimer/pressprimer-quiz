/**
 * Rich Text Editor Component (TinyMCE wrapper)
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Typography, Space, Progress } from 'antd';
import ImageUploadModal from './ImageUploadModal';

const { Text } = Typography;

let editorCounter = 0;

const RichTextEditor = ({ value, onChange, placeholder, maxChars = 2000, rows = 3, questionId = 0 }) => {
	const [charCount, setCharCount] = useState(0);
	const [imageModalOpen, setImageModalOpen] = useState(false);
	const editorRef = useRef(null);
	const editorInstanceRef = useRef(null);
	const [editorId] = useState(() => `ppq-rte-${++editorCounter}`);
	const [isInitialized, setIsInitialized] = useState(false);
	const onChangeRef = useRef(onChange);
	const imageEnabledRef = useRef(questionId > 0);

	// Always keep the ref updated with the latest onChange callback
	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

	// Keep the image-enabled ref in sync — TinyMCE's setup callback closes over
	// the initial value, so we read through the ref to handle late-arriving IDs.
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
						? 'bold,italic,bullist,numlist,link,forecolor,ppq_image,removeformat'
						: 'bold,italic,bullist,numlist,link,forecolor,removeformat';

					// Initialize editor
					window.wp.editor.initialize(editorId, {
						tinymce: {
							wpautop: true,
							plugins: 'lists,link,textcolor,paste',
							toolbar1: toolbar,
							menubar: false,
							height: rows * 40,
							placeholder: placeholder,
							setup: (editor) => {
								// Register the custom image button only when image
								// uploads are enabled for this instance.
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
						quicktags: false,
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
	}, [editorId]);

	// Handle a successful image upload: insert the <img> at the editor caret.
	// The image is centered horizontally via an inline style so both the
	// editor preview and the frontend render the same alignment. The
	// data-ppq-attachment-id attribute lets the refcount cleanup target
	// the exact element on duplicate / delete without URL parsing.
	const handleImageUpload = ({ id, url }) => {
		setImageModalOpen(false);

		const editor = editorInstanceRef.current;
		if (!editor) return;

		const html = `<img src="${url}" alt="" data-ppq-attachment-id="${id}" style="display: block; margin: 0 auto;" />`;
		editor.execCommand('mceInsertContent', false, html);
	};

	const percentUsed = (charCount / maxChars) * 100;
	const strokeColor = percentUsed > 90 ? '#ff4d4f' : '#52c41a';

	return (
		<div>
			<textarea
				ref={editorRef}
				id={editorId}
				className="wp-editor-area"
				style={{ width: '100%' }}
				defaultValue={value || ''}
			/>
			<div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 4 }}>
				<Space size={8} align="center">
					<Text type="secondary" style={{ fontSize: 12 }}>
						{charCount.toLocaleString()} / {maxChars.toLocaleString()}
					</Text>
					<Progress
						type="circle"
						percent={percentUsed}
						strokeColor={strokeColor}
						width={24}
						format={() => ''}
					/>
				</Space>
			</div>
			{questionId > 0 && (
				<ImageUploadModal
					isOpen={imageModalOpen}
					onClose={() => setImageModalOpen(false)}
					onUpload={handleImageUpload}
					questionId={questionId}
				/>
			)}
		</div>
	);
};

export default RichTextEditor;
