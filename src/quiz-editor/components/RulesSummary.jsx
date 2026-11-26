/**
 * Rules Summary Component
 *
 * A dynamic quiz's rules, read-only. The dashboard quiz editor shows this in
 * place of the rule builder, which stays in wp-admin in 3.2.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Alert, Card, Empty, List, Spin, Typography } from 'antd';
import { debugError } from '../../utils/debug';
import { getAnyBankCopy } from './RulesPanel';

const { Text } = Typography;

/**
 * Names for a list of IDs, joined.
 *
 * @param {number[]} ids   IDs.
 * @param {Object[]} items Items with id and name.
 * @return {string} Comma-separated names (IDs it can't name are left out).
 */
const namesFor = (ids = [], items = []) =>
	ids
		.map((id) => items.find((item) => Number(item.id) === Number(id)))
		.filter(Boolean)
		.map((item) => item.name)
		.join(', ');

/**
 * Rules Summary
 *
 * @param {Object} props           Component props.
 * @param {number} props.quizId    Quiz ID.
 * @param {string} props.ruleScope Whose questions rules draw from: 'all', 'own' or 'owner'.
 * @return {JSX.Element} The summary.
 */
const RulesSummary = ({ quizId, ruleScope = 'own' }) => {
	const [rules, setRules] = useState([]);
	const [banks, setBanks] = useState([]);
	const [categories, setCategories] = useState([]);
	const [tags, setTags] = useState([]);
	const [loading, setLoading] = useState(!!quizId);
	const [failed, setFailed] = useState(false);

	useEffect(() => {
		if (!quizId) {
			return undefined;
		}

		let active = true;
		setLoading(true);

		Promise.all([
			apiFetch({ path: `/ppq/v1/quizzes/${quizId}/rules` }),
			apiFetch({ path: '/ppq/v1/banks' }).catch(() => []),
			apiFetch({ path: '/ppq/v1/taxonomies?type=category' }).catch(() => []),
			apiFetch({ path: '/ppq/v1/taxonomies?type=tag' }).catch(() => []),
		])
			.then(([ruleList, bankList, categoryList, tagList]) => {
				if (!active) {
					return;
				}
				setRules(Array.isArray(ruleList) ? ruleList : []);
				setBanks(Array.isArray(bankList) ? bankList : []);
				setCategories(Array.isArray(categoryList) ? categoryList : []);
				setTags(Array.isArray(tagList) ? tagList : []);
			})
			.catch((error) => {
				debugError('Failed to load quiz rules:', error);
				if (active) {
					setFailed(true);
				}
			})
			.finally(() => {
				if (active) {
					setLoading(false);
				}
			});

		return () => {
			active = false;
		};
	}, [quizId]);

	const difficultyLabels = {
		beginner: __('Beginner', 'pressprimer-quiz'),
		intermediate: __('Intermediate', 'pressprimer-quiz'),
		advanced: __('Advanced', 'pressprimer-quiz'),
		expert: __('Expert', 'pressprimer-quiz'),
	};
	const anyBank = getAnyBankCopy(ruleScope).label;
	const total = rules.reduce((sum, rule) => sum + (parseInt(rule.question_count, 10) || 0), 0);

	const describeRule = (rule) => {
		let source = anyBank;
		if (rule.bank_id) {
			const bank = banks.find((item) => Number(item.id) === Number(rule.bank_id));
			source = bank ? bank.name : __('A bank you can’t see', 'pressprimer-quiz');
		}
		const lines = [
			/* translators: %s: question bank name, or e.g. "All questions I can use". */
			sprintf(__('From: %s', 'pressprimer-quiz'), source),
		];

		const categoryNames = namesFor(rule.category_ids, categories);
		if (categoryNames) {
			/* translators: %s: comma-separated category names. */
			lines.push(sprintf(__('Categories: %s', 'pressprimer-quiz'), categoryNames));
		}

		const tagNames = namesFor(rule.tag_ids, tags);
		if (tagNames) {
			/* translators: %s: comma-separated tag names. */
			lines.push(sprintf(__('Tags: %s', 'pressprimer-quiz'), tagNames));
		}

		const difficulties = (rule.difficulties || []).map((value) => difficultyLabels[value] || value);
		if (difficulties.length) {
			/* translators: %s: comma-separated difficulty levels. */
			lines.push(sprintf(__('Difficulty: %s', 'pressprimer-quiz'), difficulties.join(', ')));
		} else {
			lines.push(__('Difficulty: any', 'pressprimer-quiz'));
		}

		return lines;
	};

	return (
		<Card className="ppq-rules-summary">
			<Alert
				type="info"
				showIcon
				message={__('This quiz picks questions by rules. Rules can’t be edited on the dashboard yet.', 'pressprimer-quiz')}
				style={{ marginBottom: 16 }}
			/>

			{loading && (
				<div style={{ textAlign: 'center', padding: 24 }}>
					<Spin />
				</div>
			)}

			{!loading && failed && (
				<Alert type="error" showIcon message={__('This quiz’s rules couldn’t be loaded.', 'pressprimer-quiz')} />
			)}

			{!loading && !failed && rules.length === 0 && (
				<Empty description={__('This quiz has no rules yet.', 'pressprimer-quiz')} />
			)}

			{!loading && !failed && rules.length > 0 && (
				<>
					<List
						dataSource={rules}
						renderItem={(rule, index) => (
							<List.Item key={rule.id}>
								<div>
									<Text strong>
										{sprintf(
											/* translators: 1: rule number, 2: number of questions. */
											_n('Rule %1$d: %2$d question', 'Rule %1$d: %2$d questions', parseInt(rule.question_count, 10) || 0, 'pressprimer-quiz'),
											index + 1,
											parseInt(rule.question_count, 10) || 0
										)}
									</Text>
									{describeRule(rule).map((line) => (
										<div key={line}>
											<Text type="secondary">{line}</Text>
										</div>
									))}
								</div>
							</List.Item>
						)}
					/>
					<Text type="secondary" style={{ display: 'block', marginTop: 12 }}>
						{sprintf(
							/* translators: %d: number of questions per attempt. */
							_n('Each attempt draws up to %d question.', 'Each attempt draws up to %d questions.', total, 'pressprimer-quiz'),
							total
						)}
					</Text>
				</>
			)}
		</Card>
	);
};

export default RulesSummary;
