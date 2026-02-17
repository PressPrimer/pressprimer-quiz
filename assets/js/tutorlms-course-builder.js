/**
 * TutorLMS Course Builder Integration - Lesson Editor
 *
 * Adds a PressPrimer Quiz selector to the lesson editor modal sidebar.
 * Uses Tutor LMS's CourseBuilder slot/fill API (v3.3.0+) to inject into the
 * "bottom_of_sidebar" slot of the lesson editor modal.
 *
 * Quizzes are linked at the lesson level, not topic level.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function() {
	'use strict';

	// Get configuration from localized data.
	var config = window.pressprimerQuizTutorCourseBuilder || {};
	var strings = config.strings || {};
	var courseId = config.courseId || 0;

	// State for lesson quiz associations.
	var lessonQuizzes = config.lessonQuizzes || {};

	// Track the currently editing lesson ID (set when user clicks edit on a lesson).
	var currentEditingLessonId = null;

	/**
	 * Initialize the integration
	 */
	function init() {
		// Inject styles.
		injectStyles();

		// Set up click tracking to capture lesson IDs before modals open.
		setupLessonClickTracking();

		// Register our component via Tutor's slot/fill API.
		registerSlot();
	}

	/**
	 * Register our quiz selector in Tutor's lesson editor sidebar slot.
	 *
	 * Tutor LMS v3.3.0+ exposes window.Tutor.CourseBuilder with registerContent().
	 * The "component" property must be a React element (JSX), not a component function.
	 * It is rendered inside an error boundary with no props passed through.
	 */
	function registerSlot() {
		// Wait for Tutor's API to be available. The course builder React app
		// initializes asynchronously, so we poll until ready.
		var attempts = 0;
		var maxAttempts = 50; // 5 seconds max.

		var interval = setInterval(function() {
			attempts++;

			if (
				window.Tutor &&
				window.Tutor.CourseBuilder &&
				window.Tutor.CourseBuilder.Curriculum &&
				window.Tutor.CourseBuilder.Curriculum.Lesson &&
				typeof window.Tutor.CourseBuilder.Curriculum.Lesson.registerContent === 'function'
			) {
				clearInterval(interval);

				// Create a container div. When Tutor renders it as a React element,
				// we will detect the mount via MutationObserver and build our UI inside.
				var container = document.createElement('div');
				container.className = 'ppq-slot-mount';

				// Use React.createElement to wrap our container in a React element.
				// Tutor's slot system expects a React element for the "component" field.
				var React = window.React || (window.wp && window.wp.element);
				if (!React || !React.createElement) {
					return;
				}

				var element = React.createElement(PPQSlotComponent, null);

				window.Tutor.CourseBuilder.Curriculum.Lesson.registerContent(
					'bottom_of_sidebar',
					{
						name: 'pressprimer_quiz_selector',
						priority: 20,
						component: element,
					}
				);
			} else if (attempts >= maxAttempts) {
				clearInterval(interval);
			}
		}, 100);
	}

	/**
	 * Get the lesson ID by walking up the React fiber tree from a DOM element.
	 *
	 * Our component is rendered inside Tutor's LessonModal via the slot/fill
	 * system. The LessonModal receives `lessonId` as a prop. By walking up the
	 * fiber tree from our own DOM element, we can find this prop reliably.
	 *
	 * @param {HTMLElement} el DOM element inside the modal.
	 * @return {string|null} Lesson ID or null.
	 */
	function getLessonIdFromOwnFiber(el) {
		if (!el) {
			return null;
		}

		// Find the React fiber key on our DOM element.
		var keys = Object.keys(el);
		var fiberKey = null;
		for (var i = 0; i < keys.length; i++) {
			if (keys[i].startsWith('__reactFiber')) {
				fiberKey = keys[i];
				break;
			}
		}

		if (!fiberKey) {
			return null;
		}

		// Walk up the fiber tree looking for a component with lessonId prop.
		var fiber = el[fiberKey];
		var maxDepth = 30; // LessonModal is several levels up.

		while (fiber && maxDepth > 0) {
			try {
				var props = fiber.memoizedProps || fiber.pendingProps;
				if (props) {
					// The LessonModal component receives lessonId as a direct prop.
					if (props.lessonId && parseInt(props.lessonId) > 0) {
						return String(props.lessonId);
					}
				}
			} catch (err) {
				// Ignore errors accessing React internals.
			}
			fiber = fiber.return;
			maxDepth--;
		}

		return null;
	}

	/**
	 * React component for the slot.
	 *
	 * Renders a mount point and populates it with vanilla DOM once mounted.
	 * This approach avoids depending on Tutor's React internals while working
	 * within their slot/fill system.
	 */
	function PPQSlotComponent() {
		var React = window.React || (window.wp && window.wp.element);
		var ref = React.useRef(null);
		var lastBuiltLessonId = React.useRef(null);

		// This effect runs on EVERY render. Tutor re-renders the modal (and our
		// slot component) each time a lesson modal opens. We detect the lesson ID
		// from the fiber tree and rebuild the quiz box if it changed.
		React.useEffect(function() {
			if (!ref.current) {
				return;
			}

			// Primary: get lesson ID from fiber tree (most reliable).
			var lessonId = getLessonIdFromOwnFiber(ref.current);

			// Fallback: use click tracking.
			if (!lessonId) {
				lessonId = currentEditingLessonId;
			}

			// Update the global so other functions can use it.
			if (lessonId) {
				currentEditingLessonId = lessonId;
			}

			// Only rebuild if the lesson ID changed (or first mount).
			if (lessonId !== lastBuiltLessonId.current) {
				lastBuiltLessonId.current = lessonId;
				buildQuizBox(ref.current);
			} else if (!ref.current.hasChildNodes()) {
				// First mount — no children yet.
				buildQuizBox(ref.current);
			}
		});

		// Also poll for lesson ID changes as a safety net. The fiber tree may
		// not be ready on the very first render, so we retry a few times.
		React.useEffect(function() {
			var attempts = 0;
			var maxAttempts = 10; // 5 seconds.

			var checkInterval = setInterval(function() {
				attempts++;
				if (!ref.current) {
					return;
				}

				var lessonId = getLessonIdFromOwnFiber(ref.current);
				if (lessonId && lessonId !== lastBuiltLessonId.current) {
					currentEditingLessonId = lessonId;
					lastBuiltLessonId.current = lessonId;
					buildQuizBox(ref.current);
					clearInterval(checkInterval);
				}

				if (attempts >= maxAttempts) {
					clearInterval(checkInterval);
				}
			}, 500);

			return function() {
				clearInterval(checkInterval);
			};
		}, []);

		return React.createElement('div', {
			ref: ref,
			className: 'ppq-lesson-quiz-slot',
		});
	}

	/**
	 * Build the quiz selector box inside the given container element.
	 *
	 * @param {HTMLElement} container The mount point element.
	 */
	function buildQuizBox(container) {
		// Clear previous content.
		container.innerHTML = '';

		// Primary: get lesson ID from fiber tree (most reliable).
		var lessonId = getLessonIdFromOwnFiber(container);

		// Fallback: use the global from click tracking or modal inspection.
		if (!lessonId) {
			lessonId = currentEditingLessonId || getLessonIdFromModal();
		}

		// Update the global.
		if (lessonId) {
			currentEditingLessonId = lessonId;
		}

		var isValidLessonId = lessonId && !lessonId.toString().startsWith('temp-');

		renderQuizBoxContent(container, lessonId, isValidLessonId);
	}

	/**
	 * Render the quiz box content after lesson ID resolution.
	 *
	 * @param {HTMLElement} container The mount point element.
	 * @param {string|null} lessonId  Lesson ID.
	 * @param {boolean}     isValidLessonId Whether lessonId is a real ID.
	 */
	function renderQuizBoxContent(container, lessonId, isValidLessonId) {
		container.innerHTML = '';

		// Get current quiz if any.
		var currentQuiz = lessonId ? lessonQuizzes[lessonId] : null;

		// Create the quiz selector box.
		var box = createQuizBox(currentQuiz, lessonId, isValidLessonId);
		container.appendChild(box);

		// Set up event handlers.
		setupBoxHandlers(box, lessonId);
	}

	/**
	 * Try to get the lesson ID from the currently open modal's DOM.
	 *
	 * Uses Tutor's stable data-cy selectors to locate the lesson modal,
	 * then inspects React fiber props for the lesson ID.
	 *
	 * @return {string|null} Lesson ID or null.
	 */
	function getLessonIdFromModal() {
		// Use the captured ID from click tracking if available.
		if (currentEditingLessonId) {
			return currentEditingLessonId;
		}

		// Try to find the modal via stable Tutor data-cy selectors.
		var saveBtn = document.querySelector('[data-cy="save-lesson"]');
		if (!saveBtn) {
			return null;
		}

		// Walk up to find the modal container.
		var modal = saveBtn.closest('[data-focus-trap="true"]') ||
					saveBtn.closest('[data-cy="tutor-modal"]');
		if (!modal) {
			return null;
		}

		// Try to extract the lesson ID from React fiber.
		return extractLessonIdFromFiber(modal);
	}

	/**
	 * Extract lesson ID from React fiber tree of a modal element.
	 *
	 * @param {HTMLElement} el DOM element to inspect.
	 * @return {string|null} Lesson ID or null.
	 */
	function extractLessonIdFromFiber(el) {
		var keys = Object.keys(el);
		for (var i = 0; i < keys.length; i++) {
			if (keys[i].startsWith('__reactFiber') || keys[i].startsWith('__reactProps')) {
				try {
					var fiber = el[keys[i]];
					var id = searchFiberForLessonId(fiber, 0);
					if (id) {
						return id;
					}
				} catch (err) {
					// Ignore errors accessing React internals.
				}
			}
		}
		return null;
	}

	/**
	 * Search through React fiber for a lesson ID.
	 *
	 * @param {Object} fiber React fiber node.
	 * @param {number} depth Current recursion depth.
	 * @return {string|null} Lesson ID or null.
	 */
	function searchFiberForLessonId(fiber, depth) {
		if (!fiber || depth > 8) {
			return null;
		}

		try {
			// Check memoizedProps for lessonId (the prop name used by LessonModal).
			if (fiber.memoizedProps) {
				var props = fiber.memoizedProps;
				if (props.lessonId && parseInt(props.lessonId) > 0) {
					return String(props.lessonId);
				}
				if (props.lesson && props.lesson.id) {
					return String(props.lesson.id);
				}
				if (props.item && props.item.id && props.item.post_type === 'lesson') {
					return String(props.item.id);
				}
			}

			// Check pendingProps.
			if (fiber.pendingProps) {
				var pending = fiber.pendingProps;
				if (pending.lessonId && parseInt(pending.lessonId) > 0) {
					return String(pending.lessonId);
				}
			}

			// Recurse into parent fiber.
			if (fiber.return) {
				var parentResult = searchFiberForLessonId(fiber.return, depth + 1);
				if (parentResult) {
					return parentResult;
				}
			}
		} catch (err) {
			// Ignore errors.
		}

		return null;
	}

	/**
	 * Set up click listeners to capture lesson IDs before modals open.
	 *
	 * Uses Tutor's stable data attributes to identify lesson edit actions.
	 */
	function setupLessonClickTracking() {
		document.addEventListener('click', function(e) {
			var target = e.target;
			var el = target;
			var maxDepth = 10;

			while (el && maxDepth > 0) {
				// Check for Tutor's stable data-cy="edit-lesson" attribute.
				if (el.getAttribute && el.getAttribute('data-cy') === 'edit-lesson') {
					var lessonId = extractLessonIdFromClickTarget(el);
					if (lessonId) {
						currentEditingLessonId = lessonId;
						return;
					}
				}

				// Check for data-lesson-icon attribute (another Tutor stable marker).
				if (el.getAttribute && el.getAttribute('data-lesson-icon') !== null) {
					var id = extractLessonIdFromClickTarget(el);
					if (id) {
						currentEditingLessonId = id;
						return;
					}
				}

				// Check for generic data attributes.
				if (el.getAttribute) {
					var dataId = el.getAttribute('data-lesson-id') ||
								 el.getAttribute('data-id') ||
								 el.getAttribute('data-content-id');
					if (dataId && parseInt(dataId) > 0) {
						currentEditingLessonId = dataId;
						return;
					}
				}

				el = el.parentElement;
				maxDepth--;
			}

			// Fallback: inspect React fiber on click target's ancestors.
			el = target;
			maxDepth = 10;
			while (el && maxDepth > 0) {
				var keys = Object.keys(el);
				for (var i = 0; i < keys.length; i++) {
					if (keys[i].startsWith('__reactFiber') || keys[i].startsWith('__reactProps')) {
						try {
							var fiber = el[keys[i]];
							var fiberLessonId = searchFiberForContentId(fiber, 0);
							if (fiberLessonId) {
								currentEditingLessonId = fiberLessonId;
								return;
							}
						} catch (err) {
							// Ignore.
						}
					}
				}
				el = el.parentElement;
				maxDepth--;
			}
		}, true); // Capture phase to run before React handlers.
	}

	/**
	 * Extract lesson ID from a click target element via React fiber.
	 *
	 * @param {HTMLElement} el The clicked element.
	 * @return {string|null} Lesson ID or null.
	 */
	function extractLessonIdFromClickTarget(el) {
		var keys = Object.keys(el);
		for (var i = 0; i < keys.length; i++) {
			if (keys[i].startsWith('__reactFiber') || keys[i].startsWith('__reactProps')) {
				try {
					var fiber = el[keys[i]];
					var id = searchFiberForContentId(fiber, 0);
					if (id) {
						return id;
					}
				} catch (err) {
					// Ignore.
				}
			}
		}
		return null;
	}

	/**
	 * Search React fiber for a content/lesson ID.
	 *
	 * Looks for common patterns in Tutor's curriculum data structure.
	 *
	 * @param {Object} fiber React fiber node.
	 * @param {number} depth Current recursion depth.
	 * @return {string|null} Content ID or null.
	 */
	function searchFiberForContentId(fiber, depth) {
		if (!fiber || depth > 6) {
			return null;
		}

		try {
			// Check memoizedProps.
			if (fiber.memoizedProps) {
				var props = fiber.memoizedProps;

				// Direct ID properties.
				if (props.id && (props.post_type === 'lesson' || props.type === 'lesson' || props.content_type === 'lesson')) {
					return String(props.id);
				}
				if (props.contentId && parseInt(props.contentId) > 0) {
					return String(props.contentId);
				}
				if (props.lessonId && parseInt(props.lessonId) > 0) {
					return String(props.lessonId);
				}

				// Nested objects.
				if (props.lesson && props.lesson.id) {
					return String(props.lesson.id);
				}
				if (props.item && props.item.id && (props.item.post_type === 'lesson' || props.item.type === 'lesson')) {
					return String(props.item.id);
				}
				if (props.content && props.content.id && props.content.type === 'lesson') {
					return String(props.content.id);
				}
				if (props.data && props.data.id && props.data.post_type === 'lesson') {
					return String(props.data.id);
				}

				// onClick handlers often have the ID in closure - check children props.
				if (props.onClick && props.id && parseInt(props.id) > 0) {
					return String(props.id);
				}
			}

			// Check pendingProps.
			if (fiber.pendingProps) {
				var pending = fiber.pendingProps;
				if (pending.lessonId && parseInt(pending.lessonId) > 0) {
					return String(pending.lessonId);
				}
				if (pending.contentId && parseInt(pending.contentId) > 0) {
					return String(pending.contentId);
				}
			}

			// Recurse into parent fiber.
			if (fiber.return) {
				var parentResult = searchFiberForContentId(fiber.return, depth + 1);
				if (parentResult) {
					return parentResult;
				}
			}
		} catch (err) {
			// Ignore errors.
		}

		return null;
	}

	/**
	 * Create the quiz selector box element.
	 *
	 * @param {Object|null} currentQuiz Current quiz data or null.
	 * @param {string|null} lessonId Lesson ID.
	 * @param {boolean}     isValidLessonId Whether lessonId is a real ID.
	 * @return {HTMLElement} The quiz box element.
	 */
	function createQuizBox(currentQuiz, lessonId, isValidLessonId) {
		var box = document.createElement('div');
		box.className = 'ppq-lesson-quiz-box';

		if (currentQuiz) {
			box.innerHTML = '\
				<div class="ppq-box-header">\
					<span class="ppq-box-title">PressPrimer Quiz</span>\
				</div>\
				<div class="ppq-box-content">\
					<div class="ppq-quiz-selector">\
						<div class="ppq-selected-quiz">\
							<div class="ppq-selected-info">\
								<span class="ppq-quiz-id">' + currentQuiz.id + '</span>\
								<span class="ppq-quiz-title">' + escapeHtml(currentQuiz.title) + '</span>\
							</div>\
							<div class="ppq-selected-actions">\
								<a href="' + config.adminUrl + 'admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=' + currentQuiz.id + '" target="_blank" class="ppq-action-btn ppq-edit-btn" title="Edit Quiz">\
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>\
								</a>\
								<button type="button" class="ppq-action-btn ppq-remove-btn" title="Remove Quiz">\
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>\
								</button>\
							</div>\
						</div>\
					</div>\
					<p class="ppq-help-text">Students must pass this quiz to complete the lesson.</p>\
				</div>\
			';
		} else if (isValidLessonId) {
			// We have a valid lesson ID - show the search interface.
			box.innerHTML = '\
				<div class="ppq-box-header">\
					<span class="ppq-box-title">PressPrimer Quiz</span>\
				</div>\
				<div class="ppq-box-content">\
					<div class="ppq-quiz-selector">\
						<div class="ppq-search-container">\
							<input type="text" class="ppq-quiz-search" placeholder="' + (strings.searchPlaceholder || 'Search quizzes...') + '" autocomplete="off" />\
						</div>\
						<div class="ppq-results-container">\
							<div class="ppq-results-heading">Recent Quizzes</div>\
							<div class="ppq-quiz-results"></div>\
						</div>\
					</div>\
					<p class="ppq-help-text">Link a quiz to this lesson. Students must pass to complete.</p>\
				</div>\
			';
		} else {
			// No valid lesson ID - show helpful message.
			box.innerHTML = '\
				<div class="ppq-box-header">\
					<span class="ppq-box-title">PressPrimer Quiz</span>\
				</div>\
				<div class="ppq-box-content">\
					<div class="ppq-no-lesson-id">\
						<p class="ppq-notice">\
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">\
								<circle cx="12" cy="12" r="10"/>\
								<line x1="12" y1="8" x2="12" y2="12"/>\
								<line x1="12" y1="16" x2="12.01" y2="16"/>\
							</svg>\
							Close this modal and click the lesson name again to attach a quiz.\
						</p>\
					</div>\
				</div>\
			';
		}

		return box;
	}

	/**
	 * Set up event handlers for the quiz box.
	 *
	 * @param {HTMLElement}  box      The quiz box element.
	 * @param {string|null}  lessonId Lesson ID.
	 */
	function setupBoxHandlers(box, lessonId) {
		var searchInput = box.querySelector('.ppq-quiz-search');
		var resultsContainer = box.querySelector('.ppq-quiz-results');
		var resultsHeading = box.querySelector('.ppq-results-heading');
		var removeBtn = box.querySelector('.ppq-remove-btn');

		if (searchInput && resultsContainer) {
			var searchTimeout;

			// Load recent quizzes on focus.
			searchInput.addEventListener('focus', function() {
				if (this.value.length < 2) {
					loadQuizzes(resultsContainer, resultsHeading, null, box, lessonId);
				}
			});

			// Search on input.
			searchInput.addEventListener('input', function() {
				var query = this.value.trim();
				clearTimeout(searchTimeout);

				if (query.length < 2) {
					loadQuizzes(resultsContainer, resultsHeading, null, box, lessonId);
					return;
				}

				searchTimeout = setTimeout(function() {
					loadQuizzes(resultsContainer, resultsHeading, query, box, lessonId);
				}, 300);
			});

			// Auto-load recent quizzes.
			loadQuizzes(resultsContainer, resultsHeading, null, box, lessonId);
		}

		if (removeBtn) {
			removeBtn.addEventListener('click', function(e) {
				e.preventDefault();
				removeQuizFromLesson(box, lessonId);
			});
		}
	}

	/**
	 * Load quizzes (recent or search).
	 *
	 * @param {HTMLElement}  container Results container.
	 * @param {HTMLElement}  heading   Results heading.
	 * @param {string|null}  query     Search query or null for recent.
	 * @param {HTMLElement}  box       The quiz box element.
	 * @param {string|null}  lessonId  Lesson ID.
	 */
	function loadQuizzes(container, heading, query, box, lessonId) {
		heading.textContent = query ? 'Search Results' : 'Recent Quizzes';
		container.innerHTML = '<div class="ppq-loading">Loading...</div>';

		var url = config.restUrl + 'ppq/v1/tutorlms/quizzes/search';
		url += query ? '?search=' + encodeURIComponent(query) : '?recent=1';

		fetch(url, {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.restNonce,
			},
		})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				if (data.success && data.quizzes && data.quizzes.length > 0) {
					renderQuizResults(data.quizzes, container, box, lessonId);
				} else {
					container.innerHTML = '<div class="ppq-no-results">' + (strings.noQuizzes || 'No quizzes found') + '</div>';
				}
			})
			.catch(function() {
				container.innerHTML = '<div class="ppq-error">' + (strings.error || 'Error loading quizzes') + '</div>';
			});
	}

	/**
	 * Render quiz results.
	 *
	 * @param {Array}       quizzes   Array of quiz objects.
	 * @param {HTMLElement}  container Results container.
	 * @param {HTMLElement}  box       The quiz box element.
	 * @param {string|null}  lessonId  Lesson ID.
	 */
	function renderQuizResults(quizzes, container, box, lessonId) {
		container.innerHTML = '';

		quizzes.forEach(function(quiz) {
			var item = document.createElement('div');
			item.className = 'ppq-quiz-item';
			item.innerHTML = '\
				<span class="ppq-quiz-id">' + quiz.id + '</span>\
				<span class="ppq-quiz-title">' + escapeHtml(quiz.title) + '</span>\
			';

			item.addEventListener('click', function() {
				selectQuiz(quiz, box, lessonId);
			});

			container.appendChild(item);
		});
	}

	/**
	 * Select a quiz and save the association.
	 *
	 * @param {Object}      quiz     Quiz data.
	 * @param {HTMLElement}  box      The quiz box element.
	 * @param {string|null}  lessonId Lesson ID.
	 */
	function selectQuiz(quiz, box, lessonId) {
		saveLessonQuiz(lessonId, quiz.id, function(success, data) {
			if (success) {
				// Update lessonId if we got a real one back.
				if (data && data.lesson_id) {
					lessonId = data.lesson_id;
				}

				lessonQuizzes[lessonId] = quiz;

				// Update the box to show selected quiz.
				var selector = box.querySelector('.ppq-quiz-selector');
				selector.innerHTML = '\
					<div class="ppq-selected-quiz">\
						<div class="ppq-selected-info">\
							<span class="ppq-quiz-id">' + quiz.id + '</span>\
							<span class="ppq-quiz-title">' + escapeHtml(quiz.title) + '</span>\
						</div>\
						<div class="ppq-selected-actions">\
							<a href="' + config.adminUrl + 'admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=' + quiz.id + '" target="_blank" class="ppq-action-btn ppq-edit-btn" title="Edit Quiz">\
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>\
							</a>\
							<button type="button" class="ppq-action-btn ppq-remove-btn" title="Remove Quiz">\
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>\
							</button>\
						</div>\
					</div>\
				';

				// Reattach remove handler.
				var removeBtn = selector.querySelector('.ppq-remove-btn');
				removeBtn.addEventListener('click', function(e) {
					e.preventDefault();
					removeQuizFromLesson(box, lessonId);
				});
			} else {
				alert('Failed to save quiz. Please try again.');
			}
		});
	}

	/**
	 * Remove quiz from lesson.
	 *
	 * @param {HTMLElement}  box      The quiz box element.
	 * @param {string|null}  lessonId Lesson ID.
	 */
	function removeQuizFromLesson(box, lessonId) {
		saveLessonQuiz(lessonId, 0, function(success) {
			if (success) {
				delete lessonQuizzes[lessonId];

				// Update box to show search again.
				var selector = box.querySelector('.ppq-quiz-selector');
				selector.innerHTML = '\
					<div class="ppq-search-container">\
						<input type="text" class="ppq-quiz-search" placeholder="' + (strings.searchPlaceholder || 'Search quizzes...') + '" autocomplete="off" />\
					</div>\
					<div class="ppq-results-container">\
						<div class="ppq-results-heading">Recent Quizzes</div>\
						<div class="ppq-quiz-results"></div>\
					</div>\
				';

				// Reattach handlers.
				setupBoxHandlers(box, lessonId);
			}
		});
	}

	/**
	 * Save lesson quiz association via REST API.
	 *
	 * @param {string|null} lessonId Lesson ID.
	 * @param {number}      quizId   Quiz ID (0 to remove).
	 * @param {Function}    callback Callback(success, data).
	 */
	function saveLessonQuiz(lessonId, quizId, callback) {
		fetch(config.restUrl + 'ppq/v1/tutorlms/lesson-quiz', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.restNonce,
			},
			body: JSON.stringify({
				course_id: courseId,
				lesson_id: lessonId,
				quiz_id: quizId,
			}),
		})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				callback(data.success, data);
			})
			.catch(function() {
				callback(false);
			});
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Inject CSS styles.
	 */
	function injectStyles() {
		var style = document.createElement('style');
		style.id = 'ppq-tutorlms-lesson-editor-styles';
		style.textContent = '\
			/* PPQ Quiz Box in Lesson Editor */\
			.ppq-lesson-quiz-box {\
				background: #fff;\
				border: 1px solid #e2e8f0;\
				border-radius: 8px;\
				margin: 16px 0;\
				overflow: hidden;\
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;\
			}\
			.ppq-box-header {\
				background: linear-gradient(135deg, #7b68ee 0%, #6c5ce7 100%);\
				padding: 12px 16px;\
			}\
			.ppq-box-title {\
				color: #fff;\
				font-weight: 600;\
				font-size: 14px;\
			}\
			.ppq-box-content {\
				padding: 16px;\
			}\
			.ppq-help-text {\
				margin: 12px 0 0;\
				font-size: 12px;\
				color: #666;\
				line-height: 1.4;\
			}\
			\
			/* Search */\
			.ppq-search-container {\
				margin-bottom: 12px;\
			}\
			.ppq-quiz-search {\
				width: 100%;\
				padding: 8px 12px;\
				border: 1px solid #d0d5dd;\
				border-radius: 6px;\
				font-size: 13px;\
				box-sizing: border-box;\
			}\
			.ppq-quiz-search:focus {\
				border-color: #7b68ee;\
				outline: none;\
				box-shadow: 0 0 0 2px rgba(123, 104, 238, 0.15);\
			}\
			\
			/* Results */\
			.ppq-results-container {\
				max-height: 200px;\
				overflow: hidden;\
				display: flex;\
				flex-direction: column;\
			}\
			.ppq-results-heading {\
				font-size: 11px;\
				font-weight: 600;\
				color: #888;\
				text-transform: uppercase;\
				letter-spacing: 0.5px;\
				margin-bottom: 8px;\
			}\
			.ppq-quiz-results {\
				flex: 1;\
				overflow-y: auto;\
				border: 1px solid #e8e8e8;\
				border-radius: 6px;\
				max-height: 150px;\
			}\
			.ppq-quiz-item {\
				padding: 10px 12px;\
				cursor: pointer;\
				border-bottom: 1px solid #f0f0f0;\
				display: flex;\
				align-items: center;\
				gap: 8px;\
				transition: background 0.15s;\
				font-size: 13px;\
			}\
			.ppq-quiz-item:hover {\
				background: #f8f9ff;\
			}\
			.ppq-quiz-item:last-child {\
				border-bottom: none;\
			}\
			.ppq-quiz-id {\
				color: #7b68ee;\
				font-weight: 600;\
				font-size: 12px;\
			}\
			.ppq-quiz-title {\
				flex: 1;\
				color: #333;\
				white-space: nowrap;\
				overflow: hidden;\
				text-overflow: ellipsis;\
			}\
			.ppq-loading,\
			.ppq-no-results,\
			.ppq-error {\
				padding: 16px;\
				text-align: center;\
				color: #888;\
				font-size: 13px;\
			}\
			\
			/* Selected Quiz */\
			.ppq-selected-quiz {\
				display: flex;\
				align-items: center;\
				justify-content: space-between;\
				padding: 12px;\
				background: linear-gradient(135deg, #f8f8ff 0%, #f0f0ff 100%);\
				border: 1px solid #d0d0ff;\
				border-radius: 6px;\
			}\
			.ppq-selected-info {\
				display: flex;\
				align-items: center;\
				gap: 8px;\
				flex: 1;\
				min-width: 0;\
			}\
			.ppq-selected-info .ppq-quiz-id {\
				flex-shrink: 0;\
			}\
			.ppq-selected-info .ppq-quiz-title {\
				white-space: nowrap;\
				overflow: hidden;\
				text-overflow: ellipsis;\
			}\
			.ppq-selected-actions {\
				display: flex;\
				gap: 4px;\
				flex-shrink: 0;\
			}\
			.ppq-action-btn {\
				background: none;\
				border: none;\
				padding: 6px;\
				cursor: pointer;\
				color: #666;\
				border-radius: 4px;\
				display: flex;\
				align-items: center;\
				justify-content: center;\
				text-decoration: none;\
			}\
			.ppq-edit-btn:hover {\
				background: #e8e8ff;\
				color: #7b68ee;\
			}\
			.ppq-remove-btn:hover {\
				background: #ffe8e8;\
				color: #dc3545;\
			}\
			\
			/* No Lesson ID Notice */\
			.ppq-no-lesson-id {\
				text-align: center;\
				padding: 8px 0;\
			}\
			.ppq-notice {\
				margin: 0 0 8px;\
				padding: 10px 12px;\
				background: #fff8e5;\
				border: 1px solid #f0c36d;\
				border-radius: 4px;\
				font-size: 13px;\
				color: #6d5a20;\
				line-height: 1.4;\
			}\
			.ppq-wp-editor-link {\
				display: inline-flex;\
				align-items: center;\
				padding: 8px 16px;\
				background: linear-gradient(135deg, #7b68ee 0%, #6c5ce7 100%);\
				color: #fff;\
				text-decoration: none;\
				border-radius: 4px;\
				font-size: 13px;\
				font-weight: 500;\
				margin-top: 8px;\
				transition: opacity 0.2s;\
			}\
			.ppq-wp-editor-link:hover {\
				opacity: 0.9;\
				color: #fff;\
			}\
		';
		document.head.appendChild(style);
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
