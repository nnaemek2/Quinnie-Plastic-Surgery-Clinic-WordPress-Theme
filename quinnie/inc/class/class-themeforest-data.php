<?php
/**
 * Themeforest Data class
 *
 * @author Jegtheme
 * @package quinnie
 */

namespace Quinnie;

/**
 * Class Api
 *
 * @package quinnie
 */
class Themeforest_Data {
	/**
	 * Endpoint Path
	 *
	 * @var string
	 */
	const ENDPOINT = 'gtb-themes-backend/v1';

	/**
	 * Blocks constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'theme_wizard' ) );
		add_action( 'admin_init', array( $this, 'theme_redirect' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend' ) );
	}

	/**
	 * Wizard Menu.
	 */
	public function theme_wizard() {
		if ( get_option( 'quinnie_wizard_setup_done' ) !== 'yes' ) {
			add_theme_page(
				'Wizard Setup',
				'Wizard Setup',
				'manage_options',
				'quinnie-wizard',
				array( $this, 'theme_wizard_page' ),
				99
			);
		}
	}
	
	/**
	 * Wizard Page.
	 */
	public function theme_wizard_page() {
		?>
		<div id="gutenverse-theme-wizard"></div>
		<?php
	}

	/**
	 * Check parameter.
	 */
	private function is_wizard_done() {
		return isset( $_GET['page'] ) && isset( $_GET['wizard_setup_done'] ) && $_GET['page'] === 'quinnie-dashboard' && $_GET['wizard_setup_done'] === 'yes';
	}

	public function theme_redirect() {
		if ( get_option( 'quinnie_wizard_init_done' ) !== 'yes' ) {
			update_option( 'quinnie_wizard_init_done', 'yes' );
			wp_safe_redirect( admin_url( 'admin.php?page=quinnie-wizard' ) );
			exit;
		}

		if ( $this->is_wizard_done() ) {
			update_option( 'quinnie_wizard_setup_done', 'yes' );
			wp_safe_redirect( admin_url( 'themes.php?page=quinnie-dashboard' ) );
		}
	}

	/**
	 * Register APIs
	 */
	public function register_routes() {
		if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/**
		 * Backend routes.
		 */

		// Themes.
		register_rest_route(
			self::ENDPOINT,
			'pages/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_pages' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', '--gctd--' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'import/menus',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_menus' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', '--gctd--' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);
	}

	/**
	 * Create pages and assign templates.
	 *
	 * @param object $request .
	 *
	 * @return int|string
	 */
	public function handle_pages( $request ) {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$title        = $request->get_param( 'title' );
		$active_theme = wp_get_theme();
		$theme_dir    = $active_theme->get_stylesheet_directory();
		$files        = glob( $theme_dir . '/gutenverse-pages/*' );
		$templates    = array();
		foreach( $files as $file ){
			$json_file_data = $wp_filesystem->get_contents( $file );
			$templates[]    = json_decode( $json_file_data, true );
		}
		$theme_url = get_template_directory_uri();
		foreach ( $templates as $value ) {
			$content = str_replace( '{{home_url}}', $theme_url, $value['content'] );
			$page_id = null;

			if ( ! empty( $value['core-patterns'] ) ) {
				$this->import_synced_patterns( $value['core-patterns'] );
			}

			if ( ! empty( $value['pro-patterns'] ) ) {
				$this->import_synced_patterns( $value['pro-patterns'] );
			}

			if ( ! empty( $value['gutenverse-patterns'] ) ) {
				$this->import_synced_patterns( $value['gutenverse-patterns'] );
			}

			if ( ! $title || $title === $value['pagetitle'] ) {
				$query = new \WP_Query(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'title'          => $value['pagetitle'],
						'posts_per_page' => 1,
					)
				);

				if ( $query->have_posts() ) {
					$existing_page = $query->posts[0];
					$page_id = $existing_page->ID;
					wp_update_post(
						array(
							'ID'            => $existing_page->ID,
							'page_template' => $value['template'],
						)
					);
				} else {
					$new_page = array(
						'post_title'    => $value['pagetitle'],
						'post_content'  => wp_slash( $content ),
						'post_status'   => 'publish',
						'post_type'     => 'page',
						'page_template' => $value['template'],
					);
					$page_id = wp_insert_post( $new_page );
				}

				if( $value['is_homepage'] && $page_id ){
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $page_id );
				}

				if ( $title ) {
					break;
				}
			}
		}

		return true;
	}

	/**
	 * Create Synced Pattern
	 *
	 * @param array $patterns .
	 */
	public function import_synced_patterns( $patterns ) {
		$pattern_list = get_option( 'quinnie_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			$pattern_list = array();
		}

		foreach ( $patterns as $block_pattern ) {
			$pattern_file = get_theme_file_path( '/inc/patterns/' . $block_pattern . '.php' );
			$pattern_data = require $pattern_file;

			if ( !! $pattern_data['is_sync'] ) {
				$post = get_page_by_path( $block_pattern . '-synced', OBJECT, 'wp_block' );

				if ( empty( $post ) ) {
					$post_id = wp_insert_post(
						array(
							'post_name'    => $block_pattern . '-synced',
							'post_title'   => $pattern_data['title'],
							'post_content' => wp_slash( $pattern_data['content'] ),
							'post_status'  => 'publish',
							'post_author'  => 1,
							'post_type'    => 'wp_block',
						)
					);
					if ( ! is_wp_error( $post_id ) ) {
						$pattern_category = $pattern_data['categories'];
						foreach( $pattern_category as $category ){
							wp_set_object_terms( $post_id, $category, 'wp_pattern_category' );
						}
					}

					$pattern_data['content']  = '<!-- wp:block {"ref":' . $post_id . '} /-->';
					$pattern_data['inserter'] = false;
					$pattern_data['slug']     = $block_pattern;

					$pattern_list[] = $pattern_data;
				}
			}
		}

		update_option( 'quinnie_synced_pattern_imported', $pattern_list );
	}

	/**
	 * Enqueue Backend Font
	 */
	public function enqueue_backend() {
		wp_enqueue_style(
			'quinnie-inter-font',
			QUINNIE_URI . '/assets/dashboard-fonts/inter/inter.css',
			array(),
			QUINNIE_VERSION
		);

		wp_enqueue_style(
			'quinnie-jakarta-sans-font',
			QUINNIE_URI . '/assets/dashboard-fonts/plus-jakarta-sans/plus-jakarta-sans.css',
			array(),
			QUINNIE_VERSION
		);
	}
}