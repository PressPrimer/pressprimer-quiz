/**
 * Quiz Block
 *
 * Gutenberg block for displaying a quiz.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Quiz icon
 */
const quizIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
		<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" fill="currentColor"/>
	</svg>
);

/**
 * Register Quiz block
 */
registerBlockType('pressprimer-quiz/quiz', {
	icon: quizIcon,

	edit: (props) => {
		const { attributes, setAttributes } = props;
		const { quizId } = attributes;
		const blockProps = useBlockProps();

		const [quizzes, setQuizzes] = useState([]);
		const [loading, setLoading] = useState(true);
		const [selectedQuiz, setSelectedQuiz] = useState(null);

		// Fetch available quizzes
		useEffect(() => {
			setLoading(true);
			apiFetch({ path: '/ppq/v1/quizzes/published' })
				.then((response) => {
					setQuizzes(response || []);
					setLoading(false);
				})
				.catch((error) => {
					console.error('Failed to fetch quizzes:', error);
					setQuizzes([]);
					setLoading(false);
				});
		}, []);

		// Find selected quiz from loaded quizzes
		useEffect(() => {
			if (quizId && quizId > 0 && quizzes.length > 0) {
				// Use == for comparison to handle potential type mismatches
				const quiz = quizzes.find((q) => parseInt(q.id, 10) === parseInt(quizId, 10));
				setSelectedQuiz(quiz || null);
			} else {
				setSelectedQuiz(null);
			}
		}, [quizId, quizzes]);

		// Build options for select
		const quizOptions = [
			{ value: 0, label: __('— Select a Quiz —', 'pressprimer-quiz') },
			...quizzes.map((quiz) => ({
				value: quiz.id,
				label: quiz.title,
			})),
		];

		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={__('Quiz Settings', 'pressprimer-quiz')} initialOpen={true}>
						{loading ? (
							<p><Spinner /> {__('Loading quizzes...', 'pressprimer-quiz')}</p>
						) : (
							<SelectControl
								label={__('Select Quiz', 'pressprimer-quiz')}
								value={quizId}
								options={quizOptions}
								onChange={(value) => setAttributes({ quizId: parseInt(value, 10) })}
								help={__('Choose the quiz to display on this page.', 'pressprimer-quiz')}
							/>
						)}
					</PanelBody>
				</InspectorControls>

				{loading ? (
					<Placeholder
						icon={quizIcon}
						label={__('PPQ Quiz', 'pressprimer-quiz')}
					>
						<p><Spinner /> {__('Loading quizzes...', 'pressprimer-quiz')}</p>
					</Placeholder>
				) : !quizId || quizId === 0 ? (
					<Placeholder
						icon={quizIcon}
						label={__('PPQ Quiz', 'pressprimer-quiz')}
						instructions={__('Select a quiz to display from the dropdown below or in the sidebar settings.', 'pressprimer-quiz')}
					>
						<SelectControl
							value={quizId}
							options={quizOptions}
							onChange={(value) => setAttributes({ quizId: parseInt(value, 10) })}
						/>
					</Placeholder>
				) : (
					<div className="ppq-quiz-block-preview">
						<div className="ppq-quiz-block-preview-header">
							<span className="ppq-quiz-block-preview-icon">{quizIcon}</span>
							<span className="ppq-quiz-block-preview-label">{__('PPQ Quiz', 'pressprimer-quiz')}</span>
						</div>
						<div className="ppq-quiz-block-preview-content">
							{selectedQuiz ? (
								<>
									<h3 className="ppq-quiz-block-preview-title">
										{selectedQuiz.title}
									</h3>
									<div className="ppq-quiz-block-preview-meta">
										{selectedQuiz.question_count !== undefined && (
											<span className="ppq-quiz-block-preview-meta-item">
												<strong>{selectedQuiz.question_count}</strong> {__('questions', 'pressprimer-quiz')}
											</span>
										)}
										{selectedQuiz.time_limit_minutes > 0 && (
											<span className="ppq-quiz-block-preview-meta-item">
												<strong>{selectedQuiz.time_limit_minutes}</strong> {__('min time limit', 'pressprimer-quiz')}
											</span>
										)}
										{selectedQuiz.passing_score !== undefined && (
											<span className="ppq-quiz-block-preview-meta-item">
												<strong>{selectedQuiz.passing_score}%</strong> {__('to pass', 'pressprimer-quiz')}
											</span>
										)}
									</div>
									{selectedQuiz.description && (
										<p className="ppq-quiz-block-preview-description">
											{selectedQuiz.description.substring(0, 150)}
											{selectedQuiz.description.length > 150 ? '...' : ''}
										</p>
									)}
								</>
							) : (
								<p>{__('Loading quiz details...', 'pressprimer-quiz')}</p>
							)}
						</div>
						<div className="ppq-quiz-block-preview-footer">
							<p className="ppq-quiz-block-preview-note">
								{__('The full quiz will be displayed on the frontend.', 'pressprimer-quiz')}
							</p>
						</div>
					</div>
				)}
			</div>
		);
	},

	save: () => {
		// Dynamic block - rendered via PHP
		return null;
	},
});
