<?php
/**
 * Self-Learning Module — التطوير الذاتي بالذكاء الاصطناعي
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Self_Learner {

    /**
     * تسجيل الـ hooks
     */
    public function init(): void {
        add_action( 'save_post', [ $this, 'on_post_saved' ], 10, 3 );
        add_action( 'transition_post_status', [ $this, 'on_post_status_change' ], 10, 3 );
    }

    /**
     * عند حفظ مقال — اكتشف التغييرات التي أجراها المحرر
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function on_post_saved( int $post_id, \WP_Post $post, bool $update ): void {
        // Skip revisions, auto-drafts, non-AI posts
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( $post->post_status === 'auto-draft' ) return;
        if ( ! get_post_meta( $post_id, '_aee_ai_generated', true ) ) return;
        if ( ! $update ) return;

        // Get the original AI-generated content
        $original = get_post_meta( $post_id, '_aee_original_content', true );
        $revised  = $post->post_content;

        if ( empty( $original ) || $original === $revised ) return;

        // Calculate quality score based on similarity
        $score = $this->calculate_quality_score( $original, $revised );

        // Determine feedback type
        $feedback_type = $score >= 0.9 ? 'approved' : ( $score >= 0.5 ? 'edited' : 'rejected' );

        // Get queue_id
        $queue_id = intval( get_post_meta( $post_id, '_aee_queue_id', true ) );

        // Record feedback
        $this->record_feedback( $post_id, $feedback_type, $original, $revised, $score, $queue_id );

        // Update original to current version (for next comparison)
        update_post_meta( $post_id, '_aee_original_content', $revised );
    }

    /**
     * عند تغيير حالة المقال (draft → publish = موافقة)
     *
     * @param string   $new_status
     * @param string   $old_status
     * @param \WP_Post $post
     */
    public function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== 'publish' ) return;
        if ( ! get_post_meta( $post->ID, '_aee_ai_generated', true ) ) return;

        global $wpdb;

        // Mark as approved if published without major edits
        $wpdb->update(
            $wpdb->prefix . 'aee_feedback',
            [ 'feedback_type' => 'approved' ],
            [
                'wp_post_id'    => $post->ID,
                'feedback_type' => 'approved',
            ],
            [ '%s' ],
            [ '%d', '%s' ]
        );
    }

    /**
     * تسجيل فيدباك في DB
     *
     * @param int    $post_id
     * @param string $type
     * @param string $original
     * @param string $revised
     * @param float  $score
     * @param int    $queue_id
     */
    public function record_feedback(
        int $post_id,
        string $type,
        string $original,
        string $revised,
        float $score,
        int $queue_id = 0
    ): void {
        global $wpdb;

        // Calculate what changed
        $changes = $this->diff_summary( $original, $revised );

        // Check if feedback already exists for this post
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aee_feedback WHERE wp_post_id = %d",
                $post_id
            )
        );

        if ( $existing ) {
            $wpdb->update(
                $wpdb->prefix . 'aee_feedback',
                [
                    'revised_text'  => $revised,
                    'editor_changes'=> $changes,
                    'feedback_type' => $type,
                    'quality_score' => $score,
                ],
                [ 'wp_post_id' => $post_id ],
                [ '%s', '%s', '%s', '%f' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'aee_feedback',
                [
                    'queue_id'      => $queue_id,
                    'wp_post_id'    => $post_id,
                    'original_text' => $original,
                    'revised_text'  => $revised,
                    'editor_changes'=> $changes,
                    'feedback_type' => $type,
                    'quality_score' => $score,
                    'created_at'    => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s' ]
            );
        }
    }

    /**
     * حساب نقاط الجودة بناءً على مدى التغيير
     *
     * @param string $original
     * @param string $revised
     * @return float 0.0 - 1.0
     */
    public function calculate_quality_score( string $original, string $revised ): float {
        if ( empty( $original ) || empty( $revised ) ) return 0.5;
        if ( $original === $revised ) return 1.0;

        // Use character-level similarity
        similar_text( $original, $revised, $percent );
        return round( $percent / 100, 2 );
    }

    /**
     * ملخص الفروق
     *
     * @param string $original
     * @param string $revised
     * @return string
     */
    private function diff_summary( string $original, string $revised ): string {
        $orig_words = str_word_count( $original );
        $rev_words  = str_word_count( $revised );
        $diff_pct   = round( abs( $orig_words - $rev_words ) / max( $orig_words, 1 ) * 100 );

        $summary = [];

        if ( $rev_words < $orig_words ) {
            $summary[] = "تقليص: -{$diff_pct}% من الكلمات";
        } elseif ( $rev_words > $orig_words ) {
            $summary[] = "إضافة: +{$diff_pct}% من الكلمات";
        }

        similar_text( $original, $revised, $similarity );
        $summary[] = "التشابه: " . round( $similarity ) . "%";

        return implode( '، ', $summary );
    }

    /**
     * الحصول على insights للتحسين
     *
     * @return array
     */
    public function get_learning_insights(): array {
        global $wpdb;

        // Average quality by AI model
        $model_scores = $wpdb->get_results(
            "SELECT q.ai_model_used, AVG(f.quality_score) as avg_score, COUNT(*) as count
             FROM {$wpdb->prefix}aee_feedback f
             JOIN {$wpdb->prefix}aee_queue q ON f.queue_id = q.id
             WHERE q.ai_model_used IS NOT NULL
             GROUP BY q.ai_model_used
             ORDER BY avg_score DESC",
            ARRAY_A
        );

        // Approval rate over time
        $approval_rate = $wpdb->get_var(
            "SELECT ROUND(
                SUM(CASE WHEN feedback_type = 'approved' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1
             ) FROM {$wpdb->prefix}aee_feedback"
        );

        // Most common edit patterns
        $common_changes = $wpdb->get_results(
            "SELECT editor_changes, COUNT(*) as count
             FROM {$wpdb->prefix}aee_feedback
             WHERE editor_changes IS NOT NULL AND editor_changes != ''
             GROUP BY editor_changes
             ORDER BY count DESC
             LIMIT 5",
            ARRAY_A
        );

        return [
            'model_scores'      => $model_scores,
            'approval_rate'     => floatval( $approval_rate ?? 0 ),
            'common_changes'    => $common_changes,
            'total_processed'   => intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aee_feedback" ) ),
        ];
    }

    /**
     * تحديث System Prompt بناءً على الفيدباك
     *
     * @return bool
     */
    public function update_system_prompt_from_feedback(): bool {
        $insights = $this->get_learning_insights();

        if ( $insights['total_processed'] < 10 ) {
            return false; // Not enough data
        }

        // Get top quality articles as new samples
        global $wpdb;
        $top_articles = $wpdb->get_results(
            "SELECT revised_text FROM {$wpdb->prefix}aee_feedback
             WHERE quality_score >= 0.85 AND feedback_type = 'approved'
             ORDER BY quality_score DESC
             LIMIT 3",
            ARRAY_A
        );

        if ( empty( $top_articles ) ) return false;

        // Save as learned samples in settings
        $settings = get_option( 'aee_settings', [] );
        $samples  = '';
        foreach ( $top_articles as $i => $article ) {
            $samples .= "--- نموذج محدَّث " . ( $i + 1 ) . " ---\n";
            $samples .= mb_substr( $article['revised_text'], 0, 600 ) . "...\n\n";
        }

        $settings['learned_samples'] = $samples;
        $settings['last_learning_update'] = current_time( 'mysql' );
        update_option( 'aee_settings', $settings );

        return true;
    }

    /**
     * توليد تقرير أسبوعي
     *
     * @return string HTML report
     */
    public function generate_weekly_report(): string {
        global $wpdb;
        $insights = $this->get_learning_insights();

        $total = intval( $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aee_queue WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) );
        $published = intval( $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aee_queue WHERE status = 'published' AND fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) );

        $report = "<h2>تقرير AI Editorial Engine — الأسبوع الماضي</h2>";
        $report .= "<ul>";
        $report .= "<li>إجمالي المقالات المجلوبة: {$total}</li>";
        $report .= "<li>المقالات المنشورة: {$published}</li>";
        $report .= "<li>معدل الموافقة: {$insights['approval_rate']}%</li>";
        $report .= "</ul>";

        if ( ! empty( $insights['model_scores'] ) ) {
            $report .= "<h3>أداء النماذج:</h3><ul>";
            foreach ( $insights['model_scores'] as $model ) {
                $score = round( floatval( $model['avg_score'] ) * 100, 1 );
                $report .= "<li>{$model['ai_model_used']}: {$score}% ({$model['count']} مقال)</li>";
            }
            $report .= "</ul>";
        }

        // Email the report
        $email = aee_get_setting( 'notify_email', get_option( 'admin_email' ) );
        if ( ! empty( $email ) ) {
            wp_mail(
                $email,
                __( '[AI Editorial Engine] التقرير الأسبوعي', 'ai-editorial-engine' ),
                $report,
                [ 'Content-Type: text/html; charset=UTF-8' ]
            );
        }

        // Trigger learning update
        $this->update_system_prompt_from_feedback();

        return $report;
    }
}
