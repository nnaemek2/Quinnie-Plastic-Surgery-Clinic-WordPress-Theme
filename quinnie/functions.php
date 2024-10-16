<?php
/**
 * Theme Functions
 *
 * @author Jegtheme
 * @package quinnie
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'QUINNIE_VERSION' ) || define( 'QUINNIE_VERSION', '1.0.0' );
defined( 'QUINNIE_DIR' ) || define( 'QUINNIE_DIR', trailingslashit( get_template_directory() ) );
defined( 'QUINNIE_URI' ) || define( 'QUINNIE_URI', trailingslashit( get_template_directory_uri() ) );

require get_parent_theme_file_path( 'inc/autoload.php' );

Quinnie\Init::instance();
