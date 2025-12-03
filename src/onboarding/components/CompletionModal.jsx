/**
 * Completion Modal Component
 *
 * Final modal shown when the tour is complete.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Row, Col } from 'antd';
import {
	CheckCircleOutlined,
	PlusOutlined,
	QuestionCircleOutlined,
	FolderAddOutlined,
} from '@ant-design/icons';

/**
 * CompletionModal Component
 *
 * @param {Object} props Component props
 * @param {string} props.title Modal title
 * @param {string} props.content Modal content
 * @param {Function} props.onComplete Complete tour callback
 * @param {Object} props.urls Admin page URLs
 */
const CompletionModal = ({ title, content, onComplete, urls = {} }) => {
	const modalRef = useRef(null);
	const completeButtonRef = useRef(null);

	// Focus management
	useEffect(() => {
		if (completeButtonRef.current) {
			completeButtonRef.current.focus();
		}

		document.body.style.overflow = 'hidden';

		return () => {
			document.body.style.overflow = '';
		};
	}, []);

	/**
	 * Handle quick action click - complete tour then navigate
	 */
	const handleQuickAction = (href) => {
		onComplete();
		// Small delay to ensure state is saved before navigation
		setTimeout(() => {
			window.location.href = href;
		}, 100);
	};

	// Handle escape key
	useEffect(() => {
		const handleKeyDown = (e) => {
			if (e.key === 'Escape') {
				onComplete();
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [onComplete]);

	const quickActions = [
		{
			key: 'create-question',
			icon: <QuestionCircleOutlined />,
			label: __('Create a Question', 'pressprimer-quiz'),
			href: urls.questions ? `${urls.questions}&action=new` : 'admin.php?page=ppq-questions&action=new',
		},
		{
			key: 'create-bank',
			icon: <FolderAddOutlined />,
			label: __('Create a Bank', 'pressprimer-quiz'),
			href: urls.banks ? `${urls.banks}&action=new` : 'admin.php?page=ppq-banks&action=new',
		},
		{
			key: 'create-quiz',
			icon: <PlusOutlined />,
			label: __('Create a Quiz', 'pressprimer-quiz'),
			href: urls.quizzes ? `${urls.quizzes}&action=new` : 'admin.php?page=ppq-quizzes&action=new',
		},
	];

	return (
		<div className="ppq-onboarding-overlay" role="dialog" aria-modal="true" aria-labelledby="ppq-complete-title">
			<div ref={modalRef} className="ppq-onboarding-modal ppq-onboarding-modal--complete">
				<div className="ppq-onboarding-modal__icon ppq-onboarding-modal__icon--success">
					<CheckCircleOutlined />
				</div>

				<h1 id="ppq-complete-title" className="ppq-onboarding-modal__title">
					{title}
				</h1>

				<p className="ppq-onboarding-modal__content">
					{content}
				</p>

				<div className="ppq-onboarding-modal__quick-actions">
					<p className="ppq-onboarding-modal__quick-actions-title">
						{__('Get started with:', 'pressprimer-quiz')}
					</p>
					<Row gutter={[12, 12]}>
						{quickActions.map((action) => (
							<Col key={action.key} xs={24} sm={8}>
								<Button
									icon={action.icon}
									onClick={() => handleQuickAction(action.href)}
									block
									className="ppq-onboarding-modal__action-btn"
								>
									{action.label}
								</Button>
							</Col>
						))}
					</Row>
				</div>

				<div className="ppq-onboarding-modal__actions">
					<Button
						ref={completeButtonRef}
						type="primary"
						size="large"
						onClick={onComplete}
						className="ppq-onboarding-modal__complete-btn"
					>
						{__('Close Tour', 'pressprimer-quiz')}
					</Button>
				</div>
			</div>
		</div>
	);
};

export default CompletionModal;
