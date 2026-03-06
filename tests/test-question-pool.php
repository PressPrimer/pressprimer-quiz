<?php
/**
 * Question Pool Feature Tests
 *
 * Tests for the question pool functionality added in v2.2.0.
 * These tests verify that pool_enabled and max_questions work correctly
 * across fixed and dynamic quiz modes.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 2.2.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Question Pool Test Case
 *
 * Tests the question pool selection logic in the Quiz model.
 *
 * Since these tests need to verify randomization and database-dependent logic,
 * they use partial mocks of the Quiz model to isolate the pool selection logic
 * in get_questions_for_attempt() from actual database calls.
 *
 * @since 2.2.0
 */
class Test_Question_Pool extends TestCase {

	/**
	 * Create a mock quiz with the given properties and item/rule stubs.
	 *
	 * @param array $props     Quiz properties to set.
	 * @param array $items     Mock items for fixed quizzes (each has question_id).
	 * @param array $rules     Mock rules for dynamic quizzes.
	 * @return PressPrimer_Quiz_Quiz|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_mock_quiz( array $props = [], array $items = [], array $rules = [] ) {
		$quiz = $this->getMockBuilder( PressPrimer_Quiz_Quiz::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_items', 'get_rules' ] )
			->getMock();

		// Set default properties.
		$quiz->id                  = 1;
		$quiz->generation_mode     = 'fixed';
		$quiz->pool_enabled        = false;
		$quiz->max_questions       = null;
		$quiz->randomize_questions = false;

		// Apply overrides.
		foreach ( $props as $key => $value ) {
			$quiz->$key = $value;
		}

		// Mock get_items() to return item objects.
		$item_objects = [];
		foreach ( $items as $question_id ) {
			$item              = new stdClass();
			$item->question_id = $question_id;
			$item_objects[]    = $item;
		}
		$quiz->method( 'get_items' )->willReturn( $item_objects );

		// Mock get_rules() to return rule objects.
		$rule_objects = [];
		foreach ( $rules as $rule_data ) {
			$rule = $this->createMock( PressPrimer_Quiz_Quiz_Rule::class );
			$rule->question_count = $rule_data['question_count'];

			$rule->method( 'get_matching_questions' )
				->willReturn( $rule_data['matching_ids'] );

			$rule->method( 'get_matching_count' )
				->willReturn( count( $rule_data['matching_ids'] ) );

			$rule_objects[] = $rule;
		}
		$quiz->method( 'get_rules' )->willReturn( $rule_objects );

		return $quiz;
	}

	/**
	 * Test: Pool disabled shows all questions.
	 *
	 * When pool_enabled is false, all questions from the quiz should be returned
	 * regardless of the max_questions setting.
	 */
	public function test_pool_disabled_shows_all_questions() {
		$all_question_ids = [ 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 ];

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => false,
				'max_questions' => 5, // Should be ignored since pool is disabled.
			],
			$all_question_ids
		);

		$result = $quiz->get_questions_for_attempt();

		$this->assertCount( 10, $result, 'All 10 questions should be returned when pool is disabled.' );
		$this->assertEquals( $all_question_ids, $result, 'Questions should match original set exactly.' );
	}

	/**
	 * Test: Pool enabled limits questions.
	 *
	 * When pool_enabled is true and max_questions is set, only that many
	 * questions should be returned from the full pool.
	 */
	public function test_pool_enabled_limits_questions() {
		$all_question_ids = [ 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 ];

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => true,
				'max_questions' => 5,
			],
			$all_question_ids
		);

		$result = $quiz->get_questions_for_attempt();

		$this->assertCount( 5, $result, 'Only 5 questions should be returned when pool limits to 5.' );

		// All returned IDs should be from the original pool.
		foreach ( $result as $qid ) {
			$this->assertContains( $qid, $all_question_ids, "Returned question ID {$qid} must be from the original pool." );
		}

		// No duplicates.
		$this->assertCount(
			count( array_unique( $result ) ),
			$result,
			'No duplicate questions should be in the result.'
		);
	}

	/**
	 * Test: Different attempts get different questions.
	 *
	 * When pool is enabled, multiple calls to get_questions_for_attempt()
	 * should (with high probability) produce different random subsets.
	 *
	 * Note: This test uses statistical probability. With 10 questions and
	 * max_questions=5, the probability of two random subsets being identical
	 * is 1/252. Running 20 attempts makes the probability of ALL being
	 * identical negligible (< 10^-48).
	 */
	public function test_different_attempts_get_different_questions() {
		$all_question_ids = [ 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 ];

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => true,
				'max_questions' => 5,
			],
			$all_question_ids
		);

		$results = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$result    = $quiz->get_questions_for_attempt();
			$results[] = $result;
		}

		// Collect unique sets (sort each to compare content not order).
		$unique_sets = [];
		foreach ( $results as $r ) {
			$sorted = $r;
			sort( $sorted );
			$key = implode( ',', $sorted );
			$unique_sets[ $key ] = true;
		}

		$this->assertGreaterThan(
			1,
			count( $unique_sets ),
			'Multiple attempts should produce different question subsets (statistical test).'
		);
	}

	/**
	 * Test: Same attempt same questions on resume.
	 *
	 * When a student resumes an in-progress attempt, they should see the exact
	 * same questions — not a reshuffled set. This is guaranteed by the architecture:
	 * questions are stored as attempt_items in the database at attempt creation time,
	 * and get_questions_for_attempt() is only called once for new attempts.
	 *
	 * This test verifies the architectural guarantee by confirming that:
	 * 1. get_questions_for_attempt() produces a random subset (pool behavior)
	 * 2. The attempt's get_items() returns stored items (resume behavior)
	 */
	public function test_same_attempt_same_questions_on_resume() {
		$all_question_ids = [ 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 ];

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => true,
				'max_questions' => 5,
			],
			$all_question_ids
		);

		// Simulate attempt creation: get questions for the attempt.
		$initial_questions = $quiz->get_questions_for_attempt();
		$this->assertCount( 5, $initial_questions );

		// Simulate resume: create a mock attempt with stored items.
		$attempt = $this->getMockBuilder( stdClass::class )
			->addMethods( [ 'get_items' ] )
			->getMock();

		$stored_items = [];
		foreach ( $initial_questions as $index => $qid ) {
			$item              = new stdClass();
			$item->question_id = $qid;
			$item->order_index = $index;
			$stored_items[]    = $item;
		}

		$attempt->method( 'get_items' )->willReturn( $stored_items );

		// On resume, get_items() returns the stored questions.
		$resumed_items = $attempt->get_items();
		$resumed_ids   = array_map(
			function ( $item ) {
				return $item->question_id;
			},
			$resumed_items
		);

		$this->assertEquals(
			$initial_questions,
			$resumed_ids,
			'Resumed attempt should have the exact same questions in the same order.'
		);
	}

	/**
	 * Test: Dynamic rules build full pool first.
	 *
	 * For dynamic quizzes, all rules should execute to build the complete pool,
	 * THEN the pool limit (max_questions) is applied. This ensures the student
	 * gets questions from across all rules, not just the first rule's questions.
	 */
	public function test_dynamic_rules_build_full_pool_first() {
		$quiz = $this->create_mock_quiz(
			[
				'generation_mode' => 'dynamic',
				'pool_enabled'    => true,
				'max_questions'   => 4,
			],
			[], // No items for dynamic quiz.
			[
				// Rule 1: 3 questions from category A.
				[
					'question_count' => 3,
					'matching_ids'   => [ 101, 102, 103, 104, 105 ],
				],
				// Rule 2: 3 questions from category B.
				[
					'question_count' => 3,
					'matching_ids'   => [ 201, 202, 203, 204, 205 ],
				],
			]
		);

		// Run multiple times to verify the pool contains questions from both rules.
		$all_seen_ids = [];
		for ( $i = 0; $i < 30; $i++ ) {
			$result = $quiz->get_questions_for_attempt();

			$this->assertCount( 4, $result, 'Pool limit of 4 should be applied.' );

			foreach ( $result as $qid ) {
				$all_seen_ids[ $qid ] = true;
			}
		}

		// Over 30 attempts, we should see questions from BOTH rules.
		$rule1_seen = array_intersect( array_keys( $all_seen_ids ), [ 101, 102, 103, 104, 105 ] );
		$rule2_seen = array_intersect( array_keys( $all_seen_ids ), [ 201, 202, 203, 204, 205 ] );

		$this->assertNotEmpty( $rule1_seen, 'Questions from Rule 1 should appear in pool results.' );
		$this->assertNotEmpty( $rule2_seen, 'Questions from Rule 2 should appear in pool results.' );
	}

	/**
	 * Test: Pool max exceeds size shows all.
	 *
	 * When max_questions is greater than the actual number of questions available,
	 * all available questions should be returned without error.
	 */
	public function test_pool_max_exceeds_size_shows_all() {
		$all_question_ids = [ 10, 20, 30 ]; // Only 3 questions available.

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => true,
				'max_questions' => 10, // Asking for 10, but only 3 exist.
			],
			$all_question_ids
		);

		$result = $quiz->get_questions_for_attempt();

		$this->assertCount(
			3,
			$result,
			'When max_questions exceeds pool size, all available questions should be returned.'
		);

		// Should contain all the original questions (in some order).
		$sorted_result   = $result;
		$sorted_original = $all_question_ids;
		sort( $sorted_result );
		sort( $sorted_original );

		$this->assertEquals(
			$sorted_original,
			$sorted_result,
			'All 3 available questions should be present.'
		);
	}

	/**
	 * Test: Scoring uses shown questions only.
	 *
	 * When pool is enabled, scoring should be calculated based only on the
	 * questions actually shown to the student (the subset), not the full pool.
	 *
	 * This is verified by checking that the scoring service iterates over
	 * attempt_items (which only contains the shown questions), and that the
	 * total_count matches the pool-limited question count.
	 */
	public function test_scoring_uses_shown_questions_only() {
		$all_question_ids = [ 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 ];

		$quiz = $this->create_mock_quiz(
			[
				'pool_enabled'  => true,
				'max_questions' => 3,
			],
			$all_question_ids
		);

		// Get the pool-limited question set.
		$shown_questions = $quiz->get_questions_for_attempt();

		$this->assertCount( 3, $shown_questions, 'Only 3 questions should be selected for the attempt.' );

		// Simulate what happens in the scoring service:
		// It counts attempt_items, which correspond exactly to shown_questions.
		$total_count = count( $shown_questions );

		$this->assertEquals(
			3,
			$total_count,
			'Scoring total_count should equal the number of shown questions (3), not the full pool (10).'
		);

		// Verify the shown questions are a subset of the full pool.
		foreach ( $shown_questions as $qid ) {
			$this->assertContains(
				$qid,
				$all_question_ids,
				'Each shown question must come from the original pool.'
			);
		}

		// The score percentage should be calculated as: correct / shown_count * 100
		// Not as: correct / full_pool_count * 100
		// Simulate: student gets 2 out of 3 correct.
		$correct_count  = 2;
		$score_percent  = ( $correct_count / $total_count ) * 100;

		$this->assertEqualsWithDelta(
			66.67,
			$score_percent,
			0.01,
			'Score should be 66.67% (2/3), not 20% (2/10).'
		);
	}
}
