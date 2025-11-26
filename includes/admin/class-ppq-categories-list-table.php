<?php
/**
 * Categories List Table class
 *
 * Extends WP_List_Table to display categories and tags.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Categories List Table class
 *
 * @since 1.0.0
 */
class PPQ_Categories_List_Table extends WP_List_Table {

	/**
	 * Taxonomy type
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy type ('category' or 'tag').
	 */
	public function __construct( $taxonomy = 'category' ) {
		$this->taxonomy = $taxonomy;

		parent::__construct(
			[
				'singular' => 'ppq-' . $taxonomy,
				'plural'   => 'ppq-' . $taxonomy . 's',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get table columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Column headers.
	 */
	public function get_columns() {
		$columns = [
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'pressprimer-quiz' ),
			'description' => __( 'Description', 'pressprimer-quiz' ),
			'slug'        => __( 'Slug', 'pressprimer-quiz' ),
			'questions'   => __( 'Questions', 'pressprimer-quiz' ),
		];

		// Add parent column for categories
		if ( 'category' === $this->taxonomy ) {
			$columns = array_slice( $columns, 0, 2, true ) +
				[ 'parent' => __( 'Parent', 'pressprimer-quiz' ) ] +
				array_slice( $columns, 2, null, true );
		}

		return $columns;
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable columns.
	 */
	protected function get_sortable_columns() {
		return [
			'name'      => [ 'name', false ],
			'slug'      => [ 'slug', false ],
			'questions' => [ 'question_count', false ],
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 *
	 * @return array Bulk actions.
	 */
	protected function get_bulk_actions() {
		return [
			'delete' => __( 'Delete', 'pressprimer-quiz' ),
		];
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		// Process bulk actions
		$this->process_bulk_action();

		// Columns
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		// Build query args
		$args = [
			'where' => [ 'taxonomy' => $this->taxonomy ],
		];

		// Search
		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			global $wpdb;
			$args['where_raw'] = $wpdb->prepare(
				'(name LIKE %s OR description LIKE %s)',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		// Ordering
		$args['order_by'] = ! empty( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'name';
		$args['order'] = ! empty( $_REQUEST['order'] ) && 'desc' === strtolower( $_REQUEST['order'] ) ? 'DESC' : 'ASC';

		// Get all items (no pagination for now, can add later if needed)
		$this->items = PPQ_Category::find( $args );

		// Set pagination (currently showing all)
		$this->set_pagination_args(
			[
				'total_items' => count( $this->items ),
				'per_page'    => count( $this->items ),
				'total_pages' => 1,
			]
		);
	}

	/**
	 * Process bulk actions
	 *
	 * @since 1.0.0
	 */
	protected function process_bulk_action() {
		// Check for delete action
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			return;
		}

		// Get IDs
		$ids = isset( $_REQUEST[ $this->_args['singular'] ] ) ? array_map( 'absint', (array) $_REQUEST[ $this->_args['singular'] ] ) : [];

		if ( empty( $ids ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Delete items
		foreach ( $ids as $id ) {
			$category = PPQ_Category::get( $id );
			if ( $category ) {
				$category->delete();
			}
		}

		// Redirect
		$page_slug = ( 'tag' === $this->taxonomy ) ? 'ppq-tags' : 'ppq-categories';
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => $page_slug,
					'message' => 'deleted',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Default column display
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @param string $column_name Column name.
	 * @return string Column content.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'description':
				return esc_html( $item->description );
			case 'slug':
				return '<code>' . esc_html( $item->slug ) . '</code>';
			case 'questions':
				return absint( $item->question_count );
			default:
				return '';
		}
	}

	/**
	 * Checkbox column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="%s[]" value="%d" />', esc_attr( $this->_args['singular'] ), absint( $item->id ) );
	}

	/**
	 * Name column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Name column HTML.
	 */
	protected function column_name( $item ) {
		$page_slug = ( 'tag' === $this->taxonomy ) ? 'ppq-tags' : 'ppq-categories';

		$edit_url = add_query_arg(
			[
				'page' => $page_slug,
				'action' => 'edit',
				'id' => $item->id,
			],
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'ppq_delete_category',
					'id' => $item->id,
					'taxonomy' => $this->taxonomy,
				],
				admin_url( 'admin-post.php' )
			),
			'ppq_delete_category_' . $item->id
		);

		$title = '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->name ) . '</a></strong>';

		// Row actions
		$actions = [];
		$actions['edit'] = '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'pressprimer-quiz' ) . '</a>';
		$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this item?', 'pressprimer-quiz' ) ) . '\');">' . __( 'Delete', 'pressprimer-quiz' ) . '</a>';

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Parent column (categories only)
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Parent column HTML.
	 */
	protected function column_parent( $item ) {
		if ( empty( $item->parent_id ) ) {
			return '<span class="ppq-text-muted">' . esc_html__( '(none)', 'pressprimer-quiz' ) . '</span>';
		}

		$parent = PPQ_Category::get( $item->parent_id );
		if ( ! $parent ) {
			return '<span class="ppq-text-muted">' . esc_html__( '(unknown)', 'pressprimer-quiz' ) . '</span>';
		}

		return esc_html( $parent->name );
	}

	/**
	 * Message when no items found
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		if ( 'tag' === $this->taxonomy ) {
			esc_html_e( 'No tags found.', 'pressprimer-quiz' );
		} else {
			esc_html_e( 'No categories found.', 'pressprimer-quiz' );
		}
	}
}
