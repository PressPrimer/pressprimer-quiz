/**
 * Premium Upsell (locked Premium tab options)
 *
 * The per-quiz options of the premium tiers that aren't active on this site,
 * so admins can discover them. The list comes from the premium catalog
 * through the editor data (quizData.quizOptions): administrators only, while
 * upsells are on, and never on the front-end dashboard. On a free site it is
 * the whole tab; with some tiers active it follows their real cards.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { Card, Space, Typography, Tag, Button, Row, Col } from 'antd';
import {
	SwapOutlined,
	LinkOutlined,
	RobotOutlined,
	RocketOutlined,
	DashboardOutlined,
	TagsOutlined,
	FileTextOutlined,
	BranchesOutlined,
	EyeOutlined,
	SafetyCertificateOutlined,
	StarOutlined,
} from '@ant-design/icons';

const { Title, Text, Paragraph } = Typography;

/**
 * Icons the catalog's quiz options name (iconType).
 */
const ICON_MAP = {
	SwapOutlined: <SwapOutlined />,
	LinkOutlined: <LinkOutlined />,
	RobotOutlined: <RobotOutlined />,
	RocketOutlined: <RocketOutlined />,
	DashboardOutlined: <DashboardOutlined />,
	TagsOutlined: <TagsOutlined />,
	FileTextOutlined: <FileTextOutlined />,
	BranchesOutlined: <BranchesOutlined />,
	EyeOutlined: <EyeOutlined />,
	SafetyCertificateOutlined: <SafetyCertificateOutlined />,
};

/**
 * Premium Upsell component.
 *
 * @param {Object}   props            Component props.
 * @param {Object[]} props.options    Locked options (quizData.quizOptions).
 * @param {string}   props.pricingUrl Tagged pricing link (quizData.quizOptionsPricingUrl).
 * @param {boolean}  props.compact    True when the tab also shows active tiers' cards.
 * @return {JSX.Element|null} The locked options, or null when there are none.
 */
const PremiumUpsell = ({ options = [], pricingUrl = '', compact = false }) => {
	if (!Array.isArray(options) || options.length === 0) {
		return null;
	}

	return (
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
						{compact
							? __('More quiz options', 'pressprimer-quiz')
							: __('Unlock more quiz options', 'pressprimer-quiz')}
					</Title>
					<Paragraph style={{ margin: 0 }}>
						{compact
							? __('These options come with the premium tiers this site doesn’t have yet.', 'pressprimer-quiz')
							: __(
								'Premium add-ons unlock advanced per-quiz options like pre/post test linking, spaced repetition, branching logic, and proctoring. Upgrade to configure them right here in the quiz editor.',
								'pressprimer-quiz'
							)}
					</Paragraph>
					{pricingUrl && (
						<Button
							type="primary"
							href={pricingUrl}
							target="_blank"
							rel="noopener noreferrer"
							style={{ alignSelf: 'flex-start' }}
						>
							{__('View Pricing & Upgrade', 'pressprimer-quiz')}
						</Button>
					)}
				</Space>
			</Card>

			<Row gutter={[16, 16]}>
				{options.map((option) => (
					<Col key={option.key} xs={24} md={12}>
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
										backgroundColor: option.color,
									}}
								>
									{ICON_MAP[option.iconType] || <StarOutlined />}
								</div>
								<div>
									<Space
										size={8}
										align="center"
										style={{ marginBottom: 4 }}
									>
										<Text strong>{option.title}</Text>
										{option.tierName && (
											<Tag color={option.tierColor}>
												{option.tierName}
											</Tag>
										)}
									</Space>
									<Paragraph
										type="secondary"
										style={{ margin: 0, fontSize: 13 }}
									>
										{option.description}
									</Paragraph>
									{option.upgradeUrl && option.tierName && (
										<Button
											type="link"
											size="small"
											href={option.upgradeUrl}
											target="_blank"
											rel="noopener noreferrer"
											style={{ padding: 0, marginTop: 4 }}
										>
											{sprintf(
												/* translators: %s: tier name (Educator, School, or Enterprise) */
												__('Get %s', 'pressprimer-quiz'),
												option.tierName
											)}
										</Button>
									)}
								</div>
							</Space>
						</Card>
					</Col>
				))}
			</Row>
		</div>
	);
};

export default PremiumUpsell;
