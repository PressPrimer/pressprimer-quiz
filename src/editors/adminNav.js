/**
 * wp-admin navigation adapter
 *
 * The editors navigate only through a `nav` adapter. This one reproduces the
 * wp-admin editor pages' navigations exactly; on the front-end dashboard the
 * host supplies its own adapter, so the editors never build a wp-admin URL
 * there.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

const PAGES = {
	quiz: 'pressprimer-quiz-quizzes',
	question: 'pressprimer-quiz-questions',
	bank: 'pressprimer-quiz-banks',
};

const EDIT_ARGS = {
	quiz: 'quiz',
	question: 'question',
	bank: 'bank_id',
};

/**
 * Build the wp-admin navigation adapter.
 *
 * @param {string} adminUrl The admin URL with a trailing slash, or '' for
 *                          URLs relative to the current wp-admin page.
 * @return {Object} nav adapter: list, edit, afterCreate, editUrl, back.
 */
export function createAdminNav(adminUrl = '') {
	const page = (type) => `${adminUrl}admin.php?page=${PAGES[type]}`;
	const go = (url) => {
		window.location.href = url;
	};

	const editUrl = (type, id) => `${page(type)}&action=edit&${EDIT_ARGS[type]}=${id}`;

	const edit = (type, id, { replace = false } = {}) => {
		if (replace) {
			window.history.replaceState({}, '', editUrl(type, id));
			return;
		}
		go(editUrl(type, id));
	};

	return {
		/**
		 * Go to an entity's list. `saved` shows the list's saved notice.
		 *
		 * @param {string}  type          'quiz', 'question' or 'bank'.
		 * @param {Object}  options       Options.
		 * @param {boolean} options.saved Show the saved notice.
		 */
		list(type, { saved = false } = {}) {
			go(page(type) + (saved ? '&saved=1' : ''));
		},

		/**
		 * Go to an entity's editor; `replace` swaps the URL without a reload
		 * (used after the editor's own first save).
		 *
		 * Takes the entity type, its ID and { replace }.
		 */
		edit,

		/**
		 * Where an editor goes after it creates an entity and leaves: a new
		 * question returns to the list with the saved notice, a new bank
		 * opens its detail page.
		 *
		 * @param {string} type Entity type.
		 * @param {number} id   New entity ID.
		 */
		afterCreate(type, id) {
			if ('bank' === type) {
				go(`${page('bank')}&action=view&bank_id=${id}&message=bank_created`);
				return;
			}
			if ('question' === type) {
				go(`${page('question')}&saved=1`);
				return;
			}
			edit(type, id, { replace: true });
		},

		editUrl,

		back() {
			window.history.back();
		},
	};
}
