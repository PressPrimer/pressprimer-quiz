/**
 * TutorLMS Course Builder Integration - Lesson Editor
 *
 * Adds a PressPrimer Quiz selector to the lesson editor modal sidebar.
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

	// Track processed modals.
	var processedModals = new WeakSet();

	// Debug mode - set to false for production.
	var DEBUG = false;

	function log() {
		if (DEBUG) {
			console.log.apply(console, ['[PPQ TutorLMS]'].concat(Array.prototype.slice.call(arguments)));
		}
	}

	/**
	 * Initialize the integration
	 */
	function init() {
		log('Initializing lesson editor integration...');
		log('Config:', config);
		log('Course ID:', courseId);
		log('Existing lesson quizzes:', lessonQuizzes);

		// Inject styles.
		injectStyles();

		// Set up click tracking to capture lesson IDs before modals open.
		setupLessonClickTracking();

		// Watch for lesson editor modals.
		observeDOM();

		// Also check periodically in case mutations are missed.
		setInterval(scanForLessonModals, 1000);
	}

	/**
	 * Observe DOM for changes
	 */
	function observeDOM() {
		var observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length > 0) {
					scanForLessonModals();
				}
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});

		log('DOM observer started');
	}

	/**
	 * Scan the entire document for lesson modals
	 */
	function scanForLessonModals() {
		// Look for ANY element that contains lesson-related text.
		// TutorLMS might not use traditional modal classes.
		var allElements = document.querySelectorAll('*');

		allElements.forEach(function(el) {
			if (processedModals.has(el)) return;

			// Skip tiny elements and common containers.
			if (el.offsetWidth < 300 || el.offsetHeight < 200) return;
			if (el.tagName === 'HTML' || el.tagName === 'BODY' || el.tagName === 'HEAD') return;

			// Check if this looks like a lesson editor.
			var html = (el.innerHTML || '').toLowerCase();
			var hasLessonFields =
				html.indexOf('lesson title') !== -1 ||
				html.indexOf('lesson name') !== -1 ||
				(html.indexOf('title') !== -1 && html.indexOf('video') !== -1 && html.indexOf('content') !== -1);

			// Check for input fields typical of lesson editors.
			var hasInputs = el.querySelector('input[type="text"], textarea');
			var hasVideoSection = html.indexOf('video') !== -1 || html.indexOf('youtube') !== -1 || html.indexOf('vimeo') !== -1;

			if (hasLessonFields && hasInputs) {
				// Check if this is a floating/overlay element (not part of main page flow).
				var style = window.getComputedStyle(el);
				var isFloating =
					style.position === 'fixed' ||
					style.position === 'absolute' ||
					el.closest('[style*="position: fixed"]') ||
					el.closest('[style*="position: absolute"]') ||
					el.closest('[class*="modal"]') ||
					el.closest('[class*="Modal"]') ||
					el.closest('[class*="drawer"]') ||
					el.closest('[class*="Drawer"]') ||
					el.closest('[class*="overlay"]') ||
					el.closest('[class*="dialog"]');

				if (isFloating) {
					processedModals.add(el);
					log('=== FOUND LESSON EDITOR ===');
					log('Element:', el.tagName, el.className);
					log('Dimensions:', el.offsetWidth, 'x', el.offsetHeight);
					log('Position style:', style.position);
					log('HTML preview:', html.substring(0, 300));

					setTimeout(function() {
						injectQuizSelector(el);
					}, 500);
				}
			}
		});

		// Also do a simpler scan for any visible overlay/modal with forms.
		var overlays = document.querySelectorAll([
			'[class*="modal"]:not([style*="display: none"])',
			'[class*="Modal"]:not([style*="display: none"])',
			'[class*="drawer"]:not([style*="display: none"])',
			'[class*="Drawer"]:not([style*="display: none"])',
			'[class*="overlay"]:not([style*="display: none"])',
			'[class*="dialog"]:not([style*="display: none"])',
			'[role="dialog"]',
			// TutorLMS specific.
			'.tutor-modal',
			'.tutor-modal-wrap',
			'[class*="tutor-"][class*="-modal"]',
			// React patterns.
			'[id*="portal"]',
			'[id*="Portal"]',
			'div[style*="position: fixed"]',
			'div[style*="z-index: 9"]'
		].join(','));

		overlays.forEach(function(overlay) {
			if (processedModals.has(overlay)) return;
			if (overlay.offsetWidth < 200) return;

			var html = (overlay.innerHTML || '').toLowerCase();
			var looksLikeLesson =
				html.indexOf('lesson') !== -1 ||
				html.indexOf('video source') !== -1 ||
				html.indexOf('video url') !== -1 ||
				(html.indexOf('title') !== -1 && html.indexOf('description') !== -1);

			if (looksLikeLesson) {
				processedModals.add(overlay);
				log('=== FOUND OVERLAY WITH LESSON CONTENT ===');
				log('Element:', overlay.tagName, overlay.className);
				log('ID:', overlay.id);
				log('HTML preview:', html.substring(0, 500));

				setTimeout(function() {
					injectQuizSelector(overlay);
				}, 500);
			}
		});
	}

	/**
	 * Check if element is a lesson editor
	 */
	function isLessonEditor(element) {
		// Skip if not visible.
		if (element.offsetParent === null && !element.closest('[style*="display: block"]')) {
			return false;
		}

		var html = element.innerHTML.toLowerCase();
		var classList = (element.className || '').toLowerCase();

		// Look for lesson-specific indicators.
		var lessonIndicators = [
			'lesson title',
			'lesson name',
			'add lesson',
			'edit lesson',
			'lesson content',
			'lesson description',
			'lesson preview',
			'video source',
			'video url'
		];

		var hasLessonContent = lessonIndicators.some(function(indicator) {
			return html.indexOf(indicator) !== -1;
		});

		// Also check for specific input fields.
		var hasLessonInput = element.querySelector([
			'input[placeholder*="lesson" i]',
			'input[placeholder*="title" i]',
			'textarea[placeholder*="lesson" i]',
			'[class*="lesson-title"]',
			'[class*="lessonTitle"]',
			'[data-lesson]',
			'[data-lesson-id]'
		].join(','));

		// Check if it looks like a modal (has backdrop, centered, etc).
		var looksLikeModal =
			classList.indexOf('modal') !== -1 ||
			classList.indexOf('dialog') !== -1 ||
			classList.indexOf('drawer') !== -1 ||
			classList.indexOf('overlay') !== -1 ||
			element.getAttribute('role') === 'dialog';

		var result = (hasLessonContent || hasLessonInput) && looksLikeModal;

		if (hasLessonContent || hasLessonInput) {
			log('Potential lesson element found:', {
				hasLessonContent: hasLessonContent,
				hasLessonInput: !!hasLessonInput,
				looksLikeModal: looksLikeModal,
				classList: classList,
				result: result
			});
		}

		return result;
	}

	/**
	 * Inject the quiz selector into the lesson modal
	 */
	function injectQuizSelector(modal) {
		log('Attempting to inject quiz selector into modal');

		// Check if already injected.
		if (modal.querySelector('.ppq-lesson-quiz-box')) {
			log('Quiz selector already exists in this modal');
			return;
		}

		// Find the best place to inject.
		var targetContainer = findInjectionTarget(modal);

		if (!targetContainer) {
			log('Could not find injection target in modal');
			// Log modal structure for debugging.
			logModalStructure(modal);
			return;
		}

		log('Found injection target:', targetContainer);

		// Get lesson ID if available.
		var lessonId = getLessonId(modal);
		log('Lesson ID:', lessonId);

		// Get current quiz if any.
		var currentQuiz = lessonId ? lessonQuizzes[lessonId] : null;
		log('Current quiz for lesson:', currentQuiz);

		// Create the quiz selector box.
		var box = createQuizBox(currentQuiz, lessonId);

		// Insert the box.
		targetContainer.appendChild(box);

		// Set up event handlers.
		setupBoxHandlers(box, modal, lessonId);

		log('Quiz selector injected successfully');
	}

	/**
	 * Find the best place to inject our quiz box
	 */
	function findInjectionTarget(modal) {
		log('Finding injection target in modal...');

		// TutorLMS uses CSS-in-JS with classes like css-xxxxx.
		// The lesson editor typically has a two-column layout.
		// Let's find the structure by looking at the actual DOM.

		// Strategy 1: Find a flex container with two column-like children.
		var allDivs = modal.querySelectorAll('div');
		var bestTarget = null;

		for (var i = 0; i < allDivs.length; i++) {
			var div = allDivs[i];
			var style = window.getComputedStyle(div);

			// Look for flex containers.
			if (style.display === 'flex' || style.display === 'grid') {
				var visibleChildren = Array.prototype.filter.call(div.children, function(c) {
					return c.offsetWidth > 100 && c.tagName === 'DIV';
				});

				// Two-column layout.
				if (visibleChildren.length === 2) {
					var leftWidth = visibleChildren[0].offsetWidth;
					var rightWidth = visibleChildren[1].offsetWidth;

					// The right column is typically narrower (sidebar).
					if (rightWidth < leftWidth && rightWidth > 200) {
						log('Found two-column layout. Left:', leftWidth, 'Right:', rightWidth);
						log('Right column classes:', visibleChildren[1].className);
						bestTarget = visibleChildren[1];
						break;
					}
					// Or the left might be the sidebar.
					else if (leftWidth < rightWidth && leftWidth > 200) {
						log('Found two-column layout (sidebar on left). Left:', leftWidth, 'Right:', rightWidth);
						log('Left column classes:', visibleChildren[0].className);
						bestTarget = visibleChildren[0];
						break;
					}
				}
			}
		}

		if (bestTarget) {
			// Don't inject into TinyMCE editor elements.
			if (bestTarget.querySelector('.mce-container, .mce-panel, [class*="mce-"]')) {
				log('Target contains TinyMCE, looking for better spot inside...');
				// Find a child that's not TinyMCE.
				var nonMceChildren = Array.prototype.filter.call(bestTarget.children, function(c) {
					return !c.querySelector('.mce-container, .mce-panel') && !c.classList.contains('mce-container');
				});
				if (nonMceChildren.length > 0) {
					bestTarget = nonMceChildren[nonMceChildren.length - 1];
					log('Using non-TinyMCE child:', bestTarget.className);
				}
			}
			return bestTarget;
		}

		// Strategy 2: Look for the focus trap container and find columns inside.
		var focusTrap = modal.querySelector('[data-focus-trap]');
		if (focusTrap) {
			log('Found focus trap container');
			var innerDivs = focusTrap.querySelectorAll(':scope > div > div');
			for (var j = 0; j < innerDivs.length; j++) {
				var innerDiv = innerDivs[j];
				var innerStyle = window.getComputedStyle(innerDiv);
				if (innerStyle.display === 'flex') {
					var cols = Array.prototype.filter.call(innerDiv.children, function(c) {
						return c.offsetWidth > 100 && c.tagName === 'DIV';
					});
					if (cols.length >= 2) {
						// Find the narrower column (likely sidebar).
						var narrowest = cols.reduce(function(prev, curr) {
							return curr.offsetWidth < prev.offsetWidth ? curr : prev;
						});
						if (narrowest.offsetWidth > 200) {
							log('Found sidebar in focus trap:', narrowest.className);
							return narrowest;
						}
					}
				}
			}
		}

		// Strategy 3: Look for any container that has "Lesson Preview" text (TutorLMS feature).
		var allText = modal.querySelectorAll('*');
		for (var k = 0; k < allText.length; k++) {
			var el = allText[k];
			if (el.childNodes.length === 1 && el.childNodes[0].nodeType === 3) {
				var text = el.textContent.trim().toLowerCase();
				if (text === 'lesson preview' || text === 'featured image' || text === 'video source') {
					// Found a label, go up to find the section container.
					var section = el.closest('div');
					if (section) {
						var sectionParent = section.parentElement;
						if (sectionParent && sectionParent.offsetWidth > 200) {
							log('Found settings section via label "' + text + '":', sectionParent.className);
							return sectionParent;
						}
					}
				}
			}
		}

		// Strategy 4: As a last resort, append to the modal content area.
		var contentArea = modal.querySelector('[data-focus-trap] > div > div');
		if (contentArea) {
			log('Using content area as fallback');
			return contentArea;
		}

		log('Could not find suitable injection target');
		return null;
	}

	/**
	 * Log modal structure for debugging
	 */
	function logModalStructure(modal) {
		log('=== MODAL STRUCTURE DEBUG ===');
		log('Modal tag:', modal.tagName);
		log('Modal classes:', modal.className);
		log('Modal ID:', modal.id);

		function logChildren(el, depth) {
			if (depth > 3) return;
			var indent = '  '.repeat(depth);
			Array.prototype.forEach.call(el.children, function(child) {
				if (child.offsetWidth > 0) {
					log(indent + child.tagName + '.' + (child.className || '').split(' ').slice(0, 3).join('.'));
					logChildren(child, depth + 1);
				}
			});
		}

		logChildren(modal, 0);
		log('=== END MODAL STRUCTURE ===');
	}

	// Track the currently editing lesson ID (set when user clicks edit on a lesson).
	var currentEditingLessonId = null;

	/**
	 * Set up click listeners on lesson items to capture the lesson ID before modal opens
	 */
	function setupLessonClickTracking() {
		log('Setting up lesson click tracking...');

		// Use event delegation to catch clicks on lesson edit buttons/items.
		document.addEventListener('click', function(e) {
			var target = e.target;

			// Debug: log what was clicked.
			log('Click detected on:', target.tagName, target.className);

			// Look for lesson-related click targets.
			// Walk up the DOM to find elements with lesson IDs.
			var el = target;
			var maxDepth = 15;
			while (el && maxDepth > 0) {
				// Check for data attributes.
				var lessonId = el.getAttribute('data-lesson-id') ||
							   el.getAttribute('data-id') ||
							   el.getAttribute('data-item-id') ||
							   el.getAttribute('data-content-id');

				if (lessonId && parseInt(lessonId) > 0) {
					log('Captured lesson ID from data attribute:', lessonId);
					currentEditingLessonId = lessonId;
					return;
				}

				// Check for React props on this element.
				var keys = Object.keys(el);
				for (var i = 0; i < keys.length; i++) {
					if (keys[i].startsWith('__reactFiber') || keys[i].startsWith('__reactProps')) {
						try {
							var fiber = el[keys[i]];
							var id = findIdInReactFiber(fiber, 0);
							if (id) {
								log('Captured lesson ID from React fiber:', id);
								currentEditingLessonId = id;
								return;
							}
						} catch (err) {
							// Ignore errors.
						}
					}
				}

				el = el.parentElement;
				maxDepth--;
			}

			// If we got here, try a more aggressive search.
			// Look for any element in the click path that mentions "lesson" and has an ID nearby.
			log('Standard search failed, trying aggressive search...');

			el = target;
			maxDepth = 15;
			while (el && maxDepth > 0) {
				// Check all attributes for any ID-like value.
				if (el.attributes) {
					for (var a = 0; a < el.attributes.length; a++) {
						var attr = el.attributes[a];
						var match = attr.value.match(/^(\d+)$/);
						if (match && parseInt(match[1]) > 0) {
							// Found a numeric attribute value - could be an ID.
							log('Found numeric attribute:', attr.name, '=', attr.value, 'on', el.tagName);
						}
					}
				}

				// Deep search React fiber for any "id" field with post_type lesson.
				var fiberKeys = Object.keys(el);
				for (var k = 0; k < fiberKeys.length; k++) {
					if (fiberKeys[k].startsWith('__reactFiber')) {
						try {
							var deepId = deepSearchFiberForLessonId(el[fiberKeys[k]], 0);
							if (deepId) {
								log('Found lesson ID from deep fiber search:', deepId);
								currentEditingLessonId = deepId;
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
		}, true); // Use capture phase to get the event before React.
	}

	/**
	 * Deep search through React fiber for lesson ID
	 */
	function deepSearchFiberForLessonId(obj, depth) {
		if (!obj || depth > 8) return null;
		if (typeof obj !== 'object') return null;

		try {
			// Check if this object has id and looks like a lesson.
			if (obj.id && (obj.post_type === 'lesson' || obj.type === 'lesson' || obj.content_type === 'lesson')) {
				return obj.id;
			}

			// Check common property names.
			if (obj.lesson && obj.lesson.id) return obj.lesson.id;
			if (obj.item && obj.item.id && (obj.item.post_type === 'lesson' || obj.item.type === 'lesson')) return obj.item.id;
			if (obj.data && obj.data.id && obj.data.post_type === 'lesson') return obj.data.id;
			if (obj.content && obj.content.id && obj.content.type === 'lesson') return obj.content.id;

			// Check memoizedProps and pendingProps.
			if (obj.memoizedProps) {
				var propsResult = deepSearchFiberForLessonId(obj.memoizedProps, depth + 1);
				if (propsResult) return propsResult;
			}
			if (obj.pendingProps) {
				var pendingResult = deepSearchFiberForLessonId(obj.pendingProps, depth + 1);
				if (pendingResult) return pendingResult;
			}

			// Check return (parent fiber).
			if (obj.return && depth < 5) {
				var returnResult = deepSearchFiberForLessonId(obj.return, depth + 1);
				if (returnResult) return returnResult;
			}
		} catch (err) {
			// Ignore errors.
		}

		return null;
	}

	/**
	 * Recursively search React fiber for lesson ID
	 */
	function findIdInReactFiber(fiber, depth) {
		if (!fiber || depth > 5) return null;

		// Check memoizedProps.
		if (fiber.memoizedProps) {
			var props = fiber.memoizedProps;
			if (props.lesson && props.lesson.id) {
				return props.lesson.id;
			}
			if (props.item && props.item.id && props.item.post_type === 'lesson') {
				return props.item.id;
			}
			if (props.id && props.post_type === 'lesson') {
				return props.id;
			}
			if (props.data && props.data.id) {
				return props.data.id;
			}
		}

		// Check pendingProps.
		if (fiber.pendingProps) {
			var pending = fiber.pendingProps;
			if (pending.lesson && pending.lesson.id) {
				return pending.lesson.id;
			}
			if (pending.item && pending.item.id) {
				return pending.item.id;
			}
		}

		// Recurse into return (parent) fiber.
		if (fiber.return) {
			var parentResult = findIdInReactFiber(fiber.return, depth + 1);
			if (parentResult) return parentResult;
		}

		return null;
	}

	/**
	 * Get lesson ID from modal
	 */
	function getLessonId(modal) {
		log('Searching for lesson ID in modal...');

		// Strategy 0: Use the captured lesson ID from click tracking.
		if (currentEditingLessonId) {
			log('Using captured lesson ID:', currentEditingLessonId);
			var capturedId = currentEditingLessonId;
			// Don't reset - keep it for subsequent operations on this modal.
			return capturedId;
		}

		// Strategy 1: Look for hidden input or data attribute.
		var selectors = [
			'input[name*="lesson_id"]',
			'input[name*="lessonId"]',
			'input[name*="lesson-id"]',
			'input[name="id"]',
			'[data-lesson-id]',
			'[data-lesson_id]',
			'[data-lessonid]',
			'[data-id]'
		];

		for (var i = 0; i < selectors.length; i++) {
			var el = modal.querySelector(selectors[i]);
			if (el) {
				var value = el.value || el.getAttribute('data-lesson-id') || el.getAttribute('data-lesson_id') || el.getAttribute('data-lessonid') || el.getAttribute('data-id');
				if (value && value !== '0' && value !== '') {
					log('Found lesson ID from selector', selectors[i], ':', value);
					return value;
				}
			}
		}

		// Strategy 2: Try to extract from URL hash or query params.
		var hash = window.location.hash;
		log('URL hash:', hash);

		var lessonMatch = hash.match(/lesson[_-]?id[=:](\d+)/i) ||
						  hash.match(/lesson[\/](\d+)/i) ||
						  hash.match(/\/lesson\/(\d+)/i) ||
						  hash.match(/edit\/(\d+)/i);
		if (lessonMatch) {
			log('Found lesson ID from URL hash:', lessonMatch[1]);
			return lessonMatch[1];
		}

		// Strategy 3: Search the React root for active modal state.
		var reactRoot = document.getElementById('tutor-course-builder') ||
						document.getElementById('tutor-backend-root') ||
						document.querySelector('[id*="tutor"]');

		if (reactRoot) {
			var rootKeys = Object.keys(reactRoot);
			for (var j = 0; j < rootKeys.length; j++) {
				if (rootKeys[j].startsWith('__reactContainer') || rootKeys[j].startsWith('_reactRoot')) {
					try {
						var container = reactRoot[rootKeys[j]];
						log('Found React container, searching for lesson state...');
						var lessonIdFromState = searchReactStateForLessonId(container, 0);
						if (lessonIdFromState) {
							log('Found lesson ID from React state:', lessonIdFromState);
							return lessonIdFromState;
						}
					} catch (err) {
						log('Error searching React state:', err.message);
					}
				}
			}
		}

		// Strategy 4: Look in window for TutorLMS global data.
		var globalVars = ['_tutorCourseBuilder', 'tutorCourseBuilder', '_tutorobject', 'tutor_data'];
		for (var g = 0; g < globalVars.length; g++) {
			if (window[globalVars[g]]) {
				log('Found global var:', globalVars[g], window[globalVars[g]]);
			}
		}

		// Strategy 5: Try to find lesson in the curriculum list by title.
		var titleInput = modal.querySelector('input[type="text"]');
		if (titleInput && titleInput.value) {
			var title = titleInput.value.trim();
			log('Looking for lesson by title:', title);

			// Search the page for lesson elements with this title.
			var lessonElements = document.querySelectorAll('[class*="lesson"], [data-type="lesson"]');
			lessonElements.forEach(function(lessonEl) {
				if (lessonEl.textContent.indexOf(title) !== -1) {
					var id = lessonEl.getAttribute('data-id') || lessonEl.getAttribute('data-lesson-id');
					if (id) {
						log('Found lesson ID by title match:', id);
						currentEditingLessonId = id;
					}
				}
			});

			if (currentEditingLessonId) {
				return currentEditingLessonId;
			}
		}

		// Generate a temporary ID.
		var tempId = 'temp-' + Date.now();
		log('WARNING: Using temporary lesson ID (saving will fail):', tempId);
		log('TIP: The lesson might be new and unsaved. Save the lesson first, then add the quiz.');
		return tempId;
	}

	/**
	 * Search React state tree for lesson ID
	 */
	function searchReactStateForLessonId(node, depth) {
		if (!node || depth > 10) return null;

		try {
			// Check if this node has state with lesson info.
			if (node.memoizedState) {
				var state = node.memoizedState;
				// Look for modal-related state.
				if (state.isOpen && state.lesson) {
					return state.lesson.id;
				}
				if (state.editingLesson) {
					return state.editingLesson.id;
				}
				if (state.currentLesson) {
					return state.currentLesson.id;
				}
			}

			// Check child.
			if (node.child) {
				var childResult = searchReactStateForLessonId(node.child, depth + 1);
				if (childResult) return childResult;
			}

			// Check sibling.
			if (node.sibling) {
				var siblingResult = searchReactStateForLessonId(node.sibling, depth + 1);
				if (siblingResult) return siblingResult;
			}
		} catch (err) {
			// Ignore errors from accessing React internals.
		}

		return null;
	}

	/**
	 * Create the quiz selector box element
	 */
	function createQuizBox(currentQuiz, lessonId) {
		var box = document.createElement('div');
		box.className = 'ppq-lesson-quiz-box';

		var isValidLessonId = lessonId && !lessonId.toString().startsWith('temp-');

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
								<a href="' + config.adminUrl + 'admin.php?page=pressprimer-quiz-edit&id=' + currentQuiz.id + '" target="_blank" class="ppq-action-btn ppq-edit-btn" title="Edit Quiz">\
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
			// No valid lesson ID - show instructions to use WordPress editor.
			// Check if we have a captured lesson ID we can use for a direct edit link.
			var editLinkUrl = config.adminUrl + 'edit.php?post_type=lesson';
			var editLinkText = 'View Lessons';

			if (currentEditingLessonId && !currentEditingLessonId.toString().startsWith('temp-')) {
				editLinkUrl = config.adminUrl + 'post.php?post=' + currentEditingLessonId + '&action=edit';
				editLinkText = 'Edit This Lesson';
			}

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
							To attach a quiz, edit this lesson in the WordPress editor.\
						</p>\
						<a href="' + editLinkUrl + '" target="_blank" class="ppq-wp-editor-link">\
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">\
								<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>\
								<polyline points="15 3 21 3 21 9"/>\
								<line x1="10" y1="14" x2="21" y2="3"/>\
							</svg>\
							' + editLinkText + '\
						</a>\
					</div>\
				</div>\
			';
		}

		return box;
	}

	/**
	 * Set up event handlers for the quiz box
	 */
	function setupBoxHandlers(box, modal, lessonId) {
		var searchInput = box.querySelector('.ppq-quiz-search');
		var resultsContainer = box.querySelector('.ppq-quiz-results');
		var resultsHeading = box.querySelector('.ppq-results-heading');
		var removeBtn = box.querySelector('.ppq-remove-btn');

		if (searchInput && resultsContainer) {
			var searchTimeout;

			// Load recent quizzes on focus.
			searchInput.addEventListener('focus', function() {
				if (this.value.length < 2) {
					loadQuizzes(resultsContainer, resultsHeading, null, box, modal, lessonId);
				}
			});

			// Search on input.
			searchInput.addEventListener('input', function() {
				var query = this.value.trim();
				clearTimeout(searchTimeout);

				if (query.length < 2) {
					loadQuizzes(resultsContainer, resultsHeading, null, box, modal, lessonId);
					return;
				}

				searchTimeout = setTimeout(function() {
					loadQuizzes(resultsContainer, resultsHeading, query, box, modal, lessonId);
				}, 300);
			});

			// Auto-load recent quizzes.
			loadQuizzes(resultsContainer, resultsHeading, null, box, modal, lessonId);
		}

		if (removeBtn) {
			removeBtn.addEventListener('click', function(e) {
				e.preventDefault();
				removeQuizFromLesson(box, modal, lessonId);
			});
		}
	}

	/**
	 * Load quizzes (recent or search)
	 */
	function loadQuizzes(container, heading, query, box, modal, lessonId) {
		heading.textContent = query ? 'Search Results' : 'Recent Quizzes';
		container.innerHTML = '<div class="ppq-loading">Loading...</div>';

		var url = config.restUrl + 'ppq/v1/tutorlms/quizzes/search';
		url += query ? '?search=' + encodeURIComponent(query) : '?recent=1';

		fetch(url, {
			headers: {
				'X-WP-Nonce': config.restNonce
			}
		})
		.then(function(response) { return response.json(); })
		.then(function(data) {
			log('Quiz search response:', data);
			if (data.success && data.quizzes && data.quizzes.length > 0) {
				renderQuizResults(data.quizzes, container, box, modal, lessonId);
			} else {
				container.innerHTML = '<div class="ppq-no-results">No quizzes found</div>';
			}
		})
		.catch(function(err) {
			log('Quiz search error:', err);
			container.innerHTML = '<div class="ppq-error">Error loading quizzes</div>';
		});
	}

	/**
	 * Render quiz results
	 */
	function renderQuizResults(quizzes, container, box, modal, lessonId) {
		container.innerHTML = '';

		quizzes.forEach(function(quiz) {
			var item = document.createElement('div');
			item.className = 'ppq-quiz-item';
			item.innerHTML = '\
				<span class="ppq-quiz-id">' + quiz.id + '</span>\
				<span class="ppq-quiz-title">' + escapeHtml(quiz.title) + '</span>\
			';

			item.addEventListener('click', function() {
				selectQuiz(quiz, box, modal, lessonId);
			});

			container.appendChild(item);
		});
	}

	/**
	 * Select a quiz
	 */
	function selectQuiz(quiz, box, modal, lessonId) {
		log('Selecting quiz:', quiz.id, 'for lesson:', lessonId);

		// Save the association.
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
							<a href="' + config.adminUrl + 'admin.php?page=pressprimer-quiz-edit&id=' + quiz.id + '" target="_blank" class="ppq-action-btn ppq-edit-btn" title="Edit Quiz">\
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
					removeQuizFromLesson(box, modal, lessonId);
				});

				log('Quiz selected successfully');
			} else {
				alert('Failed to save quiz. Please try again.');
			}
		});
	}

	/**
	 * Remove quiz from lesson
	 */
	function removeQuizFromLesson(box, modal, lessonId) {
		log('Removing quiz from lesson:', lessonId);

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
				setupBoxHandlers(box, modal, lessonId);

				log('Quiz removed successfully');
			}
		});
	}

	/**
	 * Save lesson quiz association
	 */
	function saveLessonQuiz(lessonId, quizId, callback) {
		log('Saving lesson quiz - Lesson:', lessonId, 'Quiz:', quizId);

		fetch(config.restUrl + 'ppq/v1/tutorlms/lesson-quiz', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.restNonce
			},
			body: JSON.stringify({
				course_id: courseId,
				lesson_id: lessonId,
				quiz_id: quizId
			})
		})
		.then(function(response) { return response.json(); })
		.then(function(data) {
			log('Save response:', data);
			callback(data.success, data);
		})
		.catch(function(err) {
			log('Save error:', err);
			callback(false);
		});
	}

	/**
	 * Escape HTML entities
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Inject CSS styles
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
