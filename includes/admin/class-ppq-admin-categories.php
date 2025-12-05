<?php
/**
 * Admin Categories class
 *
 * Handles WordPress admin interface for Categories and Tags.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Categories class
 *
 * Manages category and tag list tables and editor.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Admin_Categories {

	/**
	 * Current taxonomy (category or tag)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $taxonomy = 'category';

	/**
	 * Initialize admin functionality
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_post_ppq_save_category', [ $this, 'handle_save' ] );
		add_action( 'admin_post_ppq_delete_category', [ $this, 'handle_delete' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Render categories page
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$this->taxonomy = 'category';
		$this->render_taxonomy_page();
	}

	/**
	 * Render tags page
	 *
	 * @since 1.0.0
	 */
	public function render_tags() {
		$this->taxonomy = 'tag';
		$this->render_taxonomy_page();
	}

	/**
	 * Render taxonomy page (categories or tags)
	 *
	 * @since 1.0.0
	 */
	private function render_taxonomy_page() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing parameters
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Handle edit action
		if ( 'edit' === $action && $id ) {
			$this->render_edit_form( $id );
			return;
		}

		// Show list with add form
		$this->render_list_with_form();
	}

	/**
	 * Render list with add form
	 *
	 * Two-column layout: Add form on left, list table on right.
	 *
	 * @since 1.0.0
	 */
	private function render_list_with_form() {
		$is_category = ( 'category' === $this->taxonomy );
		$singular    = $is_category ? __( 'Category', 'pressprimer-quiz' ) : __( 'Tag', 'pressprimer-quiz' );
		$plural      = $is_category ? __( 'Categories', 'pressprimer-quiz' ) : __( 'Tags', 'pressprimer-quiz' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $plural ); ?></h1>

			<div id="col-container" class="wp-clearfix">
				<!-- Add Form Column -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php printf( esc_html__( 'Add New %s', 'pressprimer-quiz' ), esc_html( $singular ) ); ?></h2>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ppq-category-form">
								<?php wp_nonce_field( 'ppq_save_category', 'ppq_category_nonce' ); ?>
								<input type="hidden" name="action" value="ppq_save_category">
								<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->taxonomy ); ?>">
								<input type="hidden" name="return_url" value="<?php echo esc_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ); ?>">

								<!-- Name -->
								<div class="form-field form-required term-name-wrap">
									<label for="category_name"><?php esc_html_e( 'Name', 'pressprimer-quiz' ); ?> <span class="required">*</span></label>
									<input
										type="text"
										id="category_name"
										name="category_name"
										required
										maxlength="100"
										aria-required="true"
									>
									<p class="description">
										<?php printf( esc_html__( 'The name is how it appears on your site.', 'pressprimer-quiz' ) ); ?>
									</p>
								</div>

								<!-- Slug -->
								<div class="form-field term-slug-wrap">
									<label for="category_slug"><?php esc_html_e( 'Slug', 'pressprimer-quiz' ); ?></label>
									<input
										type="text"
										id="category_slug"
										name="category_slug"
										maxlength="100"
									>
									<p class="description">
										<?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'pressprimer-quiz' ); ?>
									</p>
								</div>

								<!-- Parent (categories only) -->
								<?php if ( $is_category ) : ?>
									<div class="form-field term-parent-wrap">
										<label for="category_parent"><?php esc_html_e( 'Parent Category', 'pressprimer-quiz' ); ?></label>
										<select name="category_parent" id="category_parent">
											<option value="0"><?php esc_html_e( 'None', 'pressprimer-quiz' ); ?></option>
											<?php echo $this->get_category_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped in get_category_options method ?>
										</select>
										<p class="description">
											<?php esc_html_e( 'Categories can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Optional.', 'pressprimer-quiz' ); ?>
										</p>
									</div>
								<?php endif; ?>

								<!-- Description -->
								<div class="form-field term-description-wrap">
									<label for="category_description"><?php esc_html_e( 'Description', 'pressprimer-quiz' ); ?></label>
									<textarea
										id="category_description"
										name="category_description"
										rows="5"
										maxlength="500"
									></textarea>
									<p class="description">
										<?php esc_html_e( 'The description is not prominent by default; however, some themes may show it.', 'pressprimer-quiz' ); ?>
									</p>
								</div>

								<p class="submit">
									<button type="submit" class="button button-primary">
										<?php printf( esc_html__( 'Add New %s', 'pressprimer-quiz' ), esc_html( $singular ) ); ?>
									</button>
								</p>
							</form>
						</div>
					</div>
				</div>

				<!-- List Table Column -->
				<div id="col-right">
					<div class="col-wrap">
						<?php
						$list_table = $this->get_list_table();
						$list_table->prepare_items();
						$list_table->display();
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render edit form
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Category ID.
	 */
	private function render_edit_form( $id ) {
		$category = PressPrimer_Quiz_Category::get( $id );

		if ( ! $category || $category->taxonomy !== $this->taxonomy ) {
			wp_die(
				esc_html__( 'Item not found.', 'pressprimer-quiz' ),
				esc_html__( 'Error', 'pressprimer-quiz' ),
				[ 'response' => 404 ]
			);
		}

		$is_category = ( 'category' === $this->taxonomy );
		$singular    = $is_category ? __( 'Category', 'pressprimer-quiz' ) : __( 'Tag', 'pressprimer-quiz' );
		$plural      = $is_category ? __( 'Categories', 'pressprimer-quiz' ) : __( 'Tags', 'pressprimer-quiz' );

		$page_slug = $is_category ? 'ppq-categories' : 'ppq-tags';
		$back_url  = admin_url( 'admin.php?page=' . $page_slug );

		?>
		<div class="wrap">
			<h1><?php printf( esc_html__( 'Edit %s', 'pressprimer-quiz' ), esc_html( $singular ) ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ppq-category-edit-form">
				<?php wp_nonce_field( 'ppq_save_category', 'ppq_category_nonce' ); ?>
				<input type="hidden" name="action" value="ppq_save_category">
				<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->taxonomy ); ?>">
				<input type="hidden" name="category_id" value="<?php echo esc_attr( $category->id ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $back_url ); ?>">

				<table class="form-table ppq-form-table">
					<tbody>
						<!-- Name -->
						<tr>
							<th scope="row">
								<label for="category_name"><?php esc_html_e( 'Name', 'pressprimer-quiz' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input
									type="text"
									id="category_name"
									name="category_name"
									value="<?php echo esc_attr( $category->name ); ?>"
									class="regular-text"
									required
									maxlength="100"
								>
							</td>
						</tr>

						<!-- Slug -->
						<tr>
							<th scope="row">
								<label for="category_slug"><?php esc_html_e( 'Slug', 'pressprimer-quiz' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="category_slug"
									name="category_slug"
									value="<?php echo esc_attr( $category->slug ); ?>"
									class="regular-text"
									maxlength="100"
								>
							</td>
						</tr>

						<!-- Parent (categories only) -->
						<?php if ( $is_category ) : ?>
							<tr>
								<th scope="row">
									<label for="category_parent"><?php esc_html_e( 'Parent Category', 'pressprimer-quiz' ); ?></label>
								</th>
								<td>
									<select name="category_parent" id="category_parent">
										<option value="0"><?php esc_html_e( 'None', 'pressprimer-quiz' ); ?></option>
										<?php echo $this->get_category_options( $category->parent_id, $category->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped in get_category_options method ?>
									</select>
								</td>
							</tr>
						<?php endif; ?>

						<!-- Description -->
						<tr>
							<th scope="row">
								<label for="category_description"><?php esc_html_e( 'Description', 'pressprimer-quiz' ); ?></label>
							</th>
							<td>
								<textarea
									id="category_description"
									name="category_description"
									rows="5"
									class="large-text"
									maxlength="500"
								><?php echo esc_textarea( $category->description ); ?></textarea>
							</td>
						</tr>

						<!-- Question Count (read-only) -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?>
							</th>
							<td>
								<strong><?php echo absint( $category->question_count ); ?></strong>
								<?php esc_html_e( 'questions', 'pressprimer-quiz' ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Update', 'pressprimer-quiz' ); ?>
					</button>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'pressprimer-quiz' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Get category options for select dropdown
	 *
	 * @since 1.0.0
	 *
	 * @param int $selected Selected category ID.
	 * @param int $exclude_id Category ID to exclude (for edit form).
	 * @param int $parent_id Parent ID for recursion.
	 * @param int $level Indentation level.
	 * @return string HTML options.
	 */
	private function get_category_options( $selected = 0, $exclude_id = 0, $parent_id = null, $level = 0 ) {
		$args = [
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		if ( null === $parent_id ) {
			// Get root categories
			$args['where'] = [
				'parent_id' => null,
				'taxonomy'  => 'category',
			];
		} else {
			$args['where'] = [
				'parent_id' => $parent_id,
				'taxonomy'  => 'category',
			];
		}

		$categories = PressPrimer_Quiz_Category::find( $args );
		$output     = '';

		foreach ( $categories as $category ) {
			// Skip if this is the category being edited
			if ( $category->id === $exclude_id ) {
				continue;
			}

			$indent        = str_repeat( '&nbsp;&nbsp;&nbsp;', $level );
			$selected_attr = ( $category->id === $selected ) ? ' selected' : '';

			$output .= sprintf(
				'<option value="%d"%s>%s%s</option>',
				intval( $category->id ),
				esc_attr( $selected_attr ),
				$indent, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML entities for indentation
				esc_html( $category->name )
			);

			// Recursively get children
			$output .= $this->get_category_options( $selected, $exclude_id, $category->id, $level + 1 );
		}

		return $output;
	}

	/**
	 * Get list table instance
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Categories_List_Table List table instance.
	 */
	private function get_list_table() {
		require_once __DIR__ . '/class-ppq-categories-list-table.php';
		return new PressPrimer_Quiz_Categories_List_Table( $this->taxonomy );
	}

	/**
	 * Handle category/tag save
	 *
	 * @since 1.0.0
	 */
	public function handle_save() {
		// Verify nonce
		if ( ! isset( $_POST['ppq_category_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_category_nonce'] ) ), 'ppq_save_category' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$taxonomy    = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : 'category';
		$category_id = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;
		$return_url  = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : admin_url( 'admin.php?page=ppq-categories' );

		$data = [
			'name'        => isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '',
			'slug'        => isset( $_POST['category_slug'] ) ? sanitize_title( wp_unslash( $_POST['category_slug'] ) ) : '',
			'description' => isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : '',
			'taxonomy'    => $taxonomy,
		];

		// Handle parent for categories
		if ( 'category' === $taxonomy && isset( $_POST['category_parent'] ) ) {
			$parent_id         = absint( wp_unslash( $_POST['category_parent'] ) );
			$data['parent_id'] = $parent_id > 0 ? $parent_id : null;
		}

		if ( $category_id > 0 ) {
			// Update existing
			$category = PressPrimer_Quiz_Category::get( $category_id );

			if ( ! $category ) {
				wp_die( esc_html__( 'Category not found.', 'pressprimer-quiz' ) );
			}

			foreach ( $data as $key => $value ) {
				$category->$key = $value;
			}

			$result  = $category->save();
			$message = 'updated';
		} else {
			// Create new
			$data['created_by'] = get_current_user_id();
			$result             = PressPrimer_Quiz_Category::create( $data );
			$message            = 'added';
		}

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[ 'message' => $message ],
				$return_url
			)
		);
		exit;
	}

	/**
	 * Handle category/tag delete
	 *
	 * @since 1.0.0
	 */
	public function handle_delete() {
		// Verify id is set
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ppq_delete_category_' . absint( wp_unslash( $_GET['id'] ) ) ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$id       = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';

		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid ID.', 'pressprimer-quiz' ) );
		}

		$category = PressPrimer_Quiz_Category::get( $id );

		if ( ! $category ) {
			wp_die( esc_html__( 'Item not found.', 'pressprimer-quiz' ) );
		}

		// Delete category
		$result = $category->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		$page_slug = ( 'tag' === $taxonomy ) ? 'ppq-tags' : 'ppq-categories';
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => $page_slug,
					'message' => 'deleted',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $page, [ 'ppq-categories', 'ppq-tags' ], true ) ) {
			return;
		}

		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = sanitize_key( wp_unslash( $_GET['message'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$class = 'notice notice-success is-dismissible';
		$text  = '';

		switch ( $message ) {
			case 'added':
				$text = __( 'Item added successfully.', 'pressprimer-quiz' );
				break;
			case 'updated':
				$text = __( 'Item updated successfully.', 'pressprimer-quiz' );
				break;
			case 'deleted':
				$text = __( 'Item deleted successfully.', 'pressprimer-quiz' );
				break;
		}

		if ( $text ) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
		}
	}
}
