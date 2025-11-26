/**
 * Rich Text Editor Component (TinyMCE wrapper)
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Typography, Space, Progress } from 'antd';

const { Text } = Typography;

let editorCounter = 0;

const RichTextEditor = ({ value, onChange, placeholder, maxChars = 2000, rows = 3 }) => {
	const [charCount, setCharCount] = useState(0);
	const editorRef = useRef(null);
	const editorInstanceRef = useRef(null);
	const [editorId] = useState(() => `ppq-rte-${++editorCounter}`);
	const [isInitialized, setIsInitialized] = useState(false);
	const onChangeRef = useRef(onChange);

	// Always keep the ref updated with the latest onChange callback
	useEffect(() => {
		onChangeRef.current = onChange;
	}, [onChange]);

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

					// Initialize editor
					window.wp.editor.initialize(editorId, {
						tinymce: {
							wpautop: true,
							plugins: 'lists,link,textcolor,paste',
							toolbar1: 'bold,italic,bullist,numlist,link,forecolor,removeformat',
							menubar: false,
							height: rows * 40,
							placeholder: placeholder,
							init_instance_callback: (editor) => {
								editorInstanceRef.current = editor;
								setIsInitialized(true);

								// Set initial content
								if (value) {
									editor.setContent(value);
								}

								// Track character count
								const updateCount = () => {
									const content = editor.getContent({ format: 'text' });
									setCharCount(content.length);
								};

								editor.on('init', updateCount);
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
					console.error('Failed to initialize editor:', error);
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
		</div>
	);
};

export default RichTextEditor;
