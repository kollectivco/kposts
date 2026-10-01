<?php
/**
 * Egyptian Arabic Style Engine — محرك العامية المصرية الصحفية
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Egyptian_Style {

    private array $dictionary = [];
    private bool  $loaded     = false;

    /**
     * تحميل المعجم من JSON والـ DB
     */
    public function load_dictionary(): void {
        if ( $this->loaded ) return;

        // Load from JSON file
        $json_file = AEE_PLUGIN_DIR . 'data/egyptian-dictionary.json';
        if ( file_exists( $json_file ) ) {
            $json = file_get_contents( $json_file );
            $data = json_decode( $json, true );
            if ( is_array( $data ) ) {
                foreach ( $data as $entry ) {
                    if ( ! empty( $entry['formal'] ) && ! empty( $entry['colloquial'] ) ) {
                        $this->dictionary[ $entry['formal'] ] = [
                            'colloquial' => $entry['colloquial'],
                            'context'    => $entry['context'] ?? 'general',
                        ];
                    }
                }
            }
        }

        // Merge/override with DB dictionary (higher priority)
        global $wpdb;
        $db_entries = $wpdb->get_results(
            "SELECT formal_word, colloquial_word, context FROM {$wpdb->prefix}aee_dictionary WHERE is_active = 1",
            ARRAY_A
        );
        foreach ( (array) $db_entries as $entry ) {
            $this->dictionary[ $entry['formal_word'] ] = [
                'colloquial' => $entry['colloquial_word'],
                'context'    => $entry['context'] ?? 'general',
            ];
        }

        // Hardcoded fallback essentials
        $this->merge_fallback_dictionary();

        $this->loaded = true;
    }

    /**
     * تطبيق الأسلوب المصري على النص
     *
     * @param string $text       النص الفصيح
     * @param float  $intensity  نسبة العامية (0.0 - 1.0)
     * @return string
     */
    public function apply_style( string $text, float $intensity = 0.3 ): string {
        $this->load_dictionary();

        if ( empty( $text ) || $intensity <= 0 ) return $text;

        $intensity = min( 1.0, max( 0.0, $intensity ) );

        // Split into sections: preserve headlines
        $sections = $this->split_by_headlines( $text );
        $result   = [];

        foreach ( $sections as $section ) {
            if ( $section['is_headline'] ) {
                // Headlines stay formal
                $result[] = $section['text'];
            } else {
                // Apply colloquial style to body paragraphs
                $result[] = $this->apply_replacements( $section['text'], $intensity );
            }
        }

        $output = implode( "\n", $result );

        // Add journalistic phrases
        $output = $this->apply_journalistic_phrases( $output );

        return $output;
    }

    /**
     * تطبيق الاستبدال على النص
     *
     * @param string $text
     * @param float  $intensity
     * @return string
     */
    private function apply_replacements( string $text, float $intensity ): string {
        if ( empty( $this->dictionary ) ) return $text;

        // Sort by length (longest first) to avoid partial replacements
        $entries = $this->dictionary;
        uksort( $entries, function( $a, $b ) {
            return mb_strlen( $b ) - mb_strlen( $a );
        } );

        foreach ( $entries as $formal => $data ) {
            // Apply based on intensity — random selection
            if ( mt_rand( 0, 100 ) / 100 > $intensity ) {
                continue;
            }

            $colloquial = $data['colloquial'];

            // Only replace full words, not substrings
            $text = $this->replace_word( $text, $formal, $colloquial );

            // Update usage count in background
            $this->increment_usage( $formal );
        }

        return $text;
    }

    /**
     * استبدال كلمة كاملة في النص (word boundary aware for Arabic)
     *
     * @param string $text
     * @param string $find
     * @param string $replace
     * @return string
     */
    private function replace_word( string $text, string $find, string $replace ): string {
        // Arabic doesn't use \b word boundaries well, use spaces/punctuation
        $pattern = '/(^|[\s،؛:.!؟"()\[\]])' . preg_quote( $find, '/' ) . '([\s،؛:.!؟"()\[\]]|$)/u';
        return preg_replace( $pattern, '$1' . $replace . '$2', $text );
    }

    /**
     * إضافة عبارات صحفية مصرية
     *
     * @param string $text
     * @return string
     */
    public function apply_journalistic_phrases( string $text ): string {
        $patterns_file = AEE_PLUGIN_DIR . 'data/style-patterns.json';
        if ( ! file_exists( $patterns_file ) ) return $text;

        $patterns = json_decode( file_get_contents( $patterns_file ), true );
        $phrases  = $patterns['egyptian_colloquial_phrases'] ?? [];
        $trans    = $patterns['transition_phrases'] ?? [];

        // Replace some formal transition phrases with Egyptian ones
        $formal_transitions   = [
            'وفي ذات السياق', 'بالمثل', 'وعلاوةً على ذلك', 'وفضلاً عن ذلك',
        ];
        $egyptian_transitions = [
            'وفي نفس الوقت', 'بنفس الطريقة', 'وعلى فكرة', 'وكمان',
        ];

        foreach ( $formal_transitions as $i => $formal ) {
            if ( isset( $egyptian_transitions[ $i ] ) ) {
                $text = str_replace( $formal, $egyptian_transitions[ $i ], $text );
            }
        }

        return $text;
    }

    /**
     * البحث عن كلمة في المعجم
     *
     * @param string $formal_word
     * @return string|null
     */
    public function get_replacement( string $formal_word ): ?string {
        $this->load_dictionary();
        return $this->dictionary[ $formal_word ]['colloquial'] ?? null;
    }

    /**
     * تقسيم النص إلى أقسام مع تمييز العناوين
     *
     * @param string $text
     * @return array
     */
    private function split_by_headlines( string $text ): array {
        $lines    = explode( "\n", $text );
        $sections = [];

        foreach ( $lines as $line ) {
            $is_headline = $this->is_headline( $line );
            $sections[]  = [
                'text'        => $line,
                'is_headline' => $is_headline,
            ];
        }

        return $sections;
    }

    /**
     * هل السطر عنوان؟
     *
     * @param string $line
     * @return bool
     */
    private function is_headline( string $line ): bool {
        $trimmed = trim( $line );
        if ( empty( $trimmed ) ) return false;

        // HTML headings
        if ( preg_match( '/^<h[1-6][^>]*>/i', $trimmed ) ) return true;

        // Markdown headings
        if ( preg_match( '/^#{1,3}\s/', $trimmed ) ) return true;

        // Short bold lines (likely headlines)
        if ( preg_match( '/^\*\*(.+)\*\*$/', $trimmed ) ) return true;

        // Short lines without punctuation (likely section headers)
        if ( mb_strlen( $trimmed ) < 80 && ! preg_match( '/[.،؟!]$/', $trimmed ) ) {
            return true;
        }

        return false;
    }

    /**
     * زيادة عداد الاستخدام في DB
     *
     * @param string $formal_word
     */
    private function increment_usage( string $formal_word ): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}aee_dictionary SET usage_count = usage_count + 1 WHERE formal_word = %s",
                $formal_word
            )
        );
    }

    /**
     * دمج المعجم الاحتياطي الأساسي
     */
    private function merge_fallback_dictionary(): void {
        $fallback = [
            'الآن'              => [ 'colloquial' => 'دلوقتي',      'context' => 'time' ],
            'أمس'               => [ 'colloquial' => 'إمبارح',      'context' => 'time' ],
            'اليوم'             => [ 'colloquial' => 'النهارده',    'context' => 'time' ],
            'غداً'              => [ 'colloquial' => 'بكره',        'context' => 'time' ],
            'هذا'               => [ 'colloquial' => 'ده',          'context' => 'pronoun' ],
            'هذه'               => [ 'colloquial' => 'دي',          'context' => 'pronoun' ],
            'ذلك'               => [ 'colloquial' => 'ده',          'context' => 'pronoun' ],
            'الذي'              => [ 'colloquial' => 'اللي',        'context' => 'pronoun' ],
            'التي'              => [ 'colloquial' => 'اللي',        'context' => 'pronoun' ],
            'لماذا'             => [ 'colloquial' => 'ليه',         'context' => 'question' ],
            'كيف'               => [ 'colloquial' => 'إزاي',       'context' => 'question' ],
            'أين'               => [ 'colloquial' => 'فين',         'context' => 'question' ],
            'متى'               => [ 'colloquial' => 'إمتى',       'context' => 'question' ],
            'من'                => [ 'colloquial' => 'مين',         'context' => 'question' ],
            'أيضاً'             => [ 'colloquial' => 'كمان',        'context' => 'conjunction' ],
            'لكن'               => [ 'colloquial' => 'بس',          'context' => 'conjunction' ],
            'لذلك'              => [ 'colloquial' => 'عشان كده',   'context' => 'conjunction' ],
            'لأن'               => [ 'colloquial' => 'عشان',        'context' => 'conjunction' ],
            'عندما'             => [ 'colloquial' => 'لما',         'context' => 'conjunction' ],
            'جداً'              => [ 'colloquial' => 'أوي',         'context' => 'adverb' ],
            'كثيراً'            => [ 'colloquial' => 'كتير',        'context' => 'adverb' ],
            'يريد'              => [ 'colloquial' => 'عايز',        'context' => 'verb' ],
            'يذهب'              => [ 'colloquial' => 'بيروح',      'context' => 'verb' ],
            'يأتي'              => [ 'colloquial' => 'بييجي',      'context' => 'verb' ],
            'في الواقع'         => [ 'colloquial' => 'بصراحة',     'context' => 'expression' ],
            'في الحقيقة'        => [ 'colloquial' => 'بصراحة',     'context' => 'expression' ],
            'في النهاية'        => [ 'colloquial' => 'في الآخر',   'context' => 'expression' ],
            'شيء'               => [ 'colloquial' => 'حاجة',       'context' => 'expression' ],
            'لا أحد'            => [ 'colloquial' => 'محدش',       'context' => 'expression' ],
            'الجميع'            => [ 'colloquial' => 'الكل',       'context' => 'expression' ],
            'بالإضافة إلى ذلك' => [ 'colloquial' => 'وفوق ده كمان', 'context' => 'conjunction' ],
        ];

        // Only add if not already in dictionary (DB/JSON takes priority)
        foreach ( $fallback as $formal => $data ) {
            if ( ! isset( $this->dictionary[ $formal ] ) ) {
                $this->dictionary[ $formal ] = $data;
            }
        }
    }

    /**
     * الحصول على إحصائيات المعجم
     *
     * @return array
     */
    public function get_stats(): array {
        $this->load_dictionary();
        return [
            'total_entries' => count( $this->dictionary ),
            'contexts'      => array_count_values( array_column( $this->dictionary, 'context' ) ),
        ];
    }
}
