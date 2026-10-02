<?php
/**
 * Translation Engine — Google Translate + DeepL
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Translator {

    private string $google_key;
    private string $deepl_key;

    public function __construct( string $google_key = '', string $deepl_key = '' ) {
        $this->google_key = $google_key ?: aee_get_api_key( 'google_translate_key' );
        $this->deepl_key  = $deepl_key ?: aee_get_api_key( 'deepl_key' );
    }

    /**
     * كشف لغة النص
     *
     * @param string $text
     * @return string Language code (e.g. 'en', 'ar')
     */
    public function detect_language( string $text ): string {
        if ( empty( $this->google_key ) ) return 'unknown';

        $text_sample = mb_substr( $text, 0, 500 );
        $url  = 'https://translation.googleapis.com/language/translate/v2/detect';
        $body = [
            'q'   => $text_sample,
            'key' => $this->google_key,
        ];

        $response = wp_remote_post( $url, [
            'body'    => wp_json_encode( $body ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return 'unknown';

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data']['detections'][0][0]['language'] ?? 'unknown';
    }

    /**
     * الترجمة باستخدام Google Translate
     *
     * @param string $text
     * @param string $target Target language code
     * @param string $source Source language code ('auto' for auto-detect)
     * @return string|WP_Error
     */
    public function translate( string $text, string $target = 'ar', string $source = 'auto' ) {
        if ( empty( $this->google_key ) ) {
            return new WP_Error( 'no_api_key', __( 'مفتاح Google Translate غير موجود', 'ai-editorial-engine' ) );
        }

        if ( empty( trim( $text ) ) ) return '';

        // Split large text into chunks (Google has 5000 char limit per request)
        $chunks = $this->split_into_chunks( $text, 4000 );
        $translated_parts = [];

        foreach ( $chunks as $chunk ) {
            $result = $this->translate_chunk( $chunk, $target, $source );
            if ( is_wp_error( $result ) ) return $result;
            $translated_parts[] = $result;
        }

        return implode( "\n\n", $translated_parts );
    }

    /**
     * ترجمة قطعة واحدة
     *
     * @param string $text
     * @param string $target
     * @param string $source
     * @return string|WP_Error
     */
    private function translate_chunk( string $text, string $target, string $source ) {
        $url  = 'https://translation.googleapis.com/language/translate/v2?key=' . $this->google_key;
        $body = [
            'q'      => $text,
            'target' => $target,
            'format' => 'text',
        ];

        if ( $source !== 'auto' ) {
            $body['source'] = $source;
        }

        $response = wp_remote_post( $url, [
            'body'    => wp_json_encode( $body ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $body_raw = wp_remote_retrieve_body( $response );
            $error    = json_decode( $body_raw, true );
            $message  = $error['error']['message'] ?? "HTTP {$code}";
            return new WP_Error( 'translate_failed', $message );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data']['translations'][0]['translatedText'] ?? '';
    }

    /**
     * الترجمة باستخدام DeepL (احتياطي)
     *
     * @param string $text
     * @param string $target
     * @return string|WP_Error
     */
    public function translate_with_deepl( string $text, string $target = 'AR' ) {
        if ( empty( $this->deepl_key ) ) {
            return new WP_Error( 'no_deepl_key', __( 'مفتاح DeepL غير موجود', 'ai-editorial-engine' ) );
        }

        $api_url = strpos( $this->deepl_key, ':fx' ) !== false
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $this->deepl_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'text'        => [ $text ],
                'target_lang' => strtoupper( $target ),
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'deepl_failed', "DeepL HTTP {$code}" );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['translations'][0]['text'] ?? '';
    }

    /**
     * ترجمة مع Fallback تلقائي
     *
     * @param string $text
     * @return string
     */
    public function translate_with_fallback( string $text ): string {
        // If already Arabic, return as-is
        $lang = $this->detect_language( $text );
        if ( $lang === 'ar' ) return $text;

        // Try Google first
        $result = $this->translate( $text );
        if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
            return $result;
        }

        // Fallback to DeepL
        $result = $this->translate_with_deepl( $text );
        if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
            return $result;
        }

        // Return original if both fail
        error_log( '[AEE] Translation failed for text starting with: ' . mb_substr( $text, 0, 100 ) );
        return $text;
    }

    /**
     * تقسيم النص إلى أجزاء
     *
     * @param string $text
     * @param int    $max_length
     * @return array
     */
    private function split_into_chunks( string $text, int $max_length ): array {
        if ( mb_strlen( $text ) <= $max_length ) {
            return [ $text ];
        }

        $chunks     = [];
        $paragraphs = explode( "\n\n", $text );
        $current    = '';

        foreach ( $paragraphs as $paragraph ) {
            if ( mb_strlen( $current ) + mb_strlen( $paragraph ) > $max_length ) {
                if ( ! empty( $current ) ) {
                    $chunks[]  = trim( $current );
                    $current   = '';
                }
                // If single paragraph is too long, split by sentences
                if ( mb_strlen( $paragraph ) > $max_length ) {
                    $sentences = preg_split( '/(?<=[.!?])\s+/u', $paragraph );
                    foreach ( $sentences as $sentence ) {
                        if ( mb_strlen( $current ) + mb_strlen( $sentence ) > $max_length ) {
                            if ( ! empty( $current ) ) {
                                $chunks[] = trim( $current );
                                $current  = '';
                            }
                        }
                        $current .= $sentence . ' ';
                    }
                    continue;
                }
            }
            $current .= $paragraph . "\n\n";
        }

        if ( ! empty( trim( $current ) ) ) {
            $chunks[] = trim( $current );
        }

        return $chunks;
    }

    /**
     * اختبار الاتصال بـ API
     *
     * @return bool|string true or error message
     */
    public function test_connection() {
        $result = $this->translate( 'Hello', 'ar' );
        if ( is_wp_error( $result ) ) {
            return $result->get_error_message();
        }
        return true;
    }
}
