/**
 * Premium Upsell (free-site preview)
 *
 * Rendered inside the Quiz Editor's Premium Settings tab when no premium addon
 * is active, so admins can discover the per-quiz options the premium tiers add.
 * Mirrors the wp-admin Upgrade page: benefit-focused copy grouped by tier with a
 * single call to action to the pricing page. Shown to administrators only — the
 * editor hides this tab from teachers on a free site.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __ } from '@wordpress/i18n';
import { Card, Space, Typography, Tag, Button, Row, Col } from 'antd';
import {
	SwapOutlined,
	RocketOutlined,
	BranchesOutlined,
	EyeOutlined,
	SafetyCertificateOutlined,
} from '@ant-design/icons';

const { Title, Text, Paragraph } = Typography;

const PRICING_URL = 'https://pressprimer.com/pressprimer-quiz-pricing/#pricing';

/**
 * Tier display metadata for the feature tags.
 */
const TIER_META = {
	educator: { label: __('Educator', 'pressprimer-quiz'), color: 'blue' },
	school: { label: __('School', 'pressprimer-quiz'), color: 'gold' },
	enterprise: { label: __('Enterprise', 'pressprimer-quiz'), color: 'purple' },
};

/**
 * Premium per-quiz options, in tier order (Educator, School, Enterprise).
 */
const FEATURES = [
	{
		key: 'prepost',
		icon: <SwapOutlined />,
		color: '#8b5cf6',
		tier: 'educator',
		title: __('Pre/Post Test Linking', 'pressprimer-quiz'),
		description: __(
			'Link a pre-test to this quiz to automatically show students their improvement on the results page.',
			'pressprimer-quiz'
		),
	},
	{
		key: 'spaced-repetition',
		icon: <RocketOutlined />,
		color: '#14b8a6',
		tier: 'school',
		title: __('Spaced Repetition', 'pressprimer-quiz'),
		description: __(
			'Track question mastery with the SM-2 algorithm and generate personalized review quizzes at optimal intervals.',
			'pressprimer-quiz'
		),
	},
	{
		key: 'branching',
		icon: <BranchesOutlined />,
		color: '#1890ff',
		tier: 'enterprise',
		title: __('Branching Logic', 'pressprimer-quiz'),
		description: __(
			'Route students to different questions based on their answers to build adaptive, conditional quizzes.',
			'pressprimer-quiz'
		),
	},
	{
		key: 'proctoring',
		icon: <EyeOutlined />,
		color: '#eb2f96',
		tier: 'enterprise',
		title: __('Proctoring', 'pressprimer-quiz'),
		description: __(
			'Monitor tab-switching and focus loss, require full-screen mode, and restrict quizzes to desktop browsers.',
			'pressprimer-quiz'
		),
	},
	{
		key: 'integrity',
		icon: <SafetyCertificateOutlined />,
		color: '#14b8a6',
		tier: 'enterprise',
		title: __('Integrity Analysis', 'pressprimer-quiz'),
		description: __(
			'Flag completed attempts with statistically unusual patterns — timing, answer similarity, shared devices, and concurrent sessions.',
			'pressprimer-quiz'
		),
	},
];

/**
 * Premium Upsell component.
 *
 * @return {JSX.Element} The upsell preview.
 */
const PremiumUpsell = () => (
	<div className="ppq-premium-upsell">
		<Card
			style={{
				marginBottom: 24,
				background: '#f6ffed',
				borderColor: '#b7eb8f',
			}}
		>
			<Space direction="vertical" size={8} style={{ width: '100%' }}>
				<Title level={4} style={{ margin: 0 }}>
					{__('Unlock more quiz options', 'pressprimer-quiz')}
				</Title>
				<Paragraph style={{ margin: 0 }}>
					{__(
						'Premium add-ons unlock advanced per-quiz options like pre/post test linking, spaced repetition, branching logic, and proctoring. Upgrade to configure them right here in the quiz editor.',
						'pressprimer-quiz'
					)}
				</Paragraph>
				<Button
					type="primary"
					href={PRICING_URL}
					target="_blank"
					rel="noopener noreferrer"
				>
					{__('View Pricing & Upgrade', 'pressprimer-quiz')}
				</Button>
			</Space>
		</Card>

		<Row gutter={[16, 16]}>
			{FEATURES.map((feature) => {
				const tier = TIER_META[feature.tier];
				return (
					<Col key={feature.key} xs={24} md={12}>
						<Card size="small" style={{ height: '100%' }}>
							<Space align="start" size={12}>
								<div
									style={{
										width: 40,
										height: 40,
										borderRadius: 8,
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'center',
										color: '#fff',
										fontSize: 18,
										flexShrink: 0,
										backgroundColor: feature.color,
									}}
								>
									{feature.icon}
								</div>
								<div>
									<Space
										size={8}
										align="center"
										style={{ marginBottom: 4 }}
									>
										<Text strong>{feature.title}</Text>
										{tier && (
											<Tag color={tier.color}>
												{tier.label}
											</Tag>
										)}
									</Space>
									<Paragraph
										type="secondary"
										style={{ margin: 0, fontSize: 13 }}
									>
										{feature.description}
									</Paragraph>
								</div>
							</Space>
						</Card>
					</Col>
				);
			})}
		</Row>
	</div>
);

export default PremiumUpsell;
