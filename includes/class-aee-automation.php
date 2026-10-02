<?php
/**
 * Automation Engine — WP-Cron workflows
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Automation {

    /**
     * تسجيل Cron Jobs
     */
    public static function register_cron_jobs(): void {
        // Add 30-minute cron interval
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );

        // Fetch content every hour
        if ( ! wp_next_scheduled( 'aee_fetch_content' ) ) {
            wp_schedule_event( time(), 'hourly', 'aee_fetch_content' );
        }

        // Process queue every 30 min
        if ( ! wp_next_scheduled( 'aee_process_queue' ) ) {
            wp_schedule_event( time(), 'aee_half_hour', 'aee_process_queue' );
        }

        // Weekly report
        if ( ! wp_next_scheduled( 'aee_weekly_report' ) ) {
            wp_schedule_event( time(), 'weekly', 'aee_weekly_report' );
        }
    }

    /**
     * إضافة فترات cron مخصصة
     */
    public static function add_cron_intervals( array $schedules ): array {
        $schedules['aee_half_hour'] = [
            'interval' => 1800,
            'display'  => __( 'كل 30 دقيقة', 'ai-editorial-engine' ),
        ];
        return $schedules;
    }

    /**
     * معالجة دفعة من Queue
     *
     * @param int $limit عدد العناصر في الدفعة
     */
    public function process_queue_batch( int $limit = 5 ): void {
        global $wpdb;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aee_queue
                 WHERE status = 'pending'
                 ORDER BY fetched_at ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if ( empty( $items ) ) return;

        $translator   = new AEE_Translator();
        $orchestrator = new AEE_AI_Orchestrator();
        $style_engine = new AEE_Egyptian_Style();
        $brand        = new AEE_Brand_Enforcer();

        $intensity = floatval( aee_get_setting( 'colloquial_intensity', 0.3 ) );

        foreach ( $items as $item ) {
            $this->process_single_item( $item, $translator, $orchestrator, $style_engine, $brand, $intensity );
        }
    }

    /**
     * Public wrapper — معالجة عنصر محدد بـ ID (للاستخدام من Admin AJAX)
     *
     * @param array $item Queue row as ARRAY_A
     */
    public function process_single_item_public( array $item ): void {
        $translator   = new AEE_Translator();
        $orchestrator = new AEE_AI_Orchestrator();
        $style_engine = new AEE_Egyptian_Style();
        $brand        = new AEE_Brand_Enforcer();
        $intensity    = floatval( aee_get_setting( 'colloquial_intensity', 0.3 ) );
        $this->process_single_item( $item, $translator, $orchestrator, $style_engine, $brand, $intensity );
    }

    /**
     * معالجة عنصر واحد من Queue
     *
     * @param array              $item
     * @param AEE_Translator     $translator
     * @param AEE_AI_Orchestrator $orchestrator
     * @param AEE_Egyptian_Style  $style_engine
     * @param AEE_Brand_Enforcer  $brand
     * @param float              $intensity
     */
    private function process_single_item(
        array $item,
        AEE_Translator $translator,
        AEE_AI_Orchestrator $orchestrator,
        AEE_Egyptian_Style $style_engine,
        AEE_Brand_Enforcer $brand,
        float $intensity
    ): void {
        $start = microtime( true );
        $id    = intval( $item['id'] );

        try {
            // Step 1: Mark as processing
            $this->update_queue_status( $id, 'translating' );

            // Step 2: Translate if not Arabic
            $content = $item['original_content'];
            $lang    = $item['original_language'];

            if ( $lang !== 'ar' ) {
                $translated = $translator->translate_with_fallback( $content );
                if ( ! empty( $translated ) ) {
                    $content = $translated;
                    $this->update_queue_field( $id, 'translated_content', $translated );
                }
            }

            // Step 3: Skip AI processing completely as per user request
            // We just use the translated content
            $this->update_queue_status( $id, 'processing' );
            $processed = $content;

            // Step 4: Apply Egyptian style
            $styled = $style_engine->apply_style( $processed, $intensity );

            // Step 5: Apply brand enforcer
            $final = $brand->enforce( $styled );

            // Step 6: Save processed content
            $this->update_queue_field( $id, 'processed_content', $final );
            $this->update_queue_field( $id, 'ai_model_used', $ai_result['model_used'] ?? '' );

            // Step 7: Create WordPress Draft
            $post_id = $this->create_wp_draft( [
                'title'            => $ai_result['title'] ?: $item['original_title'],
                'content'          => $final,
                'meta_description' => $ai_result['meta_description'] ?? '',
                'keywords'         => $ai_result['keywords'] ?? '',
                'source_url'       => $item['original_url'],
                'queue_id'         => $id,
            ] );

            // Step 8: Update queue as ready
            $elapsed = microtime( true ) - $start;
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'aee_queue',
                [
                    'status'          => 'ready',
                    'wp_post_id'      => $post_id,
                    'processing_time' => round( $elapsed, 2 ),
                ],
                [ 'id' => $id ],
                [ '%s', '%d', '%f' ],
                [ '%d' ]
            );

            // Step 9: Notify admin
            $this->notify_admin( $post_id, $ai_result['title'] ?: $item['original_title'] );

            // Step 10: Auto-publish if configured
            if ( aee_get_setting( 'auto_publish', false ) ) {
                wp_publish_post( $post_id );
                $this->update_queue_status( $id, 'published' );
            }

        } catch ( \Exception $e ) {
            $this->fail_item( $id, $e->getMessage() );
        }
    }

    /**
     * إنشاء مسودة WordPress
     *
     * @param array $data
     * @return int Post ID
     */
    public function create_wp_draft( array $data ): int {
        $category_id = $this->get_or_create_category();

        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $data['title'] ),
            'post_content' => wp_kses_post( $data['content'] ),
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => 1,
            'post_category'=> $category_id ? [ $category_id ] : [],
            'meta_input'   => [
                '_aee_source_url'       => esc_url_raw( $data['source_url'] ?? '' ),
                '_aee_queue_id'         => intval( $data['queue_id'] ?? 0 ),
                '_aee_ai_generated'     => 1,
                '_aee_meta_description' => sanitize_text_field( $data['meta_description'] ?? '' ),
                '_aee_keywords'         => sanitize_text_field( $data['keywords'] ?? '' ),
                '_aee_original_content' => $data['content'],
            ],
        ] );

        return is_wp_error( $post_id ) ? 0 : intval( $post_id );
    }

    /**
     * إشعار المحرر بمقال جديد جاهز
     *
     * @param int    $post_id
     * @param string $title
     */
    private function notify_admin( int $post_id, string $title ): void {
        $email = aee_get_setting( 'notify_email', get_option( 'admin_email' ) );
        if ( empty( $email ) ) return;

        $edit_link = get_edit_post_link( $post_id, 'raw' );

        wp_mail(
            $email,
            sprintf( __( '[AI Editorial Engine] مقال جديد جاهز: %s', 'ai-editorial-engine' ), $title ),
            sprintf(
                __( "تم إنشاء مقال جديد بواسطة الذكاء الاصطناعي:\n\nالعنوان: %s\n\nرابط المراجعة:\n%s", 'ai-editorial-engine' ),
                $title,
                $edit_link
            ),
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    /**
     * الحصول على أو إنشاء تصنيف AI
     *
     * @return int|null
     */
    private function get_or_create_category(): ?int {
        $cat_name = aee_get_setting( 'default_category', 'AI Content' );
        $cat      = get_category_by_slug( sanitize_title( $cat_name ) );

        if ( $cat ) return $cat->term_id;

        $result = wp_insert_category( [
            'cat_name' => $cat_name,
            'category_nicename' => sanitize_title( $cat_name ),
        ] );

        return is_wp_error( $result ) ? null : intval( $result );
    }

    /**
     * تحديث حالة عنصر في Queue
     *
     * @param int    $id
     * @param string $status
     */
    private function update_queue_status( int $id, string $status ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aee_queue',
            [ 'status' => $status ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * تحديث حقل في Queue
     *
     * @param int    $id
     * @param string $field
     * @param mixed  $value
     */
    private function update_queue_field( int $id, string $field, $value ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aee_queue',
            [ $field => $value ],
            [ 'id'   => $id ],
            [ is_int( $value ) ? '%d' : '%s' ],
            [ '%d' ]
        );
    }

    /**
     * تسجيل فشل المعالجة
     *
     * @param int    $id
     * @param string $error
     */
    private function fail_item( int $id, string $error ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aee_queue',
            [
                'status'    => 'failed',
                'error_log' => $error,
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        error_log( "[AEE] Queue item #{$id} failed: {$error}" );
    }
}

// Hook cron actions
add_action( 'aee_process_queue', function () {
    $automation = new AEE_Automation();
    $automation->process_queue_batch( 5 );
} );

add_action( 'aee_weekly_report', function () {
    $learner = new AEE_Self_Learner();
    $learner->generate_weekly_report();
} );
