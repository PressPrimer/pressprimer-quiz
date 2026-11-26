/**
 * Bank Questions Panel
 *
 * A saved bank's questions in the dashboard bank editor (R13): the list,
 * with search and paging, adding questions the author may use (the quiz
 * editor's picker) and removing them, through the bank membership routes.
 * A bank shared with the author is a read-only list. wp-admin keeps its bank
 * detail page in 3.2.
 *
 * Fires ppq-bank-questions-added and ppq-bank-questions-removed on document
 * with { bankId, questionIds }.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Alert,
	Button,
	Card,
	Input,
	message,
	Popconfirm,
	Space,
	Table,
	Tabs,
	Tag,
	Tooltip,
	Typography,
} from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { useEditorContext } from '../../editors/EditorContext';
import { emitEditorEvent, toId } from '../../editors/events';
import QuestionPickerModal from '../../editors/QuestionPickerModal';
import { debugError } from '../../utils/debug';

const { Text } = Typography;
const { Search } = Input;

const PAGE_SIZE = 20;

/**
 * Bank Questions Panel
 *
 * @param {Object}  props         Component props.
 * @param {number}  props.bankId  Bank ID.
 * @param {boolean} props.canEdit Whether the author may change the bank.
 * @return {JSX.Element} The panel.
 */
const BankQuestionsPanel = ({ bankId, canEdit }) => {
	const { nav } = useEditorContext();
	const [rows, setRows] = useState([]);
	const [total, setTotal] = useState(0);
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');
	const [searchInput, setSearchInput] = useState('');
	const [loading, setLoading] = useState(true);
	const [failed, setFailed] = useState(false);
	const [pickerOpen, setPickerOpen] = useState(false);
	// A new picker for each opening, so it starts from its defaults.
	const [pickerKey, setPickerKey] = useState(0);

	/**
	 * Load a page of the bank's questions.
	 *
	 * @param {number} nextPage   Page to load.
	 * @param {string} nextSearch Search text.
	 */
	const load = async (nextPage, nextSearch) => {
		setLoading(true);
		try {
			const params = new URLSearchParams({
				bank_id: String(bankId),
				status: 'all',
				per_page: String(PAGE_SIZE),
				page: String(nextPage),
			});
			if (nextSearch) {
				params.append('search', nextSearch);
			}
			const response = await apiFetch({ path: `/ppq/v1/questions?${params.toString()}` });
			setRows((response.questions || []).map((row) => ({ ...row, id: Number(row.id) })));
			setTotal(Number(response.total) || 0);
			setPage(nextPage);
			setFailed(false);
		} catch (error) {
			debugError('Failed to load the bank questions:', error);
			setFailed(true);
		} finally {
			setLoading(false);
		}
	};

	useEffect(() => {
		load(1, '');
		// Load when the bank changes; paging and search load themselves.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [bankId]);

	/**
	 * Add the questions picked in the dialog.
	 *
	 * @param {number[]} questionIds Picked question IDs.
	 */
	const handleAdd = async (questionIds) => {
		try {
			const response = await apiFetch({
				path: `/ppq/v1/banks/${bankId}/questions`,
				method: 'POST',
				data: { question_ids: questionIds },
			});
			const added = (response.added || []).map(toId).filter(Boolean);

			message.success(sprintf(
				/* translators: %d: number of questions added to the bank. */
				_n('%d question added to the bank.', '%d questions added to the bank.', added.length, 'pressprimer-quiz'),
				added.length
			));
			setPickerOpen(false);

			if (added.length) {
				emitEditorEvent('ppq-bank-questions-added', { bankId: toId(bankId), questionIds: added });
			}
			load(page, search);
		} catch (error) {
			debugError('Failed to add questions to the bank:', error);
			message.error(error.message || __('Failed to add questions', 'pressprimer-quiz'));
		}
	};

	/**
	 * Remove a question from the bank (the question itself is kept).
	 *
	 * @param {number} questionId Question ID.
	 */
	const handleRemove = async (questionId) => {
		try {
			await apiFetch({
				path: `/ppq/v1/banks/${bankId}/questions/${questionId}`,
				method: 'DELETE',
			});
			message.success(__('Question removed from the bank.', 'pressprimer-quiz'));
			emitEditorEvent('ppq-bank-questions-removed', { bankId: toId(bankId), questionIds: [toId(questionId)] });

			// The last row of a later page: go back a page.
			load(rows.length === 1 && page > 1 ? page - 1 : page, search);
		} catch (error) {
			debugError('Failed to remove the question from the bank:', error);
			message.error(error.message || __('Failed to remove question', 'pressprimer-quiz'));
		}
	};

	const difficultyLabels = {
		beginner: __('Beginner', 'pressprimer-quiz'),
		intermediate: __('Intermediate', 'pressprimer-quiz'),
		advanced: __('Advanced', 'pressprimer-quiz'),
		expert: __('Expert', 'pressprimer-quiz'),
	};

	// A read-only bank whose questions the author didn't write has no actions.
	const showActions = canEdit || rows.some((row) => row.can_edit && nav.editUrl('question', row.id));

	const columns = [
		{
			title: __('Question', 'pressprimer-quiz'),
			dataIndex: 'stem',
			key: 'stem',
			ellipsis: { showTitle: false },
			render: (text, record) => (
				<Tooltip placement="topLeft" title={record.stem_full || text}>
					<span>{text}</span>
				</Tooltip>
			),
		},
		{
			title: __('Type', 'pressprimer-quiz'),
			dataIndex: 'type',
			key: 'type',
			width: 80,
			render: (type) => (type ? type.toUpperCase() : ''),
		},
		{
			title: __('Difficulty', 'pressprimer-quiz'),
			dataIndex: 'difficulty',
			key: 'difficulty',
			width: 120,
			render: (difficulty) => difficultyLabels[difficulty] || difficulty || '',
		},
		{
			title: __('Status', 'pressprimer-quiz'),
			dataIndex: 'status',
			key: 'status',
			width: 90,
			render: (status) => (
				'draft' === status ? <Tag color="gold">{__('Draft', 'pressprimer-quiz')}</Tag> : ''
			),
		},
		showActions && {
			title: __('Actions', 'pressprimer-quiz'),
			key: 'actions',
			width: 100,
			render: (_, record) => (
				<Space size="small">
					{/* The author's own questions (all, for managers), when the host has question URLs. */}
					{record.can_edit && nav.editUrl('question', record.id) && (
						<Tooltip title={__('Edit question', 'pressprimer-quiz')}>
							<Button
								type="text"
								icon={<EditOutlined />}
								aria-label={__('Edit question', 'pressprimer-quiz')}
								onClick={() => window.open(nav.editUrl('question', record.id), '_blank')}
							/>
						</Tooltip>
					)}
					{canEdit && (
						<Popconfirm
							title={__('Remove this question from the bank?', 'pressprimer-quiz')}
							description={__('The question itself is kept.', 'pressprimer-quiz')}
							onConfirm={() => handleRemove(record.id)}
							okText={__('Remove', 'pressprimer-quiz')}
							cancelText={__('Cancel', 'pressprimer-quiz')}
						>
							<Tooltip title={__('Remove from bank', 'pressprimer-quiz')}>
								<Button
									type="text"
									danger
									icon={<DeleteOutlined />}
									aria-label={__('Remove from bank', 'pressprimer-quiz')}
								/>
							</Tooltip>
						</Popconfirm>
					)}
				</Space>
			),
		},
	].filter(Boolean);

	const list = (
		<Space direction="vertical" style={{ width: '100%' }} size="middle">
			{!canEdit && (
				<Alert
					type="info"
					showIcon
					message={__('This bank is shared with you. You can use its questions in your quizzes, but only its owner can change it.', 'pressprimer-quiz')}
				/>
			)}

			<div className="ppq-bank-questions-toolbar" style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', justifyContent: 'space-between' }}>
				<Search
					placeholder={__('Search this bank’s questions', 'pressprimer-quiz')}
					allowClear
					value={searchInput}
					onChange={(event) => setSearchInput(event.target.value)}
					onSearch={(value) => {
						setSearch(value);
						load(1, value);
					}}
					style={{ maxWidth: 320 }}
				/>
				{canEdit && (
					<Space wrap>
						{typeof nav.create === 'function' && (
							<Button
								icon={<PlusOutlined />}
								onClick={() => nav.create('question', { bankIds: [toId(bankId)] })}
							>
								{__('New question', 'pressprimer-quiz')}
							</Button>
						)}
						<Button
							type="primary"
							icon={<PlusOutlined />}
							className="ppq-bank-add-questions-button"
							onClick={() => {
								setPickerKey((key) => key + 1);
								setPickerOpen(true);
							}}
						>
							{__('Add existing questions', 'pressprimer-quiz')}
						</Button>
					</Space>
				)}
			</div>

			{failed ? (
				<Alert type="error" showIcon message={__('This bank’s questions couldn’t be loaded.', 'pressprimer-quiz')} />
			) : (
				<Table
					rowKey="id"
					loading={loading}
					dataSource={rows}
					columns={columns}
					scroll={{ x: 'max-content' }}
					pagination={{
						current: page,
						pageSize: PAGE_SIZE,
						total,
						showSizeChanger: false,
						hideOnSinglePage: true,
						onChange: (nextPage) => load(nextPage, search),
					}}
					locale={{
						emptyText: search
							? __('No questions in this bank match your search.', 'pressprimer-quiz')
							: __('No questions in this bank yet.', 'pressprimer-quiz'),
					}}
				/>
			)}

			<Text type="secondary">
				{sprintf(
					/* translators: %d: number of questions in the bank (or matching the search). */
					_n('%d question', '%d questions', total, 'pressprimer-quiz'),
					total
				)}
			</Text>

			{canEdit && (
				<QuestionPickerModal
					key={pickerKey}
					open={pickerOpen}
					onCancel={() => setPickerOpen(false)}
					onAdd={handleAdd}
					query={{ exclude_bank_id: bankId }}
					hideBankId={bankId}
				/>
			)}
		</Space>
	);

	// Phase 4 adds the AI generation tab here (feature 3).
	const tabs = [
		{ key: 'questions', label: __('Questions', 'pressprimer-quiz'), children: list },
	];

	return (
		<Card className="ppq-bank-questions" title={__('Questions', 'pressprimer-quiz')} style={{ marginTop: 20 }}>
			{tabs.length > 1 ? <Tabs items={tabs} /> : list}
		</Card>
	);
};

export default BankQuestionsPanel;
