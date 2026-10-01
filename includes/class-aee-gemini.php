<?php
/**
 * Gemini API Client
 *
 * @package AI_Editorial_Engine
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AEE_Gemini {

    private string $api_key;
    private string $default_model;

    public function __construct( string $api_key = '' ) {
        $this->api_key       = $api_key ?: aee_get_api_key( 'gemini_api_key' );
        $this->default_model = 'gemini-1.5-pro';
    }

    /**
     * إرسال طلب لـ Gemini
     *
     * @param string $prompt
     * @param string $system_prompt
     * @param string $model
     * @return string|WP_Error
     */
    public function complete( string $prompt, string $system_prompt = '', string $model = '' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Gemini API key is not set.' );
        }

        $model = $model ?: $this->default_model;
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->api_key}";

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [ [ 'text' => $prompt ] ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 8192,
                'topP'            => 0.8,
            ],
            'safetySettings' => [
                [ 'category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH' ],
                [ 'category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH' ],
            ],
        ];

        if ( ! empty( $system_prompt ) ) {
            $body['systemInstruction'] = [
                'parts' => [ [ 'text' => $system_prompt ] ],
            ];
        }

        $response = wp_remote_post( $url, [
            'body'    => wp_json_encode( $body ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 90,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * تلخيص النص
     *
     * @param string $text
     * @return string|WP_Error
     */
    public function summarize( string $text ) {
        $prompt = "لخّص النص التالي بأسلوب صحفي عربي مختصر واحتفظ بالحقائق الأساسية:\n\n{$text}";
        return $this->complete( $prompt, 'أنت محلل صحفي متخصص في تلخيص المحتوى الإخباري.' );
    }

    /**
     * تحليل المحتوى واستخراج الحقائق
     *
     * @param string $text
     * @return string|WP_Error
     */
    public function analyze_content( string $text ) {
        $prompt = "حلّل النص التالي واستخرج:\n1. الحقائق الرئيسية\n2. الشخصيات المذكورة\n3. الأماكن والتواريخ\n4. الموضوع الرئيسي\n\nالنص:\n{$text}";
        return $this->complete( $prompt, 'أنت محلل محتوى صحفي متخصص.' );
    }

    /**
     * إعادة كتابة المحتوى بأسلوب صحفي
     *
     * @param string $text
     * @param string $magazine_prompt
     * @return string|WP_Error
     */
    public function rewrite_as_news( string $text, string $magazine_prompt = '' ) {
        $system = $magazine_prompt ?: 'أنت محرر صحفي محترف متخصص في كتابة الأخبار بالعربية.';
        $prompt = "أعد كتابة المحتوى التالي كمقال إخباري صحفي متكامل باللغة العربية:\n\n{$text}\n\nالمقال يجب أن يكون:\n- بأسلوب صحفي واضح ومباشر\n- يبدأ بليد قوي (خمسة أسئلة)\n- ويشمل التفاصيل المهمة\n- ويختتم بسياق أوسع";
        return $this->complete( $prompt, $system );
    }

    /**
     * اختبار الاتصال
     *
     * @return bool|string
     */
    public function test_connection() {
        $result = $this->complete( 'قل "متصل" فقط.' );
        if ( is_wp_error( $result ) ) return $result->get_error_message();
        return true;
    }

    /**
     * Parse API response
     *
     * @param mixed $response
     * @return string|WP_Error
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $message = $data['error']['message'] ?? "Gemini HTTP {$code}";
            return new WP_Error( 'gemini_error', $message );
        }

        // Check for blocking
        $finish_reason = $data['candidates'][0]['finishReason'] ?? '';
        if ( $finish_reason === 'SAFETY' ) {
            return new WP_Error( 'gemini_safety', 'Content blocked by Gemini safety filters.' );
        }

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
