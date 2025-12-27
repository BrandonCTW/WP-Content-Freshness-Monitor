<?php
/**
 * Shortcodes for Content Freshness Monitor
 *
 * Provides shortcodes for displaying content freshness information in posts/pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CFM_Shortcodes {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Register shortcodes
	 */
	public function register_shortcodes() {
		add_shortcode( 'content_health_score', array( $this, 'health_score_shortcode' ) );
		add_shortcode( 'content_freshness_stats', array( $this, 'stats_shortcode' ) );
		add_shortcode( 'stale_content_count', array( $this, 'stale_count_shortcode' ) );
	}

	/**
	 * Enqueue frontend styles when shortcodes are used
	 */
	public function enqueue_frontend_styles() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check if any of our shortcodes are used
		$shortcodes = array( 'content_health_score', 'content_freshness_stats', 'stale_content_count' );
		$has_shortcode = false;

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$has_shortcode = true;
				break;
			}
		}

		if ( $has_shortcode ) {
			wp_enqueue_style(
				'cfm-frontend',
				plugins_url( 'assets/css/frontend.css', dirname( __FILE__ ) ),
				array(),
				CONTENT_FRESHNESS_MONITOR_VERSION
			);
		}
	}

	/**
	 * Content Health Score shortcode
	 *
	 * Usage: [content_health_score]
	 * Usage: [content_health_score show_score="yes" show_label="yes"]
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function health_score_shortcode( $atts ) {
		// Check capability - only logged-in users with edit_posts can see this
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'show_score' => 'yes',
				'show_label' => 'yes',
				'size'       => 'medium', // small, medium, large
			),
			$atts,
			'content_health_score'
		);

		$health = CFM_Scanner::get_health_score();

		$size_class = 'cfm-size-' . sanitize_key( $atts['size'] );

		$output = '<div class="cfm-health-score-widget ' . esc_attr( $size_class ) . '">';
		$output .= '<div class="cfm-grade-badge ' . esc_attr( $health['class'] ) . '">';
		$output .= '<span class="cfm-grade-letter">' . esc_html( $health['grade'] ) . '</span>';

		if ( 'yes' === $atts['show_score'] ) {
			$output .= '<span class="cfm-grade-score">' . esc_html( $health['score'] ) . '%</span>';
		}

		$output .= '</div>';

		if ( 'yes' === $atts['show_label'] ) {
			$output .= '<div class="cfm-grade-label">' . esc_html( $health['label'] ) . '</div>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Content Freshness Stats shortcode
	 *
	 * Usage: [content_freshness_stats]
	 * Usage: [content_freshness_stats layout="horizontal" show_percentage="yes"]
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function stats_shortcode( $atts ) {
		// Check capability
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'layout'          => 'horizontal', // horizontal, vertical, compact
				'show_percentage' => 'yes',
				'show_total'      => 'yes',
				'show_threshold'  => 'no',
			),
			$atts,
			'content_freshness_stats'
		);

		$stats = CFM_Scanner::get_stats();

		$layout_class = 'cfm-layout-' . sanitize_key( $atts['layout'] );

		$output = '<div class="cfm-stats-widget ' . esc_attr( $layout_class ) . '">';

		if ( 'yes' === $atts['show_total'] ) {
			$output .= '<div class="cfm-stat cfm-stat-total">';
			$output .= '<span class="cfm-stat-value">' . esc_html( number_format_i18n( $stats['total'] ) ) . '</span>';
			$output .= '<span class="cfm-stat-label">' . esc_html__( 'Total Posts', 'content-freshness-monitor' ) . '</span>';
			$output .= '</div>';
		}

		$output .= '<div class="cfm-stat cfm-stat-fresh">';
		$output .= '<span class="cfm-stat-value">' . esc_html( number_format_i18n( $stats['fresh'] ) ) . '</span>';
		$output .= '<span class="cfm-stat-label">' . esc_html__( 'Fresh', 'content-freshness-monitor' ) . '</span>';
		$output .= '</div>';

		$output .= '<div class="cfm-stat cfm-stat-stale">';
		$output .= '<span class="cfm-stat-value">' . esc_html( number_format_i18n( $stats['stale'] ) ) . '</span>';
		$output .= '<span class="cfm-stat-label">' . esc_html__( 'Stale', 'content-freshness-monitor' ) . '</span>';
		if ( 'yes' === $atts['show_percentage'] && $stats['total'] > 0 ) {
			$output .= '<span class="cfm-stat-percent">(' . esc_html( $stats['stale_percent'] ) . '%)</span>';
		}
		$output .= '</div>';

		if ( 'yes' === $atts['show_threshold'] ) {
			$output .= '<div class="cfm-stat cfm-stat-threshold">';
			$output .= '<span class="cfm-stat-value">' . esc_html( $stats['threshold'] ) . '</span>';
			$output .= '<span class="cfm-stat-label">' . esc_html__( 'Days Threshold', 'content-freshness-monitor' ) . '</span>';
			$output .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Stale Content Count shortcode
	 *
	 * Usage: [stale_content_count]
	 * Returns just the number of stale posts.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function stale_count_shortcode( $atts ) {
		// Check capability
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'format' => 'number', // number, text
			),
			$atts,
			'stale_content_count'
		);

		$stats = CFM_Scanner::get_stats();

		if ( 'text' === $atts['format'] ) {
			return sprintf(
				/* translators: %d: number of stale posts */
				_n(
					'%d stale post',
					'%d stale posts',
					$stats['stale'],
					'content-freshness-monitor'
				),
				$stats['stale']
			);
		}

		return number_format_i18n( $stats['stale'] );
	}
}

new CFM_Shortcodes();
