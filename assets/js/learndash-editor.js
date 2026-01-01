/**
 * LearnDash Integration - Block Editor Sidebar
 *
 * Adds a sidebar panel to LearnDash content types for selecting a PPQ Quiz.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function(wp) {
	const { registerPlugin } = wp.plugins;
	// Use wp.editor for WP 6.6+, fall back to wp.editPost for older versions
	const { PluginDocumentSettingPanel } = wp.editor || wp.editPost;
	const { useSelect, useDispatch } = wp.data;
	const { useState, useEffect, useCallback, createElement: el, Fragment } = wp.element;
	const {
		TextControl,
		Button,
		Spinner,
		ToggleControl,
		Notice,
	} = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized data
	const config = window.pressprimerQuizLearnDash || {};
	const strings = config.strings || {};

	/**
	 * Quiz Selector Component
	 */
	const QuizSelector = ({ quizId, onSelect }) => {
		const [searchQuery, setSearchQuery] = useState('');
		const [searchResults, setSearchResults] = useState([]);
		const [isSearching, setIsSearching] = useState(false);
		const [selectedQuiz, setSelectedQuiz] = useState(null);
		const [showResults, setShowResults] = useState(false);

		// Format quiz display string
		const formatQuizDisplay = (quiz) => quiz.id + ' - ' + quiz.title;

		// Load quiz title if we have an ID
		useEffect(() => {
			if (quizId && !selectedQuiz) {
				apiFetch({
					path: '/ppq/v1/quizzes/' + quizId,
				}).then(response => {
					if (response && response.title) {
						setSelectedQuiz({ id: quizId, title: response.title });
					}
				}).catch(() => {
					// Quiz might not exist
				});
			}
		}, [quizId]);

		// Load recent quizzes on focus
		const handleFocus = useCallback(() => {
			if (selectedQuiz) return;

			setIsSearching(true);
			apiFetch({
				path: '/ppq/v1/learndash/quizzes/search?recent=1',
			}).then(response => {
				if (response.success && response.quizzes) {
					setSearchResults(response.quizzes);
					setShowResults(true);
				}
			}).catch(() => {
				setSearchResults([]);
			}).finally(() => {
				setIsSearching(false);
			});
		}, [selectedQuiz]);

		// Debounced search - only triggers when user types 2+ characters
		useEffect(() => {
			if (searchQuery.length < 2) {
				return;
			}

			const timeoutId = setTimeout(() => {
				setIsSearching(true);
				apiFetch({
					path: '/ppq/v1/learndash/quizzes/search?search=' + encodeURIComponent(searchQuery),
				}).then(response => {
					if (response.success) {
						setSearchResults(response.quizzes || []);
						setShowResults(true);
					}
				}).catch(() => {
					setSearchResults([]);
				}).finally(() => {
					setIsSearching(false);
				});
			}, 300);

			return () => clearTimeout(timeoutId);
		}, [searchQuery]);

		const handleSelect = useCallback((quiz) => {
			setSelectedQuiz(quiz);
			setSearchQuery('');
			setShowResults(false);
			onSelect(quiz.id);
		}, [onSelect]);

		const handleRemove = useCallback(() => {
			setSelectedQuiz(null);
			onSelect(0);
		}, [onSelect]);

		// Render selected quiz view
		if (selectedQuiz) {
			return el('div', { className: 'ppq-quiz-selector-gutenberg' },
				el('div', { className: 'ppq-selected-quiz-gutenberg' },
					el('span', { className: 'ppq-quiz-name' }, formatQuizDisplay(selectedQuiz)),
					el(Button, {
						isDestructive: true,
						isSmall: true,
						onClick: handleRemove,
						'aria-label': __('Remove quiz', 'pressprimer-quiz')
					}, __('Remove', 'pressprimer-quiz'))
				)
			);
		}

		// Render search view - use native input for better onFocus support
		return el('div', { className: 'ppq-quiz-selector-gutenberg' },
			el('div', { className: 'ppq-quiz-search-gutenberg' },
				el('input', {
					type: 'text',
					className: 'components-text-control__input',
					placeholder: strings.searchPlaceholder || __('Click to browse or type to search...', 'pressprimer-quiz'),
					value: searchQuery,
					onChange: function(e) { setSearchQuery(e.target.value); },
					onFocus: handleFocus,
					autoComplete: 'off'
				}),
				isSearching && el(Spinner, null),
				showResults && searchResults.length > 0 && el('div', { className: 'ppq-search-results-gutenberg' },
					searchResults.map(quiz =>
						el(Button, {
							key: quiz.id,
							className: 'ppq-search-result-item',
							onClick: () => handleSelect(quiz)
						},
							el('span', { className: 'ppq-quiz-id' }, quiz.id),
							' - ' + quiz.title
						)
					)
				),
				showResults && searchResults.length === 0 && !isSearching && el('p', { className: 'ppq-no-results' },
					__('No quizzes found', 'pressprimer-quiz')
				)
			)
		);
	};

	/**
	 * PPQ LearnDash Panel Component
	 */
	const PPQLearnDashPanel = () => {
		const { editPost } = useDispatch('core/editor');

		// Try REST field first (ppq_quiz_id), fall back to meta for compatibility
		const postData = useSelect(select => {
			const editor = select('core/editor');
			return {
				ppqQuizId: editor.getEditedPostAttribute('ppq_quiz_id'),
				ppqRestrict: editor.getEditedPostAttribute('ppq_restrict_until_complete'),
				meta: editor.getEditedPostAttribute('meta') || {},
			};
		});

		// Use REST field if available, otherwise fall back to meta
		const quizId = postData.ppqQuizId !== undefined
			? postData.ppqQuizId
			: (postData.meta[config.metaKeyQuizId] || 0);
		const restrictUntilComplete = postData.ppqRestrict !== undefined
			? postData.ppqRestrict
			: (postData.meta[config.metaKeyRestrict] || '');

		const handleQuizSelect = useCallback((newQuizId) => {
			// Update both REST field and meta for compatibility
			editPost({
				ppq_quiz_id: newQuizId,
				meta: {
					[config.metaKeyQuizId]: newQuizId,
				},
			});
		}, [editPost]);

		const handleRestrictToggle = useCallback((value) => {
			// Update both REST field and meta for compatibility
			editPost({
				ppq_restrict_until_complete: value ? '1' : '',
				meta: {
					[config.metaKeyRestrict]: value ? '1' : '',
				},
			});
		}, [editPost]);

		var children = [
			el('p', null, el('strong', null, strings.selectQuiz || __('Select Quiz', 'pressprimer-quiz'))),
			el(QuizSelector, { quizId: quizId, onSelect: handleQuizSelect })
		];

		// Add course-specific options
		if (config.isCourse) {
			children.push(
				el('div', { style: { marginTop: '16px' } },
					el(ToggleControl, {
						label: strings.restrictLabel || __('Restrict access until all lessons and topics are completed', 'pressprimer-quiz'),
						checked: restrictUntilComplete === '1',
						onChange: handleRestrictToggle
					})
				),
				el('p', { className: 'description', style: { marginTop: '8px', color: '#757575' } },
					strings.restrictHelp || __('When enabled, users must complete all course content before taking the quiz.', 'pressprimer-quiz')
				)
			);
		}

		// Add lesson/topic help text
		if (!config.isCourse && quizId > 0) {
			children.push(
				el('p', { className: 'description', style: { marginTop: '8px', color: '#757575' } },
					strings.quizHelp || __('The quiz will appear at the end of this content. Users must pass to mark it complete.', 'pressprimer-quiz')
				)
			);
		}

		return el(PluginDocumentSettingPanel, {
			name: 'ppq-learndash-panel',
			title: strings.panelTitle || __('PressPrimer Quiz', 'pressprimer-quiz'),
			className: 'ppq-learndash-panel'
		}, children);
	};

	// Only register if we're on a supported post type
	if (config.postType) {
		registerPlugin('ppq-learndash', {
			render: PPQLearnDashPanel,
			icon: 'welcome-learn-more',
		});
	}

	// Add styles
	const style = document.createElement('style');
	style.textContent = `
		.ppq-learndash-panel .ppq-selected-quiz-gutenberg {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			background: #f0f6fc;
			border-radius: 4px;
			margin-top: 8px;
		}
		.ppq-learndash-panel .ppq-quiz-name {
			flex: 1;
			font-weight: 500;
		}
		.ppq-learndash-panel .ppq-quiz-search-gutenberg {
			position: relative;
			margin-top: 8px;
		}
		.ppq-learndash-panel .ppq-search-results-gutenberg {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			background: #fff;
			border: 1px solid #ddd;
			border-top: none;
			max-height: 200px;
			overflow-y: auto;
			z-index: 1000;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.ppq-learndash-panel .ppq-search-result-item {
			display: block;
			width: 100%;
			padding: 8px 12px;
			text-align: left;
			border: none;
			border-bottom: 1px solid #f0f0f0;
			background: none;
			cursor: pointer;
		}
		.ppq-learndash-panel .ppq-search-result-item:hover {
			background: #f0f0f0;
		}
		.ppq-learndash-panel .ppq-search-result-item:last-child {
			border-bottom: none;
		}
		.ppq-learndash-panel .ppq-no-results {
			padding: 12px;
			color: #666;
			font-style: italic;
			margin: 0;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-top: none;
		}
		.ppq-learndash-panel .ppq-quiz-id {
			color: #666;
			font-weight: 600;
		}
		.ppq-learndash-panel .ppq-quiz-search-gutenberg input {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #8c8f94;
			border-radius: 2px;
			font-size: 13px;
			line-height: 1.4;
			min-height: 36px;
			box-sizing: border-box;
		}
		.ppq-learndash-panel .ppq-quiz-search-gutenberg input:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}
	`;
	document.head.appendChild(style);
})(window.wp);
