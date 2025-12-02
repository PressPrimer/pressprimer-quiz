/**
 * TutorLMS Integration - Block Editor Sidebar
 *
 * Adds a sidebar panel to TutorLMS lesson post type for selecting a PPQ Quiz.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function(wp) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect, useDispatch } = wp.data;
	const { useState, useEffect, useCallback, createElement: el } = wp.element;
	const {
		Button,
		Spinner,
		ToggleControl,
	} = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized data.
	const config = window.ppqTutorLMS || {};
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

		// Format quiz display string.
		const formatQuizDisplay = (quiz) => quiz.id + ' - ' + quiz.title;

		// Load quiz title if we have an ID.
		useEffect(() => {
			if (quizId && !selectedQuiz) {
				apiFetch({
					path: '/ppq/v1/quizzes/' + quizId,
				}).then(response => {
					if (response && response.title) {
						setSelectedQuiz({ id: quizId, title: response.title });
					}
				}).catch(() => {
					// Quiz might not exist.
				});
			}
		}, [quizId]);

		// Load recent quizzes on focus.
		const handleFocus = useCallback(() => {
			if (selectedQuiz) return;

			setIsSearching(true);
			apiFetch({
				path: '/ppq/v1/tutorlms/quizzes/search?recent=1',
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

		// Debounced search.
		useEffect(() => {
			if (searchQuery.length < 2) {
				return;
			}

			const timeoutId = setTimeout(() => {
				setIsSearching(true);
				apiFetch({
					path: '/ppq/v1/tutorlms/quizzes/search?search=' + encodeURIComponent(searchQuery),
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

		// Render selected quiz view.
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

		// Render search view.
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
	 * PPQ TutorLMS Panel Component
	 */
	const PPQTutorLMSPanel = () => {
		const { editPost } = useDispatch('core/editor');
		const meta = useSelect(select => select('core/editor').getEditedPostAttribute('meta') || {});

		const quizId = meta[config.metaKeyQuizId] || 0;
		const requirePass = meta[config.metaKeyRequirePass] || '';

		const handleQuizSelect = useCallback((newQuizId) => {
			editPost({
				meta: {
					[config.metaKeyQuizId]: newQuizId,
				},
			});
		}, [editPost]);

		const handleRequirePassToggle = useCallback((value) => {
			editPost({
				meta: {
					[config.metaKeyRequirePass]: value ? '1' : '',
				},
			});
		}, [editPost]);

		var children = [
			el('p', { key: 'label' }, el('strong', null, strings.selectQuiz || __('Select Quiz', 'pressprimer-quiz'))),
			el(QuizSelector, { key: 'selector', quizId: quizId, onSelect: handleQuizSelect })
		];

		// Add require pass toggle.
		children.push(
			el('div', { key: 'toggle', style: { marginTop: '16px' } },
				el(ToggleControl, {
					label: strings.requirePassLabel || __('Require passing score to complete lesson', 'pressprimer-quiz'),
					checked: requirePass === '1',
					onChange: handleRequirePassToggle
				})
			),
			el('p', { key: 'help', className: 'description', style: { marginTop: '8px', color: '#757575' } },
				strings.requirePassHelp || __('When enabled, students must pass this quiz to mark the lesson complete.', 'pressprimer-quiz')
			)
		);

		return el(PluginDocumentSettingPanel, {
			name: 'ppq-tutorlms-panel',
			title: strings.panelTitle || __('PressPrimer Quiz', 'pressprimer-quiz'),
			className: 'ppq-tutorlms-panel'
		}, children);
	};

	// Only register if we're on a supported post type.
	if (config.postType) {
		registerPlugin('ppq-tutorlms', {
			render: PPQTutorLMSPanel,
			icon: 'welcome-learn-more',
		});
	}

	// Add styles.
	const style = document.createElement('style');
	style.textContent = `
		.ppq-tutorlms-panel .ppq-selected-quiz-gutenberg {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			background: #f0f6fc;
			border-radius: 4px;
			margin-top: 8px;
		}
		.ppq-tutorlms-panel .ppq-quiz-name {
			flex: 1;
			font-weight: 500;
		}
		.ppq-tutorlms-panel .ppq-quiz-search-gutenberg {
			position: relative;
			margin-top: 8px;
		}
		.ppq-tutorlms-panel .ppq-search-results-gutenberg {
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
		.ppq-tutorlms-panel .ppq-search-result-item {
			display: block;
			width: 100%;
			padding: 8px 12px;
			text-align: left;
			border: none;
			border-bottom: 1px solid #f0f0f0;
			background: none;
			cursor: pointer;
		}
		.ppq-tutorlms-panel .ppq-search-result-item:hover {
			background: #f0f0f0;
		}
		.ppq-tutorlms-panel .ppq-search-result-item:last-child {
			border-bottom: none;
		}
		.ppq-tutorlms-panel .ppq-no-results {
			padding: 12px;
			color: #666;
			font-style: italic;
			margin: 0;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-top: none;
		}
		.ppq-tutorlms-panel .ppq-quiz-id {
			color: #666;
			font-weight: 600;
		}
		.ppq-tutorlms-panel .ppq-quiz-search-gutenberg input {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #8c8f94;
			border-radius: 2px;
			font-size: 13px;
			line-height: 1.4;
			min-height: 36px;
			box-sizing: border-box;
		}
		.ppq-tutorlms-panel .ppq-quiz-search-gutenberg input:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}
	`;
	document.head.appendChild(style);
})(window.wp);
