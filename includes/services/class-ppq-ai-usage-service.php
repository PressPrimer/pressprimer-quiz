<?php
/**
 * AI usage service
 *
 * How much AI generation each user has used this month on the site's key,
 * and the per-teacher monthly limit (feature 3 FR-004). One generated
 * question is one unit; Educator's feedback for one question is one unit.
 * A caller reserves the units it asks for before calling the provider, then
 * settles to what it got, or releases them when the call fails, so a
 * teacher isn't charged when the key is broken.
 *
 * Educator detects the API with class_exists( 'PressPrimer_Quiz_AI_Usage_Service' ).
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI usage service class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_AI_Usage_Service {

	/**
	 * User meta holding the user's usage for the current period:
	 * { period, units, requests, tokens, by_feature }.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const META_KEY = 'pressprimer_quiz_ai_usage';

	/**
	 * The limit a fresh install starts with, and the fallback when none is set.
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const DEFAULT_LIMIT = 200;

	/**
	 * Lowest limit the setting accepts.
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const MIN_LIMIT = 1;

	/**
	 * Highest limit the setting accepts.
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const MAX_LIMIT = 10000;

	/**
	 * The current period: the calendar month in the site's timezone.
	 *
	 * @since 3.2.0
	 *
	 * @return string Period key, 'Y-m'.
	 */
	public static function get_period() {
		return wp_date( 'Y-m' );
	}

	/**
	 * When the current period ends: midnight on the 1st of next month in the
	 * site's timezone.
	 *
	 * @since 3.2.0
	 *
	 * @return int Unix timestamp.
	 */
	public static function get_resets_at() {
		$next = new DateTimeImmutable( 'first day of next month midnight', wp_timezone() );

		return $next->getTimestamp();
	}

	/**
	 * Whether the per-teacher limit is switched on (Settings → Integrations).
	 *
	 * Missing (a site upgraded from 3.1) means off.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = get_option( 'pressprimer_quiz_settings', [] );

		return is_array( $settings ) && ! empty( $settings['ai_user_limit_enabled'] );
	}

	/**
	 * The site's per-teacher limit setting.
	 *
	 * @since 3.2.0
	 *
	 * @return int Units per user per month.
	 */
	public static function get_site_limit() {
		$settings = get_option( 'pressprimer_quiz_settings', [] );
		$limit    = is_array( $settings ) && isset( $settings['ai_user_limit'] ) ? absint( $settings['ai_user_limit'] ) : self::DEFAULT_LIMIT;

		return self::clamp_limit( $limit );
	}

	/**
	 * Keep a limit within the setting's range.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $limit Limit.
	 * @return int The limit, 1 to 10,000.
	 */
	public static function clamp_limit( $limit ) {
		return max( self::MIN_LIMIT, min( self::MAX_LIMIT, absint( $limit ) ) );
	}

	/**
	 * Whether a user is exempt from the limit: the people responsible for
	 * the key (pressprimer_quiz_manage_settings or pressprimer_quiz_manage_all).
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_exempt( $user_id ) {
		$user_id = absint( $user_id );
		$exempt  = $user_id && ( user_can( $user_id, 'pressprimer_quiz_manage_settings' ) || user_can( $user_id, 'pressprimer_quiz_manage_all' ) );

		/**
		 * Filters whether a user is exempt from the monthly AI limit.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $exempt  Whether the user is exempt.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'pressprimer_quiz_ai_limit_exempt', $exempt, $user_id );
	}

	/**
	 * A user's monthly limit, or null when they have none.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return int|null Units per month, or null for no limit.
	 */
	public static function get_limit( $user_id ) {
		$user_id = absint( $user_id );

		if ( self::is_exempt( $user_id ) ) {
			return null;
		}

		$limit = self::is_enabled() ? self::get_site_limit() : null;

		/**
		 * Filters a user's monthly AI limit (null: no limit).
		 *
		 * @since 3.2.0
		 *
		 * @param int|null $limit   Units per month, or null.
		 * @param int      $user_id User ID.
		 */
		$limit = apply_filters( 'pressprimer_quiz_ai_user_limit', $limit, $user_id );

		return null === $limit ? null : absint( $limit );
	}

	/**
	 * A user's usage this period.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return array { period, units, requests, tokens, by_feature, limit, remaining, resets_at }; limit and remaining are null without a limit.
	 */
	public static function get_usage( $user_id ) {
		$user_id = absint( $user_id );
		$record  = self::get_record( $user_id );
		$limit   = self::get_limit( $user_id );

		return [
			'period'     => $record['period'],
			'units'      => $record['units'],
			'requests'   => $record['requests'],
			'tokens'     => $record['tokens'],
			'by_feature' => $record['by_feature'],
			'limit'      => $limit,
			'remaining'  => null === $limit ? null : max( 0, $limit - $record['units'] ),
			'resets_at'  => self::get_resets_at(),
		];
	}

	/**
	 * Whether a user may use more units now.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @param int $units   Units wanted.
	 * @return true|WP_Error True, or ppq_ai_limit_reached (429) with limit, used, remaining and resets_at.
	 */
	public static function check( $user_id, $units ) {
		$user_id = absint( $user_id );
		$units   = max( 1, absint( $units ) );
		$limit   = self::get_limit( $user_id );

		if ( null === $limit ) {
			return true;
		}

		$used = self::get_record( $user_id )['units'];

		if ( $used + $units <= $limit ) {
			return true;
		}

		$remaining = max( 0, $limit - $used );
		$resets_at = self::get_resets_at();

		if ( $remaining > 0 ) {
			$message = sprintf(
				/* translators: 1: units asked for, 2: units left this month, 3: reset date. */
				__( 'That would use %1$d AI items, but you have %2$d left this month. Your limit resets on %3$s.', 'pressprimer-quiz' ),
				$units,
				$remaining,
				wp_date( get_option( 'date_format' ), $resets_at )
			);
		} else {
			$message = sprintf(
				/* translators: 1: monthly limit, 2: reset date. */
				__( 'You\'ve used all %1$d AI items for this month. Your limit resets on %2$s.', 'pressprimer-quiz' ),
				$limit,
				wp_date( get_option( 'date_format' ), $resets_at )
			);
		}

		return new WP_Error(
			'ppq_ai_limit_reached',
			$message,
			[
				'status'    => 429,
				'limit'     => $limit,
				'used'      => $used,
				'remaining' => $remaining,
				'resets_at' => $resets_at,
			]
		);
	}

	/**
	 * Reserve units before calling the provider.
	 *
	 * The units count toward the month at once, so two open tabs can't both
	 * pass the check and overrun it. Every user's usage is recorded; only
	 * limited users are refused.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param int    $units   Units to reserve.
	 * @param string $feature What it's for ('questions', 'feedback', 'distractors', …).
	 * @return array|WP_Error The reservation (pass it to settle() or release()), or ppq_ai_limit_reached.
	 */
	public static function reserve( $user_id, $units, $feature ) {
		$user_id = absint( $user_id );
		$units   = max( 1, absint( $units ) );

		$allowed = self::check( $user_id, $units );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$record           = self::get_record( $user_id );
		$record['units'] += $units;
		self::save_record( $user_id, $record );

		return [
			'user_id' => $user_id,
			'units'   => $units,
			'feature' => sanitize_key( $feature ),
			'period'  => $record['period'],
		];
	}

	/**
	 * Settle a reservation to what the request actually used.
	 *
	 * @since 3.2.0
	 *
	 * @param array $reservation  From reserve().
	 * @param int   $actual_units Units used (e.g. the valid questions returned).
	 * @param int   $tokens       Tokens the provider reported (0 when unknown).
	 */
	public static function settle( $reservation, $actual_units, $tokens = 0 ) {
		if ( ! is_array( $reservation ) || empty( $reservation['user_id'] ) ) {
			return;
		}

		$user_id      = absint( $reservation['user_id'] );
		$actual_units = absint( $actual_units );
		$tokens       = absint( $tokens );
		$feature      = '' !== (string) ( $reservation['feature'] ?? '' ) ? (string) $reservation['feature'] : 'other';
		$record       = self::get_record( $user_id );

		// The reservation counted in this period's record; one from last
		// month was reset with it.
		if ( $record['period'] === ( $reservation['period'] ?? '' ) ) {
			$record['units'] -= absint( $reservation['units'] ?? 0 );
		}

		$record['units']                  = max( 0, $record['units'] + $actual_units );
		$record['requests']              += 1;
		$record['tokens']                += $tokens;
		$record['by_feature'][ $feature ] = absint( $record['by_feature'][ $feature ] ?? 0 ) + $actual_units;

		self::save_record( $user_id, $record );

		/**
		 * Fires after AI usage is recorded for a user.
		 *
		 * @since 3.2.0
		 *
		 * @param int    $user_id User ID.
		 * @param int    $units   Units used.
		 * @param string $feature What they were for.
		 * @param int    $tokens  Tokens the provider reported.
		 */
		do_action( 'pressprimer_quiz_ai_usage_recorded', $user_id, $actual_units, $feature, $tokens );
	}

	/**
	 * Give a reservation back in full (the call failed or returned nothing
	 * usable).
	 *
	 * @since 3.2.0
	 *
	 * @param array $reservation From reserve().
	 */
	public static function release( $reservation ) {
		if ( ! is_array( $reservation ) || empty( $reservation['user_id'] ) ) {
			return;
		}

		$user_id = absint( $reservation['user_id'] );
		$record  = self::get_record( $user_id );

		if ( $record['period'] !== ( $reservation['period'] ?? '' ) ) {
			return;
		}

		$record['units'] = max( 0, $record['units'] - absint( $reservation['units'] ?? 0 ) );
		self::save_record( $user_id, $record );
	}

	/**
	 * Tokens in a provider's usage report.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $usage Usage as the provider reported it (OpenAI or Anthropic shape).
	 * @return int Total tokens, or 0.
	 */
	public static function count_tokens( $usage ) {
		if ( ! is_array( $usage ) ) {
			return 0;
		}

		if ( isset( $usage['total_tokens'] ) ) {
			return absint( $usage['total_tokens'] );
		}

		$in  = absint( $usage['input_tokens'] ?? ( $usage['prompt_tokens'] ?? 0 ) );
		$out = absint( $usage['output_tokens'] ?? ( $usage['completion_tokens'] ?? 0 ) );

		return $in + $out;
	}

	/**
	 * Every user's usage this period, most first (for Settings).
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Rows of { user_id, name, units, requests, tokens, by_feature, limit }.
	 */
	public static function get_report() {
		global $wpdb;

		$period = self::get_period();

		// The record is stored serialized; this period's records contain its key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Settings report over user meta.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_KEY,
				'%' . $wpdb->esc_like( '"' . $period . '"' ) . '%'
			)
		);

		$rows = [];

		foreach ( array_unique( array_map( 'absint', $user_ids ) ) as $user_id ) {
			$user   = get_userdata( $user_id );
			$record = self::get_record( $user_id );

			if ( ! $user || ( $record['units'] <= 0 && $record['requests'] <= 0 ) ) {
				continue;
			}

			$rows[] = [
				'user_id'    => $user_id,
				'name'       => $user->display_name,
				'units'      => $record['units'],
				'requests'   => $record['requests'],
				'tokens'     => $record['tokens'],
				'by_feature' => $record['by_feature'],
				'limit'      => self::get_limit( $user_id ),
			];
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['units'] <=> $a['units'];
			}
		);

		return $rows;
	}

	/**
	 * A user's record for the current period (a new one when it's a new month).
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return array { period, units, requests, tokens, by_feature }.
	 */
	private static function get_record( $user_id ) {
		$period = self::get_period();
		$stored = $user_id ? get_user_meta( $user_id, self::META_KEY, true ) : [];

		if ( ! is_array( $stored ) || ( $stored['period'] ?? '' ) !== $period ) {
			$stored = [];
		}

		return [
			'period'     => $period,
			'units'      => absint( $stored['units'] ?? 0 ),
			'requests'   => absint( $stored['requests'] ?? 0 ),
			'tokens'     => absint( $stored['tokens'] ?? 0 ),
			'by_feature' => isset( $stored['by_feature'] ) && is_array( $stored['by_feature'] ) ? array_map( 'absint', $stored['by_feature'] ) : [],
		];
	}

	/**
	 * Store a user's record.
	 *
	 * @since 3.2.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $record  Record.
	 */
	private static function save_record( $user_id, array $record ) {
		if ( $user_id ) {
			update_user_meta( $user_id, self::META_KEY, $record );
		}
	}
}
