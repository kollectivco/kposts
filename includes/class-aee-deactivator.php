<?php
/**
 * Plugin Deactivator
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;

class AEE_Deactivator {
    public static function deactivate(): void {
        // Clear all scheduled cron events
        $events = [ 'aee_fetch_content', 'aee_process_queue', 'aee_weekly_report' ];
        foreach ( $events as $event ) {
            wp_clear_scheduled_hook( $event );
        }
    }
}
