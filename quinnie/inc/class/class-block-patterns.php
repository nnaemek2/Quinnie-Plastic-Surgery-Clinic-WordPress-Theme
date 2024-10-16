<?php
/**
 * Block Pattern Class
 *
 * @author Jegstudio
 * @package quinnie
 */

namespace Quinnie;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Block_Pattern_Categories_Registry;

/**
 * Init Class
 *
 * @package quinnie
 */
class Block_Patterns {

	/**
	 * Instance variable
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Class instance.
	 *
	 * @return BlockPatterns
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->register_block_patterns();
		$this->register_synced_patterns();
	}

	/**
	 * Register Block Patterns
	 */
	private function register_block_patterns() {
		$block_pattern_categories = array(
			'quinnie-core' => array( 'label' => __( 'Quinnie Core Patterns', 'quinnie' ) ),
		);

		if ( defined( 'GUTENVERSE' ) ) {
			$block_pattern_categories['quinnie-gutenverse'] = array( 'label' => __( 'Quinnie Gutenverse Patterns', 'quinnie' ) );
			$block_pattern_categories['quinnie-pro'] = array( 'label' => __( 'Quinnie Gutenverse PRO Patterns', 'quinnie' ) );
		}

		$block_pattern_categories = apply_filters( 'quinnie_block_pattern_categories', $block_pattern_categories );

		foreach ( $block_pattern_categories as $name => $properties ) {
			if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $name ) ) {
				register_block_pattern_category( $name, $properties );
			}
		}

		$block_patterns = array(
            
		);

		if ( defined( 'GUTENVERSE' ) ) {
            $block_patterns[] = 'quinnie-gutenverse-header';			$block_patterns[] = 'quinnie-gutenverse-footer';			$block_patterns[] = 'quinnie-404-gutenverse-hero';			$block_patterns[] = 'quinnie-single-post-gutenverse-hero';			$block_patterns[] = 'quinnie-single-post-gutenverse-content';			$block_patterns[] = 'quinnie-index-gutenverse-hero';			$block_patterns[] = 'quinnie-archive-gutenverse-hero';			$block_patterns[] = 'quinnie-search-gutenverse-hero';
            
		}

		$block_patterns = apply_filters( 'quinnie_block_patterns', $block_patterns );
		$pattern_list   = get_option( 'quinnie_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			$pattern_list = array();
		}

		if ( function_exists( 'register_block_pattern' ) ) {
			foreach ( $block_patterns as $block_pattern ) {
				$pattern_file = get_theme_file_path( '/inc/patterns/' . $block_pattern . '.php' );
				$pattern_data = require $pattern_file;

				if ( (bool) $pattern_data['is_sync'] ) {
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
				} else {
					register_block_pattern(
						'quinnie/' . $block_pattern,
						require $pattern_file
					);
				}
			}
			update_option( 'quinnie_synced_pattern_imported', $pattern_list );
		}
	}

	/**
	 * Register Synced Patterns
	 */
	 private function register_synced_patterns() {
		$patterns = get_option( 'quinnie_synced_pattern_imported' );

		 foreach ( $patterns as $block_pattern ) {
			 register_block_pattern(
				'quinnie/' . $block_pattern['slug'],
				$block_pattern
			);
		 }
	 }
}
