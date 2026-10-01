<?php
/**
 * REST API Endpoints
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;

class AEE_REST_API {

    const NAMESPACE = 'ai-editorial/v1';

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        // GET /queue — list queue items
        register_rest_route( self::NAMESPACE, '/queue', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_queue' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'status' => [ 'type' => 'string', 'default' => '' ],
                'page'   => [ 'type' => 'integer', 'default' => 1 ],
                'per_page'=> [ 'type' => 'integer', 'default' => 20 ],
            ],
        ] );

        // POST /queue/{id}/process — process specific item
        register_rest_route( self::NAMESPACE, '/queue/(?P<id>\d+)/process', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'process_queue_item' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // POST /fetch — trigger content fetch
        register_rest_route( self::NAMESPACE, '/fetch', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'trigger_fetch' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'source_id' => [ 'type' => 'integer', 'default' => 0 ],
            ],
        ] );

        // GET /stats — plugin statistics
        register_rest_route( self::NAMESPACE, '/stats', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_stats' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // POST /test-api — test API connection
        register_rest_route( self::NAMESPACE, '/test-api', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'test_api' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'api' => [ 'type' => 'string', 'required' => true ],
                'key' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );

        // GET /sources — list sources
        register_rest_route( self::NAMESPACE, '/sources', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_sources' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );
    }

    // =========================================================================
    // ENDPOINT CALLBACKS
    // =========================================================================

    public function get_queue( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $status   = sanitize_text_field( $request->get_param( 'status' ) );
        $page     = intval( $request->get_param( 'page' ) );
        $per_page = min( 100, intval( $request->get_param( 'per_page' ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where = $status ? $wpdb->prepare( ' WHERE status = %s', $status ) : '';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aee_queue{$where}" );
        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aee_queue{$where} ORDER BY fetched_at DESC LIMIT {$per_page} OFFSET {$offset}",
            ARRAY_A
        );

        $response = new \WP_REST_Response( [
            'items' => $items,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
        ], 200 );

        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

        return $response;
    }

    public function process_queue_item( \WP_REST_Request $request ): \WP_REST_Response {
        $id = intval( $request->get_param( 'id' ) );

        global $wpdb;
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aee_queue WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $item ) {
            return new \WP_REST_Response( [ 'error' => 'Item not found' ], 404 );
        }

        // Trigger processing via automation
        $automation = new AEE_Automation();
        $automation->process_queue_batch( 1 );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Processing started',
        ], 200 );
    }

    public function trigger_fetch( \WP_REST_Request $request ): \WP_REST_Response {
        $source_id = intval( $request->get_param( 'source_id' ) );
        $fetcher   = new AEE_Content_Fetcher();

        if ( $source_id > 0 ) {
            global $wpdb;
            $source = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aee_sources WHERE id = %d", $source_id ),
                ARRAY_A
            );
            if ( ! $source ) return new \WP_REST_Response( [ 'error' => 'Source not found' ], 404 );
            $count = $fetcher->fetch_from_rss( $source );
        } else {
            $fetcher->run_scheduled_fetch();
            $count = -1; // all sources
        }

        return new \WP_REST_Response( [
            'success' => true,
            'fetched' => $count,
        ], 200 );
    }

    public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $queue_stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}aee_queue GROUP BY status",
            ARRAY_A
        );

        $feedback_stats = $wpdb->get_results(
            "SELECT feedback_type, COUNT(*) as count, ROUND(AVG(quality_score) * 100, 1) as avg_score
             FROM {$wpdb->prefix}aee_feedback GROUP BY feedback_type",
            ARRAY_A
        );

        $source_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aee_sources WHERE is_active = 1" );

        return new \WP_REST_Response( [
            'queue'           => $queue_stats,
            'feedback'        => $feedback_stats,
            'active_sources'  => $source_count,
            'plugin_version'  => AEE_VERSION,
        ], 200 );
    }

    public function test_api( \WP_REST_Request $request ): \WP_REST_Response {
        $api = sanitize_text_field( $request->get_param( 'api' ) );
        $key = sanitize_text_field( $request->get_param( 'key' ) );

        $result = match ( $api ) {
            'gemini' => ( new AEE_Gemini( $key ) )->test_connection(),
            'claude' => ( new AEE_Claude( $key ) )->test_connection(),
            'openai' => ( new AEE_OpenAI( $key ) )->test_connection(),
            'google' => ( new AEE_Translator( $key ) )->test_connection(),
            default  => 'Unknown API',
        };

        if ( $result === true ) {
            return new \WP_REST_Response( [ 'connected' => true ], 200 );
        }

        return new \WP_REST_Response( [ 'connected' => false, 'error' => $result ], 200 );
    }

    public function get_sources( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $sources = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}aee_sources ORDER BY name", ARRAY_A );
        return new \WP_REST_Response( $sources, 200 );
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }
}
