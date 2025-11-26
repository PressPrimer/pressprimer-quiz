<?php
/**
 * Banks List Table class
 *
 * Extends WP_List_Table to display question banks.
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
 * Banks List Table class
 *
 * @since 1.0.0
 */
class PPQ_Banks_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'ppq-bank',
				'plural'   => 'ppq-banks',
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
		return [
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'pressprimer-quiz' ),
			'description' => __( 'Description', 'pressprimer-quiz' ),
			'questions'   => __( 'Questions', 'pressprimer-quiz' ),
			'author'      => __( 'Author', 'pressprimer-quiz' ),
			'date'        => __( 'Date', 'pressprimer-quiz' ),
		];
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
			'questions' => [ 'question_count', false ],
			'author'    => [ 'author_id', false ],
			'date'      => [ 'created_at', true ],
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
		$actions = [];

		if ( current_user_can( 'ppq_manage_all' ) ) {
			$actions['delete'] = __( 'Delete', 'pressprimer-quiz' );
		}

		return $actions;
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		// Process bulk actions
		$this->process_bulk_action();

		// Columns
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		// Pagination
		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		// Build query
		$table = $wpdb->prefix . 'ppq_banks';

		$where = [ '1=1' ];
		$where_values = [];

		// Search
		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
			$where[] = '(name LIKE %s OR description LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
		}

		// Author filter (only show user's own if not admin)
		if ( ! current_user_can( 'ppq_manage_all' ) ) {
			$where[] = 'author_id = %d';
			$where_values[] = get_current_user_id();
		} elseif ( ! empty( $_REQUEST['author'] ) ) {
			$where[] = 'author_id = %d';
			$where_values[] = absint( $_REQUEST['author'] );
		}

		// No deleted banks
		$where[] = 'deleted_at IS NULL';

		$where_sql = implode( ' AND ', $where );

		// Ordering
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'created_at';
		$order = ! empty( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'DESC';

		// Validate orderby
		$allowed_orderby = [ 'name', 'question_count', 'author_id', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		// Validate order
		$order = ( 'ASC' === strtoupper( $order ) ) ? 'ASC' : 'DESC';

		// Get total count
		if ( ! empty( $where_values ) ) {
			$total_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$total_items = absint( $wpdb->get_var( $wpdb->prepare( $total_query, $where_values ) ) );
		} else {
			$total_items = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ) );
		}

		// Get items
		$items_query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );

		$results = $wpdb->get_results( $wpdb->prepare( $items_query, $query_values ) );

		// Convert to bank objects
		$this->items = [];
		if ( ! empty( $results ) && class_exists( 'PPQ_Bank' ) ) {
			foreach ( $results as $row ) {
				$bank = new PPQ_Bank();
				foreach ( $row as $key => $value ) {
					$bank->$key = $value;
				}
				$this->items[] = $bank;
			}
		}

		// Pagination
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
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
		if ( ! current_user_can( 'ppq_manage_all' ) ) {
			return;
		}

		// Get bank IDs
		$bank_ids = isset( $_REQUEST['bank'] ) ? array_map( 'absint', (array) $_REQUEST['bank'] ) : [];

		if ( empty( $bank_ids ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Delete banks
		if ( class_exists( 'PPQ_Bank' ) ) {
			foreach ( $bank_ids as $bank_id ) {
				$bank = PPQ_Bank::get( $bank_id );
				if ( $bank ) {
					$bank->delete();
				}
			}
		}

		// Redirect
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'message' => 'banks_deleted',
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
	 * @param object $item Bank object.
	 * @param string $column_name Column name.
	 * @return string Column content.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
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
	 * @param object $item Bank object.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bank[]" value="%d" />', absint( $item->id ) );
	}

	/**
	 * Name column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Bank object.
	 * @return string Name column HTML.
	 */
	protected function column_name( $item ) {
		$view_url = add_query_arg(
			[
				'page' => 'ppq-banks',
				'action' => 'view',
				'bank_id' => $item->id,
			],
			admin_url( 'admin.php' )
		);

		$edit_url = add_query_arg(
			[
				'page' => 'ppq-banks',
				'action' => 'edit',
				'bank_id' => $item->id,
			],
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'action' => 'ppq_delete_bank',
					'bank_id' => $item->id,
				],
				admin_url( 'admin-post.php' )
			),
			'ppq_delete_bank_' . $item->id
		);

		$title = '<strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $item->name ) . '</a></strong>';

		// Row actions
		$actions = [];
		$actions['view'] = '<a href="' . esc_url( $view_url ) . '">' . __( 'View', 'pressprimer-quiz' ) . '</a>';

		// Check ownership for edit/delete
		if ( current_user_can( 'ppq_manage_all' ) || absint( $item->author_id ) === get_current_user_id() ) {
			$actions['edit'] = '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'pressprimer-quiz' ) . '</a>';
			$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this bank?', 'pressprimer-quiz' ) ) . '\');">' . __( 'Delete', 'pressprimer-quiz' ) . '</a>';
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Description column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Bank object.
	 * @return string Description column HTML.
	 */
	protected function column_description( $item ) {
		if ( empty( $item->description ) ) {
			return '<span class="ppq-text-muted">' . esc_html__( '(no description)', 'pressprimer-quiz' ) . '</span>';
		}

		return esc_html( wp_trim_words( $item->description, 20 ) );
	}

	/**
	 * Author column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Bank object.
	 * @return string Author column HTML.
	 */
	protected function column_author( $item ) {
		$author = get_userdata( $item->author_id );
		if ( ! $author ) {
			return '<span class="ppq-text-muted">' . esc_html__( '(unknown)', 'pressprimer-quiz' ) . '</span>';
		}

		// If admin, make it a filter link
		if ( current_user_can( 'ppq_manage_all' ) ) {
			$filter_url = add_query_arg(
				[
					'page' => 'ppq-banks',
					'author' => $item->author_id,
				],
				admin_url( 'admin.php' )
			);
			return '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $author->display_name ) . '</a>';
		}

		return esc_html( $author->display_name );
	}

	/**
	 * Date column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Bank object.
	 * @return string Date column HTML.
	 */
	protected function column_date( $item ) {
		$date = mysql2date( get_option( 'date_format' ), $item->created_at );
		$time = mysql2date( get_option( 'time_format' ), $item->created_at );

		return sprintf(
			'%s<br><span class="ppq-text-muted">%s</span>',
			esc_html( $date ),
			esc_html( $time )
		);
	}

	/**
	 * Message when no items found
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No question banks found.', 'pressprimer-quiz' );
	}
}
