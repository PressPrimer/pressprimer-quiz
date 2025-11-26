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

const { Title, Text } = Typography;

const StemEditor = ({ value, onChange }) => {
	const [charCount, setCharCount] = useState(0);
	const editorRef = useRef(null);
	const editorInstanceRef = useRef(null);
	const maxChars = 10000;
	const editorId = 'ppq-stem-editor';
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
							plugins: 'lists,link,textcolor,paste,wordpress,wplink',
							toolbar1: 'formatselect,bold,italic,bullist,numlist,link,forecolor,removeformat',
							height: 300,
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
						quicktags: true,
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
	}, []);

	const percentUsed = (charCount / maxChars) * 100;
	const strokeColor = percentUsed > 90 ? '#ff4d4f' : percentUsed > 75 ? '#faad14' : '#52c41a';

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
		</Card>
	);
};

export default StemEditor;
