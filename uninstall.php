<?php
/**
 * Uninstall Plugin — cleanup all data
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'aee_sources',
    $wpdb->prefix . 'aee_queue',
    $wpdb->prefix . 'aee_feedback',
    $wpdb->prefix . 'aee_dictionary',
    $wpdb->prefix . 'aee_style_log',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Delete options
delete_option( 'aee_settings' );
delete_option( 'aee_db_version' );

// Clear scheduled cron jobs
$cron_events = [ 'aee_fetch_content', 'aee_process_queue', 'aee_weekly_report' ];
foreach ( $cron_events as $event ) {
    $timestamp = wp_next_scheduled( $event );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $event );
    }
}

// Remove post meta from AI-generated posts
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_ai_generated' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_source_url' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_queue_id' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_original_content' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_meta_description' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aee_keywords' ] );
