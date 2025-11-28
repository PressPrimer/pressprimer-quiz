/**
 * Quiz Rules Panel Component (Dynamic Mode)
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	Button,
	Select,
	InputNumber,
	Checkbox,
	Space,
	Typography,
	Row,
	Col,
	Empty,
	Tag,
	Tooltip,
	message,
} from 'antd';
import {
	PlusOutlined,
	DeleteOutlined,
	HolderOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

const { Text, Title } = Typography;

/**
 * Rules Panel Component (Dynamic Quiz Mode)
 *
 * @param {Object} props Component props
 * @param {number} props.quizId Quiz ID
 * @param {string} props.generationMode Generation mode
 */
const RulesPanel = ({ quizId, generationMode }) => {
	const [rules, setRules] = useState([]);
	const [loading, setLoading] = useState(false);
	const [banks, setBanks] = useState([]);
	const [categories, setCategories] = useState([]);
	const [tags, setTags] = useState([]);

	const difficultyOptions = [
		{ label: __('Beginner', 'pressprimer-quiz'), value: 'beginner' },
		{ label: __('Intermediate', 'pressprimer-quiz'), value: 'intermediate' },
		{ label: __('Advanced', 'pressprimer-quiz'), value: 'advanced' },
		{ label: __('Expert', 'pressprimer-quiz'), value: 'expert' },
	];

	// Load initial data
	useEffect(() => {
		if (quizId && generationMode === 'dynamic') {
			loadRules();
			loadFilterOptions();
		}
	}, [quizId, generationMode]);

	/**
	 * Load quiz rules
	 */
	const loadRules = async () => {
		try {
			setLoading(true);
			console.log('Loading rules for quiz:', quizId);
			const response = await apiFetch({ path: `/ppq/v1/quizzes/${quizId}/rules` });
			console.log('Rules loaded:', response);
			setRules(response || []);
		} catch (error) {
			console.error('Failed to load quiz rules:', error);
			message.error(__('Failed to load quiz rules', 'pressprimer-quiz'));
			setRules([]);
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Load filter options (banks, categories, tags)
	 */
	const loadFilterOptions = async () => {
		try {
			// Load banks
			const banksResponse = await apiFetch({ path: '/ppq/v1/banks' });
			setBanks(banksResponse || []);

			// Load categories
			const categoriesResponse = await apiFetch({ path: '/ppq/v1/taxonomies?type=category' });
			setCategories(categoriesResponse || []);

			// Load tags
			const tagsResponse = await apiFetch({ path: '/ppq/v1/taxonomies?type=tag' });
			setTags(tagsResponse || []);
		} catch (error) {
			console.error('Failed to load filter options:', error);
			message.error(__('Failed to load filter options', 'pressprimer-quiz'));
		}
	};

	/**
	 * Add new rule
	 */
	const handleAddRule = async () => {
		const newRule = {
			id: `temp-${Date.now()}`,
			bank_id: null,
			category_ids: [],
			tag_ids: [],
			difficulties: [],
			question_count: 10,
			matching_count: 0,
		};

		setRules([...rules, newRule]);

		// Auto-save the new rule immediately with the rule data
		saveRuleData(newRule.id, newRule);
	};

	/**
	 * Update rule field
	 */
	const handleUpdateRule = async (ruleId, field, value) => {
		setRules(rules.map(rule =>
			rule.id === ruleId ? { ...rule, [field]: value } : rule
		));

		// Auto-save if this is an existing rule (not a temp ID)
		if (!String(ruleId).startsWith('temp-')) {
			await saveRule(ruleId, { [field]: value });
		}
	};

	/**
	 * Remove rule
	 */
	const handleRemoveRule = async (ruleId) => {
		// Delete from server if it's an existing rule
		if (!String(ruleId).startsWith('temp-')) {
			try {
				await apiFetch({
					path: `/ppq/v1/quizzes/${quizId}/rules/${ruleId}`,
					method: 'DELETE',
				});
				message.success(__('Rule deleted', 'pressprimer-quiz'));
			} catch (error) {
				console.error('Failed to delete rule:', error);
				message.error(__('Failed to delete rule', 'pressprimer-quiz'));
				return;
			}
		}

		setRules(rules.filter(rule => rule.id !== ruleId));
	};

	/**
	 * Save rule data directly (used when rule might not be in state yet)
	 */
	const saveRuleData = async (ruleId, ruleObject, updates = {}) => {
		const ruleData = {
			bank_id: ruleObject.bank_id,
			category_ids: ruleObject.category_ids,
			tag_ids: ruleObject.tag_ids,
			difficulties: ruleObject.difficulties,
			question_count: ruleObject.question_count,
			...updates,
		};

		console.log('Saving rule:', ruleId, 'Data:', ruleData);

		try {
			// If it's a temp rule, create it
			if (String(ruleId).startsWith('temp-')) {
				console.log('Creating new rule...');
				const response = await apiFetch({
					path: `/ppq/v1/quizzes/${quizId}/rules`,
					method: 'POST',
					data: ruleData,
				});

				console.log('Rule created, response:', response);

				// Replace temp ID with real ID
				setRules(currentRules => currentRules.map(r =>
					r.id === ruleId ? { ...r, id: response.id } : r
				));

				// Reload to get matching count
				loadRules();
			} else {
				// Update existing rule
				console.log('Updating existing rule...');
				await apiFetch({
					path: `/ppq/v1/quizzes/${quizId}/rules/${ruleId}`,
					method: 'PUT',
					data: ruleData,
				});

				console.log('Rule updated');

				// Reload to get updated matching count
				loadRules();
			}
		} catch (error) {
			console.error('Failed to save rule:', error);
			message.error(__('Failed to save rule', 'pressprimer-quiz'));
		}
	};

	/**
	 * Save a single rule (looks up from state)
	 */
	const saveRule = async (ruleId, updates = {}) => {
		const rule = rules.find(r => r.id === ruleId);
		if (!rule) {
			console.log('Rule not found for saving:', ruleId);
			return;
		}

		await saveRuleData(ruleId, rule, updates);
	};

	/**
	 * Handle drag end
	 */
	const handleDragEnd = async (result) => {
		if (!result.destination) {
			return;
		}

		const reorderedRules = Array.from(rules);
		const [removed] = reorderedRules.splice(result.source.index, 1);
		reorderedRules.splice(result.destination.index, 0, removed);

		setRules(reorderedRules);

		// Save new order to server
		try {
			const ruleOrder = reorderedRules.map(r => r.id);
			await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/rules/reorder`,
				method: 'POST',
				data: { rule_order: ruleOrder },
			});
		} catch (error) {
			console.error('Failed to save rule order:', error);
			message.error(__('Failed to save rule order', 'pressprimer-quiz'));
		}
	};

	/**
	 * Calculate total expected questions
	 */
	const getTotalQuestions = () => {
		return rules.reduce((sum, rule) => sum + (parseInt(rule.question_count) || 0), 0);
	};

	/**
	 * Render rule card
	 */
	const renderRuleCard = (rule, index) => {
		const selectedBank = banks.find(b => b.id === rule.bank_id);
		const selectedCategories = categories.filter(c => rule.category_ids.includes(c.id));
		const selectedTags = tags.filter(t => rule.tag_ids.includes(t.id));

		return (
			<Card
				key={rule.id}
				size="small"
				style={{ marginBottom: 16, cursor: 'move' }}
				title={
					<Space>
						<HolderOutlined />
						<Text strong>{__('Rule', 'pressprimer-quiz')} {index + 1}</Text>
						{rule.matching_count !== undefined && (
							<Tag color="blue">{rule.matching_count} {__('questions match', 'pressprimer-quiz')}</Tag>
						)}
					</Space>
				}
				extra={
					<Button
						type="text"
						danger
						size="small"
						icon={<DeleteOutlined />}
						onClick={() => handleRemoveRule(rule.id)}
					>
						{__('Remove', 'pressprimer-quiz')}
					</Button>
				}
			>
				<Row gutter={16}>
					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							{/* Bank selector */}
							<div>
								<Space>
									<Text strong style={{ fontSize: 12 }}>{__('Source Bank', 'pressprimer-quiz')}</Text>
									<Tooltip title={__('Select a specific question bank, or leave empty to search all banks', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
								<Select
									style={{ width: '100%' }}
									size="small"
									placeholder={__('Any bank', 'pressprimer-quiz')}
									allowClear
									value={rule.bank_id}
									onChange={(value) => handleUpdateRule(rule.id, 'bank_id', value)}
									options={banks.map(bank => ({
										value: bank.id,
										label: bank.name,
									}))}
								/>
								<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
									{selectedBank ? selectedBank.name : __('Search all banks', 'pressprimer-quiz')}
								</Text>
							</div>

							{/* Categories */}
							<div>
								<Space>
									<Text strong style={{ fontSize: 12 }}>{__('Categories', 'pressprimer-quiz')}</Text>
									<Tooltip title={__('Filter by categories - leave empty for all', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
								<Select
									mode="multiple"
									style={{ width: '100%' }}
									size="small"
									placeholder={__('Any category', 'pressprimer-quiz')}
									value={rule.category_ids}
									onChange={(value) => handleUpdateRule(rule.id, 'category_ids', value)}
									options={categories.map(cat => ({
										value: cat.id,
										label: cat.name,
									}))}
								/>
								{selectedCategories.length > 0 && (
									<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
										{selectedCategories.map(c => c.name).join(', ')}
									</Text>
								)}
							</div>

							{/* Tags */}
							<div>
								<Space>
									<Text strong style={{ fontSize: 12 }}>{__('Tags', 'pressprimer-quiz')}</Text>
									<Tooltip title={__('Filter by tags - leave empty for all', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
								<Select
									mode="multiple"
									style={{ width: '100%' }}
									size="small"
									placeholder={__('Any tag', 'pressprimer-quiz')}
									value={rule.tag_ids}
									onChange={(value) => handleUpdateRule(rule.id, 'tag_ids', value)}
									options={tags.map(tag => ({
										value: tag.id,
										label: tag.name,
									}))}
								/>
								{selectedTags.length > 0 && (
									<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
										{selectedTags.map(t => t.name).join(', ')}
									</Text>
								)}
							</div>
						</Space>
					</Col>

					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							{/* Difficulties */}
							<div>
								<Space>
									<Text strong style={{ fontSize: 12 }}>{__('Difficulty Levels', 'pressprimer-quiz')}</Text>
									<Tooltip title={__('Check difficulty levels to include - leave empty for all', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
								<div style={{ marginTop: 8 }}>
									<Checkbox.Group
										value={rule.difficulties}
										onChange={(value) => handleUpdateRule(rule.id, 'difficulties', value)}
										style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}
									>
										<Space direction="vertical" size={4}>
											{difficultyOptions.map(option => (
												<Checkbox key={option.value} value={option.value}>
													{option.label}
												</Checkbox>
											))}
										</Space>
									</Checkbox.Group>
								</div>
								{rule.difficulties.length === 0 && (
									<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
										{__('All difficulty levels', 'pressprimer-quiz')}
									</Text>
								)}
							</div>

							{/* Question Count */}
							<div>
								<Space>
									<Text strong style={{ fontSize: 12 }}>{__('Question Count', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span></Text>
									<Tooltip title={__('How many questions to randomly select from matching questions', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
								<InputNumber
									min={1}
									max={500}
									value={rule.question_count}
									onChange={(value) => handleUpdateRule(rule.id, 'question_count', value)}
									size="small"
									style={{ width: '100%' }}
								/>
								<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: 4 }}>
									{__('Random questions will be selected from matching pool', 'pressprimer-quiz')}
								</Text>
							</div>

							{/* Preview button - TODO: implement matching count */}
							<Button
								size="small"
								block
								style={{ marginTop: 8 }}
								disabled
							>
								{__('Preview Matching Questions', 'pressprimer-quiz')}
							</Button>
							<Text type="secondary" style={{ fontSize: 10, textAlign: 'center', display: 'block' }}>
								{__('(Available after quiz is saved)', 'pressprimer-quiz')}
							</Text>
						</Space>
					</Col>
				</Row>
			</Card>
		);
	};

	if (generationMode !== 'dynamic') {
		return (
			<Card>
				<Empty
					description={__('Fixed mode selected. Switch to Settings tab to change generation mode.', 'pressprimer-quiz')}
				/>
			</Card>
		);
	}

	if (!quizId) {
		return (
			<Card>
				<Empty
					description={__('Please save the quiz first before adding rules.', 'pressprimer-quiz')}
				/>
			</Card>
		);
	}

	return (
		<Card>
			<Space direction="vertical" style={{ width: '100%' }} size="large">
				{/* Header */}
				<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
					<div>
						<Text strong>{__('Rules:', 'pressprimer-quiz')} {rules.length}</Text>
						<Text style={{ marginLeft: 16 }}>{__('Total Questions:', 'pressprimer-quiz')} {getTotalQuestions()}</Text>
					</div>
					<Button
						type="primary"
						icon={<PlusOutlined />}
						onClick={handleAddRule}
					>
						{__('Add Rule', 'pressprimer-quiz')}
					</Button>
				</div>

				{/* Info box */}
				{rules.length === 0 && (
					<Card type="inner" style={{ backgroundColor: '#f0f5ff', borderColor: '#adc6ff' }}>
						<Space direction="vertical">
							<Text strong>{__('About Dynamic Quizzes', 'pressprimer-quiz')}</Text>
							<Text>
								{__('Dynamic quizzes use rules to randomly select questions for each student attempt. This ensures every student gets a different set of questions while maintaining consistent difficulty and coverage.', 'pressprimer-quiz')}
							</Text>
							<Text type="secondary" style={{ fontSize: 12 }}>
								{__('Click "Add Rule" to create your first rule. Each rule specifies filters (bank, categories, tags, difficulty) and how many questions to select.', 'pressprimer-quiz')}
							</Text>
						</Space>
					</Card>
				)}

				{/* Rules list */}
				{rules.length > 0 && (
					<DragDropContext onDragEnd={handleDragEnd}>
						<Droppable droppableId="rules">
							{(provided) => (
								<div {...provided.droppableProps} ref={provided.innerRef}>
									{rules.map((rule, index) => (
										<Draggable
											key={rule.id}
											draggableId={String(rule.id)}
											index={index}
										>
											{(provided) => (
												<div
													ref={provided.innerRef}
													{...provided.draggableProps}
													{...provided.dragHandleProps}
												>
													{renderRuleCard(rule, index)}
												</div>
											)}
										</Draggable>
									))}
									{provided.placeholder}
								</div>
							)}
						</Droppable>
					</DragDropContext>
				)}
			</Space>
		</Card>
	);
};

export default RulesPanel;
