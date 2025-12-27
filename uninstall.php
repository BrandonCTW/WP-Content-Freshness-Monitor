<?php
/**
 * Uninstall handler for Content Freshness Monitor
 *
 * This file runs when the plugin is deleted through the WordPress admin.
 * It cleans up all plugin data from the database.
 *
 * @package ContentFreshnessMonitor
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Clear scheduled cron events.
$timestamp = wp_next_scheduled( 'cfm_send_stale_digest' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'cfm_send_stale_digest' );
}
wp_clear_scheduled_hook( 'cfm_send_stale_digest' );

// Clear author notification cron.
$author_timestamp = wp_next_scheduled( 'cfm_send_author_digest' );
if ( $author_timestamp ) {
    wp_unschedule_event( $author_timestamp, 'cfm_send_author_digest' );
}
wp_clear_scheduled_hook( 'cfm_send_author_digest' );

// Clear trends snapshot cron.
$trends_timestamp = wp_next_scheduled( 'cfm_daily_snapshot' );
if ( $trends_timestamp ) {
    wp_unschedule_event( $trends_timestamp, 'cfm_daily_snapshot' );
}
wp_clear_scheduled_hook( 'cfm_daily_snapshot' );

// Delete plugin options.
delete_option( 'cfm_settings' );
delete_option( 'cfm_stats_history' );

// Delete transient caches.
delete_transient( 'cfm_stats_cache' );
delete_transient( 'cfm_stale_count_cache' );
if ( is_multisite() ) {
    delete_site_transient( 'cfm_network_stats_cache' );
}

// Delete all post meta created by this plugin.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete(
    $wpdb->postmeta,
    array( 'meta_key' => '_cfm_last_reviewed' ),
    array( '%s' )
);

// Clear any cached data.
wp_cache_flush();
