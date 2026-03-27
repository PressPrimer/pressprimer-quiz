/**
 * Premium Settings Panel Component
 *
 * Settings provided by premium addons (Educator, School, Enterprise).
 * Displayed as a separate tab in the Quiz Editor when at least one addon is active.
 *
 * @package PressPrimer_Quiz
 * @since 2.2.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Select,
	Switch,
	Card,
	Space,
	Typography,
	Tooltip,
	Alert,
	Modal,
} from 'antd';
import {
	QuestionCircleOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

/**
 * Premium Settings Panel Component
 *
 * @param {Object} props Component props
 * @param {Object} props.form Ant Design form instance
 * @param {Object} props.quizData Initial quiz data (includes addon fields)
 */
const PremiumSettingsPanel = ({ form, quizData = {} }) => {
	// Pre-test selector state (only used when Educator addon is active).
	const [preTestOptions, setPreTestOptions] = useState([]);
	const [preTestLoading, setPreTestLoading] = useState(false);
	const [preTestFetched, setPreTestFetched] = useState(false);

	// Set initial pre-test option if quiz already has one linked.
	useEffect(() => {
		if (quizData.pre_test_id && quizData.pre_test_title) {
			setPreTestOptions([{
				value: quizData.pre_test_id,
				label: quizData.pre_test_title,
			}]);
		}
	}, [quizData.pre_test_id, quizData.pre_test_title]);

	/**
	 * Fetch available pre-tests from the educator REST endpoint
	 */
	const fetchPreTests = useCallback(async (search = '') => {
		if (!quizData.educatorActive) {
			return;
		}

		setPreTestLoading(true);
		try {
			const params = new URLSearchParams({
				per_page: '20',
			});

			if (quizData.id) {
				params.append('exclude', quizData.id);
			}

			if (search) {
				params.append('search', search);
			}

			const results = await apiFetch({
				path: `/ppqe/v1/quizzes/available-pretests?${params.toString()}`,
			});

			const options = results.map((quiz) => ({
				value: quiz.id,
				label: quiz.owner_name
					? `${quiz.title} (${quiz.owner_name})`
					: quiz.title,
			}));

			setPreTestOptions(options);
			setPreTestFetched(true);
		} catch {
			// Silently fail - the selector will just show no options.
		} finally {
			setPreTestLoading(false);
		}
	}, [quizData.educatorActive, quizData.id]);

	/**
	 * Handle pre-test dropdown open - load initial options
	 */
	const handlePreTestDropdownOpen = useCallback((open) => {
		if (open && !preTestFetched) {
			fetchPreTests();
		}
	}, [preTestFetched, fetchPreTests]);

	/**
	 * Handle pre-test search with debounce
	 */
	const handlePreTestSearch = useCallback((value) => {
		fetchPreTests(value);
	}, [fetchPreTests]);

	/**
	 * Handle pre-test change - confirm before unlinking
	 */
	const handlePreTestChange = useCallback((value) => {
		const currentValue = form.getFieldValue('pre_test_id');

		// If clearing (unlinking) and there was a previous value, confirm.
		if (!value && currentValue) {
			Modal.confirm({
				title: __('Unlink Pre-Test?', 'pressprimer-quiz'),
				content: __('Are you sure you want to unlink this pre-test? Existing comparison data will still be available, but new attempts will not be linked.', 'pressprimer-quiz'),
				okText: __('Unlink', 'pressprimer-quiz'),
				cancelText: __('Cancel', 'pressprimer-quiz'),
				onOk: () => {
					form.setFieldsValue({ pre_test_id: null });
				},
			});
			return;
		}

		form.setFieldsValue({ pre_test_id: value || null });
	}, [form]);

	return (
		<Space direction="vertical" size="large" style={{ width: '100%' }}>
			{/* Pre/Post Test Linking - Only shown when Educator addon is active */}
			{quizData.educatorActive && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Pre/Post Test Linking', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Link this quiz to a pre-test to track student improvement between assessments', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Alert
						type="info"
						showIcon
						message={__('Pre/Post Test Comparison', 'pressprimer-quiz')}
						description={__('Link a pre-test to this quiz to automatically show students their improvement on the results page. The pre-test should be taken before this quiz.', 'pressprimer-quiz')}
						style={{ marginBottom: 16 }}
					/>

					<Form.Item
						label={
							<Space>
								<span>{__('Pre-Test Quiz', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Select the quiz that serves as the pre-test for this quiz. Students who complete both will see a comparison of their scores.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="pre_test_id"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							placeholder={__('None (no pre-test linked)', 'pressprimer-quiz')}
							allowClear
							showSearch
							filterOption={false}
							loading={preTestLoading}
							options={preTestOptions}
							onDropdownVisibleChange={handlePreTestDropdownOpen}
							onSearch={handlePreTestSearch}
							onChange={handlePreTestChange}
							notFoundContent={preTestLoading ? __('Loading...', 'pressprimer-quiz') : __('No quizzes found', 'pressprimer-quiz')}
						/>
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
						{__('Select a quiz to use as the pre-test for this quiz', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}

			{/* Proctoring Overrides - Only shown when Enterprise addon is active */}
			{quizData.enterpriseActive && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Proctoring', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Override global proctoring settings for this quiz', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Alert
						type="info"
						showIcon
						message={__('Per-Quiz Proctoring Overrides', 'pressprimer-quiz')}
						description={
							quizData.proctoring_global_mode === 'off'
								? __('Global proctoring is currently off. You can enable it for this quiz individually.', 'pressprimer-quiz')
								: __('These settings override the global proctoring configuration for this quiz only. Use "Use Global Default" to inherit from Settings > Proctoring.', 'pressprimer-quiz')
						}
						style={{ marginBottom: 16 }}
					/>

					<Form.Item
						label={
							<Space>
								<span>{__('Proctoring Mode', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Controls the level of proctoring for this quiz. Monitor logs incidents silently. Strict warns students of violations.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_mode"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'off', label: __('Off', 'pressprimer-quiz') },
								{ value: 'monitor', label: __('Monitor', 'pressprimer-quiz') },
								{ value: 'strict', label: __('Strict', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>{__('Tab/Focus Monitoring', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Detect when the student leaves the quiz tab or switches to another application.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_tab_monitoring"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'on', label: __('Enabled', 'pressprimer-quiz') },
								{ value: 'off', label: __('Disabled', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>{__('Full-Screen Mode', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Require the quiz to be taken in full-screen mode. Exiting full-screen is logged as a proctoring incident.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_fullscreen"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'on', label: __('Enabled', 'pressprimer-quiz') },
								{ value: 'off', label: __('Disabled', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						name="proctoring_require_desktop"
						valuePropName="checked"
					>
						<Space>
							<Switch size="small" />
							<span>{__('Require Desktop Browser', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Block mobile and tablet devices from taking this quiz. Detection is based on user agent strings and may not be 100% accurate.', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
						{__('Mobile devices have limited proctoring support. Enable this to restrict quizzes to desktop browsers only.', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}

			{/* Spaced Repetition - Only shown when School addon is active */}
			{quizData.schoolActive && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Spaced Repetition', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Track student mastery over time and generate review quizzes for questions they need to practice', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Form.Item
						label={
							<Space>
								<span>{__('Enable Spaced Repetition', 'pressprimer-quiz')}</span>
								<Tooltip title={__('When enabled, student answers are tracked for mastery using SM-2 (SuperMemo 2), a proven algorithm that schedules reviews at increasing intervals based on how well each question is remembered. Questions answered incorrectly are reviewed sooner; mastered questions are spaced further apart.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="enable_sr"
						valuePropName="checked"
						style={{ marginBottom: 0 }}
					>
						<Switch size="small" />
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 12, display: 'block', marginTop: 8 }}>
						{__('Students will see review prompts and can generate personalized review quizzes from questions they need to practice.', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}
		</Space>
	);
};

export default PremiumSettingsPanel;
