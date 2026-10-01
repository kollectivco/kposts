<?php
/**
 * Public-facing features
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) die;

class AEE_Public {
    public function init(): void {
        // Add "AI Generated" badge to posts if enabled
        add_filter( 'the_content', [ $this, 'add_ai_badge' ] );

        // Add source attribution meta tag
        add_action( 'wp_head', [ $this, 'add_source_meta' ] );
    }

    /**
     * إضافة شارة "بمساعدة AI" للمقالات المولّدة
     *
     * @param string $content
     * @return string
     */
    public function add_ai_badge( string $content ): string {
        if ( ! is_single() ) return $content;

        $post_id = get_the_ID();
        if ( ! get_post_meta( $post_id, '_aee_ai_generated', true ) ) {
            return $content;
        }

        // Only add badge if setting is enabled
        if ( ! aee_get_setting( 'show_ai_badge', 0 ) ) {
            return $content;
        }

        $badge = '<div style="background:#f0f0f1;border-radius:4px;padding:8px 12px;margin-top:20px;font-size:12px;color:#50575e;direction:rtl;">
            🤖 هذا المحتوى أُعدّ بمساعدة الذكاء الاصطناعي وتم مراجعته من قِبل فريق التحرير
        </div>';

        return $content . $badge;
    }

    /**
     * إضافة meta للمصدر الأصلي في head
     */
    public function add_source_meta(): void {
        if ( ! is_single() ) return;

        $post_id    = get_the_ID();
        $source_url = get_post_meta( $post_id, '_aee_source_url', true );
        $meta_desc  = get_post_meta( $post_id, '_aee_meta_description', true );
        $keywords   = get_post_meta( $post_id, '_aee_keywords', true );

        if ( $source_url ) {
            echo '<link rel="canonical" href="' . esc_url( $source_url ) . '">' . "\n";
        }

        if ( $meta_desc ) {
            echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
        }

        if ( $keywords ) {
            echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
        }
    }
}
