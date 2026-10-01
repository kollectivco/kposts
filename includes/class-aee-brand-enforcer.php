<?php
/**
 * Brand Enforcer — منفّذ أسلوب المجلة
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Brand_Enforcer {

    private array $settings;

    public function __construct() {
        $this->settings = get_option( 'aee_settings', [] );
    }

    /**
     * تطبيق كل قواعد العلامة التجارية
     *
     * @param string $text
     * @return string
     */
    public function enforce( string $text ): string {
        if ( empty( $text ) ) return $text;

        // 1. Remove banned words
        $text = $this->remove_banned_words( $text );

        // 2. Enforce paragraph length
        $text = $this->enforce_paragraph_length( $text );

        // 3. Enforce article length limits
        $text = $this->enforce_length_limits( $text );

        // 4. Fix common Arabic typography issues
        $text = $this->fix_typography( $text );

        // 5. Add attribution line at the end
        $text = $this->add_attribution( $text );

        return trim( $text );
    }

    /**
     * إزالة الكلمات الممنوعة
     *
     * @param string $text
     * @return string
     */
    private function remove_banned_words( string $text ): string {
        $banned = $this->settings['banned_words'] ?? '';
        if ( empty( $banned ) ) return $text;

        $words = array_filter( array_map( 'trim', explode( ',', $banned ) ) );
        foreach ( $words as $word ) {
            $text = str_ireplace( $word, '***', $text );
        }

        return $text;
    }

    /**
     * ضبط طول الفقرات
     *
     * @param string $text
     * @return string
     */
    private function enforce_paragraph_length( string $text ): string {
        $paragraphs = explode( "\n\n", $text );
        $result     = [];

        foreach ( $paragraphs as $para ) {
            $word_count = str_word_count( $para );

            // If paragraph is too long (>150 words), try to split at sentence boundary
            if ( $word_count > 150 ) {
                $sentences = preg_split( '/(?<=[.!؟])\s+/u', $para );
                $chunk     = '';
                $chunks    = [];

                foreach ( $sentences as $sentence ) {
                    $chunk_words = str_word_count( $chunk . ' ' . $sentence );
                    if ( $chunk_words > 80 && ! empty( $chunk ) ) {
                        $chunks[] = trim( $chunk );
                        $chunk    = $sentence;
                    } else {
                        $chunk .= ' ' . $sentence;
                    }
                }

                if ( ! empty( trim( $chunk ) ) ) {
                    $chunks[] = trim( $chunk );
                }

                $result = array_merge( $result, $chunks );
            } else {
                $result[] = $para;
            }
        }

        return implode( "\n\n", $result );
    }

    /**
     * ضبط طول المقال الإجمالي
     *
     * @param string $text
     * @return string
     */
    private function enforce_length_limits( string $text ): string {
        $min_words = intval( $this->settings['article_min_length'] ?? 300 );
        $max_words = intval( $this->settings['article_max_length'] ?? 1200 );

        $words = str_word_count( $text );

        if ( $words > $max_words ) {
            // Truncate at last paragraph boundary before max
            $paragraphs  = explode( "\n\n", $text );
            $result      = [];
            $total_words = 0;

            foreach ( $paragraphs as $para ) {
                $para_words = str_word_count( $para );
                if ( $total_words + $para_words > $max_words && ! empty( $result ) ) {
                    break;
                }
                $result[]    = $para;
                $total_words += $para_words;
            }

            $text = implode( "\n\n", $result );
        }

        return $text;
    }

    /**
     * إصلاح مشاكل التنسيق الطباعي العربي
     *
     * @param string $text
     * @return string
     */
    private function fix_typography( string $text ): string {
        // Fix double spaces
        $text = preg_replace( '/[ \t]{2,}/', ' ', $text );

        // Fix spacing before Arabic punctuation
        $text = preg_replace( '/\s+([،؛:.!؟])/', '$1', $text );

        // Fix common Arabic mistakes
        $corrections = [
            'إن شاء الله' => 'إن شاء الله',  // common misspelling variants
            'ان شاء الله' => 'إن شاء الله',
            'لانه'        => 'لأنه',
            'لانها'       => 'لأنها',
            'لان '        => 'لأن ',
            'إنه'         => 'أنه', // When used as 'that he'
        ];

        foreach ( $corrections as $wrong => $right ) {
            $text = str_replace( $wrong, $right, $text );
        }

        // Remove trailing spaces from lines
        $text = preg_replace( '/[ \t]+$/m', '', $text );

        // Normalize multiple newlines
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return $text;
    }

    /**
     * إضافة سطر النسب للمصدر
     *
     * @param string $text
     * @return string
     */
    private function add_attribution( string $text ): string {
        $magazine = $this->settings['magazine_name'] ?? '';
        if ( empty( $magazine ) ) return $text;

        // Check if attribution already exists
        if ( strpos( $text, '— ' . $magazine ) !== false ) return $text;

        // Only add if article doesn't already have one
        return $text . "\n\n---\n*المحتوى بتصرف — " . esc_html( $magazine ) . '*';
    }

    /**
     * فحص المقال وإرجاع قائمة المشاكل
     *
     * @param string $text
     * @return array ['issues', 'score']
     */
    public function validate_article( string $text ): array {
        $issues = [];
        $score  = 100;

        $word_count  = str_word_count( $text );
        $min_words   = intval( $this->settings['article_min_length'] ?? 300 );
        $max_words   = intval( $this->settings['article_max_length'] ?? 1200 );

        if ( $word_count < $min_words ) {
            $issues[] = "المقال قصير جداً ({$word_count} كلمة، الحد الأدنى {$min_words})";
            $score   -= 20;
        }

        if ( $word_count > $max_words ) {
            $issues[] = "المقال طويل جداً ({$word_count} كلمة، الحد الأقصى {$max_words})";
            $score   -= 10;
        }

        // Check for banned words
        $banned = $this->settings['banned_words'] ?? '';
        if ( ! empty( $banned ) ) {
            $words = array_filter( array_map( 'trim', explode( ',', $banned ) ) );
            foreach ( $words as $word ) {
                if ( stripos( $text, $word ) !== false ) {
                    $issues[] = "الكلمة الممنوعة موجودة: {$word}";
                    $score   -= 15;
                }
            }
        }

        // Check for too many short paragraphs
        $paragraphs   = array_filter( explode( "\n\n", $text ) );
        $short_paras  = array_filter( $paragraphs, fn( $p ) => str_word_count( $p ) < 20 );
        if ( count( $short_paras ) > count( $paragraphs ) / 2 ) {
            $issues[] = 'كثير من الفقرات قصيرة جداً';
            $score   -= 10;
        }

        return [
            'issues' => $issues,
            'score'  => max( 0, $score ),
        ];
    }

    /**
     * الحصول على إعدادات العلامة
     *
     * @return array
     */
    public function get_brand_settings(): array {
        return [
            'magazine_name'      => $this->settings['magazine_name'] ?? '',
            'tone'               => $this->settings['magazine_tone'] ?? 'neutral',
            'min_length'         => $this->settings['article_min_length'] ?? 300,
            'max_length'         => $this->settings['article_max_length'] ?? 1200,
            'banned_words_count' => count( array_filter( explode( ',', $this->settings['banned_words'] ?? '' ) ) ),
        ];
    }
}
