/**
 * Data Tools Tab — Reset Quiz Progress
 *
 * Admin tooling to delete quiz attempts for a user, a quiz, or a user on a
 * quiz: preview → typed confirmation → chunked deletion, with an operation
 * log. Read-only settings tab (no global Save button); all actions run through
 * the /ppq/v1/tools/reset-progress endpoints (feature 006).
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Radio,
	Select,
	Button,
	Input,
	Progress,
	Alert,
	Typography,
	Space,
	Table,
	Empty,
	Spin,
	message,
} from 'antd';
import { DeleteOutlined, WarningOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Format an integer for display in the current locale.
 *
 * @param {number} value Numeric value.
 * @return {string} Localized number.
 */
const formatNumber = (value) => Number(value || 0).toLocaleString();

/**
 * Format a stored datetime ("Y-m-d H:i:s") as a locale date, or '' if empty.
 *
 * @param {string} value Datetime string.
 * @return {string} Localized date.
 */
const formatDate = (value) => {
	if (!value) {
		return '';
	}
	const date = new Date(String(value).replace(' ', 'T'));
	return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
};

const DataToolsTab = () => {
	const [scope, setScope] = useState('user_quiz');
	const [userId, setUserId] = useState(null);
	const [userOptions, setUserOptions] = useState([]);
	const [userSearching, setUserSearching] = useState(false);
	const [quizId, setQuizId] = useState(null);
	const [quizOptions, setQuizOptions] = useState([]);

	const [preview, setPreview] = useState(null);
	const [previewing, setPreviewing] = useState(false);
	const [confirmText, setConfirmText] = useState('');

	const [deleting, setDeleting] = useState(false);
	const [progress, setProgress] = useState({ deleted: 0, total: 0 });
	const [completed, setCompleted] = useState(null);

	const [log, setLog] = useState([]);
	const [logLoading, setLogLoading] = useState(false);

	const searchTimer = useRef(null);

	const needsUser = 'user' === scope || 'user_quiz' === scope;
	const needsQuiz = 'quiz' === scope || 'user_quiz' === scope;
	const selectionReady = (!needsUser || !!userId) && (!needsQuiz || !!quizId);

	// Quiz options (load once).
	useEffect(() => {
		(async () => {
			try {
				const res = await apiFetch({ path: '/ppq/v1/statistics/quiz-options' });
				if (res && res.success) {
					setQuizOptions(res.data || []);
				}
			} catch (err) {
				// Leave empty; the selector simply shows no options.
			}
		})();
	}, []);

	// Operation log.
	const loadLog = useCallback(async () => {
		setLogLoading(true);
		try {
			const res = await apiFetch({ path: '/ppq/v1/tools/reset-progress/log' });
			setLog(Array.isArray(res) ? res : []);
		} catch (err) {
			setLog([]);
		}
		setLogLoading(false);
	}, []);

	useEffect(() => {
		loadLog();
	}, [loadLog]);

	// User search (server-side, debounced).
	const fetchUsers = useCallback(async (term) => {
		setUserSearching(true);
		try {
			const res = await apiFetch({
				path: `/ppq/v1/tools/users?search=${encodeURIComponent(term || '')}`,
			});
			setUserOptions(
				Array.isArray(res) ? res.map((u) => ({ value: u.id, label: u.label })) : []
			);
		} catch (err) {
			setUserOptions([]);
		}
		setUserSearching(false);
	}, []);

	useEffect(() => {
		fetchUsers('');
	}, [fetchUsers]);

	const onUserSearch = (term) => {
		if (searchTimer.current) {
			clearTimeout(searchTimer.current);
		}
		searchTimer.current = setTimeout(() => fetchUsers(term), 300);
	};

	// Any change to the selection invalidates a prior preview, which disables
	// the Delete button until the admin previews the new selection.
	useEffect(() => {
		setPreview(null);
		setConfirmText('');
		setCompleted(null);
		setProgress({ deleted: 0, total: 0 });
	}, [scope, userId, quizId]);

	const handlePreview = async () => {
		setPreviewing(true);
		setCompleted(null);
		try {
			const params = new URLSearchParams();
			if (needsUser && userId) {
				params.set('user_id', userId);
			}
			if (needsQuiz && quizId) {
				params.set('quiz_id', quizId);
			}
			const res = await apiFetch({
				path: `/ppq/v1/tools/reset-progress/preview?${params.toString()}`,
			});
			setPreview(res);
			setConfirmText('');
		} catch (err) {
			message.error(err.message || __('Could not load the preview.', 'pressprimer-quiz'));
		}
		setPreviewing(false);
	};

	// The string the admin must type, and the noun used to prompt for it.
	const expectedToken = () => {
		if (!preview) {
			return '';
		}
		if (preview.quiz_id) {
			return preview.quiz_title || String(preview.quiz_id);
		}
		return preview.user_login || (preview.user_id ? String(preview.user_id) : '');
	};

	const tokenNoun = () => {
		if (preview && preview.quiz_id) {
			return __('quiz title', 'pressprimer-quiz');
		}
		return __('username', 'pressprimer-quiz');
	};

	const scopeLabel = () => {
		if (!preview) {
			return '';
		}
		if (preview.quiz_id) {
			return preview.quiz_title || `#${preview.quiz_id}`;
		}
		return preview.user_display || preview.user_login || `#${preview.user_id}`;
	};

	const canDelete =
		!!preview &&
		preview.attempts_total > 0 &&
		'' !== confirmText.trim() &&
		confirmText.trim() === expectedToken() &&
		!deleting;

	const handleDelete = async () => {
		if (!canDelete) {
			return;
		}
		setDeleting(true);
		setCompleted(null);

		const total = preview.attempts_total;
		setProgress({ deleted: 0, total });

		const base = { confirm_token: confirmText.trim() };
		if (needsUser && userId) {
			base.user_id = userId;
		}
		if (needsQuiz && quizId) {
			base.quiz_id = quizId;
		}

		let cursor = 0;
		let deleted = 0;
		let remaining = total;
		let totals = { attempts: 0, items: 0 };

		try {
			do {
				const res = await apiFetch({
					path: '/ppq/v1/tools/reset-progress',
					method: 'POST',
					data: { ...base, cursor },
				});
				deleted += res.deleted;
				cursor = res.cursor;
				remaining = res.remaining;
				totals = res.totals || totals;
				setProgress({ deleted, total });
			} while (remaining > 0);

			setCompleted(totals);
			message.success(__('Quiz progress reset.', 'pressprimer-quiz'));
			setPreview(null);
			setConfirmText('');
			loadLog();
		} catch (err) {
			message.error(
				err.message || __('The reset could not be completed.', 'pressprimer-quiz')
			);
		}

		setDeleting(false);
	};

	const logColumns = [
		{
			title: __('When', 'pressprimer-quiz'),
			dataIndex: 'timestamp',
			key: 'timestamp',
			render: (value) => formatDate(value),
		},
		{
			title: __('By', 'pressprimer-quiz'),
			key: 'initiator',
			render: (_, row) => {
				if (row.initiator_name) {
					return row.initiator_name;
				}
				/* translators: %d: user id. */
				return sprintf(__('User #%d', 'pressprimer-quiz'), row.initiator_id || 0);
			},
		},
		{
			title: __('Scope', 'pressprimer-quiz'),
			key: 'scope',
			render: (_, row) => {
				const parts = [];
				if (row.quiz_id) {
					parts.push(row.quiz_label || `#${row.quiz_id}`);
				}
				if (row.user_id) {
					parts.push(row.user_label || `#${row.user_id}`);
				}
				return parts.join(' · ');
			},
		},
		{
			title: __('Deleted', 'pressprimer-quiz'),
			key: 'deleted',
			render: (_, row) =>
				sprintf(
					/* translators: 1: attempt count, 2: answer-record count. */
					__('%1$s attempts, %2$s records', 'pressprimer-quiz'),
					formatNumber(row.attempts),
					formatNumber(row.items)
				),
		},
	];

	return (
		<div>
			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Reset Quiz Progress', 'pressprimer-quiz')}
				</Title>
				<Paragraph className="ppq-settings-section-description">
					{__(
						'Permanently delete quiz attempts for a user, a quiz, or a single user on a quiz. Quiz content, questions, and banks are never affected.',
						'pressprimer-quiz'
					)}
				</Paragraph>

				<div className="ppq-settings-field">
					<Text strong style={{ display: 'block', marginBottom: 8 }}>
						{__('Scope', 'pressprimer-quiz')}
					</Text>
					<Radio.Group value={scope} onChange={(e) => setScope(e.target.value)}>
						<Space direction="vertical">
							<Radio value="user">{__('By user', 'pressprimer-quiz')}</Radio>
							<Radio value="quiz">{__('By quiz', 'pressprimer-quiz')}</Radio>
							<Radio value="user_quiz">{__('By user + quiz', 'pressprimer-quiz')}</Radio>
						</Space>
					</Radio.Group>
				</div>

				{needsUser && (
					<div className="ppq-settings-field">
						<Text style={{ display: 'block', marginBottom: 4 }}>
							{__('User', 'pressprimer-quiz')}
						</Text>
						<Select
							showSearch
							allowClear
							value={userId}
							placeholder={__('Search by name, login, or email', 'pressprimer-quiz')}
							filterOption={false}
							onSearch={onUserSearch}
							onChange={(value) => setUserId(value || null)}
							notFoundContent={userSearching ? <Spin size="small" /> : null}
							options={userOptions}
							style={{ width: 360, maxWidth: '100%' }}
						/>
					</div>
				)}

				{needsQuiz && (
					<div className="ppq-settings-field">
						<Text style={{ display: 'block', marginBottom: 4 }}>
							{__('Quiz', 'pressprimer-quiz')}
						</Text>
						<Select
							showSearch
							allowClear
							value={quizId}
							placeholder={__('Select a quiz', 'pressprimer-quiz')}
							optionFilterProp="label"
							onChange={(value) => setQuizId(value || null)}
							options={quizOptions.map((q) => ({ value: q.id, label: q.title }))}
							style={{ width: 360, maxWidth: '100%' }}
						/>
					</div>
				)}

				<div className="ppq-settings-field">
					<Button onClick={handlePreview} loading={previewing} disabled={!selectionReady || deleting}>
						{__('Preview', 'pressprimer-quiz')}
					</Button>
				</div>
			</div>

			{preview && (
				<div className="ppq-settings-section">
					<Title level={5} className="ppq-settings-section-title">
						{__('Preview', 'pressprimer-quiz')}
					</Title>

					{0 === preview.attempts_total ? (
						<Alert
							type="info"
							showIcon
							message={__('No attempts match this selection.', 'pressprimer-quiz')}
						/>
					) : (
						<div>
							<Paragraph>
								{sprintf(
									/* translators: 1: total attempts, 2: completed, 3: in progress. */
									__(
										'Will delete %1$s attempts (%2$s completed, %3$s in progress).',
										'pressprimer-quiz'
									),
									formatNumber(preview.attempts_total),
									formatNumber(preview.attempts_completed),
									formatNumber(preview.attempts_in_progress)
								)}
								{preview.attempts_abandoned > 0 &&
									` ${sprintf(
										/* translators: %s: abandoned attempt count. */
										__('Includes %s abandoned.', 'pressprimer-quiz'),
										formatNumber(preview.attempts_abandoned)
									)}`}
							</Paragraph>
							<Paragraph>
								{sprintf(
									/* translators: %s: answer-record (attempt item) count. */
									__('%s answer records.', 'pressprimer-quiz'),
									formatNumber(preview.items_total)
								)}
								{preview.guest_attempts > 0 &&
									` ${sprintf(
										/* translators: %s: guest attempt count. */
										__('Includes %s guest attempts.', 'pressprimer-quiz'),
										formatNumber(preview.guest_attempts)
									)}`}
							</Paragraph>
							{preview.date_from && preview.date_to && (
								<Paragraph>
									{sprintf(
										/* translators: 1: start date, 2: end date. */
										__('From %1$s to %2$s.', 'pressprimer-quiz'),
										formatDate(preview.date_from),
										formatDate(preview.date_to)
									)}
								</Paragraph>
							)}

							{preview.addon_lines && preview.addon_lines.length > 0 && (
								<ul>
									{preview.addon_lines.map((line, index) => (
										<li key={index}>{line}</li>
									))}
								</ul>
							)}

							<Alert
								type="warning"
								showIcon
								icon={<WarningOutlined />}
								style={{ marginBottom: 16 }}
								message={__(
									'This permanently deletes the attempts above and cannot be undone.',
									'pressprimer-quiz'
								)}
							/>

							<div className="ppq-settings-field">
								<label htmlFor="ppq-reset-confirm" style={{ display: 'block', marginBottom: 4 }}>
									{sprintf(
										/* translators: %s: "quiz title" or "username". */
										__('Type the %s to confirm:', 'pressprimer-quiz'),
										tokenNoun()
									)}
									{' '}
									<Text code>{expectedToken()}</Text>
								</label>
								<Input
									id="ppq-reset-confirm"
									value={confirmText}
									onChange={(e) => setConfirmText(e.target.value)}
									disabled={deleting}
									style={{ width: 360, maxWidth: '100%' }}
									aria-label={__('Confirmation text', 'pressprimer-quiz')}
								/>
							</div>

							<div className="ppq-settings-field">
								<Button
									type="primary"
									danger
									icon={<DeleteOutlined />}
									disabled={!canDelete}
									loading={deleting}
									onClick={handleDelete}
								>
									{sprintf(
										/* translators: 1: attempt count, 2: scope label. */
										__('Delete %1$s attempts for "%2$s"', 'pressprimer-quiz'),
										formatNumber(preview.attempts_total),
										scopeLabel()
									)}
								</Button>
							</div>
						</div>
					)}
				</div>
			)}

			{deleting && (
				<div className="ppq-settings-section">
					<Progress
						percent={progress.total ? Math.round((progress.deleted / progress.total) * 100) : 0}
						status="active"
					/>
					<Text type="secondary">
						{sprintf(
							/* translators: 1: deleted so far, 2: total. */
							__('Deleted %1$s of %2$s attempts…', 'pressprimer-quiz'),
							formatNumber(progress.deleted),
							formatNumber(progress.total)
						)}
					</Text>
				</div>
			)}

			{completed && (
				<div className="ppq-settings-section">
					<Alert
						type="success"
						showIcon
						message={__('Reset complete', 'pressprimer-quiz')}
						description={sprintf(
							/* translators: 1: attempt count, 2: answer-record count. */
							__('Deleted %1$s attempts and %2$s answer records.', 'pressprimer-quiz'),
							formatNumber(completed.attempts),
							formatNumber(completed.items)
						)}
					/>
				</div>
			)}

			<div className="ppq-settings-section">
				<Title level={4} className="ppq-settings-section-title">
					{__('Recent reset operations', 'pressprimer-quiz')}
				</Title>
				{log.length > 0 ? (
					<Table
						size="small"
						loading={logLoading}
						columns={logColumns}
						dataSource={log.map((entry, index) => ({ ...entry, key: index }))}
						pagination={false}
					/>
				) : (
					<Empty description={__('No reset operations yet.', 'pressprimer-quiz')} />
				)}
			</div>
		</div>
	);
};

export default DataToolsTab;
