<?php
/**
 * AI Orchestrator — منسّق الذكاء الاصطناعي
 * يوزع المهام بين Gemini وClaude وGPT-4o بذكاء
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_AI_Orchestrator {

    private AEE_Gemini $gemini;
    private AEE_Claude $claude;
    private AEE_OpenAI $openai;
    private array $settings;

    public function __construct() {
        $this->settings = get_option( 'aee_settings', [] );
        $this->gemini   = new AEE_Gemini();
        $this->claude   = new AEE_Claude();
        $this->openai   = new AEE_OpenAI();
    }

    /**
     * بناء System Prompt ديناميكي من إعدادات المجلة
     *
     * @return string
     */
    public function get_system_prompt(): string {
        $name     = $this->settings['magazine_name']    ?? 'مجلتنا';
        $tone     = $this->get_tone_description();
        $audience = $this->settings['target_audience']  ?? 'جمهور عربي عام';
        $voice    = $this->settings['brand_voice']      ?? '';
        $banned   = $this->settings['banned_words']     ?? '';
        $length   = $this->settings['article_max_length'] ?? 800;

        // Get sample articles from recent approved feedback
        $samples = $this->get_sample_articles();

        $prompt = "أنت محرر صحفي متخصص في مجلة «{$name}».\n\n";
        $prompt .= "=== هوية المجلة ===\n";
        $prompt .= "النبرة: {$tone}\n";
        $prompt .= "الجمهور المستهدف: {$audience}\n";

        if ( ! empty( $voice ) ) {
            $prompt .= "شخصية المجلة: {$voice}\n";
        }

        $prompt .= "\n=== قواعد الكتابة ===\n";
        $prompt .= "- اكتب باللغة العربية الفصحى المبسّطة\n";
        $prompt .= "- لا يتجاوز المقال {$length} كلمة\n";
        $prompt .= "- ابدأ بليد قوي يضم أهم المعلومات\n";
        $prompt .= "- استخدم فقرات قصيرة (3-5 جمل كحد أقصى)\n";
        $prompt .= "- اجعل الجمل سلسة وواضحة\n";
        $prompt .= "- تجنّب التكرار والحشو\n";
        $prompt .= "- انسب المعلومات لمصادرها دائماً\n";

        if ( ! empty( $banned ) ) {
            $prompt .= "- الكلمات الممنوعة (تجنّبها تماماً): {$banned}\n";
        }

        if ( ! empty( $samples ) ) {
            $prompt .= "\n=== نماذج من أسلوب المجلة ===\n";
            $prompt .= $samples;
        }

        return $prompt;
    }

    /**
     * المعالجة الرئيسية للمقال — pipeline كامل
     *
     * @param string $content   المحتوى المترجم
     * @param string $type      نوع المحتوى: news|analysis|feature
     * @return array ['title', 'content', 'meta_description', 'keywords', 'model_used', 'error']
     */
    public function process_article( string $content, string $type = 'news' ): array {
        $result = [
            'title'            => '',
            'content'          => '',
            'meta_description' => '',
            'keywords'         => '',
            'model_used'       => '',
            'error'            => null,
        ];

        $system_prompt = $this->get_system_prompt();

        // Step 1: Analyze & summarize with Gemini
        $analysis = $this->try_with_fallback( 'analyze', $content, $system_prompt );
        if ( is_wp_error( $analysis ) ) {
            $result['error'] = $analysis->get_error_message();
            // Continue with original content
            $analysis = $content;
        }

        // Step 2: Rewrite with Claude (best literary model)
        $rewritten = $this->try_with_fallback( 'rewrite', $content, $system_prompt );
        if ( is_wp_error( $rewritten ) ) {
            $result['error'] = $rewritten->get_error_message();
            $rewritten = $content; // Use original as fallback
        } else {
            $result['model_used'] = 'claude';
        }

        // Step 3: Structure & SEO with GPT-4o
        if ( ! empty( $rewritten ) ) {
            $structured = $this->try_with_fallback( 'structure', $rewritten, $system_prompt );
            if ( ! is_wp_error( $structured ) && ! empty( $structured ) ) {
                $rewritten            = $structured;
                $result['model_used'] .= '+gpt4o';
            }
        }

        $result['content'] = $rewritten;

        // Step 4: Generate SEO title (GPT-4o → Claude fallback)
        $seo_title = $this->openai->generate_seo_title( $rewritten );
        if ( is_wp_error( $seo_title ) ) {
            $seo_title = $this->claude->generate_headline( $rewritten );
        }
        if ( ! is_wp_error( $seo_title ) ) {
            // Extract first title if multiple returned
            $titles = array_filter( explode( "\n", $seo_title ) );
            $result['title'] = trim( preg_replace( '/^\d+[.\-\)]\s*/', '', reset( $titles ) ) );
        }

        // Step 5: Meta description
        $meta = $this->openai->generate_meta_description( $rewritten );
        if ( ! is_wp_error( $meta ) ) {
            $result['meta_description'] = trim( $meta );
        }

        // Step 6: Keywords
        $keywords = $this->openai->suggest_keywords( $rewritten );
        if ( ! is_wp_error( $keywords ) ) {
            $result['keywords'] = trim( $keywords );
        }

        return $result;
    }

    /**
     * إعادة كتابة بأفضل نموذج مع Fallback
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function rewrite_with_best_model( string $content ) {
        return $this->try_with_fallback( 'rewrite', $content, $this->get_system_prompt() );
    }

    /**
     * إضافة هيكل SEO
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function add_seo_structure( string $content ) {
        return $this->try_with_fallback( 'structure', $content, $this->get_system_prompt() );
    }

    /**
     * تحليل وتلخيص
     *
     * @param string $content
     * @return string|WP_Error
     */
    public function analyze_and_summarize( string $content ) {
        return $this->try_with_fallback( 'analyze', $content, $this->get_system_prompt() );
    }

    /**
     * المحاولة مع fallback chain تلقائي
     *
     * @param string $task    'rewrite'|'structure'|'analyze'
     * @param string $content
     * @param string $system_prompt
     * @return string|WP_Error
     */
    private function try_with_fallback( string $task, string $content, string $system_prompt ) {
        $primary = $this->settings['primary_ai_model'] ?? 'claude';

        // Define fallback chains per task
        $chains = [
            'rewrite'   => [ 'claude', 'gemini', 'openai' ],
            'structure' => [ 'openai', 'claude', 'gemini' ],
            'analyze'   => [ 'gemini', 'claude', 'openai' ],
        ];

        // Reorder chain based on primary preference
        $chain = $chains[ $task ] ?? [ 'gemini', 'claude', 'openai' ];
        if ( $primary !== $chain[0] && in_array( $primary, $chain, true ) ) {
            $chain = array_merge( [ $primary ], array_diff( $chain, [ $primary ] ) );
        }

        $last_error = null;

        foreach ( $chain as $model_name ) {
            $result = $this->call_model( $model_name, $task, $content, $system_prompt );

            if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                return $result;
            }

            $last_error = $result;
            error_log( "[AEE] {$model_name} failed for task '{$task}', trying next..." );
        }

        return $last_error ?? new WP_Error( 'all_models_failed', 'All AI models failed.' );
    }

    /**
     * استدعاء نموذج محدد
     *
     * @param string $model_name
     * @param string $task
     * @param string $content
     * @param string $system_prompt
     * @return string|WP_Error
     */
    private function call_model( string $model_name, string $task, string $content, string $system_prompt ) {
        switch ( $model_name ) {
            case 'gemini':
                if ( $task === 'analyze' ) return $this->gemini->analyze_content( $content );
                return $this->gemini->rewrite_as_news( $content, $system_prompt );

            case 'claude':
                if ( $task === 'rewrite' ) return $this->claude->rewrite( $content, $system_prompt );
                return $this->claude->complete( $content, $system_prompt );

            case 'openai':
                if ( $task === 'structure' ) return $this->openai->structure_article( $content );
                return $this->openai->complete( $content, $system_prompt );
        }

        return new WP_Error( 'unknown_model', "Unknown model: {$model_name}" );
    }

    /**
     * الحصول على نماذج مقالات ناجحة من DB
     *
     * @return string
     */
    private function get_sample_articles(): string {
        global $wpdb;

        $samples = $wpdb->get_results(
            "SELECT revised_text FROM {$wpdb->prefix}aee_feedback
             WHERE feedback_type = 'approved' AND quality_score >= 0.8
             ORDER BY created_at DESC LIMIT 2",
            ARRAY_A
        );

        if ( empty( $samples ) ) {
            // Use manual sample articles from settings
            return $this->settings['sample_articles'] ?? '';
        }

        $text = '';
        foreach ( $samples as $i => $sample ) {
            if ( ! empty( $sample['revised_text'] ) ) {
                $text .= "\n--- نموذج " . ( $i + 1 ) . " ---\n";
                $text .= mb_substr( $sample['revised_text'], 0, 500 ) . "...\n";
            }
        }

        return $text;
    }

    /**
     * وصف النبرة بالعربية
     *
     * @return string
     */
    private function get_tone_description(): string {
        $tone = $this->settings['magazine_tone'] ?? 'neutral';
        $map  = [
            'formal'    => 'رسمية وجادة — أسلوب المراكز البحثية والصحف الكبرى',
            'neutral'   => 'محايدة ومهنية — صحافة موضوعية متوازنة',
            'local'     => 'محلية ودافئة — قريبة من القارئ المصري',
            'satirical' => 'ساخرة وذكية — تحليل نقدي بروح مصرية',
        ];
        return $map[ $tone ] ?? $map['neutral'];
    }
}
